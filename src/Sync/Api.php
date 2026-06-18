<?php

namespace EntireUserSync\Sync;

class Api {

	use SyncHelper;

	private string $secret;

	public function __construct() {
		$this->secret = $this->get_secret();

		$this->init();
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

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



	public function verify_signature( \WP_REST_Request $request ): bool {

		// Mark this as an incoming EntireUS sync request
		if ( ! defined( 'ENTIREUS_INCOMING_SYNC' ) ) {
			define( 'ENTIREUS_INCOMING_SYNC', true );
		}

		$secret    = $this->secret;
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

		$sync      = new Sync();
		$meta_keys = $sync->get_meta_keys();
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



	public function handle_sync_user( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data['user_email'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Missing user_email.' ), 400 );
		}

		$email      = sanitize_email( $data['user_email'] );
		$existing   = get_user_by( 'email', $email );
		$user_login = sanitize_user( $data['user_login'] ?? '' ) ?: sanitize_user( strstr( $email, '@', true ) );

		$user_data = array(
			'user_email'   => $email,
			'user_login'   => $user_login,
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'display_name' => sanitize_text_field( $data['display_name'] ?? '' ),
			'user_url'     => esc_url_raw( $data['user_url'] ?? '' ),
			'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
			'user_pass'    => ! empty( $data['password_hash'] )
				? $data['password_hash']
				: wp_generate_password( 20 ),
		);

		if ( $existing ) {
			$user_data['ID'] = $existing->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$user_id = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response( array( 'message' => $user_id->get_error_message() ), 500 );
		}

		if ( ! empty( $data['password_hash'] ) ) {
			$this->write_password_hash( $user_id, $data['password_hash'] );
		}

		$wp_user = new \WP_User( $user_id );

		if ( ! empty( $data['roles'] ) && is_array( $data['roles'] ) ) {
			$wp_user->set_role( '' );
			foreach ( $data['roles'] as $role ) {
				$role = sanitize_key( $role );
				if ( get_role( $role ) ) {
					$wp_user->add_role( $role );
				}
			}
		}

		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				update_user_meta( $user_id, sanitize_key( $key ), $value );
			}
		}

		$event = $existing ? 'update' : 'create';

		$this->write_log(
			array(
				'event'       => $event,
				'direction'   => 'incoming',
				'user_email'  => $email,
				'source_site' => $data['site_url'] ?? '',
				'status'      => 'success',
				'message'     => $existing ? 'User updated from remote.' : 'User created from remote.',
				'payload'     => $data,
			)
		);

		return new \WP_REST_Response(
			array(
				'message' => $existing ? 'User updated.' : 'User created.',
				'user_id' => $user_id,
			),
			200
		);
	}



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
