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
class Dashboard extends Settings_Component {

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'new_dashboard';

	/**
	 * Get the settings aregs.
	 *
	 * @return array
	 */
	public function settings() {
		$args = array(
			'menu_title' => __( 'Dashboard', 'cloudinary' ),
			'page_title' => __( 'Dashboard', 'cloudinary' ),
			'type'       => 'page',
			array(
				'type' => 'row',
				array(
					'type' => 'column',
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
				array(
					'type'  => 'column',
					'class' => array(
						'cld-ui-accordion',
					),
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
							'type'    => 'html',
							'content' => __( 'Some stuff', 'cloudinary' ),
						),
					),
					array(
						'type'        => 'panel',
						'title'       => __( 'Extensions', 'cloudinary' ),
						'description' => __( '1 Active extension', 'cloudinary' ),
						'collapsible' => 'closed',
						array(
							'type'    => 'html',
							'content' => __( 'Some stuff', 'cloudinary' ),
						),
					),
				),
			),
		);

		return $args;
	}

}
