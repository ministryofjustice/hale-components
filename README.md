# Hale Components

A WordPress plugin used by the Hale theme, allowing for a user to apply custom settings and run specialised tools.

## Install

Download to the /plugins folder of WordPress.

## Included in the plugin

### Custom settings

| Features   | Description                                        |
| ---------- | -------------------------------------------------- |
| Users      | Switch users and apply custom user settings        |
| Site Map   | Generate the code to populate a site map on a page |
| Head       | Add custom code into the WP head                   |
| Analytics  | Add GTM code in                                    |
| Custom API | Adds custom API endpoints - sites and blocks       |

### Tools

- Taxonomy Updater - A tool that makes a db update changing one taxonomy name for another.
- Import Users - A tool that adds users into the WP site user table
- ACF Meta Field Updater - Run a database update on the current site in the \_postmeta table.
- Network Dashboard available for controlling features across the whole platform.

### Plugin UI

Two backend interfaces are installed with this plugin. One for global network
features and one for individual site features.

Global multisite settings can be found at `wp-admin/network/settings.php?page=hale-components-network-dashboard`
Individual site settings can be found at `/wp-admin/admin.php?page=mojComponentSettings&moj-tab=component-tab-0`
