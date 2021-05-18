<?php
/**
 * Defines the settings structure for the main footer.
 *
 * @package Cloudinary
 */

$settings = array(
	'type'       => 'html',
	'attributes' => array(
		'wrap' => array(
			'class' => array(
				'cld-footer',
			),
		),
	),
	array(
		'type'       => 'notice',
		'message'    => __( 'Thanks for using Cloudinary, please take a minute to rate our plugin.', 'cloudinary' ),
		'dismiss'    => true,
		'duration'   => HOUR_IN_SECONDS,
		'attributes' => array(
			'class' => array(
				'cld-footer',
			),
		),
		array(
			'type'       => 'tag',
			'element'    => 'a',
			'attributes' => array(
				'href'   => 'https://wordpress.org/support/plugin/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/reviews/#new-post',
				'target' => '_blank',
				'class'  => array(
					'cld-stars',
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'span',
				'attributes' => array(
					'class' => array(
						'dashicons',
						'dashicons-star-filled',
					),
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'span',
				'attributes' => array(
					'class' => array(
						'dashicons',
						'dashicons-star-filled',
					),
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'span',
				'attributes' => array(
					'class' => array(
						'dashicons',
						'dashicons-star-filled',
					),
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'span',
				'attributes' => array(
					'class' => array(
						'dashicons',
						'dashicons-star-filled',
					),
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'span',
				'attributes' => array(
					'class' => array(
						'dashicons',
						'dashicons-star-filled',
					),
				),
			),

		),
	),
);

/**
 * Filter the Cloudinary admin footer.
 *
 * @hook cloudinary_admin_footer
 *
 * @param $settings {array} The footer settings.
 *
 * @return {array}
 */
return apply_filters( 'cloudinary_admin_footer', $settings );
