<?php
/**
 * Shared helper trait for the sync subsystem.
 *
 * Provides accessors for plugin settings, site lists and shared logger used by
 * Sync, Sender and Api classes.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Sync;

use EntireUserSync\Admin\Settings\Settings;
use WP_Error;
use WP_User;

trait SyncHelper {

	/**
	 * Settings instance (overrideable for tests).
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings_instance = null;

	/**
	 * Logger instance (created on demand).
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger_instance = null;

	/**
	 * Inject a Settings instance (for tests or overrides).
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function set_settings( Settings $settings ): void {
		$this->settings_instance = $settings;
	}

	/**
	 * Inject a Logger instance.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function set_logger( Logger $logger ): void {
		$this->logger_instance = $logger;
	}

	/**
	 * Return the Settings singleton for this plugin.
	 */
	private function settings(): Settings {
		if ( $this->settings_instance === null ) {
			$this->settings_instance = new Settings();
		}
		return $this->settings_instance;
	}

	/**
	 * Return the Logger instance, creating it if missing.
	 *
	 * @return Logger
	 */
	private function logger(): Logger {
		if ( $this->logger_instance === null ) {
			$this->logger_instance = new Logger();
			// Share the same settings instance with Logger.
			$this->logger_instance->set_settings( $this->settings() );
		}
		return $this->logger_instance;
	}

	/**
	 * Build a safe user_login string from remote payload or email.
	 *
	 * @param string $username
	 * @param string $email Sanitized user email.
	 *
	 * @return string
	 */
	public function build_user_login( string $username, string $email ): string {
		// Prefer remote user_login when provided; fall back to the local part of the email.
		$user_login = sanitize_user( $username );
		if ( empty( $user_login ) ) {
			$user_login = sanitize_user( strstr( $email, '@', true ) );
		}

		if ( username_exists( $user_login ) ) {
			$user_login .= '_' . substr( md5( $email ), 0, 5 );
		}

		return $user_login;
	}

	/**
	 * Create or update a local user from remote payload.
	 *
	 * @param array $data Remote user payload.
	 *
	 * @return WP_User|WP_Error Local WP_User instance or WP_Error on failure.
	 */
	public function create_user( array $data ): WP_User|WP_Error {

		$email = sanitize_email( $data['user_email'] ?? '' );
		if ( ! $email ) {
			return new WP_Error( 'entireus_bad_email', 'Remote user has no email' );
		}

		$user_data = array(
			'user_email'   => $email,
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'display_name' => sanitize_text_field( $data['display_name'] ?? '' ),
			'user_url'     => esc_url_raw( $data['user_url'] ?? '' ),
			'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
			'user_pass'    => sanitize_text_field( $data['password'] ?? '' ),
		);

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			$user_data['ID'] = $existing->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$username = sanitize_text_field( $data['user_login'] ?? '' );

			$user_data['user_pass']  = wp_generate_password();
			$user_data['user_login'] = $this->build_user_login( $username, $email );
			$user_id                    = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( ! empty( $data['roles'] ) && is_array( $data['roles'] ) ) {
			$this->apply_roles( $user_id, $data['roles'], $this->get_roles() );
		}

		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$this->apply_meta( $user_id, $data['meta'] );
		}

		update_user_meta( $user_id, '_entireus_imported_from', sanitize_text_field( $data['site_url'] ?? '' ) );

		return $user_id;
	}

	/**
	 * Apply roles to a WP_User instance, filtering by allowed list.
	 *
	 * @param int $user_id User ID to modify.
	 * @param array $roles Roles from remote payload.
	 *
	 * @return void
	 */
	public function apply_roles( int $user_id, array $roles ): void {
		$wp_user = new WP_User( $user_id );
		$wp_user->set_role( '' );
		foreach ( $roles as $role ) {
			$role = sanitize_key( $role );
			$wp_user->add_role( $role );
		}
	}

	/**
	 * Apply user meta from remote payload respecting allowed meta keys.
	 *
	 * @param int $user_id User ID to update.
	 * @param array $meta Meta array from remote.
	 *
	 * @return void
	 */
	public function apply_meta( int $user_id, array $meta ): void {
		$allowed_meta_keys       = $this->get_meta_keys();
		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( empty( $allowed_meta_keys ) || in_array( $key, $allowed_meta_keys, true ) ) {
				update_user_meta( $user_id, $key, $value );
			}
		}
	}

	/**
	 * Get secret key for remote signing.
	 */
	public function get_secret(): string {
		return $this->settings()->get( 'integrations', 'secret_key' ) ?? ENTIREUS_SECURITY_KEY;
	}

	/**
	 * Whether automatic outbound sync is enabled.
	 */
	public function auto_sync(): bool {
		return (bool) $this->settings()->get( 'setup', 'sync_enable' );
	}

	/**
	 * Return configured sites.
	 *
	 * @return array<int,array>
	 */
	public function get_sites(): array {
		return $this->settings()->get( 'setup', 'sites' ) ?? array();
	}

	/**
	 * Return only active sites.
	 *
	 * @return array<int,array>
	 */
	public function get_active_sites(): array {
		$sites = $this->get_sites();

		return array_values(
			array_filter(
				$sites,
				function ( $site ) {
					return isset( $site['active'] ) && '1' === $site['active'];
				}
			)
		);
	}

	public function is_allowed_site( string $site_url ): bool {
		$active_sites = $this->get_active_sites();

		return in_array( $site_url, array_column( $active_sites, 'url' ), true );
	}

	/**
	 * Return the current site url.
	 */
	public function get_site_url(): string {
		return get_site_url();
	}

	/**
	 * Return the route namespace.
	 */
	public function get_route_namespace(): string {
		return 'entireus/v1';
	}

	/**
	 * Return configured roles allowed for syncing.
	 */
	public function get_roles(): array {
		return $this->settings()->get( 'configuration', 'roles' ) ?? array();
	}

	/**
	 * Return configured meta keys to include in payloads.
	 */
	public function get_meta_keys(): array {
		return $this->settings()->get( 'configuration', 'sync_meta_keys' ) ?? array();
	}

	/**
	 * Generate and persist a new secret key.
	 */
	public function generate_and_save_secret(): string {
		$secret = bin2hex( random_bytes( 32 ) );
		$this->settings()->set( 'integrations', 'secret_key', $secret );
		return $secret;
	}

	/**
	 * Whether logging is enabled.
	 */
	public function enable_logging(): bool {
		return (bool) $this->settings()->get( 'configuration', 'enable_log' );
	}

	/**
	 * Write a log if logging is enabled.
	 *
	 * @param array $args Log arguments.
	 */
	public function write_log( array $args ): void {
		if ( ! $this->enable_logging() ) {
			return;
		}
		$this->logger()->log( $args );
	}
}
