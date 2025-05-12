<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo wp_kses_post( __( '<h1>Hale Components Network Dashboard</h1>', 'hale-components' ) );
echo wp_kses_post( __( '<p>Hale Components Platform wide network settings page.</p>', 'hale-components' ) );

