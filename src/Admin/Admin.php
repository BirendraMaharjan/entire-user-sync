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
 */
class Admin extends Base {

	/**
	 * Boot admin submodules.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		$menu = new Menus();
		$menu->init();

		$assets = new Assets();
		$assets->init();

		new Settings();
	}
}
