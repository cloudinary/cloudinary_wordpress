<?php
/**
 * Defines the settings structure for the main pages.
 *
 * @package Cloudinary
 */

use Cloudinary\Utils;
use Cloudinary\Report;
use function Cloudinary\get_plugin_instance;

$media    = $this->get_component( 'media' );
$settings = array(
	'dashboard'      => array(
		'page_title'          => __( 'Cloudinary Dashboard', 'cloudinary' ),
		'menu_title'          => __( 'Dashboard', 'cloudinary' ),
		'priority'            => 1,
		'requires_connection' => true,
		'sidebar'             => true,
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
	'connect'        => array(
		'page_title'         => __( 'General settings', 'cloudinary' ),
		'menu_title'         => __( 'General settings', 'cloudinary' ),
		'disconnected_title' => __( 'Setup', 'cloudinary' ),
		'priority'           => 5,
		'sidebar'            => true,
		'settings'           => array(
			array(
				'title'       => __( 'Account Status', 'cloudinary' ),
				'type'        => 'panel',
				'collapsible' => 'open',
				array(
					'slug' => \Cloudinary\Connect::META_KEYS['url'],
					'type' => 'connect',
				),
			),
			array(
				'type' => 'switch_cloud',
			),
		),
	),
	'image_settings' => array(
		'page_title'          => __( 'Image settings', 'cloudinary' ),
		'menu_title'          => __( 'Image settings', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-image.php',
	),
	'video_settings' => array(
		'page_title'          => __( 'Video settings', 'cloudinary' ),
		'menu_title'          => __( 'Video settings', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-video.php',
	),
	'lazy_loading'   => array(),
	'responsive'     => array(
		'page_title'          => __( 'Responsive images', 'cloudinary' ),
		'menu_title'          => __( 'Responsive images', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => array(
			array(
				'type'        => 'panel',
				'title'       => __( 'Image breakpoints', 'cloudinary' ),
				'option_name' => 'media_display',
				array(
					'type' => 'row',
					array(
						'type' => 'column',
						array(
							'type'               => 'on_off',
							'slug'               => 'enable_breakpoints',
							'title'              => __( 'Breakpoints', 'cloudinary' ),
							'optimisation_title' => __( 'Responsive breakpoints', 'cloudinary' ),
							'tooltip_text'       => __(
								'Automatically generate multiple sizes based on the configured breakpoints to enable your images to responsively adjust to different screen sizes. Note that your Cloudinary usage will increase when enabling responsive images.',
								'cloudinary'
							),
							'description'        => __( 'Enable responsive images', 'cloudinary' ),
							'default'            => 'off',
						),
						array(
							'type'      => 'group',
							'condition' => array(
								'enable_breakpoints' => true,
							),
							array(
								'type'         => 'number',
								'slug'         => 'pixel_step',
								'priority'     => 9,
								'title'        => __( 'Breakpoints distance', 'cloudinary' ),
								'tooltip_text' => __( 'The distance between each generated image. Adjusting this will adjust the number of images generated.', 'cloudinary' ),
								'suffix'       => __( 'px', 'cloudinary' ),
								'attributes'   => array(
									'step' => 50,
									'min'  => 50,
								),
								'default'      => 100,
							),
							array(
								'type'         => 'number',
								'slug'         => 'breakpoints',
								'title'        => __( 'Max Images', 'cloudinary' ),
								'tooltip_text' => __(
									'The maximum number of images to be generated. Note that generating large numbers of images will deliver a more optimal version for a wider range of screen sizes but will result in an increase in your usage.  For smaller images, the responsive algorithm may determine that the ideal number is less than the value you specify.',
									'cloudinary'
								),
								'suffix'       => __( 'Valid values: 3-200', 'cloudinary' ),
								'default'      => 3,
								'attributes'   => array(
									'min' => 3,
									'max' => 200,
								),
							),
							array(
								'type'         => 'select',
								'slug'         => 'dpr',
								'priority'     => 8,
								'title'        => __( 'DPR settings', 'cloudinary' ),
								'tooltip_text' => __( 'The device pixel ratio to use for your generated images.', 'cloudinary' ),
								'default'      => 'auto',
								'options'      => array(
									'off'  => __( 'None', 'cloudinary' ),
									'auto' => __( 'Auto', 'cloudinary' ),
									'2'    => __( '2X', 'cloudinary' ),
									'3'    => __( '3X', 'cloudinary' ),
									'4'    => __( '4X', 'cloudinary' ),
								),
							),
							array(
								'type'        => 'number',
								'slug'        => 'max_width',
								'title'       => __( 'Image width limit', 'cloudinary' ),
								'extra_title' => __(
									'The minimum and maximum width of an image created as a breakpoint. Leave “max” as empty to automatically detect based on the largest registered size in WordPress.',
									'cloudinary'
								),
								'prefix'      => __( 'Max', 'cloudinary' ),
								'suffix'      => __( 'px', 'cloudinary' ),
								'default'     => $media->default_max_width(),
								'attributes'  => array(
									'step'         => 50,
									'data-default' => $media->default_max_width(),
								),
							),
							array(
								'type'       => 'number',
								'slug'       => 'min_width',
								'prefix'     => __( 'Min', 'cloudinary' ),
								'suffix'     => __( 'px', 'cloudinary' ),
								'default'    => 800,
								'attributes' => array(
									'step' => 50,
								),
							),
						),
					),
					array(
						'type'  => 'column',
						'class' => array(
							'cld-ui-preview',
						),
						array(
							'type'    => 'breakpoints_preview',
							'title'   => __( 'Preview', 'cloudinary' ),
							'slug'    => 'breakpoints_preview',
							'default' => CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE . 'w_600/sample.jpg',
						),
					),
				),
			),
		),
	),
	'gallery'        => array(),
	'help'           => array(
		'page_title' => __( 'Need help?', 'cloudinary' ),
		'menu_title' => __( 'Need help?', 'cloudinary' ),
		'priority'   => 50,
		'sidebar'    => true,
		array(
			'type'  => 'panel',
			'title' => __( 'Help Centre', 'cloudinary' ),
			array(
				'type'    => 'tag',
				'element' => 'h4',
				'content' => __( 'How can we help', 'cloudinary' ),
			),
			array(
				'type'    => 'span',
				'content' => 'This help center is divided into segments, to make sure you will get the right answer and information as fast as possible. Know that we are here for you!',
			),
			array(
				'type'       => 'row',
				'attributes' => array(
					'wrap' => array(
						'class' => array(
							'help-wrap',
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'   => 'https://cloudinary.com/documentation/wordpress_integration',
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
							'class'  => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'img',
							'attributes' => array(
								'src' => $this->dir_url . 'css/images/documentation.jpg',
							),
						),
						array(
							'type'    => 'tag',
							'element' => 'h4',
							'content' => __( 'Documentation', 'cloudinary' ),
						),
						array(
							'type'    => 'span',
							'content' => __( 'Learn more about how to use the Cloudinary plugin and get the most out of the functionality.', 'cloudinary' ),
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'   => Utils::get_support_link( '-' ),
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
							'class'  => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'img',
							'attributes' => array(
								'src' => $this->dir_url . 'css/images/request.jpg',
							),
						),
						array(
							'type'    => 'tag',
							'element' => 'h4',
							'content' => __( 'Submit a request', 'cloudinary' ),
						),
						array(
							'type'    => 'span',
							'content' => __( 'If you’re encountering an issue or struggling to get the plugin work, open a ticket to contact our support team. To help us debug your queries, we recommend generating a system report.', 'cloudinary' ),
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'  => add_query_arg( 'section', Report::REPORT_SLUG ),
							'class' => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'img',
							'attributes' => array(
								'src' => $this->dir_url . 'css/images/report.jpg',
							),
						),
						array(
							'type'    => 'tag',
							'element' => 'h4',
							'content' => __( 'System Report', 'cloudinary' ),
						),
						array(
							'type'    => 'a',
							'content' => __( "Generate a system report to help debug any specific issues you're having with your Cloudinary media, our support team will usually ask for this when submitting a support request.", 'cloudinary' ),
						),
					),
				),
			),
		),
		array(
			'type'  => 'panel',
			'title' => __( 'FAQ', 'cloudinary' ),
			array(
				array(
					'type'        => 'panel',
					'title'       => __( 'Do I need a Cloudinary account to use the plugin and can I try it out for free?', 'cloudinary' ),
					'enabled'     => static function () {
						return ! get_plugin_instance()->get_component( 'connect' )->is_connected();
					},
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'To use the Cloudinary Plugin and all the functionality that comes with it, you will need to have a Cloudinary Account. If you don’t have an account yet, %1$ssign up%2$s now for a free plan. You’ll start with generous usage limits and when your requirements grow, you can easily upgrade to a plan that best fits your needs.', 'cloudinary' ),
						'<a href="https://cloudinary.com/signup" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I’ve installed the plugin, what happens now?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => __( 'If you left all the settings as default, all your current media will begin syncing to Cloudinary and will start to be optimized and delivered by fast CDN on your website. Once the syncing is complete, you should start seeing improvements in performance across your site.', 'cloudinary' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Which file types are supported?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'Most common media files are supported for optimization and delivery by Cloudinary. For free accounts, you will not be able to deliver PDF or ZIP files by default for security reasons. If this is a requirement, please contact our support team who can help activate this for you.%1$sTo deliver additional file types via Cloudinary, you can extend the functionality of the plugin using the %2$sactions and filters%3$s the plugin exposes for developers.', 'cloudinary' ),
						'<br><br>',
						'<a href="https://cloudinary.com/documentation/wordpress_integration#actions_and_filters" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => sprintf(
						// translators: The HTML markup.
						__( 'I have various other plugins installed, will the Cloudinary Plugin still work?%sI’m having an incompatibility issue with a theme, plugin, or hosting environment, what can I do?', 'cloudinary' ),
						'<br>'
					),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'We’re compatible with most other plugins so we expect it to work absolutely fine. If you do have any issues, please %1$scontact our support team%2$s who will help resolve your issue.', 'cloudinary' ),
						'<a href="' . Utils::get_support_link() . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Can I use the Cloudinary plugin for my eCommerce websites?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'Yes, the plugin has full support for WooCommerce. We also have additional functionality that allows you to add a fully optimized %1$sProduct Gallery%2$s.', 'cloudinary' ),
						'<a href="">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Why are my images loading locally and not from Cloudinary?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'Your images may be loading locally for a number of reasons:%1$sThe asset has been selected to be delivered from WordPress. You can update this for each asset via the WordPress Media Library.%2$sYour asset is %3$sstored outside%4$s of your WordPress %5$sstorage%4$s.%2$sThe asset is not properly synced with Cloudinary. You can find the sync status of your assets in the WordPress Media Library.%6$s', 'cloudinary' ),
						'<ul><li>',
						'</li><li>',
						'<a href="' . add_query_arg( array( 'page' => 'cloudinary_connect#connect.cache_external.external_assets' ), admin_url( 'admin.php' ) ) . '">',
						'</a>',
						'<a href="#">',
						'</li></ul>'
					),
				),
			),
		),
	),
	'wizard'         => array(
		'section'  => 'wizard',
		'slug'     => 'wizard',
	),
);

return apply_filters( 'cloudinary_admin_pages', $settings );
