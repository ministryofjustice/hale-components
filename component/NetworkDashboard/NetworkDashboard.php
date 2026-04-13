<?php

declare(strict_types=1);

namespace MOJComponents\NetworkDashboard;

class NetworkDashboard
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('network_admin_menu', [$this, 'addMenuPage']);
        add_action('init', [$this, 'setWafBypassCookie']);
    }

    public function enqueue(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'hale-components-network-dashboard') {
            return;
        }

        $cssFile    = 'dist/css/hc-network-dashboard.css';
        $filePath   = HALE_COMPONENTS_DIR . $cssFile;
        $fileVersion = file_exists($filePath) ? (string) filemtime($filePath) : '1';

        wp_register_style(
            'network_dashboard',
            HALE_COMPONENTS_URL . $cssFile,
            [],
            $fileVersion,
            'all'
        );
        wp_enqueue_style('network_dashboard');
    }

    /** Add the dashboard page under Settings in the network admin menu. */
    public function addMenuPage(): void
    {
        add_submenu_page(
            'settings.php',
            'Hale Components Network Dashboard',
            'Hale Components',
            'manage_network_options',
            'hale-components-network-dashboard',
            [$this, 'renderContent']
        );
    }

    /** Render the network dashboard page content. */
    public function renderContent(): void
    {
        $partsDir = __DIR__ . '/parts/';
        echo '<div class="wrap">';
        include $partsDir . 'dashboard-intro.php';
        include $partsDir . 'waf-status.php';
        include $partsDir . 'api-status.php';
        echo '</div>';
    }

    /**
     * Retrieve the value of an environment variable, checking ENV and SERVER.
     *
     * @param string $key     Environment variable name.
     * @param string $default Default value if not set.
     * @return string
     */
    public function getEnvVariable(string $key, string $default = ''): string
    {
        return (string) (getenv($key) ?: ($_ENV[$key] ?? $_SERVER[$key] ?? $default));
    }

    /**
     * Detect if the WB_CONFIG cookie is present.
     */
    public function isWafBypassCookiePresent(): bool
    {
        return isset($_COOKIE['WB_CONFIG']) && !empty($_COOKIE['WB_CONFIG']);
    }

    /**
     * Set a WAF bypass cookie for logged-in editors.
     * Subscribers are excluded and remain subject to WAF rules.
     */
    public function setWafBypassCookie(): void
    {
        $wbConfigValue = $this->getEnvVariable('WB_CONFIG');

        if (current_user_can('edit_posts')) {
            setcookie(
                'WB_CONFIG',
                $wbConfigValue,
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN ?: '',
                is_ssl(),
                true
            );
        }
    }
}
