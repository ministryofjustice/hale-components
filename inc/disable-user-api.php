<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Remove the public WP REST API user endpoints to prevent anonymous user enumeration.
add_filter('rest_endpoints', 'hale_disable_public_user_api_endpoints');

/**
 * Disables the core /wp/v2/users REST API endpoints for public (unauthenticated)
 * requests, while leaving them available to logged-in users who can edit posts.
 *
 * This stops anonymous user enumeration via /wp-json/wp/v2/users without breaking
 * the block editor, which reads the endpoint for the author of the post it is
 * editing. That needs edit_posts (contributors and up), not list_users, which
 * only administrators have.
 *
 * @param array $endpoints The registered REST API endpoints.
 * @return array The filtered endpoints.
 */
function hale_disable_public_user_api_endpoints($endpoints)
{
    // Allow logged-in users who can edit posts (e.g. contributors, authors, editors, admins).
    if (is_user_logged_in() && current_user_can('edit_posts')) {
        return $endpoints;
    }

    // Core user routes to remove for everyone else.
    // '/wp/v2/users/me' is not disabled here (it has it's own auth checks).
    $user_routes = [
        '/wp/v2/users',
        '/wp/v2/users/(?P<id>[\d]+)',
    ];

    foreach ($user_routes as $route) {
        if (isset($endpoints[$route])) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
}
