<?php

namespace MOJComponents\WafConfig;

use MOJComponents\WafConfig\WafConfigSettings as Settings;

class WafConfig
{
    /**
     * @var string
     */
    public $parentPath = '';

    /**
     * @var boolean
     */
    public $hasSettings = true;

    /**
     * @var object
     */
    public $settings;

    /**
     * @var string
     */
    public $WafConfigElement;

    public function __construct()
    {
        $this->settings = new Settings();

        $this->actions();

        $options = get_option('moj_component_settings');
        $this->WafConfigElement = $options['WafConfig_element'] ?? '';
    }

    public function actions()
    {
        add_action('wp_loaded', [$this->settings, 'settings'], 1);
        add_action('wp_head', [$this,'loadWafConfigElement']);
    }
}
