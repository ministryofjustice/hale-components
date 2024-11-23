<?php

namespace MOJComponents\WafConfig;

class WafConfigSettings extends WafConfig
{
    public $helper;

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;
    }

    public function settings()
    {
        $this->helper->initSettings($this);
    }

    public function settingsFields($section)
    {
        add_settings_field(
            'WafConfig_element',
            __('Add header code:', 'wp-moj-components'),
            [$this, 'addWafConfigElement'],
            'mojComponentSettings',
            $section
        );
    }

    /**
     * Function that collects inputed GTM ID and running checks on it.
     */
    public function addWafConfigElement()
    {
        $options = get_option('moj_component_settings');
        $WafConfigElement = $options['WafConfig_element'] ?? '';

        ?>
        <input type='text' name='moj_component_settings[WafConfig_element]'
        placeholder="For example, add <meta> element" value='<?php echo $WafConfigElement; ?>'
        class="moj-component-input">
        <?php
    }

    public function settingsSectionCB()
    {
        ?>
        <div class="welcome-panel-column">
            <h4><?php _e('Info', 'wp-moj-components') ?></h4>
            <p><?php _e('Add HTML meta-related elements to the websites wp_head.
            On WP multisite, this applies to the specific site your logged in as.<br><br>
            This is normally for validating an external app, testing a CDN script,
            or SEO tagging that cannot be done via Yoast.', 'wp-moj-components'); ?></p>
        </div>
        <?php
    }
}
