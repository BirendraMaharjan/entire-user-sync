<?php
/**
 * Sync service.
 *
 * Handles automatic user sync triggers, incoming remote lookups and user creation.
 *
 * @package EntireUserSync
 */

namespace EntireUserSync\Sync;

use EntireUserSync\Admin\Settings\Settings;
use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Common\Traits\Requester;
use EntireUserSync\Logger\Logger;
use WP_Error;
use WP_User;

/**
 * Class Sync
 *
 * Coordinates sync triggers, inbound authentication and user creation.
 */
class Sync extends Base {
	/**
	 * Requester.
	 */
	use Requester;

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
	 * Sync constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->init();
	}

	/**
	 * Register hooks for sync operations.
	 */
	public function init(): void {

		add_action( 'user_register', array( $this, 'maybe_auto_sync_user' ) );
		add_action( 'profile_update', array( $this, 'maybe_auto_sync_user' ) );

		// add_action( 'set_user_role', array( $this, 'maybe_auto_sync_user_role' ) );

		add_action( 'delete_user', array( $this, 'maybe_auto_delete_user' ) );
		// add_action( 'remove_user_from_blog', array( $this, 'maybe_auto_delete_user' ) );

		add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		/*add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );*/

		add_filter( 'authenticate', array( $this, 'maybe_import_remote_user' ), 20, 3 );
		// add_action( 'wp_login', array( $this, 'on_local_login' ), 10, 2 );
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
	 * Build a full REST endpoint for a site.
	 *
	 * @param array  $site Site config.
	 * @param string $route Route name.
	 *
	 * @return string Full URL endpoint.
	 */
	public function endpoint( array $site, string $route ): string {
		return trailingslashit( $site['url'] ?? '' ) . 'wp-json/entireus/v1/' . $route;
	}

	/**
	 * Return a human-friendly site key for result indexing.
	 *
	 * @param array $site Site config.
	 *
	 * @return string Key.
	 */
	public function site_key( array $site ): string {
		if ( ! empty( $site['label'] ) ) {
			return (string) $site['label'];
		}

		return (string) ( $site['url'] ?? '' );
	}

	/**
	 * Decide whether the user should be synced outbound.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if allowed, false otherwise.
	 */
	public function allow_sync( int $user_id ): bool {

		if ( defined( 'ENTIREUS_INCOMING_SYNC' ) || ! $this->auto_sync() ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->write_log(
				array(
					'event'      => 'sync',
					'direction'  => 'outgoing',
					'user_email' => '',
					'status'     => 'error',
					'message'    => 'User does not exist.',
					'payload'    => array(
						'hook'    => current_filter(),
						'user_id' => $user_id,
					),
				)
			);

			return false;
		}

		if ( empty( $user->roles ) ) {
			$user->roles = array( 'none' );
		}

		if ( empty( $this->get_roles() ) || ! array_intersect( $user->roles, $this->get_roles() ) ) {
			$this->write_log(
				array(
					'event'       => 'sync',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'status'      => 'error',
					'target_site' => '',
					'source_site' => $this->get_site_url(),
					'message'     => 'User does not have the required role for sync.',
					'payload'     => array(
						'hook'       => current_filter(),
						'user_email' => $user->user_email,
					),

				)
			);

			return false;
		}

		return true;
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
	 * @return false|WP_Error|WP_User Local WP_User instance or WP_Error on failure.
	 */
	public function create_user( array $data ) {

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
		);

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			$user_data['ID'] = $existing->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$username = sanitize_text_field( $data['user_login'] ?? '' );

			$user_data['user_login'] = $this->build_user_login( $username, $email );
			$user_data['user_pass']  = sanitize_text_field( $data['user_pass'] ?? '' );
			$user_id                 = wp_insert_user( $user_data );
		}

		$user = get_user_by( 'id', $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! empty( $data['roles'] ) && is_array( $data['roles'] ) ) {
			$this->apply_roles( $user_id, $data['roles'] );
		}

		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$this->apply_meta( $user_id, $data['meta'] );
		}

		update_user_meta( $user_id, '_entireus_imported_from', sanitize_text_field( $data['site_url'] ?? '' ) );

		return $user;
	}

	/**
	 * Apply roles to a WP_User instance, filtering by allowed list.
	 *
	 * @param int   $user_id User ID to modify.
	 * @param array $roles Roles from remote payload.
	 *
	 * @return void
	 */
	public function apply_roles( int $user_id, array $roles ): void {
		$wp_user = new WP_User( $user_id );

		$valid_roles = array_intersect(
			array_map( 'sanitize_key', $roles ),
			$this->get_roles()
		);

		$role = reset( $valid_roles );

		if ( $role && ( get_role( $role ) || $role === 'none' ) ) {
			if ( $role === 'none' ) {
				$wp_user->set_role( '' );
			} else {
				$wp_user->set_role( $role );
			}
		} elseif ( empty( $wp_user->roles ) ) {
			$default_role = get_option( 'default_role', 'subscriber' );

			if ( get_role( $default_role ) ) {
				$wp_user->set_role( $default_role );
			}
		}
	}

	/**
	 * Apply user meta from remote payload respecting allowed meta keys.
	 *
	 * @param int   $user_id User ID to update.
	 * @param array $meta Meta array from remote.
	 *
	 * @return void
	 */
	public function apply_meta( int $user_id, array $meta ): void {
		$allowed_meta_keys = $this->get_meta_keys();
		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( empty( $allowed_meta_keys ) || in_array( $key, $allowed_meta_keys, true ) ) {
				update_user_meta( $user_id, $key, $value );
			}
		}
	}

	/**
	 * Send a signed HTTP request to an endpoint.
	 *
	 * @param string $endpoint URL to call.
	 * @param array  $data Data to send.
	 * @param string $method HTTP method.
	 *
	 * @return array|WP_Error
	 */
	public function send_request( string $endpoint, array $data, string $method = 'POST' ) {
		$body      = wp_json_encode( $data );
		$signature = hash_hmac( 'sha256', $body, $this->get_secret() );

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-EntireUS-Signature' => $signature,
					'X-EntireUS-Timestamp' => time(),
					'X-EntireUS-Site'      => $this->get_site_url(),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$decoded = array();

		if ( '' !== $body ) {
			$decoded = json_decode( $body, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error(
					'entireus_invalid_json',
					'Invalid JSON response from remote site.',
					array(
						'code'     => $code,
						'body'     => $body,
						'endpoint' => $endpoint,
						'status'   => 'error',
					)
				);
			}
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'entireus_remote_error',
				$decoded['message'] ?? sprintf( 'Remote request failed (%d).', $code ),
				array(
					'code'     => $code,
					'data'     => $decoded,
					'endpoint' => $endpoint,
					'status'   => 'error',
				)
			);
		}

		// Optionally include the HTTP status code.
		$decoded['code'] = $code;

		return $decoded;
	}

	/**
	 * Triggered when a user is registered/updated; conditionally send the user.
	 *
	 * @param int $user_id User ID.
	 */
	public function maybe_auto_sync_user( int $user_id ): void {

		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}

		if ( ! defined( 'ENTIREUS_INCOMING_SYNC' ) ) {
			define( 'ENTIREUS_INCOMING_SYNC', true );
		}

		$this->sync_user( $user_id );
	}

	/**
	 * Sync a single user to configured remote sites.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array Results per site.
	 */
	public function sync_user( int $user_id ): array {
		$user = get_userdata( $user_id );

		$results = array();

		foreach ( $this->get_active_sites() as $site ) {

			$request_payload = $this->build_payload( $user, $site );
			$response        = $this->send_request(
				$this->endpoint( $site, 'sync-user' ),
				$request_payload
			);

			$results[ $this->site_key( $site ) ] = $response;

			if ( is_wp_error( $response ) ) {
				$this->write_log(
					array(
						'event'       => 'update',
						'direction'   => 'outgoing',
						'user_email'  => $user->user_email,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => $response->get_error_data(),
					)
				);

				continue;
			}

			$this->write_log(
				array(
					'event'       => 'update',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
					'status'      => 'success',
					'message'     => $response['message'] ?? '',
					'payload'     => $response,
				)
			);
		}

		return $results;
	}

	/**
	 * Build the outgoing payload for a user.
	 *
	 * @param WP_User $user User object.
	 * @param array   $site Site config.
	 *
	 * @return array Payload array.
	 */
	private function build_payload( WP_User $user, array $site ): array {
		$payload = array(
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'user_url'     => $user->user_url,
			'user_pass'    => $_POST['password'] ?? $user->user_pass,
			'description'  => $user->description,
			'roles'        => empty( $user->roles ) ? array( 'none' ) : $user->roles,
			'source_site'  => $this->get_site_url(),
			'target_site'  => $site['url'],
			'hook'         => current_filter(),
		);

		$meta_keys       = $this->get_meta_keys();
		$payload['meta'] = array();
		foreach ( $meta_keys as $key ) {
			$payload['meta'][ $key ] = get_user_meta( $user->ID, $key, true );
		}

		return $payload;
	}

	/**
	 * Conditionally delete a user on remote sites when local user is removed.
	 *
	 * @param int $user_id User ID.
	 */
	public function maybe_auto_delete_user( int $user_id ): void {

		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$this->delete_user( $user->user_email );
	}

	/**
	 * Request remote sites to delete a user.
	 *
	 * @param string $email User email.
	 *
	 * @return array Results per site.
	 */
	public function delete_user( string $email ): array {
		$results = array();

		foreach ( $this->get_active_sites() as $site ) {

			$response = $this->send_request(
				$this->endpoint( $site, 'delete-user' ),
				array(
					'email'       => $email,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
				),
				'DELETE'
			);

			$results[ $this->site_key( $site ) ] = $response;

			if ( is_wp_error( $response ) ) {
				$this->write_log(
					array(
						'event'       => 'delete',
						'direction'   => 'outgoing',
						'user_email'  => $email,
						'source_site' => $this->get_site_url(),
						'target_site' => $site['url'],
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => $response->get_error_data(),
					)
				);

				continue;
			}

			$this->write_log(
				array(
					'event'       => 'delete',
					'direction'   => 'outgoing',
					'user_email'  => $email,
					'source_site' => $this->get_site_url(),
					'target_site' => $site['url'],
					'status'      => 'success',
					'message'     => $response['message'] ?? '',
					'payload'     => $response,
				)
			);
		}

		return $results;
	}

	/**
	 * Attempt to authenticate by checking remote sites for the user.
	 *
	 * @param WP_User|WP_Error|null $user Authenticated user, WP_Error or null.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 *
	 * @return WP_User|WP_Error|null
	 */
	public function maybe_import_remote_user( $user, string $username, string $password ) {

		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( empty( $username ) ) {
			return $user;
		}

		foreach ( $this->get_active_sites() as $site ) {

			$response = $this->send_request(
				$this->endpoint( $site, 'get-user' ),
				array(
					'username'    => $username,
					'user_pass'   => $password,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
				)
			);

			if ( is_wp_error( $response ) || empty( $response ) ) {

				$this->write_log(
					array(
						'event'       => 'login',
						'direction'   => 'outgoing',
						'user_email'  => $username,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => $response->get_error_data(),
					)
				);

				continue;
			}

			$remote_user = $response['user'] ?? null;
			if ( empty( $remote_user ) ) {
				$this->write_log(
					array(
						'event'       => 'login',
						'direction'   => 'outgoing',
						'user_email'  => $username,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => 'No user found on remote site.',
						'payload'     => $response,
					)
				);

				continue;
			}

			$remote_user['user_pass'] = $password;
			$local_user               = $this->create_user( $remote_user );

			if ( is_wp_error( $local_user ) ) {
				$this->write_log(
					array(
						'event'       => 'login',
						'direction'   => 'outgoing',
						'user_email'  => $username,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => $local_user->get_error_message(),
						'payload'     => $response,
					)
				);

				return $local_user;
			}

			$this->write_log(
				array(
					'event'       => 'login',
					'direction'   => 'outgoing',
					'user_email'  => $username,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
					'status'      => 'success',
					'message'     => 'User imported and logged in from remote site',
					'payload'     => $response,
				)
			);

			return $local_user;
		}

		return $user;
	}

	/**
	 * Handle password reset event.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $new_pass New password.
	 */
	public function on_password_reset( \WP_User $user, string $new_pass ): void {
		if ( ! $this->allow_sync( $user->ID ) ) {
			return;
		}

		$this->sync_password( $user, $new_pass );
	}

	/**
	 * Handle low-level set password action.
	 *
	 * @param string $password New password.
	 * @param int    $user_id User ID.
	 */
	public function on_set_password( string $password, int $user_id ): void {
		if ( $user_id ) {
			$this->sync_password( $user_id, $password );
		}
	}

	/**
	 * Sync a user's password hash to remote sites.
	 *
	 * @param int|WP_User $user User ID or user object.
	 * @param string      $password Password hash.
	 *
	 * @return array Results per site.
	 */
	public function sync_password( $user, string $password ): array {
		$results = array();

		if ( ! $user instanceof WP_User ) {
			$user = get_userdata( (int) $user );
		}

		if ( ! $user instanceof WP_User ) {
			return $results;
		}

		foreach ( $this->get_active_sites() as $site ) {
			$response = $this->send_request(
				$this->endpoint( $site, 'sync-password' ),
				array(
					'user_email'  => $user->user_email,
					'user_pass'   => $password,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
				)
			);

			$results[ $this->site_key( $site ) ] = $response;

			if ( is_wp_error( $response ) ) {
				$this->write_log(
					array(
						'event'       => 'password',
						'direction'   => 'outgoing',
						'user_email'  => $user->user_email,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => $response->get_error_data(),
					)
				);

				continue;
			}

			$this->write_log(
				array(
					'event'       => 'password',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
					'status'      => 'success',
					'message'     => $response['message'] ?? '',
					'payload'     => $response,
				)
			);
		}

		return $results;
	}

	/**
	 * Write a log if logging is enabled.
	 *
	 * @param array $args Log arguments.
	 */
	public function write_log( array $args ): void {
		if ( ! $this->settings()->get( 'configuration', 'enable_log' ) ) {
			return;
		}

		$logger = new Logger();

		$logger->log( $args );
	}
}
