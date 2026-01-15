<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Disable comments per-site - compatible with multisite.
 *
 * Note: All functions in this file are prefixed with 'hale_comments_' to avoid conflicts.
 * 
 * Important: If enabling comments for any sites, currently anonymous comments are allowed
 * via wp-comments-post.php. Investigate and implement measures to prevent spam comments if needed.
 */

/**
 * Add actions and filters to disable comments.
 */

// Admin actions.
add_action('admin_init', 'hale_comments_remove_post_types_support');
add_action('admin_init', 'hale_comments_remove_comments_meta_box');
add_action('admin_init', 'hale_comments_admin_pages_redirect');
add_action('admin_menu', 'hale_comments_remove_admin_menus');
add_action('wp_before_admin_bar_render', 'hale_comments_admin_bar_render');
add_filter('the_comments', '__return_empty_array');
add_filter('feed_links_show_comments_feed', '__return_false');

// Frontend.
add_action('init', 'hale_comments_remove_menu_from_admin_bar');
add_filter('get_comments_number', '__return_zero');

// Close comments on the front-end.
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments.
add_filter('comments_array', '__return_empty_array', 10, 2);

// Set default state on posts.
add_filter('get_default_comment_status', fn() => 'closed');


/**
 * Functions that are called by the actions and filters above.
 */

/** Disable support for comments and trackbacks in post types. */
function hale_comments_remove_post_types_support()
{
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}

/** Remove comments page in menu. */
function hale_comments_remove_admin_menus()
{
    remove_menu_page('edit-comments.php');
    remove_submenu_page('options-general.php', 'options-discussion.php');
}

/** Redirect any user trying to access comments page. */
function hale_comments_admin_pages_redirect()
{
    global $pagenow;
    if (in_array($pagenow, ['edit-comments.php', 'options-discussion.php'])) {
        wp_safe_redirect(admin_url());
        exit;
    }
}

/** Remove comments meta box from dashboard. */
function hale_comments_remove_comments_meta_box()
{
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}

/**  Remove comments links from admin bar. */
function hale_comments_remove_menu_from_admin_bar()
{
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
}

/**  Remove comments links from admin bar. */
function hale_comments_admin_bar_render()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
}
