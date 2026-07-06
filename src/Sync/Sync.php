<?php
/**
 * Sync service.
 *
 * Handles automatic user sync triggers, incoming remote lookups and user creation.
 *
 * @package EntireUserSync
 */

namespace EntireUserSync\Sync;

use EntireUserSync\Common\Abstracts\Base;
use EntireUserSync\Common\Traits\Requester;
use WP_Error;
use WP_User;

/**
 * Class Sync
 *
 * Coordinates sync triggers, inbound authentication and user creation.
 */
class Sync extends Base {

	use SyncHelper;
	use Requester;

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

		/*add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );*/

		add_filter( 'authenticate', array( $this, 'maybe_import_remote_user' ), 20, 3 );
		// add_action( 'wp_login', array( $this, 'on_local_login' ), 10, 2 );
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

		if ( ! defined( 'ENTIREUS_INCOMING_SYNC' ) ) {
			define( 'ENTIREUS_INCOMING_SYNC', true );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->write_log(
				array(
					'event'      => 'sync',
					'direction'  => 'outgoing',
					'user_email' => $user->user_email,
					'status'     => 'error',
					'message'    => 'User does not exist.',
					'payload'    => array(
						'hook'       => current_filter(),
						'user_email' => $user->user_email
					),
				)
			);

			return false;
		}

		if ( empty( $this->get_roles() ) || ! array_intersect( $user->roles, $this->get_roles() ) ) {
			$this->write_log(
				array(
					'event'      => 'sync',
					'direction'  => 'outgoing',
					'user_email' => $user->user_email,
					'status'     => 'error',
					'message'    => 'User does not have the required role.',
					'payload'    => array(
						'hook'       => current_filter(),
						'user_email' => $user->user_email
					),

				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Send a signed HTTP request to an endpoint.
	 *
	 * @param string $endpoint URL to call.
	 * @param array $data Data to send.
	 * @param string $method HTTP method.
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
		$data = json_decode( $body, true );


		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'entireus_invalid_json',
				'Invalid JSON response from remote site.',
				array(
					'code'     => $code,
					'body'     => $body,
					'endpoint' => $endpoint,
				)
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'entireus_remote_error',
				$data['message'] ?? sprintf( 'Remote request failed (%d).', $code ),
				array(
					'code'     => $code,
					'data'     => $data,
					'endpoint' => $endpoint,
				)
			);
		}

		return $data;
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
	 * @param array $site Site config.
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
			'description'  => $user->description,
			'roles'        => $user->roles,
			'source_site'  => $this->get_site_url(),
			'target_site'  => $site['url'],
			'hook'         => current_filter()
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
					'source_site' => $this->get_site_url(),
					'target_site' => $site['url'],
					'status'      => $response['status'],
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
	 * @param mixed $user WP_User or other auth value.
	 * @param string $username Username.
	 * @param string $password Password.
	 *
	 * @return mixed WP_User or original $user on failure.
	 */
	public function maybe_import_remote_user( $user, string $username, string $password ) {

		if ( $user instanceof WP_User ) {
			error_log('$user');
			return $user;
		}

		if ( empty( $username ) ) {
			error_log('$username');
			return $user;
		}

		foreach ( $this->get_active_sites() as $site ) {

			$response = $this->send_request(
				$this->endpoint( $site, 'get-user' ),
				array(
					'username'    => $username,
					'password'    => $password,
					'target_site' => $site['url'],
					'source_site' => $this->get_site_url()
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

			$remote_user             = $response['user'] ?? null;
			$remote_user['password'] = $password;

			$local_user = $this->create_user( $remote_user );

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
	 * @param string $new_pass New password.
	 */
	public function on_password_reset( \WP_User $user, string $new_pass ): void {
		$this->sync_password( $user, $new_pass );
	}

	/**
	 * Handle low-level set password action.
	 *
	 * @param string $password New password.
	 * @param int $user_id User ID.
	 */
	public function on_set_password( string $password, int $user_id ): void {
		if ( $user_id ) {
			$this->sync_password( $user_id, $password );
		}
	}

	/**
	 * Sync a user's password hash to remote sites.
	 *
	 * @param $user
	 * @param string $password
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
			$response                            = $this->send_request(
				$this->endpoint( $site, 'sync-password' ),
				array(
					'user_email'    => $user->user_email,
					'password_hash' => $password,
				)
			);
			$results[ $this->site_key( $site ) ] = $response;

			$this->write_log(
				array(
					'event'       => 'password',
					'direction'   => 'outgoing',
					'user_email'  => $user->user_email,
					'target_site' => $this->get_site_url(),
					'source_site' => $site['url'],
					'status'      => $response['status'],
					'message'     => $response['message'] ?? '',
					'payload'     => $response,
				)
			);
		}

		return $results;
	}
}
