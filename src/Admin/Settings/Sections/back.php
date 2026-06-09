<?php

namespace EntireUserSync\Admin\Settings\Sections;

class back {

	public function get(): array {
		return array(
			'configuration' => array(
				'title'  => __( 'Configuration', 'your-plugin' ),
				'icon'   => 'dashicons-admin-generic',
				'fields' => array(
					'site_title' => array(
						'label'   => __( 'Site Title one', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Enter your site title.', 'your-plugin' ),
					),
					'tagline_txt' => array(
						'label'   => __( 'Tagline', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Short description of your site.', 'your-plugin' ),
					),
				),
			),
			'integrations' => array(
				'title'  => __( 'Integrations', 'your-plugin' ),
				'icon'   => 'dashicons-share',
				'fields' => array(

					'ga_tracking_id' => array(
						'label'   => __( 'Google Analytics ID', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Format: G-XXXXXXXXXX', 'your-plugin' ),
					),

					'gtm_container' => array(
						'label'   => __( 'GTM Container ID', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
						'desc'    => __( 'Format: GTM-XXXXXX', 'your-plugin' ),
					),

					'fb_pixel_id' => array(
						'label'   => __( 'Facebook Pixel ID', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
					),

					'fb_app_key' => array(
						'label'   => __( 'Facebook App Key', 'your-plugin' ),
						'type'    => 'password',
						'default' => '',
					),

					'smtp_host' => array(
						'label'   => __( 'SMTP Host', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
					),

					'smtp_port' => array(
						'label'   => __( 'SMTP Port', 'your-plugin' ),
						'type'    => 'number',
						'default' => 587,
						'min'     => 1,
						'max'     => 65535,
					),

					'smtp_user' => array(
						'label'   => __( 'SMTP Username', 'your-plugin' ),
						'type'    => 'email',
						'default' => '',
					),

					'smtp_pass' => array(
						'label'   => __( 'SMTP Password', 'your-plugin' ),
						'type'    => 'password',
						'default' => '',
					),

					'smtp_enc' => array(
						'label'   => __( 'SMTP Encryption', 'your-plugin' ),
						'type'    => 'radio',
						'default' => 'tls',
						'options' => array(
							'none' => __( 'None', 'your-plugin' ),
							'ssl'  => 'SSL',
							'tls'  => 'TLS',
						),
					),

					'recaptcha_key' => array(
						'label'   => __( 'reCAPTCHA Site Key', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
					),

					'recaptcha_secret' => array(
						'label'   => __( 'reCAPTCHA Secret', 'your-plugin' ),
						'type'    => 'password',
						'default' => '',
					),

					'stripe_pub_key' => array(
						'label'   => __( 'Stripe Publishable Key', 'your-plugin' ),
						'type'    => 'text',
						'default' => '',
					),

					'stripe_secret' => array(
						'label'   => __( 'Stripe Secret Key', 'your-plugin' ),
						'type'    => 'password',
						'default' => '',
					),

					'stripe_mode' => array(
						'label'   => __( 'Stripe Mode', 'your-plugin' ),
						'type'    => 'radio',
						'default' => 'test',
						'options' => array(
							'test' => __( 'Test', 'your-plugin' ),
							'live' => __( 'Live', 'your-plugin' ),
						),
					),

					'map_api_key' => array(
						'label'   => __( 'Google Maps API Key', 'your-plugin' ),
						'type'    => 'password',
						'default' => '',
					),

					'social_platforms' => array(
						'label'   => __( 'Social Platforms', 'your-plugin' ),
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

					'webhook_url' => array(
						'label'   => __( 'Webhook URL', 'your-plugin' ),
						'type'    => 'url',
						'default' => '',
						'desc'    => __( 'Receives POST on events.', 'your-plugin' ),
					),

					'webhook_events' => array(
						'label'   => __( 'Webhook Events', 'your-plugin' ),
						'type'    => 'multiselect',
						'default' => array(),
						'options' => array(
							'user_register' => __( 'User Register', 'your-plugin' ),
							'order_new'     => __( 'New Order', 'your-plugin' ),
							'post_publish'  => __( 'Post Publish', 'your-plugin' ),
						),
					),

					'api_notes' => array(
						'label'   => __( 'Integration Notes', 'your-plugin' ),
						'type'    => 'textarea',
						'default' => '',
						'rows'    => 3,
					),

					'enable_zapier' => array(
						'label'   => __( 'Enable Zapier', 'your-plugin' ),
						'type'    => 'checkbox',
						'default' => '0',
					),
				),
			),
		);
	}
}
