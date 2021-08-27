<?php
/**
 * Dashboard class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Class Dashboard
 *
 * @package Cloudinary
 */
class Dashboard {

	/**
	 * Holds the plugin instance.
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Delivery_Feature constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {
		$pages['dashboard'] = array(
			'page_title'          => __( 'Cloudinary Dashboard', 'cloudinary' ),
			'menu_title'          => __( 'Dashboard', 'cloudinary' ),
			'priority'            => 1,
			'requires_connection' => true,
			'sidebar'             => true,
			'settings'            => array(
				array(
					'type'  => 'panel',
					'title' => __( 'How much you optimize', 'cloudinary' ),
					array(
						'type'  => 'row',
						'align' => 'center',
						array(
							'type' => 'column',
							array(
								'type'       => 'tag',
								'attributes' => array(
									'class' => array(
										'cld-center-column',
										'cld-info-text',
									),
								),
								'content'    => __( 'Average percentage of compression', 'cloudinary' ),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'  => 'progress_sync',
								'value' => 'size_percent',
								'text'  => 'size_difference',
								'poll'  => true,
							),
						),
						array(
							'type' => 'column',
							array(
								'type'       => 'tag',
								'attributes' => array(
									'class' => array(
										'cld-center-column',
										'cld-info-text',
									),
								),
								'content'    => __( 'Percentage of optimized precessing images', 'cloudinary' ),
								array(
									'type'       => 'tag',
									'element'    => 'div',
									'content'    => __( '13 Assets unoptimized by your selection', 'cloudinary' ),
									'attributes' => array(
										'class' => array(
											'description',
										),
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'div',
									'content'    => __( '40/200 Assets being optimized now', 'cloudinary' ),
									'attributes' => array(
										'class' => array(
											'description',
										),
									),
								),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'  => 'progress_sync',
								'value' => 'percentage_synced',
								'text'  => 'total_assets',
								'poll'  => true,
							),
						),
					),
				),
				array(
					'type'  => 'panel',
					'title' => __( 'Your plan status', 'cloudinary' ),
					array(
						'type' => 'row',
						array(
							'type' => 'column',
							array(
								'type'  => 'chart_stat',
								'title' => 'Transformations',
								'stat'  => 'transformations',
							),
						),
						array(
							'type' => 'column',
							array(
								'type'  => 'chart_stat',
								'title' => 'Bandwidth',
								'stat'  => 'bandwidth',
							),
						),
						array(
							'type' => 'column',
							array(
								'type'  => 'chart_stat',
								'title' => 'Storage',
								'stat'  => 'storage',
							),
						),
					),
				),
				array(
					'type'  => 'panel',
					'title' => __( 'Plan details', 'cloudinary' ),
					array(
						'type' => 'row',
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
					),
					array(
						'type' => 'row',
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
						array(
							'type' => 'column',
							array(
								'type'    => 'html',
								'content' => __( 'Icon', 'cloudinary' ),
							),
						),
					),
				),
			),
		);

		return $pages;
	}

}
