<?php

declare(strict_types=1);

namespace MOJComponents\Head;

use MOJComponents\Head\HeadSettings as Settings;

class Head
{
    public string $parentPath = '';

    public bool $hasSettings = true;

    public $settings;

    public string $headElement = '';

    public function __construct()
    {
        $this->settings = new Settings();

        $this->actions();

        $options           = get_option('moj_component_settings');
        $this->headElement = $options['head_element'] ?? '';
    }

    public function actions(): void
    {
        add_action('wp_loaded', [$this->settings, 'settings'], 1);
        add_action('wp_head', [$this, 'loadHeadElement']);
    }

    /** Print the configured head element/code into wp_head. */
    public function loadHeadElement(): void
    {
        if (!empty($this->headElement)) {
            echo $this->headElement;
        }
    }
}
