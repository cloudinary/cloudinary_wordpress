<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;
use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Settings\Setting;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Responsive_Breakpoints implements Setup, Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'responsive_breakpoints';

	/**
	 * Flag to determine if we're in the breakpoints change.
	 *
	 * @var bool
	 */
	protected $doing_responsive = false;

	/**
	 * Holds the settings.
	 *
	 * @var Setting
	 */
	protected $settings;

	/**
	 * Holds the current post.
	 *
	 * @var int
	 */
	protected $current_post;

	/**
	 * Responsive_Breakpoints constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		$this->media  = $plugin->get_component( 'media' );
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
			$tag_element['atts']['src'] = $this->media->cloudinary_url( $attachment_id );
		}
		$transformations = $this->media->get_transformations_from_string( $tag_element['atts']['src'] );
		$original_string = Api::generate_transformation_string( $transformations );

		// Check if first is a size.
		if ( isset( $transformations[0] ) && isset( $transformations[0]['width'] ) || isset( $transformations[0]['height'] ) ) {
			// remove the size.
			array_shift( $transformations );
		}
		$size_str                   = '--size--/' . Api::generate_transformation_string( $transformations );
		$data_url                   = str_replace( $original_string, $size_str, $tag_element['atts']['src'] );
		$tag_element['atts']['src'] = $data_url;
		if ( isset( $tag_element['atts']['srcset'] ) ) {
			unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );
		}

		return $tag_element;
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {

		return ! is_admin();
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
		wp_register_script( 'cld-responsive-breakpoints', $this->plugin->dir_url . 'js/responsive-breakpoints.js', null, $this->plugin->version, false );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'cld-responsive-breakpoints' );
		$config = $this->settings->get_value();
		wp_add_inline_script( 'cld-responsive-breakpoints', 'var CLDLB = ' . wp_json_encode( $config ), 'before' );
		if ( isset( $config['use_lazy_loading'] ) && 'on' === $config['use_lazy_loading'] && 'on' === $config['enable_breakpoints'] ) {
			add_filter( 'cloudinary_pre_image_tag', array( $this, 'add_features' ), 10, 3 );
		}
	}

	/**
	 * Setup the class.
	 */
	public function setup() {
		$this->register_settings();
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'remove_legacy_breakpoints' ) );
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
		remove_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10 );

		return $structs;
	}

	/**
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {
		return array();
	}

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {

		$media_settings    = $this->media->get_settings()->get_setting( 'image_display' );
		$image_breakpoints = $media_settings->get_setting( 'image_breakpoints' );
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
		$media_settings->get_option_parent()->set_value( null );

		$this->settings = $media_settings;
	}
}
