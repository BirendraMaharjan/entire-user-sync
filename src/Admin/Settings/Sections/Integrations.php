<?php

namespace EntireUserSync\Admin\Settings\Sections;

class Integrations {

	public function get_section(): array {
		return array(
			'integrations' => array(
				'title'  => __( 'Integrations', 'entire-user-sync' ),
				'icon'   => 'dashicons dashicons-share',
				'fields' => $this->fields(),
			),
		);
	}

	public function fields(): array {
		return array(
			'secret_key' => array(
				'label'   => __( 'Secret Key', 'entire-user-sync' ),
				'type'    => 'text',
				'default' => ENTIRE_USER_SYNC_SECURITY_KEY,
				'required' => true,
			),
			'image' => array(
				'label'   => __( 'Secret Key', 'entire-user-sync' ),
				'type'    => 'image',
				'default' => ENTIRE_USER_SYNC_SECURITY_KEY,
				'required' => true,
			),
		);
	}
}
