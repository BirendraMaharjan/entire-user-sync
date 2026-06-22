<?php

namespace EntireUserSync\Admin\Settings\Sections;

class Setup {

	public function get_section(): array {
		return array(
			'setup' => array(
				'title'  => __( 'Setup', 'entire-user-sync' ),
				'icon'   => 'dashicons dashicons-admin-generic',
				'fields' => $this->fields(),
			),
		);
	}

	public function fields(): array {
		return array(
			'sync_enable' => array(
				'label'   => __( 'Enable Sync', 'entire-user-sync' ),
				'type'    => 'checkbox',
				'default' => '1',
			),
			'sites'       => array(
				'label'      => __( 'Target Sites', 'entire-user-sync' ),
				'type'       => 'repeater',
				'default'    => array(),
				'add_label'  => __( 'Add Site', 'entire-user-sync' ),
				'desc'       => __( 'Add each remote WordPress site to sync users to.', 'entire-user-sync' ),
				'sub_fields' => array(
					'label'  => array(
						'label'       => __( 'Label', 'entire-user-sync' ),
						'type'        => 'text',
						'placeholder' => __( 'Site Name', 'entire-user-sync' ),
					),
					'url'    => array(
						'label'       => __( 'URL', 'entire-user-sync' ),
						'type'        => 'url',
						'placeholder' => 'https://example.com',
					),
					'active' => array(
						'label' => __( 'Active', 'entire-user-sync' ),
						'type'  => 'checkbox',
					),
					'sso'    => array(
						'label' => __( 'Sign-On', 'entire-user-sync' ),
						'type'  => 'checkbox',
					),
				),
			),
		);
	}
}
