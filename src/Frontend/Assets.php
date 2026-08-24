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
		$asset_manifest = $this->plugin->plugin_path() . '/assets/build/frontend.asset.php';

		$asset = file_exists( $asset_manifest ) ?
			require $asset_manifest : array(
				'dependencies' => array( 'jquery', 'wp-i18n' ),
				'version'      => $this->plugin->version(),
			);

		wp_enqueue_style(
			$this->plugin->slug() . '-frontend',
			$this->plugin->url() . '/assets/build/frontend.css',
			array(),
			$this->plugin->version()
		);

		wp_enqueue_script(
			$this->plugin->slug() . '-frontend',
			$this->plugin->url() . '/assets/build/frontend.js',
			$asset['dependencies'],
			$this->plugin->version(),
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			$this->plugin->slug() . '-frontend',
			'entireUsAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'entire_nonce' ),
			)
		);
	}
}
