=== WP Umbrella: Update Backup Restore & Monitoring ===

Contributors: gmulti, truchot, wplio
Tags: monitoring, backups, backup, restore, update
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: v2.24.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Everything you need to sell WordPress maintenance and manage multiple sites effortlessly: backup, update, uptime monitoring, and security.

== Description ==

WP Umbrella empowers agencies and WordPress developers to master WordPress maintenance, and manage multiple sites effortlessly. Key features include:

* Dashboard: Monitor, update, and backup all your sites from a single dashboard.
* Automated Cloud Backup: Secured, incremental backup with GDPR compliance, ensuring your data's safety and easy backup restoration. WP Umbrella provide with GDPR Backup.
* One-Click Updates: Update core, themes, and plugins, maintaining security and performance. Update Rollback available. Exclude update and ignore updates too.
* Uptime Monitoring: Stay informed with alerts on uptime, downtime, and site performance, including Google Page Speed monitoring.
* Error Tracking: Monitor PHP errors to maintain a safe website.
* Security monitoring: monitor vulnerabilities and security metrics.
* Reports: automate your reporting on update, GDPR backup, uptime, etc.

WP Umbrella is the best alternative to ManageWP, MainWP, WP Remote, InfiniteWP.


== WordPress Management Features ==

* Multiple Sites Management: manage and log into your WordPress sites with a unified dashboard.
* Update Management: Bulk update plugins, and themes in 1-click. Rollback included.
* Backup and Restoration: automated and scheduled backups. Backup WordPress now!
* Comprehensive Monitoring: From uptime to WordPress errors.

= Premium / Freemium =

Create an account and enjoy 14 day trial with all features (backup, uptime monitoring, safe update, etc). Then you only have access to our health check.

== Installation ==

= Minimum Requirements for WP Umbrella =
* WordPress 5.8 or greater
* PHP version 7.4 or greater

== Frequently Asked Questions ==

= Why do I need WP Umbrella ? =

WP Umbrella is an all-in-one tool for managing multiple WordPress sites. Save time with centralized backup, monitoring, updates, and restoration. Perfect for developers and agencies managing multiple sites.

= Is WordPress maintenance needed? =

Routine maintenance keeps WordPress sites secure, updated, and optimized. WP Umbrella makes site management simple with automated backups, uptime monitoring, and bulk updates.

= How does WP Umbrella handle backups? =

We offer GDPR-compliant backups on Google Cloud servers in Europe. Our GDPR backup system store your backups during 50 days. Our GDPR backups are incremental and the backup encrypted.

= How can I bulk update WordPress ? =

WP Umbrella’s update manager lets you update all plugins, themes, and WordPress core across multiple sites in one click. Rollback, Enable or disable automatic updates as needed.

= What do you monitor? =

WP Umbrella includes uptime and downtime alerts, performance checks, and Google PageSpeed insights. Get notified instantly for any site issues, allowing quick resolutions. Read our guide about [WordPress monitoring!](https://wp-umbrella.com/blog/monitoring-wordpress-the-ultimate-guide/) for more info.

= How can I manage multiple WordPress sites? =

We suggest you to read our guide about [How to manage multiple WordPress sites easily](https://wp-umbrella.com/blog/manage-multiple-wordpress-sites-one-dashboard/)

= Does WP Umbrella work with multisite ? =

Yes, WP Umbrella fully supports Multisite networks, allowing backups, updates, and monitoring across all sites in a network.

= How are you better than ManageWP? =

WP Umbrella is faster, and more reliable than alternatives like ManageWP, MainWP, and WP Remote. Features include accurate monitoring (no false positives), GDPR backups, and a user-friendly dashboard.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.]( https://patchstack.com/database/vdp/dc85fd1d-7634-4195-bc42-b2f50c1aaf5b )

== Changelog ==

= 2.24.3 (06-10-2026) =
- Fixed: updating several plugins in a row no longer fails after the first one on some hosting setups.
- Fixed: updates are no longer reported as failed when the plugin or theme was already at the latest version.
- Fixed: broken link highlighting now works on sites served from a page cache.
- Improved: premium plugin and theme updates are more reliable.
- Improved: when no update is actually available for a plugin or theme, the task now ends quickly with a clear explanation.
- Improved: a working site is no longer rolled back when an update finishes without changing the version.
- Improved: failed updates are reported faster, with a clearer explanation of the most likely cause.
- Improved: connecting a site now surfaces PHP errors detected on it, to make troubleshooting easier.
- Improved: clearing the cache from WP Umbrella now also flushes Redis and Memcached object caches.

= 2.24.2 (05-21-2026) =
- Improved: connection reliability on a wider range of hosting setups and sites running additional plugins.
- Fixed: white label is correctly applied on connected sites.

= 2.24.1 (05-19-2026) =
- Fixed: connection no longer fails on sites where a security plugin or a custom snippet restricts unauthenticated access to the WordPress REST API.

= 2.24.0 (05-19-2026) =
- New: WP-CLI command to connect a site to WP Umbrella with your API key, with workspace selection when you belong to several.
- Improved: when your site is protected by HTTP Basic Authentication, the plugin now verifies your credentials against your site before saving them, and skips saving them when they are not actually needed.
- Improved: connection stability with the WP Umbrella backend.
- Improved: support page now includes a "Test ping" diagnostic action.
- Fixed: HTTP Auth credentials entered during connection are now reliably saved and used on every subsequent communication with your site.
- Fixed: plugin path resolution on hosts that use symlinked plugin directories.

= 2.23.0 (05-11-2026) =
- New: Activity Log on demand
- Improved: per-extension backup file size limits
- Improved: support page now describes each setting and ships a buffer cleanup action
- Fixed: skip non-instantiable classes when wiring action hooks

= 2.22.5 (04-23-2026) =
- Improved: backup reliability and error handling
- Improved: backup exclusion list accuracy
- Improved: Pressable compatibility
- Improved: system report stability and customization
- Fixed: fatal error in Content Selector on Pressable environments
- Fixed: backup symlink loop detection
- Fixed: backup resource handle safety on closed streams

= 2.22.4 (04-15-2026) =
- Improved: database table enumeration compatibility across MySQL versions
- Improved: restore script reliability with SQL mode handling
- Improved: update process stability and race condition prevention
- Improved: compatibility with Really Simple SSL Pro
- Improved: error handling on PHP 8.0+
- Fixed: edge cases in plugin update version detection

= 2.22.3 (04-09-2026) =
- New: request trace breadcrumbs for update diagnostics
- New: support for SiteGround cache
- Improved: update state machine to prevent race conditions during rollback
- Fixed: use move_dir() instead of copy_dir() in rollback backup directory

= 2.22.2 (04-02-2026) =
- Fixed: auto rollback on failed update
- Fixed: PHP error with W3 Total Cache

= 2.22.1 (03-31-2026) =
- Improved: broken link checker crawling for better reliability
- Improved: backup scan performance for large sites
- Improved: redirect router performance
- Improved: support page redesign with data counts
- Fixed: plugin rollback on partial corruption after failed upgrade
- Fixed: PHP 8.2+ compatibility in checksum generator
- Fixed: WP Engine API key redefinition warning

= 2.22.0 (03-11-2026) =
- New: Broken link checker on demand
- Improved: remove old backup process
- Improved: prevent plugin and theme update process on failure
- Improved: add debug log on update process
- Fixed: PHP warning on update process

= 2.21.0 (02-05-2026) =
- Improved: API connectivity
- Improved: backup process for databases
- Improved: compatibility with SiteGround
- Improved: compatibility with WordPress.com
- Improved: disable WordPress actions during update process to prevent conflicts

= 2.20.1 (01-08-2026) =
- Improved: backup performance settings

= 2.20.0 (12-16-2025) =
- Improved: traceability to guarantee the entire backup process

Full changelog available [Here!](https://wp-umbrella.com/change-log/)
