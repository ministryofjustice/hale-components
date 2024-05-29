<?php

namespace MOJComponents\AcfFieldUpdater;

class AcfFieldUpdater
{
    /**
     * Displays a form in the WordPress admin to update meta keys or meta values.
     * 
     * This function provides an interface for site administrators to update meta keys or meta values stored in the
     * wp_postmeta table. Users can select whether they want to update a meta key or a meta value using a dropdown.
     * Depending on the selection, appropriate input fields are displayed. After submitting the form, the specified
     * updates are performed on the database.
     */
    public function hale_acf_field_updater_tool()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Check if the user has submitted the form
        if (isset($_POST['update_acf_field'])) {
            if (
                !isset($_POST['acf_field_updater_nonce']) ||
                !wp_verify_nonce($_POST['acf_field_updater_nonce'], 'acf_field_updater_action')
            ) {
                die('Security check failed');
            }

            global $wpdb;

            $updateType = sanitize_text_field($_POST['update_type']);
            $oldValue = sanitize_text_field($_POST['old_value']);
            $newValue = sanitize_text_field($_POST['new_value']);
            $metaKey = sanitize_text_field($_POST['meta_key']);

            if ($updateType === 'key') {
                $sql = $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta}
                    SET meta_key = %s
                    WHERE meta_key = %s",
                    $newValue,
                    $oldValue
                );
            } elseif ($updateType === 'value') {
                $sql = $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta}
                    SET meta_value = %s
                    WHERE meta_value = %s AND meta_key = %s",
                    $newValue,
                    $oldValue,
                    $metaKey
                );
            }

            // Execute the query
            $updatedRows = $wpdb->query($sql);

            // Check for success
            if ($updatedRows > 0) {
                echo '<div class="updated"><p>ACF field updated successfully. Rows affected: ' . $updatedRows . '</p></div>';
            } else {
                echo '<div class="error"><p>No rows updated. It\'s possible the old meta key or value did not exist or was already updated.</p></div>';
            }
        }
        ?>

        <div class="wrap">
            <h1>ACF Meta Field Updater</h1>
            <p>
                Run a database update on the current site in the _postmeta table. 
                <br>Select whether you want to update a meta key or meta value.
                <br>Once run, all content related to the old meta key or value will
                <br>be associated with the new meta key or value.
            </p>
            <form method="post" action="">
                <?php wp_nonce_field('acf_field_updater_action', 'acf_field_updater_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="update_type">Update Type:</label></th>
                        <td>
                            <select id="update_type" name="update_type" onchange="toggleMetaKeyField()" required>
                                <option value="key">Meta Key</option>
                                <option value="value">Meta Value</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top" id="meta_key_field" style="display: none;">
                        <th scope="row"><label for="meta_key">Target Meta Key:</label></th>
                        <td><input type="text" id="meta_key" name="meta_key" value="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="old_value">Old Value:</label></th>
                        <td><input type="text" id="old_value" name="old_value" value="" required /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="new_value">New Value:</label></th>
                        <td><input type="text" id="new_value" name="new_value" value="" required /></td>
                    </tr>
                </table>
                <p>
                    <input type="submit" name="update_acf_field" class="button button-primary" value="Update">
                </p>
            </form>
        </div>
        <script type="text/javascript">
            function toggleMetaKeyField() {
                var updateType = document.getElementById('update_type').value;
                var metaKeyField = document.getElementById('meta_key_field');
                if (updateType === 'value') {
                    metaKeyField.style.display = 'table-row';
                    document.getElementById('meta_key').required = true;
                } else {
                    metaKeyField.style.display = 'none';
                    document.getElementById('meta_key').required = false;
                }
            }
            // Initialize the display state based on the selected option
            document.addEventListener('DOMContentLoaded', function() {
                toggleMetaKeyField();
            });
        </script>
        <?php
    }
}
