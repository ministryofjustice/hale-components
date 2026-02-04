<?php
/**
 * Hale Components Network Dashboard
 */

 add_action('admin_enqueue_scripts', 'hc_network_dashboard_enqueue'); 

function hc_network_dashboard_enqueue() {
    // Get the current screen object
    $screen = get_current_screen();

    // Check we are on the specific admin page
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'hale-components-network-dashboard' ) {
        // Path to the CSS file
        $css_file = '../dist/css/hc-network-dashboard.css';

        // Get the file modification time for cache busting
        $file_version = filemtime( plugin_dir_path( __FILE__ ) . $css_file );

        // Register and enqueue the style with the cache-busted version
        wp_register_style(
            'network_dashboard',
            plugins_url( $css_file, __FILE__ ) . '?v=' . $file_version, // Append version for cache busting
            array(), // Dependencies (empty array means no dependencies)
            null, // No need for a version since we're using filemtime for cache busting
            'all' // Media (all for all devices)
        );
        wp_enqueue_style( 'network_dashboard' );
    }
}

add_action( 'network_admin_menu', 'hc_network_dashboard_page' );

/**
 * Add the dashboard page under Settings in the network admin menu.
 */
function hc_network_dashboard_page() {
    add_submenu_page(
        'settings.php',
        'Hale Components Network Dashboard',
        'Hale Components',  // Menu title
        'manage_network_options',
        'hale-components-network-dashboard',  // Slug for the page
        'hc_network_dashboard_content'  // Callback
    );
}

/**
 * Callback function to display the content of the custom dashboard page.
 */
function hc_network_dashboard_content() {
	echo '<div class="wrap">';	    
	include plugin_dir_path( __FILE__ ) . '/parts/dashboard-intro.php';
   	include plugin_dir_path( __FILE__ ) . '/parts/waf-status.php';
  	include plugin_dir_path( __FILE__ ) . '/parts/api-status.php';
	echo '</div>';
}

/**
 * Function to retrieve the value of an environment variable.
 * Checks both ENV and SERVER
 * 
 * @param string $key The name of the environment variable.
 * @param mixed $default A default value if the environment variable is not set.
 * 
 * @return string The value of the environment variable or the default value.
 */
function hc_get_env_variable($key, $default = '') {
    // Check if the environment variable is set in $_ENV or $_SERVER
    $value = getenv($key) ?: ($_ENV[$key] ?? $_SERVER[$key] ?? $default);

    return $value;
}

/**
 * Detect if the WB_CONFIG cookie is present.
 *
 * @return bool True if the cookie is present, false otherwise.
 */
function hc_is_waf_bypass_cookie_present() {
    // Check if the WB_CONFIG cookie is set and not empty
    if (isset($_COOKIE['WB_CONFIG']) && !empty($_COOKIE['WB_CONFIG'])) {
        return true;
    }

    return false;
}

add_action('init', 'hc_set_waf_bypass_cookie');

/**
 * Set a WAF bypass cookie for logged-in users if the setting is enabled.
 * // Excludes subscribers (they will still get WAF)
 */
function hc_set_waf_bypass_cookie()
{
    // Value provided into the container via GitAction secrets
    $wb_config_env_value = hc_get_env_variable('WB_CONFIG');

    // Setting to edit posts excludes subscribers
    if (current_user_can('edit_posts')) {
        setcookie('WB_CONFIG', $wb_config_env_value, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
}