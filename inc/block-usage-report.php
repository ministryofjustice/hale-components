<?php
/**
 * Hale Components — Block Usage Report
 *
 * A network admin screen that answers "where is this block actually used?".
 * Enter a block name (e.g. mojblocks/card) and every site on the network is
 * scanned, one site per AJAX request, listing the posts that use the block,
 * how many times each, per-site subtotals and a network-wide total.
 *
 * Counting is done with parse_blocks() rather than a raw string match, so
 * nested blocks (a card inside a columns block, for example) are included and
 * a block name that is a prefix of another (mojblocks/card vs
 * mojblocks/card-group) is never miscounted.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'HC_BLOCK_USAGE_PAGE' ) ) {
    define( 'HC_BLOCK_USAGE_PAGE', 'hale-components-block-usage' );
}

if ( ! defined( 'HC_BLOCK_USAGE_OPTION' ) ) {
    define( 'HC_BLOCK_USAGE_OPTION', 'hc_block_usage_seen_blocks' );
}

/**
 * Capability required to run the report.
 *
 * @return string
 */
function hc_block_usage_capability() {
    return is_multisite() ? 'manage_network_options' : 'manage_options';
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', 'hc_block_usage_menu' );

/**
 * Register the report under Settings.
 */
function hc_block_usage_menu() {
    add_submenu_page(
        is_multisite() ? 'settings.php' : 'tools.php',
        __( 'Block Usage', 'hale-components' ),
        __( 'Block Usage', 'hale-components' ),
        hc_block_usage_capability(),
        HC_BLOCK_USAGE_PAGE,
        'hc_block_usage_render_page'
    );
}

/**
 * Render the page.
 */
function hc_block_usage_render_page() {
    if ( ! current_user_can( hc_block_usage_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'hale-components' ) );
    }

    echo '<div class="wrap">';
    include plugin_dir_path( __FILE__ ) . '/parts/block-usage.php';
    echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'hc_block_usage_enqueue' );

/**
 * Enqueue the dashboard stylesheet and the scanner script on this screen only.
 */
function hc_block_usage_enqueue() {
    if ( ! isset( $_GET['page'] ) || HC_BLOCK_USAGE_PAGE !== $_GET['page'] ) {
        return;
    }

    $css      = '../dist/css/hc-network-dashboard.css';
    $js       = '../dist/js/hc-block-usage.js';
    $css_path = plugin_dir_path( __FILE__ ) . $css;
    $js_path  = plugin_dir_path( __FILE__ ) . $js;

    wp_enqueue_style(
        'hc-block-usage',
        plugins_url( $css, __FILE__ ),
        array(),
        file_exists( $css_path ) ? filemtime( $css_path ) : false
    );

    wp_enqueue_script(
        'hc-block-usage',
        plugins_url( $js, __FILE__ ),
        array(),
        file_exists( $js_path ) ? filemtime( $js_path ) : false,
        true
    );

    wp_localize_script(
        'hc-block-usage',
        'hcBlockUsage',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'hc_block_usage_scan' ),
            'sites'   => hc_block_usage_sites(),
            'i18n'    => array(
                'scanning'   => __( 'Scanning %1$s of %2$s sites…', 'hale-components' ),
                'done'       => __( 'Scanned %1$s sites in %2$ss.', 'hale-components' ),
                'stopped'    => __( 'Stopped after %1$s of %2$s sites.', 'hale-components' ),
                'none'       => __( 'No usage of %s found anywhere on the network.', 'hale-components' ),
                'instances'  => __( 'Total instances', 'hale-components' ),
                'posts'      => __( 'Posts using it', 'hale-components' ),
                'sitesUsing' => __( 'Sites using it', 'hale-components' ),
                'post'       => __( 'Post', 'hale-components' ),
                'type'       => __( 'Type', 'hale-components' ),
                'status'     => __( 'Status', 'hale-components' ),
                'used'       => __( 'Instances', 'hale-components' ),
                'actions'    => __( 'Actions', 'hale-components' ),
                'view'       => __( 'View', 'hale-components' ),
                'edit'       => __( 'Edit', 'hale-components' ),
                'subtotal'   => __( '%1$s instances across %2$s posts', 'hale-components' ),
                'siteId'     => __( 'Site ID %s', 'hale-components' ),
                'error'      => __( 'Could not scan %s.', 'hale-components' ),
                'invalid'    => __( 'Enter a block name, for example mojblocks/card.', 'hale-components' ),
            ),
        )
    );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Every site on the network (id, name, url), or just this site if not multisite.
 *
 * @return array
 */
function hc_block_usage_sites() {
    static $sites = null;

    if ( null !== $sites ) {
        return $sites;
    }

    if ( ! is_multisite() ) {
        $sites = array(
            array(
                'id'   => get_current_blog_id(),
                'name' => get_bloginfo( 'name' ),
                'url'  => home_url( '/' ),
            ),
        );

        return $sites;
    }

    $sites    = array();
    $site_ids = get_sites(
        apply_filters(
            'hc_block_usage_get_sites_args',
            array(
                'fields'   => 'ids',
                'number'   => 0,
                'archived' => 0,
                'deleted'  => 0,
                'spam'     => 0,
            )
        )
    );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        $sites[] = array(
            'id'   => (int) $site_id,
            'name' => get_bloginfo( 'name' ),
            'url'  => home_url( '/' ),
        );
        restore_current_blog();
    }

    return $sites;
}

/**
 * Normalise a user-supplied block name.
 *
 * Core blocks may be typed either way ("paragraph" or "core/paragraph") and
 * always come back namespaced. Returns '' if the name is not a valid block
 * name, which the callers treat as a hard failure.
 *
 * @param string $raw Raw input.
 * @return string Normalised block name, or ''.
 */
function hc_block_usage_sanitize_block_name( $raw ) {
    // Deliberately validate rather than strip: a name containing anything
    // unexpected is rejected outright, not quietly rewritten into a different
    // (valid looking) block name.
    $name = strtolower( trim( (string) $raw ) );

    if ( '' === $name ) {
        return '';
    }

    if ( false === strpos( $name, '/' ) ) {
        $name = 'core/' . $name;
    }

    if ( ! preg_match( '#^[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $name ) ) {
        return '';
    }

    return $name;
}

/**
 * The name as it appears in the block delimiter comment.
 *
 * Core blocks are serialised without their namespace: core/gallery is written
 * to post_content as <!-- wp:gallery -->.
 *
 * @param string $name Normalised block name.
 * @return string
 */
function hc_block_usage_delimiter_name( $name ) {
    return 0 === strpos( $name, 'core/' ) ? substr( $name, 5 ) : $name;
}

/**
 * Count occurrences of a block in a parsed block tree, including inner blocks.
 *
 * @param array  $blocks Parsed blocks.
 * @param string $name   Block name to count.
 * @return int
 */
function hc_block_usage_count_blocks( $blocks, $name ) {
    $count = 0;

    foreach ( (array) $blocks as $block ) {
        if ( isset( $block['blockName'] ) && $block['blockName'] === $name ) {
            $count++;
        }

        if ( ! empty( $block['innerBlocks'] ) ) {
            $count += hc_block_usage_count_blocks( $block['innerBlocks'], $name );
        }
    }

    return $count;
}

/**
 * Collect every block name in a parsed tree, so the autocomplete list learns
 * real block names as the report is used.
 *
 * @param array $blocks Parsed blocks.
 * @param array $seen   Accumulator, passed by reference.
 */
function hc_block_usage_collect_block_names( $blocks, &$seen ) {
    foreach ( (array) $blocks as $block ) {
        if ( ! empty( $block['blockName'] ) ) {
            $seen[ $block['blockName'] ] = true;
        }

        if ( ! empty( $block['innerBlocks'] ) ) {
            hc_block_usage_collect_block_names( $block['innerBlocks'], $seen );
        }
    }
}

/**
 * Block names offered in the search box datalist: everything registered in this
 * request, plus everything previous scans have seen on the network.
 *
 * @return array
 */
function hc_block_usage_known_block_names() {
    $names = array();

    if ( class_exists( 'WP_Block_Type_Registry' ) ) {
        $names = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
    }

    $stored = is_multisite()
        ? get_site_option( HC_BLOCK_USAGE_OPTION, array() )
        : get_option( HC_BLOCK_USAGE_OPTION, array() );

    if ( is_array( $stored ) ) {
        $names = array_merge( $names, $stored );
    }

    $names = array_values( array_unique( array_filter( $names ) ) );
    sort( $names );

    return apply_filters( 'hc_block_usage_known_block_names', $names );
}

/**
 * Remember block names discovered during a scan.
 *
 * @param array $names Block names.
 */
function hc_block_usage_remember_block_names( $names ) {
    $names = array_values( array_filter( (array) $names ) );

    if ( empty( $names ) ) {
        return;
    }

    $stored = is_multisite()
        ? get_site_option( HC_BLOCK_USAGE_OPTION, array() )
        : get_option( HC_BLOCK_USAGE_OPTION, array() );

    if ( ! is_array( $stored ) ) {
        $stored = array();
    }

    $merged = array_values( array_unique( array_merge( $stored, $names ) ) );
    sort( $merged );

    if ( count( $merged ) > 500 ) {
        $merged = array_slice( $merged, 0, 500 );
    }

    if ( $merged === $stored ) {
        return;
    }

    if ( is_multisite() ) {
        update_site_option( HC_BLOCK_USAGE_OPTION, $merged );
    } else {
        update_option( HC_BLOCK_USAGE_OPTION, $merged, false );
    }
}

/* -------------------------------------------------------------------------
 * The scan
 * ---------------------------------------------------------------------- */

/**
 * Scan a single site for a block.
 *
 * @param int    $site_id Blog ID.
 * @param string $block   Normalised block name.
 * @return array Site details, matching posts and totals.
 */
function hc_block_usage_scan_site( $site_id, $block ) {
    $site_id  = (int) $site_id;
    $switched = false;

    if ( is_multisite() && $site_id && get_current_blog_id() !== $site_id ) {
        switch_to_blog( $site_id );
        $switched = true;
    }

    global $wpdb;

    $needle = '%<!-- wp:' . $wpdb->esc_like( hc_block_usage_delimiter_name( $block ) ) . ' %';

    // Revisions carry a copy of the post content and would double-count every
    // edit; the rest are internal post types that never hold editorial blocks.
    $skip_types = apply_filters(
        'hc_block_usage_excluded_post_types',
        array( 'revision', 'wp_global_styles', 'oembed_cache', 'customize_changeset', 'user_request' )
    );

    $type_clause = '';
    if ( ! empty( $skip_types ) ) {
        $type_clause = ' AND post_type NOT IN (' . implode( ',', array_fill( 0, count( $skip_types ), '%s' ) ) . ')';
    }

    $batch      = 100;
    $last_id    = 0;
    $posts      = array();
    $instances  = 0;
    $seen_names = array();

    do {
        $sql = "SELECT ID, post_title, post_type, post_status, post_content
                  FROM {$wpdb->posts}
                 WHERE ID > %d
                   AND post_content LIKE %s
                   AND post_status NOT IN ('trash','auto-draft','inherit')
                   {$type_clause}
              ORDER BY ID ASC
                 LIMIT %d";

        $params = array_merge( array( $last_id, $needle ), $skip_types, array( $batch ) );
        $rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

        foreach ( $rows as $row ) {
            $last_id = (int) $row->ID;
            $parsed  = parse_blocks( $row->post_content );

            hc_block_usage_collect_block_names( $parsed, $seen_names );

            $count = hc_block_usage_count_blocks( $parsed, $block );

            if ( ! $count ) {
                continue;
            }

            $instances += $count;

            $edit_link = get_edit_post_link( $row->ID, 'raw' );
            if ( ! $edit_link ) {
                $edit_link = admin_url( 'post.php?post=' . (int) $row->ID . '&action=edit' );
            }

            $posts[] = array(
                'id'     => (int) $row->ID,
                'title'  => '' !== trim( (string) $row->post_title )
                    ? $row->post_title
                    : sprintf( __( '(no title) #%d', 'hale-components' ), (int) $row->ID ),
                'type'   => $row->post_type,
                'status' => $row->post_status,
                'count'  => $count,
                'view'   => (string) get_permalink( $row->ID ),
                'edit'   => (string) $edit_link,
            );
        }
    } while ( count( $rows ) === $batch );

    // Busiest posts first.
    usort(
        $posts,
        static function ( $a, $b ) {
            return $b['count'] <=> $a['count'];
        }
    );

    $site = array(
        'id'   => $site_id ? $site_id : get_current_blog_id(),
        'name' => get_bloginfo( 'name' ),
        'url'  => home_url( '/' ),
    );

    if ( $switched ) {
        restore_current_blog();
    }

    hc_block_usage_remember_block_names( array_keys( $seen_names ) );

    return array(
        'site'      => $site,
        'posts'     => $posts,
        'instances' => $instances,
        'postCount' => count( $posts ),
    );
}

/* -------------------------------------------------------------------------
 * AJAX
 * ---------------------------------------------------------------------- */

add_action( 'wp_ajax_hc_block_usage_scan', 'hc_block_usage_ajax_scan' );

/**
 * Scan one site and return its results as JSON.
 */
function hc_block_usage_ajax_scan() {
    check_ajax_referer( 'hc_block_usage_scan', 'nonce' );

    if ( ! current_user_can( hc_block_usage_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to run this report.', 'hale-components' ) ), 403 );
    }

    $block = hc_block_usage_sanitize_block_name( isset( $_POST['block'] ) ? wp_unslash( $_POST['block'] ) : '' );

    if ( '' === $block ) {
        wp_send_json_error( array( 'message' => __( 'That is not a valid block name.', 'hale-components' ) ), 400 );
    }

    $site_id = isset( $_POST['site_id'] ) ? (int) $_POST['site_id'] : 0;

    if ( is_multisite() ) {
        if ( ! $site_id || ! get_site( $site_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Unknown site.', 'hale-components' ) ), 400 );
        }
    } else {
        $site_id = get_current_blog_id();
    }

    wp_send_json_success( hc_block_usage_scan_site( $site_id, $block ) );
}

/* -------------------------------------------------------------------------
 * CSV export
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'hc_block_usage_maybe_export_csv' );

/**
 * Stream the results already gathered in the browser as a CSV.
 *
 * The rows are posted back rather than re-scanned so the download is instant
 * and can't disagree with what is on screen.
 */
function hc_block_usage_maybe_export_csv() {
    if ( empty( $_POST['hc_block_usage_export'] ) ) {
        return;
    }

    if ( ! current_user_can( hc_block_usage_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'hale-components' ) );
    }

    check_admin_referer( 'hc_block_usage_export_action', 'hc_block_usage_export_nonce' );

    $block = hc_block_usage_sanitize_block_name( isset( $_POST['hc_block_usage_block'] ) ? wp_unslash( $_POST['hc_block_usage_block'] ) : '' );
    $raw   = isset( $_POST['hc_block_usage_data'] ) ? wp_unslash( $_POST['hc_block_usage_data'] ) : '';
    $rows  = json_decode( $raw, true );

    if ( '' === $block || ! is_array( $rows ) || empty( $rows ) ) {
        wp_die( esc_html__( 'Nothing to export — run a scan first.', 'hale-components' ) );
    }

    $file_name = 'block-usage-' . str_replace( '/', '-', $block ) . '-' . gmdate( 'Ymd-His' ) . '.csv';

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );

    $output = fopen( 'php://output', 'w' );

    fputcsv( $output, array( 'Block', 'Site ID', 'Site', 'Site URL', 'Post ID', 'Post title', 'Post type', 'Status', 'Instances', 'URL', 'Edit URL' ) );

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        fputcsv(
            $output,
            array_map(
                'hc_block_usage_csv_cell',
                array(
                    $block,
                    isset( $row['siteId'] ) ? (int) $row['siteId'] : '',
                    isset( $row['siteName'] ) ? $row['siteName'] : '',
                    isset( $row['siteUrl'] ) ? esc_url_raw( $row['siteUrl'] ) : '',
                    isset( $row['id'] ) ? (int) $row['id'] : '',
                    isset( $row['title'] ) ? $row['title'] : '',
                    isset( $row['type'] ) ? $row['type'] : '',
                    isset( $row['status'] ) ? $row['status'] : '',
                    isset( $row['count'] ) ? (int) $row['count'] : 0,
                    isset( $row['view'] ) ? esc_url_raw( $row['view'] ) : '',
                    isset( $row['edit'] ) ? esc_url_raw( $row['edit'] ) : '',
                )
            )
        );
    }

    fclose( $output );
    exit;
}

/**
 * Sanitise a CSV cell, neutralising spreadsheet formula injection.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function hc_block_usage_csv_cell( $value ) {
    $value = sanitize_text_field( (string) $value );

    if ( '' !== $value && strpos( "=+-@\t\r", $value[0] ) !== false ) {
        $value = "'" . $value;
    }

    return $value;
}
