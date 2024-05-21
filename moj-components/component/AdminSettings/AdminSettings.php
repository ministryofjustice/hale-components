<?php

namespace MOJComponents\AdminSettings;

use MOJComponents\TaxonomyUpdater\TaxonomyUpdater;
use MOJComponents\ImportUsers\ImportUsers;

class AdminSettings
{
    public $helper;
    public $tabs = [];
    public $content = [];
    public $object = ''; // the settings() current object

    public function __construct()
    {
        global $mojHelper;
        $this->helper = $mojHelper;
        $this->actions();
    }

    public function actions()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_init', [$this, 'settings'], 11);
        add_action('admin_menu', [$this, 'page']);
        add_action('admin_init', [$this, 'mojColourSchemes']);
    }

    public function enqueue()
    {
        wp_enqueue_style('settings_admin_css', $this->helper->cssPath(__FILE__) . 'main.css', []);
        wp_enqueue_script('settings_admin_js', $this->helper->jsPath(__FILE__) . 'main.js', ['jquery']);
    }

    public function page()
    {

        $taxonomyUpdaterObject = new TaxonomyUpdater();
        $importUsersObject = new ImportUsers();

        add_menu_page(
            'General settings',
            'Hale Components',
            'manage_options',
            'mojComponentSettings',
            [$this, 'content'],
            '',
            99
        );
        
        add_submenu_page(
            'mojComponentSettings',
            'Taxonomy Updater',
            'Taxonomy Updater',
            'manage_options',
            'taxonomy-updater',
            [$taxonomyUpdaterObject, 'hale_taxonomy_updater_tool']
        );

        add_submenu_page(
            'mojComponentSettings',
            'Import Users',
            'Import Users',
            'manage_options',
            'import-users',
            [$importUsersObject, 'hale_import_users_tool']
        );
        
    }

    public function mojColourSchemes()
    {
        # MoJ Digital & Technology
        wp_admin_css_color(
            'moj_dt',
            __('MoJ Digital & Technology', 'wp-moj-components'),
            $this->helper->cssPath(__FILE__) . 'scheme/moj-dt/colours.css',
            [ '#0b0c0c', '#626a6e', '#2c5d94', '#1d70b8' ]
        );
    }

    public function settings()
    {
        register_setting('mojComponentSettings', 'moj_component_settings');

        foreach ($this->helper->adminSettings as $key => $class) {
            $this->object = new $class();
            $hasSettings = $this->object->hasSettings ?? false;

            if ($hasSettings === true) {
                $thisClass = get_class($this->object);
                $thisClass = str_replace('\\', '/', $thisClass);
                $className = $this->helper->splitCamelCase(basename($thisClass));

                $this->tabs[] = [
                    'key' => $key,
                    'class' => str_replace(' Settings', '', $className)
                ];

                add_settings_section(
                    'component-tab-' . $key,
                    $className,
                    [$this->object, 'settingsSectionCB'],
                    'mojComponentSettings'
                );

                $this->object->settingsFields('component-tab-' . $key);
            }
        }

        return $this->tabs;
    }

    public function content()
{
    ?>
    <form action='options.php' method='post'>

        <h1>Hale Components</h1>

        <?php
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($this->tabs as $tab) {
            // Ensure href attribute is always valid
            echo '<a href="#component-tab-' . esc_attr($tab['key']) . '" class="nav-tab">' . esc_html($tab['class']) . '</a>';
        }

        echo '</h2>';

        settings_fields('mojComponentSettings');
        $this->doSettingsSections('mojComponentSettings');

        echo '<hr>';

        submit_button();
        ?>

    </form>
    <?php
}


    /**
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function doSettingsSections($page)
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page])) {
            return;
        }

        foreach ((array)$wp_settings_sections[$page] as $key => $section) {
            echo '<div id="' . $key . '" class="moj-component-settings-section">';
            if ($section['title']) {
                echo "<h2>{$section['title']}</h2>\n";
            }

            if ($section['callback']) {
                call_user_func($section['callback'], $section);
            }

            if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                continue;
            }

            echo '<table class="form-table">';
            do_settings_fields($page, $section['id']);
            echo '</table>';
            echo '</div>';
        }
    }

    public function getSettings()
    {
        return $this->helper->adminSettings;
    }
}
