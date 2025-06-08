<?php

add_action( 'network_site_new_form', 'add_news_cpt_checkbox' );

function add_news_cpt_checkbox() {
    ?>
    <h3>Custom Site Options</h3>
    <table class="form-table">
        <tr>
            <th><label for="create_news_cpt">Create News Section?</label></th>
            <td>
                <label>
                    <input type="checkbox" name="create_news_cpt" id="create_news_cpt" value="1" checked />
                    Yes, create a "News" post type and listing page
                </label>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'wpmu_new_blog', 'create_listing_page_on_new_site', 10, 6 );

function create_listing_page_on_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

    if ( isset( $_POST['create_news_cpt'] ) && $_POST['create_news_cpt'] === '1' ) {
        switch_to_blog( $blog_id );

        create_news_cpt();
        create_news_articles();

        // Create the page
        $page_id = wp_insert_post( array(
            'post_title'     => 'News',
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => $user_id,
        ) );

        // Assign the custom template (file must exist in theme directory)
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', 'page-listing.php' );
            update_post_meta( $page_id, 'listing_post_type', 'news' );
        }
        

        restore_current_blog();
    }   
}

function create_news_cpt(){
    $array_content = array (
        'post_type' => 'news',
        'advanced_configuration' => true,
        'import_source' => '',
        'import_date' => '',
        'labels' => 
        array(
            'name' => 'News',
            'singular_name' => 'News Article',
            'menu_name' => 'News',
            'all_items' => 'All News',
            'edit_item' => 'Edit News Article',
            'view_item' => 'View News Article',
            'view_items' => 'View News',
            'add_new_item' => 'Add New News Article',
            'add_new' => 'Add New News Article',
            'new_item' => 'New News Article',
            'parent_item_colon' => 'Parent News Article:',
            'search_items' => 'Search News',
            'not_found' => 'No news found',
            'not_found_in_trash' => 'No news found in the bin',
            'archives' => 'News Article Archives',
            'attributes' => 'News Article Attributes',
            'insert_into_item' => 'Insert into news article',
            'uploaded_to_this_item' => 'Uploaded to this news article',
            'filter_items_list' => 'Filter news list',
            'filter_by_date' => 'Filter news by date',
            'items_list_navigation' => 'News list navigation',
            'items_list' => 'News list',
            'item_published' => 'News Article published.',
            'item_published_privately' => 'News Article published privately.',
            'item_reverted_to_draft' => 'News Article reverted to draft.',
            'item_scheduled' => 'News Article scheduled.',
            'item_updated' => 'News Article updated.',
            'item_link' => 'News Article Link',
            'item_link_description' => 'A link to a news article.',
        ),
        'description' => '',
        'public' => true,
        'hierarchical' => false,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'admin_menu_parent' => '',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'rest_base' => '',
        'rest_namespace' => 'wp/v2',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'menu_position' => '',
        'menu_icon' => 
        array (
          'type' => 'dashicons',
          'value' => 'dashicons-admin-post',
        ),
        'rename_capabilities' => false,
        'singular_capability_name' => 'post',
        'plural_capability_name' => 'posts',
        'supports' => 
        array (
          0 => 'title',
          1 => 'editor',
          2 => 'thumbnail',
          3 => 'custom-fields',
        ),
        'taxonomies' => 
        array (
 
        ),
        'has_archive' => false,
        'has_archive_slug' => '',
        'rewrite' => 
        array (
          'permalink_rewrite' => 'post_type_key',
          'with_front' => '1',
          'feeds' => '0',
          'pages' => '1',
        ),
        'query_var' => 'post_type_key',
        'query_var_name' => '',
        'can_export' => true,
        'delete_with_user' => false,
        'register_meta_box_cb' => '',
        'enter_title_here' => '',
        'allow_document_upload' => 0,
        'post_summary' => 1,
        'show_published_date_on_single_view' => 1,
        'show_summary_on_single_view' => 1,
        'show_toc_on_single_view' => 0,
        'number_headings' => 0,
        'show_tax_on_single_view' => 0,
        'enable_banner_on_single_view' => 0,
        'restrict_blocks_multi' => '',
    );

    $serialized_content = serialize($array_content);
    
    // Insert post
    $post_id = wp_insert_post([
        'post_type'    => 'acf-post-type', // Your custom post type
        'post_title'   => 'News',
        'post_content' => $serialized_content, 
        'post_status'  => 'publish',
    ]);

    flush_rewrite_rules();
}


function create_news_articles(){

    for ($i = 1; $i <= 3; $i++) {
        wp_insert_post([
            'post_title'   => "Dummy News Post $i",
            'post_content' => "This is the dummy content for News Post $i. Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
            'post_status'  => 'publish',
            'post_type'    => 'news',
        ]);
    }

}