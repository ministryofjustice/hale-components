<?php

namespace MOJComponents;

defined('ABSPATH') || exit;

include 'vendor/autoload.php';

use MOJComponents\Helper\Helper;
use MOJComponents\AdminSettings\AdminSettings;
use MOJComponents\Security\Security;
use MOJComponents\Users\Users;
use MOJComponents\Sitemap\Sitemap;
use MOJComponents\Head\Head;
use MOJComponents\Introduce\Introduce;
use MOJComponents\Analytics\Analytics;
use MOJComponents\TaxonomyUpdater\TaxonomyUpdater;
use MOJComponents\AcfFieldUpdater\AcfFieldUpdater;
use MOJComponents\ImportUsers\ImportUsers;

define('MOJ_COMPONENT_PLUGIN_PATH', __FILE__);

global $mojHelper;
$mojHelper = new Helper();

new AdminSettings();

/*****************
 * Load Components
 ******************/

new Security();
new Users();
new Sitemap();
new Head();
new Introduce();
new Analytics();
new TaxonomyUpdater();
new ImportUsers();
new AcfFieldUpdater();