<?php

declare(strict_types=1);

namespace MOJComponents\Users;

use WP_User;

/**
 * Filters and actions for custom user roles.
 */
class RoleHooks
{
    /**
     * Remove 'Administrator' from the editable roles list for non-admins.
     *
     * @param array<string, mixed> $roles
     * @return array<string, mixed>
     */
    public static function filterEditableRoles(array $roles): array
    {
        if (isset($roles['administrator']) && !current_user_can('administrator')) {
            unset($roles['administrator']);
        }

        uasort($roles, function ($a, $b) {
            return (count($a['capabilities']) - count($b['capabilities'])) * -1;
        });

        return $roles;
    }

    /**
     * Prevent non-admins from editing or deleting admin users.
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $userId
     * @param mixed[]  $args
     * @return string[]
     */
    public static function filterPreventModificationOfAdminUser(
        array $caps,
        string $cap,
        int $userId,
        array $args
    ): array {
        $mapCaps = [
            'edit_user',
            'remove_user',
            'promote_user',
            'delete_user',
            'delete_users',
        ];

        if (
            in_array($cap, $mapCaps, true) &&
            isset($args[0]) &&
            self::disallowNonAdminsToEditAdmins($userId, $args[0])
        ) {
            $caps = ['do_not_allow'];
        }

        return $caps;
    }

    /**
     * Allow all editors to write unfiltered HTML (iFrames, Script tags etc.).
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $userId
     * @return string[]
     */
    public static function allowUnfilteredHTMLforAllEditors(array $caps, string $cap, int $userId): array
    {
        if ('unfiltered_html' === $cap && user_can($userId, 'edit_pages')) {
            return ['unfiltered_html'];
        }

        return $caps;
    }

    /**
     * Determine if a non-admin is trying to act on an admin user.
     */
    public static function disallowNonAdminsToEditAdmins(int $actorId, int $subjectId): bool
    {
        $actor   = new WP_User($actorId);
        $subject = new WP_User($subjectId);

        return !$actor->has_cap('administrator') && $subject->has_cap('administrator');
    }

    /** Prevent non-admins from accessing Appearance > Themes. */
    public static function actionRestrictAppearanceThemesMenu(): void
    {
        if (!current_user_can('administrator')) {
            remove_submenu_page('themes.php', 'themes.php');
        }
    }

    /** Show a notice if a web-admin tried to unpublish the homepage. */
    public static function mojCannotModifyHomepageStatus(): void
    {
        if (RoleUtils::isWebAdministratorOnHomepage() && get_option('MOJ_POST_STATUS_UPDATE_STOPPED', null)) {
            echo '<div class="notice notice-error is-dismissible">
                <p><strong>There was an attempt to remove the homepage from public view.
                This action has undesirable results and has been stopped to protect the website.</strong></p>
            </div>';

            delete_option('MOJ_POST_STATUS_UPDATE_STOPPED');
        }
    }

    /**
     * Revert homepage status to publish if a web-admin tries to unpublish it.
     *
     * @param string   $newStatus
     * @param string   $oldStatus
     * @param \WP_Post $post
     */
    public static function onHomepageStatusChange(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        $forbidden = ['draft', 'future', 'private', 'pending', 'trash'];

        if (in_array($newStatus, $forbidden, true) && RoleUtils::isWebAdministratorOnHomepage()) {
            wp_update_post([
                'ID'          => $post->ID,
                'post_status' => 'publish',
            ]);

            add_option('MOJ_POST_STATUS_UPDATE_STOPPED', true);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postArray
     * @return array<string, mixed>
     */
    public static function onHomepageStatusChangeQuickEdit(array $data, array $postArray): array
    {
        $forbidden = ['draft', 'future', 'private', 'pending', 'trash'];

        if (
            in_array($data['post_status'], $forbidden, true) &&
            !current_user_can('administrator') &&
            ($postArray['ID'] === (int) get_option('page_on_front'))
        ) {
            add_option('MOJ_POST_STATUS_UPDATE_STOPPED', true);
            $data['post_status'] = 'publish';
        }

        return $data;
    }

    public static function frontPageBodyCLass(?string $classes): string
    {
        global $post;

        if (gettype($post) !== 'object') {
            return $classes ?? '';
        }

        if ($post->ID === (int) get_option('page_on_front')) {
            $classes .= ' is-front-page';
        }

        return $classes ?? '';
    }

    /**
     * @param array<string, string> $actions
     * @param \WP_Post              $post
     * @return array<string, string>
     */
    public static function removeQuickEditLink(array $actions, \WP_Post $post): array
    {
        if (!current_user_can('administrator') && ($post->ID === (int) get_option('page_on_front'))) {
            unset($actions['inline hide-if-no-js']);
        }

        return $actions;
    }

    /** Register all role-related filters and actions. */
    public static function apply(): void
    {
        add_filter('editable_roles', __CLASS__ . '::filterEditableRoles', 10, 1);
        add_filter('map_meta_cap', __CLASS__ . '::filterPreventModificationOfAdminUser', 10, 4);
        add_filter('map_meta_cap', __CLASS__ . '::allowUnfilteredHTMLforAllEditors', 10, 3);
        add_action('admin_menu', __CLASS__ . '::actionRestrictAppearanceThemesMenu', 999);
        add_action('transition_post_status', __CLASS__ . '::onHomepageStatusChange', 10, 3);
        add_filter('wp_insert_post_data', __CLASS__ . '::onHomepageStatusChangeQuickEdit', 10, 2);
        add_action('admin_notices', __CLASS__ . '::mojCannotModifyHomepageStatus');
        add_filter('page_row_actions', __CLASS__ . '::removeQuickEditLink', 10, 2);
        add_filter('admin_body_class', __CLASS__ . '::frontPageBodyCLass', 99);
    }
}
