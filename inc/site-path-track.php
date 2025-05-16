<?php
/*
    Functions that manage the site path slug.
*/

// Set site_path_slug when a new site is created
function hale_components_set_new_site_path_slug( $site ) {
    switch_to_blog( $site->blog_id );

    $site_path_slug = trim( $site->path, '/' );

    if ( ! empty( $site_path_slug ) && get_option( 'site_path_slug' ) === false ) {
        add_option( 'site_path_slug', $site_path_slug );
    }

    restore_current_blog();
}
add_action( 'wp_initialize_site', 'hale_components_set_new_site_path_slug', 900 );

// Update site_path_slug when a site's path is changed
function hale_components_update_site_path_slug( $site ) {
    switch_to_blog( $site->blog_id );

    $site_path_slug = trim( $site->path, '/' );

    if ( ! empty( $site_path_slug ) ) {
        update_option( 'site_path_slug', $site_path_slug );
    }

    restore_current_blog();
}
add_action( 'wp_update_site', 'hale_components_update_site_path_slug', 900 );

