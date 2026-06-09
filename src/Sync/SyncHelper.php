<?php

namespace EntireUserSync\Sync;

use EntireUserSync\Admin\Settings\Settings;

trait SyncHelper {

	private ?Settings $settings_instance = null;
	private ?Logger   $logger_instance   = null;

	public function set_settings( Settings $settings ): void {
		$this->settings_instance = $settings;
	}

	public function set_logger( Logger $logger ): void {
		$this->logger_instance = $logger;
	}

	private function settings(): Settings {
		if ( $this->settings_instance === null ) {
			$this->settings_instance = new Settings();
		}
		return $this->settings_instance;
	}

	private function logger(): Logger {
		if ( $this->logger_instance === null ) {
			$this->logger_instance = new Logger();
			// Share the same settings instance with Logger
			$this->logger_instance->set_settings( $this->settings() );
		}
		return $this->logger_instance;
	}

	public function get_secret(): string {
		return $this->settings()->get( 'integrations', 'secret_key' ) ?? '';
	}

	public function auto_sync(): bool {
		return (bool) $this->settings()->get( 'setup', 'sync_enable' );
	}

	public function get_sites(): array {
		return $this->settings()->get( 'setup', 'sites' ) ?? array();
	}

	public function get_roles(): array {
		return $this->settings()->get( 'configuration', 'roles' ) ?? array();
	}

	public function get_meta_keys(): array {
		return $this->settings()->get( 'configuration', 'option_meta' ) ?? array();
	}

	public function generate_and_save_secret(): string {
		$secret = bin2hex( random_bytes( 32 ) );
		$this->settings()->set( 'integrations', 'secret_key', $secret );
		return $secret;
	}

	public function enable_logging(): bool {
		return (bool) $this->settings()->get( 'configuration', 'enable_log' );
	}

	public function write_log( array $args ): void {
		if ( ! $this->enable_logging() ) {
			return;
		}
		$this->logger()->log( $args );
	}
}