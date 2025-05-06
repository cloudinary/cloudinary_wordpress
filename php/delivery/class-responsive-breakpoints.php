<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Delivery_Feature;
use Cloudinary\Connect\Api;
use Cloudinary\Utils;

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
		if ( 'upload' !== $this->media->get_media_delivery( $tag_element['id'] ) ) {
			return $tag_element;
		}

		/**
		 * Do not add the responsive breakpoint features.
		 *
		 * @hook   cloudinary_skip_responsive_breakpoints
		 * @since  3.2.4
		 * @default {false}
		 *
		 * @param $skip_features {bool}  True to skip adding in the features.
		 * @param $tag_element   {array} The tag element.
		 *
		 * @return {bool}
		 */
		if ( apply_filters( 'cloudinary_skip_responsive_breakpoints', false, $tag_element ) ) {
			return $tag_element;
		}

		// Bypass file formats that shouldn't have Responsive Images.
		if (
			in_array(
				$tag_element['format'],
				/**
				 * Filter out file formats for Responsive Images.
				 *
				 * @hook  cloudinary_responsive_images_bypass_formats
				 * @since 3.0.9
				 *
				 * @param $formats {array) The list of formats to exclude.
				 */
				apply_filters( 'cloudinary_responsive_images_bypass_formats', array( 'svg' ) ),
				true
			)
		) {
			return $tag_element;
		}

		if ( Utils::is_amp() ) {
			$tag_element['atts']['layout'] = 'responsive';
		} else {
			$tag_element['atts']['data-responsive'] = true;
		}
		unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );

		$lazy = $this->plugin->get_component( 'lazy_load' );

		/**
		 * Short circuit the lazy load.
		 *
		 * @hook  cloudinary_lazy_load_bypass
		 * @since 3.0.9
		 *
		 * @param $short_circuit {bool}  The short circuit value.
		 * @param $tag_element   {array} The tag element.
		 */
		if ( is_null( $lazy ) || ! $lazy->is_enabled() || Utils::is_amp() || apply_filters( 'cloudinary_lazy_load_bypass', false, $tag_element ) ) {
			$tag_element = $this->apply_breakpoints( $tag_element );
		}

		return $tag_element;
	}

	/**
	 * Apply srcset breakpoints if lazy loading is off.
	 *
	 * @param array $tag_element The tag element array.
	 *
	 * @return array
	 */
	protected function apply_breakpoints( $tag_element ) {
		if ( empty( $tag_element['id'] ) ) {
			return $tag_element;
		}

		$settings  = $this->settings->get_value( 'media_display' );
		$max       = $settings['max_width'];
		$min       = $settings['min_width'];
		$width     = (int) $tag_element['width'];
		$height    = (int) $tag_element['height'];
		$debug_key = 'responsive_breakpoints_' . $tag_element['id'];

		if ( ! $width || ! $height ) {
			Utils::log( 'Missing width or height for image with ID: ' . $tag_element['id'], $debug_key );
			return $tag_element;
		}

		$size_tag            = '-' . $width . 'x' . $height . '.';
		$step                = $settings['pixel_step'];
		$ratio               = $width / $height;
		$src                 = $tag_element['atts']['src'];
		$size_transformation = $this->media->get_crop_from_transformation( $this->media->get_transformations_from_string( $src ) );
		$size_string         = Api::generate_transformation_string( array( $size_transformation ) );
		$breakpoints         = array();
		while ( $max >= $min ) {
			if ( $width >= $max ) {
				$size_transformation['width']  = $max;
				$size_transformation['height'] = floor( $max / $ratio );
				$new_size_tag                  = '-' . $size_transformation['width'] . 'x' . $size_transformation['height'] . '.';
				$new_size                      = Api::generate_transformation_string( array( $size_transformation ) );
				$new_url                       = str_replace( $size_string, $new_size, $src );
				$new_url                       = str_replace( $size_tag, $new_size_tag, $new_url );
				$breakpoints[]                 = $new_url . ' ' . $max . 'w';
			}
			$max -= $step;
		}

		if ( ! empty( $breakpoints ) ) {
			array_unshift( $breakpoints, $src . ' ' . $width . 'w' );
			$tag_element['atts']['srcset'] = implode( ', ', $breakpoints );
			$tag_element['atts']['sizes']  = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
		}

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
