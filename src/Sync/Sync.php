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
	 * Whether the current operation is caused by an incoming sync.
	 *
	 * Prevents remote changes from triggering another outbound sync.
	 *
	 * @var bool
	 */
	protected static bool $is_syncing = false;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings_instance = null;

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
		add_action( 'set_user_role', array( $this, 'maybe_auto_sync_user_role' ) );

		add_action( 'delete_user', array( $this, 'maybe_auto_delete_user' ) );
		// add_action( 'remove_user_from_blog', array( $this, 'maybe_auto_delete_user' ) );

		// add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );

		add_filter( 'authenticate', array( $this, 'maybe_import_remote_user' ), 20, 3 );
		// add_action( 'wp_login', array( $this, 'on_local_login' ), 10, 2 );
	}

	/**
	 * Return the Settings singleton for this plugin.
	 */
	private function settings(): Settings {
		if ( null === $this->settings_instance ) {
			$this->settings_instance = new Settings();
		}

		return $this->settings_instance;
	}

	/**
	 * Get secret key for remote signing.
	 *
	 * @return string Secret key.
	 */
	public function get_secret(): string {
		$secret = $this->settings()->get( 'integrations', 'secret_key' );

		return is_string( $secret ) && '' !== $secret ? $secret : ENTIREUS_SECURITY_KEY;
	}

	/**
	 * Whether automatic outbound sync is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public function auto_sync(): bool {
		return (bool) $this->settings()->get( 'setup', 'sync_enable' );
	}

	/**
	 * Return configured sites.
	 *
	 * @return array<int,array<string,mixed>> Configured sites.
	 */
	public function get_sites(): array {
		$sites = $this->settings()->get( 'setup', 'sites' );

		return is_array( $sites ) ? $sites : array();
	}

	/**
	 * Return only active sites.
	 *
	 * @return array<int,array<string,mixed>> Active sites.
	 */
	public function get_active_sites(): array {
		$active_sites = array();

		foreach ( $this->get_sites() as $site ) {
			if (
				is_array( $site ) &&
				! empty( $site['url'] ) &&
				! empty( $site['active'] )
			) {
				$active_sites[] = $site;
			}
		}

		return $active_sites;
	}

	/**
	 * Check if a site URL is in the active sites list.
	 *
	 * @param string $site_url Site URL to check.
	 *
	 * @return bool True if allowed.
	 */
	public function is_allowed_site( string $site_url ): bool {
		$site_url = untrailingslashit( esc_url_raw( $site_url ) );

		if ( '' === $site_url ) {
			return false;
		}

		foreach ( $this->get_active_sites() as $site ) {
			$configured_url = isset( $site['url'] ) ?
				untrailingslashit( esc_url_raw( $site['url'] ) ) :
				'';

			if ( $configured_url === $site_url ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the current site url.
	 *
	 * @return string Current site url.
	 */
	public function get_site_url(): string {
		return get_site_url();
	}

	/**
	 * Return the route namespace.
	 *
	 * @return string REST route namespace.
	 */
	public function get_route_namespace(): string {
		return 'entireus/v1';
	}

	/**
	 * Return configured roles allowed for syncing.
	 *
	 * @return array<int,string> Allowed roles.
	 */
	public function get_roles(): array {
		$roles = $this->settings()->get( 'configuration', 'roles' );

		if ( ! is_array( $roles ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_key', $roles )
			)
		);
	}

	/**
	 * Return configured meta keys to include in payloads.
	 *
	 * @return array<int,string> Allowed meta keys.
	 */
	public function get_meta_keys(): array {
		$meta_keys = $this->settings()->get( 'configuration', 'sync_meta_keys' );

		if ( ! is_array( $meta_keys ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_key', $meta_keys )
			)
		);
	}

	/**
	 * Build a full REST endpoint for a site.
	 *
	 * @param array  $site  Site config.
	 * @param string $route Route name.
	 *
	 * @return string Full URL endpoint.
	 */
	public function endpoint( array $site, string $route ): string {
		$site_url = isset( $site['url'] ) ? esc_url_raw( $site['url'] ) : '';

		return trailingslashit( $site_url ) .
				'wp-json/' .
				trim( $this->get_route_namespace(), '/' ) .
				'/' .
				ltrim( sanitize_key( $route ), '/' );
	}

	/**
	 * Return a human-friendly site key for result indexing.
	 *
	 * @param array<string,mixed> $site Site config.
	 *
	 * @return string Site key.
	 */
	public function site_key( array $site ): string {
		if ( ! empty( $site['label'] ) ) {
			return sanitize_text_field( $site['label'] );
		}

		return isset( $site['url'] ) ? esc_url_raw( $site['url'] ) : '';
	}

	/**
	 * Check whether a user has an allowed role.
	 *
	 * This method is intentionally separate from auto_sync().
	 * Incoming requests should not depend on whether outbound sync is enabled.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if the user has an allowed role.
	 */
	public function user_has_allowed_role( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$allowed_roles = $this->get_roles();

		if ( empty( $allowed_roles ) ) {
			return false;
		}

		if ( empty( $user->roles ) ) {
			$user->roles = array( 'none' );
		}

		return ! empty( array_intersect( $user->roles, $allowed_roles ) );
	}

	/**
	 * Decide whether the user should be synced outbound.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $target_site Site.
	 *
	 * @return bool True if allowed, false otherwise.
	 */
	public function allow_sync( int $user_id, string $target_site = '' ): bool {
		if ( ! $this->auto_sync() ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			$this->write_log(
				array(
					'event'       => 'sync',
					'direction'   => 'outgoing',
					'user_email'  => '',
					'status'      => 'error',
					'target_site' => $target_site,
					'source_site' => $this->get_site_url(),
					'message'     => 'User does not exist.',
					'payload'     => array(
						'hook'          => current_filter(),
						'user_email'    => $user->user_email,
						'user_role'     => $user->roles,
						'allowed_roles' => $this->get_roles(),
					),
				)
			);

			return false;
		}

		if ( empty( $user->roles ) ) {
			$user->roles = array( 'none' );
		}

		if ( ! $this->user_has_allowed_role( $user_id ) ) {
			$this->write_log(
				array(
					'event'       => 'sync',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'status'      => 'error',
					'target_site' => $target_site,
					'source_site' => $this->get_site_url(),
					'message'     => 'User does not have the required role for sync.',
					'payload'     => array(
						'hook'          => current_filter(),
						'user_email'    => $user->user_email,
						'user_role'     => $user->roles,
						'allowed_roles' => $this->get_roles(),
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
	 * @param string $username Remote username.
	 * @param string $email    User email.
	 *
	 * @return string User login.
	 */
	public function build_user_login( string $username, string $email ): string {
		$user_login = sanitize_user( $username, true );

		if ( '' === $user_login ) {
			$email_local_part = strstr( $email, '@', true );

			$user_login = sanitize_user( $email_local_part, true );
		}

		if ( '' === $user_login ) {
			$user_login = 'user';
		}

		$base_login = substr( $user_login, 0, 50 );
		$user_login = $base_login;
		$suffix     = 1;

		while ( username_exists( $user_login ) ) {
			$suffix_text = '_' . $suffix;
			$max_length  = 60 - strlen( $suffix_text );

			$user_login = substr( $base_login, 0, $max_length ) . $suffix_text;

			++$suffix;
		}

		return $user_login;
	}

	/**
	 * Create or update a local user from remote payload.
	 *
	 * @param array<string,mixed> $data Remote user payload.
	 *
	 * @return WP_User|WP_Error User instance or error.
	 */
	public function create_user( array $data ) {
		$email = sanitize_email( $data['user_email'] ?? '' );
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error(
				'entireus_bad_email',
				'Remote user has an invalid email address.'
			);
		}

		$user_data = array(
			'user_email'   => $email,
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'display_name' => sanitize_text_field( $data['display_name'] ?? '' ),
			'user_url'     => esc_url_raw( $data['user_url'] ?? '' ),
			'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
		);

		/*
		 * Passwords must not be sanitized or modified.
		 */
		if ( ! empty( $data['user_pass'] ) ) {
			$user_data['user_pass'] = $data['user_pass'];
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing instanceof WP_User ) {
			$user_data['ID'] = $existing->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$username = sanitize_text_field( $data['user_login'] ?? '' );

			$user_data['user_login'] = $this->build_user_login( $username, $email );
			$user_id                 = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'entireus_user_not_found',
				'Unable to retrieve the created user.'
			);
		}

		if ( ! empty( $data['roles'] ) && is_array( $data['roles'] ) ) {
			$this->apply_roles( $user_id, $data['roles'] );
		}

		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$this->apply_meta( $user_id, $data['meta'] );
		}

		if ( ! empty( $data['site_url'] ) ) {
			update_user_meta(
				$user_id,
				'_entireus_imported_from',
				esc_url_raw( $data['site_url'] )
			);
		}

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Apply roles to a user.
	 *
	 * Only configured roles are allowed.
	 *
	 * @param int              $user_id User ID.
	 * @param array<int,mixed> $roles   Remote roles.
	 *
	 * @return void
	 */
	public function apply_roles( int $user_id, array $roles ): void {
		$wp_user = new WP_User( $user_id );

		$allowed_roles = $this->get_roles();

		if ( empty( $allowed_roles ) ) {
			return;
		}

		$remote_roles = array_map( 'sanitize_key', $roles );

		$valid_roles = array_intersect(
			$remote_roles,
			$allowed_roles
		);

		$role = reset( $valid_roles );

		if ( 'none' === $role ) {
			$wp_user->set_role( '' );

			return;
		}

		if ( false !== $role && get_role( $role ) ) {
			$wp_user->set_role( $role );
		}
	}

	/**
	 * Apply user meta from remote payload.
	 *
	 * Only explicitly configured meta keys are synchronized.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $meta    Remote meta.
	 *
	 * @return void
	 */
	public function apply_meta( int $user_id, array $meta ): void {
		$allowed_meta_keys = $this->get_meta_keys();

		if ( empty( $allowed_meta_keys ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed_meta_keys, true ) ) {
				continue;
			}

			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * Send a signed HTTP request to an endpoint.
	 *
	 * The timestamp and source site are included in the signed data so that
	 * the receiver can validate both the request body and authentication context.
	 *
	 * @param string $endpoint REST endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function send_request( string $endpoint, array $data, string $method = 'POST' ) {
		$body = wp_json_encode( $data );

		if ( false === $body ) {
			return new WP_Error(
				'entireus_json_encode_failed',
				'Unable to encode request data as JSON.'
			);
		}

		$timestamp   = time();
		$source_site = $this->get_site_url();

		$signature_data = $timestamp . "\n" . $source_site . "\n" . $body;

		$signature = hash_hmac(
			'sha256',
			$signature_data,
			$this->get_secret()
		);

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-EntireUS-Signature' => $signature,
					'X-EntireUS-Timestamp' => (string) $timestamp,
					'X-EntireUS-Site'      => $this->get_site_url(),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$decoded = array();

		if ( '' !== $response_body ) {
			$decoded = json_decode( $response_body, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				return new WP_Error(
					'entireus_invalid_json',
					'Invalid JSON response from remote site.',
					array(
						'status'   => $status_code,
						'endpoint' => $endpoint,
					)
				);
			}
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = ! empty( $decoded['message'] ) && is_string( $decoded['message'] )
				? sanitize_text_field( $decoded['message'] )
				: sprintf(
					'Remote request failed with status code %d.',
					$status_code
				);

			return new WP_Error(
				'entireus_remote_error',
				$message,
				array(
					'status'   => $status_code,
					'data'     => $decoded,
					'endpoint' => $endpoint,
				)
			);
		}

		$decoded['code'] = $status_code;

		return $decoded;
	}

	/**
	 * Triggered when a user is registered or updated.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function maybe_auto_sync_user( int $user_id ): void {
		if ( self::$is_syncing ) {
			return;
		}

		$this->sync_user( $user_id );
	}

	/**
	 * Triggered when a user role is changed; conditionally send the user.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $role      New role.
	 * @param array  $old_roles Old roles.
	 */
	public function maybe_auto_sync_user_role( int $user_id, $role = null, $old_roles = null ): void {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		if ( 'users.php' !== $pagenow ) {
			return;
		}

		$this->maybe_auto_sync_user( $user_id );
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
			if ( ! $this->allow_sync( $user_id, $site['url'] ) ) {
				continue;
			}

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
						'payload'     => array(
							'payload'  => $request_payload,
							'response' => $response,
						),
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
					'status'      => $response['status'] ?? 'success',
					'message'     => $response['message'] ?? '',
					'payload'     => array(
						'payload'  => $request_payload,
						'response' => $response,
					),
				)
			);
		}

		return $results;
	}

	/**
	 * Build the outgoing payload for a user.
	 *
	 * Passwords are intentionally not included here. Password changes are
	 * synchronized through the wp_set_password hook.
	 *
	 * @param WP_User             $user User object.
	 * @param array<string,mixed> $site Site config.
	 *
	 * @return array<string,mixed> Payload.
	 */
	private function build_payload( WP_User $user, array $site ): array {
		$payload = array(
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'user_url'     => $user->user_url,
			'description'  => $user->description,
			'roles'        => empty( $user->roles )
				? array( 'none' )
				: $user->roles,
			'source_site'  => $this->get_site_url(),
			'target_site'  => isset( $site['url'] )
				? esc_url_raw( $site['url'] )
				: '',
			'hook'         => current_filter(),
		);

		$meta_keys = $this->get_meta_keys();

		if ( ! empty( $meta_keys ) ) {
			$payload['meta'] = array();

			foreach ( $meta_keys as $key ) {
				$payload['meta'][ $key ] = get_user_meta(
					$user->ID,
					$key,
					true
				);
			}
		}

		return $payload;
	}

	/**
	 * Conditionally delete a user on remote sites.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function maybe_auto_delete_user( int $user_id ): void {
		if ( self::$is_syncing ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$this->delete_user( $user );
	}

	/**
	 * Request remote sites to delete a user.
	 *
	 * @param WP_User $user User object.
	 *
	 * @return array<string,array|WP_Error> Results per site.
	 */
	public function delete_user( WP_User $user ): array {
		$results = array();

		$email = sanitize_email( $user->user_email );
		foreach ( $this->get_active_sites() as $site ) {
			if ( ! $this->allow_sync( $user->ID, $site['url'] ) ) {
				continue;
			}

			$payload  = array(
				'email'       => $email,
				'roles'       => empty( $user->roles ) ? array( 'none' ) : $user->roles,
				'target_site' => $site['url'],
				'source_site' => $this->get_site_url(),
			);
			$response = $this->send_request(
				$this->endpoint( $site, 'delete-user' ),
				$payload,
				'DELETE'
			);

			$results[ $this->site_key( $site ) ] = $response;

			if ( is_wp_error( $response ) ) {
				$this->write_log(
					array(
						'event'       => 'delete',
						'direction'   => 'outgoing',
						'user_email'  => $email,
						'roles'       => empty( $user->roles ) ? array( 'none' ) : $user->roles,
						'source_site' => $this->get_site_url(),
						'target_site' => $site['url'],
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => array(
							'payload'  => $payload,
							'response' => $response,
						),
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
					'payload'     => array(
						'payload'  => $payload,
						'response' => $response,
					),
				)
			);
		}

		return $results;
	}

	/**
	 * Attempt to authenticate by checking remote sites.
	 *
	 * @param WP_User|WP_Error|null $user     Authenticated user, error or null.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 *
	 * @return WP_User|WP_Error|null
	 */
	public function maybe_import_remote_user( $user, string $username, string $password ) {
		if ( $user instanceof WP_User || '' === $username || '' === $password ) {
			return $user;
		}

		foreach ( $this->get_active_sites() as $site ) {
			$payload  = array(
				'username'    => $username,
				'user_pass'   => $password,
				'roles'       => empty( $user->roles ) ? array( 'none' ) : $user->roles,
				'source_site' => $this->get_site_url(),
				'target_site' => $site['url'],
				'hook'        => current_filter(),
			);
			$response = $this->send_request(
				$this->endpoint( $site, 'get-user' ),
				$payload
			);

			if ( is_wp_error( $response ) ) {
				$this->write_log(
					array(
						'event'       => 'login',
						'direction'   => 'outgoing',
						'user_email'  => $username,
						'target_site' => $site['url'],
						'source_site' => $this->get_site_url(),
						'status'      => 'error',
						'message'     => $response->get_error_message(),
						'payload'     => array(
							'payload'  => $payload,
							'response' => $response,
						),
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
						'payload'     => array(
							'payload'  => $payload,
							'response' => $response,
						),
					)
				);

				continue;
			}

			/*
			 * The remote endpoint has already authenticated the password.
			 * Use the supplied plaintext password only for creating/updating
			 * the local WordPress password.
			 */
			$remote_user['user_pass'] = $password;
			self::$is_syncing         = true;

			try {
				$local_user = $this->create_user( $remote_user );
			} finally {
				self::$is_syncing = false;
			}

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
						'payload'     => array(
							'payload'  => $payload,
							'response' => $response,
						),
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
					'message'     => 'User imported and logged in from remote site.',
					'payload'     => array(
						'payload'  => $payload,
						'response' => $response,
					),
				)
			);

			return $local_user;
		}

		return $user;
	}

	/**
	 * Handle password reset event.
	 *
	 * @param WP_User $user     User object.
	 * @param string  $new_pass New plaintext password.
	 *
	 * @return void
	 */
	public function on_password_reset( WP_User $user, string $new_pass ): void {
		if ( self::$is_syncing ) {
			return;
		}

		$this->sync_password( $user, $new_pass );
	}

	/**
	 * Handle low-level password change.
	 *
	 * @param string $password Plaintext password.
	 * @param int    $user_id  User ID.
	 *
	 * @return void
	 */
	public function on_set_password( string $password, int $user_id ): void {
		if ( self::$is_syncing || '' === $password || ! $user_id ) {
			return;
		}

		$this->sync_password( $user_id, $password );
	}

	/**
	 * Sync a user's password to remote sites.
	 *
	 * The password is transmitted only to the configured remote endpoint
	 * over the signed HTTPS request and is never written to logs.
	 *
	 * @param int|WP_User $user     User ID or user object.
	 * @param string      $password Plaintext password.
	 *
	 * @return array<string,array|WP_Error> Results per site.
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
			if ( ! $this->allow_sync( $user->ID, $site['url'] ) ) {
				continue;
			}

			$payload = array(
				'user_email'  => $user->user_email,
				'user_pass'   => $password,
				'roles'       => empty( $user->roles ) ? array( 'none' ) : $user->roles,
				'target_site' => $site['url'],
				'source_site' => $this->get_site_url(),
				'hook'        => current_filter(),
			);

			$response = $this->send_request(
				$this->endpoint( $site, 'sync-password' ),
				$payload
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
						'payload'     => array(
							'payload'  => $payload,
							'response' => $response,
						),
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
					'payload'     => array(
						'payload'  => $payload,
						'response' => $response,
					),
				)
			);
		}

		return $results;
	}

	/**
	 * Remove sensitive values before logging.
	 *
	 * @param mixed $data Data to redact.
	 *
	 * @return mixed Redacted data.
	 */
	private function redact_sensitive_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$sensitive_keys = array(
			'user_pass',
			'password',
			'pass1',
			'pass2',
			'pwd',
			'secret',
			'secret_key',
			'token',
			'access_token',
			'refresh_token',
		);

		foreach ( $data as $key => &$value ) {
			if ( in_array( strtolower( (string) $key ), $sensitive_keys, true ) ) {
				$value = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$value = $this->redact_sensitive_data( $value );
			}
		}

		unset( $value );

		return $data;
	}

	/**
	 * Return a safe representation of a response for logging.
	 *
	 * @param array|WP_Error $response Response.
	 *
	 * @return array<string,mixed>
	 */
	private function format_response_for_log( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'error_code' => $response->get_error_code(),
				'message'    => $response->get_error_message(),
				'status'     => $response->get_error_data()['status'] ?? null,
			);
		}

		if ( ! is_array( $response ) ) {
			return array();
		}

		return array(
			'code'    => $response['code'] ?? null,
			'status'  => $response['status'] ?? null,
			'message' => isset( $response['message'] )
				? sanitize_text_field( $response['message'] )
				: '',
		);
	}

	/**
	 * Write a log if logging is enabled.
	 *
	 * @param array<string,mixed> $args Log arguments.
	 *
	 * @return void
	 */
	public function write_log( array $args ): void {
		if ( ! $this->settings()->get( 'configuration', 'enable_log' ) ) {
			return;
		}

		$logger = new Logger();

		$logger->log(
			$this->redact_sensitive_data( $args )
		);
	}
}
