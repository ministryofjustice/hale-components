# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress plugin for the Hale theme (MoJ Digital & Technology). Deployed to WP multisite. Two distinct admin UIs:
- Per-site settings: `/wp-admin/admin.php?page=mojComponentSettings`
- Network dashboard (multisite only): `/wp-admin/network/settings.php?page=hale-components-network-dashboard`

## Build (frontend assets)

```bash
npm install
npm run dev        # development build
npm run production # production build with versioning
npm run watch      # watch mode
```

Laravel Mix compiles `assets/scss/login.scss` and `assets/scss/hc-network-dashboard.scss` into `dist/css/`.

## Architecture

Single OOP layer — all components are PSR-4 autoloaded from `component/`.

Entry point: `hale-components.php`. Bootstraps the autoloader, instantiates all components directly.

Namespace `MOJComponents\`. PSR-4 mapping: `"MOJComponents\\" => "component/"` (defined in root `composer.json`).

Global `$mojHelper` (instance of `Helper`) is created first and injected into every component via `global $mojHelper`. `AdminSettings` registers all tab-based settings by iterating `$mojHelper->adminSettings` — components self-register by calling `$mojHelper->initSettings(ClassName::class)` in their constructors.

Each component follows this pattern:
- Constructor calls `$this->actions()` to register WP hooks
- If it has settings UI: sets `$this->hasSettings = true`, implements `settingsSectionCB()` and `settingsFields()`
- Settings stored under option key `moj-component-<lowercased-classname>`
- Per-component assets live in `component/<Name>/assets/css/` and `assets/js/`, referenced via `$this->helper->cssPath(__FILE__)` / `jsPath(__FILE__)`

**Components:**
| Class | Purpose | Multisite only |
|-------|---------|----------------|
| `Helper` | Global utility methods, mail, cron intervals | |
| `AdminSettings` | Tab-based settings page scaffold | |
| `Security` | Security hardening hooks | |
| `Users` | User switching, role management, site assignment | |
| `Sitemap` | Sitemap code generator | |
| `Head` | Custom `<head>` code injection | |
| `Analytics` | GTM integration | |
| `Introduce` | WP dashboard "Contact Us" widget | |
| `TaxonomyUpdater` | DB tool: rename taxonomy terms | |
| `ImportUsers` | DB tool: bulk user import | |
| `AcfFieldUpdater` | DB tool: update ACF `_postmeta` fields | |
| `Blocks` | Block editor filters (e.g. table accessibility) | |
| `Comments` | Disable comments sitewide | |
| `SitePathTracker` | Tracks site path slug on create/update | |
| `LoginSettings` | Custom login page branding | |
| `CloudFront` | Invalidate CloudFront cache on attachment delete | |
| `SiteUserReports` | CSV export of per-site users | |
| `SearchReplaceDatabase` | WP-CLI search/replace tool in network admin | |
| `NetworkDashboard` | Network admin dashboard + WAF bypass cookie | Yes |
| `RestApiRoutes` | REST endpoints for sites and blocks | Yes |
| `CleanUpUsers` | Remove unassigned users, delete unconfirmed signups | Yes |
| `NetworkUserReports` | CSV export of network-wide users | Yes |

## Adding a new component

1. Create `component/<Name>/<Name>.php` in namespace `MOJComponents\<Name>`
2. Add `use` and `new <Name>()` in `hale-components.php`
3. If it needs settings, call `$mojHelper->initSettings(<Name>Settings::class)` from the settings class constructor and set `$this->hasSettings = true`
4. Run `composer dump-autoload` from plugin root if autoloader needs regenerating
