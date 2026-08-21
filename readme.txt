=== PluginRx Control Center ===
Contributors: apos37
Tags: site management, maintenance, updates, monitoring, logs
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: Proprietary
License URI: https://pluginrx.com/proprietary-license-agreement/

Centralized management and monitoring for multiple WordPress sites using the PluginRx Agent.

== Description ==

**PluginRx Control Center** is the command hub for managing, monitoring, and maintaining multiple WordPress sites from a single dashboard. Designed for developers, agencies, and site owners running more than one site, it aggregates system data from connected sites and allows you to take controlled remote actions without logging into each site individually.

This plugin works in tandem with the **PluginRx Agent** plugin, which must be installed on each managed site. The Control Center never executes remote actions directly; it securely orchestrates requests and displays results.

**Features:**

- View WordPress core version and update status across all sites
- Monitor PHP versions with upgrade warnings
- See active themes and plugin versions with update availability
- Remotely update WordPress core, themes, and plugins (agent-permitted)
- Check whether `WP_DEBUG` is enabled
- View admin email, multisite status, ABSPATH, and hosting IP

**Integrations:**

- View online user counts, total users, and debug log size per site if Developer Debug Tools is installed
- Track broken link counts if Broken Link Notifier is installed
- Track flagged or fake user accounts if Fake User Detector is installed
- Clear caches across all connected sites if Clear Cache Everywhere is installed

The Control Center is intentionally read-only by default. All destructive or mutating actions must be explicitly allowed by each connected site’s agent.

This plugin does not replace backups, hosting dashboards, or security plugins. It provides operational visibility and controlled maintenance actions. Backups should still be done separately.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pluginrx-control-center/` directory.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. Navigate to PluginRx Control Center > Settings to begin setup.
4. Install the PluginRx Agent plugin on each site you want to manage.
5. Connect each site using the secure pairing process.

== Frequently Asked Questions ==

= Is this plugin safe to use on production sites? =
Yes. The Control Center itself does not expose inbound endpoints and does not execute remote code. All remote actions are executed by the PluginRx Agent under strict capability controls.

= Do I need to install this on every site? =
No. The Control Center is installed on a single “host” site. All other sites only need the PluginRx Agent.

= Can I restrict what actions can be performed on a site? =
Yes. Each agent site explicitly allows or denies specific actions such as updates, log access, or cache clearing.

= Does this use XML-RPC or WP Cron? =
No. Communication is handled via authenticated REST API requests over HTTPS.

== Screenshots ==

1. Control Center dashboard showing all connected sites
2. Settings page showing where to add new sites
3. PluginRx Agent plugin settings showing the permissions

== Changelog ==
= 1.1.0 =
* Update: Removed license key requirement and licensing system
* Tweak: Plugin updates now check for new versions without license validation

= 1.0.6 =
* Fix: Update broken link notifier link
* Fix: If "Allow previous versions..." is unchecked for WP and PHP versions, it wasn't highlight red on patch difference
* Fix: Action buttons not re-enabling after check all functionality is done
* Update: Added a home/admin linked icon next to Site URL
* Tweak: Plugins and themes boxes now turn red if needing updates
* Compatibility: Tested with WordPress 7.0

= 1.0.5 =
* Fix: Validating license returning an error after clearing transients

= 1.0.4.8 =
* Fix: Checking for updates was failing and not checking often enough

= 1.0.4 =
* Tweak: Added alert confirmation to action buttons
* Update: Added host cache plugin request
* Update: Added admin users request
* Update: Added a hook to allow changing the menu name of the control center
* Update: Added progress of checking all sites at the top
* Tweak: Added site count to check all button
* Update: Added Purge Mail Errors action button

= 1.0.3 =
* Update: Added dashboard options
* Update: Added Clear Debug Log action with Developer Debug Tools plugin integration
* Update: Added Form Spam request with Advanced Tools for Gravity Forms plugin integration

= 1.0.2 =
* Update: Added integrations support with hooks
* Tweak: Expand on error descriptions when there is an issue executing actions
* Fix: Removed formatting on updates available after updating plugins and re-checking site

= 1.0.1 =
* Initial release of PluginRx Control Center
