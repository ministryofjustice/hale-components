<?php

defined('ABSPATH') || exit;

/**
 * Disables client-side media processing (WordPress 7.1).
 *
 * It turns on for every HTTPS site. The block editor then sends a
 * Document-Isolation-Policy header and adds crossorigin="anonymous" to media,
 * which breaks previews of files served from S3 and the CDN. Image sub-sizes
 * would also be made in the browser, skipping EWWW and the S3 Uploads image
 * editor.
 */
add_filter('wp_client_side_media_processing_enabled', '__return_false');
