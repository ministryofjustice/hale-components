<?php

defined('ABSPATH') || exit;

/**
 * Add support for Apple and other new formats
 */
add_filter( 'upload_mimes', function( $mimes ) {
	$mimes['heic'] = 'image/heic';
	$mimes['heif'] = 'image/heif';
	return $mimes;
} );

/**
 * Makes the default size/resolution for image block "medium"
 */
add_action( 'after_setup_theme', function() {
	if ( get_option( 'image_default_size' ) !== 'medium' ) {
		update_option( 'image_default_size', 'medium' );
	}
} );
