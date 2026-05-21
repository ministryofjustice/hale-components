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
 * Handles the "update firewall mode" form submission.
 *
 * Flow:
 *   1. Verify nonce + capability.
 *   2. Read current firewall:config from Redis.
 *   3. Swap in the new mode.
 *   4. POST the updated config to nginx /firewall/admin/validate?kind=config.
 *   5. On success write the normalised payload back to Redis and bump
 *      firewall:cache_version so all nginx pods reload within ~1 s.
 */
function hc_firewall_handle_update_mode(): void {
    check_admin_referer('hc_firewall_update_mode');

    if (!current_user_can('manage_network_options')) {
        wp_die(__('You do not have permission to do this.', 'hale-components'));
    }

    $new_mode     = sanitize_key($_POST['firewall_mode'] ?? '');
    $allowed      = array_keys(hc_firewall_get_all_modes());

    if (!in_array($new_mode, $allowed, true)) {
        wp_die(__('Invalid firewall mode.', 'hale-components'));
    }

    // Read current config as an object so empty {} values survive re-encoding,
    // then overlay the new mode before sending to nginx for validation.
    // Fall back to an empty object if the key is missing or the stored JSON is corrupt.
    $config_string  = hc_firewall_redis_get('firewall:config');
    $config         = ($config_string ? json_decode($config_string) : null) ?? new \stdClass();
    $config->mode   = $new_mode;
    $payload        = wp_json_encode($config);

    // Validate with nginx before writing — same endpoint the admin form uses.
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'https://nginx', '/');
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
        set_transient('hc_firewall_mode_error_' . get_current_user_id(), $response->get_error_message(), 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    $result = json_decode(wp_remote_retrieve_body($response));

    if (empty($result->ok)) {
        $errors = implode(', ', (array) ($result->errors ?? ['unknown error']));
        set_transient('hc_firewall_mode_error_' . get_current_user_id(), $errors, 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    // Write the normalised config (defaults applied, types coerced) and
    // increment the cluster-wide version counter so all pods reload.
    hc_firewall_redis_set('firewall:config', wp_json_encode($result->normalised));
    hc_firewall_redis_connect()->incr('firewall:cache_version');

    set_transient('hc_firewall_mode_success_' . get_current_user_id(), true, 60);
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
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'https://nginx', '/');
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
    $rules        = $rules_string ? json_decode($rules_string) : new \stdClass();
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
    $nginx_url = rtrim(getenv('NGINX_INTERNAL_URL') ?: 'https://nginx', '/');
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
