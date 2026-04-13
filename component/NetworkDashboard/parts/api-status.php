<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (empty($GLOBALS['wp_rest_server'])) {
    require_once ABSPATH . 'wp-includes/rest-api.php';
    $GLOBALS['wp_rest_server'] = new \WP_REST_Server();
    do_action('rest_api_init', $GLOBALS['wp_rest_server']);
}

$wpRestServer = $GLOBALS['wp_rest_server'];
$routes       = $wpRestServer->get_routes();

$customRoutes = array_filter(array_keys($routes), function (string $route): bool {
    return str_starts_with($route, '/hc-rest/');
});

$apiEndpointText = 'This plugin registers a custom rest API "/hc-rest" with WordPress.<br>
    These endpoints support various tools used to support the platform.<br>
    As a general rule, extend rather than replace these APIs.<br><br>
    An example of one of the routes: https://websitebuilder.service.justice.gov.uk/wp-json/hc-rest/v1/sites/domain';
?>

<div class="hc-dashboard-grid">
    <div class="hc-dashboard-item">
        <div class="hc-dashboard-left">
            <h4><?php _e('API Endpoints', 'hale-components'); ?></h4>
            <?php if (!empty($customRoutes)) : ?>
                <ul>
                    <?php foreach ($customRoutes as $route) : ?>
                        <li><code><?php echo esc_html($route); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>No custom endpoints registered.</p>
            <?php endif; ?>
        </div>
        <div class="hc-dashboard-right">
            <h4><?php _e('Hale Component custom API endpoints', 'hale-components'); ?></h4>
            <p><?php echo $apiEndpointText; ?></p>
        </div>
    </div>
</div>
