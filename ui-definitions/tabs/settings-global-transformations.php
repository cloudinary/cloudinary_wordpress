<?php
/**
 * Defines the tab structure for Cloudinary settings page.
 *
 * @package Cloudinary
 */

$struct = array(
	'title'          => __( 'Image Settings', 'cloudinary' ),
	'heading'        => __( 'Global Image Settings', 'cloudinary' ),
	'description'    => __( 'Define the global (default) transformations to be applied to all image assets. These transformations can be overridden by transformations you apply to individual assets.', 'cloudinary' ),
	'success_notice' => __( 'Global Image Settings have been updated.', 'cloudinary' ),
	'classes'        => array(
		'connect',
	),
	'fields'         => array(
		'responsive_header'  => array(
			'type'  => 'heading',
			'label' => __( 'Responsive Image Settings', 'cloudinary' ),
		),
		'enable_breakpoints' => array(
			'label'   => __( 'Enable Breakpoints', 'cloudinary' ),
			'suffix'  => __( 'Enable breakpoint generation on upload.', 'cloudinary' ),
			'type'    => 'checkbox',
			'default' => 'off',
		),
		'breakpoints'        => array(
			'label'             => __( 'Max breakpoints', 'cloudinary' ),
			'description'       => __( 'Indicates the max number of images that will be generated when delivering responsive images. For some images, the responsive algorithm may determine that the ideal number of breakpoints is smaller than the value you specify.', 'cloudinary' ),
			'type'              => 'number',
			'default'           => 5,
			'max'               => 200,
			'min'               => 3,
			'sanitize_callback' => array( '\Cloudinary\Media', 'sanitize_breakpoints' ),
			'required'          => true,
			'condition'         => array(
				'enable_breakpoints' => true,
			),
			// translators: Placeholders are numerical ranges or min and max.
			'suffix'            => sprintf( __( 'Valid values: %1$d – %2$d.', 'cloudinary' ), 3, 200 ),
		),
		'bytes_step'         => array(
			'label'       => __( 'Byte step', 'cloudinary' ),
			'description' => __( 'The minimum number of bytes between two consecutive breakpoints.', 'cloudinary' ),
			'type'        => 'number',
			'max'         => 10000000,
			'min'         => 0,
			'default'     => 20000,
			'required'    => true,
			'suffix'      => __( 'bytes', 'cloudinary' ),
			'condition'   => array(
				'enable_breakpoints' => true,
			),
		),
		'max_width'          => array(
			'label'       => __( 'Image width limit', 'cloudinary' ),
			'description' => __( 'The maximum width of an image created as a breakpoint. Leave as empty to auto detect based on largest registered size in WordPress.', 'cloudinary' ),
			'type'        => 'number',
			'max'         => 10000000,
			'min'         => 0,
			'default'     => 0,
			'prefix'      => __( 'Max', 'cloudinary' ),
			'suffix'      => __( 'px', 'cloudinary' ),
			'condition'   => array(
				'enable_breakpoints' => true,
			),
		),
		'min_width'          => array(
			'label'       => null,
			'description' => __( 'The minimum width of an image created as a breakpoint.', 'cloudinary' ),
			'type'        => 'number',
			'max'         => 10000000,
			'min'         => 0,
			'default'     => 50,
			'required'    => true,
			'prefix'      => __( 'Min', 'cloudinary' ),
			'suffix'      => __( 'px', 'cloudinary' ),
			'condition'   => array(
				'enable_breakpoints' => true,
			),
		),
		'delivery_header'    => array(
			'type'  => 'heading',
			'label' => __( 'Delivery Settings', 'cloudinary' ),
		),
		'image_format'       => array(
			'label'       => __( 'Image format', 'cloudinary' ),
			'description' => sprintf(
				// translators: Placeholders are <strong> tags.
				__( 'If set, all images will be delivered in the selected format. %1$sAuto%2$s applies an algorithm that automatically selects the optimal format for each image. Not relevant for videos.', 'cloudinary' ),
				'<strong>',
				'</strong>'
			),
			'type'        => 'select',
			'choices'     => array(
				'none' => __( 'Not Set', 'cloudinary' ),
				'auto' => __( 'Auto', 'cloudinary' ),
				'png'  => __( 'PNG', 'cloudinary' ),
				'jpg'  => __( 'JPG', 'cloudinary' ),
				'gif'  => __( 'GIF', 'cloudinary' ),
				'webp' => __( 'WebP', 'cloudinary' ),
			),
			'data_meta'   => 'f', // String to be added to the input field data-meta.
			'default'     => 'auto',
			'context'     => 'image',
			'suffix'      => '<a href="https://cloudinary.com/documentation/image_transformation_reference#fetch_format_parameter" target="_blank"><span class="dashicons dashicons-info"></span></a>',
		),
		'image_quality'      => array(
			'label'       => __( 'Quality', 'cloudinary' ),
			'description' => sprintf(
				// translators: Placeholders are <strong> tags.
				__( 'The compression quality to apply when delivering all assets. %1$sAuto%2$s applies an algorithm that finds the best tradeoff between visual quality and file size.', 'cloudinary' ),
				'<strong>',
				'</strong>'
			),
			'type'        => 'select',
			'choices'     => array(
				'none'      => __( 'Not Set', 'cloudinary' ),
				'auto'      => __( 'Auto', 'cloudinary' ),
				'auto:best' => __( 'Auto Best', 'cloudinary' ),
				'auto:good' => __( 'Auto Good', 'cloudinary' ),
				'auto:eco'  => __( 'Auto Eco', 'cloudinary' ),
				'auto:low'  => __( 'Auto Low', 'cloudinary' ),
				'100'       => '100',
				'80'        => '80',
				'60'        => '60',
				'40'        => '40',
				'20'        => '20',
			),
			'data_meta'   => 'q', // String to be added to the input field data-meta.
			'default'     => 'auto',
			'context'     => 'image',
			'suffix'      => '<a href="https://cloudinary.com/documentation/image_transformation_reference#quality_parameter" target="_blank"><span class="dashicons dashicons-info"></span></a>',
		),
		'image_freeform'     => array(
			'label'             => __( 'Image transformations', 'cloudinary' ),
			'description'       => sprintf(
				// translators: Placeholders are <a> tags.
				__( 'The set of transformations to apply to all image assets, as a URL transformation string. %1$sImage transformation reference%2$s.%3$s<span class="settings-alert settings-alert-info"><strong>Note</strong>: When updating transformations, all relevant assets will have new transformations applied; This may cause an increase in your Cloudinary usage.</span>', 'cloudinary' ),
				'<br><a href="https://cloudinary.com/documentation/image_transformation_reference" target="_blank">',
				'</a>',
				'<br><br>'
			),
			'contextual'        => true, // Flags the field to be used in a contextual basis. i.e. taxonomies.
			'context'           => 'image',
			'sanitize_callback' => 'trim',
			'error_notice'      => __( 'Format and Quality cannot be used in free form.', 'cloudinary' ),
			'type'              => 'textarea',
		),
		'image_preview'      => array(
			'label'      => __( 'Preview', 'cloudinary' ),
			'contextual' => true, // Flags the field to be used in a contextual basis. i.e. taxonomies.
			'context'    => 'image',
			'type'       => function () {
				\Cloudinary\get_plugin_instance()->components['media']->global_transformations->load_preview();
			},
		),
	),
);

return apply_filters( 'cloudinary_admin_tab_global_transformations', $struct );
