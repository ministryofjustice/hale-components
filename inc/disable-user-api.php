<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Remove the public WP REST API user endpoints to prevent anonymous user enumeration.
add_filter('rest_endpoints', 'hale_disable_public_user_api_endpoints');

/**
 * Disables the core /wp/v2/users REST API endpoints for public (unauthenticated)
 * requests, while leaving them available to logged-in users who can list users.
 *
 * This stops anonymous user enumeration via /wp-json/wp/v2/users without breaking
 * the block editor author selector, which relies on the endpoint for editors/admins.
 *
 * @param array $endpoints The registered REST API endpoints.
 * @return array The filtered endpoints.
 */
function hale_disable_public_user_api_endpoints($endpoints)
{
    // Allow users who are logged in and permitted to list users (e.g. editors, admins).
    if (is_user_logged_in() && current_user_can('list_users')) {
        return $endpoints;
    }

    // Core user routes to remove for everyone else.
    $user_routes = [
        '/wp/v2/users',
        '/wp/v2/users/(?P<id>[\d]+)',
        '/wp/v2/users/me',
    ];

    foreach ($user_routes as $route) {
        if (isset($endpoints[$route])) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
}
