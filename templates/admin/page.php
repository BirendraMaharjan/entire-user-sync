<?php

defined( 'ABSPATH' ) || exit;

$updated = isset( $_GET['settings-updated'] );

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
						esc_html__( 'V %s', 'entire-account-manager' ),
						esc_html( $plugin->version() )
					);
					?>
				</span>
			</div>
		</div>
	</div>

	<!-- Notice -->
	<?php if ( $updated ) : ?>
		<div class="entire-admin-notice entire-admin-notice--success">
			<strong><?php esc_html_e( 'Settings saved successfully.', 'entire-account-manager' ); ?></strong>
		</div>
	<?php endif; ?>

	<div class="entire-admin-body">
		<?php
		$file = $plugin->template_path() . '/admin/pages/' . $page . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		} else {
			echo '<div class="notice notice-error">Page not found</div>';
		}
		?>
	</div><!-- .entire-admin-body -->
</div><!-- .entire-admin-wrap -->
