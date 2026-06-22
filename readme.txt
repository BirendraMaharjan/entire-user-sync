=== Entire User Sync ===
Contributors: aegkr
Tags: users, sync, rest api, logging
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Lightweight WordPress plugin to synchronize user data between systems.

== Description ==

Entire User Sync provides a simple, secure way to synchronize WordPress user data with external systems. It includes a REST API endpoint, admin tools and logging to assist with debugging and audit.

== Installation ==

1. Upload the `entire-user-sync` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings under Settings → Entire User Sync (if available).

== Frequently Asked Questions ==

= How do I trigger a sync manually? =
Use the admin UI or the REST endpoint `wp-json/eus/v1/sync` when authenticated.

= Is WooCommerce required? =
No. The plugin can operate without WooCommerce, but it includes compatibility checks if WC is present.

== Screenshots ==

1. (none)

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== A brief Markdown Example ==

This file is the WordPress.org readme.txt format. For developer info see `README.md` in the plugin root.


