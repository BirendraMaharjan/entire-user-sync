<?php
/**
 * Admin page wrapper template.
 *
 * Renders the shared header, settings-saved notice, and the requested page partial.
 *
 * @package EntireUserSync
 */

defined( 'ABSPATH' ) || exit;

$entireus_updated = isset( $_GET['settings-updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No form submission.
?>
<div class="entire-admin-wrap">
	<!-- Header -->
	<div class="entire-admin-header">
		<div class="entire-admin-header__inner">
			<div class="entire-admin-header__brand">
				<div>
					<h1 class="entire-admin-header__title">
						<?php echo esc_html( $plugin->title() ); ?>
					</h1>
				</div>
			</div>
			<div class="entire-admin-header__meta">
				<span class="entire-admin-badge">
					<?php
					printf(
					/* Translators: %s: Plugin version. */
						esc_html__( 'V %s', 'entire-user-sync' ),
						esc_html( $plugin->version() )
					);
					?>
				</span>
			</div>
		</div>
	</div>

	<!-- Notice -->
	<?php if ( $entireus_updated ) : ?>
		<div class="entire-admin-notice entire-admin-notice--success">
			<strong>
				<?php esc_html_e( 'Settings saved successfully.', 'entire-user-sync' ); ?>
			</strong>
		</div>
	<?php endif; ?>

	<div class="entire-admin-body">
		<?php
		$entireus_file = $plugin->template_path() . '/admin/pages/' . $page . '.php';

		if ( is_readable( $entireus_file ) ) {
			require $entireus_file;
		} else {
			echo '<div class="notice notice-error">' . esc_html__( 'Page not found.', 'entire-user-sync' ) . '</div>';
		}
		?>
	</div><!-- .entire-admin-body -->
</div><!-- .entire-admin-wrap -->
