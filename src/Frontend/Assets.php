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
class Assets extends Base {

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue public stylesheet and script.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			$this->plugin->slug(),
			$this->plugin->url() . '/assets/build/frontend.css',
			array(),
			$this->plugin->version()
		);

		wp_enqueue_script(
			$this->plugin->slug(),
			$this->plugin->url() . '/assets/build/frontend.js',
			array( 'wp-i18n' ),
			$this->plugin->version(),
			true
		);
	}
}
