<?php

namespace MOJComponents\WafConfig;

class WafConfigSettings extends WafConfig
{
    public $helper;

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;

        // Hook to set or remove the WAF bypass cookie.
        add_action('init', [$this, 'setWafBypassCookie']);
    }

    public function settings()
    {
        $this->helper->initSettings($this);
    }

    public function settingsFields($section)
    {
        add_settings_field(
            'WafConfig_element',
            __('Enable platform wide WAF bypass', 'wp-moj-components'),
            [$this, 'addWafConfigElement'],
            'mojComponentSettings',
            $section
        );
    }

    /**
     * Render a toggle button for enabling/disabling WAF bypass.
     */
    public function addWafConfigElement()
    {
        $options = get_option('moj_component_settings');
        $WafConfigElement = !empty($options['WafConfig_element']) ? 'checked' : '';

        ?>
        <label class="moj-component-toggle">
            <input type="checkbox" name="moj_component_settings[WafConfig_element]" value="1" <?php echo $WafConfigElement; ?>>
            <span class="moj-component-slider"></span>
        </label>
        <p class="description">
            <?php _e('', 'wp-moj-components'); ?>
        </p>
        <style>
            .moj-component-toggle {
                position: relative;
                display: inline-block;
                width: 60px;
                height: 34px;
                margin-right: 10px;
            }
            .moj-component-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .moj-component-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 34px;
            }
            .moj-component-slider:before {
                position: absolute;
                content: "";
                height: 26px;
                width: 26px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }
            input:checked + .moj-component-slider {
                background-color: #2196F3;
            }
            input:checked + .moj-component-slider:before {
                transform: translateX(26px);
            }
            .description {
                font-size: 14px;
                color: #666;
                margin-top: 8px;
            }
        </style>
        <?php
    }

    /**
     * Set a WAF bypass cookie for logged-in users if the setting is enabled.
     */
    public function setWafBypassCookie()
    {
        if (is_user_logged_in()) {
            $options = get_option('moj_component_settings', []);
            if (!empty($options['WafConfig_element'])) {
                setcookie('WAF_CONFIG', '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            } else {
                // Remove the cookie if the toggle is off.
                if (isset($_COOKIE['waf_bypass'])) {
                    setcookie('waf_bypass', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
                }
            }
        }
    }

    public function settingsSectionCB()
    {
        ?>
        <div class="welcome-panel-column">
            <h4><?php _e('Context', 'wp-moj-components') ?></h4>
            <p><?php _e('Use this toggle to enable WAF bypass for logged-in users (excluding subscribers). This sets a cookie, WAF_CONFIG and secret value, that allows logged-in users to bypass WAF checks.', 'wp-moj-components'); ?></p>
        </div>
        <?php
    }
}

