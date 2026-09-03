<?php
/**
 * Admin logs page template.
 *
 * @package EntireUserSync
 */

use EntireUserSync\Logger\LogsTable;

defined( 'ABSPATH' ) || exit;

/*
 * Create and prepare the logs table.
 */
$entireus_log_table = new LogsTable();
$entireus_log_table->prepare_items();
?>
<!-- Content -->
<div class="entire-admin-content">
	<?php
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These GET parameters are only used to display an admin notice.
	if ( isset( $_GET['entireus_trashed'] ) ) {
		$entireus_log_table->display_log_notice( 'trashed', absint( $_GET['entireus_trashed'] ) );
	}

	if ( isset( $_GET['entireus_restored'] ) ) {
		$entireus_log_table->display_log_notice( 'restored', absint( $_GET['entireus_restored'] ) );
	}

	if ( isset( $_GET['entireus_deleted'] ) ) {
		$entireus_log_table->display_log_notice( 'deleted', absint( $_GET['entireus_deleted'] ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>

	<form id="entireus-logs-filter" method="get">
		<input
			type="hidden"
			name="page"
			value="
			<?php
			echo esc_attr( sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The page parameter only preserves the current admin page.
			?>
			"
		/>
		<?php wp_nonce_field( 'bulk-logs' ); ?>

		<?php $entireus_log_table->views(); ?>

		<?php $entireus_log_table->search_box( __( 'Search Log', 'entire-user-sync' ), 'entireus-log' ); ?>
		<?php $entireus_log_table->display(); ?>
	</form>
</div>