<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Delivery_Feature;
use Cloudinary\Connect\Api;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Responsive_Breakpoints extends Delivery_Feature {

	/**
	 * The feature application priority.
	 *
	 * @var int
	 */
	protected $priority = 9;

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'media_display';

	/**
	 * Holds the enabler slug.
	 *
	 * @var string
	 */
	protected $enable_slug = 'enable_breakpoints';

	/**
	 * Setup hooks used when enabled.
	 */
	protected function setup_hooks() {
		add_action( 'cloudinary_init_delivery', array( $this, 'remove_srcset_filter' ) );
		add_filter( 'cloudinary_apply_breakpoints', '__return_false' );
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {
		$tag_element['atts']['data-responsive'] = true;

		return $tag_element;
	}

	/**
	 * Remove the legacy breakpoints sync type and filters.
	 *
	 * @param array $structs The sync types structure.
	 *
	 * @return array
	 */
	public function remove_legacy_breakpoints( $structs ) {
		unset( $structs['breakpoints'] );

		return $structs;
	}

	/**
	 * Check to see if Breakpoints are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$lazy = $this->plugin->get_component( 'lazy_load' );

		return ! is_null( $lazy ) && $lazy->is_enabled() && parent::is_enabled();
	}

	/**
	 * Remove the srcset filter.
	 */
	public function remove_srcset_filter() {
		remove_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10 );
	}

	/**
	 * Setup the class.
	 */
	public function setup() {
		parent::setup();
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'remove_legacy_breakpoints' ) );
	}

	/**
	 * Create Settings.
	 */
	protected function create_settings() {
		$this->settings = $this->media->get_settings()->get_setting( 'image_display' );
	}
}
