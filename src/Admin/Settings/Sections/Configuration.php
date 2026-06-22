<?php
/**
 * Configuration section definitions.
 *
 * @package EntireUserSync\Admin\Settings
 */

namespace EntireUserSync\Admin\Settings\Sections;

/**
 * Class Configuration
 */
class Configuration {

	/**
	 * Return the configuration section definition.
	 *
	 * @return array Section array.
	 */
	public function get_section(): array {
		return array(
			'configuration' => array(
				'title'  => __( 'Configuration', 'entire-user-sync' ),
				'icon'   => 'dashicons dashicons-admin-generic',
				'fields' => $this->fields(),
			),
		);
	}

	/**
	 * Define configuration fields.
	 *
	 * @return array Field definitions.
	 */
	private function fields(): array {
		return array(
			'roles'             => array(
				'label'       => __( 'Sync Roles', 'entire-user-sync' ),
				'type'        => 'multiselect',
				'default'     => array(),
				'placeholder' => __( 'Select roles to sync…', 'entire-user-sync' ),
				'desc'        => __( 'Only users with these roles will be synchronised. Leave empty to sync all roles.', 'entire-user-sync' ),
				'options'     => $this->get_role_options(),
			),
			'sync_meta_keys'    => array(
				'label'       => __( 'Sync Meta Keys', 'entire-user-sync' ),
				'type'        => 'multiselect_grouped',
				'default'     => array(),
				'placeholder' => __( 'Select meta keys to sync…', 'entire-user-sync' ),
				'desc'        => __( 'Choose which user meta fields to include in the sync payload.', 'entire-user-sync' ),
				'options'     => $this->get_meta_key_options(),
			),
			'sync_direction'    => array(
				'label'   => __( 'Sync Direction', 'entire-user-sync' ),
				'type'    => 'radio',
				'default' => 'push',
				'desc'    => __( 'Push sends data from this site; Pull fetches from remotes.', 'entire-user-sync' ),
				'options' => array(
					'push' => __( 'Push  (this site → remotes)', 'entire-user-sync' ),
					'pull' => __( 'Pull  (remotes → this site)', 'entire-user-sync' ),
					'both' => __( 'Both', 'entire-user-sync' ),
				),
			),
			'sync_trigger'      => array(
				'label'       => __( 'Sync Trigger', 'entire-user-sync' ),
				'type'        => 'multiselect',
				'default'     => array( 'profile_update' ),
				'placeholder' => __( 'Select triggers…', 'entire-user-sync' ),
				'desc'        => __( 'Events that trigger an automatic sync.', 'entire-user-sync' ),
				'options'     => array(
					'user_register'  => __( 'User Registered', 'entire-user-sync' ),
					'profile_update' => __( 'Profile Updated', 'entire-user-sync' ),
					'delete_user'    => __( 'User Deleted', 'entire-user-sync' ),
					'set_user_role'  => __( 'Role Changed', 'entire-user-sync' ),
					'wp_login'       => __( 'User Login', 'entire-user-sync' ),
				),
			),
			'conflict_strategy' => array(
				'label'   => __( 'Conflict Strategy', 'entire-user-sync' ),
				'type'    => 'select',
				'default' => 'source_wins',
				'desc'    => __( 'What to do when the same user exists on both sites with different data.', 'entire-user-sync' ),
				'options' => array(
					'source_wins' => __( 'Source always wins', 'entire-user-sync' ),
					'target_wins' => __( 'Target always wins', 'entire-user-sync' ),
					'newest_wins' => __( 'Most recently modified wins', 'entire-user-sync' ),
					'skip'        => __( 'Skip — do not overwrite', 'entire-user-sync' ),
				),
			),
			'role_fallback'     => array(
				'label'   => __( 'Role Fallback', 'entire-user-sync' ),
				'type'    => 'select',
				'default' => 'subscriber',
				'desc'    => __( 'Assign this role when the user\'s original role does not exist on the target site.', 'entire-user-sync' ),
				'options' => array(
					'subscriber'  => __( 'Subscriber (recommended)', 'entire-user-sync' ),
					'contributor' => __( 'Contributor', 'entire-user-sync' ),
					'author'      => __( 'Author', 'entire-user-sync' ),
					'editor'      => __( 'Editor', 'entire-user-sync' ),
					'skip'        => __( 'Skip — do not sync this user', 'entire-user-sync' ),
					'strip'       => __( 'Sync without a role', 'entire-user-sync' ),
				),
			),
			'role_map'          => array(
				'label'      => __( 'Role Map', 'entire-user-sync' ),
				'type'       => 'repeater',
				'default'    => array(),
				'add_label'  => __( 'Add Mapping', 'entire-user-sync' ),
				'desc'       => __( 'Map a source role to a different role on the target site when the original does not exist.', 'entire-user-sync' ),
				'sub_fields' => array(
					'source_role' => array(
						'label'       => __( 'Source Role', 'entire-user-sync' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. shop_manager', 'entire-user-sync' ),
					),
					'target_role' => array(
						'label'       => __( 'Target Role', 'entire-user-sync' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. editor', 'entire-user-sync' ),
					),
				),
			),
			'batch_size'        => array(
				'label'   => __( 'Batch Size', 'entire-user-sync' ),
				'type'    => 'number',
				'default' => 50,
				'min'     => 1,
				'max'     => 500,
				'step'    => 1,
				'desc'    => __( 'Number of users processed per sync request.', 'entire-user-sync' ),
			),
			'enable_log'        => array(
				'label'          => __( 'Enable Logging', 'entire-user-sync' ),
				'type'           => 'checkbox',
				'default'        => '1',
				'checkbox_label' => __( 'Write sync activity to the error log', 'entire-user-sync' ),
			),
		);
	}

	/**
	 * Build options array from all registered WP roles.
	 *
	 * Returns: [ 'administrator' => 'Administrator', 'editor' => 'Editor', … ]
	 */
	private function get_role_options(): array {
		$roles   = wp_roles()->roles;
		$options = array();

		foreach ( $roles as $slug => $role ) {
			$options[ $slug ] = translate_user_role( $role['name'] );
		}

		return $options;
	}

	/**
	 * Build options array from every distinct meta_key in wp_usermeta.
	 *
	 * Skips internal WP keys that start with wp_ or session_tokens to keep
	 * the list clean — adjust $skip_patterns to taste.
	 *
	 * Returns: [ 'Category Label' => [ 'first_name' => 'first_name', … ], … ]
	 */
	private function get_meta_key_options(): array {
		global $wpdb;

		$skip_patterns = array(
			'wp_%',
			'session_tokens',
			'%capabilities',
			'%user_level',
			'%user-settings%',
			'closedpostboxes_%',
			'metaboxhidden_%',
			'edit_%',
			'screen_layout_%',
			'dismissed_wp_pointers',
			'show_welcome_panel',
			'community-events-location',
		);

		$where_fragments = array_map(
			fn( $p ) => $wpdb->prepare( 'meta_key NOT LIKE %s', $p ),
			$skip_patterns
		);

		$where = implode( ' AND ', $where_fragments );
		$where = '' !== $where ? 'WHERE ' . $where : '';

		$sql = 'SELECT DISTINCT meta_key FROM ' . $wpdb->usermeta . ' ' . $where . ' ORDER BY meta_key ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where_fragments are prepared before use.
		$keys = $wpdb->get_col( $sql );

		if ( empty( $keys ) ) {
			return array();
		}

		// -------------------------------------------------------------------------
		// Category bucket definitions
		// key   = category label shown in <optgroup>
		// value = array of exact meta_key matches OR prefix patterns (prefix:)
		// -------------------------------------------------------------------------
		$categories = array(

			'Core WordPress'         => array(
				'exact'  => array(
					'first_name',
					'last_name',
					'nickname',
					'description',
					'user_url',
					'user_registered',
					'rich_editing',
					'syntax_highlighting',
					'comment_shortcuts',
					'admin_color',
					'use_ssl',
					'show_admin_bar_front',
					'locale',
				),
				'prefix' => array(),
			),

			'WooCommerce — Billing'  => array(
				'exact'  => array(),
				'prefix' => array( 'billing_' ),
			),

			'WooCommerce — Shipping' => array(
				'exact'  => array(),
				'prefix' => array( 'shipping_' ),
			),

			'WooCommerce — Account'  => array(
				'exact'  => array(
					'paying_customer',
					'woocommerce_cart_hash',
					'wc_last_active',
					'last_update',
				),
				'prefix' => array(
					'woocommerce_',
					'_woocommerce_',
				),
			),

			'ACF / Custom Fields'    => array(
				'exact'  => array(),
				'prefix' => array( 'acf_', 'field_' ),
			),

			'BuddyPress / BuddyBoss' => array(
				'exact'  => array(),
				'prefix' => array( 'bp_', 'buddypress_' ),
			),

			'Ultimate Member'        => array(
				'exact'  => array(),
				'prefix' => array( 'um_', '_um_' ),
			),

			'Profile Builder'        => array(
				'exact'  => array(),
				'prefix' => array( 'pb_', 'profile_builder_' ),
			),

			'Social / OAuth'         => array(
				'exact'  => array(),
				'prefix' => array(
					'facebook_',
					'google_',
					'twitter_',
					'linkedin_',
					'oauth_',
				),
			),

		);

		// -------------------------------------------------------------------------
		// Bucket each key into its category
		// -------------------------------------------------------------------------
		$grouped          = array_fill_keys( array_keys( $categories ), array() );
		$grouped['Other'] = array();

		foreach ( $keys as $key ) {
			$matched = false;

			foreach ( $categories as $cat_label => $rules ) {

				// Exact match.
				if ( in_array( $key, $rules['exact'], true ) ) {
					$grouped[ $cat_label ][ $key ] = $key;
					$matched                       = true;
					break;
				}

				// Prefix match.
				foreach ( $rules['prefix'] as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						$grouped[ $cat_label ][ $key ] = $key;
						$matched                       = true;
						break 2;
					}
				}
			}

			if ( ! $matched ) {
				$grouped['Other'][ $key ] = $key;
			}
		}

		// Remove empty categories so no blank <optgroup> appears.
		return array_filter( $grouped );
	}
}
