<?php
/**
 * Handles plugin activation and deactivation routines.
 *
 * @package EntireUserSync\Common
 * @since   1.0.0
 */

namespace EntireUserSync\Config;

use EntireUserSync\Common\Traits\Singleton;
use EntireUserSync\Sync\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Setup
 *
 * Registered via register_activation_hook() and register_deactivation_hook()
 * in the main plugin file.
 *
 * @since 1.0.0
 */
final class Setup {

	use Singleton;

	/**
	 * Runs on plugin activation.
	 *
	 * - Creates required database tables.
	 * - Flushes rewrite rules.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function activation(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		Logger::create_table();

		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * - Clears scheduled cron events.
	 * - Flushes rewrite rules.
	 *
	 * Note: data is intentionally preserved on deactivation.
	 * Tables and options are only removed on uninstall (uninstall.php).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function deactivation(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Logger::drop_table();

		wp_clear_scheduled_hook( 'entireus_scheduled_sync' );

		flush_rewrite_rules();
	}
}