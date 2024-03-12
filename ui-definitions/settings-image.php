<?php
/**
 * Defines the settings structure for images.
 *
 * @package Cloudinary
 */

use function Cloudinary\get_plugin_instance;

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Image - Global Settings', 'cloudinary' ),
		'anchor'      => true,
		'option_name' => 'media_display',
		array(
			'type' => 'tabs',
			'tabs' => array(
				'image_setting' => array(
					'text' => __( 'Settings', 'cloudinary' ),
					'id'   => 'settings',
				),
				'image_preview' => array(
					'text' => __( 'Preview', 'cloudinary' ),
					'id'   => 'preview',
				),
			),
		),
		array(
			'type' => 'row',
			array(
				'type'   => 'column',
				'tab_id' => 'settings',
				array(
					'type'               => 'on_off',
					'slug'               => 'image_delivery',
					'title'              => __( 'Image delivery', 'cloudinary' ),
					'optimisation_title' => __( 'Image delivery', 'cloudinary' ),
					'tooltip_text'       => __(
						'If you turn this setting off, your images will be delivered from WordPress.',
						'cloudinary'
					),
					'description'        => __( 'Sync and deliver images from Cloudinary.', 'cloudinary' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'image',
					),
					'readonly'           => static function () {
						return ! get_plugin_instance()->get_component( 'storage' )->is_local_full();
					},
					'readonly_message'   => sprintf(
						// translators: %s is a link to the storage settings page.
						__( 'This setting currently can’t be turned off. Your images must be delivered from Cloudinary because your assets are being stored in Cloudinary only. To enable delivering images from WordPress, first select a %s in the General Settings page that will enable storing your assets also in WordPress.', 'cloudinary' ),
						sprintf(
							'<a href="%s">%s</a>',
							add_query_arg( array( 'page' => 'cloudinary_connect#connect.offload' ), admin_url( 'admin.php' ) ),
							__( 'Storage setting', 'cloudinary' )
						)
					),
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'image_delivery' => true,
					),
					array(
						'type'    => 'tag',
						'element' => 'hr',
					),
					array(
						'type'               => 'on_off',
						'slug'               => 'image_optimization',
						'title'              => __( 'Image optimization', 'cloudinary' ),
						'optimisation_title' => __( 'Image optimization', 'cloudinary' ),
						'tooltip_text'       => __(
							'Images will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.',
							'cloudinary'
						),
						'description'        => __( 'Optimize images on my site.', 'cloudinary' ),
						'default'            => 'on',
						'attributes'         => array(
							'data-context' => 'image',
						),
						'depends'            => array(
							'image_delivery',
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
							'suffix'       => 'f_@value',
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
							'suffix'       => 'q_@value',
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
						'title'          => __( 'Additional image transformations', 'cloudinary' ),
						'default'        => '',
						'tooltip_text'   => sprintf(
							// translators: The link to transformation reference.
							__(
								'A set of additional transformations to apply to all images. Specify your transformations using Cloudinary URL transformation syntax. See %1$sreference%2$s for all available transformations and syntax.',
								'cloudinary'
							),
							'<a href="https://cloudinary.com/documentation/transformation_reference" target="_blank" rel="noopener noreferrer">',
							'</a>'
						),
						'link'           => array(
							'text' => __( 'See examples', 'cloudinary' ),
							'href' => 'https://cloudinary.com/documentation/image_transformations',
						),
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
						'icon'  => $this->dir_url . 'css/images/transformation.svg',
						'title' => __( 'What are transformations?', 'cloudinary' ),
						'text'  => __(
							'A set of parameters included in a Cloudinary URL to programmatically transform the visual appearance of the assets on your website.',
							'cloudinary'
						),
					),
					array(
						'type'    => 'tag',
						'element' => 'hr',
					),
					array(
						'type'               => 'on_off',
						'slug'               => 'svg_support',
						'title'              => __( 'SVG Support', 'cloudinary' ),
						'optimisation_title' => __( 'SVG Support', 'cloudinary' ),
						'tooltip_text'       => __(
							'Enable Cloudinary\'s SVG Support.',
							'cloudinary'
						),
						'description'        => __( 'Enable SVG support.', 'cloudinary' ),
						'default'            => 'off',
					),
					array(
						'type'    => 'crops',
						'slug'    => 'crop_sizes',
						'title'   => __( 'Crop and Gravity control (beta)', 'cloudinary' ),
						'enabled' => static function () {
							/**
							 * Enable the Crop and Gravity control settings.
							 *
							 * @hook  cloudinary_enable_crop_and_gravity_control
							 * @since 3.1.3
							 * @default {false}
							 *
							 * @param $enabeld {bool} Is the Crop and Gravity control enabled?
							 *
							 * @retrun {bool}
							 */
							return apply_filters( 'cloudinary_enable_crop_and_gravity_control', false );
						},
					),
				),
			),
			array(
				'type'      => 'column',
				'tab_id'    => 'preview',
				'class'     => array(
					'cld-ui-preview',
				),
				'condition' => array(
					'image_delivery' => true,
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
		array(
			'type'  => 'info_box',
			'icon'  => $this->dir_url . 'css/images/academy-icon.svg',
			'title' => __( 'Need help?', 'cloudinary' ),
			'text'  => sprintf(
				// Translators: The HTML for opening and closing link tags.
				__(
					'Watch free lessons on how to use the Image Global Settings in the %1$sCloudinary Academy%2$s.',
					'cloudinary'
				),
				'<a href="https://training.cloudinary.com/learn/course/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-1h85/lessons/transforming-images-and-videos-for-pages-and-posts-1545?page=1" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
		),
	),
);

return apply_filters( 'cloudinary_admin_image_settings', $settings );
