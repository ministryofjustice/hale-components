<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cookie_present = hc_is_waf_bypass_cookie_present();

$cookie_message = $cookie_present 
    ? __('<span class="hc-status-on">ON</span> WB_CONFIG cookie is present.', 'hale-components') 
    : __('<span class="hc-status-off">OFF</span> WB_CONFIG cookie is not present.', 'hale-components');


    // Define text for the WAF bypass information
    $waf_description_panel_text = 'To avoid WAF rules disrupting editors working in the backend,<br>
    all logged-in users are assigned the WB_CONFIG cookie. The presence of the WB_CONFIG cookie and value
    disables WAF running.<br>Subscribers, however, are an exception and are still subject to WAF rules.';

    $waf_body_panel_text = 'The WB_CONFIG cookie is set with a value
    provided by GitActions.<br> Our ingress configuation uses NGINX to apply a WAF but
    skips WAF rules if the cookie and the correct value are present.';
    ?>
        
        <!-- Grid layout -->
        <div class="hc-dashboard-grid">
            <!-- First row: WAF bypass information -->
            <div class="hc-dashboard-item">
                <div class="hc-dashboard-left">
                    <h4><?php _e( 'WAF Bypass Status', 'hale-components' ); ?></h4>
                    <p><?php echo $cookie_message; ?></p>
                </div>
                <div class="hc-dashboard-right">
                    <h4><?php _e( 'What is the WB_CONFIG cookie?', 'hale-components' ); ?></h4>
                    <p><?php echo $waf_description_panel_text; ?></p>
                    <p><?php echo $waf_body_panel_text; ?></p>
                </div>
            </div>
        </div>

