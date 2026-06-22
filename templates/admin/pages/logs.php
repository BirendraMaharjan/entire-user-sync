<?php
/**
 * Admin logs page template.
 *
 * @package EntireUserSync
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $entireus_log_page ) ) {
	$entireus_log_page = new \EntireUserSync\Sync\LogPage();
}

if ( ! isset( $entireus_view ) ) {
	$entireus_view = $entireus_log_page->prepare_view();
}

$entireus_badge        = $entireus_view['badge'];
$entireus_base_url     = $entireus_view['base_url'];
$entireus_days         = $entireus_view['days'];
$entireus_deleted      = $entireus_view['deleted'];
$entireus_filters      = $entireus_view['filters'];
$entireus_notice       = $entireus_view['notice'];
$entireus_paged        = $entireus_view['paged'];
$entireus_page_slug    = $entireus_view['page_slug'];
$entireus_pages        = $entireus_view['pages'];
$entireus_prune_action = $entireus_view['prune_action'];
$entireus_rows         = $entireus_view['rows'];
$entireus_total        = $entireus_view['total'];
?>
<div class="wrap" id="entireus-log-wrap">
	<h1>
		<?php esc_html_e( 'User Sync Log', 'entire-user-sync' ); ?>
		<span class="title-count"><?php echo esc_html( number_format_i18n( $entireus_total ) ); ?></span>
	</h1>

	<?php if ( 'pruned' === $entireus_notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* Translators: 1: Number of deleted log rows, 2: Number of days. */
					esc_html__( 'Pruned %1$d log rows older than %2$d days.', 'entire-user-sync' ),
					absint( $entireus_deleted ),
					absint( $entireus_days )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" class="entireus-filter-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( $entireus_page_slug ); ?>">
		<div class="entireus-filters">
			<select name="event">
				<option value=""><?php esc_html_e( 'All Events', 'entire-user-sync' ); ?></option>
				<?php foreach ( array( 'create', 'update', 'delete', 'password', 'login' ) as $entireus_event ) : ?>
					<option value="<?php echo esc_attr( $entireus_event ); ?>" <?php selected( $entireus_filters['event'], $entireus_event ); ?>>
						<?php echo esc_html( ucfirst( $entireus_event ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="direction">
				<option value=""><?php esc_html_e( 'Both Directions', 'entire-user-sync' ); ?></option>
				<option value="outgoing" <?php selected( $entireus_filters['direction'], 'outgoing' ); ?>>
					<?php esc_html_e( 'Outgoing', 'entire-user-sync' ); ?>
				</option>
				<option value="incoming" <?php selected( $entireus_filters['direction'], 'incoming' ); ?>>
					<?php esc_html_e( 'Incoming', 'entire-user-sync' ); ?>
				</option>
			</select>

			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'entire-user-sync' ); ?></option>
				<option value="success" <?php selected( $entireus_filters['status'], 'success' ); ?>>
					<?php esc_html_e( 'Success', 'entire-user-sync' ); ?>
				</option>
				<option value="error" <?php selected( $entireus_filters['status'], 'error' ); ?>>
					<?php esc_html_e( 'Error', 'entire-user-sync' ); ?>
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

			<input type="date" name="date_from" value="<?php echo esc_attr( $entireus_filters['date_from'] ); ?>" title="From">
			<input type="date" name="date_to" value="<?php echo esc_attr( $entireus_filters['date_to'] ); ?>" title="To">

			<button type="submit" class="button">
				<?php esc_html_e( 'Filter', 'entire-user-sync' ); ?>
			</button>

			<a href="<?php echo esc_url( $entireus_base_url ); ?>" class="button">
				<?php esc_html_e( 'Reset', 'entire-user-sync' ); ?>
			</a>
		</div>
	</form>

	<?php if ( empty( $entireus_rows ) ) : ?>
		<p><?php esc_html_e( 'No log entries found.', 'entire-user-sync' ); ?></p>
	<?php else : ?>
		<?php $entireus_log_page->render_pagination( $entireus_paged, $entireus_pages, $entireus_filters, $entireus_base_url ); ?>

		<table class="wp-list-table widefat fixed striped entireus-log-table">
			<thead>
			<tr>
				<th style="width:50px"><?php esc_html_e( 'ID', 'entire-user-sync' ); ?></th>
				<th style="width:90px"><?php esc_html_e( 'Event', 'entire-user-sync' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Direction', 'entire-user-sync' ); ?></th>
				<th><?php esc_html_e( 'User', 'entire-user-sync' ); ?></th>
				<th><?php esc_html_e( 'From', 'entire-user-sync' ); ?></th>
				<th><?php esc_html_e( 'To', 'entire-user-sync' ); ?></th>
				<th style="width:70px"><?php esc_html_e( 'Status', 'entire-user-sync' ); ?></th>
				<th><?php esc_html_e( 'Message', 'entire-user-sync' ); ?></th>
				<th style="width:140px"><?php esc_html_e( 'Time (UTC)', 'entire-user-sync' ); ?></th>
				<th style="width:60px"><?php esc_html_e( 'Payload', 'entire-user-sync' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $entireus_rows as $entireus_row ) : ?>
				<tr>
					<td><?php echo esc_html( $entireus_row['id'] ); ?></td>
					<td>
						<span class="entireus-badge" style="background:<?php echo esc_attr( $entireus_badge[ $entireus_row['event'] ] ?? '#666' ); ?>">
							<?php echo esc_html( strtoupper( $entireus_row['event'] ) ); ?>
						</span>
					</td>
					<td>
						<?php if ( 'outgoing' === $entireus_row['direction'] ) : ?>
							<span title="Outgoing">&#11014; Out</span>
						<?php else : ?>
							<span title="Incoming">&#11015; In</span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$entireus_user = get_user_by( 'email', $entireus_row['user_email'] );

						if ( $entireus_user ) {
							$entireus_edit_link = get_edit_user_link( $entireus_user->ID );

							echo '<a href="' . esc_url( $entireus_edit_link ) . '">' . esc_html( $entireus_row['user_email'] ) . '</a>';
						} else {
							echo esc_html( $entireus_row['user_email'] );
						}
						?>
					</td>
					<td class="entireus-site-cell" title="<?php echo esc_attr( $entireus_row['source_site'] ); ?>">
						<?php echo esc_html( $entireus_log_page->short_url( $entireus_row['source_site'] ) ); ?>
					</td>
					<td class="entireus-site-cell" title="<?php echo esc_attr( $entireus_row['target_site'] ); ?>">
						<?php echo esc_html( $entireus_log_page->short_url( $entireus_row['target_site'] ) ); ?>
					</td>
					<td>
						<?php if ( 'success' === $entireus_row['status'] ) : ?>
							<span style="color:#00a32a"><?php echo esc_html( $entireus_row['status'] ); ?></span>
						<?php else : ?>
							<span style="color:#d63638"><?php echo esc_html( $entireus_row['status'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $entireus_row['message'] ); ?></td>
					<td><?php echo esc_html( $entireus_row['created_at'] ); ?></td>
					<td>
						<button
							type="button"
							class="button button-small entireus-payload-btn"
							data-payload="<?php echo esc_attr( $entireus_row['payload'] ); ?>"
						>
							JSON
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php $entireus_log_page->render_pagination( $entireus_paged, $entireus_pages, $entireus_filters, $entireus_base_url ); ?>
	<?php endif; ?>

	<div class="entireus-prune-form">
		<h3><?php esc_html_e( 'Prune Old Logs', 'entire-user-sync' ); ?></h3>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $entireus_prune_action ); ?>">
			<?php wp_nonce_field( 'entireus_prune', 'entireus_prune_nonce' ); ?>

			<label>
				<?php esc_html_e( 'Delete entries older than', 'entire-user-sync' ); ?>
				<input type="number" name="prune_days" value="90" min="0" style="width:70px"> days
			</label>

			<button type="submit" class="button button-secondary">
				<?php esc_html_e( 'Prune Now', 'entire-user-sync' ); ?>
			</button>
		</form>
	</div>
</div>

<div id="entireus-modal" style="display:none">
	<div id="entireus-modal-inner">
		<button id="entireus-modal-close" type="button">✕</button>
		<pre id="entireus-modal-body"></pre>
	</div>
</div>

<?php $entireus_log_page->render_assets(); ?>
