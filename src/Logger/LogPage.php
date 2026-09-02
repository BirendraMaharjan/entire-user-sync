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
 * Class LogPage.
 *
 * @package EntireUserSync\Logger
 */
class LogPage extends Base {

	/**
	 * Menu slug suffix.
	 */
	private const MENU_SLUG_SUFFIX = '-logs';

	/**
	 * Default number of logs per page.
	 */
	public const DEFAULT_PER_PAGE = 50;

	/**
	 * Prune action.
	 */
	private const PRUNE_ACTION = 'entireus_prune_logs';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_action(
			'admin_post_' . self::PRUNE_ACTION,
			array( $this, 'handle_prune' )
		);
	}

	/**
	 * Handle prune form submission from the admin page.
	 */
	public function handle_prune(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to prune sync logs.',
					'entire-user-sync'
				)
			);
		}

		check_admin_referer(
			'entireus_prune',
			'entireus_prune_nonce'
		);

		$days = max(
			0,
			absint(
				wp_unslash(
					$_POST['prune_days'] ?? 90
				)
			)
		);

		$deleted = Logger::prune( $days );

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
	 * Render the logs admin page.
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
				esc_html__(
					'Log page template not found.',
					'entire-user-sync'
				)
			);

			return;
		}

		require $template;
	}

	/**
	 * Prepare view data for the logs admin page.
	 *
	 * @return array View context.
	 */
	public function prepare_view(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filter state.
		return array(
			'base_url'     => $this->page_url(),
			'days'         => absint(
				wp_unslash(
					$_GET['entireus_days'] ?? 90
				)
			),
			'deleted'      => absint(
				wp_unslash(
					$_GET['entireus_deleted'] ?? 0
				)
			),
			'filters'      => $this->get_filters(),
			'notice'       => sanitize_key(
				wp_unslash(
					$_GET['entireus_log_notice'] ?? ''
				)
			),
			'page_slug'    => $this->menu_slug(),
			'prune_action' => self::PRUNE_ACTION,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Parse and return filters from request query parameters.
	 *
	 * @return array Filters.
	 */
	public function get_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filter state.
		return array(
			'event'      => sanitize_key(
				wp_unslash(
					$_GET['event'] ?? ''
				)
			),
			'direction'  => sanitize_key(
				wp_unslash(
					$_GET['direction'] ?? ''
				)
			),
			'status'     => sanitize_key(
				wp_unslash(
					$_GET['status'] ?? ''
				)
			),
			'user_email' => sanitize_email(
				wp_unslash(
					$_GET['user_email'] ?? ''
				)
			),
			'site'       => sanitize_text_field(
				wp_unslash(
					$_GET['site'] ?? ''
				)
			),
			'date_from'  => sanitize_text_field(
				wp_unslash(
					$_GET['date_from'] ?? ''
				)
			),
			'date_to'    => sanitize_text_field(
				wp_unslash(
					$_GET['date_to'] ?? ''
				)
			),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Shorten a URL for display.
	 *
	 * @param string $url URL to shorten.
	 *
	 * @return string Shortened URL.
	 */
	public function short_url( string $url ): string {
		return $url
			? preg_replace(
				'#^https?://#',
				'',
				untrailingslashit( $url )
			)
			: '—';
	}

	/**
	 * Return this page's menu slug.
	 *
	 * @return string
	 */
	public function menu_slug(): string {
		return $this->plugin->slug() . self::MENU_SLUG_SUFFIX;
	}

	/**
	 * Return the admin page URL for the logs page.
	 *
	 * @return string
	 */
	public function page_url(): string {
		return admin_url(
			'admin.php?page=' . $this->menu_slug()
		);
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

			.entireus-filters input,
			.entireus-filters select {
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
				display: none;
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
		</style>

		<script>
			(
				function () {
					var modal = document.getElementById( 'entireus-modal' );
					var modalBody = document.getElementById( 'entireus-modal-body' );
					var modalClose = document.getElementById( 'entireus-modal-close' );

					document.querySelectorAll( '.entireus-payload-btn' ).forEach( function ( btn ) {
						btn.addEventListener( 'click', function () {
							var raw = this.dataset.payload;

							try {
								raw = JSON.stringify( JSON.parse( raw ), null, 2 );
							} catch ( e ) {
							}

							modalBody.textContent = raw;
							modal.style.display = 'flex';
						} );
					} );

					if ( modalClose ) {
						modalClose.addEventListener( 'click', function () {
							modal.style.display = 'none';
						} );
					}

					if ( modal ) {
						modal.addEventListener( 'click', function ( e ) {
							if ( e.target === this ) {
								this.style.display = 'none';
							}
						} );
					}
				}
			)();
		</script>
		<?php
	}
}