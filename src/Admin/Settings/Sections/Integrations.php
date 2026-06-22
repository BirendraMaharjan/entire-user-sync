<?php
/**
 * Integrations section definitions (API secrets etc.).
 *
 * @package EntireUserSync\Admin\Settings
 */

namespace EntireUserSync\Admin\Settings\Sections;

/**
 * Integrations settings section provider.
 *
 * @package EntireUserSync\Admin\Settings
 */
class Integrations {

	/**
	 * Return the integrations section.
	 *
	 * @return array
	 */
	public function get_section(): array {
		return array(
			'integrations' => array(
				'title'  => __( 'Integrations', 'entire-user-sync' ),
				'icon'   => 'dashicons dashicons-share',
				'fields' => $this->fields(),
			),
		);
	}

	/**
	 * Field definitions for integrations.
	 *
	 * @return array
	 */
	public function fields(): array {
		return array(
			'secret_key' => array(
				'label'    => __( 'Secret Key', 'entire-user-sync' ),
				'type'     => 'text',
				'default'  => ENTIREUS_SECURITY_KEY,
				'required' => true,
			),
		);
	}
}
