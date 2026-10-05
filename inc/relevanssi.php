<?php

defined('ABSPATH') || exit;

/**
 * Defaults Relevanssi Premium click tracking to off.
 *
 * Premium's installer runs add_option('relevanssi_click_tracking', 'on') when the
 * plugin is first activated on a site. This flips it to off at that point only, so
 * site admins can still opt in later from the Relevanssi settings.
 */
add_action(
    'add_option_relevanssi_click_tracking',
    fn ($option) => update_option($option, 'off')
);
