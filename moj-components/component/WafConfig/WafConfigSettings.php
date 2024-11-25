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
            __('Enable WAF bypass', 'wp-moj-components'),
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
     * // Excludes subscribers (they will still get WAF)
     */
    public function setWafBypassCookie()
    {
        // Value provided into the container via GitAction secrets
        $wb_config_env_value = $this->get_env_variable('WB_CONFIG');

        // Setting to edit posts excludes subscribers
        if (current_user_can('edit_posts')) {
            $options = get_option('moj_component_settings', []);

            if (!empty($options['WafConfig_element'])) {
                setcookie('WB_CONFIG', $wb_config_env_value, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            } else {
                // Remove the cookie if the toggle is off.
                if (isset($_COOKIE['WB_CONFIG'])) {
                    setcookie('WB_CONFIG', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
                }
            }
        }
    }

    public function settingsSectionCB()
    {
        $welcome_panel_text = 'To avoid WAF rules disrupting editors working in the backend,<br>
        this toggle turns WAF rules off on all logged-in users across the platform.<br>
        Subscribers, however, are an exception and are still subject to WAF rules.';

        $body_panel_text = 'Activating generates a cookie for logged-in users called WB_CONFIG <br>
        with a value provided by GitActions that is used by the WAF as a flag to skip.';
        ?>
    
        <div class="welcome-panel-column">
            <h4><?php _e('Context', 'wp-moj-components') ?></h4>
            <p><?php _e($welcome_panel_text, 'wp-moj-components'); ?>
            <br><br>
            <?php _e($body_panel_text, 'wp-moj-components'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Function to retrieve the value of an environment variable.
     * 
     * @param string $key The name of the environment variable.
     * @param mixed $default A default value if the environment variable is not set.
     * 
     * @return string The value of the environment variable or the default value.
     */
    function get_env_variable($key, $default = null) {
        // Check if the environment variable is set in $_ENV or $_SERVER
        $value = getenv($key) ?: ($_ENV[$key] ?? $_SERVER[$key] ?? $default);

        return $value;
    }
}

