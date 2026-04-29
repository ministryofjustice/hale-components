<?php

defined('ABSPATH') || exit;

add_filter('plugins_url', 'hale_components_reformat_plugin_urls', 10, 3);

/**
 * Replace symlink mount path (/mnt/dev/) with target path (/var/www/html/wp-content/).
 *
 * This fix is not specific to this plugin; it will apply anywhere plugins_url is called.
 * It fixes an issue where the value for `$plugin` is a path that WordPress doesn't recognise,
 * e.g. /mnt/dev/mu-plugins/hale-components/moj-components/component/Users/UserSwitch.php
 *
 * - A find and replace is used to reformat the `$plugin` value.
 * - plugins_url is called with this reformatted value.
 * - This URL is returned.
 *
 * @param string $url    The complete URL to the plugins directory including scheme and path.
 * @param string $path   Path relative to the URL to the plugins directory. Blank string
 *                       if no path is specified.
 * @param string $plugin The plugin file path to be relative to. Blank string if no plugin
 *                       is specified.
 */
function hale_components_reformat_plugin_urls($url, $path, $plugin)
{
    // If $plugin doesn't start with the mount path, then do nothing.
    if (!str_starts_with($plugin, '/mnt/dev/')) {
        return $url;
    }

    // Replace symlink mount path (/mnt/dev) with 
    // target path WP_CONTENT_DIR (e.g. /var/www/html/wp-content).
    $plugin_reformatted = str_replace('/mnt/dev', WP_CONTENT_DIR, $plugin);

    // Recall plugins_url with the reformatted path.
    // No infinite loop, because we replaced `/mnt/dev`
    return plugins_url($path, $plugin_reformatted);
}
