<?php

namespace MOJComponents\Users;

use WP_Roles;

class RoleUtils
{
    /**
     * @var array
     */
    private static $debug;

    /**
     * Check if role exists
     *
     * @param $role
     *
     * @return bool
     */
    public static function roleExists($role)
    {
        $obj = self::getWpRolesObject()->get_role($role);

        return ! is_null($obj);
    }

    /**
     * Return the existing global $wp_roles object if it exists.
     * If not, create it.
     *
     * @return WP_Roles object
     */
    public static function getWpRolesObject()
    {
        global $wp_roles;
        if ( ! isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        return $wp_roles;
    }

    /**
     * Rename a user role
     *
     * @param $role
     * @param $name
     */
    public static function renameRole($role, $name)
    {
        self::getWpRolesObject()->roles[$role]['name'] = $name;
        self::getWpRolesObject()->role_names[$role]    = $name;
    }

    /**
     * Get the name of a role
     *
     * @param $role
     *
     * @return bool|string returns a string on success, false on failure
     */
    public static function roleName($role)
    {
        $names = self::getWpRolesObject()->get_names();
        if (isset($names[$role])) {
            return $names[$role];
        } else {
            return false;
        }
    }
    

    /**
     * Remove the named role from the database
     *
     * @param $role
     *
     * @return bool
     */
    public static function removeRole($role)
    {
        if (self::roleExists($role)) {
            self::getWpRolesObject()->remove_role($role);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determine if a Web Administrator is editing the homepage.
     * @return bool
     */
    public static function isWebAdministratorOnHomepage()
    {
        global $post_ID;
        return !current_user_can('administrator') && ($post_ID === (int)get_option('page_on_front'));
    }
}

