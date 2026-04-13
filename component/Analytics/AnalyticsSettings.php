<?php

declare(strict_types=1);

namespace MOJComponents\Analytics;

class AnalyticsSettings extends Analytics
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
            'gtm_analytics_id',
            __('GTM ID', 'wp-moj-components'),
            [$this, 'setGoogleTagManagerID'],
            'mojComponentSettings',
            $section
        );
    }

    public function setGoogleTagManagerID(): void
    {
        $options            = get_option('moj_component_settings');
        $googleTagManagerID = $options['gtm_analytics_id'] ?? '';

        ?>
        <input type='text' name='moj_component_settings[gtm_analytics_id]'
               placeholder="GTM-XXXXXXX"
               value='<?php echo sanitize_html_class($googleTagManagerID); ?>'
               class="moj-component-input">
        <?php

        if ($googleTagManagerID === '') {
            return;
        }

        $googleTagManagerID = preg_replace('/\s+/', '', $googleTagManagerID);

        if (strlen($googleTagManagerID) !== 11) {
            echo '<div class="notice notice-error settings-error" style="margin-left: 0;">
                GTM ID might be invalid. Double check the character count.</div>';
        }

        if (!preg_match('/^GTM-/', $googleTagManagerID)) {
            echo '<div class="notice notice-error settings-error" style="margin-left: 0;">
                GTM ID might be invalid. ID must start with GTM.</div>';
        }
    }

    public function settingsSectionCB(): void
    {
        ?>
        <div class="welcome-panel-column">
            <h4><?php _e('Google Tag Manager (GTM)', 'wp_analytics_page'); ?></h4>
            <p style="max-width: 600px">
                <?php _e(
                    'Analytic tracking on our site is done through GTM. First setup a GTM account and then add the
                     GTM container ID below and save. This will add GTM code to the site. You can find the eleven
                     character GTM ID, by logging into your GTM account, in the top right corner of the dashboard.
                     <br><br>If no GTM ID is added, no code is loaded on the page.',
                    'wp_analytics_page'
                ); ?>
            </p>
            <h4><?php _e('Google Analytics (GA)', 'wp_analytics_page'); ?></h4>
            <p style="max-width: 600px">
                <?php _e('Add Google Analytics or any other tracker, via the GTM dashboard.', 'wp_analytics_page'); ?>
            </p>
        </div>
        <?php
    }
}
