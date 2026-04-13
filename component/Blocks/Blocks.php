<?php

declare(strict_types=1);

namespace MOJComponents\Blocks;

class Blocks
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_filter('render_block_core/table', [$this, 'addTableHeaderScope'], 10, 2);
    }

    /**
     * Accessibility fix: add scope="col" to table block header cells.
     *
     * @param string $blockContent
     * @param array  $block
     * @return string
     */
    public function addTableHeaderScope(string $blockContent, array $_block): string
    {
        if (strpos($blockContent, '<th') === false) {
            return $blockContent;
        }

        return (string) preg_replace(
            '/<th\b(?![^>]*\bscope=)/',
            '<th scope="col"',
            $blockContent
        );
    }
}
