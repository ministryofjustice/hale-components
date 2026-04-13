<?php

declare(strict_types=1);

namespace MOJComponents\Security;

class Security
{
    public $helper;

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;

        $this->hooks();
    }

    public function hooks(): void
    {
        add_filter('sanitize_file_name', [$this, 'removeFilenameBadChars'], 10);
    }

    public static function removeFilenameBadChars(string $filename): string
    {
        $badChars = ['–', '#', '~', '%', '|', '^', '>', '<', '[', ']', '{', '}'];
        return str_replace($badChars, '-', $filename);
    }
}
