<?php
/**
 * Plugin Name:       Entire User Sync
 * Plugin URI:        https://github.com/BirendraMaharjan/entire-user-sync
 * Description:       User sync for WordPress.
 * Version:           1.0.0
 * Author:            Birendra Maharjan
 * Author URI:        https://www.linkedin.com/in/birendramaharjan/
 * Text Domain:       entire-user-sync
 * Domain Path:       /languages
 *
 * @package EntireUserSync
 * @author  Birendra Maharjan
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

const ENTIREUS_FILE = __FILE__;
define( 'ENTIREUS_PATH', plugin_dir_path( ENTIREUS_FILE ) );
const ENTIREUS_SECURITY_KEY = 'f8Om2Na9ma4Sh7iv6ay3Ne5pa1Ls9JqP';

if ( ! file_exists( ENTIREUS_PATH . 'vendor/autoload.php' ) ) {

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

require_once ENTIREUS_PATH . 'vendor/autoload.php';

// Activation / Deactivation.
register_activation_hook(
	ENTIREUS_FILE,
	array( 'EntireUserSync\Config\Setup', 'activation' )
);
register_deactivation_hook(
	ENTIREUS_FILE,
	array( 'EntireUserSync\Config\Setup', 'deactivation' )
);

add_action(
	'plugins_loaded',
	static function () {
		new EntireUserSync\Init();
	}
);