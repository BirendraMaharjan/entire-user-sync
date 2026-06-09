<?php

namespace EntireUserSync\Admin\Settings\Sections;

class Integrations {

	public function get(): array {
		return array(
			'integrations' => array(
				'title'  => __( 'Integrations', 'entire-user-sync' ),
				'icon'   => 'dashicons-share',
				'fields' => array(

					'secret_key' => array(
						'label'   => __( 'Secret Key', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
					),
				),
			),
		);
	}
}
