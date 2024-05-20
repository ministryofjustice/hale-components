<?php
namespace MOJComponents\TaxonomyUpdater;

class TaxonomyUpdater
{
    public function hale_taxonomy_updater_tool() {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Check if the user has submitted the form
        if (isset($_POST['update_taxonomy'])) {
    
            if (!isset($_POST['taxonomy_updater_nonce']) || !wp_verify_nonce($_POST['taxonomy_updater_nonce'], 'taxonomy_updater_action')) {
                die('Security check failed');
            }
    
            global $wpdb;
    
            $old_slug = sanitize_text_field($_POST['old_slug']);
            $new_slug = sanitize_text_field($_POST['new_slug']);
    
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s",
                $new_slug,
                $old_slug
            );
    
            // Execute the query
            $updated_rows = $wpdb->query($sql);
    
            flush_rewrite_rules();
    
            // Check for success
            if ($updated_rows > 0) {
                echo '<div class="updated"><p>Taxonomy updated successfully. Rows affected: ' . $updated_rows . '</p></div>';
            } else {
                echo '<div class="error"><p>No rows updated. It\'s possible the old slug did not exist or was already updated.</p></div>';
            }
        }
        ?>
    
        <div class="wrap">
            <h1>Taxonomy Updater</h1>
            <p>Run a database update on the current site that changes a taxonomy entry in the database to a new one. 
            <br>Make sure to use the exact taxonomy key of the taxonomy you want to change. 
            <br>Once run, all content related to the old taxonomy will now <br>be associated with the new taxonomy name.</p>
            <form method="post" action="">
                <?php wp_nonce_field('taxonomy_updater_action', 'taxonomy_updater_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="old_slug">Taxonomy key:</label></th>
                        <td><input type="text" id="old_slug" name="old_slug" value="" required /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="new_slug">New taxonomy key:</label></th>
                        <td><input type="text" id="new_slug" name="new_slug" value="" required /></td>
                    </tr>
                </table>
                <p>
                    <input type="submit" name="update_taxonomy" class="button button-primary" value="Update Taxonomy">
                </p>
            </form>
        </div>
        <?php
    }
}