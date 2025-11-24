<?php

 add_action( 'admin_menu', function() {
    add_users_page(
        'User Reports',          // Page title
        'User Reports',          // Menu title
        'list_users',            // Capability
        'site-user-reports',    // Slug
        'hc_render_site_user_reports_page' // Callback
    );
});

/**
 * Get user email domains (multisite-aware).
 */
function hc_get_user_email_domains_multisite() {
    global $wpdb;

    if ( is_multisite() ) {
        $prefix = $wpdb->get_blog_prefix( get_current_blog_id() );

        $sql = $wpdb->prepare("
            SELECT DISTINCT
                SUBSTRING_INDEX(u.user_email, '@', -1) AS domain
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um
                ON um.user_id = u.ID
            WHERE um.meta_key = %s
            ORDER BY domain ASC
        ", $prefix . 'capabilities' );

        return $wpdb->get_col( $sql );
    }

    $sql = "
        SELECT DISTINCT
            SUBSTRING_INDEX(user_email, '@', -1) AS domain
        FROM {$wpdb->users}
        ORDER BY domain ASC
    ";

    return $wpdb->get_col( $sql );
}

function hc_render_site_user_reports_page() {
    $domains = hc_get_user_email_domains_multisite();
    ?>
    <div class="wrap">
        <h1>User Reports</h1>
        <p>Creates csv report of users.</p>
        <p>By default the report will not be filtered. You can be filter this report by selecting a email domain below.</p>
        <form method="post">
            <?php  wp_nonce_field('generate_site_user_report_action', 'generate_site_user_report_nonce'); ?>
            <label for="user_report_email_domain"><strong>Email Domain:</strong></label><br>
            <br>
            <select name="user_report_email_domain" id="user_report_email_domain">
                <option value="">— Select a email domain —</option>
                <?php foreach ( $domains as $domain ) : ?>
                    <option value="<?php echo esc_attr( $domain ); ?>">
                        <?php echo esc_html( $domain ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php  submit_button('Generate Report', 'generate_site_user_report', 'generate_site_user_report');; ?>
        </form>

       
    </div>
    <?php
}

add_action('init', 'hc_generate_site_user_report');

function hc_generate_site_user_report() {

    if ( isset($_POST['generate_site_user_report']) ) {

        if ( ! isset($_POST['generate_site_user_report_nonce']) || ! wp_verify_nonce($_POST['generate_site_user_report_nonce'], 'generate_site_user_report_action') ) {
            wp_die('Security check failed.');
        }

        $users = [];
        $file_name = "user-report-" . time() . ".csv";

        $user_filters = [];

        if(! empty($_POST['user_report_email_domain'])) {
            $email_domain = $_POST['user_report_email_domain'];
            $user_filters = [
                'search'         => '*@' . $email_domain,
                'search_columns' => ['user_email'],
            ];
        }

        $users = get_users($user_filters);

        if(is_array($users) && count($users) > 0){

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add header row
            fputcsv($output, ['Username', 'Email', 'Last Login Date']);

            foreach ( $users as $user ) {

                $last_logged_in_date = '';

                $login_date = get_user_meta($user->ID, '_moj_comp_user_login', true);

                if(!empty($login_date)){
                    $last_logged_in_date = date('Y-m-d', $login_date);
                }

                fputcsv($output, [$user->user_login, $user->user_email, $last_logged_in_date]);
            }

            fclose($output);
            exit;
        }

    }
}