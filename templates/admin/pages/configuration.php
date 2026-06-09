<?php
/**
 * Admin configuration page template.
 *
 * @package EntireUserSync
 */
?>
<div class="entire-admin-navigation">
	<nav class="nav-tab-wrapper wp-clearfix">
		<?php foreach ( $sections as $key => $section ) : ?>
			<a
				href="<?php echo esc_url( $setting->get_tab_url( $key ) ); ?>"
				class="nav-tab<?php echo $active_tab === $key ? ' nav-tab-active' : ''; ?>"
			>
				<?php if ( ! empty( $section['icon'] ) ) : ?>
					<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>"></span>
				<?php endif; ?>

				<?php echo esc_html( $section['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
</div>

<!-- Content -->
<div class="entire-admin-content">
	<?php foreach ( $sections as $section_key => $section ) : ?>
		<div
			class="your-plugin-section<?php echo $active_tab === $section_key ? ' is-active' : ''; ?>"
			data-section="<?php echo esc_attr( $section_key ); ?>"
		>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( $plugin->prefix() . '_' . $section_key . '_group' ); ?>
				<?php do_settings_sections( 'your-plugin-settings-' . $section_key ); ?>

				<div class="your-plugin-section-footer">
					<?php submit_button( __( 'Save Changes', 'your-plugin' ), 'primary', 'submit', false ); ?>

					<button
						type="button"
						class="button button-secondary your-plugin-reset"
						data-section="<?php echo esc_attr( $section_key ); ?>"
					>
						<span class="dashicons dashicons-image-rotate"></span>
						<?php esc_html_e( 'Reset Default', 'your-plugin' ); ?>
					</button>

					<span class="your-plugin-reset-notice"></span>
				</div>
			</form>

		</div>
	<?php endforeach; ?>

</div><!-- .entire-admin-content -->