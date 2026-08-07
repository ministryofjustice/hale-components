<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Add filter to disable AI support.
add_filter('wp_supports_ai', '__return_false');

/**
 * Removes the Connectors (AL/LLM) submenu entry.
 */
add_action(
    'admin_menu',
    static function () {
        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            remove_submenu_page('options-general.php', 'options-connectors.php');
        }
    },
    999
);

/**
 * Redirects direct visits to the Connectors (AL/LLM) page.
 */
add_action(
    'load-options-connectors.php',
    static function () {
        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }
);
