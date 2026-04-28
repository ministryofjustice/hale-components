<?php

/**
 * Replace symlink mount path (/mnt/dev/) with target path (/var/www/html/wp-content/).
 *
 * This. fix is not specific for this plugin, it will apply anywhere plugins_url is called.
 * It fixes an issue where the value for `$plugin` is a path that WordPress doesn't recognise,
 * e.g. /mnt/dev/mu-plugins/hale-components/moj-components/component/Users/UserSwitch.php
 *
 * - A find and replace is used to reformat the `$plugin` value.
 * - plugins_url is called with this reformatted value.
 * - this URL is returned.
 */
add_filter('plugins_url', function ($url, $path, $plugin) {
    // If $plugin doesn't start with the mount path, then do noting.
    if (!str_starts_with($plugin, '/mnt/dev/')) {
        return $url;
    }

    // Replace symlink mount path (/mnt/dev/) with target path (/var/www/html/wp-content/)
    $plugin_reformatted = str_replace('/mnt/dev/', '/var/www/html/wp-content/', $plugin);

    // Recall plugins_url with the reformatted path.
    return plugins_url($path, $plugin_reformatted);
}, 10, 3);
