<?php

declare(strict_types=1);

namespace MOJComponents\NetworkUserReports;

class NetworkUserReports
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('network_admin_menu', [$this, 'addMenuPage']);
        add_action('init', [$this, 'generateReport']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'users.php',
            'User Reports',
            'User Reports',
            'manage_network_users',
            'user-reports',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!is_super_admin()) {
            wp_die('You do not have permission to access this page.');
        }

        echo '<div class="wrap">';
        echo '<h1>User Reports</h1>';
        echo '<p>Creates csv report of users that have not logged in.</p>';
        echo '<form method="post">';
        wp_nonce_field('generate_user_report_action', 'generate_user_report_nonce');

        echo '<label for="user_report_site_id"><strong>Site:</strong></label><br>';
        echo '<p>By default the script will search all sites.</p>';
        echo '<select name="user_report_site_id" id="user_report_site_id">';
        echo '<option value="">-- Select Site --</option>';

        $sites = get_sites();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            echo '<option value="' . esc_attr((string) $site->blog_id) . '"> [' . $site->blog_id . '] ' . esc_html(get_bloginfo('name')) . '</option>';
            restore_current_blog();
        }

        echo '</select><br>';
        submit_button('Generate Report', 'generate_user_report', 'generate_user_report');
        echo '</form>';
        echo '</div>';
    }

    public function generateReport(): void
    {
        if (!is_super_admin()) {
            return;
        }

        if (!isset($_POST['generate_user_report'])) {
            return;
        }

        if (
            !isset($_POST['generate_user_report_nonce']) ||
            !wp_verify_nonce($_POST['generate_user_report_nonce'], 'generate_user_report_action')
        ) {
            wp_die('Security check failed.');
        }

        $users    = [];
        $fileName = 'user-report-' . time() . '.csv';

        if (!empty($_POST['user_report_site_id']) && is_numeric($_POST['user_report_site_id'])) {
            $siteId = (int) $_POST['user_report_site_id'];

            $users = get_users([
                'blog_id'    => $siteId,
                'meta_query' => [
                    [
                        'key'     => '_moj_comp_user_login',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ]);

            $fileName = 'user-report-site-' . $siteId . '-' . time() . '.csv';
        } else {
            global $wpdb;

            $metaKey = '_moj_comp_user_login';

            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT u.ID, u.user_login, u.user_email
                     FROM {$wpdb->users} u
                     WHERE u.ID NOT IN (
                         SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s
                     )",
                    $metaKey
                )
            );
        }

        if (!is_array($users) || count($users) === 0) {
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['Username', 'Email', 'Sites']);

        foreach ($users as $user) {
            $blogs      = get_blogs_of_user($user->ID);
            $siteIds    = array_map(fn($b) => $b->userblog_id, $blogs);
            sort($siteIds);
            fputcsv($output, [$user->user_login, $user->user_email, implode(',', $siteIds)]);
        }

        fclose($output);
        exit;
    }
}
