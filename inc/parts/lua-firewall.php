<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns true unless the FIREWALL_ENABLED env var is explicitly set to
 * the string "false". Defaults to enabled so a missing var is safe.
 */
function hc_is_firewall_enabled() {
    return getenv('FIREWALL_ENABLED') !== "false";
}

// --- Status checks ---------------------------------------------------------
$hc_firewall_enabled      = hc_is_firewall_enabled();
$hc_firewall_redis_connected = hc_firewall_redis_ping(); // true | error string
$hc_firewall_config_mode  = hc_firewall_get_mode();     // ['key'=>..., 'label'=>...]

// --- Human-readable status strings for the UI -----------------------------
$hc_firewall_enabled_message = $hc_firewall_enabled
    ? __('<span class="hc-status-on">ON</span> FIREWALL_ENABLED environment variable is true.', 'hale-components') 
    : __('<span class="hc-status-off">OFF</span> FIREWALL_ENABLED environment variable is false', 'hale-components');

$hc_firewall_connected_message = $hc_firewall_redis_connected === true
    ? __('<span class="hc-status-on">YES</span> connection established to database.', 'hale-components') 
    : __('<span class="hc-status-off">NO</span> connection established to database', 'hale-components');

// --- Flash messages (transients) -------------------------------------------
// Form handlers set a transient keyed by user ID, then redirect back here.
// Read each value and immediately delete it so it only renders once.
$hc_firewall_mode_error      = get_transient('hc_firewall_mode_error_'       . get_current_user_id());
$hc_firewall_allowlist_error = get_transient('hc_firewall_allowlist_error_'  . get_current_user_id());
$hc_firewall_blocklist_error = get_transient('hc_firewall_blocklist_error_'  . get_current_user_id());
$hc_firewall_rules_error     = get_transient('hc_firewall_rules_error_'      . get_current_user_id());

$hc_firewall_mode_success      = get_transient('hc_firewall_mode_success_'      . get_current_user_id());
$hc_firewall_allowlist_success = get_transient('hc_firewall_allowlist_success_' . get_current_user_id());
$hc_firewall_blocklist_success = get_transient('hc_firewall_blocklist_success_' . get_current_user_id());
$hc_firewall_rules_success     = get_transient('hc_firewall_rules_success_'     . get_current_user_id());

if ($hc_firewall_mode_error)      { delete_transient('hc_firewall_mode_error_'       . get_current_user_id()); }
if ($hc_firewall_allowlist_error) { delete_transient('hc_firewall_allowlist_error_'  . get_current_user_id()); }
if ($hc_firewall_blocklist_error) { delete_transient('hc_firewall_blocklist_error_'  . get_current_user_id()); }
if ($hc_firewall_rules_error)     { delete_transient('hc_firewall_rules_error_'      . get_current_user_id()); }

if ($hc_firewall_mode_success)      { delete_transient('hc_firewall_mode_success_'      . get_current_user_id()); }
if ($hc_firewall_allowlist_success) { delete_transient('hc_firewall_allowlist_success_' . get_current_user_id()); }
if ($hc_firewall_blocklist_success) { delete_transient('hc_firewall_blocklist_success_' . get_current_user_id()); }
if ($hc_firewall_rules_success)     { delete_transient('hc_firewall_rules_success_'     . get_current_user_id()); }

    ?>
        
        <!-- Grid layout -->
        <div class="hc-dashboard-grid">
            <!-- First row: WAF bypass information -->
            <div class="hc-dashboard-item">
                <div class="hc-dashboard-left">
                    <h4><?php _e( 'Lua Firewall Status', 'hale-components' ); ?></h4>
                    <p><?php echo $hc_firewall_enabled_message; ?></p>
                    <p><?php echo $hc_firewall_connected_message; ?></p>
                </div>
                <div class="hc-dashboard-right">
                    <h4><?php _e( 'Manage the Lua Firewall', 'hale-components' ); ?></h4>

                    <?php if ($hc_firewall_redis_connected === true) : ?>

                        <?php if ($hc_firewall_mode_success) : ?>
                            <p class="hc-status-on"><?php _e('Mode updated successfully.', 'hale-components'); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_firewall_mode_error) : ?>
                            <p class="hc-status-off"><?php echo esc_html($hc_firewall_mode_error); ?></p>
                        <?php endif; ?>

                        <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hc_firewall_update_mode">
                            <?php wp_nonce_field('hc_firewall_update_mode'); ?>
                            <select name="firewall_mode">
                                <?php foreach(hc_firewall_get_all_modes() as $key => $value ) : ?>
                                    <option
                                        value="<?= esc_attr($key) ?>"
                                        <?= $key === $hc_firewall_config_mode['key'] ? 'selected' : '' ?>
                                    >
                                        <?= esc_html($value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button button-primary">
                                <?php _e('Update mode', 'hale-components'); ?>
                            </button>
                        </form>


                        <?php if ($hc_firewall_allowlist_success) : ?>
                            <p class="hc-status-on"><?php _e('Allowlist updated successfully.', 'hale-components'); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_firewall_allowlist_error) : ?>
                            <p class="hc-status-off"><?php echo esc_html($hc_firewall_allowlist_error); ?></p>
                        <?php endif; ?>

                        <?php $allowlist = hc_firewall_get_allowlist(); ?>
                        <?php if(is_array($allowlist)) : ?>
                            <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="hc_firewall_update_list">
                                <input type="hidden" name="list_name" value="allowlist">
                                <?php wp_nonce_field('hc_firewall_update_list'); ?>
                                <textarea name="firewall_allowlist" rows="3"><?= implode(', ', $allowlist); ?></textarea>
                                <button type="submit" class="button button-primary">
                                    <?php _e('Update allowlist', 'hale-components'); ?>
                                </button>
                            </form>
                        <?php endif; ?>


                        <?php if ($hc_firewall_blocklist_success) : ?>
                            <p class="hc-status-on"><?php _e('Blocklist updated successfully.', 'hale-components'); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_firewall_blocklist_error) : ?>
                            <p class="hc-status-off"><?php echo esc_html($hc_firewall_blocklist_error); ?></p>
                        <?php endif; ?>

                        <?php $blocklist = hc_firewall_get_blocklist(); ?>
                        <?php if(is_array($blocklist)) : ?>
                            <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="hc_firewall_update_list">
                                <input type="hidden" name="list_name" value="blocklist">
                                <?php wp_nonce_field('hc_firewall_update_list'); ?>
                                <textarea name="firewall_blocklist" rows="3"><?= implode(', ', $blocklist); ?></textarea>
                                <button type="submit" class="button button-primary">
                                    <?php _e('Update blocklist', 'hale-components'); ?>
                                </button>
                            </form>
                        <?php endif; ?>


                        <?php if ($hc_firewall_rules_success) : ?>
                            <p class="hc-status-on"><?php _e('Rules updated successfully.', 'hale-components'); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_firewall_rules_error) : ?>
                            <p class="hc-status-off"><?php echo esc_html($hc_firewall_rules_error); ?></p>
                        <?php endif; ?>

                        <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hc_firewall_update_rules">
                            <?php wp_nonce_field('hc_firewall_update_rules'); ?>
                            <textarea name="firewall_rules"  rows="12"><?php echo hc_firewall_get_rules(); ?></textarea>
                            <button type="submit" class="button button-primary">
                                <?php _e('Update rules', 'hale-components'); ?>
                            </button>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>

