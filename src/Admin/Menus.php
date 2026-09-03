<?php
/**
 * Admin menu registration and page routing.
 *
 * Creates the top-level plugin menu and subpages and delegates rendering.
 *
 * @package EntireUserSync\Admin
 */

namespace EntireUserSync\Admin;

use EntireUserSync\Admin\Settings\Settings;
use EntireUserSync\Common\Abstracts\Base;

/**
 * Registers admin menus and dispatches page rendering.
 */
class Menus extends Base {

	/**
	 * Initialize menu registration and hooks.
	 */
	public function init(): void {

		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
		add_action( 'current_screen', array( $this, 'suppress_admin_notices' ) );
		add_filter(
			'plugin_action_links_' . $this->plugin->plugin_basename(),
			array( $this, 'settings_link' )
		);
	}

	/**
	 * Add allowed redirect host entries.
	 *
	 * @param array $hosts Current hosts.
	 * @return array Modified hosts.
	 */
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'wordpress.org';
		return $hosts;
	}

	/**
	 * Suppress admin notices when viewing the plugin pages to keep UI clean.
	 */
	public function suppress_admin_notices(): void {
		if (
			! isset( $_GET['page'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! str_starts_with( sanitize_key( $_GET['page'] ), $this->plugin->slug() ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

	/**
	 * Register top-level and submenu pages for the plugin.
	 */
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

		$settings = new Settings();
		if ( $settings->get( 'configuration', 'enable_log' ) ) {
			$logs = add_submenu_page(
				$this->plugin->slug(),
				__( 'Logs', 'entire-user-sync' ),
				__( 'Logs', 'entire-user-sync' ),
				'manage_options',
				$this->plugin->slug() . '-logs',
				array( $this, 'render_page' )
			);
			add_action( 'load-' . $logs, array( $this, 'add_logs_screen_options' ) );
			add_filter( 'manage_' . $logs . '_columns', array( $this, 'get_logs_columns' ) );
		}

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

	/**
	 * Add a settings link to the plugin list row.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function settings_link( $links ) {
		$url = add_query_arg(
			array(
				'page' => $this->plugin->slug(),
			),
			admin_url( 'admin.php' )
		);

		$settings_link = '<a href="' . esc_url( $url ) . '">' .
							esc_html__( 'Settings', 'entire-user-sync' ) .
						'</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the plugin admin page based on requested subpage.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No permission', 'entire-user-sync' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_page = sanitize_key( $_GET['page'] ?? $this->plugin->slug() );
		$prefix   = $this->plugin->slug() . '-';

		$setting  = new Settings();
		$sections = $setting->get_sections();
		$page     = array_key_first( $sections );

		if ( str_starts_with( $raw_page, $prefix ) ) {
			$page = substr( $raw_page, strlen( $prefix ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = array(
			'plugin'     => $this->plugin,
			'page'       => $page,
			'sections'   => $sections,
			'setting'    => $setting,
			'active_tab' => sanitize_key( $_GET['tab'] ?? array_key_first( $sections ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$this->render_template( $data );
	}

	/**
	 * Render admin template with the provided data.
	 *
	 * @param array $data Template data.
	 */
	private function render_template( array $data ): void {
		$plugin     = $data['plugin'];
		$page       = $data['page'];
		$sections   = $data['sections'];
		$setting    = $data['setting'];
		$active_tab = $data['active_tab'];

		require $this->plugin->template_path() . '/admin/page.php';
	}

	/**
	 * Add screen options for the logs page.
	 */
	public function add_logs_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Logs per page', 'entire-user-sync' ),
				'default' => 20,
				'option'  => 'entireus_logs_per_page',
			)
		);
	}

	/**
	 * Handle screen option value for the logs page.
	 *
	 * @param bool|int $status Current status.
	 * @param string   $option Option name.
	 * @param mixed    $value  Option value.
	 *
	 * @return mixed Modified status or value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'entireus_logs_per_page' === $option ) {
			return absint( $value );
		}

		return $status;
	}

	/**
	 * Get the columns for the logs table.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function get_logs_columns( $columns ): array {
		return array_merge(
			$columns,
			array(
				'created_at'  => __( 'Date', 'entire-user-sync' ),
				'event'       => __( 'Event', 'entire-user-sync' ),
				'direction'   => __( 'Direction', 'entire-user-sync' ),
				'user_email'  => __( 'User Email', 'entire-user-sync' ),
				'source_site' => __( 'Source Site', 'entire-user-sync' ),
				'target_site' => __( 'Target Site', 'entire-user-sync' ),
				'status'      => __( 'Status', 'entire-user-sync' ),
				'message'     => __( 'Message', 'entire-user-sync' ),
			)
		);
	}
}
