<?php

declare(strict_types=1);

namespace MOJComponents\SitePathTracker;

use WP_Site;

class SitePathTracker
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('wp_initialize_site', [$this, 'setNewSitePathSlug'], 900);
        add_action('wp_update_site', [$this, 'updateSitePathSlug'], 900);
    }

    /** Set site_path_slug when a new site is created. */
    public function setNewSitePathSlug(WP_Site $site): void
    {
        switch_to_blog((int) $site->blog_id);

        $sitePathSlug = trim($site->path, '/');

        if (!empty($sitePathSlug) && get_option('site_path_slug') === false) {
            add_option('site_path_slug', $sitePathSlug);
        }

        restore_current_blog();
    }

    /** Update site_path_slug when a site's path changes. */
    public function updateSitePathSlug(WP_Site $site): void
    {
        switch_to_blog((int) $site->blog_id);

        $sitePathSlug = trim($site->path, '/');

        if (!empty($sitePathSlug)) {
            update_option('site_path_slug', $sitePathSlug);
        }

        restore_current_blog();
    }
}
