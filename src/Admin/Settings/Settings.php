<?php

namespace EntireUserSync\Admin\Settings;

use EntireUserSync\Common\Abstracts\Base;

defined( 'ABSPATH' ) || exit;

class Settings extends Base {

	public string $setting_option_name;

	private array $sections;

	public function __construct() {
		parent::__construct();

		$this->setting_option_name = $this->plugin->prefix();

		$this->init_hooks();
	}

	private function init_hooks(): void {

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_entire_reset_section', array( $this, 'ajax_reset_section' ) );
	}

	public function sections(): array {
		if ( empty( $this->sections ) ) {
			$this->sections = $this->get_sections();
		}

		return $this->sections;
	}

	public function get_sections() {
		return array_merge(
			( new Sections\Setup() )->get_section(),
			( new Sections\Configuration() )->get_section(),
			( new Sections\Integrations() )->get_section(),
		);
	}

	public function register_settings() {
		foreach ( $this->sections() as $section_key => $section ) {
			$option_name = $this->setting_option_name . '_' . $section_key;
			$group       = $option_name . '_group';

			register_setting(
				$group,
				$option_name,
				array(
					'sanitize_callback' => array( $this, 'sanitize_section' ),
				)
			);

			add_settings_section(
				$section_key,
				'',
				'__return_false',
				$option_name
			);

			foreach ( $section['fields'] as $field_key => $field ) {
				add_settings_field(
					$field_key,
					$field['label'],
					array( $this, 'render_field' ),
					$option_name,
					$section_key,
					array(
						'section'     => $section_key,
						'field_key'   => $field_key,
						'field'       => $field,
						'option_name' => $option_name,
						'label_for'   => in_array(
							$field['type'],
							array(
								'radio',
								'multiselect',
								'checkbox',
								'wysiwyg',
								'image',
							),
							true
						) ?
							false :
							$field_key,
					)
				);
			}
		}
	}

	public function sanitize_section( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$section_key = $this->get_current_section();

		if ( ! isset( $this->sections()[ $section_key ]['fields'] ) ) {
			return array();
		}

		$fields    = $this->sections()[ $section_key ]['fields'];
		$sanitized = array();

		foreach ( $fields as $field_key => $field ) {
			$type  = $field['type'] ?? 'text';
			$value = $input[ $field_key ] ?? null;

			$sanitized[ $field_key ] = $this->sanitize_field( $value, $type, $field );
		}

		return $sanitized;
	}

	private function sanitize_field( $value, $type, $field ) {
		switch ( $type ) {

			case 'checkbox':
				return isset( $value ) && '1' === (string) $value ? '1' : '0';

			case 'radio':
			case 'select':
				if ( empty( $field['options'] ) ) {
					return sanitize_text_field( $value );
				}
				$allowed = array_keys( $field['options'] );

				return in_array( $value, $allowed, true ) ? $value : $field['default'];
			case 'multiselect':
				if ( ! is_array( $value ) || empty( $field['options'] ) ) {
					return array();
				}
				$allowed = array_keys( $field['options'] );

				return array_values(
					array_filter(
						$value,
						function ( $v ) use ( $allowed ) {
							return in_array( $v, $allowed, true );
						}
					)
				);

			case 'multiselect_grouped':
				if ( ! is_array( $value ) ) {
					return array();
				}
				// Flatten all grouped options into one allowed list
				$allowed = array();
				foreach ( $field['options'] as $group_options ) {
					$allowed = array_merge( $allowed, array_keys( $group_options ) );
				}

				return array_values(
					array_filter(
						$value,
						fn( $v ) => in_array( $v, $allowed, true )
					)
				);

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'wysiwyg':
				return wp_kses_post( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'email':
				return sanitize_email( $value );

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return isset( $field['default'] ) ? $field['default'] : 0;
				}
				$number = $value + 0;
				if ( isset( $field['min'] ) && $number < $field['min'] ) {
					$number = $field['min'];
				}
				if ( isset( $field['max'] ) && $number > $field['max'] ) {
					$number = $field['max'];
				}

				return $number;

			case 'color':
				return sanitize_hex_color( $value );

			case 'image':
				return absint( $value );

			case 'password':
				return '' !== trim( (string) $value ) ? sanitize_text_field( $value ) : $value;
			case 'repeater':
				if ( ! is_array( $value ) ) {
					return array();
				}

				$sub_fields = $field['sub_fields'] ?? array();
				$sanitized  = array();

				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$clean_row = array();
					foreach ( $sub_fields as $sub_key => $sub_field ) {
						$sub_value             = $row[ $sub_key ] ?? '';
						$clean_row[ $sub_key ] = $this->sanitize_field( $sub_value, $sub_field['type'], $sub_field );
					}

					if ( array_filter( $clean_row ) ) {
						$sanitized[] = $clean_row;
					}
				}

				return $sanitized;

			default:
				return sanitize_text_field( $value );
		}
	}

	public function sanitize( $input, $type = 'text' ): mixed {
		if ( is_array( $input ) || 'array' === $type ) {
			return array_map( array( $this, 'sanitize' ), (array) $input );
		}

		$map = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'file'     => 'sanitize_file_name',
			'class'    => 'sanitize_html_class',
			'email'    => 'sanitize_email',
			'key'      => 'sanitize_key',
			'title'    => 'sanitize_title',
			'user'     => 'sanitize_user',
		);

		$fn = $map[ $type ] ?? 'sanitize_text_field';

		return $fn( $input );
	}

	private function get_current_section() {
		$group = isset( $_POST['option_page'] ) ? sanitize_key( $_POST['option_page'] ) : '';

		$group = str_replace( $this->setting_option_name . '_', '', $group );

		return str_replace( '_group', '', $group );
	}


	public function render_field( $args ) {
		$option_name = $args['option_name'];
		$field_key   = $args['field_key'];
		$field       = $args['field'];
		$options     = get_option( $option_name, array() );
		$value       = $options[ $field_key ] ?? $field['default'];
		$type        = $field['type'] ?? 'text';
		$required    = ! empty( $field['required'] ) ? 'required' : '';
		$name        = esc_attr( $option_name ) . '[' . esc_attr( $field_key ) . ']';
		$id          = esc_attr( $field_key );

		switch ( $type ) {

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					isset( $field['rows'] ) ? absint( $field['rows'] ) : 5,
					esc_textarea( $value )
				);
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, '1', false ),
					isset( $field['checkbox_label'] ) ? esc_html( $field['checkbox_label'] ) : esc_html__( 'Enable', 'entire-user-sync' )
				);
				break;

			case 'radio':
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<label style="display:block;margin-bottom:5px;">
							<input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s
						</label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						checked( $value, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				break;

			case 'select':
				printf(
					'<select id="%1$s" name="%2$s">',
					esc_attr( $id ),
					esc_attr( $name )
				);
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						selected( $value, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'multiselectDefault':
				$value = is_array( $value ) ? $value : array();
				printf(
					'<select id="%1$s" name="%2$s[]" multiple="multiple" size="%3$d" style="min-width:200px;">',
					esc_attr( $id ),
					esc_attr( $name ),
					isset( $field['size'] ) ? absint( $field['size'] ) : 5
				);
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						in_array( $opt_value, $value, true ) ? 'selected="selected"' : '',
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;
			case 'multiselect':
				$value = is_array( $value ) ? $value : array();

				printf(
					'<select id="%s" name="%s[]" multiple="multiple" class="entire-select2" style="width:100%%;max-width:25em;" data-placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : esc_attr__( 'Select options...', 'entire-user-sync' )
				);

				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt_val ),
						in_array( $opt_val, $value, true ) ? 'selected="selected"' : '',
						esc_html( $opt_label )
					);
				}

				echo '</select>';
				break;
			case 'multiselect_grouped':
				$value = is_array( $value ) ? $value : array();

				printf(
					'<select id="%s" name="%s[]" multiple="multiple" class="entire-select2" style="width:100%%;max-width:25em;" data-placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : esc_attr__( 'Select options...', 'entire-user-sync' )
				);

				foreach ( $field['options'] as $group_label => $group_options ) {
					printf( '<optgroup label="%s">', esc_attr( $group_label ) );

					foreach ( $group_options as $opt_val => $opt_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $opt_val ),
							in_array( $opt_val, $value, true ) ? 'selected="selected"' : '',
							esc_html( $opt_label )
						);
					}

					echo '</optgroup>';
				}

				echo '</select>';
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					isset( $field['min'] ) ? esc_attr( $field['min'] ) : '0',
					isset( $field['max'] ) ? esc_attr( $field['max'] ) : '',
					isset( $field['step'] ) ? esc_attr( $field['step'] ) : '1'
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_url( $value )
				);
				break;

			case 'password':
				printf(
					'<input type="password" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="new-password" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'color':
				printf(
					'<input type="color" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'wysiwyg':
				wp_editor(
					wp_kses_post( $value ),
					$id,
					array(
						'textarea_name' => $name,
						'textarea_rows' => isset( $field['rows'] ) ? absint( $field['rows'] ) : 10,
						'media_buttons' => isset( $field['media_buttons'] ) && (bool) $field['media_buttons'],
					)
				);
				break;

			case 'image':
				$attachment_id = absint( $value );
				$img_url       = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
				?>
				<div class="entire-image-field" data-field="<?php echo esc_attr( $id ); ?>">
					<input
						type="hidden"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $attachment_id ); ?>"
					/>

					<div class="entire-image-preview">
						<?php if ( $img_url ) : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt=""/>
						<?php endif; ?>
					</div>

					<button
						type="button"
						class="button entire-upload-image"
						data-field="<?php echo esc_attr( $id ); ?>"
					>
						<?php esc_html_e( 'Upload Image', 'entire-user-sync' ); ?>
					</button>

					<?php if ( $attachment_id ) : ?>
						<button
							type="button"
							class="button entire-remove-image"
							data-field="<?php echo esc_attr( $id ); ?>"
						>
							<?php esc_html_e( 'Remove', 'entire-user-sync' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<?php
				break;
			case 'repeater':
				$rows       = is_array( $value ) ? $value : array();
				$sub_fields = $field['sub_fields'] ?? array();
				$name_base  = $option_name . '[' . $field_key . ']';
				?>

				<div class="entire-repeater" data-name-base="<?php echo esc_attr( $name_base ); ?>">

					<div class="entire-repeater-rows">
						<?php foreach ( $rows as $i => $row ) : ?>
							<div class="entire-repeater-row">
								<?php foreach ( $sub_fields as $sub_key => $sub_field ) : ?>
									<div class="entire-repeater-col">
										<label><?php echo esc_html( $sub_field['label'] ); ?></label>
										<?php
										$this->render_repeater_input(
											$sub_field,
											$name_base . '[' . $i . '][' . $sub_key . ']',
											$row[ $sub_key ] ?? ''
										);
										?>
									</div>
								<?php endforeach; ?>
								<button type="button" class="button entire-remove-row">&#x2715;</button>
							</div>
						<?php endforeach; ?>
					</div>

					<button
						type="button"
						class="button entire-add-row"
						data-sub-fields="<?php echo esc_attr( wp_json_encode( $sub_fields ) ); ?>"
					>
						+ <?php echo esc_html( $field['add_label'] ?? __( 'Add Row', 'entire-user-sync' ) ); ?>
					</button>

				</div>

				<?php
				break;
			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" %5$s/>',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $required )
				);
				break;
		}

		if ( ! empty( $field['desc'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['desc'] ) );
		}
	}

	private function render_repeater_input( $sub_field, $name, $value ) {
		$type        = isset( $sub_field['type'] ) ? $sub_field['type'] : 'text';
		$placeholder = isset( $sub_field['placeholder'] ) ? $sub_field['placeholder'] : '';

		switch ( $type ) {

			case 'url':
				printf(
					'<input type="url" name="%s" value="%s" class="regular-text" placeholder="%s" />',
					esc_attr( $name ),
					esc_url( $value ),
					esc_attr( $placeholder )
				);
				break;

			case 'email':
				printf(
					'<input type="email" name="%s" value="%s" class="regular-text" placeholder="%s" />',
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $placeholder )
				);
				break;

			case 'select':
				printf( '<select name="%s">', esc_attr( $name ) );
				foreach ( $sub_field['options'] as $opt_val => $opt_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt_val ),
						selected( $opt_val, $value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" name="%s" value="1" %s />',
					esc_attr( $name ),
					checked( '1', $value, false )
				);
				break;

			case 'textarea':
				printf(
					'<textarea name="%s" rows="%d" class="large-text" placeholder="%s">%s</textarea>',
					esc_attr( $name ),
					isset( $sub_field['rows'] ) ? absint( $sub_field['rows'] ) : 3,
					esc_attr( $placeholder ),
					esc_textarea( $value )
				);
				break;

			default:
				printf(
					'<input type="%s" name="%s" value="%s" class="regular-text" placeholder="%s" />',
					esc_attr( $type ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $placeholder )
				);
				break;
		}
	}

	public function ajax_reset_section() {

		check_ajax_referer( 'entire_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'entire-user-sync' ) ) );
		}

		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';

		if ( ! isset( $this->sections()[ $section ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid section.', 'entire-user-sync' ) ) );
		}

		$defaults = array();

		foreach ( $this->sections()[ $section ]['fields'] as $key => $field ) {
			$defaults[ $key ] = $field['default'] ?? '';
		}

		update_option( $this->setting_option_name . '_' . $section, $defaults );

		wp_send_json_success( array( 'defaults' => $defaults ) );
	}

	public function navigation() {
		$active_tab = sanitize_key( $_GET['tab'] ?? array_key_first( $this->sections() ) );
		?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $this->sections() as $key => $section ) : ?>
				<a
					href="<?php echo esc_url( $this->get_tab_url( $key ) ); ?>"
					class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>"
				>
					<?php if ( ! empty( $section['icon'] ) ) : ?>
						<span class="<?php echo esc_attr( $section['icon'] ); ?>"></span>
					<?php endif; ?>

					<?php echo esc_html( $section['title'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public function get_tab_url( string $tab ): string {
		$page = sanitize_key( $_GET['page'] ?? '' );

		return add_query_arg(
			array(
				'page' => $page,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get a field value with fallback chain:
	 *   1. DB value  (even if falsy: 0, '', false)
	 *   2. $default param (if passed)
	 *   3. Field definition 'default'
	 *   4. null
	 *
	 * Usage:
	 *   $this->get( 'setup', 'enable' );
	 *   $this->get( 'setup', 'enable', false );
	 */
	public function get( string $section, string $field, mixed $default = '__UNSET__' ): mixed {

		$option_name = $this->setting_option_name . '_' . $section;
		$option      = get_option( $option_name );   // false if never saved

		// Something in DB for this section
		if ( is_array( $option ) && array_key_exists( $field, $option ) ) {
			return $option[ $field ];
		}

		// Explicit fallback passed
		if ( $default !== '__UNSET__' ) {
			return $default;
		}

		// Fall back to field definition default
		return $this->sections()[ $section ]['fields'][ $field ]['default'] ?? null;
	}

	/**
	 * Save a single field into its section option.
	 *
	 * Usage:
	 *   $this->set( 'setup', 'enable', '1' );
	 */
	public function set( string $section, string $field, mixed $value ): bool {

		if ( ! isset( $this->sections()[ $section ] ) ) {
			return false;
		}

		$option_name      = $this->setting_option_name . '_' . $section;
		$option           = get_option( $option_name, array() );
		$option           = is_array( $option ) ? $option : array();
		$option[ $field ] = $value;

		return update_option( $option_name, $option );
	}
}
