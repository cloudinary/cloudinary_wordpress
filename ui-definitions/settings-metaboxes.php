<?php
/**
 * Defines the settings structure for metaboxes.
 *
 * @package Cloudinary
 */

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
					array(
						'type'      => 'sizes',
						'slug'      => 'single_sizes',
						'mode'      => 'full',
						'condition' => array(
							'enable_single_sizes' => true,
						),
					),
					array(
						'type'  => 'info_box',
						'icon'  => 'dashicons-image-crop',
						'title' => __( 'What are crop sizes?', 'cloudinary' ),
						'text'  => Utils::get_crop_sizes_info_box_text(),
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
