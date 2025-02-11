<?php

namespace MOJComponents\Users;

use WP_User;

/**
 * Class Hooks
 *
 * Some required functionality for the user roles can only be achieved through the use of WordPress's hook API.
 * All the required filters and actions for the new user roles are defined and registered using this class.
 *
 * @package MOJDigital\UserRoles
 */
class RoleHooks
{
    /**
     * Filter editable_roles
     * Remove 'Administrator' from the list of roles if the current user is not an admin.
     *
     * @param array $roles
     *
     * @return array
     */
    public static function filterEditableRoles($roles)
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
     * Filter PreventModificationOfAdminUser
     * Map meta capabilities to capabilities
     * If someone is trying to edit or delete an admin and that user isn't an admin, don't allow it.
     *
     * @param $caps
     * @param $cap
     * @param $user_id
     * @param $args
     *
     * @return array
     */
    public static function filterPreventModificationOfAdminUser($caps, $cap, $user_id, $args)
    {
        $mapCaps = [
            'edit_user',
            'remove_user',
            'promote_user',
            'delete_user',
            'delete_users',
        ];
        if (in_array($cap, $mapCaps) &&
            isset($args[0]) &&
            self::disallowNonAdminsToEditAdmins($user_id, $args[0])
        ) {
            $caps = ['do_not_allow'];
        }

        return $caps;
    }

    /**
     * Filter allowUnfilteredHTMLforAllEditors
     * Map meta capabilities to capabilities
     * Allows all editors to write unfiltered HTML, meaning iFrames and Script tags will no longer be stripped out
     */
    public static function allowUnfilteredHTMLforAllEditors($caps, $cap, $user_id) {
        if ( 'unfiltered_html' === $cap && (user_can($user_id,'site-manager') || user_can($user_id,'editor'))) {
            return [ 'unfiltered_html' ];
        }

        return $caps;
    }

    /**
     * Determine if the current user is allowed to edit/delete/manage the specified user.
     * Non-administrators cannot edit administrators.
     *
     * @param int $actorId The user performing the action (i.e. the user performing the edit/deletion)
     * @param int $subjectId The user being acted upon (i.e. the user being edited/deleted)
     *
     * @return bool
     */
    public static function disallowNonAdminsToEditAdmins($actorId, $subjectId)
    {
        $actor = new WP_User($actorId);
        $subject = new WP_User($subjectId);

        return (
            !$actor->has_cap('administrator') &&
            $subject->has_cap('administrator')
        );
    }

    /**
     * Prevent non-administrator users from accessing the `Appearance` > `Themes` sub-menu
     */
    public static function actionRestrictAppearanceThemesMenu()
    {
        if (!current_user_can('administrator')) {
            remove_submenu_page('themes.php', 'themes.php');
        }
    }

    /**
     * Show a notification to the user if an unqualified attempt has been made to remove the homepage
     * from public view.
     */
    public static function mojCannotModifyHomepageStatus()
    {
        if (RoleUtils::isWebAdministratorOnHomepage() && get_option('MOJ_POST_STATUS_UPDATE_STOPPED', null)) {
            echo '<div class="notice notice-error is-dismissible">
                <p><strong>There was an attempt to remove the homepage from public view. This action has undesirable results and has been stopped to protect the website.</strong></p>
            </div>';

            delete_option('MOJ_POST_STATUS_UPDATE_STOPPED');
        }
    }

    /**
     * If a Web Administrator has been detected and they have accidentally opted to remove the homepage
     * from public view, switch the status of the homepage back to publish for them.
     *
     * This operation is only tied to users with a role of Web Administrator or below. Digital Webmasters
     * have full control.
     *
     * @param $new_status
     * @param $old_status
     * @param $post
     */
    public static function onHomepageStatusChange($new_status, $old_status, $post)
    {
        $is_forbidden = [
            'draft',
            'future',
            'private',
            'pending',
            'trash'
        ];

        if (in_array($new_status, $is_forbidden)) {
            if (RoleUtils::isWebAdministratorOnHomepage()) {
                wp_update_post([
                    'ID' => $post->ID,
                    'post_status' => 'publish'
                ]);

                add_option('MOJ_POST_STATUS_UPDATE_STOPPED', true);
            }
        }
    }

    public static function onHomepageStatusChangeQuickEdit($data, $post_array)
    {
        $is_forbidden = [
            'draft',
            'future',
            'private',
            'pending',
            'trash'
        ];

        if (in_array($data['post_status'], $is_forbidden)) {
            if (!current_user_can('administrator') && ($post_array['ID'] === (int)get_option('page_on_front'))) {
                add_option('MOJ_POST_STATUS_UPDATE_STOPPED', true);
                $data['post_status'] = 'publish';
            }
        }

        return $data;
    }
    
    public static function frontPageBodyCLass($classes)
    {
        global $post;

        if (gettype($post) !== 'object') {
            return $classes;
        }

        if ($post->ID === (int)get_option('page_on_front')) {
            $classes .= ' is-front-page';
        }
        return $classes;
    }

    public static function removeQuickEditLink($actions, $post)
    {
        $can_edit_post = current_user_can('administrator');
        if (!$can_edit_post && ($post->ID === (int)get_option('page_on_front'))) {
            unset($actions['inline hide-if-no-js']);
        }

        return $actions;
    }

    /**
     * Register actions and filters for the new roles.
     */
    public static function apply()
    {
        add_filter('editable_roles', __CLASS__ . '::filterEditableRoles', 10, 1);
        add_filter('map_meta_cap', __CLASS__ . '::filterPreventModificationOfAdminUser', 10, 4);
        add_filter('map_meta_cap', __CLASS__ . '::allowUnfilteredHTMLforAllEditors', 10, 3);
        add_action('admin_menu', __CLASS__ . '::actionRestrictAppearanceThemesMenu', 999);

        // stop Editors
        add_action('transition_post_status', __CLASS__ . '::onHomepageStatusChange', 10, 3);
        add_filter('wp_insert_post_data', __CLASS__ . '::onHomepageStatusChangeQuickEdit', 10, 2);
        add_action('admin_notices', __CLASS__ . '::mojCannotModifyHomepageStatus');
        add_filter('page_row_actions', __CLASS__ . '::removeQuickEditLink', 10, 2);

        // mark the front-page in admin
        add_filter('admin_body_class', __CLASS__ . '::frontPageBodyCLass', 99);
    }
}
