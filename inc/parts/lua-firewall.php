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

                        <?php
                            // hc_firewall_get_mode() returns false if Redis has an unexpected value;
                            // fall back to 'monitor' so the dropdown still renders sensibly, but
                            // surface the corruption as an admin notice above the form so it can
                            // be repaired.
                            $hc_firewall_config_mode = hc_firewall_get_mode();
                            $hc_current_mode_key     = is_array($hc_firewall_config_mode) ? $hc_firewall_config_mode['key'] : 'monitor';
                        ?>
                        <?php if ($hc_firewall_config_mode === false) : ?>
                            <p class="hc-status-off">
                                <?php _e('Warning: the stored firewall mode in Redis is invalid or missing. Defaulting to "Monitor". Pick a mode below and click Update mode to repair it.', 'hale-components'); ?>
                            </p>
                        <?php endif; ?>

                        <form class="hc-dashboard-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hc_firewall_update_mode">
                            <?php wp_nonce_field('hc_firewall_update_mode'); ?>
                            <select name="firewall_mode">
                                <?php foreach(hc_firewall_get_all_modes() as $key => $value ) : ?>
                                    <option
                                        value="<?= esc_attr($key) ?>"
                                        <?= $key === $hc_current_mode_key ? 'selected' : '' ?>
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
                                <h4>Manual allowlist</h4>
                                <p>Comma separated IPs or IP ranges in CIDR format.</p>
                                <input type="hidden" name="action" value="hc_firewall_update_list">
                                <input type="hidden" name="list_name" value="allowlist">
                                <?php wp_nonce_field('hc_firewall_update_list'); ?>
                                <textarea name="firewall_allowlist" rows="3"><?= esc_textarea(implode(', ', $allowlist)); ?></textarea>
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
                                <h4>Manual blocklist</h4>
                                <p>Comma separated IPs or IP ranges in CIDR format.</p>
                                <input type="hidden" name="action" value="hc_firewall_update_list">
                                <input type="hidden" name="list_name" value="blocklist">
                                <?php wp_nonce_field('hc_firewall_update_list'); ?>
                                <textarea name="firewall_blocklist" rows="3"><?= esc_textarea(implode(', ', $blocklist)); ?></textarea>
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
                            <h4>Rules</h4>
                            <p>JSON formatted cost rules (advanced)</p>
                            <input type="hidden" name="action" value="hc_firewall_update_rules">
                            <?php wp_nonce_field('hc_firewall_update_rules'); ?>
                            <textarea name="firewall_rules"  rows="12"><?php echo esc_textarea(hc_firewall_get_rules()); ?></textarea>
                            <button type="submit" class="button button-primary">
                                <?php _e('Update rules', 'hale-components'); ?>
                            </button>
                        </form>

                    <?php endif; ?>
                </div>
                <div class="hc-dashboard-wide">
                    <?php if ($hc_firewall_redis_connected === true) : ?>
                        <?php
                            $hc_active_blocks    = hc_firewall_get_active_blocks();
                            $hc_current_mode     = hc_firewall_get_mode();
                            $hc_is_monitor_mode  = $hc_current_mode && $hc_current_mode['key'] === 'monitor';
                            $hc_page_slug        = 'hale-components-network-dashboard';
                            $hc_base_url         = network_admin_url('settings.php?page=' . $hc_page_slug);
                            $hc_audit_ip         = isset($_GET['audit_ip'])
                                ? (filter_var(wp_unslash($_GET['audit_ip']), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ?: null)
                                : null;
                        ?>
                        <?php
                            $hc_clear_penalties_error   = get_transient('hc_firewall_clear_penalties_error_'   . get_current_user_id());
                            $hc_clear_penalties_success = get_transient('hc_firewall_clear_penalties_success_' . get_current_user_id());
                            if ($hc_clear_penalties_error)   { delete_transient('hc_firewall_clear_penalties_error_'   . get_current_user_id()); }
                            if ($hc_clear_penalties_success) { delete_transient('hc_firewall_clear_penalties_success_' . get_current_user_id()); }
                        ?>
                        <h4><?php
                            if ($hc_is_monitor_mode) {
                                _e('IPs That Would Be Blocked (Monitor Mode)', 'hale-components');
                            } else {
                                _e('Currently Blocked IPs', 'hale-components');
                            }
                        ?></h4>
                        <?php if ($hc_clear_penalties_success) : ?>
                            <p class="hc-status-on"><?php echo esc_html(
                                is_string($hc_clear_penalties_success)
                                    ? $hc_clear_penalties_success
                                    : __('All auto-bans cleared.', 'hale-components')
                            ); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_clear_penalties_error) : ?>
                            <p class="hc-status-off"><?php echo esc_html($hc_clear_penalties_error); ?></p>
                        <?php endif; ?>
                        <?php if ($hc_is_monitor_mode) : ?>
                            <p><em><?php _e('Firewall is in monitor mode — these IPs are being tracked but not blocked.', 'hale-components'); ?></em></p>
                        <?php endif; ?>
                        <?php
                            $hc_has_auto_bans = !empty(array_filter($hc_active_blocks, fn($b) => $b['type'] === 'auto'));
                        ?>
                        <?php if ($hc_has_auto_bans) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:1em">
                                <input type="hidden" name="action" value="hc_firewall_clear_penalties">
                                <?php wp_nonce_field('hc_firewall_clear_penalties'); ?>
                                <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e('Clear all auto-bans? Manual bans will not be affected.', 'hale-components'); ?>')">
                                    <?php _e('Clear all auto-bans', 'hale-components'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (empty($hc_active_blocks)) : ?>
                            <p><?php
                                if ($hc_is_monitor_mode) {
                                    _e('No IPs would currently be blocked.', 'hale-components');
                                } else {
                                    _e('No IPs are currently blocked.', 'hale-components');
                                }
                            ?></p>
                        <?php else : ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('IP Address', 'hale-components'); ?></th>
                                        <th><?php _e('Ban Type', 'hale-components'); ?></th>
                                        <th><?php _e('Expires', 'hale-components'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hc_active_blocks as $block) : ?>
                                        <tr>
                                            <td><?php echo esc_html($block['ip']); ?></td>
                                            <td><?php
                                                if ($block['type'] === 'auto') {
                                                    echo $hc_is_monitor_mode
                                                        ? __('Auto (GCRA) — would block', 'hale-components')
                                                        : __('Auto (GCRA)', 'hale-components');
                                                } else {
                                                    echo __('Manual', 'hale-components');
                                                }
                                            ?></td>
                                            <td><?php
                                                if ($block['ttl_ms'] < 0) {
                                                    _e('Never', 'hale-components');
                                                } else {
                                                    $secs = (int) ceil($block['ttl_ms'] / 1000);
                                                    if ($secs < 60) {
                                                        echo esc_html(sprintf(_n('%d second', '%d seconds', $secs, 'hale-components'), $secs));
                                                    } elseif ($secs < 3600) {
                                                        $mins = (int) ceil($secs / 60);
                                                        echo esc_html(sprintf(_n('%d minute', '%d minutes', $mins, 'hale-components'), $mins));
                                                    } else {
                                                        $hours = (int) ceil($secs / 3600);
                                                        echo esc_html(sprintf(_n('%d hour', '%d hours', $hours, 'hale-components'), $hours));
                                                    }
                                                }
                                            ?></td>
                                            <td>
                                                <?php
                                                    $hc_is_viewing = $hc_audit_ip === $block['ip'];
                                                    $hc_show_audit_url = esc_url(add_query_arg('audit_ip', $block['ip'], $hc_base_url));
                                                    $hc_hide_audit_url = esc_url(remove_query_arg('audit_ip', $hc_base_url));
                                                ?>
                                                <?php if ($hc_is_viewing): ?>
                                                    <a href="<?php echo $hc_hide_audit_url; ?>" class="button button-small">
                                                        <?= __('Hide Audit', 'hale-components') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo $hc_show_audit_url; ?>" class="button button-small">
                                                        <?= __('View Audit', 'hale-components') ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($block['type'] === 'auto') : ?>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                                        <input type="hidden" name="action" value="hc_firewall_clear_penalty_ip">
                                                        <input type="hidden" name="ip" value="<?php echo esc_attr($block['ip']); ?>">
                                                        <?php wp_nonce_field('hc_firewall_clear_penalty_ip'); ?>
                                                        <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_attr(sprintf(__('Unblock %s?', 'hale-components'), $block['ip'])); ?>')">
                                                            <?php _e('Unblock', 'hale-components'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if ($hc_audit_ip !== null) : ?>
                            <?php
                                $hc_audit_entries   = hc_firewall_get_audit_entries($hc_audit_ip);
                            ?>
                        <?php endif; ?>

                        <h4><?php _e('Audit History Lookup', 'hale-components'); ?></h4>
                        <form method="get" action="">
                            <?php
                                // Preserve existing query params (page slug etc.) as hidden fields.
                                foreach ($_GET as $hc_k => $hc_v) {
                                    if ($hc_k === 'audit_ip' || is_array($hc_v)) {
                                        continue;
                                    }
                                    echo '<input type="hidden" name="' . esc_attr($hc_k) . '" value="' . esc_attr($hc_v) . '">';
                                }
                            ?>
                            <input
                                type="text"
                                name="audit_ip"
                                value="<?php echo esc_attr($hc_audit_ip ?? ''); ?>"
                                placeholder="<?php esc_attr_e('e.g. 1.2.3.4', 'hale-components'); ?>"
                                pattern="^(\d{1,3}\.){3}\d{1,3}$"
                                style="width:180px"
                            >
                            <button type="submit" class="button"><?php _e('Look up', 'hale-components'); ?></button>
                            <?php if ($hc_audit_ip !== null) : ?>
                                <a href="<?php echo esc_url(remove_query_arg('audit_ip')); ?>" class="button"><?php _e('Clear', 'hale-components'); ?></a>
                            <?php endif; ?>
                        </form>

                        <?php if ($hc_audit_ip !== null) : ?>
                            <h4><?php echo esc_html(sprintf(__('Audit History: %s', 'hale-components'), $hc_audit_ip)); ?></h4>
                            <p><em><?php _e('One entry is recorded per block episode — not per request. When an IP is blocked, all further requests within the cooldown window are rejected without a new audit entry. Each row below marks the start of a new block window after the previous cooldown expired.', 'hale-components'); ?></em></p>
                            <p><em><?php _e('Older entries may have been rotated out of the capped stream.', 'hale-components'); ?></em></p>
                            <?php if (empty($hc_audit_entries)) : ?>
                                <p><?php _e('No audit entries found for this IP.', 'hale-components'); ?></p>
                            <?php else : ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Time', 'hale-components'); ?></th>
                                            <th><?php _e('Mode', 'hale-components'); ?></th>
                                            <th><?php _e('Reason', 'hale-components'); ?></th>
                                            <th><?php _e('Block Trigger', 'hale-components'); ?></th>
                                            <th><?php _e('Previous Hits', 'hale-components'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($hc_audit_entries as $entry) : ?>
                                            <tr>
                                                <td><?php echo esc_html(
                                                    $entry['blocked_at']
                                                        ? wp_date('Y-m-d H:i:s', (int) ($entry['blocked_at'] / 1000))
                                                        : '—'
                                                ); ?></td>
                                                <td><?php echo esc_html($entry['mode']); ?></td>
                                                <td><?php echo esc_html($entry['reason'] ?: '—'); ?></td>
                                                <td><?php
                                                    $hc_trigger = $entry['trigger'];
                                                    if (in_array($hc_trigger, ['ip-block', 'penalty'], true)) {
                                                        echo esc_html($hc_trigger);
                                                    } else {
                                                        $hc_parts = array_map('trim', explode(',', $hc_trigger));
                                                        $hc_lines = [];
                                                        foreach ($hc_parts as $hc_part) {
                                                            // rule:req-score:name:cost  or  rule:res-score:name:cost
                                                            $hc_clean = preg_replace('/^rule:(?:req|res)-score:/', '', $hc_part);
                                                            // hc_clean is now  name:cost
                                                            $hc_colon = strrpos($hc_clean, ':');
                                                            if ($hc_colon !== false) {
                                                                $hc_name  = substr($hc_clean, 0, $hc_colon);
                                                                $hc_cost  = substr($hc_clean, $hc_colon + 1);
                                                                $hc_lines[] = esc_html($hc_name) . ' <small>(+' . esc_html($hc_cost) . ')</small>';
                                                            } else {
                                                                $hc_lines[] = esc_html($hc_clean);
                                                            }
                                                        }
                                                        echo implode('<br>', $hc_lines);
                                                    }
                                                ?></td>
                                                <td><?php
                                                    if (empty($entry['accumulated'])) {
                                                        echo '—';
                                                    } else {
                                                        $parts = [];
                                                        foreach ($entry['accumulated'] as $rule => $hits) {
                                                            $hc_rule_clean = preg_replace('/^rule:(?:req|res)-score:/', '', $rule);
                                                            $parts[] = esc_html($hc_rule_clean) . ': ' . (int) $hits;
                                                        }
                                                        echo implode('<br>', $parts);
                                                    }
                                                ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
