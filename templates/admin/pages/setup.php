<?php
/**
 * Admin configuration page template.
 *
 * @package EntireUserSync
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="entire-admin-navigation">
	<?php $setting->navigation() ?>
</div>

<!-- Content -->
<div class="entire-admin-content">
	<?php foreach ( $sections as $section_key => $section ) : ?>
		<div
			class="entire-setting-section <?php echo $active_tab === $section_key ? 'is-active' : ''; ?>"
			data-section="<?php echo esc_attr( $section_key ); ?>"
		>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( $setting->setting_option_name . '_' . $section_key . '_group' ); ?>
				<?php do_settings_sections( $setting->setting_option_name . '_' . $section_key ); ?>

				<div class="entire-section-footer">
					<?php submit_button( __( 'Save Changes', 'entire-user-sync' ), 'primary', 'submit', false ); ?>

					<button
						type="button"
						class="button button-secondary entire-setting-reset"
						data-section="<?php echo esc_attr( $section_key ); ?>"
					>
						<span class="dashicons dashicons-image-rotate"></span>
						<?php esc_html_e( 'Reset Default', 'entire-user-sync' ); ?>
					</button>

					<span class="entire-setting-reset-notice"></span>
				</div>
			</form>

		</div>
	<?php endforeach; ?>

</div><!-- .entire-admin-content -->