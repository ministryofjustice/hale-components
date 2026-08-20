<?php
/**
 * Page cache admin UI
 *
 * Two ways to clear the OpenResty/Redis full-page cache by hand:
 *   1. Network admins: "Clear page cache on all sites" button on the Hale
 *      Components Network Dashboard (inc/parts/page-cache.php) - bumps
 *      pagecache:version for an instant network-wide flush.
 *   2. Site admins: Settings -> Cache on each site - deletes the cached
 *      entries for that site only.
 *
 * Also owns the runtime page-cache MODE (active/inactive), stored in Redis as
 * pagecache:config and read by opt/lua/pagecache/init.lua in the hale-platform
 * repo. This is the soft switch: it flips network-wide in about a second with
 * no deploy. PAGECACHE_ENABLED remains the hard switch and wins over the mode.
 * Same shape as the firewall's mode in inc/lua-firewall-controller.php.
 *
 * The purge functions themselves live in inc/pagecache-purge.php.
 */

if (! defined('ABSPATH')) {
    exit;
}

// --- Network: clear cache on ALL sites (super admins) -----------------------

/**
 * Handles the "clear page cache on all sites" form on the network dashboard.
 */
function hc_pagecache_handle_purge_all(): void
{
    check_admin_referer('hc_pagecache_purge_all');

    if (! current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $result = hc_pagecache_purge_all_sites();

    if (is_wp_error($result)) {
        set_transient('hc_pagecache_purge_all_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        set_transient('hc_pagecache_purge_all_success_' . get_current_user_id(), true, 60);
    }

    $redirect = wp_get_referer() ?: network_admin_url('admin.php?page=hale-components-network-dashboard');
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_hc_pagecache_purge_all', 'hc_pagecache_handle_purge_all');


// --- Runtime mode: active / inactive (network admins) -----------------------

/**
 * The modes the page cache understands. Keys are stored in Redis and read by
 * the Lua module; values are the labels shown in the UI.
 *
 * Must stay in sync with VALID_MODES in opt/lua/pagecache/init.lua.
 */
function hc_pagecache_get_all_modes(): array
{
    return [
        'active'   => 'Active',
        'inactive' => 'Inactive',
    ];
}

/**
 * Current mode from pagecache:config.
 *
 * Returns ['key' => ..., 'label' => ...], or false if the stored value is
 * present but not a mode we recognise, so the dashboard can offer to repair
 * it. A missing key is not an error: it means "active", the same default the
 * Lua module applies.
 *
 * @return array{key: string, label: string}|false
 */
function hc_pagecache_get_mode(): array|false
{
    $modes = hc_pagecache_get_all_modes();

    $redis = hc_pagecache_redis_connect();
    if (null === $redis) {
        return false;
    }

    try {
        $config_string = $redis->get('pagecache:config');
        $redis->close();
    } catch (\Throwable $t) {
        try { $redis->close(); } catch (\Throwable $t2) {}
        error_log('pagecache mode: ' . $t->getMessage());
        return false;
    }

    // A missing key is not corruption: it means "active", the same default the
    // Lua module applies. Anything present but not decodable to a JSON object
    // IS corruption and must be reported so the dashboard can offer to repair
    // it. Decoded as an object (not assoc) for the same reason
    // hc_pagecache_update_mode() does: assoc arrays cannot tell a JSON object
    // from a JSON array, so '["a"]' would otherwise read back as "active".
    if (false === $config_string || null === $config_string) {
        $config = new \stdClass();
    } else {
        $config = json_decode($config_string);
        if (! is_object($config)) {
            return false;
        }
    }

    $mode = $config->mode ?? 'active';

    if (! is_string($mode) || ! array_key_exists($mode, $modes)) {
        return false;
    }

    return [
        'key'   => $mode,
        'label' => $modes[$mode],
    ];
}

/**
 * Set the runtime page-cache mode.
 *
 * Reads pagecache:config, swaps in $new_mode, writes it back, and bumps
 * pagecache:version when the mode actually changed. The bump matters: without
 * it, going active -> inactive -> active would resurrect entries written
 * before the cache was switched off, which is exactly the content whoever
 * flipped the switch was trying to stop serving.
 *
 * Every nginx pod picks the change up within about a second - no restart.
 *
 * @param string $new_mode One of the keys from hc_pagecache_get_all_modes().
 * @return true|\WP_Error
 */
function hc_pagecache_update_mode(string $new_mode): true|\WP_Error
{
    if ('true' !== getenv('PAGECACHE_ENABLED')) {
        return new \WP_Error('hc_pagecache_disabled', __('The page cache is not enabled (PAGECACHE_ENABLED).', 'hale-components'));
    }

    if (! array_key_exists($new_mode, hc_pagecache_get_all_modes())) {
        return new \WP_Error('hc_pagecache_invalid_mode', __('Invalid page cache mode.', 'hale-components'));
    }

    $redis = hc_pagecache_redis_connect();
    if (null === $redis) {
        return new \WP_Error('hc_pagecache_redis', __('Could not connect to the page cache Redis database.', 'hale-components'));
    }

    try {
        // Decode as an object, not an array, so any keys added to this config
        // later survive a mode change untouched (and an empty {} re-encodes as
        // an object rather than []).
        $config_string = $redis->get('pagecache:config');
        $config        = $config_string ? json_decode($config_string) : null;
        if (! is_object($config)) {
            $config = new \stdClass();
        }

        $old_mode      = $config->mode ?? 'active';
        $config->mode  = $new_mode;

        // Bump BEFORE writing the config, not after. These are two separate
        // round trips and the second can fail on its own. Written the other way
        // round, a SET that succeeds followed by a failed INCR leaves the cache
        // active with pre-disable entries still valid, and a retry sees
        // $old_mode === $new_mode so it never bumps - the failure never heals.
        // In this order the orphan case is a bumped version with the config
        // unchanged: one harmless extra flush, and the retry still sees a real
        // mode change and completes properly.
        if ($old_mode !== $new_mode) {
            $redis->incr('pagecache:version');
        }

        $redis->set('pagecache:config', wp_json_encode($config));

        $redis->close();
        return true;
    } catch (\Throwable $t) {
        try { $redis->close(); } catch (\Throwable $t2) {}
        error_log('pagecache update mode: ' . $t->getMessage());
        return new \WP_Error('hc_pagecache_redis', __('Redis error while updating the page cache mode.', 'hale-components'));
    }
}

/**
 * Handles the "update page cache mode" form on the network dashboard.
 */
function hc_pagecache_handle_update_mode(): void
{
    check_admin_referer('hc_pagecache_update_mode');

    if (! current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $new_mode = sanitize_key($_POST['pagecache_mode'] ?? '');
    $result   = hc_pagecache_update_mode($new_mode);

    if (is_wp_error($result)) {
        set_transient('hc_pagecache_mode_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        set_transient('hc_pagecache_mode_success_' . get_current_user_id(), $new_mode, 60);
    }

    $redirect = wp_get_referer() ?: network_admin_url('admin.php?page=hale-components-network-dashboard');
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_hc_pagecache_update_mode', 'hc_pagecache_handle_update_mode');

/**
 * WP-CLI mirror of the dashboard control, for when the admin UI is the thing
 * that is broken:
 *
 *   wp hale-pagecache mode            # print the current mode
 *   wp hale-pagecache mode inactive   # turn the cache off network-wide
 */
if (defined('WP_CLI') && WP_CLI) {
    class HC_Pagecache_CLI
    {
        /**
         * Gets or sets the runtime page cache mode.
         *
         * ## OPTIONS
         *
         * [<mode>]
         * : The mode to set - active or inactive. Omit to print the current mode.
         */
        public function mode($args)
        {
            if (empty($args)) {
                $current = hc_pagecache_get_mode();
                if (false === $current) {
                    \WP_CLI::error('Could not read the page cache mode (Redis unreachable, or the stored value is invalid).');
                }
                \WP_CLI::log($current['key']);
                return;
            }

            $result = hc_pagecache_update_mode($args[0]);
            if (is_wp_error($result)) {
                \WP_CLI::error($result->get_error_message());
            }
            \WP_CLI::success("Page cache mode set to {$args[0]}.");
        }
    }
    \WP_CLI::add_command('hale-pagecache', 'HC_Pagecache_CLI');
}


// --- Row action: purge ONE page/post from the list tables --------------------

/**
 * Add a "Purge cache" row action (next to Quick Edit / Trash) on the pages,
 * posts, and CPT list tables, for items that can actually be in the page
 * cache: published and publicly viewable.
 *
 * @param array    $actions
 * @param \WP_Post $post
 * @return array
 */
function hc_pagecache_row_actions(array $actions, \WP_Post $post): array
{
    if ('true' !== getenv('PAGECACHE_ENABLED')) {
        return $actions;
    }
    if ('publish' !== $post->post_status) {
        return $actions;
    }
    if (! is_post_type_viewable(get_post_type($post))) {
        return $actions;
    }
    if (! current_user_can('edit_post', $post->ID)) {
        return $actions;
    }

    $url = wp_nonce_url(
        add_query_arg(
            [
                'action'  => 'hc_pagecache_purge_post',
                'post_id' => $post->ID,
            ],
            admin_url('admin-post.php')
        ),
        'hc_pagecache_purge_post_' . $post->ID
    );

    $actions['hc_purge_cache'] = '<a href="' . esc_url($url) . '">' . esc_html__('Purge cache', 'hale-components') . '</a>';

    return $actions;
}
add_filter('post_row_actions', 'hc_pagecache_row_actions', 10, 2);
add_filter('page_row_actions', 'hc_pagecache_row_actions', 10, 2);

/**
 * Handles the "Purge cache" row action link.
 *
 * Deletes the cached entry for the item's permalink AND for this site's
 * homepage (which lists/links the item, so it goes stale too), with a purge
 * fence via hc_pagecache_purge_paths, then redirects back to the list table.
 */
function hc_pagecache_handle_purge_post(): void
{
    $post_id = (int) ($_GET['post_id'] ?? 0);
    check_admin_referer('hc_pagecache_purge_post_' . $post_id);

    if (! current_user_can('edit_post', $post_id)) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $post = get_post($post_id);
    if (! $post || 'publish' !== $post->post_status || ! is_post_type_viewable(get_post_type($post))) {
        wp_die(__('This item cannot be in the page cache.', 'hale-components'));
    }

    $paths = [
        hc_pagecache_home_path(),                       // home lists/links the item
        hc_pagecache_path(get_permalink($post)),
    ];

    hc_pagecache_purge_paths(array_values(array_unique(array_filter($paths))));

    set_transient('hc_pagecache_purge_post_success_' . get_current_user_id(), $post->post_title, 60);

    wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . $post->post_type));
    exit;
}
add_action('admin_post_hc_pagecache_purge_post', 'hc_pagecache_handle_purge_post');

/**
 * Success notice after a row-action purge, shown once on the next admin page.
 */
function hc_pagecache_purge_post_notice(): void
{
    $title = get_transient('hc_pagecache_purge_post_success_' . get_current_user_id());
    if (false === $title) {
        return;
    }
    delete_transient('hc_pagecache_purge_post_success_' . get_current_user_id());
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html(sprintf(__('Page cache cleared for "%s".', 'hale-components'), $title)); ?></p>
    </div>
    <?php
}
add_action('admin_notices', 'hc_pagecache_purge_post_notice');


// --- Site: clear cache for ONE site (site admins) ---------------------------

/**
 * Handles the "clear cache for this site" form on Settings -> Cache.
 */
function hc_pagecache_handle_purge_site(): void
{
    check_admin_referer('hc_pagecache_purge_site');

    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $result = hc_pagecache_purge_current_site();

    if (is_wp_error($result)) {
        set_transient('hc_pagecache_purge_site_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        set_transient('hc_pagecache_purge_site_success_' . get_current_user_id(), (string) $result, 60);
    }

    $redirect = wp_get_referer() ?: admin_url('options-general.php?page=hale-cache-settings');
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_hc_pagecache_purge_site', 'hc_pagecache_handle_purge_site');

/**
 * Register Settings -> Cache on every site.
 */
function hc_pagecache_settings_page(): void
{
    add_options_page(
        __('Cache', 'hale-components'),
        __('Cache', 'hale-components'),
        'manage_options',
        'hale-cache-settings',
        'hc_pagecache_settings_page_content'
    );
}
add_action('admin_menu', 'hc_pagecache_settings_page');

/**
 * Render Settings -> Cache.
 */
function hc_pagecache_settings_page_content(): void
{
    $enabled = 'true' === getenv('PAGECACHE_ENABLED');

    // Flash messages set by the purge handler; render once, then delete.
    $purge_error   = get_transient('hc_pagecache_purge_site_error_'   . get_current_user_id());
    $purge_success = get_transient('hc_pagecache_purge_site_success_' . get_current_user_id());
    if ($purge_error)            { delete_transient('hc_pagecache_purge_site_error_'   . get_current_user_id()); }
    if (false !== $purge_success) { delete_transient('hc_pagecache_purge_site_success_' . get_current_user_id()); }
    ?>
    <div class="wrap">
        <h1><?php _e('Cache', 'hale-components'); ?></h1>

        <p><?php _e('Pages on this site are cached as full HTML in Redis and served without running WordPress. The cache is cleared automatically when content is published or updated, and entries expire on their own after a few minutes.', 'hale-components'); ?></p>

        <?php if (! $enabled) : ?>
            <div class="notice notice-warning">
                <p><?php _e('The page cache is not enabled on this environment (PAGECACHE_ENABLED), so there is nothing to clear.', 'hale-components'); ?></p>
            </div>
        <?php else : ?>

            <?php if (false !== $purge_success) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sprintf(
                        _n('Cache cleared: %d entry removed for this site.', 'Cache cleared: %d entries removed for this site.', (int) $purge_success, 'hale-components'),
                        (int) $purge_success
                    )); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($purge_error) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($purge_error); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php _e('Clear the cache for this site', 'hale-components'); ?></h2>
            <p><?php _e('Removes every cached page for this site only. Other sites on the network are not affected. Use this if a page is showing outdated content.', 'hale-components'); ?></p>
            <p><?php _e('The next visit to each page will be slightly slower while the cache rebuilds.', 'hale-components'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="hc_pagecache_purge_site">
                <?php wp_nonce_field('hc_pagecache_purge_site'); ?>
                <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Clear all cached pages for this site?', 'hale-components' ) ); ?>')">
                    <?php _e('Clear cache for this site', 'hale-components'); ?>
                </button>
            </form>

        <?php endif; ?>
    </div>
    <?php
}
