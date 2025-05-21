<?php

add_action('network_admin_menu', function () {
    add_submenu_page(
        'users.php',
        'Clean Up Users',
        'Clean Up Users',
        'manage_network_users',
        'cleanup-unassigned-users',
        'render_cleanup_page'
    );
});

function render_cleanup_page() {
    if ( ! is_super_admin() ) {
        wp_die('You do not have permission to access this page.');
    }

    echo '<div class="wrap">';
    echo '<h1>Clean Up Unassigned Users</h1>';
    echo '<p>This will delete all users who are not assigned to any site and are not super admins.</p>';
    echo '<p>If confirm is unchecked it will do a dry run (no users deleted)</p>';
    echo '<form method="post">';
    wp_nonce_field('cleanup_unassigned_users_action', 'cleanup_unassigned_users_nonce');
    // Super admin reassignment dropdown
    echo '<label for="reassign_user_id"><strong>Reassign content to:</strong></label><br><br>';
    echo '<select name="reassign_user_id" id="reassign_user_id">';
    echo '<option value="">-- Select Super Admin --</option>';

    $super_admins = get_super_admins();
    foreach ( $super_admins as $username ) {
        $user = get_user_by( 'login', $username );
        if ( $user ) {
            echo '<option value="' . esc_attr( $user->ID ) . '">' . $user->display_name . '</option>';
        }
    }

    echo '</select><br><br><br>';
    echo '<label><input type="checkbox" name="confirm_delete">I understand this will permanently delete unassigned users.</label><br><br>';
    submit_button('Delete Unassigned Users', 'delete', 'delete_unassigned_users');
    echo '</form>';

    if ( isset($_POST['delete_unassigned_users']) ) {

        if ( ! isset($_POST['cleanup_unassigned_users_nonce']) || ! wp_verify_nonce($_POST['cleanup_unassigned_users_nonce'], 'cleanup_unassigned_users_action') ) {
            wp_die('Security check failed.');
        }
        
        $unassigned_users = hale_get_unassigned_users();

        if ( empty($unassigned_users) ) {
            echo '<div class="notice notice-info"><p>No unassigned users found.</p></div>';
        } else {

            $user_list = '<ul>';
            foreach ( $unassigned_users as $user ) {
                $user_list .=  '<li>' . $user->user_email . '</li>';
            }

            $user_list .= '</ul>';

            if( ! empty($_POST['confirm_delete']) ){

                $reassign_user_id = null;
                if(! empty($_POST['reassign_user_id']) && is_numeric($_POST['reassign_user_id']) ) {
                    $reassign_user_id = (int) $_POST['reassign_user_id'];
                }
                hale_delete_unassigned_users($unassigned_users, $reassign_user_id);
                echo '<div class="notice notice-success"><p>Deleted users:</p>' . $user_list . '</div>';
            }
            else {
                echo '<div class="notice notice-success"><p>Dry run - Unassigned users found:</p>' . $user_list . '</div>';
            }

        }
    }

    echo '</div>';
}

function hale_get_unassigned_users() {
    $unassigned_users = [];

    $all_users = get_users([ 'blog_id' => 0 ]);

    foreach ( $all_users as $user ) {
        $user_id = $user->ID;

        if ( is_super_admin($user_id) ) {
            continue;
        }

        $blogs = get_blogs_of_user($user_id);

        if ( empty($blogs) ) {
            $unassigned_users[] = $user;
        }
    }

    return $unassigned_users;
}

function hale_delete_unassigned_users($unassigned_users, $reassign_user_id) {
    foreach ( $unassigned_users as $user ) {
        if(!empty($reassign_user_id)){
            hale_reassign_unassigned_users_content($user->ID, $reassign_user_id);
        }
        $user_id = $user->ID;
        wpmu_delete_user( $user_id );
    }
}

function hale_reassign_unassigned_users_content($user_id, $reassign_user_id) {
    $sites = get_sites();
    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );

        // Reassign posts and other content types
        $user_posts = get_posts([
            'author' => $user_id,
            'post_type' => 'any',
            'post_status' => 'any',
            'numberposts' => -1,
        ]);

        foreach ( $user_posts as $post ) {
            wp_update_post([
                'ID' => $post->ID,
                'post_author' => $reassign_user_id,
            ]);
        }

        restore_current_blog();
    }
}