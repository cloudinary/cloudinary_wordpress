<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;

/**
 * Plugin logger class.
 */
class Support extends Settings_Component implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Logger constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function setup() {
		if ( 'on' === $this->settings->get_value( 'enable_support' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'image_meta_viewer' ) );
		}
	}

	public function image_meta_viewer() {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || 'attachment' !== $screen->id ) {
			return;
		}

		add_meta_box(
			'meta-viewer',
			'Cloudinary Metadata viewer',
			function ( $post ) {

				if ( 'attachment' === $post->post_type ) {
					$meta = wp_get_attachment_metadata( $post->ID );
					echo '<pre>';
					echo wp_json_encode( $meta, JSON_PRETTY_PRINT );
					echo '<h2>All Meta</h2>';
					echo wp_json_encode( get_post_meta( $post->ID ), JSON_PRETTY_PRINT );
					echo '</pre>';
				}

			}
		);

		add_meta_box(
			'sizes-viewer',
			'Image Sizes viewer',
			function ( $post ) {

				if ( 'attachment' === $post->post_type ) {
					$meta = wp_get_attachment_metadata( $post->ID );
					if ( ! empty( $meta['sizes'] ) ) {
						echo '<div class="image-viewer">';
						$keys = array_keys( $meta['sizes'] );
						foreach ( $keys as $size ) {
							$image = wp_get_attachment_image( $post->ID, $size );
							echo $size;
							echo '<div>' . $image . '</div>';
						}
						echo '</div>';
						echo '<style>.image-viewer img{ max-width: 500px; height: auto;}</style>';
					}
				}

			}
		);
	}

	public function settings() {
		$args = array(
			'type'       => 'page',
			'menu_title' => __( 'Support', 'cloudinary' ),
			'tabs'       => array(
				'setup'  => array(
					'page_title' => __( 'Support', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Support and Debug', 'cloudinary' ),
						array(
							'title' => __( 'Enable debug reporting', 'cloudinary' ),
							'type'  => 'on_off',
							'slug'  => 'enable_support',
						),
					),
					array(
						'type' => 'submit',
					),
				),
				'report' => array(
					'page_title' => __( 'Report', 'cloudinary' ),
					'enabled'    => function () {
						$enabled = get_plugin_instance()->settings->get_value( 'enable_support' );

						return 'on' === $enabled;
					},
					array(
						'type'  => 'panel',
						'title' => __( 'Report', 'cloudinary' ),
						array(
							'type' => 'system',
						),
					),

				),
			),
		);

		return $args;
	}
}
