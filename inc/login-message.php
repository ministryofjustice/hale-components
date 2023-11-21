<?php

function hale_custom_login_message() {

    $message = get_option('login_message');

    if(!empty($message)){
        echo '<div class="custom-login-message" style="  padding: 10px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        background-color: #f9f9f9;">'.$message.'</div>';
    }
}
add_action('login_message', 'hale_custom_login_message');

function hale_add_login_message_page() {
    add_menu_page(
        'Login Message',
        'Login Message',
        'manage_options',
        'login-message',
        'hale_login_message_page',
        'dashicons-admin-generic'
    );
}

add_action('admin_menu', 'hale_add_login_message_page');

function hale_login_message_page() {
    // Check if the current user has the 'manage_options' capability (administrator)
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Define variables to store form data and error messages
    $message = get_option('login_message');
    $updated = 0;

    // Check if the form is submitted
    if (isset($_POST['submit'])) {
        // Retrieve form data
        $message = sanitize_text_field($_POST['message_text']);

        update_option( 'login_message', $message );

        $updated = 1;

    }

    ?>
    <div class="wrap">
        <h1>Login Message</h1>
        <?php
        if($updated > 0){
            echo '<div class="notice notice-success is-dismissible"><p>Login Message updated</p></div>';
        }
        ?>
        <form method="post" action="">

            <label for="message_text">Message Text:</label><br/><br/>
            <input type="text" name="message_text" id="message_text" style="width:50%;" required value="<?php echo ($message); ?>"/><br/><br/>
    
            <input type="submit" name="submit" class="button button-primary" value="Save">
        </form>
    </div>
    <?php
}