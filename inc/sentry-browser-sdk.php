<?php
/**
 * Sentry Browser SDK override.
 *
 * wp-sentry-integration ships Browser SDK 8.55.0. Its Session Replay compression-worker bridge attaches one
 * "message" listener per request and removes it only when that request's own response arrives, so under a backlog
 * (a slow machine, a long block-editor page, bursts of DOM mutations) every worker response is dispatched to every
 * pending listener, the main thread saturates, timers starve, and the editor appears frozen while still accepting
 * input. Fixed upstream in sentry-javascript 10.51.0 (getsentry/sentry-javascript#20547, PR #20548).
 *
 * Until the plugin bundles a fixed SDK, this swaps the registered bundle script for the vendored CDN build in
 * lib/sentry-browser-sdk/<version>/, keeping the same variant the plugin chose (browser, tracing, replay). The
 * plugin's own public/wp-sentry-init.js still runs unchanged; the APIs it uses (Sentry.init, replayIntegration,
 * browserTracingIntegration, feedbackIntegration) are unchanged in 10.x.
 *
 * It stands down by itself when the plugin's bundled SDK is already at or above HALE_SENTRY_BROWSER_SDK_VERSION,
 * and when the feedback integration is enabled (the plugin's feedback add-on is an 8.x file and must not be paired
 * with a 10.x core). To disable: add_filter('hale_sentry_browser_sdk_override', '__return_false');
 */

const HALE_SENTRY_BROWSER_SDK_VERSION = '10.51.0';

/**
 * Version of the Browser SDK the plugin itself ships, read once from its bundle banner
 * ("@sentry/browser ... 8.55.0 (134fcf3)") and cached per plugin version.
 */
function hale_sentry_plugin_bundled_sdk_version(): string
{
    if (!defined('WP_SENTRY_PLUGIN_FILE')) {
        return '0';
    }
    $file = dirname(WP_SENTRY_PLUGIN_FILE) . '/public/wp-sentry-browser.min.js';
    $stamp = (class_exists('WP_Sentry_Version') ? WP_Sentry_Version::SDK_VERSION : '?') . ':' . (int) @filemtime($file);
    $cached = get_site_option('hale_sentry_bundled_sdk_version');
    if (is_array($cached) && ($cached['stamp'] ?? null) === $stamp) {
        return (string) $cached['version'];
    }
    $version = '0';
    $head = is_readable($file) ? (string) file_get_contents($file, false, null, 0, 1024) : '';
    if (preg_match('/\b(\d+\.\d+\.\d+)\s*\([0-9a-f]{6,}\)/', $head, $m) || preg_match('/["\'](\d+\.\d+\.\d+)["\']/', $head, $m)) {
        $version = $m[1];
    }
    update_site_option('hale_sentry_bundled_sdk_version', ['stamp' => $stamp, 'version' => $version]);
    return $version;
}

function hale_sentry_browser_sdk_override(): void
{
    if (!apply_filters('hale_sentry_browser_sdk_override', true)) {
        return;
    }
    $scripts = wp_scripts();
    if (!isset($scripts->registered['wp-sentry-browser-bundle'])) {
        return;
    }
    $script = $scripts->registered['wp-sentry-browser-bundle'];

    // Which variant did the plugin pick? public/wp-sentry-browser[.tracing][.replay].min.js
    if (!preg_match('#/public/wp-sentry-(browser(?:\.tracing)?(?:\.replay)?)\.min\.js$#', (string) $script->src, $m)) {
        return; // already swapped, or not the plugin's file
    }
    if (defined('WP_SENTRY_BROWSER_FEEDBACK_OPTIONS') && !empty(WP_SENTRY_BROWSER_FEEDBACK_OPTIONS['enabled'])) {
        return; // the 8.x feedback add-on would be paired with a 10.x core
    }
    if (version_compare(hale_sentry_plugin_bundled_sdk_version(), HALE_SENTRY_BROWSER_SDK_VERSION, '>=')) {
        return; // the plugin has caught up; nothing to do
    }
    $variant = str_replace('browser', 'bundle', $m[1]) . '.min.js';
    $relative = 'lib/sentry-browser-sdk/' . HALE_SENTRY_BROWSER_SDK_VERSION . '/' . $variant;
    if (!is_readable(dirname(__DIR__) . '/' . $relative)) {
        return;
    }
    $script->src = plugins_url($relative, dirname(__DIR__) . '/hale-components.php');
    $script->ver = HALE_SENTRY_BROWSER_SDK_VERSION;
}

// The plugin enqueues at priority 0 on these hooks; run just after it.
foreach (['admin_enqueue_scripts', 'login_enqueue_scripts', 'wp_enqueue_scripts'] as $hook) {
    add_action($hook, 'hale_sentry_browser_sdk_override', 1);
}
add_action('wp_print_scripts', 'hale_sentry_browser_sdk_override', 1);
