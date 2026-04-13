<?php

declare(strict_types=1);

namespace MOJComponents\Helper;

class Helper
{
    public string $assetPath = '';

    public string $mailTo = '';

    public string $mailSubject = '';

    public string $mailMessage = '';

    /** @var string[] */
    public array $mailHeaders = [];

    /** @var array<int, object> */
    public array $adminSettings = [];

    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_filter('cron_schedules', [$this, 'addIntervals']);
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function getPageUrl(): string
    {
        global $wp;
        return home_url($wp->request);
    }

    public function getTimePeriod(?int $time = null): string
    {
        $hour = (int) date('H', $time ?: time());

        if ($hour < 12) {
            return 'morning';
        }

        if ($hour < 17) {
            return 'afternoon';
        }

        return 'evening';
    }

    /**
     * Get the URL to the assets directory for the given component file.
     *
     * @param string $path Path to the component file (use __FILE__).
     */
    public function assetPath(string $path): string
    {
        return esc_url(plugins_url('assets/', $path));
    }

    public function cssPath(string $path): string
    {
        return $this->assetPath($path) . 'css/';
    }

    public function fontPath(string $path): string
    {
        return $this->assetPath($path) . 'fonts/';
    }

    public function imagePath(string $path): string
    {
        return $this->assetPath($path) . 'images/';
    }

    public function jsPath(string $path): string
    {
        return $this->assetPath($path) . 'js/';
    }

    public function emailPath(string $path): string
    {
        return $path . 'email-templates/';
    }

    public function setupSettings(object $object, string $key): ?bool
    {
        if (!$object || !$key) {
            return false;
        }

        if (isset($object->hasSettings) && $object->hasSettings === true) {
            $object->settings = get_option('moj-component-' . strtolower(ltrim(basename($key), '\\')), []);
            return true;
        }

        return null;
    }

    /**
     * Register a settings class for the admin settings tabs.
     *
     * @param object $class Instance of the settings class to register.
     */
    public function initSettings(object $class): void
    {
        if (!in_array($class, $this->adminSettings, true)) {
            $this->adminSettings[] = $class;
        }
    }

    public function mail(): void
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        wp_mail($this->mailTo, $this->mailSubject, $this->mailMessage, $headers);
    }

    public function splitCamelCase(string $string): string
    {
        $regex = '/
          (?<=[a-z])
          (?=[A-Z])
        | (?<=[A-Z])
          (?=[A-Z][a-z])
        /x';
        $array = preg_split($regex, $string);
        return implode(' ', $array);
    }

    public function setMailSubject(string $subject): void
    {
        $this->mailSubject = $subject;
    }

    public function setMailMessage(string $message): void
    {
        $this->mailMessage = $message;
    }

    public function setMaiTo(string $to): void
    {
        $this->mailTo = $to;
    }

    /** @param array<string, array{interval: int, display: string}> $schedules */
    public function addIntervals(array $schedules): array
    {
        $schedules['weekly'] = [
            'interval' => 604800,
            'display'  => __('Once Weekly'),
        ];
        $schedules['monthly'] = [
            'interval' => 2635200,
            'display'  => __('Once Monthly'),
        ];
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display'  => esc_html__('Every Five Minutes'),
        ];
        $schedules['three_minutes'] = [
            'interval' => 180,
            'display'  => esc_html__('Every Three Minutes'),
        ];

        return $schedules;
    }
}

/** Backward-compatibility helper function. */
function moj_get_page_uri(): string
{
    global $mojHelper;
    return $mojHelper->getPageUrl();
}
