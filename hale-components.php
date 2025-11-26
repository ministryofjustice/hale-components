<?php

/*
Plugin Name: Hale Components
Plugin URI: https://github.com/ministryofjustice/hale-components
Description: Functions that are commonly used across the Hale Platform.
Version: 1.9.0
Author: Ministry of Justice
Author URI: https://github.com/ministryofjustice
Text Domain: hale-components
Domain Path: /languages
License: MIT

*/

// Include additional functionality/tools not in tabs
include 'inc/search-replace-database.php'; 
include 'inc/site-path-track.php'; 
include 'inc/login-settings.php';
include 'inc/blocks.php';

// Only include the network dashboard if this is a multisite setup
if (is_multisite()) {
    include 'inc/network-dashboard.php'; 
	include 'inc/register-rest-api-routes.php';
    include 'inc/clean-up-users.php'; 
    include 'inc/user-reports.php'; 
}

include 'moj-components/moj-components.php'; 

