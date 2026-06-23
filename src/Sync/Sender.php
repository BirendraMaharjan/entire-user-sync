<?php
/**
 * Sender for outgoing sync requests.
 *
 * Sends local user changes to configured remote sites.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Sync;

use WP_User;

/**
 * Class Sender
 */
class Sender {

	use SyncHelper;

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

			$payload  = $this->build_payload( $user, $site );
			$response = $this->send_request(
				$this->endpoint( $site, 'sync-user' ),
				$payload
			);

			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'update',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => $payload,
				)
			);
		}

		return $results;
	}

	/**
	 * Bulk sync all users (used by admin tools).
	 *
	 * @param array $sites Sites to send to.
	 * @param array $roles Roles to include.
	 * @param array $meta_keys Meta keys to include.
	 *
	 * @return array Results per site.
	 */
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

			foreach ( $sites as $site ) {
				$payload  = $this->build_payload( $user, $site );
				$response = $this->send_request(
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
					'source_site' => $this->get_site_url()
				),
				'DELETE'
			);

			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'delete',
					'direction'   => 'outgoing',
					'user_email'  => $email,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url(),
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => array( 'email' => $email ),
				)
			);
		}

		return $results;
	}

	/**
	 * Sync a user's password hash to remote sites.
	 *
	 * @param string $email User email.
	 * @param string $password_hash Stored password hash.
	 * @param array $sites Sites to contact.
	 *
	 * @return array Results per site.
	 */
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

	/**
	 * Build the outgoing payload for a user.
	 *
	 * @param WP_User $user User object.
	 * @param array $site Site config.
	 *
	 * @return array Payload array.
	 */
	private function build_payload( WP_User $user, array $site ): array {
		$payload = array(
			'user_login'    => $user->user_login,
			'user_email'    => $user->user_email,
			'first_name'    => $user->first_name,
			'last_name'     => $user->last_name,
			'display_name'  => $user->display_name,
			'user_url'      => $user->user_url,
			'description'   => $user->description,
			'roles'         => $user->roles,
			'source_site'   => $this->get_site_url(),
			'target_site'   => $site['url'] ?? '',
			'hook'          => current_filter()
		);


		if ( ! empty( $this->get_roles() ) ) {
			$payload['roles'] = array_values( array_intersect( $user->roles, $this->get_roles() ) );
		}

		$meta_keys       = $this->get_meta_keys();
		$payload['meta'] = array();
		foreach ( $meta_keys as $key ) {
			$payload['meta'][ $key ] = get_user_meta( $user->ID, $key, true );
		}

		return $payload;
	}

	/**
	 * Send a signed HTTP request to an endpoint.
	 *
	 * @param string $endpoint URL to call.
	 * @param array $data Data to send.
	 * @param string $method HTTP method.
	 *
	 * @return array Response summary.
	 */
	public function send_request( string $endpoint, array $data, string $method = 'POST' ): array {
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

	/**
	 * Build a full REST endpoint for a site.
	 *
	 * @param array $site Site config.
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
}
