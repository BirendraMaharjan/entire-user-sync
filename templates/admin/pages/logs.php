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
	<?php if ( isset( $_GET['entireus_deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				$deleted = isset( $_GET['entireus_deleted'] ) ? absint( $_GET['entireus_deleted'] ) : 0;

				printf(
					esc_html(
						_n(
							/* translators: %d: number of deleted log entries. */
							'%d log entry deleted.',
							'%d log entries deleted.',
							$deleted,
							'entire-user-sync'
						)
					),
					$deleted
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form id="entireus-logs-filter" method="get">
		<input
			type="hidden"
			name="page"
			value="<?php echo esc_attr( sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) ) ); ?>"
		/>
		<?php wp_nonce_field( 'bulk-logs' ); ?>

		<?php $entireus_log_table->views(); ?>

		<?php $entireus_log_table->search_box( __( 'Search Log', 'entire-user-sync' ), 'entireus-log' ); ?>
		<?php $entireus_log_table->display(); ?>
	</form>
</div>