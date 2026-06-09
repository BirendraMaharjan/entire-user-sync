<?php

namespace EntireUserSync\Sync;

use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Common\Traits\Requester;

class Sync extends Base {

	use SyncHelper;
	use Requester;

	public Sender $sender;
	public Api $api;

	public function __construct() {
		parent::__construct();

		// var_dump($this->enable_logging());

		$this->sender = new Sender();
		$this->api    = new Api();

		$this->init();
	}

	public function init(): void {

		add_action( 'user_register', array( $this, 'maybe_auto_sync_user' ) );
		add_action( 'profile_update', array( $this, 'maybe_auto_sync_user' ) );

		add_action( 'set_user_role', array( $this, 'maybe_auto_sync_user_role' ) );

		add_action( 'delete_user', array( $this, 'maybe_auto_delete_user' ) );
		add_action( 'remove_user_from_blog', array( $this, 'maybe_auto_delete_user' ) );

		add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );

		add_filter( 'authenticate', array( $this, 'maybe_import_remote_user' ), 20, 3 );

		add_action( 'wp_login', array( $this, 'on_local_login' ), 10, 2 );
	}

	public function allow_sync( int $user_id ): bool {

		if ( defined( 'ENTIREUS_INCOMING_SYNC' ) || ! $this->auto_sync() ) {
			return false;
		}

		$user         = get_userdata( $user_id );
		$current_hook = current_filter();

		if ( ! $user ) {
			$this->write_log(
				array(
					'event'      => 'allow_sync',
					'direction'  => 'outgoing',
					'user_email' => $user_id,
					'status'     => 'error',
					'message'    => 'User does not exist. ' . $current_hook,
				)
			);

			return false;
		}

		if ( empty( $this->get_roles() ) || ! array_intersect( $user->roles, $this->get_roles() ) ) {
			$this->write_log(
				array(
					'event'      => 'allow_sync',
					'direction'  => 'outgoing',
					'user_email' => $user->user_email,
					'status'     => 'error',
					'message'    => 'User does not have the required role. ' . $current_hook,
				)
			);

			return false;
		}

		return true;
	}
	public function maybe_auto_sync_user( int $user_id ): void {

		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}

		$this->sender->sync_user( $user_id );
	}

	public function maybe_auto_sync_user_role( int $user_id ): void {
		$this->maybe_auto_sync_user( $user_id );
	}

	public function maybe_auto_delete_user( int $user_id ): void {
		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$this->sender->delete_user( $user->user_email, $this->get_sites() );
	}


	public function on_password_reset( \WP_User $user, string $new_pass ): void {
		$this->push_password_hash( $user );
	}

	public function on_set_password( string $password, int $user_id ): void {
		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->push_password_hash( $user );
		}
	}

	public function on_local_login( string $user_login, \WP_User $user ): void {
		$this->push_password_hash( $user );
	}

	private function push_password_hash( \WP_User $user ): void {
		$sites = $this->get_sites();
		if ( empty( $sites ) ) {
			return;
		}

		$this->sender->sync_password( $user->user_email, $user->user_pass, $sites );
	}


	public function maybe_import_remote_user( $user, string $username, string $password ) {
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		if ( empty( $username ) ) {
			return $user;
		}

		$sites     = $this->get_sites();
		$meta_keys = $this->get_meta_keys();

		if ( empty( $sites ) ) {
			return $user;
		}

		foreach ( $sites as $site ) {
			$remote_user = $this->fetch_remote_user( $username, $password, $site );

			if ( is_wp_error( $remote_user ) || empty( $remote_user ) ) {
				continue;
			}

			$local_user = $this->create_local_user( $remote_user, $meta_keys );

			if ( is_wp_error( $local_user ) ) {
				$this->write_log(
					array(
						'event'       => 'login',
						'direction'   => 'incoming',
						'user_email'  => $remote_user['user_email'] ?? $username,
						'source_site' => $site['url'],
						'status'      => 'error',
						'message'     => $local_user->get_error_message(),
						'payload'     => array( 'username' => $username ),
					)
				);

				return $local_user;
			}

			$this->write_log(
				array(
					'event'       => 'login',
					'direction'   => 'incoming',
					'user_email'  => $local_user->user_email,
					'source_site' => $site['url'],
					'status'      => 'success',
					'message'     => 'User imported and logged in from remote site',
					'payload'     => array(
						'username' => $username,
						'roles'    => $remote_user['roles'] ?? array(),
					),
				)
			);

			return $local_user;
		}

		return $user;
	}


	private function fetch_remote_user( string $username, string $password, array $site ): array|\WP_Error {
		$endpoint  = trailingslashit( $site['url'] ) . 'wp-json/entireus/v1/get-user';
		$body      = wp_json_encode(
			array(
				'username' => $username,
				'password' => $password,
			)
		);
		$signature = hash_hmac( 'sha256', $body, $this->get_secret() );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-EntireUS-Signature' => $signature,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new \WP_Error( 'entireus_remote_' . $code, 'Remote lookup failed' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return $data['user'] ?? new \WP_Error( 'entireus_no_user', 'No user in response' );
	}


	private function create_local_user( array $remote, array $meta_keys ): \WP_User|\WP_Error {
		$email = sanitize_email( $remote['user_email'] ?? '' );
		if ( ! $email ) {
			return new \WP_Error( 'entireus_bad_email', 'Remote user has no email' );
		}

		$existing   = get_user_by( 'email', $email );
		$user_login = sanitize_user( $remote['user_login'] ?? '' ) ?: sanitize_user( strstr( $email, '@', true ) );

		if ( ! $existing && username_exists( $user_login ) ) {
			$user_login .= '_' . substr( md5( $email ), 0, 5 );
		}

		$user_data = array(
			'user_login'   => $user_login,
			'user_email'   => $email,
			'first_name'   => sanitize_text_field( $remote['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $remote['last_name'] ?? '' ),
			'display_name' => sanitize_text_field( $remote['display_name'] ?? '' ),
			'user_url'     => esc_url_raw( $remote['user_url'] ?? '' ),
			'description'  => sanitize_textarea_field( $remote['description'] ?? '' ),
		);

		if ( ! empty( $remote['password_hash'] ) ) {

			$user_data['user_pass'] = $remote['password_hash'];
		} else {
			$user_data['user_pass'] = wp_generate_password( 24 );
		}

		if ( $existing ) {
			$user_data['ID'] = $existing->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$user_id = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( ! empty( $remote['password_hash'] ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->users,
				array( 'user_pass' => $remote['password_hash'] ),
				array( 'ID' => $user_id )
			);
			wp_cache_delete( $user_id, 'users' );
		}

		$wp_user = new \WP_User( $user_id );

		if ( ! empty( $remote['roles'] ) && is_array( $remote['roles'] ) ) {
			$allowed = $this->get_roles();
			$wp_user->set_role( '' );
			foreach ( $remote['roles'] as $role ) {
				$role = sanitize_key( $role );
				if ( get_role( $role ) && ( empty( $allowed ) || in_array( $role, $allowed, true ) ) ) {
					$wp_user->add_role( $role );
				}
			}
		}

		if ( ! empty( $remote['meta'] ) && is_array( $remote['meta'] ) ) {
			foreach ( $remote['meta'] as $key => $value ) {
				$key = sanitize_key( $key );
				if ( empty( $meta_keys ) || in_array( $key, $meta_keys, true ) ) {
					update_user_meta( $user_id, $key, $value );
				}
			}
		}

		update_user_meta( $user_id, '_entireus_imported_from', sanitize_text_field( $remote['site_url'] ?? '' ) );

		return $wp_user;
	}
}
