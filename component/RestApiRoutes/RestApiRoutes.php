<?php

declare(strict_types=1);

namespace MOJComponents\RestApiRoutes;

use WP_REST_Response;

/**
 * Registers custom public REST API routes for the hale-components plugin.
 * Only for WP multisite configurations.
 */
class RestApiRoutes
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }

    public function registerEndpoints(): void
    {
        register_rest_route('hc-rest/v1', '/sites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSites'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hc-rest/v1', '/sites/path', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPathSites'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hc-rest/v1', '/sites/domain', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getDomainSites'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hc-rest/v1', '/blocks', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getBlocks'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hc-rest/v1', '/blocks/moj', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getMojBlocks'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** Return a list of all sites in the multisite network. */
    public function getSites(): WP_REST_Response
    {
        $sites = get_sites();
        $data  = [];

        foreach ($sites as $site) {
            $details   = get_blog_details($site->blog_id);
            $url       = $details->siteurl;
            $parsedUrl = parse_url($url);
            $domain    = $parsedUrl['host'] ?? $details->domain;

            $data[] = [
                'blog_id' => $site->blog_id,
                'slug'    => $details->path,
                'url'     => $url,
                'domain'  => $domain,
                'name'    => $details->blogname,
            ];
        }

        return rest_ensure_response($data);
    }

    /** Return sites installed as subdirectories under the root domain. */
    public function getPathSites(): WP_REST_Response
    {
        $sites             = get_sites();
        $subdirectorySites = [];

        foreach ($sites as $site) {
            if (!$site->path || $site->path === '/') {
                continue;
            }

            switch_to_blog($site->blog_id);

            $subdirectorySites[] = [
                'blog_id' => $site->blog_id,
                'slug'    => $site->path,
                'url'     => get_site_url($site->blog_id),
                'name'    => get_bloginfo('name', 'raw'),
            ];

            restore_current_blog();
        }

        return rest_ensure_response($subdirectorySites);
    }

    /** Return sites using a unique domain (not subdirectory-based). */
    public function getDomainSites(): WP_REST_Response
    {
        $sites = get_sites();
        $data  = [];

        foreach ($sites as $site) {
            $details = get_blog_details($site->blog_id);
            $url     = $details->siteurl;

            if (
                strpos($url, 'hale.docker') !== false ||
                strpos($url, '://websitebuilder.service.justice.gov.uk') !== false
            ) {
                continue;
            }

            $parsedUrl = parse_url($url);
            $domain    = $parsedUrl['host'] ?? $details->domain;

            switch_to_blog($site->blog_id);
            $slug = get_option('site_path_slug');
            restore_current_blog();

            if (empty($slug)) {
                $slug = 'site-' . $site->blog_id;
            }

            $data[] = [
                'blogID' => $site->blog_id,
                'path'   => $details->path,
                'slug'   => $slug,
                'url'    => $url,
                'domain' => $domain,
                'name'   => $details->blogname,
            ];
        }

        return rest_ensure_response($data);
    }

    /** Return all registered block types. */
    public function getBlocks(): WP_REST_Response
    {
        $registry  = \WP_Block_Type_Registry::get_instance();
        $allBlocks = $registry->get_all_registered();
        $blocks    = [];

        foreach ($allBlocks as $block) {
            $blocks[] = [
                'name'        => $block->name,
                'title'       => $block->title ?? '',
                'category'    => $block->category ?? '',
                'description' => $block->description ?? '',
            ];
        }

        return rest_ensure_response($blocks);
    }

    /** Return only block types registered under the 'mojblocks/' namespace. */
    public function getMojBlocks(): WP_REST_Response
    {
        $registry  = \WP_Block_Type_Registry::get_instance();
        $allBlocks = $registry->get_all_registered();

        $mojBlocks = array_filter($allBlocks, function ($block) {
            return str_starts_with($block->name, 'mojblocks/');
        });

        $data = [];

        foreach ($mojBlocks as $block) {
            $data[] = [
                'name'        => $block->name,
                'title'       => $block->title ?? '',
                'category'    => $block->category ?? '',
                'description' => $block->description ?? '',
            ];
        }

        return rest_ensure_response($data);
    }
}
