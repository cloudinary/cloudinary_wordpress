<?php
/**
 * Asset Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use function Cloudinary\get_plugin_instance;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Asset_Preview extends Asset {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'preview';

	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {
		$attachment = filter_input( INPUT_GET, 'asset', FILTER_SANITIZE_NUMBER_INT );
		$dataset    = $this->assets->get_asset( $attachment, 'dataset' );

		// Check if the attachment is a video.
		$is_video = wp_attachment_is( 'video', $attachment );

		if ( $is_video ) {
			$dataset['type'] = 'video';
		}

		// Image preview structure.
		$struct['element']                 = 'div';
		$struct['attributes']['id']        = 'cld-asset-edit';
		$struct['attributes']['data-item'] = $dataset;
		$struct['render']                  = true;

		return $struct;
	}

	/**
	 * Get available grid positioning options for asset preview.
	 *
	 * @return array Array of grid position strings representing compass directions and center.
	 */
	public static function get_grid_options() {
		return array( 'north_west', 'north', 'north_east', 'west', 'center', 'east', 'south_west', 'south', 'south_east' );
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		$attachment = filter_input( INPUT_GET, 'asset', FILTER_SANITIZE_NUMBER_INT );

		// Check if the attachment is a video.
		if ( $attachment && wp_attachment_is( 'video', $attachment ) ) {
			// Get the actual video URL.
			$video_url = wp_get_attachment_url( $attachment );

			// Setup video preview JavaScript.
			$url         = CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO;
			$preview_src = $url;
			$script_data = array(
				'url'         => $url,
				'preview_url' => $preview_src,
				'file'        => $video_url,
				'error'       => esc_html__( 'Invalid transformations or error loading preview.', 'cloudinary' ),
				'valid_types' => \Cloudinary\Connect\Api::$transformation_index['video'],
			);
			wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );
			wp_add_inline_script( 'cloudinary', 'CLD_GLOBAL_TRANSFORMATIONS.video = ' . wp_json_encode( $script_data ), 'before' );

			// Get the actual cloud name from the plugin configuration.
			$plugin     = get_plugin_instance();
			$cloud_name = $plugin->get_component( 'connect' )->get_cloud_name();

			$player   = array();
			$player[] = 'var cld = cloudinary.Cloudinary.new({ cloud_name: \'' . esc_js( $cloud_name ) . '\', analytics: false });';
			wp_add_inline_script( 'cld-player', implode( $player ) );
		}

		parent::pre_render();
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'cloudinary-asset-edit', $plugin->dir_url . 'js/asset-edit.js', array(), $plugin->version, true );
		wp_enqueue_media();

		// Check if the attachment is a video and enqueue video player assets.
		$attachment = filter_input( INPUT_GET, 'asset', FILTER_SANITIZE_NUMBER_INT );
		if ( $attachment && wp_attachment_is( 'video', $attachment ) ) {
			wp_enqueue_style( 'cld-player' );
			wp_enqueue_script( 'cld-player' );
		}
	}
}
