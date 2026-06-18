<?php

namespace EntireUserSync\Admin\Settings\Sections;

class back {

	public function get(): array {
		return array(
			'configuration' => array(
				'title'  => __( 'Configuration', 'entire-user-sync' ),
				'icon'   => 'dashicons-admin-generic',
				'fields' => array(
					'site_title'  => array(
						'label'   => __( 'Site Title one', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Enter your site title.', 'entire-user-sync' ),
					),
					'tagline_txt' => array(
						'label'   => __( 'Tagline', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Short description of your site.', 'entire-user-sync' ),
					),
				),
			),
			'integrations'  => array(
				'title'  => __( 'Integrations', 'entire-user-sync' ),
				'icon'   => 'dashicons-share',
				'fields' => array(

					'ga_tracking_id'   => array(
						'label'   => __( 'Google Analytics ID', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Format: G-XXXXXXXXXX', 'entire-user-sync' ),
					),

					'gtm_container'    => array(
						'label'   => __( 'GTM Container ID', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Format: GTM-XXXXXX', 'entire-user-sync' ),
					),

					'fb_pixel_id'      => array(
						'label'   => __( 'Facebook Pixel ID', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
					),

					'fb_app_key'       => array(
						'label'   => __( 'Facebook App Key', 'entire-user-sync' ),
						'type'    => 'password',
						'default' => '',
					),

					'smtp_host'        => array(
						'label'   => __( 'SMTP Host', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
					),

					'smtp_port'        => array(
						'label'   => __( 'SMTP Port', 'entire-user-sync' ),
						'type'    => 'number',
						'default' => 587,
						'min'     => 1,
						'max'     => 65535,
					),

					'smtp_user'        => array(
						'label'   => __( 'SMTP Username', 'entire-user-sync' ),
						'type'    => 'email',
						'default' => '',
					),

					'smtp_pass'        => array(
						'label'   => __( 'SMTP Password', 'entire-user-sync' ),
						'type'    => 'password',
						'default' => '',
					),

					'smtp_enc'         => array(
						'label'   => __( 'SMTP Encryption', 'entire-user-sync' ),
						'type'    => 'radio',
						'default' => 'tls',
						'options' => array(
							'none' => __( 'None', 'entire-user-sync' ),
							'ssl'  => 'SSL',
							'tls'  => 'TLS',
						),
					),

					'recaptcha_key'    => array(
						'label'   => __( 'reCAPTCHA Site Key', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
					),

					'recaptcha_secret' => array(
						'label'   => __( 'reCAPTCHA Secret', 'entire-user-sync' ),
						'type'    => 'password',
						'default' => '',
					),

					'stripe_pub_key'   => array(
						'label'   => __( 'Stripe Publishable Key', 'entire-user-sync' ),
						'type'    => 'text',
						'default' => '',
					),

					'stripe_secret'    => array(
						'label'   => __( 'Stripe Secret Key', 'entire-user-sync' ),
						'type'    => 'password',
						'default' => '',
					),

					'stripe_mode'      => array(
						'label'   => __( 'Stripe Mode', 'entire-user-sync' ),
						'type'    => 'radio',
						'default' => 'test',
						'options' => array(
							'test' => __( 'Test', 'entire-user-sync' ),
							'live' => __( 'Live', 'entire-user-sync' ),
						),
					),

					'map_api_key'      => array(
						'label'   => __( 'Google Maps API Key', 'entire-user-sync' ),
						'type'    => 'password',
						'default' => '',
					),

					'social_platforms' => array(
						'label'   => __( 'Social Platforms', 'entire-user-sync' ),
						'type'    => 'multiselect',
						'default' => array(),
						'options' => array(
							'facebook'  => 'Facebook',
							'twitter'   => 'X (Twitter)',
							'instagram' => 'Instagram',
							'linkedin'  => 'LinkedIn',
							'youtube'   => 'YouTube',
						),
					),

					'webhook_url'      => array(
						'label'   => __( 'Webhook URL', 'entire-user-sync' ),
						'type'    => 'url',
						'default' => '',
						'desc'    => __( 'Receives POST on events.', 'entire-user-sync' ),
					),

					'webhook_events'   => array(
						'label'   => __( 'Webhook Events', 'entire-user-sync' ),
						'type'    => 'multiselect',
						'default' => array(),
						'options' => array(
							'user_register' => __( 'User Register', 'entire-user-sync' ),
							'order_new'     => __( 'New Order', 'entire-user-sync' ),
							'post_publish'  => __( 'Post Publish', 'entire-user-sync' ),
						),
					),

					'api_notes'        => array(
						'label'   => __( 'Integration Notes', 'entire-user-sync' ),
						'type'    => 'textarea',
						'default' => '',
						'rows'    => 3,
					),

					'enable_zapier'    => array(
						'label'   => __( 'Enable Zapier', 'entire-user-sync' ),
						'type'    => 'checkbox',
						'default' => '0',
					),
				),
			),
		);
	}
}
