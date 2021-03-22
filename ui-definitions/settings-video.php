<?php
/**
 * Defines the settings structure for video.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'   => 'panel',
		'title'  => __( 'Video - Global Settings', 'cloudinary' ),
		'icon'   => $this->plugin->dir_url . 'css/video.svg',
		'slug'   => 'video_settings',
		'anchor' => true,
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-45',
				),
				array(
					'type' => 'group',
					array(
						'type'         => 'select',
						'slug'         => 'video_player',
						'title'        => __( 'Video player', 'cloudinary' ),
						'tooltip_text' => __( 'Which video player to use on all videos.', 'cloudinary' ),
						'default'      => 'wp',
						'options'      => array(
							'wp'  => __( 'WordPress player', 'cloudinary' ),
							'cld' => __( 'Cloudinary player', 'cloudinary' ),
						),
					),
					array(
						'type'      => 'group',
						'title'     => __( 'Player options', 'cloudinary' ),
						'condition' => array(
							'video_player' => 'cld',
						),
						array(
							'slug'        => 'video_controls',
							'description' => __( 'Show controls', 'cloudinary' ),
							'type'        => 'on_off',
							'default'     => 'on',
						),
						array(
							'slug'        => 'video_loop',
							'description' => __( ' Repeat video', 'cloudinary' ),
							'type'        => 'on_off',
							'default'     => 'off',
						),
						array(
							'slug'        => 'video_autoplay_mode',
							'title'       => __( 'Autoplay', 'cloudinary' ),
							'type'        => 'radio',
							'default'     => 'off',
							'options'     => array(
								'off'       => __( 'Off', 'cloudinary' ),
								'always'    => __( 'Always', 'cloudinary' ),
								'on-scroll' => __( 'On-scroll (autoplay when in view)', 'cloudinary' ),
							),
							'description' => sprintf(
								// translators: Placeholders are <a> tags.
								__( 'Please note that when choosing "always", the video will autoplay without sound (muted). This is a built-in browser feature and applies to all major browsers.%1$sRead more about muted autoplay%2$s', 'cloudinary' ),
								'<br><a href="https://developers.google.com/web/updates/2016/07/autoplay" target="_blank">',
								'</a>'
							),
						),
					),
					array(
						'type'         => 'on_off',
						'slug'         => 'video_limit_bitrate',
						'title'        => __( 'Bitrate', 'cloudinary' ),
						'description'  => __( 'Enable bitrate limiter', 'cloudinary' ),
						'tooltip_text' => __( 'If set, all videos will be delivered in the defined bitrate.', 'cloudinary' ),
						'default'      => 'off',
						'attributes'   => array(
							'data-context' => 'video',
						),
					),
					array(
						'type'        => 'number',
						'slug'        => 'video_bitrate',
						'prefix'      => __( 'Bitrate limit', 'cloudinary' ),
						'description' => __( 'Maximum number of bits per second in Kilobytes.', 'cloudinary' ),
						'default'     => '500',
						'suffix'      => 'k',
						'condition'   => array(
							'video_limit_bitrate' => true,
						),
						'attributes'  => array(
							'data-context' => 'video',
							'data-meta'    => 'br',
							'data-suffix'  => 'k',
						),
					),
				),
				array(
					'type' => 'group',
					array(
						'type'         => 'on_off',
						'slug'         => 'video_optimization',
						'title'        => __( 'Video optimization', 'cloudinary' ),
						'tooltip_text' => __( 'Videos will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.', 'cloudinary' ),
						'description'  => __( 'Optimize videos on my site.', 'cloudinary' ),
						'default'      => 'on',
						'attributes'   => array(
							'data-context' => 'video',
						),
					),
				),
				array(
					'type'        => 'group',
					'title'       => __( 'Advanced optimization', 'cloudinary' ),
					'collapsible' => 'open',
					'condition'   => array(
						'video_optimization' => true,
					),
					array(
						'type'         => 'select',
						'slug'         => 'video_format',
						'title'        => __( 'Video format', 'cloudinary' ),
						'tooltip_text' => __( "The video format to use for delivery. Leave as Auto to automatically deliver the most optimal format based on the user's browser and device.", 'cloudinary' ),
						'default'      => 'auto',
						'options'      => array(
							'none' => __( 'Not set', 'cloudinary' ),
							'auto' => __( 'Auto', 'cloudinary' ),
						),
						'attributes'   => array(
							'data-context' => 'video',
							'data-meta'    => 'f',
						),
					),
					array(
						'type'         => 'select',
						'slug'         => 'video_quality',
						'title'        => __( 'Video quality', 'cloudinary' ),
						'tooltip_text' => __( 'The compression quality to apply when delivering videos. Leave as Auto to apply an algorithm that finds the best tradeoff between visual quality and file size.', 'cloudinary' ),
						'default'      => 'auto',
						'options'      => array(
							'none'      => __( 'Not set', 'cloudinary' ),
							'auto'      => __( 'Auto', 'cloudinary' ),
							'auto:best' => __( 'Auto best', 'cloudinary' ),
							'auto:good' => __( 'Auto good', 'cloudinary' ),
							'auto:eco'  => __( 'Auto eco', 'cloudinary' ),
							'auto:low'  => __( 'Auto low', 'cloudinary' ),
							'100'       => '100',
							'80'        => '80',
							'60'        => '60',
							'40'        => '40',
							'20'        => '20',
						),
						'attributes'   => array(
							'data-context' => 'video',
							'data-meta'    => 'q',
						),
					),

				),
				array(
					'type'           => 'text',
					'slug'           => 'video_freeform',
					'title'          => __( 'Custom transformation', 'cloudinary' ),
					'tooltip_text'   => __( 'The set of transformations to apply to all video assets, as a URL transformation string.', 'cloudinary' ),
					'attributes'     => array(
						'data-context' => 'video',
						'placeholder'  => 'fps_15-25,ac_none',
					),
					'taxonomy_field' => array(
						'context'  => 'video',
						'priority' => 10,
					),
				),
			),
			array(
				'type'  => 'column',
				'class' => array(
					'column-55',
				),
				array(
					'type'           => 'video_preview',
					'title'          => __( 'Video preview', 'cloudinary' ),
					'slug'           => 'video_preview',
					'taxonomy_field' => array(
						'context'  => 'video',
						'priority' => 10,
					),
				),
			),

		),
	),
	array(
		'type' => 'submit',
	),
);

return apply_filters( 'cloudinary_admin_tab_global_transformations', $settings );
