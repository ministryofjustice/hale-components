<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// $this is available here — included within NetworkDashboard::renderContent()
$cookiePresent = $this->isWafBypassCookiePresent();

$cookieMessage = $cookiePresent
    ? __('<span class="hc-status-on">ON</span> WB_CONFIG cookie is present.', 'hale-components')
    : __('<span class="hc-status-off">OFF</span> WB_CONFIG cookie is not present.', 'hale-components');

$wafDescriptionText = 'To avoid WAF rules disrupting editors working in the backend,<br>
    all logged-in users are assigned the WB_CONFIG cookie. The presence of the WB_CONFIG cookie and value
    disables WAF running.<br>Subscribers, however, are an exception and are still subject to WAF rules.';

$wafBodyText = 'The WB_CONFIG cookie is set with a value
    provided by GitActions.<br> Our ingress configuration uses NGINX to apply a WAF but
    skips WAF rules if the cookie and the correct value are present.';
?>

<div class="hc-dashboard-grid">
    <div class="hc-dashboard-item">
        <div class="hc-dashboard-left">
            <h4><?php _e('WAF Bypass Status', 'hale-components'); ?></h4>
            <p><?php echo $cookieMessage; ?></p>
        </div>
        <div class="hc-dashboard-right">
            <h4><?php _e('What is the WB_CONFIG cookie?', 'hale-components'); ?></h4>
            <p><?php echo $wafDescriptionText; ?></p>
            <p><?php echo $wafBodyText; ?></p>
        </div>
    </div>
</div>
