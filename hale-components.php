<?php

/*
Plugin Name: Hale Components
Plugin URI: https://github.com/ministryofjustice/hale-components
Description: Functions that are commonly used across the Hale Platform.
Version: 1.15.2
Author: Ministry of Justice
Author URI: https://github.com/ministryofjustice
Text Domain: hale-components
Domain Path: /languages
License: MIT
*/

declare(strict_types=1);

namespace MOJComponents;

defined('ABSPATH') || exit;

define('HALE_COMPONENTS_DIR', plugin_dir_path(__FILE__));
define('HALE_COMPONENTS_URL', plugin_dir_url(__FILE__));
define('MOJ_COMPONENT_PLUGIN_PATH', __FILE__);

include __DIR__ . '/vendor/autoload.php';

use MOJComponents\Helper\Helper;
use MOJComponents\AdminSettings\AdminSettings;
use MOJComponents\Blocks\Blocks;
use MOJComponents\Comments\Comments;
use MOJComponents\SitePathTracker\SitePathTracker;
use MOJComponents\LoginSettings\LoginSettings;
use MOJComponents\CloudFront\CloudFront;
use MOJComponents\SiteUserReports\SiteUserReports;
use MOJComponents\SearchReplaceDatabase\SearchReplaceDatabase;
use MOJComponents\Security\Security;
use MOJComponents\Users\Users;
use MOJComponents\Sitemap\Sitemap;
use MOJComponents\Head\Head;
use MOJComponents\Introduce\Introduce;
use MOJComponents\Analytics\Analytics;
use MOJComponents\TaxonomyUpdater\TaxonomyUpdater;
use MOJComponents\AcfFieldUpdater\AcfFieldUpdater;
use MOJComponents\ImportUsers\ImportUsers;
use MOJComponents\NetworkDashboard\NetworkDashboard;
use MOJComponents\RestApiRoutes\RestApiRoutes;
use MOJComponents\CleanUpUsers\CleanUpUsers;
use MOJComponents\NetworkUserReports\NetworkUserReports;

global $mojHelper;
$mojHelper = new Helper();

new AdminSettings();

/*****************
 * Load Components
 *****************/

new Blocks();
new Comments();
new SitePathTracker();
new LoginSettings();
new CloudFront();
new SiteUserReports();
new SearchReplaceDatabase();
new Security();
new Users();
new Sitemap();
new Head();
new Introduce();
new Analytics();
new TaxonomyUpdater();
new ImportUsers();
new AcfFieldUpdater();

if (is_multisite()) {
    new NetworkDashboard();
    new RestApiRoutes();
    new CleanUpUsers();
    new NetworkUserReports();
}
