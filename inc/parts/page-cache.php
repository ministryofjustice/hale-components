<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Status checks ---------------------------------------------------------
$hc_pagecache_enabled = 'true' === getenv('PAGECACHE_ENABLED');
$hc_pagecache_redis   = $hc_pagecache_enabled ? hc_pagecache_redis_connect() : null;
$hc_pagecache_ttl     = (int) (getenv('PAGECACHE_TTL') ?: 300);
$hc_pagecache_version = null;
if ($hc_pagecache_redis instanceof \Redis) {
    try {
        $hc_pagecache_version = (int) ($hc_pagecache_redis->get('pagecache:version') ?: 0);
        $hc_pagecache_redis->close();
    } catch (\Throwable $t) {
        $hc_pagecache_redis = null;
    }
}

// --- Human-readable status strings for the UI -----------------------------
$hc_pagecache_enabled_message = $hc_pagecache_enabled
    ? __('<span class="hc-status-on">ON</span> PAGECACHE_ENABLED environment variable is true.', 'hale-components')
    : __('<span class="hc-status-off">OFF</span> PAGECACHE_ENABLED environment variable is not true.', 'hale-components');

$hc_pagecache_connected_message = $hc_pagecache_redis instanceof \Redis
    ? __('<span class="hc-status-on">YES</span> connection established to database.', 'hale-components')
    : __('<span class="hc-status-off">NO</span> connection established to database.', 'hale-components');

// --- Flash messages (transients) -------------------------------------------
// The purge handler sets a transient keyed by user ID, then redirects back
// here. Read each value and immediately delete it so it only renders once.
$hc_pagecache_purge_error   = get_transient('hc_pagecache_purge_all_error_'   . get_current_user_id());
$hc_pagecache_purge_success = get_transient('hc_pagecache_purge_all_success_' . get_current_user_id());
if ($hc_pagecache_purge_error)   { delete_transient('hc_pagecache_purge_all_error_'   . get_current_user_id()); }
if ($hc_pagecache_purge_success) { delete_transient('hc_pagecache_purge_all_success_' . get_current_user_id()); }

?>

<!-- Grid layout -->
<div class="hc-dashboard-grid">
    <div class="hc-dashboard-item">
        <div class="hc-dashboard-left">
            <h4><?php _e( 'Page Cache Status', 'hale-components' ); ?></h4>
            <p><?php echo $hc_pagecache_enabled_message; ?></p>
            <?php if ($hc_pagecache_enabled) : ?>
                <p><?php echo $hc_pagecache_connected_message; ?></p>
                <?php if (null !== $hc_pagecache_version) : ?>
                    <p><?php echo esc_html(sprintf(
                        __('Cache version: %1$d. Entries expire after %2$d seconds.', 'hale-components'),
                        $hc_pagecache_version,
                        $hc_pagecache_ttl
                    )); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="hc-dashboard-right">
            <h4><?php _e( 'Manage the Page Cache', 'hale-components' ); ?></h4>

            <?php if ($hc_pagecache_purge_success) : ?>
                <p class="hc-status-on"><?php _e('Page cache cleared on all sites.', 'hale-components'); ?></p>
            <?php endif; ?>
            <?php if ($hc_pagecache_purge_error) : ?>
                <p class="hc-status-off"><?php echo esc_html($hc_pagecache_purge_error); ?></p>
            <?php endif; ?>

            <?php if ($hc_pagecache_enabled && $hc_pagecache_redis instanceof \Redis) : ?>
                <p><?php _e('Instantly invalidates every cached page on every site in the network. Pages re-cache on their next visit.', 'hale-components'); ?></p>
                <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="hc_pagecache_purge_all">
                    <?php wp_nonce_field('hc_pagecache_purge_all'); ?>
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Clear the page cache for ALL sites on the network?', 'hale-components'); ?>')">
                        <?php _e('Clear page cache on all sites', 'hale-components'); ?>
                    </button>
                </form>
            <?php elseif (! $hc_pagecache_enabled) : ?>
                <p><?php _e('The page cache is disabled on this environment, so there is nothing to clear.', 'hale-components'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
