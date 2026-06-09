<?php
/**
 * Admin assets.
 *
 * @package EntireUserSync\Admin
 * @since   1.0.0
 */

namespace EntireUserSync\Admin;

use EntireUserSync\Common\Abstracts\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Assets
 *
 * Enqueues styles, scripts, and passes data to JS on plugin admin pages.
 *
 * @since 1.0.0
 */
class Assets extends Base {

	/**
	 * Register hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, $this->plugin->slug() ) ) {
			return;
		}

		wp_enqueue_media();

		$this->enqueue_select2();
		$this->enqueue_main_assets();
		$this->localize_script();
	}

	/**
	 * Enqueue Select2 — use WooCommerce's bundled selectWoo if available,
	 * otherwise load from CDN.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function enqueue_select2(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'selectWoo' );
			return;
		}

		wp_enqueue_style(
			$this->plugin->slug() . '-select2',
			'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
			array(),
			'4.1.0'
		);

		wp_enqueue_script(
			$this->plugin->slug() . '-select2',
			'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0',
			true
		);
	}

	/**
	 * Enqueue the main compiled backend stylesheet and script.
	 *
	 * Reads the webpack/wp-scripts asset manifest when present so that
	 * auto-generated dependency arrays and content-hashed versions are used.
	 * Falls back to sensible defaults when the manifest is absent (e.g. in
	 * development before a first build).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function enqueue_main_assets(): void {
		$asset_manifest = $this->plugin->plugin_path() . '/assets/build/backend.asset.php';

		$asset = file_exists( $asset_manifest )
			? require $asset_manifest
			: array(
				'dependencies' => array( 'jquery', 'media-editor', 'media-upload' ),
				'version'      => $this->plugin->version(),
			);

		wp_enqueue_style(
			$this->plugin->slug() . '-admin',
			$this->plugin->url() . '/assets/build/backend.css',
			array(),
			$this->plugin->version()
		);

		wp_enqueue_script(
			$this->plugin->slug() . '-admin',
			$this->plugin->url() . '/assets/build/backend.js',
			$asset['dependencies'],
			$this->plugin->version(),
			true
		);
	}

	/**
	 * Pass server-side data to the admin JS bundle.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function localize_script(): void {
		wp_localize_script(
			$this->plugin->slug() . '-admin',
			'entireAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'entire_nonce' ),
				'i18n'    => array(
					'confirmReset' => __( 'Reset all fields in this section to their defaults? This cannot be undone.', 'entire-user-sync' ),
					'resetSuccess' => __( 'Section reset to defaults.', 'entire-user-sync' ),
					'resetError'   => __( 'Reset failed. Please try again.', 'entire-user-sync' ),
					'uploadTitle'  => __( 'Select Image', 'entire-user-sync' ),
					'uploadBtn'    => __( 'Use this image', 'entire-user-sync' ),
				),
			)
		);
	}
}