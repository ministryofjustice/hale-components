<?php

declare(strict_types=1);

namespace MOJComponents\CleanUpUsers;

use WP_User;

class CleanUpUsers
{
    public function __construct()
    {
        $this->actions();
        $this->scheduleCleanup();
    }

    private function actions(): void
    {
        add_action('network_admin_menu', [$this, 'addMenuPage']);
        add_action('hale_cleanup_users_cron', [$this, 'runCleanup']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'users.php',
            'Clean Up Users',
            'Clean Up Users',
            'manage_network_users',
            'cleanup-unassigned-users',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!is_super_admin()) {
            wp_die('You do not have permission to access this page.');
        }

        echo '<div class="wrap">';
        echo '<h1>Clean Up Unassigned Users</h1>';
        echo '<p>This will delete all users who are not assigned to any site and are not super admins.</p>';
        echo '<p>If confirm is unchecked it will do a dry run (no users deleted)</p>';
        echo '<form method="post">';
        wp_nonce_field('cleanup_unassigned_users_action', 'cleanup_unassigned_users_nonce');

        echo '<label for="reassign_user_id"><strong>Reassign content to:</strong></label><br><br>';
        echo '<select name="reassign_user_id" id="reassign_user_id">';
        echo '<option value="">-- Select Super Admin --</option>';

        $superAdmins = get_super_admins();
        foreach ($superAdmins as $username) {
            $user = get_user_by('login', $username);
            if ($user) {
                echo '<option value="' . esc_attr((string) $user->ID) . '">' . esc_html($user->display_name) . '</option>';
            }
        }

        echo '</select><br><br><br>';
        echo '<label><input type="checkbox" name="confirm_delete">I understand this will permanently delete unassigned users.</label><br><br>';
        submit_button('Delete Unassigned Users', 'delete', 'delete_unassigned_users');
        echo '</form>';

        if (isset($_POST['delete_unassigned_users'])) {
            if (
                !isset($_POST['cleanup_unassigned_users_nonce']) ||
                !wp_verify_nonce($_POST['cleanup_unassigned_users_nonce'], 'cleanup_unassigned_users_action')
            ) {
                wp_die('Security check failed.');
            }

            $unassignedUsers = $this->getUnassignedUsers();

            if (empty($unassignedUsers)) {
                echo '<div class="notice notice-info"><p>No unassigned users found.</p></div>';
            } else {
                $userList = '<ul>';
                foreach ($unassignedUsers as $user) {
                    $userList .= '<li>' . esc_html($user->user_email) . '</li>';
                }
                $userList .= '</ul>';

                if (!empty($_POST['confirm_delete'])) {
                    $reassignUserId = null;
                    if (!empty($_POST['reassign_user_id']) && is_numeric($_POST['reassign_user_id'])) {
                        $reassignUserId = (int) $_POST['reassign_user_id'];
                    }

                    $this->deleteUnassignedUsers($unassignedUsers, $reassignUserId);
                    echo '<div class="notice notice-success"><p>Deleted users:</p>' . $userList . '</div>';
                } else {
                    echo '<div class="notice notice-success"><p>Dry run - Unassigned users found:</p>' . $userList . '</div>';
                }
            }
        }

        echo '</div>';
    }

    /**
     * Get all users not assigned to any site and not super admins.
     *
     * @return WP_User[]
     */
    public function getUnassignedUsers(): array
    {
        $unassignedUsers = [];
        $allUsers        = get_users(['blog_id' => 0]);

        foreach ($allUsers as $user) {
            if (is_super_admin($user->ID)) {
                continue;
            }

            if (empty(get_blogs_of_user($user->ID))) {
                $unassignedUsers[] = $user;
            }
        }

        return $unassignedUsers;
    }

    /**
     * Delete unassigned users, optionally reassigning their content first.
     *
     * @param WP_User[] $unassignedUsers
     * @param int|null  $reassignUserId
     */
    public function deleteUnassignedUsers(array $unassignedUsers, ?int $reassignUserId): void
    {
        foreach ($unassignedUsers as $user) {
            if ($reassignUserId !== null) {
                $this->reassignUserContent($user->ID, $reassignUserId);
            }

            wpmu_delete_user($user->ID);
        }
    }

    /**
     * Delete unconfirmed signups older than 14 days.
     */
    public function deleteUnconfirmedUsers(): void
    {
        global $wpdb;

        $cutoffDate = date(
            'Y-m-d H:i:s',
            strtotime('-14 days', current_time('timestamp'))
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->signups} WHERE active = 0 AND registered < %s",
                $cutoffDate
            )
        );
    }

    /**
     * Reassign all posts by a given user to another user across all sites.
     *
     * @param int $userId         The user whose content is being reassigned.
     * @param int $reassignUserId The user receiving the content.
     */
    public function reassignUserContent(int $userId, int $reassignUserId): void
    {
        $sites = get_sites();

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $posts = get_posts([
                'author'      => $userId,
                'post_type'   => 'any',
                'post_status' => 'any',
                'numberposts' => -1,
            ]);

            foreach ($posts as $post) {
                wp_update_post([
                    'ID'          => $post->ID,
                    'post_author' => $reassignUserId,
                ]);
            }

            restore_current_blog();
        }
    }

    /**
     * Cron callback: delete unassigned users and unconfirmed signups.
     */
    public function runCleanup(): void
    {
        if (!function_exists('wpmu_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/ms.php';
        }

        $unassignedUsers = $this->getUnassignedUsers();
        $this->deleteUnassignedUsers($unassignedUsers, null);
        $this->deleteUnconfirmedUsers();
    }

    /** Schedule the cleanup cron to run at 3am if not already scheduled. */
    private function scheduleCleanup(): void
    {
        if (wp_next_scheduled('hale_cleanup_users_cron')) {
            return;
        }

        $now       = time();
        $today3am  = strtotime('today 3:00', $now);
        $timestamp = ($now >= $today3am) ? strtotime('tomorrow 3:00', $now) : $today3am;

        wp_schedule_event($timestamp, 'daily', 'hale_cleanup_users_cron');
    }
}
