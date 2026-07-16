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
 * Deletes the cached entry for the item's permalink (with a purge fence, via
 * hc_pagecache_purge_paths) and redirects back to the list table.
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

    hc_pagecache_purge_paths([hc_pagecache_path(get_permalink($post))]);

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
