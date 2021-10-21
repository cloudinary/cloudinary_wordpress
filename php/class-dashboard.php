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
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Class constructor.
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
						'type'       => 'row',
						'align'      => 'center',
						'attributes' => array(
							'wrap' => array(
								'class' => array(
									'cld-optimize-panel',
								),
							),
						),
						array(
							'type'  => 'column',
							'width' => 'auto',
							array(
								'type'  => 'progress_sync',
								'value' => 'optimized_percent',
								'text'  => 'optimized_info',
								'color' => '#58c4d8',
								'poll'  => true,
							),
						),
						array(
							'type'  => 'column',
							'width' => 'auto',
							array(
								'type'       => 'tag',
								'attributes' => array(
									'class' => array(
										'cld-center-column',
										'cld-info-text',
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'h3',
									'content'    => sprintf(
										// translators: The BR tag.
										__( 'Percentage of assets%soptimized by Cloudinary', 'cloudinary' ),
										'<br>'
									),
									'attributes' => array(
										'class' => array(
											'cld-progress-box-title',
										),
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'div',
									'content'    => '&nbsp;',
									'attributes' => array(
										'data-text' => 'unoptimized_status_text',
										'class'     => array(
											'cld-stat-text',
										),
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'div',
									'content'    => '&nbsp;',
									'attributes' => array(
										'data-text' => 'optimized_status_text',
										'class'     => array(
											'cld-stat-text',
										),
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'a',
									'content'    => '&nbsp;',
									'attributes' => array(
										'href'      => add_query_arg(
											array(
												'cloudinary-filter' => Sync::META_KEYS['sync_error'],
											),
											admin_url( 'upload.php' )
										),
										'data-text' => 'error_count_hr',
										'class'     => array(
											'cld-stat-text',
										),
									),
								),
							),
						),
						array(
							'type'  => 'column',
							'width' => '50%',
							array(
								'type'        => 'progress_bar',
								'title'       => __( 'Original size', 'cloudinary' ),
								'percent_key' => 'original_size_percent',
								'value_key'   => 'original_size_hr',
								'color'       => '#304ec4',
							),
							array(
								'type'        => 'progress_bar',
								'title'       => __( 'Optimized size', 'cloudinary' ),
								'percent_key' => 'optimized_size_percent',
								'value_key'   => 'optimized_size_hr',
								'color'       => '#58c4d8',
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'cld-stat-percent',
									),
								),
								array(
									'type'    => 'tag',
									'element' => 'span',
									array(
										'type'       => 'tag',
										'element'    => 'h2',
										'content'    => '0%',
										'attributes' => array(
											'data-text' => 'optimized_diff_percent',
										),
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'span',
									'content'    => __( "That's the amount you've saved by using Cloudinary.", 'cloudinary' ),
									'attributes' => array(
										'class' => array(
											'cld-stat-percent-text',
										),
									),
								),
							),
						),
						array(
							'type'  => 'column',
							'width' => 'auto',
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'cld-stat-legend',
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'span',
									'attributes' => array(
										'class' => array(
											'cld-stat-legend-dot',
											'blue-dot',
										),
									),
									'content'    => '&nbsp;',
								),
								array(
									'type'    => 'tag',
									'element' => 'span',
									'content' => __( 'Unoptimized', 'cloudinary' ),
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'cld-stat-legend',
									),
								),
								array(
									'type'       => 'tag',
									'element'    => 'span',
									'attributes' => array(
										'class' => array(
											'cld-stat-legend-dot',
											'aqua-dot',
										),
									),
									'content'    => '&nbsp;',
								),
								array(
									'type'    => 'tag',
									'element' => 'span',
									'content' => __( 'Optimized', 'cloudinary' ),
								),
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
								'color' => '#58c4d8',
							),
						),
						array(
							'type' => 'column',
							array(
								'type'  => 'chart_stat',
								'title' => 'Storage',
								'stat'  => 'storage',
								'color' => '#ec4c4f',
							),
						),
					),
				),
				array(
					'type'  => 'panel',
					'title' => __( 'Plan details', 'cloudinary' ),
					array(
						'type' => 'plan_details',
					),
				),
			),
		);

		return $pages;
	}

}
