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
	protected $settings_slug = 'responsive_breakpoints';

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
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array  $tag_element   The tag element set.
	 * @param int    $attachment_id The attachment id.
	 * @param string $original_tag  The original html tag.
	 *
	 * @return array
	 */
	public function add_features( $tag_element, $attachment_id, $original_tag ) {

		if ( ! $this->media->is_cloudinary_url( $tag_element['atts']['src'] ) ) {
			$tag_element['atts']['src'] = $this->media->cloudinary_url( $attachment_id, array(), array(), null, $tag_element['cld-overwrite'] );
		}
		$transformations = $this->media->get_transformations_from_string( $tag_element['atts']['src'] );
		$original_string = Api::generate_transformation_string( $transformations );

		// Check if first is a size.
		if ( isset( $transformations[0] ) && isset( $transformations[0]['width'] ) || isset( $transformations[0]['height'] ) ) {
			// remove the size.
			array_shift( $transformations );
		}
		$size_str = '--size--';
		if ( ! empty( $transformations ) ) {
			$size_str .= '/' . Api::generate_transformation_string( $transformations );
		}
		$tag_element['atts']['src'] = str_replace( $original_string, $size_str, $tag_element['atts']['src'] );
		if ( isset( $tag_element['atts']['srcset'] ) ) {
			unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );
		}
		$tag_element['delivery'] = 'cld';

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
		$lazy         = $this->plugin->get_component( 'lazy_load' );
		$lazy_enabled = $lazy->is_enabled();

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

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {

		$image_breakpoints = $this->settings->get_setting( 'image_breakpoints' );
		// Add pixel step.
		$params = array(
			'type'         => 'number',
			'slug'         => 'pixel_step',
			'priority'     => 9,
			'title'        => __( 'Breakpoints distance', 'cloudinary' ),
			'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
			'suffix'       => __( 'px', 'cloudinary' ),
			'default'      => 100,
			'condition'    => array(
				'use_lazy_loading' => true,
			),
		);
		$image_breakpoints->create_setting( 'pixel_step', $params, $image_breakpoints );

		$self = $this;
		// Add density.
		$params = array(
			'type'         => 'select',
			'slug'         => 'dpr',
			'priority'     => 8,
			'title'        => __( 'DPR settings', 'cloudinary' ),
			'tooltip_text' => __( 'The distance from the original image for responsive breakpoints generation.', 'cloudinary' ),
			'default'      => 'auto',
			'condition'    => array(
				'use_lazy_loading' => true,
			),
			'enabled'      => function () use ( $self ) {
				$settings = $self->settings->get_value();

				return ! isset( $settings['dpr_precise'] );
			},
			'options'      => array(
				'off'  => __( 'None', 'cloudinary' ),
				'auto' => __( 'Auto', 'cloudinary' ),
				'2'    => __( '2X', 'cloudinary' ),
				'3'    => __( '3X', 'cloudinary' ),
				'4'    => __( '4X', 'cloudinary' ),
			),
		);
		$image_breakpoints->create_setting( 'dpr', $params, $image_breakpoints )->get_value();
		// Reset the option parent.
		$this->settings->get_option_parent()->set_value( null );
	}
}
