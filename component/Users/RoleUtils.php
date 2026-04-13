<?php

declare(strict_types=1);

namespace MOJComponents\Users;

use WP_Roles;

class RoleUtils
{
    public static function roleExists(string $role): bool
    {
        return !is_null(self::getWpRolesObject()->get_role($role));
    }

    /**
     * Return the global $wp_roles object, creating it if it does not exist.
     */
    public static function getWpRolesObject(): WP_Roles
    {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        return $wp_roles;
    }

    public static function renameRole(string $role, string $name): void
    {
        self::getWpRolesObject()->roles[$role]['name'] = $name;
        self::getWpRolesObject()->role_names[$role]    = $name;
    }

    public static function roleName(string $role): string|false
    {
        $names = self::getWpRolesObject()->get_names();

        return $names[$role] ?? false;
    }

    public static function removeRole(string $role): bool
    {
        if (!self::roleExists($role)) {
            return false;
        }

        self::getWpRolesObject()->remove_role($role);
        return true;
    }

    /** Determine if a Web Administrator is currently editing the homepage. */
    public static function isWebAdministratorOnHomepage(): bool
    {
        global $post_ID;
        return !current_user_can('administrator') && ($post_ID === (int) get_option('page_on_front'));
    }
}
