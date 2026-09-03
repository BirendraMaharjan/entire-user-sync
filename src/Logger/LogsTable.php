<?php
/**
 * User sync logs list table.
 *
 * @package EntireUserSync
 */

namespace EntireUserSync\Logger;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Logs list table.
 */
class LogsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	protected function get_views(): array {
		$current_status = isset( $_GET['filter_status'] ) ?
			sanitize_key( wp_unslash( $_GET['filter_status'] ) ) :
			'';

		$current_view = isset( $_GET['log_view'] ) ?
			sanitize_key( wp_unslash( $_GET['log_view'] ) ) :
			'';

		$counts = Logger::get_log_counts();

		$base_url = remove_query_arg(
			array_diff( array_keys( $_GET ), array( 'page' ) )
		);

		return array(
			'all'     => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $base_url ),
				'' === $current_status && '' === $current_view ? 'current' : '',
				sprintf(
					/* translators: %d: Number of logs. */
					__( 'All (%d)', 'entire-user-sync' ),
					$counts['all']
				)
			),
			'success' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'filter_status', 'success', $base_url ) ),
				'success' === $current_status && '' === $current_view ? 'current' : '',
				sprintf(
					/* translators: %d: Number of successful logs. */
					__( 'Success (%d)', 'entire-user-sync' ),
					$counts['success']
				)
			),
			'error'   => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'filter_status', 'error', $base_url ) ),
				'error' === $current_status && '' === $current_view ? 'current' : '',
				sprintf(
					/* translators: %d: Number of error logs. */
					__( 'Error (%d)', 'entire-user-sync' ),
					$counts['error']
				)
			),
			'trash'   => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'log_view', 'trash', $base_url ) ),
				'trash' === $current_view ? 'current' : '',
				sprintf(
				/* translators: %d: Number of trashed logs. */
					__( 'Trash (%d)', 'entire-user-sync' ),
					$counts['trash']
				)
			),
		);
	}

	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'created_at'  => __( 'Date', 'entire-user-sync' ),
			'event'       => __( 'Event', 'entire-user-sync' ),
			'direction'   => __( 'Direction', 'entire-user-sync' ),
			'user_email'  => __( 'User Email', 'entire-user-sync' ),
			'source_site' => __( 'Source', 'entire-user-sync' ),
			'target_site' => __( 'Target', 'entire-user-sync' ),
			'status'      => __( 'Status', 'entire-user-sync' ),
			'message'     => __( 'Message', 'entire-user-sync' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'created_at'  => array( 'created_at', true ),
			'direction'   => array( 'direction', true ),
			'event'       => array( 'event', false ),
			'status'      => array( 'status', false ),
			'source_site' => array( 'source_site', false ),
			'target_site' => array( 'target_site', false ),
			'user_email'  => array( 'user_email', false ),
		);
	}

	protected function get_bulk_actions(): array {
		$view = isset( $_GET['log_view'] ) ?
			sanitize_key( wp_unslash( $_GET['log_view'] ) ) :
			'';

		if ( 'trash' === $view ) {
			return array(
				'restore' => __( 'Restore', 'entire-user-sync' ),
				'delete'  => __( 'Delete Permanently', 'entire-user-sync' ),
			);
		}

		return array(
			'trash'  => __( 'Move to Trash', 'entire-user-sync' ),
			'delete' => __( 'Delete Permanently', 'entire-user-sync' ),
		);
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$event     = isset( $_REQUEST['filter_event'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_event'] ) ) : '';
		$direction = isset( $_REQUEST['filter_direction'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_direction'] ) ) : '';
		$status    = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '';
		?>
		<div class="alignleft actions">
			<label>
				<select name="filter_direction">
					<option value=""><?php esc_html_e( 'All directions', 'entire-user-sync' ); ?></option>
					<option
						value="outgoing" <?php selected( $direction, 'outgoing' ); ?>><?php esc_html_e( 'Outgoing', 'entire-user-sync' ); ?></option>
					<option
						value="incoming" <?php selected( $direction, 'incoming' ); ?>><?php esc_html_e( 'Incoming', 'entire-user-sync' ); ?></option>
				</select>
			</label>
			<label>
				<select name="filter_status">
					<option value=""><?php esc_html_e( 'All statuses', 'entire-user-sync' ); ?></option>
					<option
						value="success" <?php selected( $status, 'success' ); ?>><?php esc_html_e( 'Success', 'entire-user-sync' ); ?></option>
					<option
						value="error" <?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'entire-user-sync' ); ?></option>
				</select>
			</label>
			<label>
				<input type="text" name="filter_event" value="<?php echo esc_attr( $event ); ?>"
						placeholder="<?php esc_attr_e( 'Event', 'entire-user-sync' ); ?>"/>
			</label>
			<?php submit_button( __( 'Filter', 'entire-user-sync' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="log[]" value="%d" />',
			absint( $item['id'] )
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	protected function column_created_at( $item ): string {
		$timestamp = strtotime( $item['created_at'] . ' UTC' );

		$t_time = sprintf(
		/* translators: 1: Log date, 2: Log time. */
			__( '%1$s at %2$s', 'entire-user-sync' ),
			wp_date( __( 'Y/m/d', 'entire-user-sync' ), $timestamp ),
			wp_date( __( 'g:i a', 'entire-user-sync' ), $timestamp )
		);

		/**
		 * Filters the displayed date/time for a log row.
		 *
		 * @param string $t_time    Formatted date/time string.
		 * @param array  $item      The log row.
		 * @param int    $timestamp Unix timestamp (UTC).
		 */
		$t_time = apply_filters( 'entireus_log_date_column_time', $t_time, $item, $timestamp );

		return esc_html( $t_time );
	}

	protected function column_status( $item ): string {
		$class = 'error' === $item['status'] ? 'notice-error' : 'notice-success';

		return sprintf(
			'<span class="entireus-status %s">%s</span>',
			esc_attr( $class ),
			esc_html( ucfirst( $item['status'] ) )
		);
	}

	protected function column_message( $item ): string {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'delete',
					'log'    => $item['id'],
				)
			),
			'bulk-' . $this->_args['plural']
		);

		$actions = array(
			'view'   => sprintf(
				'<a href="#" class="entireus-view-payload" data-id="%d" data-payload="%s">%s</a>',
				absint( $item['id'] ),
				esc_attr( $item['payload'] ?? '' ),
				esc_html__( 'View payload', 'entire-user-sync' )
			),

			'delete' => sprintf(
				'<a href="%s" class="entireus-delete-log" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this log entry? This cannot be undone.', 'entire-user-sync' ) ),
				esc_html__( 'Delete', 'entire-user-sync' )
			),
		);

		return esc_html( $item['message'] ) . $this->row_actions( $actions );
	}

	/**
	 * Handle the bulk action.
	 *
	 * Verifies the bulk-action nonce and capability, performs the requested
	 * action, then redirects (PRG) to avoid resubmission on refresh.
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! in_array( $action, array( 'trash', 'restore', 'delete' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'entire-user-sync' ) );
		}

		$ids = isset( $_REQUEST['log'] )
			? array_map( 'absint', (array) wp_unslash( $_REQUEST['log'] ) )
			: array();

		$ids = array_values( array_filter( $ids ) );

		if ( 'trash' === $action ) {
			$affected = Logger::trash( $ids );
			$notice   = 'entireus_trashed';
		} elseif ( 'restore' === $action ) {
			$affected = Logger::restore( $ids );
			$notice   = 'entireus_restored';
		} else {
			$affected = Logger::delete( $ids );
			$notice   = 'entireus_deleted';
		}

		$redirect_url = remove_query_arg(
			array( 'action', 'action2', 'log', '_wpnonce', '_wp_http_referer' )
		);

		$redirect_url = add_query_arg(
			$notice,
			$affected,
			$redirect_url
		);

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	public function prepare_items(): void {
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'entireus_logs_per_page' );
		$current_page = $this->get_pagenum();
		$orderby      = ! empty( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order        = ! empty( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		$search       = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$this->_column_headers = array(
			$this->get_columns(),
			get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(),
		);

		$result = Logger::query(
			array(
				'per_page'  => $per_page,
				'paged'     => $current_page,
				'orderby'   => $orderby,
				'order'     => $order,
				'event'     => ! empty( $_REQUEST['filter_event'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_event'] ) ) : '',
				'direction' => ! empty( $_REQUEST['filter_direction'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_direction'] ) ) : '',
				'status'    => ! empty( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '',
				'view'      => ! empty( $_REQUEST['log_view'] ) ? sanitize_key( wp_unslash( $_REQUEST['log_view'] ) ) : '',
				'search'    => $search,
			)
		);

		$this->items = $result['rows'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => $result['pages'],
			)
		);
	}

	/**
	 * Display an admin log action notice.
	 *
	 * @param string $action Action name.
	 * @param int    $count  Number of affected logs.
	 */
	public function display_log_notice( string $action, int $count ): void {
		if ( ! $count ) {
			return;
		}

		switch ( $action ) {
			case 'trashed':
				$message = _n(
					'%d log entry moved to Trash.',
					'%d log entries moved to Trash.',
					$count,
					'entire-user-sync'
				);
				break;

			case 'restored':
				$message = _n(
					'%d log entry restored.',
					'%d log entries restored.',
					$count,
					'entire-user-sync'
				);
				break;

			case 'deleted':
				$message = _n(
					'%d log entry permanently deleted.',
					'%d log entries permanently deleted.',
					$count,
					'entire-user-sync'
				);
				break;

			default:
				return;
		}

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html( $message ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</p>
		</div>
		<?php
	}
}