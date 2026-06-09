<?php

namespace EntireUserSync\Sync;

use EntireUserSync\Common\Abstracts\Base;

class LogPage extends Base {

	private const MENU_SLUG_SUFFIX = '-logs';
	private const DEFAULT_PER_PAGE = 10;
	private const PRUNE_ACTION     = 'entireus_prune_logs';

	private const EVENT_BADGES = array(
		'create'   => '#00a32a',
		'update'   => '#0073aa',
		'delete'   => '#d63638',
		'password' => '#8c5cf5',
		'login'    => '#f0a30a',
	);

	public function __construct() {
		parent::__construct();

		add_action( 'admin_post_' . self::PRUNE_ACTION, array( $this, 'handle_prune' ) );
	}

	public function handle_prune(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to prune sync logs.', 'entire-user-sync' ) );
		}

		check_admin_referer( 'entireus_prune', 'entireus_prune_nonce' );

		$days     = max( 0, absint( wp_unslash( $_POST['prune_days'] ?? 90 ) ) );

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

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$view = $this->prepare_view();

		extract( $view, EXTR_SKIP );

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

	public function prepare_view(): array {
		$filters = $this->get_filters();
		$result  = Logger::query( $filters );

		return array(
			'badge'        => self::EVENT_BADGES,
			'base_url'     => $this->page_url(),
			'days'         => absint( wp_unslash( $_GET['entireus_days'] ?? 90 ) ),
			'deleted'      => absint( wp_unslash( $_GET['entireus_deleted'] ?? 0 ) ),
			'filters'      => $filters,
			'notice'       => sanitize_key( wp_unslash( $_GET['entireus_log_notice'] ?? '' ) ),
			'paged'         => $filters['paged'],
			'page_slug'    => $this->menu_slug(),
			'pages'        => $result['pages'],
			'prune_action' => self::PRUNE_ACTION,
			'rows'         => $result['rows'],
			'total'        => $result['total'],
		);
	}

	public function get_filters(): array {
		return array(
			'event'      => sanitize_key( wp_unslash( $_GET['event'] ?? '' ) ),
			'direction'  => sanitize_key( wp_unslash( $_GET['direction'] ?? '' ) ),
			'status'     => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'user_email' => sanitize_email( wp_unslash( $_GET['user_email'] ?? '' ) ),
			'site'       => sanitize_text_field( wp_unslash( $_GET['site'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
			'per_page'   => self::DEFAULT_PER_PAGE,
			'paged'       => max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ),
		);
	}

	public function render_pagination( int $page, int $pages, array $filters, string $base_url ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$query = array_filter( array_diff_key( $filters, array_flip( array( 'page', 'per_page' ) ) ) );
		echo '<div class="tablenav"><div class="tablenav-pages">';

		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = add_query_arg( array_merge( $query, array( 'paged' => $i ) ), $base_url );

			if ( $i === $page ) {
				echo '<span class="current">' . esc_html( $i ) . '</span> ';
			} else {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $i ) . '</a> ';
			}
		}

		echo '</div></div>';
	}

	public function short_url( string $url ): string {
		return $url ? preg_replace( '#^https?://#', '', untrailingslashit( $url ) ) : '—';
	}

	public function menu_slug(): string {
		return $this->plugin->slug() . self::MENU_SLUG_SUFFIX;
	}

	public function page_url(): string {
		return admin_url( 'admin.php?page=' . $this->menu_slug() );
	}

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
