<?php

defined("ABSPATH") || exit();

/**
 * Unregisters every ability, from core and from plugins.
 *
 * Abilities can be listed and run over REST (/wp-json/wp-abilities/v1) by
 * logged-in users. Running last on wp_abilities_api_init leaves the registry
 * empty, so the REST routes return an empty list rather than an error.
 *
 * @param WP_Abilities_Registry $registry The abilities registry.
 */
add_action(
	"wp_abilities_api_init",
	static function ($registry) {
		foreach (array_keys($registry->get_all_registered()) as $name) {
			$registry->unregister($name);
		}
	},
	PHP_INT_MAX,
);
