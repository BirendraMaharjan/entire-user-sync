<?php
/**
 * Admin logs page template.
 *
 * @package EntireUserSync
 */

use EntireUserSync\Admin\Tables\LogsTable;

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
				printf(
					/* translators: %d: number of deleted log entries. */
					esc_html( _n( '%d log entry deleted.', '%d log entries deleted.', absint( $_GET['entireus_deleted'] ), 'entire-user-sync' ) ),
					absint( $_GET['entireus_deleted'] )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form id="entireus-logs-filter" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) ) ); ?>"/>
		<?php wp_nonce_field( 'bulk-logs' ); ?>

		<?php $entireus_log_table->search_box( __( 'Search site URL', 'entire-user-sync' ), 'entireus-log' ); ?>
		<?php $entireus_log_table->display(); ?>
	</form>
</div>
<style>
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

<div id="entireus-modal">
	<div id="entireus-modal-inner">
		<button type="button" id="entireus-modal-close" aria-label="<?php esc_attr_e( 'Close', 'entire-user-sync' ); ?>">&times;</button>
		<strong><?php esc_html_e( 'Payload', 'entire-user-sync' ); ?></strong>
		<div id="entireus-modal-body"></div>
	</div>
</div>

<script>
	(
		function () {
			const modal = document.getElementById( 'entireus-modal' );
			const modalBody = document.getElementById( 'entireus-modal-body' );
			const modalClose = document.getElementById( 'entireus-modal-close' );
			const form = document.getElementById( 'entireus-logs-filter' );

			document.querySelectorAll( '.entireus-view-payload' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();

					let raw = this.dataset.payload || '';

					if ( ! raw ) {
						modalBody.textContent = '<?php echo esc_js( __( 'No payload recorded for this entry.', 'entire-user-sync' ) ); ?>';
						modal.style.display = 'flex';
						return;
					}

					try {
						raw = JSON.stringify( JSON.parse( raw ), null, 2 );
					} catch ( err ) {
						// Leave raw as-is if it isn't valid JSON.
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

			if ( form ) {
				form.addEventListener( 'submit', function ( e ) {
					const action  = form.querySelector( 'select[name="action"]' );
					const action2 = form.querySelector( 'select[name="action2"]' );
					const chosen  = ( action && 'delete' === action.value ) || ( action2 && 'delete' === action2.value );

					if ( chosen && ! window.confirm( '<?php echo esc_js( __( 'Delete the selected log entries? This cannot be undone.', 'entire-user-sync' ) ); ?>' ) ) {
						e.preventDefault();
					}
				} );
			}
		}
	)();
</script>