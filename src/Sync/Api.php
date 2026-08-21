<?php
/**
 * REST API endpoints for Entire User Sync.
 *
 * Exposes endpoints for getting user info, syncing users, syncing passwords and deleting users.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Sync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Api
 */
class Api extends Sync {

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
			$this->get_route_namespace(),
			'/get-user',
			array_merge(
				$args,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'handle_get_user' ),
				)
			)
		);

		register_rest_route(
			$this->get_route_namespace(),
			'/sync-user',
			array_merge(
				$args,
				array(
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => array( $this, 'handle_sync_user' ),
				)
			)
		);

		register_rest_route(
			$this->get_route_namespace(),
			'/sync-password',
			array_merge(
				$args,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'handle_sync_password' ),
				)
			)
		);

		register_rest_route(
			$this->get_route_namespace(),
			'/delete-user',
			array_merge(
				$args,
				array(
					'methods'  => WP_REST_Server::DELETABLE,
					'callback' => array( $this, 'handle_delete_user' ),
				)
			)
		);
	}

	/**
	 * Verify incoming request signature and mark request as incoming sync.
	 *
	 * @param WP_REST_Request $request REST request instance.
	 *
	 * @return true|WP_Error True if the signature is valid, otherwise a WP_Error.
	 */
	public function verify_signature( WP_REST_Request $request ) {

		$secret      = $this->get_secret();
		$signature   = $request->get_header( 'X-EntireUS-Signature' );
		$source_site = $request->get_header( 'X-EntireUS-Site' );
		$timestamp   = absint( $request->get_header( 'X-EntireUS-Timestamp' ) );
		$body        = $request->get_body();

		// 1. Allowlist.
		if ( ! $this->is_allowed_site( $source_site ) ) {
			return $this->fail_auth( $request, 'Unauthorized site: ' . $source_site );
		}

		// 2. Missing headers.
		if ( ! $secret || ! $signature ) {
			return $this->fail_auth( $request, 'Missing auth headers' );
		}

		// 3. Replay protection (5-min window).
		if ( ! $timestamp || abs( time() - $timestamp ) > 300 ) {
			return $this->fail_auth( $request, 'Request expired' );
		}

		// 4. Signature check.
		$valid = hash_equals(
			hash_hmac( 'sha256', $body, $secret ),
			$signature
		);

		if ( ! $valid ) {
			return $this->fail_auth( $request, 'Invalid signature' );
		}

		return true;
	}

	/**
	 * Log a failed authentication attempt.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $message Failure message.
	 *
	 * @return WP_Error
	 */
	private function fail_auth( WP_REST_Request $request, string $message ): WP_Error {

		$source_site = $request->get_header( 'X-EntireUS-Site' );

		$this->write_log(
			array(
				'event'       => 'auth',
				'direction'   => 'incoming',
				'status'      => 'error',
				'message'     => $message,
				'source_site' => esc_url_raw( $source_site ),
				'target_site' => esc_url_raw( $this->get_site_url() ),
				'payload'     => wp_unslash( $request->get_json_params() ),
			)
		);

		return new WP_Error(
			'entireus_forbidden',
			$message,
			array( 'status' => 403 )
		);
	}

	/**
	 * Handle get-user endpoint.
	 *
	 * Authenticate then return limited user info.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function handle_get_user( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$username = sanitize_text_field( $params['username'] ?? '' );
		$password = $params['user_pass'] ?? '';

		if ( ! $username || ! $password ) {
			return new WP_REST_Response( array( 'message' => 'Missing credentials.' ), 400 );
		}

		$user = get_user_by( 'login', $username );

		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		if ( ! $this->allow_sync( $user->ID ) ) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync.',
					'user_role' => $user->roles,
				),
				401
			);
		}

		if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Invalid credentials.',
				),
				401
			);
		}

		if ( ! $this->allow_sync( $user->ID ) ) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync.',
					'user_role' => $user->roles,
				),
				401
			);
		}

		$meta_keys = $this->get_meta_keys();
		$meta      = array();

		foreach ( $meta_keys as $key ) {
			$meta[ $key ] = get_user_meta( $user->ID, $key, true );
		}

		return new WP_REST_Response(
			array(
				'user' => array(
					'user_login'   => $user->user_login,
					'user_email'   => $user->user_email,
					'first_name'   => $user->first_name,
					'last_name'    => $user->last_name,
					'display_name' => $user->display_name,
					'user_url'     => $user->user_url,
					'description'  => $user->description,
					'roles'        => $user->roles,
					'meta'         => $meta,
					'site_url'     => get_site_url(),
				),
			),
			200
		);
	}

	/**
	 * Handle syncing (create/update) a user from remote.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_sync_user( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data['user_email'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Missing user_email.' ), 400 );
		}

		$email      = sanitize_email( $data['user_email'] );
		$existing   = get_user_by( 'email', $email );

		$roles = $data['roles'] ?? array();
		if ( $existing && ! $this->allow_sync( $existing->ID ) ) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync2.',
					'user_role' => $existing->roles,
					'status'    => 'error',
				),
			);

		} else if (
			! $existing &&
			(
				empty( $roles ) ||
				! array_intersect( $roles, $this->get_roles() )
			)
		) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync1.',
					'user_role' => $roles,
					'status'    => 'error',
				),
			);
		}


		self::$is_syncing = true;
		try {
			$local_user = $this->create_user( $data );
		} finally {
			self::$is_syncing = false;
		}

		$event = $existing ? 'update' : 'create';
		if ( is_wp_error( $local_user ) ) {
			$this->write_log(
				array(
					'event'       => $event,
					'direction'   => 'incoming',
					'user_email'  => $email,
					'source_site' => $data['source_site'] ?? '',
					'target_site' => $data['target_site'] ?? '',
					'message'     => $local_user->get_error_message(),
					'payload'     => $data,
					'status'      => 'error',
				)
			);

			return new WP_REST_Response( array( 'message' => $local_user->get_error_message() ), 500 );
		}

		$this->write_log(
			array(
				'event'       => $event,
				'direction'   => 'incoming',
				'user_email'  => $email,
				'source_site' => $data['source_site'] ?? '',
				'target_site' => $data['target_site'] ?? '',
				'message'     => $existing ? 'User updated from remote.' : 'User created from remote.',
				'payload'     => $data,
				'status'      => 'success',
			)
		);

		return new WP_REST_Response(
			array(
				'message' => $existing ? 'User updated.' : 'User created.',
				'code'    => $existing ? 'user_updated' : 'user_created',
				'user'    => $local_user,
				'status'  => 'success',
			),
			200
		);
	}

	/**
	 * Handle syncing a password hash from remote.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_sync_password( WP_REST_Request $request ): WP_REST_Response {
		$data     = $request->get_json_params();
		$email    = sanitize_email( $data['user_email'] ?? '' );
		$password = sanitize_text_field( $data['user_pass'] ?? '' );

		if ( ! $email || ! $password ) {
			return new WP_REST_Response( array( 'message' => 'Missing email or password.' ), 400 );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'message' => 'User not found.' ), 404 );
		}

		if ( ! $this->allow_sync( $user->ID ) ) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync.',
					'user_role' => $user->roles,
					'status'    => 'error',
				),
				401
			);
		}

		self::$is_syncing = true;
		try {
			wp_set_password( $password, $user->ID );
		} finally {
			self::$is_syncing = false;
		}

		$this->write_log(
			array(
				'event'       => 'password',
				'direction'   => 'incoming',
				'user_email'  => $email,
				'source_site' => $data['source_site'] ?? '',
				'target_site' => $data['target_site'] ?? '',
				'status'      => 'success',
				'message'     => 'Password synced from remote.',
				'payload'     => $data,
			)
		);

		return new WP_REST_Response(
			array(
				'message' => 'Password synced.',
				'code'    => 'password_synced',
				'user'    => $user,
				'status'  => 'success',
			),
			200
		);
	}

	/**
	 * Handle deleting a local user from a remote request.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_delete_user( WP_REST_Request $request ): WP_REST_Response {
		$data  = $request->get_json_params();
		$email = sanitize_email( $data['email'] ?? '' );

		if ( ! $email ) {
			return new WP_REST_Response( array( 'message' => 'Missing email.' ), 400 );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'message' => 'User not found.' ), 404 );
		}

		if ( ! $this->allow_sync( $user->ID ) ) {
			return new WP_REST_Response(
				array(
					'message'   => 'User does not have the required role for sync.',
					'user_role' => $user->roles,
					'status'    => 'error',
				),
				401
			);
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		self::$is_syncing = true;

		try {
			$deleted = wp_delete_user( $user->ID );
		} finally {
			self::$is_syncing = false;
		}

		$this->write_log(
			array(
				'event'       => 'delete',
				'direction'   => 'incoming',
				'user_email'  => $email,
				'source_site' => $data['source_site'] ?? '',
				'target_site' => $data['target_site'] ?? '',
				'status'      => $deleted ? 'success' : 'error',
				'message'     => $deleted ? 'User deleted from remote request.' : 'Delete failed.',
				'payload'     => $data,
			)
		);

		return new WP_REST_Response(
			array(
				'message'    => $deleted ? 'User deleted.' : 'Delete failed.',
				'code'       => $deleted ? 'user_deleted' : 'delete_failed',
				'user_email' => $email,
				'status'     => $deleted ? 'success' : 'error',
			),
			$deleted ? 200 : 500
		);
	}
}
