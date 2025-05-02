<?php
/**
 * Defines the settings structure for video.
 *
 * @package Cloudinary
 */

use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

$transformations_title = Utils::get_transformations_title( esc_html__( 'Video', 'cloudinary' ) );

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Video - Global Settings', 'cloudinary' ),
		'anchor'      => true,
		'option_name' => 'media_display',
		array(
			'type' => 'tabs',
			'tabs' => array(
				'image_setting' => array(
					'text' => __( 'Settings', 'cloudinary' ),
					'id'   => 'settings',
				),
				'image_preview' => array(
					'text' => __( 'Preview', 'cloudinary' ),
					'id'   => 'preview',
				),
			),
		),
		array(
			'type' => 'row',
			array(
				'type'   => 'column',
				'tab_id' => 'settings',
				'class'  => array(
					'column-min-w-50',
				),
				array(
					'type'               => 'on_off',
					'slug'               => 'video_delivery',
					'title'              => __( 'Video delivery', 'cloudinary' ),
					'optimisation_title' => __( 'Video delivery', 'cloudinary' ),
					'tooltip_text'       => __(
						'If you turn this setting off, your videos will be delivered from WordPress.',
						'cloudinary'
					),
					'description'        => __( 'Sync and deliver videos from Cloudinary.', 'cloudinary' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'video',
					),
					'readonly'           => static function () {
						$plugin = get_plugin_instance();
						return 'on' === $plugin->settings->get_value( 'video_delivery' ) && ! $plugin->get_component( 'storage' )->is_local_full();
					},
					'readonly_message'   => sprintf(
						// translators: %s is a link to the storage settings page.
						__( 'This setting currently can’t be turned off. Your videos must be delivered from Cloudinary because your assets are being stored in Cloudinary only. To enable delivering videos from WordPress, first select a %s in the General Settings page that will enable storing your assets also in WordPress.', 'cloudinary' ),
						sprintf(
							'<a href="%s">%s</a>',
							add_query_arg( array( 'page' => 'cloudinary_connect#connect.offload' ), admin_url( 'admin.php' ) ),
							__( 'Storage setting', 'cloudinary' )
						)
					),
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'video_delivery' => true,
					),
					array(
						'type'    => 'tag',
						'element' => 'hr',
					),
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
						'condition' => array(
							'video_player' => 'cld',
						),
						array(
							'slug'         => 'adaptive_streaming',
							'description'  => __( 'Adaptive bitrate streaming (beta)', 'cloudinary' ),
							'type'         => 'on_off',
							'default'      => 'off',
							'tooltip_text' => sprintf(
								// translators: Placeholders are <a> tags.
								__(
									'Adaptive bitrate streaming is a video delivery technique that adjusts the quality of a video stream in real time according to detected bandwidth and CPU capacity.%1$sRead more about Adaptive bitrate streaming%2$s',
									'cloudinary'
								),
								'<br><a href="https://cloudinary.com/documentation/adaptive_bitrate_streaming" target="_blank">',
								'</a>'
							),
						),
						array(
							'slug'      => 'adaptive_streaming_mode',
							'title'     => __( 'Streaming protocol', 'cloudinary' ),
							'type'      => 'select',
							'default'   => 'mpd',
							'options'   => array(
								'mpd'  => __( 'Dynamic adaptive streaming over HTTP (MPEG-DASH)', 'cloudinary' ),
								'm3u8' => __( 'HTTP live streaming (HLS)', 'cloudinary' ),
							),
							'condition' => array(
								'adaptive_streaming' => true,
							),
						),
					),
					array(
						'type'      => 'group',
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
							'description' => __( 'Repeat video', 'cloudinary' ),
							'type'        => 'on_off',
							'default'     => 'off',
						),
						array(
							'slug'         => 'video_autoplay_mode',
							'title'        => __( 'Autoplay', 'cloudinary' ),
							'type'         => 'radio',
							'default'      => 'off',
							'options'      => array(
								'off'       => __( 'Off', 'cloudinary' ),
								'always'    => __( 'Always', 'cloudinary' ),
								'on-scroll' => __( 'On-scroll (autoplay when in view)', 'cloudinary' ),
							),
							'tooltip_text' => sprintf(
								// translators: Placeholders are <a> tags.
								__(
									'Please note that when choosing "always", the video will autoplay without sound (muted). This is a built-in browser feature and applies to all major browsers.%1$sRead more about muted autoplay%2$s',
									'cloudinary'
								),
								'<br><a href="https://cloudinary.com/glossary/video-autoplay" target="_blank">',
								'</a>'
							),
						),
					),
					array(
						'type'    => 'tag',
						'element' => 'hr',
					),
					array(
						'type' => 'group',
						array(
							'type'         => 'on_off',
							'slug'         => 'video_optimization',
							'title'        => __( 'Video optimization', 'cloudinary' ),
							'tooltip_text' => __(
								'Videos will be delivered using Cloudinary’s automatic format and quality algorithms for the best tradeoff between visual quality and file size. Use Advanced Optimization options to manually tune format and quality.',
								'cloudinary'
							),
							'description'  => __( 'Optimize videos on my site.', 'cloudinary' ),
							'default'      => 'on',
							'attributes'   => array(
								'data-context' => 'video',
							),
							'depends'      => array(
								'video_delivery',
							),
						),
					),
					array(
						'type'      => 'group',
						'condition' => array(
							'video_optimization' => true,
						),
						array(
							'type'         => 'select',
							'slug'         => 'video_format',
							'title'        => __( 'Video format', 'cloudinary' ),
							'tooltip_text' => __(
								"The video format to use for delivery. Leave as Auto to automatically deliver the most optimal format based on the user's browser and device.",
								'cloudinary'
							),
							'default'      => 'auto',
							'options'      => array(
								'none' => __( 'Not set', 'cloudinary' ),
								'auto' => __( 'Auto', 'cloudinary' ),
							),
							'suffix'       => 'f_@value',
							'attributes'   => array(
								'data-context' => 'video',
								'data-meta'    => 'f',
							),
						),
						array(
							'type'         => 'select',
							'slug'         => 'video_quality',
							'title'        => __( 'Video quality', 'cloudinary' ),
							'tooltip_text' => __(
								'The compression quality to apply when delivering videos. Leave as Auto to apply an algorithm that finds the best tradeoff between visual quality and file size.',
								'cloudinary'
							),
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
							'suffix'       => 'q_@value',
							'attributes'   => array(
								'data-context' => 'video',
								'data-meta'    => 'q',
							),
						),

					),
					array(
						'type'    => 'tag',
						'element' => 'hr',
					),
					array(
						'type'           => 'text',
						'slug'           => 'video_freeform',
						'title'          => $transformations_title,
						'default'        => '',
						'anchor'         => true,
						'tooltip_text'   => sprintf(
							// translators: The link to transformation reference.
							__(
								'A set of additional transformations to apply to all videos. Specify your transformations using Cloudinary URL transformation syntax. See %1$sreference%2$s for all available transformations and syntax.%3$s* The Cloudinary global transformations are only applied to assets managed in the Media Library%4$s.',
								'cloudinary'
							),
							'<a href="https://cloudinary.com/documentation/transformation_reference" target="_blank" rel="noopener noreferrer">',
							'</a>',
							'<br><br><em>',
							'</em>'
						),
						'link'           => array(
							'text' => __( 'See examples', 'cloudinary' ),
							'href' => 'https://cloudinary.com/documentation/transformation_reference',
						),
						'attributes'     => array(
							'data-context' => 'video',
							'placeholder'  => 'fps_15-25,ac_none',
						),
						'taxonomy_field' => array(
							'context'  => 'video',
							'priority' => 10,
						),
					),
					array(
						'type'  => 'info_box',
						'icon'  => $this->dir_url . 'css/images/video.svg',
						'title' => __( 'What are transformations?', 'cloudinary' ),
						'text'  => __(
							'A set of parameters included in a Cloudinary URL to programmatically transform the visual appearance of the assets on your website.',
							'cloudinary'
						),
					),
				),
			),
			array(
				'type'      => 'column',
				'tab_id'    => 'preview',
				'class'     => array(
					'column-min-w-50',
				),
				'condition' => array(
					'video_delivery' => true,
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
		array(
			'type'  => 'info_box',
			'icon'  => $this->dir_url . 'css/images/academy-icon.svg',
			'title' => __( 'Need help?', 'cloudinary' ),
			'text'  => sprintf(
				// Translators: The HTML for opening and closing link tags.
				__(
					'Watch free lessons on how to use the Video Global Settings in the %1$sCloudinary Academy%2$s.',
					'cloudinary'
				),
				'<a href="https://training.cloudinary.com/learn/course/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-1h85/lessons/transforming-images-and-videos-for-pages-and-posts-1545?page=1" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
		),
	),
);

return apply_filters( 'cloudinary_admin_video_settings', $settings );
