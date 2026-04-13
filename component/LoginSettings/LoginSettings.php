<?php

declare(strict_types=1);

namespace MOJComponents\LoginSettings;

class LoginSettings
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('login_message', [$this, 'customLoginMessage']);
        add_action('login_head', [$this, 'loginHead']);
        add_filter('login_headertext', [$this, 'customLoginTitle']);
        add_filter('login_headerurl', [$this, 'loginHeaderLink']);
        add_action('admin_menu', [$this, 'addLoginSettingsPage']);
    }

    public function customLoginMessage(): void
    {
        $message = get_option('login_message');

        if (!empty($message)) {
            ?>
            <div class="govuk-notification-banner" role="region"
                 aria-labelledby="govuk-notification-banner-title"
                 data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
                        Important
                    </h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <?php echo wpautop($message); ?>
                </div>
            </div>
            <?php
        }
    }

    public function loginHead(): void
    {
        wp_register_style('custom_loginstyle', HALE_COMPONENTS_URL . 'dist/css/login.css');
        wp_enqueue_style('custom_loginstyle');
    }

    public function customLoginTitle(string $title): string
    {
        $customTitle = (string) get_option('login_title');

        if (!empty($customTitle)) {
            return $customTitle;
        }

        return get_bloginfo('name');
    }

    public function loginHeaderLink(string $loginHeaderUrl): string
    {
        return home_url();
    }

    public function addLoginSettingsPage(): void
    {
        add_options_page(
            'Login Settings',
            'Login',
            'manage_options',
            'login-settings',
            [$this, 'loginSettingsPage']
        );
    }

    public function loginSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $title = (string) get_option('login_title');
        $message = (string) get_option('login_message');
        $updated = false;

        if (isset($_POST['submit'])) {
            $title = sanitize_text_field($_POST['login_title']);
            $message = sanitize_textarea_field($_POST['login_message']);

            update_option('login_title', $title);
            update_option('login_message', $message);

            $updated = true;
        }
        ?>
        <div class="wrap">
            <h1>Login Settings</h1>
            <br/>
            <?php if ($updated) { ?>
                <div class="notice notice-success is-dismissible"><p>Login Settings updated</p></div>
            <?php } ?>
            <form method="post" action="">
                <label for="login_title">Login Title: (optional, default is site name)</label><br/><br/>
                <input type="text" name="login_title" id="login_title" style="width:50%;"
                       value="<?php echo esc_attr($title); ?>"/><br/><br/>

                <label for="login_message">Login Message:</label><br/><br/>
                <textarea name="login_message" id="login_message" rows="10"
                          style="width:50%;"><?php echo esc_textarea($message); ?></textarea><br/><br/>

                <input type="submit" name="submit" class="button button-primary" value="Save">
            </form>
        </div>
        <?php
    }
}
