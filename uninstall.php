<?php
/**
 * Uninstall handler.
 *
 * Runs automatically when the plugin is deleted from the WordPress admin.
 * Removes all plugin options and cleans up any scheduled events.
 *
 * @package EntireUserSync
 * @since   1.0.0
 */

// Block direct access and ensure this is a legitimate uninstallation call.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ── Options ───────────────────────────────────────────────────────────────────
// Delete every option registered by the plugin.

$options = array(
	/*'entireus_setup',
	'entireus_configuration',
	'entireus_integrations',*/
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Multisite ─────────────────────────────────────────────────────────────────
// On a multisite network, clean up every sub-site's options as well.

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		restore_current_blog();
	}
}

// ── Scheduled events ──────────────────────────────────────────────────────────
// Clear any cron jobs registered by the plugin.

$cron_hooks = array(
	'entireus_scheduled_sync',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

// ── Transients ────────────────────────────────────────────────────────────────

$transients = array(
	'entireus_sync_status',
	'entireus_sync_log',
);

foreach ( $transients as $transient ) {
	delete_transient( $transient );
}

// ── Custom tables ─────────────────────────────────────────────────────────────

use EntireUserSync\Sync\Logger;

// Autoloader must be loaded before this.
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/*Logger::drop_table();

// Multisite: drop on every sub-site.
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
		switch_to_blog( $site_id );
		Logger::drop_table();
		restore_current_blog();
	}
}*/