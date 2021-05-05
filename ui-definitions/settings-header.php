<?php
/**
 * Defines the settings structure for the main header.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'       => 'tag',
		'element'    => 'img',
		'attributes' => array(
			'src'   => esc_url( $this->dir_url . 'css/logo.svg' ),
			'alt'   => __( "Cloudinary's logo", 'cloudinary' ),
			'width' => '150px',
		),
	),
	array(
		'type'       => 'tag',
		'element'    => 'p',
		'attributes' => array(
			'style' => 'margin-left: 1rem; font-size: 0.75rem;',
			'alt'   => __( "Cloudinary's logo", 'cloudinary' ),
		),
		array(
			'type'       => 'tag',
			'element'    => 'span',
			'content'    => $this->version . ' | ',
			'attributes' => array(
				'class' => array(
					'description',
				),
			),
		),
		array(
			'type'       => 'tag',
			'element'    => 'a',
			'content'    => __( 'Need help?', 'cloudinary' ),
			'attributes' => array(
				'href'   => 'https://cloudinary.com/documentation/wordpress_integration',
				'target' => '_blank',
				'rel'    => 'noreferrer',
			),
		),
	),
);

return apply_filters( 'cloudinary_admin_header', $settings );
