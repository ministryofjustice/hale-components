<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Stop the Auth0 plugin (4.x) writing to the options table on every request.
add_filter('pre_update_option_auth0_db_version', 'hale_auth0_db_version_noop', 10, 2);

/**
 * Auth0 4.x runs WP_Auth0_DBManager::install_db() on plugins_loaded, which calls
 * update_option('auth0_db_version', <int>) even when already up to date. The stored
 * value is read back as a string, so core's strict no-op check never matches and a
 * SELECT autoload + UPDATE hit the database on every uncached request.
 *
 * Returning the old value when only the type differs lets update_option() return early.
 * Real version changes still pass through.
 *
 * @param mixed $value     The new option value.
 * @param mixed $old_value The old option value.
 * @return mixed The value to save.
 */
function hale_auth0_db_version_noop($value, $old_value)
{
    return (string) $value === (string) $old_value ? $old_value : $value;
}
