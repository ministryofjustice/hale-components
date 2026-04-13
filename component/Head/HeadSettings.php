<?php

declare(strict_types=1);

namespace MOJComponents\Head;

class HeadSettings extends Head
{
    public $helper;

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;
    }

    public function settings(): void
    {
        $this->helper->initSettings($this);
    }

    public function settingsFields(string $section): void
    {
        add_settings_field(
            'head_element',
            __('Add header code:', 'wp-moj-components'),
            [$this, 'addHeadElement'],
            'mojComponentSettings',
            $section
        );
    }

    public function addHeadElement(): void
    {
        $options     = get_option('moj_component_settings');
        $headElement = $options['head_element'] ?? '';

        ?>
        <input type='text' name='moj_component_settings[head_element]'
               placeholder="For example, add &lt;meta&gt; element"
               value='<?php echo esc_attr($headElement); ?>'
               class="moj-component-input">
        <?php
    }

    public function settingsSectionCB(): void
    {
        ?>
        <div class="welcome-panel-column">
            <h4><?php _e('Info', 'wp-moj-components'); ?></h4>
            <p>
                <?php _e(
                    'Add HTML meta-related elements to the website\'s wp_head.
                     On WP multisite, this applies to the specific site you\'re logged in as.<br><br>
                     This is normally for validating an external app, testing a CDN script,
                     or SEO tagging that cannot be done via Yoast.',
                    'wp-moj-components'
                ); ?>
            </p>
        </div>
        <?php
    }
}
