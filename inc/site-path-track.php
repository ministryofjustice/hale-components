<?php
/*
    Functions that manage the site path slug.     
*/

//Sets the site path slug if it does not already exist
function hale_components_set_default_site_path_slug(){
    if ( ! get_option( 'site_path_slug' ) ) {
        add_option('site_path_slug', '');
    }
}

add_action( 'wp_loaded', 'hale_components_set_default_site_path_slug');

//Sets the site_path_slug after a new site has been created
function hale_components_set_new_site_path_slug($site) {
    switch_to_blog( $site->blog_id );
    $site_path_slug = str_replace("/", "", $site->path);

    if(!empty($site_path_slug)){
        update_option('site_path_slug', $site_path_slug);
    }
    restore_current_blog();
}

add_action('wp_initialize_site', 'hale_components_set_new_site_path_slug', 900);