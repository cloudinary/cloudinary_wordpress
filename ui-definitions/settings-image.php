<?php
/**
 * Defines the settings structure for images.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'   => 'panel',
		'title'  => __( 'Image - Global Settings', 'cloudinary' ),
		'icon'   => $this->plugin->dir_url . 'css/image.svg',
		'slug'   => 'image_settings',
		'anchor' => true,
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-45',
				),
				array(
					'type' => 'group',
					array(
						'type'         => 'on_off',
						'slug'         => 'image_optimization',
						'title'        => __( 'Image optimization', 'cloudinary' ),
						'tooltip_text' => __( 'Images will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.', 'cloudinary' ),
						'description'  => __( 'Optimize images on my site.', 'cloudinary' ),
						'default'      => 'on',
						'attributes'   => array(
							'data-context' => 'image',
						),
					),
				),
				array(
					'type'        => 'group',
					'title'       => __( 'Advanced optimization', 'cloudinary' ),
					'collapsible' => 'open',
					'condition'   => array(
						'image_optimization' => true,
					),
					array(
						'type'         => 'select',
						'slug'         => 'image_format',
						'title'        => __( 'Image format', 'cloudinary' ),
						'tooltip_text' => __( "The image format to use for delivery. Leave as Auto to automatically deliver the most optimal format based on the user's browser and device.", 'cloudinary' ),
						'default'      => 'auto',
						'options'      => array(
							'none' => __( 'Not set', 'cloudinary' ),
							'auto' => __( 'Auto', 'cloudinary' ),
							'png'  => __( 'PNG', 'cloudinary' ),
							'jpg'  => __( 'JPG', 'cloudinary' ),
							'gif'  => __( 'GIF', 'cloudinary' ),
							'webp' => __( 'WebP', 'cloudinary' ),
						),
						'attributes'   => array(
							'data-context' => 'image',
							'data-meta'    => 'f',
						),
					),
					array(
						'type'         => 'select',
						'slug'         => 'image_quality',
						'title'        => __( 'Image quality', 'cloudinary' ),
						'tooltip_text' => __( 'The compression quality to apply when delivering images. Leave as Auto to apply an algorithm that finds the best tradeoff between visual quality and file size.', 'cloudinary' ),
						'default'      => 'auto',
						'options'      => array(
							'none'      => __( 'Not set', 'cloudinary' ),
							'auto'      => __( 'Auto', 'cloudinary' ),
							'auto:best' => __( 'Auto best', 'cloudinary' ),
							'auto:good' => __( 'Auto good', 'cloudinary' ),
							'auto:eco'  => __( 'Auto eco', 'cloudinary' ),
							'auto:low'  => __( 'Auto low', 'cloudinary' ),
							'100'       => '100',
							'80'        => '80',
							'60'        => '60',
							'40'        => '40',
							'20'        => '20',
						),
						'attributes'   => array(
							'data-context' => 'image',
							'data-meta'    => 'q',
						),
					),

				),
				array(
					'type' => 'group',
					array(
						'type'         => 'on_off',
						'slug'         => 'enable_breakpoints',
						'title'        => __( 'Image breakpoints', 'cloudinary' ),
						'tooltip_text' => __( 'Automatically generate multiple sizes based on the configured breakpoints to enable your images to responsively adjust to different screen sizes. Note that your Cloudinary usage will increase when enabling responsive images.', 'cloudinary' ),
						'description'  => __( 'Enable responsive images.', 'cloudinary' ),
						'default'      => 'off',
					),
					array(
						'type'      => 'group',
						'title'     => __( 'Image breakpoints', 'cloudinary' ),
						'condition' => array(
							'enable_breakpoints' => true,
						),
						array(
							'type'         => 'number',
							'slug'         => 'breakpoints',
							'title'        => __( 'Max breakpoints', 'cloudinary' ),
							'tooltip_text' => __( 'The maximum number of images to be generated when delivering responsive images. For some images, the responsive algorithm may determine that the ideal number of breakpoints is smaller than the value you specify.', 'cloudinary' ),
							'suffix'       => __( 'Valid values: 3-200', 'cloudinary' ),
							'default'      => 3,
							'attributes'   => array(
								'min' => 3,
								'max' => 200,
							),
						),
						array(
							'type'         => 'number',
							'slug'         => 'bytes_step',
							'title'        => __( 'Byte step', 'cloudinary' ),
							'tooltip_text' => __( 'The minimum number of bytes between two consecutive breakpoints.', 'cloudinary' ),
							'suffix'       => __( 'bytes', 'cloudinary' ),
							'default'      => 200,
						),
						array(
							'type'         => 'number',
							'slug'         => 'max_width',
							'title'        => __( 'Image width limit', 'cloudinary' ),
							'tooltip_text' => __( 'The minimum and maximum width of an image created as a breakpoint. Leave max as empty to auto detect based on largest registered size in WordPress.', 'cloudinary' ),
							'prefix'       => __( 'Max', 'cloudinary' ),
							'suffix'       => __( 'px', 'cloudinary' ),
							'default'      => $this->default_max_width(),
						),
						array(
							'type'    => 'number',
							'slug'    => 'min_width',
							'prefix'  => __( 'Min', 'cloudinary' ),
							'suffix'  => __( 'px', 'cloudinary' ),
							'default' => 800,
						),
					),
				),
				array(
					'type'           => 'text',
					'slug'           => 'image_freeform',
					'title'          => __( 'Custom transformation', 'cloudinary' ),
					'tooltip_text'   => __( 'The set of transformations to apply to all image assets, as a URL transformation string', 'cloudinary' ),
					'attributes'     => array(
						'data-context' => 'image',
						'placeholder'  => 'w_90,r_max',
					),
					'taxonomy_field' => array(
						'context'  => 'image',
						'priority' => 10,
					),
				),
			),
			array(
				'type'  => 'column',
				'class' => array(
					'column-55',
				),
				array(
					'type'           => 'image_preview',
					'title'          => __( 'Image preview', 'cloudinary' ),
					'slug'           => 'image_preview',
					'default'        => 'https://res.cloudinary.com/demo/image/upload/w_600/sample.jpg',
					'taxonomy_field' => array(
						'context'  => 'image',
						'priority' => 10,
					),
				),
			),

		),
	),
	array(
		'type' => 'submit',
	),
);

return apply_filters( 'cloudinary_admin_tab_global_transformations', $settings );
