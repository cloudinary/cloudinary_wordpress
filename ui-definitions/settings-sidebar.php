<?php
/**
 * Defines the settings structure for the sidebar.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Defines the settings structure for the main header.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Account status', 'cloudinary' ),
		'description' => __( 'Subscription plan name', 'cloudinary' ),
		'collapsible' => 'open',
		array(
			'type'        => 'line_stat',
			'title'       => __( 'Storage', 'cloudinary' ),
			'stat'        => 'storage',
			'format_size' => true,
		),
		array(
			'type'  => 'line_stat',
			'title' => __( 'Transformations', 'cloudinary' ),
			'stat'  => 'transformations',
		),
		array(
			'type'        => 'line_stat',
			'title'       => __( 'Bandwidth', 'cloudinary' ),
			'stat'        => 'bandwidth',
			'format_size' => true,
		),
		array(
			'type'       => 'tag',
			'element'    => 'a',
			'content'    => __( 'View my account status', 'cloudinary' ),
			'attributes' => array(
				'href'   => 'https://cloudinary.com/documentation/wordpress_integration',
				'target' => '_blank',
				'rel'    => 'noreferrer',
				'class'  => array(
					'cld-link-button',
				),
			),
		),
	),
	array(
		'type'        => 'panel',
		'title'       => __( 'Optimization level', 'cloudinary' ),
		'description' => __( '40% Optimized', 'cloudinary' ),
		'collapsible' => 'closed',
		array(
			'type'  => 'opt_level',
			'title' => __( 'Optimized Images', 'cloudinary' ),
		),
	),
	array(
		'type'        => 'panel',
		'title'       => __( 'Extensions', 'cloudinary' ),
		'description' => __( '1 Active extension', 'cloudinary' ),
		'collapsible' => 'closed',
		array(
			'type'  => 'info_box',
			'icon'  => $this->dir_url . 'css/images/cloud.svg',
			'title' => __( 'Cloudinary', 'cloudinary' ),
			'text'  => __(
				'Cloudinary\'s digital asset management solution bridges the gap between asset management and delivery, enabling creative.',
				'cloudinary'
			),

			'link_text' => 'Active',
			'url'       => '#',
			
		),
	),
);

return apply_filters( 'cloudinary_admin_sidebar', $settings );
