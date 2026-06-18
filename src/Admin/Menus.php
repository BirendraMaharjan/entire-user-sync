<?php

namespace EntireUserSync\Admin;

use EntireUserSync\Admin\Settings\Settings;
use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Sync\LogPage;

class Menus extends Base {

	private LogPage $log_page;
	public function init(): void {
		$this->log_page = new LogPage();

		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'current_screen', array( $this, 'suppress_admin_notices' ) );
		add_filter( 'plugin_action_links_' . $this->plugin->plugin_basename(), array( $this, 'settings_link' ) );
	}

	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'wordpress.org';
		return $hosts;
	}

	public function suppress_admin_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			! str_starts_with( sanitize_key( $_GET['page'] ), $this->plugin->slug() )
		) {
			return;
		}

		add_action(
			'in_admin_header',
			static function () {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			},
			1000
		);
	}

	public function register_menu(): void {
		add_menu_page(
			$this->plugin->title(),
			$this->plugin->title(),
			'manage_options',
			$this->plugin->slug(),
			array( $this, 'render_page' ),
			'dashicons-id-alt',
			10
		);

		add_submenu_page(
			$this->plugin->slug(),
			__( 'Configuration', 'entire-user-sync' ),
			__( 'Configuration', 'entire-user-sync' ),
			'manage_options',
			$this->plugin->slug(),
			array( $this, 'render_page' )
		);

		add_submenu_page(
			$this->plugin->slug(),
			__( 'Overview', 'entire-user-sync' ),
			__( 'Overview', 'entire-user-sync' ),
			'manage_options',
			$this->plugin->slug() . '-overview',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			$this->plugin->slug(),
			__( 'Logs', 'entire-user-sync' ),
			__( 'Logs', 'entire-user-sync' ),
			'manage_options',
			$this->plugin->slug() . '-logs',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			$this->plugin->slug(),
			__( 'License', 'entire-user-sync' ),
			__( 'License', 'entire-user-sync' ),
			'manage_options',
			$this->plugin->slug() . '-license',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			$this->plugin->slug(),
			__( 'Help', 'entire-user-sync' ),
			__( 'Help', 'entire-user-sync' ),
			'manage_options',
			$this->plugin->slug() . '-help',
			function () {
				wp_safe_redirect( 'https://wordpress.org/plugins/' );
				exit;
			}
		);
	}

	public function settings_link( $links ) {
		$url = add_query_arg(
			array(
				'page' => $this->plugin->slug(),
			),
			admin_url( 'admin.php' )
		);

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'entire-user-sync' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No permission' );
		}

		$raw_page = sanitize_key( $_GET['page'] ?? $this->plugin->slug() );

		$prefix = $this->plugin->slug() . '-';

		if ( str_starts_with( $raw_page, $prefix ) ) {
			$page = substr( $raw_page, strlen( $prefix ) );
		} else {
			$page = 'configuration';
		}

		$setting  = new Settings();
		$sections = $setting->get_sections();

		$data = array(
			'plugin'     => $this->plugin,
			'page'       => $page,
			'sections'   => $sections,
			'setting'    => $setting,
			'active_tab' => sanitize_key( $_GET['tab'] ?? array_key_first( $sections ) ),
		);

		$this->render_template( 'admin/page.php', $data );
	}

	private function render_template( string $file, array $data ): void {
		extract( $data, EXTR_SKIP );
		require $this->plugin->template_path() . '/' . $file;
	}
}
