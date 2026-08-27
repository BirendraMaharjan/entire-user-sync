=== Entire User Sync ===
Contributors: aegkr
Tags: User Sync, Sync User Data
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WordPress plugin to synchronize user data between systems.

== Description ==

Entire User Sync provides a simple, secure way to synchronize WordPress user data with other WordPress sites. It includes a REST API endpoint, admin tools and logging to assist with debugging and audit.

== Installation ==

1. Upload the `entire-user-sync` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings under Settings → Entire User Sync (if available).

== Frequently Asked Questions ==

= How do I trigger a sync manually? =
Use the admin UI or the REST endpoint `wp-json/eus/v1/sync` when authenticated.

= Is WooCommerce required? =
No. The plugin can operate without WooCommerce, but it includes compatibility checks if WC is present.

= Source Code =

The complete source code and build tools for this plugin are available at:

[https://github.com/BirendraMaharjan/entire-user-sync](https://github.com/BirendraMaharjan/entire-user-sync)

The source files used to generate the compiled assets are located in:

* JavaScript source files: `assets/src/js/`
* CSS source files: `assets/src/scss/`

Compiled production assets are located in:

* `assets/build/`

= Build & Development =

The JavaScript and CSS files in the `assets/build/` directory are generated from the source files in `assets/src/js/` and `assets/src/scss/` using WordPress Scripts and Webpack.

**Build Tools:**

* Node.js and npm
* @wordpress/scripts
* Webpack

**Building the plugin from source:**

1. Install dependencies:

`npm install`

2. Build production assets:

`npm run build`

3. Watch for development changes:

`npm start`

= Third-Party Libraries =

[Select2](https://github.com/select2/select2)
Copyright (c) 2012-2017 Kevin Brown, Igor Vaynberg, and Select2 contributors
Licensed under the MIT License (MIT).

== Screenshots ==

1. (none)

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
This is the first release of the plugin.


