<?php

add_action('network_admin_menu', function () {
    add_submenu_page(
        'users.php',
        'User Reports',
        'User Reports',
        'manage_network_users',
        'user-reports',
        'hc_user_reports'
    );
});

function hc_user_reports() {
    if ( ! is_super_admin() ) {
        wp_die('You do not have permission to access this page.');
    }

    echo get_query_var('generate_user_report_status');
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

    foreach ( $sites as $site ) {
        // Switch to each site
        switch_to_blog( $site->blog_id );

        echo '<option value="' . $site->blog_id . '"> [' . $site->blog_id . '] ' . get_bloginfo( 'name' ) . '</option>';

        // Restore to the current site
        restore_current_blog();
    }


    echo '</select><br>';
    submit_button('Generate Report', 'generate_user_report', 'generate_user_report');
    echo '</form>';

 

    echo '</div>';
}

add_action('init', 'hc_generate_user_report');

function hc_generate_user_report() {

    if ( ! is_super_admin() ) {
       return;
    }

    if ( isset($_POST['generate_user_report']) ) {

        if ( ! isset($_POST['generate_user_report_nonce']) || ! wp_verify_nonce($_POST['generate_user_report_nonce'], 'generate_user_report_action') ) {
            wp_die('Security check failed.');
        }

        $users = [];
        $file_name = "user-report-" . time() . ".csv";

        if(! empty($_POST['user_report_site_id']) && is_numeric($_POST['user_report_site_id']) ) {
            $user_report_site_id = (int) $_POST['user_report_site_id'];

            $users = get_users([
                'blog_id' => $user_report_site_id,
                'meta_query' => [
                    [
                        'key'     => '_moj_comp_user_login',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ]);

            $file_name = "user-report-site-" . $user_report_site_id . '-' . time() . ".csv";
        }
        else {

            global $wpdb;

            $meta_key = "_moj_comp_user_login";

            $users = $wpdb->get_results( $wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email
            FROM {$wpdb->users} u
            WHERE u.ID NOT IN (
                SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = %s
            )
            ", $meta_key) );
        }

        if(is_array($users) && count($users) > 0){

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add header row
            fputcsv($output, ['Username', 'Email', 'Sites']);

            foreach ( $users as $user ) {
                $blogs = get_blogs_of_user( $user->ID );
                $site_ids = array_map( function( $b ) { return $b->userblog_id; }, $blogs );
                sort( $site_ids );
                $site_ids_str = implode( ",", $site_ids );
                fputcsv($output, [$user->user_login, $user->user_email, $site_ids_str]);
            }

            fclose($output);
            exit;
        }

    }
}