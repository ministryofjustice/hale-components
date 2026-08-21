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
        try { $hc_pagecache_redis->close(); } catch (\Throwable $t2) {}
        $hc_pagecache_redis = null;
    }
}

// --- Runtime mode ----------------------------------------------------------
// false means Redis is unreachable or the stored value is not a mode we know,
// so the UI offers to repair it. A missing key is not an error: that reads
// back as 'active', the same default the Lua module applies.
$hc_pagecache_mode     = $hc_pagecache_enabled ? hc_pagecache_get_mode() : false;
$hc_pagecache_mode_key = is_array($hc_pagecache_mode) ? $hc_pagecache_mode['key'] : 'active';

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

$hc_pagecache_mode_error   = get_transient('hc_pagecache_mode_error_'   . get_current_user_id());
$hc_pagecache_mode_success = get_transient('hc_pagecache_mode_success_' . get_current_user_id());
if ($hc_pagecache_mode_error)   { delete_transient('hc_pagecache_mode_error_'   . get_current_user_id()); }
if ($hc_pagecache_mode_success) { delete_transient('hc_pagecache_mode_success_' . get_current_user_id()); }

?>

<!-- Grid layout -->
<div class="hc-dashboard-grid">
    <div class="hc-dashboard-item">
        <div class="hc-dashboard-left">
            <h4><?php _e( 'Page Cache Status', 'hale-components' ); ?></h4>
            <p><?php echo wp_kses_post( $hc_pagecache_enabled_message ); ?></p>
            <?php if ($hc_pagecache_enabled) : ?>
                <p><?php echo wp_kses_post( $hc_pagecache_connected_message ); ?></p>
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

            <?php // Mode flashes render outside the Redis guard below: an unreachable
                  // Redis is itself a reason hc_pagecache_update_mode() fails, and the
                  // transient is read+deleted unconditionally above, so gating these
                  // would swallow the error entirely. ?>
            <?php if ($hc_pagecache_mode_success) : ?>
                <p class="hc-status-on"><?php printf(
                    /* translators: %s: the mode that was just applied, active or inactive. */
                    esc_html__('Page cache mode updated to %s.', 'hale-components'),
                    esc_html($hc_pagecache_mode_success)
                ); ?></p>
            <?php endif; ?>
            <?php if ($hc_pagecache_mode_error) : ?>
                <p class="hc-status-off"><?php echo esc_html($hc_pagecache_mode_error); ?></p>
            <?php endif; ?>

            <?php if ($hc_pagecache_enabled && $hc_pagecache_redis instanceof \Redis) : ?>

                <?php if (false === $hc_pagecache_mode) : ?>
                    <p class="hc-status-off">
                        <?php _e('Warning: the stored page cache mode in Redis is invalid. Defaulting to "Active". Pick a mode below and click Update mode to repair it.', 'hale-components'); ?>
                    </p>
                <?php endif; ?>

                <p>
                    <?php _e('Inactive turns the page cache off across the whole network within a few seconds.', 'hale-components'); ?>
                    <br/>
                    <?php _e('Pages are served by WordPress directly, and nothing is cached or served from the cache until it is set back to Active.', 'hale-components'); ?>
                </p>

                <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="hc_pagecache_update_mode">
                    <?php wp_nonce_field('hc_pagecache_update_mode'); ?>
                    <select name="pagecache_mode">
                        <?php foreach (hc_pagecache_get_all_modes() as $hc_mode_key => $hc_mode_label) : ?>
                            <option
                                value="<?php echo esc_attr($hc_mode_key); ?>"
                                <?php selected($hc_mode_key, $hc_pagecache_mode_key); ?>
                            >
                                <?php echo esc_html($hc_mode_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">
                        <?php _e('Update mode', 'hale-components'); ?>
                    </button>
                </form>

            <?php endif; ?>

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
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Clear the page cache for ALL sites on the network?', 'hale-components' ) ); ?>')">
                        <?php _e('Clear page cache on all sites', 'hale-components'); ?>
                    </button>
                </form>
            <?php elseif (! $hc_pagecache_enabled) : ?>
                <p><?php _e('The page cache is disabled on this environment, so there is nothing to clear.', 'hale-components'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
