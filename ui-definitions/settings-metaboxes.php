<?php
/**
 * Defines the settings structure for metaboxes.
 *
 * @package Cloudinary
 */

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
if ( ! apply_filters( 'cloudinary_enable_crop_and_gravity_control', true ) ) {
	return array();
}
$metaboxes = array(
	'crop_meta' => array(
		'title'    => __( 'Cloudinary Crop and Gravity control', 'cloudinary' ),
		'screen'   => 'attachment',
		'settings' => array(
			array(
				'slug' => 'single_crop_and_gravity',
				'type' => 'stand_alone',
				array(
					'type'         => 'on_off',
					'slug'         => 'enable_crop_and_gravity',
					'title'        => __( 'Crop and Gravity control (beta)', 'cloudinary' ),
					'tooltip_text' => __(
						'Enable Crop and Gravity control for registered image sizes.',
						'cloudinary'
					),
					'description'  => __( 'Enable Crop and Gravity', 'cloudinary' ),
					'default'      => 'off',
				),
				array(
					'type'      => 'crops',
					'slug'      => 'single_sizes',
					'mode'      => 'full',
					'condition' => array(
						'enable_crop_and_gravity' => true,
					),
				),
			),
		),
	),
);

/**
 * Filter the meta boxes.
 *
 * @hook   cloudinary_meta_boxes
 * @since  3.1.3
 *
 * @param $metaboxes {array}  Array of meta boxes to create.
 *
 * @return {array}
 */
return apply_filters( 'cloudinary_meta_boxes', $metaboxes );
