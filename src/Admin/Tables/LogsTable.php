<?php
/**
 * User sync logs list table.
 *
 * @package EntireUserSync
 */

namespace EntireUserSync\Admin\Tables;

use EntireUserSync\Logger\LogPage;
use EntireUserSync\Logger\Logger;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Logs list table.
 */
class LogsTable extends \WP_List_Table {

	/**
	 * Event badge colors.
	 */
	private const EVENT_BADGES = array(
		'create'   => '#00a32a',
		'update'   => '#0073aa',
		'delete'   => '#d63638',
		'password' => '#8c5cf5',
		'login'    => '#f0a30a',
	);

	/**
	 * Log page helper.
	 *
	 * @var LogPage
	 */
	private LogPage $log_page;

	/**
	 * Active filters.
	 *
	 * @var array
	 */
	private array $filters;

	/**
	 * Constructor.
	 *
	 * @param LogPage $log_page Log page instance.
	 * @param array   $filters  Active filters.
	 */
	public function __construct(
		LogPage $log_page,
		array $filters = array()
	) {
		$this->log_page = $log_page;
		$this->filters  = $filters;

		parent::__construct(
			array(
				'singular' => 'entireus_log',
				'plural'   => 'entireus_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'id'          => __( 'ID', 'entire-user-sync' ),
			'event'       => __( 'Event', 'entire-user-sync' ),
			'direction'   => __( 'Direction', 'entire-user-sync' ),
			'user_email'  => __( 'User', 'entire-user-sync' ),
			'source_site' => __( 'From', 'entire-user-sync' ),
			'target_site' => __( 'To', 'entire-user-sync' ),
			'status'      => __( 'Status', 'entire-user-sync' ),
			'message'     => __( 'Message', 'entire-user-sync' ),
			'created_at'  => __( 'Time (UTC)', 'entire-user-sync' ),
			'payload'     => __( 'Payload', 'entire-user-sync' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return array(
			'id'          => array( 'id', true ),
			'event'       => array( 'event', false ),
			'direction'   => array( 'direction', false ),
			'user_email'  => array( 'user_email', false ),
			'source_site' => array( 'source_site', false ),
			'target_site' => array( 'target_site', false ),
			'status'      => array( 'status', false ),
			'created_at'  => array( 'created_at', true ),
		);
	}

	/**
	 * Prepare table items.
	 */
	public function prepare_items(): void {

		$per_page = LogPage::DEFAULT_PER_PAGE;
		$paged    = $this->get_pagenum();

		$orderby = sanitize_key(
			wp_unslash(
				$_GET['orderby'] ?? 'id'
			)
		);

		$order = strtoupper(
			sanitize_key(
				wp_unslash(
					$_GET['order'] ?? 'DESC'
				)
			)
		);

		$result = Logger::query(
			array(
				'per_page'   => $per_page,
				'paged'      => $paged,
				'event'      => $this->filters['event'] ?? '',
				'direction'  => $this->filters['direction'] ?? '',
				'status'     => $this->filters['status'] ?? '',
				'user_email' => $this->filters['user_email'] ?? '',
				'site'       => $this->filters['site'] ?? '',
				'date_from'  => $this->filters['date_from'] ?? '',
				'date_to'    => $this->filters['date_to'] ?? '',
				'orderby'    => $orderby,
				'order'      => $order,
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
	 * Default column output.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default(
		$item,
		$column_name
	): string {
		return esc_html(
			$item[ $column_name ] ?? ''
		);
	}

	/**
	 * Event column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_event( $item ): string {

		$event = $item['event'] ?? '';
		$color = self::EVENT_BADGES[ $event ] ?? '#666';

		return sprintf(
			'<span class="entireus-badge" style="background:%1$s">%2$s</span>',
			esc_attr( $color ),
			esc_html( strtoupper( $event ) )
		);
	}

	/**
	 * Direction column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_direction( $item ): string {

		if ( 'outgoing' === $item['direction'] ) {
			return sprintf(
				'<span title="%1$s">&#11014; %2$s</span>',
				esc_attr__(
					'Outgoing',
					'entire-user-sync'
				),
				esc_html__(
					'Out',
					'entire-user-sync'
				)
			);
		}

		return sprintf(
			'<span title="%1$s">&#11015; %2$s</span>',
			esc_attr__(
				'Incoming',
				'entire-user-sync'
			),
			esc_html__(
				'In',
				'entire-user-sync'
			)
		);
	}

	/**
	 * User column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_user_email( $item ): string {

		$email = $item['user_email'] ?? '';

		$user = get_user_by(
			'email',
			$email
		);

		if ( ! $user ) {
			return esc_html( $email );
		}

		$edit_link = get_edit_user_link(
			$user->ID
		);

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $edit_link ),
			esc_html( $email )
		);
	}

	/**
	 * Source site column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_source_site( $item ): string {

		$url = $item['source_site'] ?? '';

		return sprintf(
			'<span class="entireus-site-cell" title="%1$s">%2$s</span>',
			esc_attr( $url ),
			esc_html(
				$this->log_page->short_url( $url )
			)
		);
	}

	/**
	 * Target site column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_target_site( $item ): string {

		$url = $item['target_site'] ?? '';

		return sprintf(
			'<span class="entireus-site-cell" title="%1$s">%2$s</span>',
			esc_attr( $url ),
			esc_html(
				$this->log_page->short_url( $url )
			)
		);
	}

	/**
	 * Status column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_status( $item ): string {

		$status = $item['status'] ?? '';

		return sprintf(
			'<span class="entireus-status entireus-status-%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $status )
		);
	}

	/**
	 * Payload column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	protected function column_payload( $item ): string {

		return sprintf(
			'<button
				type="button"
				class="button button-small entireus-payload-btn"
				data-payload="%1$s"
			>%2$s</button>',
			esc_attr( $item['payload'] ?? '' ),
			esc_html__( 'JSON', 'entire-user-sync' )
		);
	}

	/**
	 * Add table classes.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function display_tablenav( $which ): void {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( 'top' === $which ) : ?>

				<?php $this->extra_tablenav( $which ); ?>

			<?php endif; ?>

			<div class="tablenav-pages">
				<?php $this->pagination( $which ); ?>
			</div>

			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Output additional table navigation.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ): void {
		?>
		<div class="alignleft actions">
			<?php
			$total_items = (int) $this->get_pagination_arg(
				'total_items'
			);

			printf(
				'<span class="displaying-num">%s</span>',
				esc_html(
					sprintf(
					/* Translators: %s: Number of log items. */
						_n(
							'%s item',
							'%s items',
							$total_items,
							'entire-user-sync'
						),
						number_format_i18n( $total_items )
					)
				)
			);
			?>
		</div>
		<?php
	}
}