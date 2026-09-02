<?php
/**
 * Admin logs page template.
 *
 * @package EntireUserSync
 */

use EntireUserSync\Admin\Tables\LogsTable;

defined( 'ABSPATH' ) || exit;

if ( ! isset( $entireus_log_page ) ) {
	$entireus_log_page = new \EntireUserSync\Logger\LogPage();
}

if ( ! isset( $entireus_view ) ) {
	$entireus_view = $entireus_log_page->prepare_view();
}

$entireus_base_url     = $entireus_view['base_url'];
$entireus_days         = $entireus_view['days'];
$entireus_deleted      = $entireus_view['deleted'];
$entireus_filters      = $entireus_view['filters'];
$entireus_notice       = $entireus_view['notice'];
$entireus_page_slug    = $entireus_view['page_slug'];
$entireus_prune_action = $entireus_view['prune_action'];

/*
 * Create and prepare the logs table.
 */
$entireus_log_table = new LogsTable(
	$entireus_log_page,
	$entireus_filters
);

$entireus_log_table->prepare_items();

$entireus_total = (int) $entireus_log_table->get_pagination_arg(
	'total_items'
);
?>

	<div class="wrap" id="entireus-log-wrap">

		<h1>
			<?php esc_html_e( 'User Sync Log', 'entire-user-sync' ); ?>

			<span class="title-count">
			<?php echo esc_html( number_format_i18n( $entireus_total ) ); ?>
		</span>
		</h1>

		<?php if ( 'pruned' === $entireus_notice ) : ?>

			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
					/* Translators: 1: Number of deleted log rows, 2: Number of days. */
						esc_html__(
							'Pruned %1$d log rows older than %2$d days.',
							'entire-user-sync'
						),
						absint( $entireus_deleted ),
						absint( $entireus_days )
					);
					?>
				</p>
			</div>

		<?php endif; ?>

		<form method="get" class="entireus-filter-form">

			<input
				type="hidden"
				name="page"
				value="<?php echo esc_attr( $entireus_page_slug ); ?>"
			>

			<div class="entireus-filters">

				<select name="event">
					<option value="">
						<?php
						esc_html_e(
							'All Events',
							'entire-user-sync'
						);
						?>
					</option>

					<?php foreach ( array( 'create', 'update', 'delete', 'password', 'login' ) as $entireus_event ) : ?>

						<option
							value="<?php echo esc_attr( $entireus_event ); ?>"
							<?php selected( $entireus_filters['event'], $entireus_event ); ?>
						>
							<?php echo esc_html( ucfirst( $entireus_event ) ); ?>
						</option>

					<?php endforeach; ?>

				</select>

				<select name="direction">

					<option value="">
						<?php
						esc_html_e(
							'Both Directions',
							'entire-user-sync'
						);
						?>
					</option>

					<option
						value="outgoing"
						<?php selected( $entireus_filters['direction'], 'outgoing' ); ?>
					>
						<?php
						esc_html_e(
							'Outgoing',
							'entire-user-sync'
						);
						?>
					</option>

					<option
						value="incoming"
						<?php selected( $entireus_filters['direction'], 'incoming' ); ?>
					>
						<?php
						esc_html_e(
							'Incoming',
							'entire-user-sync'
						);
						?>
					</option>

				</select>

				<select name="status">

					<option value="">
						<?php
						esc_html_e(
							'All Statuses',
							'entire-user-sync'
						);
						?>
					</option>

					<option
						value="success"
						<?php selected( $entireus_filters['status'], 'success' ); ?>
					>
						<?php
						esc_html_e(
							'Success',
							'entire-user-sync'
						);
						?>
					</option>

					<option
						value="error"
						<?php selected( $entireus_filters['status'], 'error' ); ?>
					>
						<?php
						esc_html_e(
							'Error',
							'entire-user-sync'
						);
						?>
					</option>

				</select>

				<input
					type="email"
					name="user_email"
					placeholder="<?php esc_attr_e( 'Filter by email', 'entire-user-sync' ); ?>"
					value="<?php echo esc_attr( $entireus_filters['user_email'] ); ?>"
				>

				<input
					type="text"
					name="site"
					placeholder="<?php esc_attr_e( 'Filter by site URL', 'entire-user-sync' ); ?>"
					value="<?php echo esc_attr( $entireus_filters['site'] ); ?>"
				>

				<input
					type="date"
					name="date_from"
					value="<?php echo esc_attr( $entireus_filters['date_from'] ); ?>"
					title="<?php esc_attr_e( 'From', 'entire-user-sync' ); ?>"
				>

				<input
					type="date"
					name="date_to"
					value="<?php echo esc_attr( $entireus_filters['date_to'] ); ?>"
					title="<?php esc_attr_e( 'To', 'entire-user-sync' ); ?>"
				>

				<button
					type="submit"
					class="button"
				>
					<?php
					esc_html_e(
						'Filter',
						'entire-user-sync'
					);
					?>
				</button>

				<a
					href="<?php echo esc_url( $entireus_base_url ); ?>"
					class="button"
				>
					<?php
					esc_html_e(
						'Reset',
						'entire-user-sync'
					);
					?>
				</a>

			</div>

		</form>

		<?php if ( 0 === $entireus_total ) : ?>

			<p>
				<?php
				esc_html_e(
					'No log entries found.',
					'entire-user-sync'
				);
				?>
			</p>

		<?php else : ?>

			<form method="get">

				<input
					type="hidden"
					name="page"
					value="<?php echo esc_attr( $entireus_page_slug ); ?>"
				>

				<?php
				$entireus_log_table->display();
				?>

			</form>

		<?php endif; ?>

		<div class="entireus-prune-form">

			<h3>
				<?php
				esc_html_e(
					'Prune Old Logs',
					'entire-user-sync'
				);
				?>
			</h3>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>

				<input
					type="hidden"
					name="action"
					value="<?php echo esc_attr( $entireus_prune_action ); ?>"
				>

				<?php wp_nonce_field( 'entireus_prune', 'entireus_prune_nonce' ); ?>

				<label>

					<?php
					esc_html_e(
						'Delete entries older than',
						'entire-user-sync'
					);
					?>

					<input
						type="number"
						name="prune_days"
						value="90"
						min="0"
						style="width:70px"
					>

					<?php esc_html_e( 'days', 'entire-user-sync' ); ?>

				</label>

				<button
					type="submit"
					class="button button-secondary"
				>
					<?php
					esc_html_e(
						'Prune Now',
						'entire-user-sync'
					);
					?>
				</button>

			</form>

		</div>

	</div>

	<div
		id="entireus-modal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="entireus-modal-body"
	>
		<div id="entireus-modal-inner">

			<button
				id="entireus-modal-close"
				type="button"
				aria-label="<?php esc_attr_e( 'Close', 'entire-user-sync' ); ?>"
			>
				✕
			</button>

			<pre id="entireus-modal-body"></pre>

		</div>
	</div>

<?php $entireus_log_page->render_assets(); ?>