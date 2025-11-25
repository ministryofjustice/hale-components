<?php
/*
    Functions that alter blocks
*/

//Accessbility fix for table block headers
add_filter( 'render_block_core/table', function( $block_content, $block ) {

    // Only process if any <th> exists
    if ( strpos( $block_content, '<th' ) === false ) {
        return $block_content;
    }

    // Add scope="col" only to <th ...> and NOT <thead ...>
    $block_content = preg_replace(
        '/<th\b(?![^>]*\bscope=)/',
        '<th scope="col"',
        $block_content
    );

    return $block_content;

}, 10, 2 );
