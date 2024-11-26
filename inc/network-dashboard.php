<?php
/**
 * Hale Components Network Dashboard
 */

add_action( 'network_admin_menu', 'hale_components_multisite_dashboard_page' );

/**
 * Add the dashboard page under Settings in the network admin menu.
 */
function hale_components_multisite_dashboard_page() {
    add_submenu_page(
        'settings.php',
        'Hale Components Network Dashboard',
        'Hale Components',  // Menu title
        'manage_network_options',
        'hale-components-network-dashboard',  // Slug for the page
        'hale_components_network_dashboard_content'  // Callback
    );
}

/**
 * Callback function to display the content of the custom dashboard page.
 */
function hale_components_network_dashboard_content() {
    // Check if the WB_CONFIG cookie is present
    $cookie_present = hc_is_waf_bypass_cookie_present();

    $cookie_message = $cookie_present 
    ? __('<span class="status-on">ON</span> WB_CONFIG cookie is present.', 'hale-components') 
    : __('<span class="status-off">OFF</span> WB_CONFIG cookie is not present.', 'hale-components');


    // Define text for the WAF bypass information
    $waf_description_panel_text = 'To avoid WAF rules disrupting editors working in the backend,<br>
    all logged-in users are assigned the WB_CONFIG cookie. The presence of the WB_CONFIG cookie and value<br>
    disables WAF running. Subscribers, however, are an exception and are still subject to WAF rules.';

    $waf_body_panel_text = 'The WB_CONFIG cookie is set with a value<br>
    provided by GitActions. Our ingress configuation uses NGINX to apply a WAF but
    skips WAF rules if the cookie and the correct value are present.';
    ?>
    <div class="wrap">
        <h1><?php _e( 'Hale Components Network Dashboard', 'hale-components' ); ?></h1>
        <p><?php _e( 'Hale Components Platform wide network settings page.', 'hale-components' ); ?></p>
        
        <!-- Grid layout -->
        <div class="hale-dashboard-grid">
            <!-- First row: WAF bypass information -->
            <div class="hale-dashboard-item">
                <div class="hale-dashboard-left">
                    <h4><?php _e( 'WAF Bypass Status', 'hale-components' ); ?></h4>
                    <p><?php echo $cookie_message; ?></p>
                </div>
                <div class="hale-dashboard-right">
                    <h4><?php _e( 'What is the WB_CONFIG cookie?', 'hale-components' ); ?></h4>
                    <p><?php echo $waf_description_panel_text; ?></p>
                    <p><?php echo $waf_body_panel_text; ?></p>
                </div>
            </div>
        </div>
    </div>
    <style>
        /* Grid container */
        .hale-dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Two equal columns */
            gap: 20px;
            margin-top: 20px;
        }

        /* Grid item */
        .hale-dashboard-item {
            display: flex;
            flex-wrap: wrap;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
        }

        .hale-dashboard-left {
            flex: 1;
            padding-right: 20px;
            border-right: 1px solid #ddd;
        }

        .hale-dashboard-right {
            flex: 1;
            padding-left: 20px;
        }

        .hale-dashboard-left h4,
        .hale-dashboard-right h4 {
            margin-top: 0;
        }

        /* Green box for "On:" */
        .status-on {
            display: inline-block;
            background-color: #28a745; /* Green background */
            color: #fff; /* White text */
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        /* Optional: Style for "Off:" */
        .status-off {
            display: inline-block;
            background-color: #dc3545; /* Red background */
            color: #fff; /* White text */
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
    <?php
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
        setcookie('WB_CONFIG', $wb_config_env_value, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
    }
}