<?php
/**
 * Shared helper trait for the sync subsystem.
 *
 * Provides accessors for plugin settings, site lists and shared logger used by
 * Sync, Sender and Api classes.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Sync;

use EntireUserSync\Admin\Settings\Settings;

trait SyncHelper {

	/**
	 * Settings instance (overrideable for tests).
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings_instance = null;

	/**
	 * Logger instance (created on demand).
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger_instance = null;

	/**
	 * Inject a Settings instance (for tests or overrides).
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function set_settings( Settings $settings ): void {
		$this->settings_instance = $settings;
	}

	/**
	 * Inject a Logger instance.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function set_logger( Logger $logger ): void {
		$this->logger_instance = $logger;
	}

	/**
	 * Return the Settings singleton for this plugin.
	 */
	private function settings(): Settings {
		if ( $this->settings_instance === null ) {
			$this->settings_instance = new Settings();
		}
		return $this->settings_instance;
	}

	/**
	 * Return the Logger instance, creating it if missing.
	 *
	 * @return Logger
	 */
	private function logger(): Logger {
		if ( $this->logger_instance === null ) {
			$this->logger_instance = new Logger();
			// Share the same settings instance with Logger.
			$this->logger_instance->set_settings( $this->settings() );
		}
		return $this->logger_instance;
	}

	/**
	 * Get secret key for remote signing.
	 */
	public function get_secret(): string {
		return $this->settings()->get( 'integrations', 'secret_key' ) ?? ENTIREUS_SECURITY_KEY;
	}

	/**
	 * Whether automatic outbound sync is enabled.
	 */
	public function auto_sync(): bool {
		return (bool) $this->settings()->get( 'setup', 'sync_enable' );
	}

	/**
	 * Return configured sites.
	 *
	 * @return array<int,array>
	 */
	public function get_sites(): array {
		return $this->settings()->get( 'setup', 'sites' ) ?? array();
	}

	/**
	 * Return only active sites.
	 *
	 * @return array<int,array>
	 */
	public function get_active_sites(): array {
		$sites = $this->get_sites();

		return array_values(
			array_filter(
				$sites,
				function ( $site ) {
					return isset( $site['active'] ) && '1' === $site['active'];
				}
			)
		);
	}

	/**
	 * Return configured roles allowed for syncing.
	 */
	public function get_roles(): array {
		return $this->settings()->get( 'configuration', 'roles' ) ?? array();
	}

	/**
	 * Return configured meta keys to include in payloads.
	 */
	public function get_meta_keys(): array {
		return $this->settings()->get( 'configuration', 'option_meta' ) ?? array();
	}

	/**
	 * Generate and persist a new secret key.
	 */
	public function generate_and_save_secret(): string {
		$secret = bin2hex( random_bytes( 32 ) );
		$this->settings()->set( 'integrations', 'secret_key', $secret );
		return $secret;
	}

	/**
	 * Whether logging is enabled.
	 */
	public function enable_logging(): bool {
		return (bool) $this->settings()->get( 'configuration', 'enable_log' );
	}

	/**
	 * Write a log if logging is enabled.
	 *
	 * @param array $args Log arguments.
	 */
	public function write_log( array $args ): void {
		if ( ! $this->enable_logging() ) {
			return;
		}
		$this->logger()->log( $args );
	}
}
