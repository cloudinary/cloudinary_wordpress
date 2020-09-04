<?php
/**
 * Defines the tab structure for Cloudinary settings page.
 *
 * @package Cloudinary
 */
$dirs = wp_get_upload_dir();
$base = wp_parse_url( $dirs['baseurl'] );

$struct = array(
	'title'           => __( 'Sync Media', 'cloudinary' ),
	'description'     => __( 'Sync WordPress Media with Cloudinary', 'cloudinary' ),
	'hide_button'     => true,
	'requires_config' => true,
	'fields'          => array(
		'auto_sync'         => array(
			'label'       => __( 'Sync method', 'cloudinary' ),
			'description' => __( 'Auto Sync: Media is synchronized automatically on demand; Manual Sync: Manually synchronize assets from the Media page.', 'cloudinary' ),
			'type'        => 'radio',
			'default'     => 'on',
			'choices'	  => array(
				'on'  => __( 'Auto Sync', 'cloudinary' ),
				'off' => __( 'Manual Sync', 'cloudinary' ),
			)
		),
		'cloudinary_folder' => array(
			'label'             => __( 'Cloudinary folder path', 'cloudinary' ),
			'placeholder'       => __( 'e.g.: wordpress_assets/', 'cloudinary' ),
			'description'       => __( 'Specify the folder in your Cloudinary account where WordPress assets are uploaded to. All assets uploaded to WordPress from this point on will be synced to the specified folder in Cloudinary. Leave blank to use the root of your Cloudinary library.', 'cloudinary' ),
			'sanitize_callback' => array( '\Cloudinary\Media', 'sanitize_cloudinary_folder' ),
		),
		'offload' => array(
			'label'   => __( 'Storage', 'cloudinary' ),
			'description' => sprintf(
				// translators: Placeholders are <a> tags.
				__( 'Choose where to store your assets. Assets stored in both Cloudinary and WordPress will enable local WordPress delivery if the Cloudinary plugin is disabled or uninstalled. Storing assets with WordPress in lower resolution will save on local WordPress storage and enable low resolution local WordPress delivery if the plugin is disabled. Storing assets with Cloudinary only will require additional steps to enable backwards compatibility. For help managing your storage, submit a %1$s support request.%2$s', 'cloudinary' ),
				'<a href="https://support.cloudinary.com/hc/en-us/requests/new" target="_blank">',
				'</a>'
			),
			'type'    => 'select',
			'default' => 'dual_full',
			'choices'     => array(
				'dual_full' => __( 'Cloudinary and WordPress', 'cloudinary' ),
				'dual_low'  => __( 'Cloudinary and WordPress (low resolution)', 'cloudinary' ),
				'cld'       => __( 'Cloudinary only', 'cloudinary' ),
			),
		),
	),
);

return apply_filters( 'cloudinary_admin_tab_sync_media', $struct );
