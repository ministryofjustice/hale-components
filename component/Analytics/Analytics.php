<?php

declare(strict_types=1);

namespace MOJComponents\Analytics;

use MOJComponents\Analytics\AnalyticsSettings as Settings;

class Analytics
{
    public string $parentPath = '';

    public bool $hasSettings = true;

    public $settings;

    public string $googleTagManagerID = '';

    public function __construct()
    {
        $this->settings = new Settings();

        $this->actions();

        $options                  = get_option('moj_component_settings');
        $this->googleTagManagerID = $options['gtm_analytics_id'] ?? '';
    }

    public function actions(): void
    {
        add_action('wp_loaded', [$this->settings, 'settings'], 1);
        add_action('wp_head', [$this, 'loadGoogleTagManagerInHead']);
        add_action('wp_body_open', [$this, 'loadGoogleTagManagerInBody']);
    }

    /**
     * Add GTM script to <head> as per Google guidance.
     *
     * @see https://developers.google.com/tag-manager/quickstart
     */
    public function loadGoogleTagManagerInHead(): void
    {
        if (empty($this->googleTagManagerID)) {
            return;
        }
        ?>
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer', '<?php echo sanitize_html_class($this->googleTagManagerID); ?>' );</script>
            <!-- End Google Tag Manager -->
        <?php
    }

    public function loadGoogleTagManagerInBody(): void
    {
        if (empty($this->googleTagManagerID)) {
            return;
        }
        ?>
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo sanitize_html_class($this->googleTagManagerID); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
        <?php
    }
}
