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

    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_pagecache_purge_all', 'hc_pagecache_handle_purge_all');


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

    wp_safe_redirect(wp_get_referer());
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
                <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Clear all cached pages for this site?', 'hale-components'); ?>')">
                    <?php _e('Clear cache for this site', 'hale-components'); ?>
                </button>
            </form>

        <?php endif; ?>
    </div>
    <?php
}
