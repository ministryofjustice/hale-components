<?php
/**
 * Block Usage report — screen markup.
 *
 * Results are rendered by dist/js/hc-block-usage.js as each site is scanned.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$hc_block_usage_names = hc_block_usage_known_block_names();
$hc_block_usage_count = count( hc_block_usage_sites() );
?>

<h1><?php esc_html_e( 'Block Usage', 'hale-components' ); ?></h1>

<p>
    <?php
    echo esc_html(
        sprintf(
            /* translators: %s: number of sites. */
            _n(
                'Find out where a block is used across the %s site on this network.',
                'Find out where a block is used across all %s sites on this network.',
                $hc_block_usage_count,
                'hale-components'
            ),
            number_format_i18n( $hc_block_usage_count )
        )
    );
    ?>
    <?php esc_html_e( 'Sites are scanned one at a time, so you can watch the totals build up and stop at any point.', 'hale-components' ); ?>
</p>

<form id="hc-block-usage-form" class="hc-dashboard-form">
    <label for="hc-block-usage-name"><strong><?php esc_html_e( 'Block name', 'hale-components' ); ?></strong></label>
    <p class="description">
        <?php esc_html_e( 'For example mojblocks/card. Core blocks can be entered either way — "gallery" or "core/gallery".', 'hale-components' ); ?>
    </p>
    <input
        type="text"
        id="hc-block-usage-name"
        name="block"
        class="regular-text code"
        list="hc-block-usage-names"
        placeholder="mojblocks/card"
        autocomplete="off"
        spellcheck="false"
        required
    >
    <datalist id="hc-block-usage-names">
        <?php foreach ( $hc_block_usage_names as $hc_block_usage_name ) : ?>
            <option value="<?php echo esc_attr( $hc_block_usage_name ); ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <button type="submit" class="button button-primary" id="hc-block-usage-submit">
        <?php esc_html_e( 'Scan network', 'hale-components' ); ?>
    </button>
    <button type="button" class="button" id="hc-block-usage-stop" hidden>
        <?php esc_html_e( 'Stop', 'hale-components' ); ?>
    </button>
</form>

<div id="hc-block-usage-progress" class="hc-block-usage-progress" hidden>
    <p class="hc-block-usage-progress-text" role="status" aria-live="polite"></p>
    <div class="hc-block-usage-bar"><span></span></div>
</div>

<div id="hc-block-usage-summary" class="hc-block-usage-summary" hidden></div>

<form method="post" id="hc-block-usage-export-form" class="hc-block-usage-export" hidden>
    <?php wp_nonce_field( 'hc_block_usage_export_action', 'hc_block_usage_export_nonce' ); ?>
    <input type="hidden" name="hc_block_usage_block" id="hc-block-usage-export-block" value="">
    <input type="hidden" name="hc_block_usage_data" id="hc-block-usage-export-data" value="">
    <button type="submit" class="button" name="hc_block_usage_export" value="1">
        <?php esc_html_e( 'Download CSV', 'hale-components' ); ?>
    </button>
</form>

<div id="hc-block-usage-results"></div>

<p id="hc-block-usage-empty" hidden></p>
