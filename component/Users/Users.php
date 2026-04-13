<?php

declare(strict_types=1);

namespace MOJComponents\Users;

class Users
{
    private $helper;

    public bool $hasSettings = true;

    public $settings;

    /** Underscore prefix hides key from the GUI. */
    public string $last_logged_in_key = '_moj_comp_user_login';

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;

        $this->addSchedule();
        $this->actions();
        $this->userSwitch();

        SiteManager::createRole();
        RoleHooks::apply();
    }

    public static function userSwitch(): UserSwitch
    {
        return new UserSwitch();
    }

    public function actions(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_login', [$this, 'wpLogin']);
        add_action('moj_check_user_activity', [$this, 'inactiveUsers']);

        add_action('wp_loaded', [new UsersSettings(), 'settings'], 1);
    }

    public function enqueue(): void
    {
    }

    public function inactiveUsers(): bool|null
    {
        $options = get_option('moj_component_settings');

        if (isset($options['user_active_disable']) && $options['user_active_disable'] === 'yes') {
            return false;
        }

        $users         = get_users('role=web-administrator');
        $inactiveUsers = [];

        foreach ($users as $user) {
            $lastLogin = get_user_meta($user->ID, $this->last_logged_in_key, true);

            if ($lastLogin === '') {
                update_user_meta($user->ID, $this->last_logged_in_key, time());
                update_user_meta($user->ID, $this->last_logged_in_key . '_source', 'system');
                continue;
            }

            $lastLogin      = (int) $lastLogin;
            $threeMonthsAgo = time() - 7776000;

            if ($lastLogin < $threeMonthsAgo) {
                $inactiveUsers[] = [
                    'name'       => $user->display_name,
                    'profile'    => $this->getUserProfileURL($user->ID),
                    'last_login' => date('l jS \of F', $lastLogin),
                    'source'     => get_user_meta($user->ID, $this->last_logged_in_key . '_source', true),
                ];
            }
        }

        if ($options['user_inactive_test']) {
            foreach ($this->dummyTestData() as $dummyUser) {
                $inactiveUsers[] = $dummyUser;
            }
        }

        if (!empty($inactiveUsers)) {
            $message = '';
            foreach ($inactiveUsers as $user) {
                $source   = ' <small style="color:#666666;">(source: ' . $user['source'] . ')</small>';
                $message .= '<a href="' . $user['profile'] . '" title="Visit profile">' . $user['name'] . '</a>'
                    . ' last logged in on ' . $user['last_login'] . $source . '<br>- - -<br>';
            }

            $siteName = get_option('blogname');
            $message  = $this->getMailHTML($message, $siteName, count($inactiveUsers));

            $subject = '[USERS] Inactive user report for ' . $siteName;
            $this->helper->setMailSubject($subject);
            $this->helper->setMailMessage($message);
            $this->helper->setMaiTo($options['user_active_to_email'] ?? '');

            $this->helper->mail();
        }

        return null;
    }

    /** @return array<int, array{name: string, profile: string, last_login: string, source: string}> */
    private function dummyTestData(): array
    {
        return [
            [
                'name'       => 'Beverley',
                'profile'    => $this->getUserProfileURL(1),
                'last_login' => date('l jS \of F', time()),
                'source'     => 'system',
            ],
            [
                'name'       => 'Robert',
                'profile'    => $this->getUserProfileURL(2),
                'last_login' => date('l jS \of F', time()),
                'source'     => 'system',
            ],
            [
                'name'       => 'Adam',
                'profile'    => $this->getUserProfileURL(3),
                'last_login' => date('l jS \of F', time()),
                'source'     => 'user',
            ],
        ];
    }

    public function getUserProfileURL(int $userId): string
    {
        return admin_url('user-edit.php?user_id=' . $userId);
    }

    public function getMailHTML(string $userList, string $site, int $nthUsers): string
    {
        $emailTemplate = file_get_contents(__DIR__ . '/assets/email-templates/moj-users.html');

        $search = [
            '{blogname}',
            '{dt-logo}',
            '{list_of_users}',
            '{nth_users}',
            '{domain}',
            '{moj-logo}',
        ];

        $replace = [
            $site,
            $this->helper->imagePath(__FILE__) . 'moj-dt.png',
            $userList,
            (string) $nthUsers,
            get_home_url(),
            $this->helper->imagePath(__FILE__) . 'moj.png',
        ];

        return str_replace($search, $replace, $emailTemplate);
    }

    public function wpLogin(string $userLogin): void
    {
        $user = get_user_by('login', $userLogin);
        update_user_meta($user->ID, $this->last_logged_in_key, time());
        update_user_meta($user->ID, $this->last_logged_in_key . '_source', 'user');
    }

    public function addSchedule(): void
    {
        $recurrence    = get_option('moj_component_settings', []);
        $recurrence    = $recurrence['user_inactive_schedule'] ?? 'monthly';
        $nowRecurrence = wp_get_schedule('moj_check_user_activity');

        if ($nowRecurrence && $recurrence !== $nowRecurrence) {
            wp_clear_scheduled_hook('moj_check_user_activity');
        }

        if (!wp_next_scheduled('moj_check_user_activity')) {
            wp_schedule_event(time(), $recurrence, 'moj_check_user_activity');
        }
    }
}
