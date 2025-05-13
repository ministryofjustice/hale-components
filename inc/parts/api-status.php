<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Wait and get rest server info
if (empty($GLOBALS['wp_rest_server'])) {
    require_once ABSPATH . 'wp-includes/rest-api.php';
	$GLOBALS['wp_rest_server'] = new \WP_REST_Server();
    do_action('rest_api_init', $GLOBALS['wp_rest_server']);
}

$wp_rest_server = $GLOBALS['wp_rest_server'];

// Fetch all registered REST API routes
$routes = $wp_rest_server->get_routes();

// Filter and display only custom routes (e.g., starting with /hc-rest/)
$custom_routes = array_filter(array_keys($routes), function ($route) {
    return str_starts_with($route, '/hc-rest/');
});

$api_endpoint_text = 'This plugin registers a custom rest API "/hc-rest" with Wordpress.<br>
					 These endpoints support various tools used to support the platform.<br>
					 As a general rule, extend rather then replace these APIs.';
?>

<!-- Custom API endpoints section -->

<div class="hc-dashboard-grid">

<div class="hc-dashboard-item">
    <div class="hc-dashboard-left">
        <h4><?php _e('API Endpoints', 'hale-components'); ?></h4>
        <?php if (!empty($custom_routes)) : ?>
            <ul>
                <?php foreach ($custom_routes as $route) : ?>
                    <li><code><?php echo esc_html($route); ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>No custom endpoints registered.</p>
        <?php endif; ?>
    </div>
		<div class="hc-dashboard-right">
            <h4><?php _e( 'Hale Component custom API endpoints', 'hale-components' ); ?></h4>
            <p><?php echo $api_endpoint_text; ?></p>
        </div>
	</div>
</div>

