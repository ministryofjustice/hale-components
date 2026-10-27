<?php

defined('ABSPATH') || exit;

// Disables the "custom CSS" field found in the advanced section of most blocks
// This feature was rolled out in WP 7.0 in May 2026
add_filter( 'register_block_type_args', function ( $args ) {
    $args['supports'] = $args['supports'] ?? [];
    $args['supports']['customCSS'] = false;

    return $args;
}, 100 );
