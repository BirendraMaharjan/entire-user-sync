<?php
/**
 * Admin page for viewing and managing sync logs.
 *
 * Provides a UI to browse, filter and prune sync event logs.
 *
 * @package EntireUserSync
 */

namespace EntireUserSync\Logger;

use EntireUserSync\Common\Abstracts\Base;

/**
 * Class LogPage
 *
 * @package EntireUserSync\Sync
 */
class LogPage extends Base {

	private const MENU_SLUG_SUFFIX = '-logs';
	private const DEFAULT_PER_PAGE = 2;
	private const PRUNE_ACTION     = 'entireus_prune_logs';

	private const EVENT_BADGES = array(
		'create'   => '#00a32a',
		'update'   => '#0073aa',
		'delete'   => '#d63638',
		'password' => '#8c5cf5',
		'login'    => '#f0a30a',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'admin_post_' . self::PRUNE_ACTION, array( $this, 'handle_prune' ) );
	}

	/**
	 * Handle prune form submission from the admin page.
	 *
	 * Validates capabilities and nonce then prunes old log entries.
	 */
	public function handle_prune(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to prune sync logs.', 'entire-user-sync' ) );
		}

		check_admin_referer( 'entireus_prune', 'entireus_prune_nonce' );

		$days = max( 0, absint( wp_unslash( $_POST['prune_days'] ?? 90 ) ) );

		$deleted  = Logger::prune( $days );
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = $this->page_url();
		}

		$redirect = add_query_arg(
			array(
				'entireus_log_notice' => 'pruned',
				'entireus_deleted'    => $deleted,
				'entireus_days'       => $days,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the logs admin page HTML.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$entireus_log_page = $this;
		$entireus_view     = $this->prepare_view();

		$template = $this->plugin->template_path() . '/admin/pages/logs.php';

		if ( ! is_readable( $template ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Log page template not found.', 'entire-user-sync' )
			);
			return;
		}

		require $template;
	}

	/**
	 * Prepare view data for the logs admin page.
	 *
	 * @return array View context including rows, filters and pagination.
	 */
	public function prepare_view(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This is read-only admin filter state.
		$filters = $this->get_filters();
		$result  = Logger::query( $filters );

		return array(
			'badge'        => self::EVENT_BADGES,
			'base_url'     => $this->page_url(),
			'days'         => absint( wp_unslash( $_GET['entireus_days'] ?? 90 ) ),
			'deleted'      => absint( wp_unslash( $_GET['entireus_deleted'] ?? 0 ) ),
			'filters'      => $filters,
			'notice'       => sanitize_key( wp_unslash( $_GET['entireus_log_notice'] ?? '' ) ),
			'paged'        => $filters['paged'],
			'page_slug'    => $this->menu_slug(),
			'pages'        => $result['pages'],
			'prune_action' => self::PRUNE_ACTION,
			'rows'         => $result['rows'],
			'total'        => $result['total'],
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Parse and return filters from the request query parameters.
	 *
	 * @return array Filters array with sanitized values.
	 */
	public function get_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This is read-only admin filter state.
		return array(
			'event'      => sanitize_key( wp_unslash( $_GET['event'] ?? '' ) ),
			'direction'  => sanitize_key( wp_unslash( $_GET['direction'] ?? '' ) ),
			'status'     => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'user_email' => sanitize_email( wp_unslash( $_GET['user_email'] ?? '' ) ),
			'site'       => sanitize_text_field( wp_unslash( $_GET['site'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
			'per_page'   => self::DEFAULT_PER_PAGE,
			'paged'      => max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Output simple pagination links for the admin view.
	 *
	 * @param int    $page     Current page number.
	 * @param int    $pages    Total pages.
	 * @param array  $filters  Active filters.
	 * @param string $base_url Base URL for links.
	 */
	public function render_pagination( int $page, int $pages, array $filters, string $base_url ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$query = array_filter(
			array_diff_key(
				$filters,
				array_flip( array( 'page', 'paged', 'per_page' ) )
			)
		);

		$base_url = add_query_arg( $query, $base_url );

		$pagination = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', $base_url ),
				'format'    => '',
				'current'   => $page,
				'total'     => $pages,
				'type'      => 'plain',
				'prev_text' => '&lsaquo;',
				'next_text' => '&rsaquo;',
			)
		);

		if ( $pagination ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post( $pagination );
			echo '</div></div>';
		}
	}

	/**
	 * Shorten a URL for display (remove protocol and trailing slash).
	 *
	 * @param string $url URL to shorten.
	 * @return string Shortened URL or em-dash when empty.
	 */
	public function short_url( string $url ): string {
		return $url ? preg_replace( '#^https?://#', '', untrailingslashit( $url ) ) : '—';
	}

	/**
	 * Return this page's menu slug.
	 */
	public function menu_slug(): string {
		return $this->plugin->slug() . self::MENU_SLUG_SUFFIX;
	}

	/**
	 * Return the admin page URL for the logs page.
	 */
	public function page_url(): string {
		return admin_url( 'admin.php?page=' . $this->menu_slug() );
	}

	/**
	 * Output inline CSS/JS required by the logs page.
	 */
	public function render_assets(): void {
		?>
		<style>
			#entireus-log-wrap .title-count {
				display: inline-block;
				font-size: 13px;
				background: #ddd;
				border-radius: 10px;
				padding: 2px 8px;
				margin-left: 8px;
				vertical-align: middle;
			}

			.entireus-filters {
				display: flex;
				flex-wrap: wrap;
				gap: 6px;
				align-items: center;
				margin: 12px 0;
			}

			.entireus-filters input, .entireus-filters select {
				height: 30px;
				font-size: 13px;
			}

			.entireus-badge {
				display: inline-block;
				color: #fff;
				font-size: 10px;
				font-weight: 700;
				padding: 2px 6px;
				border-radius: 3px;
				letter-spacing: .5px;
			}

			.entireus-site-cell {
				max-width: 160px;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			.entireus-log-table td {
				vertical-align: middle;
			}

			.entireus-prune-form {
				margin-top: 30px;
				padding: 16px;
				background: #fff;
				border: 1px solid #ddd;
				max-width: 400px;
			}

			#entireus-modal {
				position: fixed;
				inset: 0;
				background: rgba(0, 0, 0, .6);
				z-index: 99999;
				display: flex;
				align-items: center;
				justify-content: center;
			}

			#entireus-modal-inner {
				background: #fff;
				border-radius: 6px;
				padding: 20px 24px;
				max-width: 700px;
				width: 90%;
				max-height: 80vh;
				overflow: auto;
				position: relative;
			}

			#entireus-modal-close {
				position: absolute;
				top: 10px;
				right: 12px;
				background: none;
				border: none;
				font-size: 18px;
				cursor: pointer;
				line-height: 1;
			}

			#entireus-modal-body {
				font-size: 12px;
				white-space: pre-wrap;
				word-break: break-all;
				margin-top: 10px;
			}

			.tablenav-pages a,
			.tablenav-pages .current {
				display: inline-block;
				padding: 3px 8px;
				border: 1px solid #ccc;
				border-radius: 3px;
				margin: 2px;
				text-decoration: none;
			}

			.tablenav-pages .current {
				background: #0073aa;
				color: #fff;
				border-color: #0073aa;
			}
		</style>
		<script>
			(
				function () {
					document.querySelectorAll( '.entireus-payload-btn' ).forEach( function ( btn ) {
						btn.addEventListener( 'click', function () {
							var raw = this.dataset.payload;
							try {
								raw = JSON.stringify( JSON.parse( raw ), null, 2 );
							} catch ( e ) {
							}

							document.getElementById( 'entireus-modal-body' ).textContent = raw;
							document.getElementById( 'entireus-modal' ).style.display = 'flex';
						} );
					} );

					document.getElementById( 'entireus-modal-close' ).addEventListener( 'click', function () {
						document.getElementById( 'entireus-modal' ).style.display = 'none';
					} );

					document.getElementById( 'entireus-modal' ).addEventListener( 'click', function ( e ) {
						if ( e.target === this ) {
							this.style.display = 'none';
						}
					} );
				}
			)();
		</script>
		<?php
	}
}
