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
		$image   = filter_input( INPUT_GET, 'asset', FILTER_SANITIZE_NUMBER_INT );
		$dataset = $this->assets->get_asset( $image, 'dataset' );

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
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'cloudinary-asset-edit', $plugin->dir_url . 'js/asset-edit.js', array(), $plugin->version, true );
		wp_enqueue_media();
	}
}
