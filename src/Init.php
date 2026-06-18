<?php
/**
 * Plugin bootstrap.
 *
 * @package EntireUserSync
 * @since   1.0.0
 */

namespace EntireUserSync;

use EntireUserSync\Admin\Admin;
use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Common\Traits\Requester;
use EntireUserSync\Frontend\Frontend;
use EntireUserSync\Sync\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * Main plugin bootstrap. Loads translations and initialises
 * Admin, Frontend, and Sync modules based on the request context.
 *
 * @package EntireUserSync
 * @since   1.0.0
 */
final class Init extends Base {

	use Requester;

	/**
	 * Constructor.
	 *
	 * Calls the parent constructor to set up the plugin config object,
	 * then kicks off the bootstrap sequence.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();
		$this->setup();
	}

	/**
	 * Run the bootstrap sequence.
	 *
	 * Loads the text domain first so all subsequent strings are translatable,
	 * then registers modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function setup(): void {
		$this->load_textdomain();
		$this->load_modules();
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * Hooked early enough that all `__()` / `_e()` calls throughout the
	 * plugin will resolve correctly. The .mo file is expected at:
	 *   /languages/entire-user-sync-{locale}.mo
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'entire-user-sync',
			false,
			$this->plugin->plugin_path() . '/languages'
		);
	}

	/**
	 * Conditionally load plugin modules based on the current request type.
	 *
	 * - Admin  : loaded only for wp-admin / AJAX requests.
	 * - Frontend: loaded only for front-end (non-admin) requests.
	 * - Sync   : loaded for every request type (REST, cron, CLI, etc.).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_modules(): void {
		if ( $this->is_request( 'admin' ) ) {
			( new Admin() )->init();
		}

		if ( $this->is_request( 'frontend' ) ) {
			( new Frontend() )->init();
		}

		new Sync();
	}
}
