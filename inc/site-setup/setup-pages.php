<?php
//add_action( 'wpmu_new_blog', 'hc_setup_content', 10, 6 );

//add_action('wp', 'hc_setup_content');

function hc_setup_content($news_page_id){
   // $query = get_query_var('hc');

   $query = 'test';
    if(!empty($query)){
        $result = hc_sitesetup_create_pages();

      //  $result = ['header_menu' => [7, 8], 'footer_menu' => [9, 10]]; 
        if(is_numeric($news_page_id)){
            $result['header_menu'][] = $news_page_id;
        }

        if(count($result['header_menu']) > 0){
                $menu_settings = [
                    'name' => 'Main Menu',
                    'location' => 'main-menu',
                    'items' => $result['header_menu']
                ];
                hc_sitesetup_create_menu($menu_settings);
        }

        if(count($result['footer_menu']) > 0){

                $menu_settings = [
                    'name' => 'Footer Menu',
                    'location' => 'footer-menu',
                    'items' => $result['footer_menu']
                ];
                hc_sitesetup_create_menu($menu_settings);
                
        }
    }


}

function hc_sitesetup_create_menu($menu_settings){

    $menu_exists = wp_get_nav_menu_object($menu_settings['name']);

    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_settings['name']);


        foreach($menu_settings['items'] as $page_id){
            // Add page as a menu item
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-object-id' => $page_id,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ]);
        }

        $locations = get_theme_mod('nav_menu_locations');
        $locations[$menu_settings['location']] = $menu_id; 
        set_theme_mod('nav_menu_locations', $locations);
    }
}

function hc_sitesetup_create_pages(){


        $pages = [
            [
                'title' => "Homepage",
                'page_template' => 'page-full-width.php',
                'content_template' => 'home',
                'front_page' => true,
                'header_menu' => false,
                'footer_menu' => false
            ],
            [
                'title' => "About Us",
                'page_template' => '',
                'content_template' => 'about-us',
                'front_page' => false,
                'header_menu' => true,
                'footer_menu' => false
            ],
            [
                'title' => "Contact Us",
                'page_template' => '',
                'content_template' => 'contact-us',
                'front_page' => false,
                'header_menu' => true,
                'footer_menu' => false
            ],
            [
                'title' => "Accessibility statement",
                'page_template' => '',
                'content_template' => 'accessibility',
                'front_page' => false,
                'header_menu' => false,
                'footer_menu' => true
            ],
            [
                'title' => "Privacy Policy",
                'page_template' => '',
                'content_template' => 'privacy-policy',
                'front_page' => false,
                'header_menu' => false,
                'footer_menu' => true
            ],

            
        ];

        $result = ['header_menu' => [], 'footer_menu' => []]; 

        foreach($pages as $page){

            $content = '';

            if(!empty($page['content_template'])){
                $content = file_get_contents( plugin_dir_path( __FILE__ ) . 'templates/' . $page['content_template'] . '.html' );
            }

            $page_id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);

            // Assign the custom template (file must exist in theme directory)
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                if(!empty($page['page_template'])){
                    update_post_meta( $page_id, '_wp_page_template', $page['page_template']);
                }

                if($page['header_menu']){
                    $result['header_menu'][] = $page_id;
                }

                if($page['footer_menu']){
                    $result['footer_menu'][] = $page_id;
                }

                if($page['front_page']){
                    update_option( 'show_on_front', 'page' ); 
                    update_option( 'page_on_front', $page_id );  
                }   

            }
        }

        return $result;

}

function rj_add_query_vars_filter($vars)
{
    $vars[] = "hc";
    return $vars;
}

add_filter('query_vars', 'rj_add_query_vars_filter');