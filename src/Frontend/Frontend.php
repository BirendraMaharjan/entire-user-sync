<?php
/**
 * Frontend module.
 *
 * @package EntireUserSync\Frontend
 * @since   1.0.0
 */

namespace EntireUserSync\Frontend;

use EntireUserSync\Common\Abstracts\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 *
 * Registers all public-facing (theme) hooks for the plugin.
 *
 * @since 1.0.0
 */
class Frontend extends Base {

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		// Instantiate Assets helper and initialize hooks.
		$assets = new Assets();
		$assets->init();
	}
}
