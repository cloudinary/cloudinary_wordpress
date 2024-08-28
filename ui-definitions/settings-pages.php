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
		'settings'            => include $this->dir_path . 'ui-definitions/settings-image.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	),
	'video_settings' => array(
		'page_title'          => __( 'Video settings', 'cloudinary' ),
		'menu_title'          => __( 'Video settings', 'cloudinary' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-video.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
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
				'title'       => __( 'Responsive images', 'cloudinary' ),
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
						array(
							'type'               => 'on_off',
							'slug'               => 'enable_breakpoints',
							'title'              => __( 'Breakpoints', 'cloudinary' ),
							'optimisation_title' => __( 'Responsive images', 'cloudinary' ),
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
								'title'        => __( 'Breakpoint distance', 'cloudinary' ),
								'tooltip_text' => __( 'The distance between each generated image. Adjusting this will adjust the number of images generated.', 'cloudinary' ),
								'suffix'       => __( 'px', 'cloudinary' ),
								'attributes'   => array(
									'step' => 50,
									'min'  => 50,
								),
								'default'      => 200,
							),
							array(
								'type'         => 'number',
								'slug'         => 'breakpoints',
								'title'        => __( 'Max images', 'cloudinary' ),
								'tooltip_text' => __(
									'The maximum number of images to be generated. Note that generating large numbers of images will deliver a more optimal version for a wider range of screen sizes but will result in an increase in your usage.  For smaller images, the responsive algorithm may determine that the ideal number is less than the value you specify.',
									'cloudinary'
								),
								'suffix'       => __( 'Recommended value: 3-40', 'cloudinary' ),
								'attributes'   => array(
									'min' => 3,
									'max' => 100,
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
								'default'    => 200,
								'attributes' => array(
									'step' => 50,
								),
							),
						),
					),
					array(
						'type'      => 'column',
						'tab_id'    => 'preview',
						'class'     => array(
							'cld-ui-preview',
						),
						'condition' => array(
							'enable_breakpoints' => true,
						),
						array(
							'type'    => 'breakpoints_preview',
							'title'   => __( 'Preview', 'cloudinary' ),
							'slug'    => 'breakpoints_preview',
							'default' => CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE . 'w_600/sample.jpg',
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
							'Watch free lessons on how to use the Responsive Images Settings in the %1$sCloudinary Academy%2$s.',
							'cloudinary'
						),
						'<a href="https://training.cloudinary.com/learn/course/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-1h85/lessons/lazily-loading-and-delivering-responsive-images-1003?page=1" target="_blank" rel="noopener noreferrer">',
						'</a>'
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
			'title' => __( 'Help Center', 'cloudinary' ),
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
					'type'                => 'column',
					'attributes'          => array(
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
							'href'   => 'https://training.cloudinary.com/courses/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-zf3x',
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
								'src' => $this->dir_url . 'css/images/academy.jpg',
							),
						),
						array(
							'type'    => 'tag',
							'element' => 'h4',
							'content' => __( 'Cloudinary Academy', 'cloudinary' ),
						),
						array(
							'type'    => 'a',
							'content' => __( "With Cloudinary's plugin, it is easy to enhance your WordPress site's images and videos! In this self-paced course, our expert instructor will show you how to configure the plugin and use its most powerful features - asset management, image and video optimization, product gallery creation and more.", 'cloudinary' ),
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
							'href'   => static function () {
								$args = array(
									'tf_360017815680' => '-',
								);
								return Utils::get_support_link( $args );
							},
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
							'content' => __( 'If you’re encountering an issue or struggling to get the plugin to work, open a ticket to contact our support team. To help us debug your queries, we recommend generating a system report.', 'cloudinary' ),
						),
					),
				),
				array(
					'type'                => 'column',
					'requires_connection' => true,
					'attributes'          => array(
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
					'title'       => __( 'Do I need a Cloudinary account to use the Cloudinary plugin and can I try it out for free?', 'cloudinary' ),
					'enabled'     => static function () {
						return ! get_plugin_instance()->get_component( 'connect' )->is_connected();
					},
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'To use the Cloudinary plugin and all the functionality that comes with it, you will need to have a Cloudinary Account. %1$sIf you don’t have an account yet, %2$ssign up%3$s now for a free Cloudinary Programmable Media account%4$s. You’ll start with generous usage limits and when your requirements grow, you can easily upgrade to a plan that best fits your needs.', 'cloudinary' ),
						'<b>',
						'<a href="https://cloudinary.com/signup?source=wp&utm_source=wp&utm_medium=wporgmarketplace&utm_campaign=wporgmarketplace" target="_blank" rel="noopener noreferrer">',
						'</a>',
						'</b>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I’ve installed the Cloudinary plugin, what happens now?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => __( 'If you left all the settings as default, all your current media will begin syncing with Cloudinary. Once syncing is complete, your media will be optimized and delivered using Cloudinary URLs and you should begin seeing improvements in performance across your site.', 'cloudinary' ),
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
					'title'       => __( 'Does the Cloudinary plugin require an active WordPress REST API connection?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( ' To function correctly, the Cloudinary plugin requires an active WordPress REST API connection. Ensure your WordPress setup, including multisite or headless configurations, has the REST API enabled and active for seamless plugin operation.%1$sFor more information, see %2$sWordPress’s REST API Handbook%3$s.', 'cloudinary' ),
						'<br><br>',
						'<a href="https://developer.wordpress.org/rest-api/" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( "I'm having an incompatibility issue with a theme, plugin, or hosting environment, what can I do?", 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => static function () {
						return sprintf(
							// translators: The HTML markup.
							__( 'We’re compatible with most other plugins so we expect it to work absolutely fine. If you do have any issues, please %1$scontact our support team%2$s who will help resolve your issue.', 'cloudinary' ),
							'<a href="' . Utils::get_support_link() . '" target="_blank" rel="noopener noreferrer">',
							'</a>'
						);
					},
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Can I use the Cloudinary plugin for my eCommerce websites?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'Yes, the Cloudinary plugin has full support for WooCommerce. We also have additional functionality that allows you to add a fully optimized %1$sProduct Gallery%2$s.', 'cloudinary' ),
						'<a href="' . esc_url( add_query_arg( 'page', 'cloudinary_gallery' ) ) . '">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Why are my images loading locally and not from Cloudinary?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						// translators: The HTML markup.
						__( 'Your images may be loading locally for a number of reasons:%1$sThe asset has been selected to be delivered from WordPress. You can update this for each asset via the %5$sWordPress Media Library%4$s.%2$sYour asset is %3$sstored outside%4$s of your WordPress storage.%2$sThe asset is not properly synced with Cloudinary. You can find the sync status of your assets in the %5$sWordPress Media Library%4$s.%6$s', 'cloudinary' ),
						'<ul><li>',
						'</li><li>',
						'<a href="' . add_query_arg( array( 'page' => 'cloudinary_connect#connect.cache_external.external_assets' ), admin_url( 'admin.php' ) ) . '">',
						'</a>',
						'<a href="' . admin_url( 'upload.php' ) . '">',
						'</li></ul>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'How do I handle a CLDBind error which is causing issues with lazy loading?', 'cloudinary' ),
					'collapsible' => 'closed',
					'content'     => __( 'The Cloudinary lazy loading scripts must be loaded in the page head. Ensure your site or any 3rd party plugins are not setup to move these scripts.', 'cloudinary' ),
				),
			),
		),
	),
	'wizard'         => array(
		'section' => 'wizard',
		'slug'    => 'wizard',
	),
	'debug'          => array(
		'section' => 'debug',
		'slug'    => 'debug',
		array(
			'type'  => 'panel',
			'title' => __( 'Debug log', 'cloudinary' ),
			array(
				'type' => 'debug',
			),
		),
	),
	'edit_asset'     => array(
		'page_title'          => __( 'Edit asset', 'cloudinary' ),
		'section'             => 'edit-asset',
		'slug'                => 'edit_asset',
		'requires_connection' => true,
		array(
			'type' => 'row',
			array(
				'type'       => 'column',
				'width'      => '950px',
				'attributes' => array(
					'wrap' => array(
						'style' => 'margin: 0 auto;max-width:1200px;',
					),
				),
				array(
					'type' => 'referrer_link',
				),
				array(
					'type' => 'panel',
					array(
						'type' => 'asset_preview',
					),
				),
			),
		),
	),
);

return apply_filters( 'cloudinary_admin_pages', $settings );
