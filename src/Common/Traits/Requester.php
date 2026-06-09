<?php
/**
 * Requester trait.
 *
 * @package EntireUserSync\Common\Traits
 */

namespace EntireUserSync\Common\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Requester
 *
 * Provides helper methods to identify the current WordPress request context.
 * Import this trait into any class that needs to branch behaviour by context.
 *
 * Usage:
 *   if ( $this->is_request( 'admin' ) ) { ... }
 *
 * Supported types: installing_wp | frontend | admin | rest | cron | cli
 */
trait Requester {

	/**
	 * Determine whether the current request matches a given context type.
	 *
	 * @param string $type One of: installing_wp, frontend, admin, rest, cron, cli.
	 *
	 * @throws \InvalidArgumentException When an unrecognised type is passed.
	 *
	 * @return bool
	 */
	public function is_request( string $type ): bool {
		switch ( $type ) {
			case 'installing_wp':
				return $this->is_installing_wp();

			case 'frontend':
				return $this->is_frontend();

			case 'admin':
				return $this->is_admin_backend();

			case 'rest':
				return $this->is_rest();

			case 'cron':
				return $this->is_cron();

			case 'cli':
				return $this->is_cli();

			default:
				throw new \InvalidArgumentException(
					sprintf(
					/* translators: %s: unknown request type string */
						esc_html__( 'Unknown request type: "%s". Expected one of: installing_wp, frontend, admin, rest, cron, cli.', 'entire-user-sync' ),
						esc_html( $type )
					)
				);
		}
	}

	/**
	 * Whether WordPress is currently being installed.
	 *
	 * @return bool
	 */
	public function is_installing_wp(): bool {
		return defined( 'WP_INSTALLING' ) && WP_INSTALLING;
	}

	/**
	 * Whether this is a regular frontend (theme) request.
	 *
	 * Returns true only when it is none of: admin, REST, cron, or CLI.
	 * This mirrors how WooCommerce and other major plugins define "frontend".
	 *
	 * Note: the original code omitted `is_cli()` here, which meant CLI
	 * commands would fall through as "frontend". Fixed below.
	 *
	 * @return bool
	 */
	public function is_frontend(): bool {
		return ! $this->is_admin_backend()
		       && ! $this->is_rest()
		       && ! $this->is_cron()
		       && ! $this->is_cli();
	}

	/**
	 * Whether this is a wp-admin backend request.
	 *
	 * The original code also required `is_user_logged_in()`, which caused
	 * two problems:
	 *   1. Plugins/themes loaded before `init` (when auth cookies are read)
	 *      would always return false because the current user isn't set yet.
	 *   2. Logged-out admin pages (login screen, install, etc.) were excluded.
	 *
	 * `is_admin()` alone is the correct and canonical WP check here.
	 *
	 * @return bool
	 */
	public function is_admin_backend(): bool {
		return is_admin();
	}

	/**
	 * Whether this is a REST API request.
	 *
	 * REST_REQUEST is defined by WordPress core before any plugin code runs
	 * during a REST request, so this is reliable at any hook priority.
	 *
	 * @return bool
	 */
	public function is_rest(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Whether this is a WP-Cron request.
	 *
	 * Prefers `wp_doing_cron()` (available since WP 4.8) and falls back to
	 * the raw constant for environments that define it directly.
	 *
	 * @return bool
	 */
	public function is_cron(): bool {
		return wp_doing_cron();
	}

	/**
	 * Whether this is a WP-CLI request.
	 *
	 * @return bool
	 */
	public function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}
}