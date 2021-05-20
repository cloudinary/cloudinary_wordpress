<?php
/**
 * Defines the settings structure for the main pages.
 *
 * @package Cloudinary
 */

$settings = array(
	$this->slug => array(
		'page_title' => __( 'Cloudinary Dashboard', 'cloudinary' ),
		'menu_title' => __( 'Dashboard', 'cloudinary' ),
		'priority'   => 0,
		array(
			'type' => 'panel',
			array(
				'type'  => 'plan',
				'title' => __( 'Your Current Plan', 'cloudinary' ),
			),
			array(
				'type'    => 'link',
				'url'     => 'https://cloudinary.com/console/lui/upgrade_options',
				'content' => __( 'Upgrade Plan', 'cloudinary' ),
			),
		),
		array(
			'type' => 'panel',
			array(
				'type'  => 'plan_status',
				'title' => __( 'Your Plan Status', 'cloudinary' ),
			),
		),
		array(
			'type' => 'panel_short',
			array(
				'type'  => 'media_status',
				'title' => __( 'Your Media Sync Status', 'cloudinary' ),
			),
		),
	),
);

/**
 * Filter to enable beta features for testing.
 *
 * @hook    cloudinary_beta
 * @default false
 *
 * @param $enable  {bool}   Flag to enable beata features.
 * @param $feature {string} Optional feature type.
 *
 * @return {bool}
 */
if ( apply_filters( 'cloudinary_beta', false, 'site_cache' ) ) {
	if ( ! empty( $this->get_component( 'cache' ) ) ) {
		$settings[ $this->slug ][] = array(
			'type' => 'panel',
			array(
				'type'  => 'cache_status',
				'title' => __( 'Cache Status', 'cloudinary' ),
			),
		);
	}
}

/**
 * Filter the Cloudinary admin pages.
 *
 * @hook cloudinary_admin_pages
 *
 * @param $settings {array} The admin pages settings.
 *
 * @return {array}
 */
return apply_filters( 'cloudinary_admin_pages', $settings );
