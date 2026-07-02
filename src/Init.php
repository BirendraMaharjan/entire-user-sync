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
use EntireUserSync\Sync\Api;
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
		$this->load_modules();
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
			$admin = new Admin();
			$admin->init();
		}

		if ( $this->is_request( 'frontend' ) ) {
			$frontend = new Frontend();
			$frontend->init();
		}

		new Sync();
		new Api();
	}
}
