<?php

declare(strict_types=1);

namespace MOJComponents\SiteUserReports;

class SiteUserReports
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('init', [$this, 'generateReport']);
    }

    public function addMenuPage(): void
    {
        add_users_page(
            'User Reports',
            'User Reports',
            'list_users',
            'site-user-reports',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('list_users')) {
            wp_die('User does not have the permissions to do this action');
        }

        $domains = $this->getEmailDomains();
        ?>
        <div class="wrap">
            <h1>User Reports</h1>
            <p>Creates csv report of users.</p>
            <p>By default the report will not be filtered.
               You can filter this report by selecting an email domain below.</p>
            <form method="post">
                <?php wp_nonce_field('generate_site_user_report_action', 'generate_site_user_report_nonce'); ?>
                <label for="user_report_email_domain"><strong>Email Domain:</strong></label><br>
                <br>
                <select name="user_report_email_domain" id="user_report_email_domain">
                    <option value="">— Select an email domain —</option>
                    <?php foreach ($domains as $domain) : ?>
                        <option value="<?php echo esc_attr($domain); ?>">
                            <?php echo esc_html($domain); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('Generate Report', 'generate_site_user_report', 'generate_site_user_report'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get distinct email domains for users on the current site (multisite-aware).
     *
     * @return string[]
     */
    public function getEmailDomains(): array
    {
        global $wpdb;

        if (is_multisite()) {
            $prefix = $wpdb->get_blog_prefix(get_current_blog_id());

            $sql = $wpdb->prepare(
                "SELECT DISTINCT SUBSTRING_INDEX(u.user_email, '@', -1) AS domain
                 FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
                 WHERE um.meta_key = %s
                 ORDER BY domain ASC",
                $prefix . 'capabilities'
            );

            return $wpdb->get_col($sql);
        }

        return $wpdb->get_col(
            "SELECT DISTINCT SUBSTRING_INDEX(user_email, '@', -1) AS domain
             FROM {$wpdb->users}
             ORDER BY domain ASC"
        );
    }

    public function generateReport(): void
    {
        if (!isset($_POST['generate_site_user_report'])) {
            return;
        }

        if (!current_user_can('list_users')) {
            wp_die('User does not have the permissions to do this action');
        }

        if (
            !isset($_POST['generate_site_user_report_nonce']) ||
            !wp_verify_nonce($_POST['generate_site_user_report_nonce'], 'generate_site_user_report_action')
        ) {
            wp_die('Security check failed.');
        }

        $userFilters = [];

        if (!empty($_POST['user_report_email_domain'])) {
            $emailDomain = sanitize_text_field($_POST['user_report_email_domain']);
            $userFilters = [
                'search'         => '*@' . $emailDomain,
                'search_columns' => ['user_email'],
            ];
        }

        $users    = get_users($userFilters);
        $fileName = 'user-report-' . time() . '.csv';

        if (!is_array($users) || count($users) === 0) {
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['Username', 'Email', 'Last Login Date']);

        foreach ($users as $user) {
            $lastLoggedInDate = '';
            $loginDate        = get_user_meta($user->ID, '_moj_comp_user_login', true);

            if (!empty($loginDate)) {
                $lastLoggedInDate = date('Y-m-d', (int) $loginDate);
            }

            fputcsv($output, [$user->user_login, $user->user_email, $lastLoggedInDate]);
        }

        fclose($output);
        exit;
    }
}
