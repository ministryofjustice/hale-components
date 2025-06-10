<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Hook into the REST API initialization action to register custom endpoints.
add_action('rest_api_init', 'hale_components_register_rest_api_endpoints');

/**
 * Registers custom public REST API routes for the hale-components plugin.
 * Only for WP multisite configurations
 */
function hale_components_register_rest_api_endpoints()
{
    // Route to get all sites in the multisite network.
    register_rest_route('hc-rest/v1', '/sites', [
        'methods' => 'GET',
        'callback' => 'hale_components_get_api_sites_callback',
        'permission_callback' => '__return_true',
    ]);

    // Route to get only subdirectory-based sites.
    register_rest_route('hc-rest/v1', '/sites/path', [
        'methods' => 'GET',
        'callback' => 'hale_components_get_api_path_callback',
        'permission_callback' => '__return_true',
    ]);

    // Route to get only domain-based sites
    register_rest_route('hc-rest/v1', '/sites/domain', [
        'methods' => 'GET',
        'callback' => 'hale_components_get_api_domain_callback',
        'permission_callback' => '__return_true',
    ]);

    // Route to get all registered block types.
    register_rest_route('hc-rest/v1', '/blocks', [
        'methods' => 'GET',
        'callback' => 'hale_components_get_api_blocks_callback',
        'permission_callback' => '__return_true',
    ]);

    // Route to get only block types registered under the 'mojblocks/' namespace.
    register_rest_route('hc-rest/v1', '/blocks/moj', [
        'methods' => 'GET',
        'callback' => 'hale_components_get_api_moj_blocks_callback',
        'permission_callback' => '__return_true',
    ]);
}

/**
 * Callback for /sites endpoint.
 * Returns a list of all sites in the multisite network.
 */
function hale_components_get_api_sites_callback()
{
    $sites = get_sites();
    $data = [];

    // Loop through each site and gather relevant details.
    foreach ($sites as $site) {
        $details = get_blog_details($site->blog_id);
        $url = $details->siteurl;

        // Parse the domain from the full URL
        $parsed_url = parse_url($url);
        $domain = $parsed_url['host'] ?? $details->domain;

        $data[] = [
            'blog_id' => $site->blog_id,
            'slug'    => $details->path,
            'url'     => $url,
            'domain'  => $domain,
            'name'    => $details->blogname, // May default to root name if not switched.
        ];
    }

    return rest_ensure_response($data);
}

/**
 * Callback for /sites/path endpoint.
 * Returns a list of all sites installed as subdirectories under the root domain.
 */
function hale_components_get_api_path_callback()
{
    // Retrieve all sites
    $sites = get_sites();

    // Array to store subdirectory-based sites
    $subdirectory_sites = [];

    // Loop through each site and check for subdirectory-based path
    foreach ($sites as $site) {
        // Check for paths other than the root '/', which indicates a subdirectory install
        if ($site->path && $site->path !== '/') {

            // Switch context to the site to get accurate name and URL
            switch_to_blog($site->blog_id);

            // Collect and store site details
            $subdirectory_sites[] = [
                'blog_id' => $site->blog_id,
                'slug'    => $site->path,
                'url'     => get_site_url($site->blog_id),
                'name'    => get_bloginfo('name', 'raw'), // Retrieves the correct site title
            ];

            // Restore context to the original site
            restore_current_blog();
        }
    }

    // Return the filtered list of subdirectory sites
    return rest_ensure_response($subdirectory_sites);
}

/**
 * Callback for /sites/domain endpoint.
 * Returns a list of all sites that are using a unique domain (i.e., not subdirectory-based).
 */
function hale_components_get_api_domain_callback()
{
    $sites = get_sites();
    $data = [];

    foreach ($sites as $site) {
        $details = get_blog_details($site->blog_id);
        $url = $details->siteurl;

        // Filter out unwanted domains. Filter out exact matches to these domains.
        // Will include subdomains but not directories.
        if (
            strpos($url, '://hale.docker') !== false ||
            strpos($url, '://websitebuilder.service.justice.gov.uk') !== false
        ) {
            continue;
        }

        // Parse the domain from the full URL
        $parsed_url = parse_url($url);
        $domain = $parsed_url['host'] ?? $details->domain;

        switch_to_blog($site->blog_id);
        $slug = get_option('site_path_slug');
        restore_current_blog();

        if (empty($slug)) {
            $slug = 'site-' . $site->blog_id;
        }

        $data[] = [
            'blogID'  => $site->blog_id,
            'path'    => $details->path,
            'slug'    => $slug,
            'url'     => $url,
            'domain'  => $domain,
            'name'    => $details->blogname,
        ];
    }

    return rest_ensure_response($data);
}

/**
 * Callback for /blocks endpoint.
 * Returns all registered block types in the current site.
 */
function hale_components_get_api_blocks_callback()
{
    // Get the block type registry
    $registry = WP_Block_Type_Registry::get_instance();

    // Get all registered blocks
    $all_blocks = $registry->get_all_registered();

    $blocks = [];

    foreach ($all_blocks as $block) {
        $blocks[] = [
            'name'       => $block->name,
            'title'      => $block->title ?? '',
            'category'   => $block->category ?? '',
            'description' => $block->description ?? '',
        ];
    }

    return rest_ensure_response($blocks);
}

/**
 * Callback for /blocks/moj endpoint.
 * Returns only registered blocks that start with the 'mojblocks/' namespace.
 */
function hale_components_get_api_moj_blocks_callback()
{
    $registry = WP_Block_Type_Registry::get_instance();
    $blocks = $registry->get_all_registered();

    $moj_blocks = array_filter($blocks, function ($block) {
        return str_starts_with($block->name, 'mojblocks/');
    });

    $data = [];

    foreach ($moj_blocks as $block) {
        $data[] = [
            'name'        => $block->name,
            'title'       => $block->title ?? '',
            'category'    => $block->category ?? '',
            'description' => $block->description ?? '',
        ];
    }

    return rest_ensure_response($data);
}
