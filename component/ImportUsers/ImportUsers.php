<?php

declare(strict_types=1);

namespace MOJComponents\ImportUsers;

class ImportUsers
{
    public function renderTool(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $jsonData    = '';
        $errors      = [];
        $addedUsers  = 0;
        $failedUsers = 0;

        if (isset($_POST['submit'])) {
            $jsonData = stripslashes($_POST['json_data']);

            if (empty($jsonData)) {
                $errors[] = 'JSON Data is required.';
            }

            $userData = json_decode($jsonData, true);

            if ($userData === null && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON format. Please enter valid JSON data.';
            }

            if (empty($errors)) {
                foreach ($userData as $user) {
                    if (!array_key_exists('userName', $user) || empty($user['userName'])) {
                        $failedUsers++;
                        continue;
                    }

                    if (
                        !array_key_exists('email', $user) ||
                        empty($user['email']) ||
                        !is_email($user['email'])
                    ) {
                        $failedUsers++;
                        continue;
                    }

                    $userId = email_exists($user['email']);

                    if (!$userId) {
                        if (username_exists($user['userName'])) {
                            $failedUsers++;
                            continue;
                        }

                        $userdata = [
                            'user_login' => $user['userName'],
                            'user_pass'  => wp_generate_password(),
                            'user_email' => $user['email'],
                            'role'       => 'subscriber',
                        ];

                        $userId = wp_insert_user($userdata);
                    }

                    if (is_wp_error($userId)) {
                        $failedUsers++;
                        continue;
                    }

                    $result = add_user_to_blog(get_current_blog_id(), $userId, 'subscriber');

                    if (is_wp_error($result)) {
                        $failedUsers++;
                        continue;
                    }

                    $addedUsers++;
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>Import Users</h1>
            <p>Imports users from JSON. Users are added as subscribers.</p>

            <p>Example JSON ('userName' and 'email' fields are required)</p>
            <code>
                [
                    {"userName": "jDoe", "email": "john.doe@example.com"},
                    {"userName": "jSmith", "email": "jane.smith@example.com"},
                    {"userName": "bJohnson", "email": "bob.johnson@example.com"}
                ]
            </code>
            <?php
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
                }
            }
            if ($addedUsers > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>' . (int) $addedUsers . ' Users Imported</p></div>';
            }
            if ($failedUsers > 0) {
                echo '<div class="notice notice-error is-dismissible"><p>' . (int) $failedUsers . ' Users Failed to be imported</p></div>';
            }
            ?>
            <br/>
            <br/>
            <form method="post" action="">
                <p>Site Importing to: <?php echo esc_html(get_bloginfo('name')); ?></p>
                <label for="json_data">JSON Data:</label><br/><br/>
                <textarea name="json_data" id="json_data" rows="10"
                          style="width:100%;" required><?php echo esc_textarea($jsonData); ?></textarea><br/><br/>
                <input type="submit" name="submit" class="button button-primary" value="Import">
            </form>
        </div>
        <?php
    }
}
