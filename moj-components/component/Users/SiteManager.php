<?php

namespace MOJComponents\Users;

class SiteManager
{
    /**
     * Create the role site-manager if it does not exist
     * and stop this user from editing the theme.
     */
    public static function createRole()
    {
        // Add site-manager role if required
        if (!RoleUtils::roleExists('site-manager')) {
            self::addNewRole();
        }
    }

    /**
     * Add new role Site Manager.
     * Inherit capabilities from Editor role.
     */
    private static function addNewRole()
    {
        $editor = RoleUtils::getWpRolesObject()->get_role('editor');

        // Add a new role with editor caps
        $site_manager = RoleUtils::getWpRolesObject()->add_role(
            'site-manager',
            'Site Manager',
            $editor->capabilities
        );

        // Additional capabilities which this role should have
        $additionalCapabilities = [
            'list_users',
            'create_users',
            'edit_users',
            'promote_users',
            'delete_users',
            'remove_users',
            'edit_theme_options',
            'unfiltered_html'
        ];
        foreach ($additionalCapabilities as $cap) {
            $site_manager->add_cap($cap);
        }
    }
}
