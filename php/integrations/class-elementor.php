<?php
/**
 * Elementor integration class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Integrations;

use Elementor\Core\Files\CSS\Post;
use Elementor\Element_Base;
use Elementor\Plugin;

/**
 * Class Elementor
 */
class Elementor extends Integrations {

	/**
	 * List of Elementor background image settings keys, along with their device and CSS suffix.
	 *
	 * @var array
	 */
	const ELEMENTOR_BACKGROUND_IMAGES = array(
		'_background_image'              => array(
			'device' => 'desktop',
			'suffix' => '',
		),
		'_background_hover_image'        => array(
			'device' => 'desktop',
			'suffix' => ':hover',
		),
		'_background_image_tablet'       => array(
			'device' => 'tablet',
			'suffix' => '',
		),
		'_background_hover_image_tablet' => array(
			'device' => 'tablet',
			'suffix' => ':hover',
		),
		'_background_image_mobile'       => array(
			'device' => 'mobile',
			'suffix' => '',
		),
		'_background_hover_image_mobile' => array(
			'device' => 'mobile',
			'suffix' => ':hover',
		),
	);

	/**
	 * Check if the integration can be enabled.
	 *
	 * @return bool
	 */
	public function can_enable() {
		return class_exists( 'Elementor\Plugin' );
	}

	/**
	 * Register hooks for the integration.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'elementor/element/parse_css', array( $this, 'replace_background_images_in_css' ), 10, 2 );
		add_action( 'cloudinary_flush_cache', array( $this, 'clear_elementor_css_cache' ) );
	}

	/**
	 * Replace all background images URLs with Cloudinary URLs, within the generated Elementor CSS file.
	 *
	 * @param Post         $post_css The post CSS object.
	 * @param Element_Base $element  The Elementor element.
	 * @return void
	 */
	public function replace_background_images_in_css( $post_css, $element ) {
		$settings = $element->get_settings_for_display();
		$media    = $this->plugin->get_component( 'media' );
		$delivery = $this->plugin->get_component( 'delivery' );

		if ( ! $media || ! $delivery ) {
			return;
		}

		foreach ( self::ELEMENTOR_BACKGROUND_IMAGES as $background_key => $background_data ) {
			// We need to have the ID from the image to proceed.
			if ( ! isset( $settings[ $background_key ]['id'] ) ) {
				continue;
			}

			$media_id   = $settings[ $background_key ]['id'];
			$media_size = isset( $settings[ $background_key ]['size'] ) ? $settings[ $background_key ]['size'] : array();

			// Skip if the media is not deliverable via Cloudinary.
			if ( ! $delivery->is_deliverable( $media_id ) ) {
				continue;
			}

			// Generate the Cloudinary URL.
			$cloudinary_url = $media->cloudinary_url( $media_id, $media_size );

			// Build the CSS selector and rule.
			$css_selector = $post_css->get_element_unique_selector( $element ) . $background_data['suffix'];
			$css_rule     = array( 'background-image' => "url('$cloudinary_url')" );

			// Retrieve the specific media query rule for non-desktop devices.
			$media_query = null;
			if ( 'desktop' !== $background_data['device'] ) {
				$media_query = array( 'max' => $background_data['device'] );
			}

			// Override the CSS rule in Elementor.
			$post_css->get_stylesheet()->add_rules( $css_selector, $css_rule, $media_query );
		}
	}

	/**
	 * Clear Elementor CSS cache.
	 * This is called when Cloudinary cache is flushed, so that any change in media URLs is reflected in Elementor CSS files.
	 *
	 * @return void
	 */
	public function clear_elementor_css_cache() {
		if ( class_exists( 'Elementor\Plugin' ) ) {
			$elementor = Plugin::instance();
			$elementor->files_manager->clear_cache();
		}
	}
}
