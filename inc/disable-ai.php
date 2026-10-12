<?php

defined('ABSPATH') || exit;

// Add filter to disable AI support.
add_filter('wp_supports_ai', '__return_false');

/**
 * Removes the Connectors (AL/LLM) submenu entry.
 */
add_action(
    'admin_menu',
    static function () {
        if (!wp_supports_ai()) {
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
        if (!wp_supports_ai()) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }
);

/**
 * Redirects requests that render the Connectors (AL/LLM) page by query string.
 *
 * Core renders the page for any admin request with ?page=options-connectors
 * (e.g. admin-post.php) from wp_options_connectors_intercept_render() on
 * admin_init at priority 10, so load-options-connectors.php never fires.
 * Priority 1 runs before it.
 */
add_action(
    'admin_init',
    static function () {
        if ('options-connectors' === ($_GET['page'] ?? '') && !wp_supports_ai()) {
            wp_safe_redirect(admin_url());
            exit;
        }
    },
    1
);
