<?php

function hale_add_import_users_page() {

    if (current_user_can('manage_options')) {
        add_menu_page(
            'Import Users',
            'Import Users',
            'manage_options',
            'import-users',
            'hale_import_users_page',
            'dashicons-admin-generic'
        );
    }
}

add_action('admin_menu', 'hale_add_import_users_page');

function hale_import_users_page() {
    // Check if the current user has the 'manage_options' capability (administrator)
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Define variables to store form data and error messages
    $json_data = '';
    $errors = [];
    $added_users = 0;
    $failed_users = 0;

    // Check if the form is submitted
    if (isset($_POST['submit'])) {
        // Retrieve form data
        $json_data = stripslashes($_POST['json_data']);

        // Validate JSON data
        if (empty($json_data)) {
            $errors[] = 'JSON Data is required.';
        }

        $user_data = json_decode($json_data, true);

        if ($user_data === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON format. Please enter valid JSON data.';
        }

        if(empty($errors)){
            foreach ($user_data as $user) {

                if(!array_key_exists('userName', $user) || empty($user['userName'])){
                    $failed_users++;
                    continue;
                }

                if(!array_key_exists('email', $user) || empty($user['email']) || !is_email($user['email'])){
                    $failed_users++;
                    continue;
                }

                // Check if the user with the same email already exists
                $user_id = email_exists($user['email']);
            
                if (!$user_id) {

                    if(username_exists($user['userName'])){
                        $failed_users++;
                        continue;
                    }

                    // Prepare user data
                    $userdata = array(
                        'user_login' =>  $user['userName'],
                        'user_pass' => wp_generate_password(),
                        'user_email' => $user['email'],
                        'role' => 'subscriber',
                    );
            
                    // Insert the user
                    $user_id = wp_insert_user($userdata);
                }
        
                // Check for errors
                if (is_wp_error($user_id)) {
                    $failed_users++;
                    continue;
                } 

                // Add the user to the specified site in the Multisite network
                $result = add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber');

                // Check for errors
                if (is_wp_error($result)) {
                    $failed_users++;
                    continue;
                } 

                $added_users++;
                
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>Import Users</h1>
        <p>Imports users from JSON. Users are added as subscribers.</p>

        <p>Example JSON ('userName' and 'email' fields are required)</p>
        <code>

                [
            {
            "userName": "jDoe",
            "email": "john.doe@example.com"
            },
            {
            "userName": "jSmith",
            "email": "jane.smith@example.com"
            },
            {
            "userName": "bJohnson",
            "email": "bob.johnson@example.com"
            }
            ]

        </code>
        <?php
        // Display JSON error banner if present
        if (!empty($errors)) {
            foreach ($errors as $error){
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            }
        }
        if($added_users > 0){
            echo '<div class="notice notice-success is-dismissible"><p>' . $added_users . ' Users Imported</p></div>';
        }
        if($failed_users > 0){
            echo '<div class="notice notice-error is-dismissible"><p>' . $failed_users . ' Users Failed to be imported</p></div>';
        }
        ?>
        <br/>
        <br/>
        <form method="post" action="">
        <p>Site Importing to: <?php echo get_bloginfo('name'); ?></p>

            <label for="json_data">JSON Data:</label><br/><br/>
            <textarea name="json_data" id="json_data" rows="10" style="width:100%;" required><?php echo ($json_data); ?></textarea><br/><br/>
        

            <input type="submit" name="submit" class="button button-primary" value="Import">
        </form>
    </div>
    <?php
}