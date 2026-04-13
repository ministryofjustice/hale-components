<?php

declare(strict_types=1);

namespace MOJComponents\Sitemap;

class SitemapSettings extends Sitemap
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
            'sitemap_exclude_pages',
            __('Exclude pages', 'wp-moj-components'),
            [$this, 'popupMessageTitleCB1'],
            'mojComponentSettings',
            $section
        );

        $cptOptions = [
            'sitemap_exclude_cpt_page'    => 'Exclude CPT - Page',
            'sitemap_exclude_cpt_post'    => 'Exclude CPT - Post',
            'sitemap_exclude_cpt_archive' => 'Exclude CPT - Archive',
            'sitemap_exclude_cpt_author'  => 'Exclude CPT - Author',
        ];

        foreach ($cptOptions as $optionName => $label) {
            add_settings_field(
                $optionName,
                __($label, 'wp-moj-components'),
                [$this, 'sitemapOptionCheckbox'],
                'mojComponentSettings',
                $section,
                ['option_name' => $optionName]
            );
        }

        $postTypes = get_post_types(['public' => true, '_builtin' => false], 'names');

        foreach ($postTypes as $postType) {
            $cpt = get_post_type_object($postType);

            add_settings_field(
                'sitemap_exclude_cpt_' . $cpt->name,
                __('Exclude CPT - ' . $cpt->label, 'wp-moj-components'),
                [$this, 'sitemapOptionCheckbox'],
                'mojComponentSettings',
                $section,
                ['option_name' => 'sitemap_exclude_cpt_' . $cpt->name]
            );
        }

        $taxonomyNames = get_taxonomies(['public' => true, '_builtin' => false]);

        foreach ($taxonomyNames as $taxonomyName) {
            $taxonomyObj = get_taxonomy($taxonomyName);

            add_settings_field(
                'sitemap_exclude_taxonomy_' . $taxonomyObj->name,
                __('Exclude Tax - ' . $taxonomyObj->label, 'wp-moj-components'),
                [$this, 'sitemapOptionCheckbox'],
                'mojComponentSettings',
                $section,
                ['option_name' => 'sitemap_exclude_taxonomy_' . $taxonomyObj->name]
            );
        }

        add_settings_field(
            'sitemap_exclude_password_protected',
            __('Exclude Password Protected', 'wp-moj-components'),
            [$this, 'sitemapOptionCheckbox'],
            'mojComponentSettings',
            $section,
            ['option_name' => 'sitemap_exclude_password_protected']
        );
    }

    /** @param array{option_name: string} $args */
    public function sitemapOptionCheckbox(array $args): void
    {
        $options = get_option('moj_component_settings');
        ?>
        <input type='checkbox'
               name='moj_component_settings[<?php echo esc_attr($args['option_name']); ?>]'
               value='yes' <?= checked('yes', $options[$args['option_name']] ?? ''); ?>
               class="moj-component-input-checkbox">
        <?php
    }

    public function popupMessageTitleCB1(): void
    {
        $options      = get_option('moj_component_settings');
        $excludePages = $options['sitemap_exclude_pages'] ?? '';
        ?>
        <input type='text' name='moj_component_settings[sitemap_exclude_pages]'
               value='<?php echo esc_attr($excludePages); ?>' class="moj-component-input">
        <?php
    }

    public function settingsSectionCB(): void
    {
        ?>
        <div class="welcome-panel-column">
            <h4><?php _e('Traditional sitemap', 'wp_sitemap_page'); ?></h4>
            <p><?php _e('To display a traditional sitemap, use [wp_sitemap_page] on any page or post.', 'wp_sitemap_page'); ?></p>
        </div>

        <div class="welcome-panel-column">
            <h4><?php _e('Display only some content', 'wp_sitemap_page'); ?></h4>
            <p><?php _e('Display only some kind of content using the following shortcodes.', 'wp_sitemap_page'); ?></p>
            <ul>
                <li>[wp_sitemap_page only="post"]</li>
                <li>[wp_sitemap_page only="page"]</li>
                <li>[wp_sitemap_page only="category"]</li>
                <li>[wp_sitemap_page only="tag"]</li>
                <li>[wp_sitemap_page only="archive"]</li>
                <li>[wp_sitemap_page only="author"]</li>
            </ul>
        </div>
        <?php
    }
}
