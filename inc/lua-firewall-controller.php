<?php

/**
 * Returns a connected Redis client using the same env vars as the Lua
 * firewall module (REDIS_HOST, REDIS_PORT, REDIS_AUTH, REDIS_SSL).
 *
 * @return \Redis
 * @throws \RedisException on connection or auth failure.
 */
function hc_firewall_redis_connect(): \Redis {
    $host = getenv('REDIS_HOST') ?: 'redis';
    $port = (int) (getenv('REDIS_PORT') ?: 6379);
    $auth = getenv('REDIS_AUTH') ?: null;
    $ssl  = getenv('REDIS_SSL') !== 'false';

    $redis = new \Redis();

    if ($ssl) {
        $redis->connect('tls://' . $host, $port);
    } else {
        $redis->connect($host, $port);
    }

    if ($auth !== null) {
        $redis->auth($auth);
    }

    return $redis;
}

/**
 * Tests the Redis connection by sending a PING command.
 * Returns true on success, or a string error message on failure.
 */
function hc_firewall_redis_ping(): bool|string {
    try {
        $redis = hc_firewall_redis_connect();
        $pong = $redis->ping();
        return $pong === true || $pong === '+PONG';
    } catch (\RedisException $e) {
        return $e->getMessage();
    }
}

/**
 * Gets a Redis key. Returns null if the key does not exist.
 *
 * @throws \RedisException
 */
function hc_firewall_redis_get(string $key): ?string {
    $redis = hc_firewall_redis_connect();
    $value = $redis->get($key);
    return $value === false ? null : $value;
}

/**
 * Sets a Redis key. Pass a positive integer $ttl_seconds to set an expiry;
 * omit (or pass 0) for no expiry.
 *
 * @throws \RedisException
 */
function hc_firewall_redis_set(string $key, string $value, int $ttl_seconds = 0): void {
    $redis = hc_firewall_redis_connect();
    if ($ttl_seconds > 0) {
        $redis->setex($key, $ttl_seconds, $value);
    } else {
        $redis->set($key, $value);
    }
}

/**
 * Functions related to config mode
 */

/**
 * Get all modes
 */
function hc_firewall_get_all_modes (): array {
  return [
        'off' => 'Off',
        'monitor' => 'Monitor',
        'enforce' => 'Enforce',
    ];
}

/**
 * Get current mode - from the config in the Redis database
 */
function hc_firewall_get_mode(): array|false {
    $allowed_modes = hc_firewall_get_all_modes();

    $config_string = hc_firewall_redis_get('firewall:config');
    $config        = ($config_string ? json_decode($config_string, true) : null) ?? [];

    $mode_from_config = $config['mode'] ?? 'monitor';

    if (!in_array($mode_from_config, array_keys($allowed_modes), true)) {
        return false;
    }

    return [
        'key' => $mode_from_config,
        'label' => $allowed_modes[$mode_from_config]
    ];
}

/**
 * Sets the firewall mode in Redis.
 *
 * Reads the current firewall:config, swaps in $new_mode, validates the
 * resulting config via nginx /firewall/admin/validate?kind=config, then
 * writes the normalised payload back to Redis and bumps
 * firewall:cache_version so all nginx pods reload within ~1 s.
 *
 * Safe to call from WP-CLI, e.g.:
 *   wp eval 'echo is_wp_error($r = hc_firewall_update_mode("monitor")) ? $r->get_error_message() : "ok";'
 *
 * @param string $new_mode One of the keys returned by hc_firewall_get_all_modes().
 * @return true|\WP_Error  true on success, WP_Error on validation/transport failure.
 */
function hc_firewall_update_mode(string $new_mode): true|\WP_Error {
    $allowed = array_keys(hc_firewall_get_all_modes());
    if (!in_array($new_mode, $allowed, true)) {
        return new \WP_Error('hc_firewall_invalid_mode', __('Invalid firewall mode.', 'hale-components'));
    }

    // Read current config as an object so empty {} values survive re-encoding,
    // then overlay the new mode before sending to nginx for validation.
    // Fall back to an empty object if the key is missing or the stored JSON is corrupt.
    $config_string = hc_firewall_redis_get('firewall:config');
    $config        = $config_string ? json_decode($config_string) : null;
    if (!is_object($config)) {
        $config = new \stdClass();
    }
    $config->mode = $new_mode;
    $payload      = wp_json_encode($config);

    // Validate with nginx before writing — same endpoint the admin form uses.
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'http://127.0.0.1:8080', '/');
    $response  = wp_remote_post(
        $nginx_url . '/firewall/admin/validate?kind=config',
        [
            'body'      => $payload,
            'headers'   => ['Content-Type' => 'application/json'],
            'sslverify' => $nginx_url !== 'https://nginx', // self-signed cert in local
            'timeout'   => 5,
        ]
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $result = json_decode(wp_remote_retrieve_body($response));

    if (empty($result->ok)) {
        $errors = implode(', ', (array) ($result->errors ?? ['unknown error']));
        return new \WP_Error('hc_firewall_validation_failed', $errors);
    }

    // Write the normalised config (defaults applied, types coerced) and
    // increment the cluster-wide version counter so all pods reload.
    hc_firewall_redis_set('firewall:config', wp_json_encode($result->normalised));
    hc_firewall_redis_connect()->incr('firewall:cache_version');

    return true;
}

/**
 * Handles the "update firewall mode" form submission.
 *
 * Verifies nonce + capability, pulls the mode from $_POST, and delegates to
 * hc_firewall_update_mode() for the actual work. Surfaces success/failure
 * via transients so the dashboard can render an admin notice on redirect.
 */
function hc_firewall_handle_update_mode(): void {
    check_admin_referer('hc_firewall_update_mode');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $new_mode = sanitize_key($_POST['firewall_mode'] ?? '');
    $result   = hc_firewall_update_mode($new_mode);

    if (is_wp_error($result)) {
        set_transient('hc_firewall_mode_error_' . get_current_user_id(), $result->get_error_message(), 60);
    } else {
        set_transient('hc_firewall_mode_success_' . get_current_user_id(), true, 60);
    }

    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_firewall_update_mode', 'hc_firewall_handle_update_mode');


/**
 * Functions related to allowlist and blocklist
 */

/**
 * Returns the current allowlist as an array of sanitised IP/CIDR strings,
 * or an empty array if the key does not exist in Redis.
 */
function hc_firewall_get_allowlist(): array|false {
    $allowlist_string = hc_firewall_redis_get('firewall:allowlist');
    $allowlist        = $allowlist_string ? json_decode($allowlist_string, true) : [];
    return is_array($allowlist) ? $allowlist : false;
}

/**
 * Returns the current blocklist as an array of sanitised IP/CIDR strings,
 * or an empty array if the key does not exist in Redis.
 */
function hc_firewall_get_blocklist(): array|false {
    $blocklist_string = hc_firewall_redis_get('firewall:blocklist');
    $blocklist        = $blocklist_string ? json_decode($blocklist_string, true) : [];
    return is_array($blocklist) ? $blocklist : false;
}

/**
 * Handles the update-allowlist / update-blocklist form submission.
 *
 * Flow:
 *   1. Verify nonce + capability.
 *   2. Parse the comma-separated textarea value into a trimmed, filtered array.
 *   3. POST the array as JSON to nginx /firewall/admin/validate?kind={list}.
 *   4. On success write the normalised payload back to Redis and bump
 *      firewall:cache_version so all nginx pods reload within ~1 s.
 */
function hc_firewall_handle_update_list(): void {
    check_admin_referer('hc_firewall_update_list');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $list_name = sanitize_key($_POST['list_name'] ?? '');
    if(!in_array($list_name, ['allowlist', 'blocklist'])) {
        wp_die(__('Invalid list_name, must be allowlist or blocklist.', 'hale-components'));
    }

    // The textarea stores entries as a comma-separated string; split, trim
    // whitespace, and drop any blank entries before encoding as JSON.
    $list_csv            = wp_unslash($_POST["firewall_$list_name"] ?? '');
    $list_array          = explode(',', $list_csv);
    $list_array_trimmed  = array_map('trim', $list_array);
    $list_array_filtered = array_filter($list_array_trimmed);
    $list_values         = array_values($list_array_filtered);
    $payload             = wp_json_encode($list_values);

    if (false === $payload) {
        set_transient('hc_firewall_' . $list_name . '_error_' . get_current_user_id(), __('Failed to encode the firewall list as JSON.', 'hale-components'), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    // Validate with nginx before writing — same endpoint the admin form uses.
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'http://127.0.0.1:8080', '/');
    $response  = wp_remote_post(
        $nginx_url . '/firewall/admin/validate?kind=' . $list_name,
        [
            'body'      => $payload,
            'headers'   => ['Content-Type' => 'application/json'],
            'sslverify' => $nginx_url !== 'https://nginx', // self-signed cert in local
            'timeout'   => 5,
        ]
    );

    if (is_wp_error($response)) {
        set_transient('hc_firewall_' . $list_name . '_error_' . get_current_user_id(), $response->get_error_message(), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $result = json_decode(wp_remote_retrieve_body($response));

    if (empty($result->ok)) {
        $errors = implode(', ', (array) ($result->errors ?? ['unknown error']));
        set_transient('hc_firewall_' . $list_name . '_error_' . get_current_user_id(), $errors, 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    // Write the normalised allowlist or blocklist and increment the cluster-wide version
    // counter so all nginx pods reload within ~1 s.
    hc_firewall_redis_set('firewall:' . $list_name, wp_json_encode($result->normalised));
    hc_firewall_redis_connect()->incr('firewall:cache_version');

    set_transient('hc_firewall_' . $list_name . '_success_' . get_current_user_id(), true, 60);
    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_firewall_update_list', 'hc_firewall_handle_update_list');





/**
 * Functions related to rules
 */

/**
 * Returns the current firewall rules as a pretty-printed JSON string, ready
 * to display in the admin textarea. Decoded without the associative flag so
 * that empty JSON objects ({}) survive the round-trip and are not collapsed
 * to arrays ([]).
 */
function hc_firewall_get_rules(): string|false {
    $rules_string = hc_firewall_redis_get('firewall:rules');
    $rules        = $rules_string ? json_decode($rules_string) : null;
    if (!is_object($rules)) {
        $rules = new \stdClass();
    }
    return json_encode($rules, JSON_PRETTY_PRINT);
}


/**
 * Handles the update-rules form submission.
 *
 * Flow:
 *   1. Verify nonce + capability.
 *   2. Strip WordPress magic-quote slashes from the raw textarea payload.
 *   3. POST the JSON to nginx /firewall/admin/validate?kind=rules.
 *   4. On success write the normalised payload back to Redis and bump
 *      firewall:cache_version so all nginx pods reload within ~1 s.
 */
function hc_firewall_handle_update_rules(): void {
    check_admin_referer('hc_firewall_update_rules');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    // wp_unslash strips the magic quotes WordPress adds to all $_POST values.
    $payload = wp_unslash($_POST['firewall_rules'] ?? '');

    // Validate with nginx before writing — same endpoint the admin form uses.
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'http://127.0.0.1:8080', '/');
    $response  = wp_remote_post(
        $nginx_url . '/firewall/admin/validate?kind=rules',
        [
            'body'      => $payload,
            'headers'   => ['Content-Type' => 'application/json'],
            'sslverify' => $nginx_url !== 'https://nginx', // self-signed cert in local
            'timeout'   => 5,
        ]
    );

    if (is_wp_error($response)) {
        set_transient('hc_firewall_rules_error_' . get_current_user_id(), $response->get_error_message(), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $result = json_decode(wp_remote_retrieve_body($response));

    if (empty($result->ok)) {
        $errors = implode(', ', (array) ($result->errors ?? ['unknown error']));
        set_transient('hc_firewall_rules_error_' . get_current_user_id(), $errors, 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    // Write the normalised rules and increment the cluster-wide version
    // counter so all nginx pods reload within ~1 s.
    hc_firewall_redis_set('firewall:rules', wp_json_encode($result->normalised));
    hc_firewall_redis_connect()->incr('firewall:cache_version');

    set_transient('hc_firewall_rules_success_' . get_current_user_id(), true, 60);
    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_firewall_update_rules', 'hc_firewall_handle_update_rules');


/**
 * Handles the "clear all auto-bans" form submission.
 *
 * Calls GET /firewall/clear-penalties on the internal nginx endpoint, which
 * deletes every firewall:block:{ip} key with value "gcra" and increments
 * firewall:penalties_version so all pods flush their blocked_cache within ~1 s.
 * Manual bans (value "1") are untouched.
 */
function hc_firewall_handle_clear_penalties(): void {
    check_admin_referer('hc_firewall_clear_penalties');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'http://127.0.0.1:8080', '/');
    $response  = wp_remote_get(
        $nginx_url . '/firewall/clear-penalties',
        [
            'sslverify' => $nginx_url !== 'https://nginx',
            'timeout'   => 5,
        ]
    );

    if (is_wp_error($response)) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(), $response->get_error_message(), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(), sprintf(__('Unexpected response: %d', 'hale-components'), $code), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    set_transient('hc_firewall_clear_penalties_success_' . get_current_user_id(), true, 60);
    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_firewall_clear_penalties', 'hc_firewall_handle_clear_penalties');


/**
 * Handles the per-row "Unblock" form submission.
 *
 * Calls GET /firewall/clear-penalties?ip=<ip> on the internal nginx endpoint,
 * which deletes the firewall:block:{ip}, firewall:gcra:{ip}, and
 * firewall:gcra:{ip}:breakdown keys (only for auto-bans) and bumps
 * firewall:penalties_version. The nginx endpoint refuses to clear manual
 * bans (returns 409); we surface that as an error to the admin.
 */
function hc_firewall_handle_clear_penalty_ip(): void {
    check_admin_referer('hc_firewall_clear_penalty_ip');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $ip = filter_var(wp_unslash($_POST['ip'] ?? ''), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if (!$ip) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(), __('Invalid IP address.', 'hale-components'), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'http://127.0.0.1:8080', '/');
    $response  = wp_remote_get(
        $nginx_url . '/firewall/clear-penalties?ip=' . rawurlencode($ip),
        [
            'sslverify' => $nginx_url !== 'https://nginx',
            'timeout'   => 5,
        ]
    );

    if (is_wp_error($response)) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(), $response->get_error_message(), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        set_transient('hc_firewall_clear_penalties_success_' . get_current_user_id(),
            sprintf(__('Cleared auto-ban for %s.', 'hale-components'), $ip), 60);
    } elseif ($code === 404) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(),
            sprintf(__('%s is no longer banned.', 'hale-components'), $ip), 60);
    } elseif ($code === 409) {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(),
            sprintf(__('%s is a manual ban — remove it from the blocklist instead.', 'hale-components'), $ip), 60);
    } else {
        set_transient('hc_firewall_clear_penalties_error_' . get_current_user_id(),
            sprintf(__('Unexpected response: %d', 'hale-components'), $code), 60);
    }

    wp_safe_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_hc_firewall_clear_penalty_ip', 'hc_firewall_handle_clear_penalty_ip');


/**
 * Returns all currently active firewall blocks from Redis.
 *
 * Scans firewall:block:{ip} keys and fetches their value and remaining TTL in
 * a single pipeline. Each entry in the returned array has:
 *   - 'ip'      string  The blocked IP address.
 *   - 'type'    string  'auto' (value "gcra") or 'manual' (value "1").
 *   - 'ttl_ms'  int     Remaining TTL in milliseconds; -1 means no expiry.
 *
 * Returns an empty array if there are no active blocks or on Redis error.
 */
function hc_firewall_get_active_blocks(): array {
    try {
        $redis = hc_firewall_redis_connect();

        // SCAN in batches to avoid blocking Redis with KEYS.
        $iterator = null;
        $keys     = [];
        do {
            $batch = $redis->scan($iterator, 'firewall:block:*', 100);
            if ($batch !== false) {
                $keys = array_merge($keys, $batch);
            }
        } while ($iterator !== 0);

        if (empty($keys)) {
            return [];
        }

        // Pipeline GET + PTTL for each key to minimise round-trips.
        $redis->multi(\Redis::PIPELINE);
        foreach ($keys as $key) {
            $redis->get($key);
            $redis->pttl($key);
        }
        $responses = $redis->exec();

        $blocks = [];
        foreach ($keys as $i => $key) {
            $value  = $responses[$i * 2];
            $pttl   = $responses[$i * 2 + 1];
            $ip     = substr($key, strlen('firewall:block:'));

            // Skip keys with unexpected values.
            if ($value === false || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            $blocks[] = [
                'ip'     => $ip,
                'type'   => ($value === 'gcra') ? 'auto' : 'manual',
                'ttl_ms' => (int) $pttl,
            ];
        }

        // Sort by IP for a stable display order.
        usort($blocks, fn($a, $b) => strcmp($a['ip'], $b['ip']));

        return $blocks;
    } catch (\RedisException $e) {
        return [];
    }
}

/**
 * Returns audit stream entries for a specific IP address.
 *
 * Paginates through firewall:audit in batches (newest first) until either
 * $max_matches entries for $ip are found or the stream is exhausted. Using
 * batches avoids a single large xRevRange call while still reading the full
 * stream when needed.
 *
 * Each entry in the returned array has:
 *   - 'id'          string   Redis stream entry ID (timestamp-sequence).
 *   - 'blocked_at'  int      Ms epoch when the block was recorded.
 *   - 'cost'        int      GCRA cost charged on this request.
 *   - 'mode'        string   'enforce' or 'monitor'.
 *   - 'trigger'     string   Comma-separated rule identifiers.
 *   - 'reason'      string   'gcra', 'block', 'penalty', or '' for res-phase entries.
 *   - 'accumulated' array    Decoded per-rule hit counts, keyed by rule name.
 *
 *
 *
 * @param string $ip          A validated IPv4 address.
 * @param int    $max_matches Stop after collecting this many matching entries.
 * @param int    $batch_size  Number of stream entries to read per round-trip.
 */
function hc_firewall_get_audit_entries(string $ip, int $max_matches = 100, int $batch_size = 200): array {
    try {
        $redis   = hc_firewall_redis_connect();
        $cursor  = '+';
        $entries = [];

        while (true) {
            $batch = $redis->xRevRange('firewall:audit', $cursor, '-', $batch_size);

            if (empty($batch)) {
                break; // stream exhausted
            }

            foreach ($batch as $id => $fields) {
                if (($fields['ip'] ?? '') === $ip) {
                    $accumulated_raw = $fields['accumulated'] ?? '';
                    $accumulated     = ($accumulated_raw !== '' && $accumulated_raw !== '""')
                        ? json_decode($accumulated_raw, true) ?? []
                        : [];

                    $entries[] = [
                        'id'          => $id,
                        'blocked_at'  => (int) ($fields['blocked_at'] ?? 0),
                        'cost'        => (int) ($fields['cost'] ?? 0),
                        'mode'        => $fields['mode'] ?? '',
                        'trigger'     => $fields['trigger'] ?? '',
                        'reason'      => $fields['reason'] ?? '',
                        'accumulated' => $accumulated,
                    ];

                    if (count($entries) >= $max_matches) {
                        return $entries; // found enough — stop early
                    }
                }
            }

            // Advance cursor to just before the oldest ID in this batch.
            // Stream IDs are "ms-seq"; decrementing the sequence by 1 gives
            // the exclusive upper bound for the next page.
            $last_id = array_key_last($batch);
            [$ms, $seq] = explode('-', $last_id);
            if ((int) $seq === 0) {
                // Sequence is 0 — step back one millisecond to avoid an
                // infinite loop when multiple entries share the same ms.
                $cursor = ((int) $ms - 1) . '-' . PHP_INT_MAX;
            } else {
                $cursor = $ms . '-' . ((int) $seq - 1);
            }

            // If this batch was smaller than requested, we've hit the end.
            if (count($batch) < $batch_size) {
                break;
            }
        }

        return $entries;
    } catch (\RedisException $e) {
        return [];
    }
}
