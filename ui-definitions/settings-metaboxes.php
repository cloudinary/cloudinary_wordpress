<?php
/**
 * Defines the settings structure for metaboxes.
 *
 * @package Cloudinary
 */

/**
 * Enable the crop size settings.
 *
 * @hook  cloudinary_enabled_crop_sizes
 * @since 3.1.0
 * @default {false}
 *
 * @param $enabeld {bool} Are the crop sizes enabled?
 *
 * @retrun {bool}
 */
if ( ! apply_filters( 'cloudinary_enabled_crop_sizes', false ) ) {
	return array();
}
$metaboxes = array(
	'crop_meta' => array(
		'title'    => __( 'Cloudinary crop sizes', 'cloudinary' ),
		'screen'   => 'attachment',
		'settings' => array(
			array(
				'slug' => 'single_crop_sizes',
				'type' => 'stand_alone',
				array(
					'type'         => 'on_off',
					'slug'         => 'enable_single_sizes',
					'title'        => __( 'Sized transformations', 'cloudinary' ),
					'tooltip_text' => __(
						'Enable transformations per registered image sizes.',
						'cloudinary'
					),
					'description'  => __( 'Enable sized transformations.', 'cloudinary' ),
					'default'      => 'off',
				),
				array(
					'type'      => 'crops',
					'slug'      => 'single_sizes',
					'mode'      => 'full',
					'condition' => array(
						'enable_single_sizes' => true,
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
 * @since  3.1.0
 *
 * @param $metaboxes {array}  Array of meta boxes to create.
 *
 * @return {array}
 */
return apply_filters( 'cloudinary_meta_boxes', $metaboxes );
