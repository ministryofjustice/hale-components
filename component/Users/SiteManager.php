<?php

declare(strict_types=1);

namespace MOJComponents\Users;

class SiteManager
{
    /** Create the site-manager role if it does not exist. */
    public static function createRole(): void
    {
        if (!RoleUtils::roleExists('site-manager')) {
            self::addNewRole();
        }
    }

    /** Add the Site Manager role, inheriting capabilities from Editor. */
    private static function addNewRole(): void
    {
        $editor = RoleUtils::getWpRolesObject()->get_role('editor');

        $siteManager = RoleUtils::getWpRolesObject()->add_role(
            'site-manager',
            'Site Manager',
            $editor->capabilities
        );

        $additionalCapabilities = [
            'list_users',
            'create_users',
            'edit_users',
            'promote_users',
            'delete_users',
            'remove_users',
            'edit_theme_options',
            'unfiltered_html',
        ];

        foreach ($additionalCapabilities as $cap) {
            $siteManager->add_cap($cap);
        }
    }
}
