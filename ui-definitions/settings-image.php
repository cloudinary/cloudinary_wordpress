<?php
/**
 * Defines the settings structure for images.
 *
 * @package Cloudinary
 */

$media    = $this->get_component( 'media' );
$settings = array(
	array(
		'type'   => 'panel',
		'title'  => __( 'Image - Global Settings', 'cloudinary' ),
		'anchor' => true,
		array(
			'type' => 'tabs',
			'tabs' => array(
				'image_setting' => array(
					'text' => __( 'Settings', 'cloudinary' ),
					'id'   => '#id',
				),
				'image_preview' => array(
					'text' => __( 'Preview', 'cloudinary' ),
					'id'   => '#id2',
				),
			),
		),
		array(
			'type' => 'row',
			array(
				'type' => 'column',
				array(
					'type' => 'group',
					array(
						'type'         => 'on_off',
						'slug'         => 'image_optimization',
						'title'        => __( 'Image optimization', 'cloudinary' ),
						'tooltip_text' => __(
							'Images will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.',
							'cloudinary'
						),
						'description'  => __( 'Optimize images on my site.', 'cloudinary' ),
						'default'      => 'on',
						'attributes'   => array(
							'data-context' => 'image',
						),
					),
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'image_optimization' => true,
					),
					array(
						'type'         => 'select',
						'slug'         => 'image_format',
						'title'        => __( 'Image format', 'cloudinary' ),
						'tooltip_text' => __(
							"The image format to use for delivery. Leave as Auto to automatically deliver the most optimal format based on the user's browser and device.",
							'cloudinary'
						),
						'default'      => 'auto',
						'options'      => array(
							'none' => __( 'Not set', 'cloudinary' ),
							'auto' => __( 'Auto', 'cloudinary' ),
							'png'  => __( 'PNG', 'cloudinary' ),
							'jpg'  => __( 'JPG', 'cloudinary' ),
							'gif'  => __( 'GIF', 'cloudinary' ),
							'webp' => __( 'WebP', 'cloudinary' ),
						),
						'suffix'       => 'f_auto',
						'attributes'   => array(
							'data-context' => 'image',
							'data-meta'    => 'f',
						),
					),
					array(
						'type'         => 'select',
						'slug'         => 'image_quality',
						'title'        => __( 'Image quality', 'cloudinary' ),
						'tooltip_text' => __(
							'The compression quality to apply when delivering images. Leave as Auto to apply an algorithm that finds the best tradeoff between visual quality and file size.',
							'cloudinary'
						),
						'default'      => 'auto',
						'suffix'       => 'q_auto',
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
					'type'    => 'tag',
					'element' => 'hr',
				),
				array(
					'type'           => 'text',
					'slug'           => 'image_freeform',
					'title'          => __( 'Image transformation', 'cloudinary' ),
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
				array(
					'type'  => 'info_box',
					'icon'  => $this->dir_url . 'css/images/crop.svg',
					'title' => __( 'What are transformation', 'cloudinary' ),
					'text'  => __(
						'Configure how your images are shown on your site. You can apply transformations to adjust the quality, format or visual appearance and define other settings such as responsive images.',
						'cloudinary'
					),
				),
			),
			array(
				'type'  => 'column',
				'class' => array(
					'cld-ui-preview',
				),
				array(
					'type'           => 'image_preview',
					'title'          => __( 'Preview', 'cloudinary' ),
					'slug'           => 'image_preview',
					'default'        => CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE . 'w_600/sample.jpg',
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

return apply_filters( 'cloudinary_admin_image_settings', $settings );
