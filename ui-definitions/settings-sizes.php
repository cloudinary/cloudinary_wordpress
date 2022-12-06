<?php

$settings = array(
	'storage'  => 'post_meta',
	'settings' => array(
		 'crop_sizes'=>array(
			'type' => 'page',
			array(
				'type'         => 'on_off',
				'slug'         => 'asset_sized_transformations',
				'title'        => __( 'Sized transformations', 'cloudinary' ),
				'tooltip_text' => __(
					'Enable transformations per registered image sizes.',
					'cloudinary'
				),
				'description'  => __( 'Enable sized transformations.', 'cloudinary' ),
				'default'      => 'off',
			),
			array(
				'type'      => 'sizes',
				'slug'      => 'sizes',
				'mode'      => 'full',
				'condition' => array(
					'asset_sized_transformations' => true,
				),
			),
		),
	),
);

return $settings;
