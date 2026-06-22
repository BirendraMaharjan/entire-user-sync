<?php
/**
 * REST API endpoints for Entire User Sync.
 *
 * Exposes endpoints for getting user info, syncing users, syncing passwords and deleting users.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Sync;

/**
 * Class Api
 */
class Api {

	use SyncHelper;

	/**
	 * Api constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Register REST hooks on rest_api_init.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		$args = array(
			'permission_callback' => array( $this, 'verify_signature' ),
		);

		register_rest_route(
			'entireus/v1',
			'/get-user',
			array_merge(
				$args,
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'handle_get_user' ),
				)
			)
		);

		register_rest_route(
			'entireus/v1',
			'/sync-user',
			array_merge(
				$args,
				array(
					'methods'  => array( 'POST', 'PUT' ),
					'callback' => array( $this, 'handle_sync_user' ),
				)
			)
		);

		register_rest_route(
			'entireus/v1',
			'/sync-password',
			array_merge(
				$args,
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'handle_sync_password' ),
				)
			)
		);

		register_rest_route(
			'entireus/v1',
			'/delete-user',
			array_merge(
				$args,
				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'handle_delete_user' ),
				)
			)
		);
	}

	/**
	 * Verify incoming request signature and mark request as incoming sync.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return bool True if signature is valid.
	 */
	public function verify_signature( \WP_REST_Request $request ): bool {

		// Mark this as an incoming EntireUS sync request.
		if ( ! defined( 'ENTIREUS_INCOMING_SYNC' ) ) {
			define( 'ENTIREUS_INCOMING_SYNC', true );
		}

		$secret    = $this->get_secret();
		$signature = $request->get_header( 'X-EntireUS-Signature' );
		$body      = $request->get_body();

		if ( ! $secret || ! $signature ) {
			$this->write_log(
				array(
					'event'     => 'auth',
					'direction' => 'incoming',
					'status'    => 'error',
					'message'   => 'Missing secret or signature header.',
					'payload'   => array(),
				)
			);
			return false;
		}

		$valid = hash_equals( hash_hmac( 'sha256', $body, $secret ), $signature );

		if ( ! $valid ) {
			$this->write_log(
				array(
					'event'     => 'auth',
					'direction' => 'incoming',
					'status'    => 'error',
					'message'   => 'Invalid signature — possible unauthorized request.',
					'payload'   => array(),
				)
			);
		}

		return $valid;
	}

	/**
	 * Handle get-user endpoint. Authenticate then return limited user info.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response Response object.
	 */
	public function handle_get_user( \WP_REST_Request $request ): \WP_REST_Response {
		$params   = $request->get_json_params();
		$username = sanitize_text_field( $params['username'] ?? '' );
		$password = $params['password'] ?? '';

		if ( ! $username || ! $password ) {
			return new \WP_REST_Response( array( 'message' => 'Missing credentials.' ), 400 );
		}

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {

			return new \WP_REST_Response( array( 'message' => 'Invalid credentials.' ), 401 );
		}

		$meta_keys = $this->get_meta_keys();
		$meta      = array();

		foreach ( $meta_keys as $key ) {
			$meta[ $key ] = get_user_meta( $user->ID, $key, true );
		}

		return new \WP_REST_Response(
			array(
				'user' => array(
					'user_login'    => $user->user_login,
					'user_email'    => $user->user_email,
					'first_name'    => $user->first_name,
					'last_name'     => $user->last_name,
					'display_name'  => $user->display_name,
					'user_url'      => $user->user_url,
					'description'   => $user->description,
					'roles'         => $user->roles,
					'meta'          => $meta,
					'site_url'      => get_site_url(),
					'password_hash' => $user->user_pass,
				),
			),
			200
		);
	}

	/**
	 * Handle syncing (create/update) a user from remote.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_sync_user( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data['user_email'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Missing user_email.' ), 400 );
		}

		$email    = sanitize_email( $data['user_email'] );
		$existing = get_user_by( 'email', $email );
		$user_id  = $this->save_synced_user( $data, $email, $existing );

		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response( array( 'message' => $user_id->get_error_message() ), 500 );
		}

		$this->sync_synced_user_roles( $user_id, $data['roles'] ?? array() );
		$this->sync_synced_user_meta( $user_id, $data['meta'] ?? array() );

		if ( ! empty( $data['password_hash'] ) ) {
			$this->write_password_hash( $user_id, $data['password_hash'] );
		}

		$event = $existing ? 'update' : 'create';

		$this->write_log(
			array(
				'event'       => $event,
				'direction'   => 'incoming',
				'user_email'  => $email,
				'source_site' => $data['source_site'] ?? '',
				'target_site' => $data['target_site'] ?? '',
				'status'      => 'success',
				'message'     => $existing ? 'User updated from remote.' : 'User created from remote.',
				'payload'     => $data,
			)
		);

		return new \WP_REST_Response(
			array(
				'message' => $existing ?
					__('User updated.', 'entire_user_sync') : __( 'User created.', 'entire_user_sync' ),
				'user_id' => $user_id,
			),
			200
		);
	}

	/**
	 * Save a synced user by creating or updating the local account.
	 *
	 * @param array    $data     Raw request payload.
	 * @param string   $email    Sanitized email.
	 * @param \WP_User $existing Existing user, when found.
	 * @return int|\WP_Error User ID or error.
	 */
	private function save_synced_user( array $data, string $email, $existing ) {
		$user_data = array(
			'user_email'   => $email,
			'user_login'   => $this->resolve_user_login( $data, $email ),
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'display_name' => sanitize_text_field( $data['display_name'] ?? '' ),
			'user_url'     => esc_url_raw( $data['user_url'] ?? '' ),
			'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
			'user_pass'    => ! empty( $data['password_hash'] ) ? $data['password_hash'] : wp_generate_password( 20 ),
		);

		if ( $existing ) {
			$user_data['ID'] = $existing->ID;
			return wp_update_user( $user_data );
		}

		return wp_insert_user( $user_data );
	}

	/**
	 * Resolve a usable login name from the payload.
	 *
	 * @param array  $data Request payload.
	 * @param string $email Sanitized email.
	 * @return string
	 */
	private function resolve_user_login( array $data, string $email ): string {
		$user_login = sanitize_user( $data['user_login'] ?? '' );
		if ( empty( $user_login ) ) {
			$user_login = sanitize_user( strstr( $email, '@', true ) );
		}

		return $user_login;
	}

	/**
	 * Sync remote roles to the local account.
	 *
	 * @param int   $user_id User ID.
	 * @param array $roles Requested roles.
	 */
	private function sync_synced_user_roles( int $user_id, array $roles ): void {
		if ( empty( $roles ) ) {
			return;
		}

		$wp_user = new \WP_User( $user_id );
		$wp_user->set_role( '' );

		foreach ( $roles as $role ) {
			$role = sanitize_key( $role );
			if ( get_role( $role ) ) {
				$wp_user->add_role( $role );
			}
		}
	}

	/**
	 * Sync remote meta values to the local account.
	 *
	 * @param int   $user_id User ID.
	 * @param array $meta    Meta values.
	 */
	private function sync_synced_user_meta( int $user_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, sanitize_key( $key ), $value );
		}
	}

	/**
	 * Handle syncing a password hash from remote.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_sync_password( \WP_REST_Request $request ): \WP_REST_Response {
		$data  = $request->get_json_params();
		$email = sanitize_email( $data['user_email'] ?? '' );
		$hash  = $data['password_hash'] ?? '';

		if ( ! $email || ! $hash ) {
			return new \WP_REST_Response( array( 'message' => 'Missing email or password_hash.' ), 400 );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {

			return new \WP_REST_Response( array( 'message' => 'User not found.' ), 404 );
		}

		$this->write_password_hash( $user->ID, $hash );

		$this->write_log(
			array(
				'event'      => 'password',
				'direction'  => 'incoming',
				'user_email' => $email,
				'status'     => 'success',
				'message'    => 'Password hash synced from remote.',
				'payload'    => array(
					'user_email'    => $email,
					'password_hash' => '[redacted]',
				),
			)
		);

		return new \WP_REST_Response( array( 'message' => 'Password synced.' ), 200 );
	}

	/**
	 * Handle deleting a local user from a remote request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_delete_user( \WP_REST_Request $request ): \WP_REST_Response {
		$data  = $request->get_json_params();
		$email = sanitize_email( $data['email'] ?? '' );

		if ( ! $email ) {
			return new \WP_REST_Response( array( 'message' => 'Missing email.' ), 400 );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new \WP_REST_Response( array( 'message' => 'User not found.' ), 404 );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( $user->ID );

		$this->write_log(
			array(
				'event'      => 'delete',
				'direction'  => 'incoming',
				'user_email' => $email,
				'source_site' => $data['source_site'] ?? '',
				'target_site' => $data['target_site'] ?? '',
				'status'     => $deleted ? 'success' : 'error',
				'message'    => $deleted ? 'User deleted from remote request.' : 'Delete failed.',
				'payload'    => array( 'email' => $email ),
			)
		);

		return new \WP_REST_Response(
			array(
				'message' => $deleted ? 'User deleted.' : 'Delete failed.',
			),
			$deleted ? 200 : 500
		);
	}

	/**
	 * Low-level DB update for user password.
	 *
	 * @param int    $user_id User ID.
	 * @param string $hash    Stored password hash.
	 */
	private function write_password_hash( int $user_id, string $hash ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->users,
			array(
				'user_pass'           => $hash,
				'user_activation_key' => '',
			),
			array( 'ID' => $user_id )
		);

		wp_cache_delete( $user_id, 'users' );
		clean_user_cache( $user_id );
	}
}
