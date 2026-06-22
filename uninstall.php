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

$entireus_options = array(
	'entireus_setup',
	'entireus_configuration',
	'entireus_integrations',
);

foreach ( $entireus_options as $entireus_option ) {
	delete_option( $entireus_option );
}

// ── Multisite ─────────────────────────────────────────────────────────────────
// On a multisite network, clean up every sub-site's options as well.

if ( is_multisite() ) {
	$entireus_sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $entireus_sites as $entireus_site_id ) {
		switch_to_blog( $entireus_site_id );

		foreach ( $entireus_options as $entireus_option ) {
			delete_option( $entireus_option );
		}

		restore_current_blog();
	}
}

// ── Scheduled events ──────────────────────────────────────────────────────────
// Clear any cron jobs registered by the plugin.

$entireus_cron_hooks = array(
	'entireus_scheduled_sync',
);

foreach ( $entireus_cron_hooks as $entireus_hook ) {
	$entireus_timestamp = wp_next_scheduled( $entireus_hook );

	if ( $entireus_timestamp ) {
		wp_unschedule_event( $entireus_timestamp, $entireus_hook );
	}
}

// ── Transients ────────────────────────────────────────────────────────────────

$entireus_transients = array(
	'entireus_sync_status',
	'entireus_sync_log',
);

foreach ( $entireus_transients as $entireus_transient ) {
	delete_transient( $entireus_transient );
}

// ── Custom tables ─────────────────────────────────────────────────────────────

// Autoloader must be loaded before using any plugin classes.
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use EntireUserSync\Sync\Logger;

// Custom table cleanup is intentionally disabled on uninstall.
