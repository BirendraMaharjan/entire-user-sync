<?php

namespace EntireUserSync\Sync;

use WP_User;

class Sender {

	use SyncHelper;
	private string $secret;

	public function __construct() {
		$this->secret = $this->get_secret();
	}

	public function sync_user( int $user_id ): array {
		$user = get_userdata( $user_id );

		$payload = $this->build_payload( $user );

		$results = array();

		foreach ( $this->get_active_sites() as $site ) {

			$response = $this->send_request( $this->endpoint( $site, 'sync-user' ), $payload );
			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'update',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'target_site' => $site['url'],
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => $payload,
				)
			);
		}

		return $results;
	}

	public function sync_all_users( array $sites, array $roles, array $meta_keys ): array {
		$args = array(
			'number' => - 1,
			'fields' => 'ID',
		);
		if ( ! empty( $roles ) ) {
			$args['role__in'] = $roles;
		}

		$results = array();

		foreach ( get_users( $args ) as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$payload = $this->build_payload( $user, $roles, $meta_keys );

			foreach ( $sites as $site ) {
				$response                              = $this->send_request(
					$this->endpoint( $site, 'sync-user' ),
					$payload
				);
				$results[ $this->site_key( $site ) ][] = array(
					'user'   => $user->user_login,
					'status' => $response['status'],
					'msg'    => $response['message'] ?? '',
				);
			}
		}

		return $results;
	}

	public function delete_user( string $email, array $sites ): array {
		$results = array();

		foreach ( $this->get_active_sites() as $site ) {

			$response = $this->send_request(
				$this->endpoint( $site, 'delete-user' ),
				array( 'email' => $email ),
				'DELETE'
			);
			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'delete',
					'direction'   => 'outgoing',
					'user_email'  => $email,
					'target_site' => $site['url'],
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => array( 'email' => $email ),
				)
			);
		}

		return $results;
	}

	public function sync_password( string $email, string $password_hash, array $sites ): array {
		$results = array();

		foreach ( $sites as $site ) {
			$response                            = $this->send_request(
				$this->endpoint( $site, 'sync-password' ),
				array(
					'user_email'    => $email,
					'password_hash' => $password_hash,
				)
			);
			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'password',
					'direction'   => 'outgoing',
					'user_email'  => $email,
					'target_site' => $site['url'],
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => array(
						'user_email'    => $email,
						'password_hash' => '[redacted]',
					),
				)
			);
		}

		return $results;
	}

	private function build_payload( WP_User $user ): array {
		$payload = array(
			'user_login'    => $user->user_login,
			'user_email'    => $user->user_email,
			'first_name'    => $user->first_name,
			'last_name'     => $user->last_name,
			'display_name'  => $user->display_name,
			'user_url'      => $user->user_url,
			'description'   => $user->description,
			'roles'         => $user->roles,
			'password_hash' => $user->user_pass,
		);

		$meta_keys = $this->get_meta_keys();

		if ( ! empty( $this->get_roles() ) ) {
			$payload['roles'] = array_values( array_intersect( $user->roles, $this->get_roles() ) );
		}

		$payload['meta'] = array();
		foreach ( $meta_keys as $key ) {
			$payload['meta'][ $key ] = get_user_meta( $user->ID, $key, true );
		}

		return $payload;
	}

	public function send_request( string $endpoint, array $data, string $method = 'POST' ): array {
		$body      = wp_json_encode( $data );
		$signature = hash_hmac( 'sha256', $body, $this->secret );

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-EntireUS-Signature' => $signature,
					'X-EntireUS-Site'      => get_site_url(),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'message' => $response->get_error_message(),
			);
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		return array(
			'status'  => ( $code >= 200 && $code < 300 ) ? 'success' : 'error',
			'code'    => $code,
			'message' => $decoded_body['message'] ?? '',
		);
	}

	private function endpoint( array $site, string $route ): string {
		return trailingslashit( $site['url'] ?? '' ) . 'wp-json/entireus/v1/' . $route;
	}

	private function site_key( array $site ): string {
		if ( ! empty( $site['label'] ) ) {
			return (string) $site['label'];
		}

		return (string) ( $site['url'] ?? '' );
	}
}
