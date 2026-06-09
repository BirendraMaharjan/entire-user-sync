<?php
/**
 * Admin module.
 *
 * @package EntireUserSync\Admin
 * @since   1.0.0
 */

namespace EntireUserSync\Admin;

use EntireUserSync\Admin\Settings\Settings;
use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Sync\LogPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Boots all wp-admin sub-modules.
 *
 * @since 1.0.0
 */
class Admin extends Base {

	/**
	 * Boot admin submodules.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		new Menus()->init();
		new Assets()->init();

		new Settings();
	}
}