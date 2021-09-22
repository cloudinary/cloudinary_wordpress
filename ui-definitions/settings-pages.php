<?php
/**
 * Defines the settings structure for the main pages.
 *
 * @package Cloudinary
 */

$media    = $this->get_component( 'media' );
$settings = array(
	'dashboard'      => array(
		'page_title'          => __( 'Cloudinary Dashboard', 'cloudinary' ),
		'menu_title'          => __( 'Dashboard', 'cloudinary' ),
		'priority'            => 1,
		'requires_connection' => true,
		'sidebar'             => true,
		array(
			'type' => 'panel',
			array(
				'type'  => 'plan',
				'title' => __( 'Your Current Plan', 'cloudinary' ),
			),
			array(
				'type'    => 'link',
				'url'     => 'https://cloudinary.com/console/lui/upgrade_options',
				'content' => __( 'Upgrade Plan', 'cloudinary' ),
			),
		),
		array(
			'type' => 'panel',
			array(
				'type'  => 'plan_status',
				'title' => __( 'Your Plan Status', 'cloudinary' ),
			),
		),
		array(
			'type' => 'panel_short',
			array(
				'type'  => 'media_status',
				'title' => __( 'Your Media Sync Status', 'cloudinary' ),
			),
		),
	),
	'connect'        => array(
		'page_title' => __( 'General settings', 'cloudinary' ),
		'menu_title' => __( 'General settings', 'cloudinary' ),
		'priority'   => 5,
		'sidebar'    => true,
		'settings'   => array(
			array(
				'title'       => __( 'Connect to Cloudinary!', 'cloudinary' ),
				'type'        => 'panel',
				'option_name' => 'connect',
				array(
					'content' => __( 'You need to connect your Cloudinary account to WordPress by adding your unique connection string. See below for where to find this.', 'cloudinary' ),
				),
				array(
					'placeholder'  => 'cloudinary://API_KEY:API_SECRET@CLOUD_NAME',
					'slug'         => \Cloudinary\Connect::META_KEYS['url'],
					'title'        => __( 'Connection string', 'cloudinary' ),
					'tooltip_text' => __(
						'The connection string is made up of your Cloudinary Cloud name, API Key and API Secret and known as the API Environment Variable. This authenticates the Cloudinary WordPress plugin with your Cloudinary account.',
						'cloudinary'
					),
					'type'         => 'text',
					'attributes'   => array(
						'class' => array(
							'connection-string',
						),
					),
				),
			),
		),
	),
	'image_settings' => array(
		'page_title'          => __( 'Image settings', 'cloudinary' ),
		'menu_title'          => __( 'Image settings', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-image.php',
	),
	'video_settings' => array(
		'page_title'          => __( 'Video settings', 'cloudinary' ),
		'menu_title'          => __( 'Video settings', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-video.php',
	),
	'gallery'        => array(),
	'lazy_loading'   => array(),
	'responsive'     => array(
		'page_title'          => __( 'Responsive', 'cloudinary' ),
		'menu_title'          => __( 'Responsive', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => array(
			array(
				'type'        => 'panel',
				'title'       => __( 'Image breakpoints', 'cloudinary' ),
				'option_name' => 'media_display',
				array(
					'type' => 'row',
					array(
						'type' => 'column',
						array(
							'type'               => 'on_off',
							'slug'               => 'enable_breakpoints',
							'title'              => __( 'Breakpoints', 'cloudinary' ),
							'optimisation_title' => __( 'Responsive breakpoints', 'cloudinary' ),
							'tooltip_text'       => __(
								'Automatically generate multiple sizes based on the configured breakpoints to enable your images to responsively adjust to different screen sizes. Note that your Cloudinary usage will increase when enabling responsive images.',
								'cloudinary'
							),
							'description'        => __( 'Enable responsive images.', 'cloudinary' ),
							'default'            => 'off',
						),
						array(
							'type'  => 'info_box',
							'icon'  => $this->dir_url . 'css/images/responsive.svg',
							'title' => __( 'What are Image breakpoints', 'cloudinary' ),
							'text'  => __(
								'Cloudinary allows you to easily transform your images on-the-fly to any required format, style and dimension, and also optimizes images for minimal file size.',
								'cloudinary'
							),
						),
						array(
							'type'      => 'group',
							'condition' => array(
								'enable_breakpoints' => true,
							),
							array(
								'type'         => 'number',
								'slug'         => 'pixel_step',
								'priority'     => 9,
								'title'        => __( 'Breakpoints distance', 'cloudinary' ),
								'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
								'suffix'       => __( 'px', 'cloudinary' ),
								'attributes'   => array(
									'step' => 50,
									'min'  => 50,
								),
								'default'      => 100,
							),
							array(
								'type'         => 'number',
								'slug'         => 'breakpoints',
								'title'        => __( 'Max breakpoints', 'cloudinary' ),
								'tooltip_text' => __(
									'The maximum number of images to be generated when delivering responsive images. For some images, the responsive algorithm may determine that the ideal number of breakpoints is smaller than the value you specify.',
									'cloudinary'
								),
								'suffix'       => __( 'Valid values: 3-200', 'cloudinary' ),
								'default'      => 3,
								'attributes'   => array(
									'min' => 3,
									'max' => 200,
								),
							),
							array(
								'type'         => 'select',
								'slug'         => 'dpr',
								'priority'     => 8,
								'title'        => __( 'DPR settings', 'cloudinary' ),
								'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
								'default'      => 'auto',
								'options'      => array(
									'off'  => __( 'None', 'cloudinary' ),
									'auto' => __( 'Auto', 'cloudinary' ),
									'2'    => __( '2X', 'cloudinary' ),
									'3'    => __( '3X', 'cloudinary' ),
									'4'    => __( '4X', 'cloudinary' ),
								),
							),
							array(
								'type'         => 'number',
								'slug'         => 'max_width',
								'title'        => __( 'Image width limit', 'cloudinary' ),
								'tooltip_text' => __(
									'The minimum and maximum width of an image created as a breakpoint. Leave max as empty to auto detect based on largest registered size in WordPress.',
									'cloudinary'
								),
								'prefix'       => __( 'Max', 'cloudinary' ),
								'suffix'       => __( 'px', 'cloudinary' ),
								'default'      => $media->default_max_width(),
								'attributes'   => array(
									'step'         => 50,
									'data-default' => $media->default_max_width(),
								),
							),
							array(
								'type'       => 'number',
								'slug'       => 'min_width',
								'prefix'     => __( 'Min', 'cloudinary' ),
								'suffix'     => __( 'px', 'cloudinary' ),
								'default'    => 800,
								'attributes' => array(
									'step' => 50,
								),
							),
						),
					),
					array(
						'type'  => 'column',
						'class' => array(
							'cld-ui-preview',
						),
						array(
							'type'    => 'breakpoints_preview',
							'title'   => __( 'Preview', 'cloudinary' ),
							'slug'    => 'breakpoints_preview',
							'default' => CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE . 'w_600/sample.jpg',
						),
					),
				),
			),
		),
	),
	'help'           => array(
		'page_title' => __( 'Need help?', 'cloudinary' ),
		'menu_title' => __( 'Need help?', 'cloudinary' ),
		'priority'   => 50,
		'sidebar'    => true,
		array(
			'type'    => 'panel',
			'title'   => __( 'Help Centre', 'cloudinary' ),
			'content' => __( 'How can we help', 'cloudinary' ),
		),
	),
);

return apply_filters( 'cloudinary_admin_pages', $settings );
