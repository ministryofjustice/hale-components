<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reject anonymous requests to the core REST batch endpoint.
 *
 * @param mixed           $result  Pre-calculated dispatch result.
 * @param WP_REST_Server  $server  REST server instance.
 * @param WP_REST_Request $request Current REST request.
 * @return mixed|WP_Error
 */
function wporg_require_authentication_for_rest_batch( $result, $server, $request ) {
    if ( '/batch/v1' !== strtolower( untrailingslashit( $request->get_route() ) ) || is_user_logged_in() ) {
        return $result;
    }

    return new WP_Error(
        'rest_batch_authentication_required',
        'Authentication is required to use the batch API.',
        array( 'status' => 401 )
    );
}

add_filter( 'rest_pre_dispatch', 'wporg_require_authentication_for_rest_batch', -1000, 3 );