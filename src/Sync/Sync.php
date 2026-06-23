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

/**
 * Class Sync
 *
 * Coordinates sync triggers, inbound authentication and user creation.
 */
class Sync extends Base {

	use SyncHelper;
	use Requester;

	/**
	 * Sync sender helper.
	 *
	 * @var Sender
	 */
	public Sender $sender;

	/**
	 * REST API helper.
	 *
	 * @var Api
	 */
	public Api $api;

	/**
	 * Sync constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Initialize helpers.
		$this->sender = new Sender();
		$this->api    = new Api();

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
		add_action( 'remove_user_from_blog', array( $this, 'maybe_auto_delete_user' ) );

		/*add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );*/

		add_filter( 'authenticate', array( $this, 'maybe_import_remote_user' ), 20, 3 );

		// add_action( 'wp_login', array( $this, 'on_local_login' ), 10, 2 );
	}

	/**
	 * Decide whether the user should be synced outbound.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if allowed, false otherwise.
	 */
	public function allow_sync( int $user_id ): bool {

		if ( defined( 'ENTIREUS_INCOMING_SYNC' ) || ! $this->auto_sync() ) {
			return false;
		}

		$user         = get_userdata( $user_id );

		if ( ! $user ) {
			$this->write_log(
				array(
					'event'      => 'sync',
					'direction'  => 'outgoing',
					'user_email' => $user->user_email,
					'status'     => 'error',
					'message'    => 'User does not exist.',
					'payload'     => array(
						'hook' => current_filter(),
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
					'payload'     => array(
						'hook' => current_filter(),
						'user_email' => $user->user_email
					),

				)
			);

			return false;
		}

		return true;
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

		$this->sender->sync_user( $user_id );
	}

	/**
	 * Proxy for role changes to sync.
	 *
	 * @param int $user_id User ID.
	 */
	public function maybe_auto_sync_user_role( int $user_id ): void {
		$this->maybe_auto_sync_user( $user_id );
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
		$this->sender->delete_user( $user->user_email, $this->get_sites() );
	}

	/**
	 * Handle password reset event.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $new_pass New password.
	 */
	public function on_password_reset( \WP_User $user, string $new_pass ): void {
		$this->push_password_hash( $user );
	}

	/**
	 * Handle low-level set password action.
	 *
	 * @param string $password New password.
	 * @param int    $user_id  User ID.
	 */
	public function on_set_password( string $password, int $user_id ): void {
		if ( ! $this->allow_sync( $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->push_password_hash( $user );
		}
	}

	/**
	 * On local login, push password hash to remotes.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user User object.
	 */
	public function on_local_login( string $user_login, \WP_User $user ): void {
		$this->push_password_hash( $user );
	}

	/**
	 * Push password hash to remote sites for the user.
	 *
	 * @param \WP_User $user User instance.
	 */
	private function push_password_hash( \WP_User $user ): void {
		$sites = $this->get_sites();
		if ( empty( $sites ) ) {
			return;
		}

		$this->sender->sync_password( $user->user_email, $user->user_pass, $sites );
	}

	/**
	 * Attempt to authenticate by checking remote sites for the user.
	 *
	 * @param mixed  $user     WP_User or other auth value.
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return mixed WP_User or original $user on failure.
	 */
	public function maybe_import_remote_user( $user, string $username, string $password ) {
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		if ( empty( $username ) ) {
			return $user;
		}

		foreach ( $this->get_active_sites() as $site ) {
			$remote_user = $this->fetch_remote_user( $username, $password, $site );

			if ( is_wp_error( $remote_user ) || empty( $remote_user ) ) {
				continue;
			}

			$local_user = $this->create_local_user( $remote_user );

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

	/**
	 * Fetch a user from a remote site via REST.
	 *
	 * @param string $username Username to check.
	 * @param string $password Password to verify.
	 * @param array  $site     Site configuration (url, key, etc.).
	 * @return array|\WP_Error Remote user array or WP_Error on failure.
	 */
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
		if ( $code !== 200 ) {
			return new \WP_Error( 'entireus_remote_' . $code, 'Remote lookup failed' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return $data['user'] ?? new \WP_Error( 'entireus_no_user', 'No user in response' );
	}

	/**
	 * Create or update a local user from remote payload.
	 *
	 * @param array $remote Remote user payload.
	 *
	 * @return \WP_User|\WP_Error Local WP_User instance or WP_Error on failure.
	 */
	private function create_local_user( array $remote ): \WP_User|\WP_Error {
		$email = sanitize_email( $remote['user_email'] ?? '' );
		if ( ! $email ) {
			return new \WP_Error( 'entireus_bad_email', 'Remote user has no email' );
		}

		$existing = get_user_by( 'email', $email );

		// Build a safe user_login string (moved to helper to reduce complexity).
		$user_login = $this->build_user_login( $remote, $email, $existing );

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
			$this->apply_roles( $wp_user, $remote['roles'], $this->get_roles() );
		}

		$meta_keys       = $this->get_meta_keys();
		if ( ! empty( $remote['meta'] ) && is_array( $remote['meta'] ) ) {
			$this->apply_meta( $user_id, $remote['meta'], $meta_keys );
		}

		update_user_meta( $user_id, '_entireus_imported_from', sanitize_text_field( $remote['site_url'] ?? '' ) );

		return $wp_user;
	}

	/**
	 * Build a safe user_login string from remote payload or email.
	 *
	 * @param array               $remote Remote payload.
	 * @param string              $email Sanitized user email.
	 * @param \WP_User|false|null $existing Existing local user if any.
	 * @return string
	 */
	private function build_user_login( array $remote, string $email, $existing ): string {
		// Prefer remote user_login when provided; fall back to the local part of the email.
		$user_login = sanitize_user( $remote['user_login'] ?? '' );
		if ( empty( $user_login ) ) {
			$user_login = sanitize_user( strstr( $email, '@', true ) );
		}

		if ( ! $existing && username_exists( $user_login ) ) {
			$user_login .= '_' . substr( md5( $email ), 0, 5 );
		}

		return $user_login;
	}

	/**
	 * Apply roles to a WP_User instance, filtering by allowed list.
	 *
	 * @param \WP_User $wp_user User object to modify.
	 * @param array    $roles Roles from remote payload.
	 * @param array    $allowed Allowed roles from settings.
	 * @return void
	 */
	private function apply_roles( \WP_User $wp_user, array $roles, array $allowed ): void {
		$wp_user->set_role( '' );
		foreach ( $roles as $role ) {
			$role = sanitize_key( $role );
			if ( get_role( $role ) && ( empty( $allowed ) || in_array( $role, $allowed, true ) ) ) {
				$wp_user->add_role( $role );
			}
		}
	}

	/**
	 * Apply user meta from remote payload respecting allowed meta keys.
	 *
	 * @param int   $user_id User ID to update.
	 * @param array $meta Meta array from remote.
	 * @param array $meta_keys Allowed meta keys.
	 * @return void
	 */
	private function apply_meta( int $user_id, array $meta, array $meta_keys ): void {
		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( empty( $meta_keys ) || in_array( $key, $meta_keys, true ) ) {
				update_user_meta( $user_id, $key, $value );
			}
		}
	}
}
