<?php

declare(strict_types=1);

namespace MOJComponents\SearchReplaceDatabase;

/**
 * Search and replace database tool via WP-CLI.
 *
 * Displayed on the network admin page: /wp-admin/network/site-info.php
 * Only accessible to super admins.
 *
 * @since 1.2.0
 */
class SearchReplaceDatabase
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_action('plugins_loaded', [$this, 'registerTool']);
    }

    /** Register the tool only for users with setup_network capability. */
    public function registerTool(): void
    {
        if (current_user_can('setup_network')) {
            add_action('network_site_info_form', [$this, 'renderTool'], 10, 0);
        }
    }

    /**
     * Render the search and replace form and execute the WP-CLI command on submission.
     */
    public function renderTool(): void
    {
        // Close the site-info page's existing form before adding ours.
        submit_button('Save Changes', 'primary', 'primary-save', true, '');
        echo '</form>';

        $blogId  = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        $siteUrl = get_site_url($blogId);

        $searchFor   = isset($_POST['search_value']) ? $_POST['search_value'] : $siteUrl;
        $replaceWith = isset($_POST['replace_value']) ? $_POST['replace_value'] : '';
        ?>

        <form method="post"
              action="site-info.php?action=run-search-and-replace&id=<?php echo $blogId; ?>">
            <hr><h3>Search and Replace</h3>
            <p>Run a search and replace against keywords in the database, limited to this site.<br>
               For domain replacements, include the https://</p>

            <table>
                <tr>
                    <td>
                        <?php wp_nonce_field('edit-site'); ?>
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) $blogId); ?>"/>
                        <table class="form-table" role="presentation">
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="search_value"><?php _e('Search for'); ?></label>
                                </th>
                                <td>
                                    <input name="search_value" type="text" id="search_value"
                                           style="width: 680px"
                                           value="<?php echo esc_attr($searchFor); ?>">
                                </td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="replace_value"><?php _e('Replace with'); ?></label>
                                </th>
                                <td>
                                    <input name="replace_value" type="text" id="replace_value"
                                           style="width: 680px"
                                           value="<?php echo esc_attr($replaceWith); ?>">
                                </td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row">
                                    <label for="dryrun_value"><?php _e('Dry run'); ?></label>
                                </th>
                                <td>
                                    <input name="dryrun_value" type="checkbox" id="dryrun_value">
                                    <label for="dryrun_value">
                                        Check to test search and replace without actually applying.
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td></td>
                </tr>
            </table>

            <?php submit_button('Run search and replace', 'secondary', 'run-search-and-replace', true, ''); ?>
        </form>

        <style>
            .submit .button { display: none; }
            #run-search-and-replace, #primary-save { display: block; }
        </style>

        <?php
        $searchFor   = isset($_POST['search_value']) ? $_POST['search_value'] : '';
        $replaceWith = isset($_POST['replace_value']) ? $_POST['replace_value'] : '';
        $dryRun      = isset($_POST['dryrun_value']) ? $_POST['dryrun_value'] : '';
        $dryRun      = ($dryRun === 'on') ? '--dry-run' : '';

        $command  = "wp search-replace '$searchFor' '$replaceWith' ";
        $command .= "--all-tables-with-prefix 'wp_{$blogId}_*' ";
        $command .= '--network ';
        $command .= '--precise ';
        $command .= '--skip-columns=guid ';
        $command .= '--report-changed-only ';
        $command .= '--recurse-objects ';
        $command .= $dryRun;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $wpCliOutput = shell_exec($command);
        }

        if ($searchFor !== '' || $replaceWith !== '' || $dryRun !== '') {
            echo '<pre>' . esc_html($command) . '</pre>';
            echo '<pre>' . esc_html($wpCliOutput ?? '') . '</pre>';
        }
    }
}
