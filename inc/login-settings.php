<?php

function hale_custom_login_message() {

    $message = get_option('login_message');

    if(!empty($message)){
        echo '<div class="custom-login-message">'.wpautop($message).'</div>';
    }
}
add_action('login_message', 'hale_custom_login_message');

function hale_login_styles() { 
    
    $title = get_option('login_title');
    $message = get_option('login_message');

        //Login Title Styles
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                background-image: none;
                text-indent: 0;
                font-size: 28px;
                font-weight: 600;
                height: auto;
                width: auto;
            }
        </style>
    <?php 


    if(!empty($message)){ 
         //Login Message Styles
        ?>
        <style type="text/css">
            #login .custom-login-message {
                padding: 10px;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                background-color: #f9f9f9;
            }
            #login .custom-login-message p {
                margin-bottom: 10px;
            }

            #login .custom-login-message p:last-child {
                margin-bottom: 0px;
            }
        </style>
    <?php
    }
}
add_action( 'login_enqueue_scripts', 'hale_login_styles' );

function hale_custom_login_title() {

    $title = get_option('login_title');

    if(!empty($title)){
        return $title;
    }

    return get_bloginfo('name');
}
add_filter( 'login_headertext', 'hale_custom_login_title' );

function hale_login_header_link($login_header_url)
{
    return home_url();
}
add_filter('login_headerurl', 'hale_login_header_link');

function hale_add_login_settings_page() {
    add_menu_page(
        'Login Settings',
        'Login Settings',
        'manage_options',
        'login-settings',
        'hale_login_settings_page',
        'dashicons-admin-generic'
    );
}

add_action('admin_menu', 'hale_add_login_settings_page');

function hale_login_settings_page() {
    // Check if the current user has the 'manage_options' capability (administrator)
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Define variables to store form data and error messages

    $title = get_option('login_title');
    $message = get_option('login_message');
    $updated = 0;

    // Check if the form is submitted
    if (isset($_POST['submit'])) {
        // Retrieve form data

        $title = sanitize_text_field($_POST['login_title']);
        $message = sanitize_textarea_field($_POST['login_message']);

        update_option( 'login_title', $title );
        update_option( 'login_message', $message );

        $updated = 1;

    }

    ?>
    <div class="wrap">
        <h1>Login Settings</h1>
        <br/>
        <?php
        if($updated > 0){
            echo '<div class="notice notice-success is-dismissible"><p>Login Settings updated</p></div>';
        }
        ?>
        <form method="post" action="">

            <label for="message_text">Login Title: (optional, default is site name)</label><br/><br/>
            <input type="text" name="login_title" id="login_title" style="width:50%;" value="<?php echo ($title); ?>"/><br/><br/>

            <label for="message_text">Login Message:</label><br/><br/>
            <textarea name="login_message" id="login_message" rows="10" style="width:50%;"><?php echo ($message); ?></textarea><br/><br/>
    
            <input type="submit" name="submit" class="button button-primary" value="Save">
        </form>
    </div>
    <?php
}