<?php

declare(strict_types=1);

namespace MOJComponents\Comments;

/**
 * Disable comments site-wide — multisite compatible.
 *
 * Note: If enabling comments for any sites, anonymous comments are currently
 * allowed via wp-comments-post.php. Investigate spam prevention if needed.
 */
class Comments
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        // Admin actions.
        add_action('admin_init', [$this, 'removePostTypesSupport']);
        add_action('admin_init', [$this, 'removeCommentsMetaBox']);
        add_action('admin_init', [$this, 'adminPagesRedirect']);
        add_action('admin_menu', [$this, 'removeAdminMenus']);
        add_action('wp_before_admin_bar_render', [$this, 'adminBarRender']);
        add_filter('the_comments', '__return_empty_array');
        add_filter('feed_links_show_comments_feed', '__return_false');

        // Frontend.
        add_action('init', [$this, 'removeMenuFromAdminBar']);
        add_filter('get_comments_number', '__return_zero');
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', '__return_empty_array', 10, 2);
        add_filter('get_default_comment_status', fn() => 'closed');
    }

    /** Disable support for comments and trackbacks in all post types. */
    public function removePostTypesSupport(): void
    {
        $postTypes = get_post_types();
        foreach ($postTypes as $postType) {
            if (post_type_supports($postType, 'comments')) {
                remove_post_type_support($postType, 'comments');
                remove_post_type_support($postType, 'trackbacks');
            }
        }
    }

    /** Remove comments page from admin menu. */
    public function removeAdminMenus(): void
    {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    /** Redirect any user trying to access comments pages. */
    public function adminPagesRedirect(): void
    {
        global $pagenow;
        if (in_array($pagenow, ['edit-comments.php', 'options-discussion.php'], true)) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    /** Remove comments meta box from dashboard. */
    public function removeCommentsMetaBox(): void
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    /** Remove comments link from admin bar. */
    public function removeMenuFromAdminBar(): void
    {
        if (is_admin_bar_showing()) {
            remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
        }
    }

    /** Remove comments node from admin bar. */
    public function adminBarRender(): void
    {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    }
}
