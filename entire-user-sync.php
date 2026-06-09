<?php
/**
 * Plugin Name:       Entire User Sync
 * Plugin URI:        https://github.com/BirendraMaharjan/vi-account-manager
 * Description:       Account Manager for WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Birendra Maharjan
 * Author URI:        https://www.linkedin.com/in/birendramaharjan/
 * Text Domain:       entire-user-sync
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/BirendraMaharjan/entire-account-manager
 */

defined( 'ABSPATH' ) || exit;

define( 'ENTIRE_USER_SYNC_FILE', __FILE__ );
define( 'ENTIRE_USER_SYNC_PATH', plugin_dir_path( __FILE__ ) );

if ( ! file_exists( ENTIRE_USER_SYNC_PATH . 'vendor/autoload.php' ) ) {

	add_action(
		'admin_notices',
		static function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'Entire User Sync is missing Composer dependencies. Run composer install.',
						'entire-user-sync'
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	return;
}

require_once ENTIRE_USER_SYNC_PATH . 'vendor/autoload.php';

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook(
	ENTIRE_USER_SYNC_FILE,
	array( 'EntireUserSync\Config\Setup', 'activation' )
);
register_deactivation_hook(
	ENTIRE_USER_SYNC_FILE,
	array( 'EntireUserSync\Config\Setup', 'deactivation' )
);

add_action(
	'plugins_loaded',
	static function () {
		new EntireUserSync\Init();
	}
);