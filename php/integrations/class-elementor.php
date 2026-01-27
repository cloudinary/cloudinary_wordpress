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
		add_action( 'elementor/element/parse_css', array( $this, 'replace_bg_images_in_css' ), 10, 2 );
	}

	/**
	 * Replace all background images URLs with Cloudinary URLs, within the generated Elementor CSS file.
	 *
	 * @param Post         $post_css The post CSS object.
	 * @param Element_Base $element  The Elementor element.
	 * @return void
	 */
	public function replace_bg_images_in_css( $post_css, $element ) {
		$settings = $element->get_settings_for_display();
		$media    = $this->plugin->get_component( 'media' );
		$delivery = $this->plugin->get_component( 'delivery' );

		if ( ! $media || ! $delivery ) {
			return;
		}

		foreach ( self::ELEMENTOR_BACKGROUND_IMAGES as $background_key => $background_data ) {
			// We need to have both URL and ID from the image to proceed.
			if ( ! isset( $settings[ $background_key ]['url'], $settings[ $background_key ]['id'] ) ) {
				continue;
			}

			$original_url = $settings[ $background_key ]['url'];
			$media_id     = $settings[ $background_key ]['id'];
			$media_size   = isset( $settings[ $background_key ]['size'] ) ? $settings[ $background_key ]['size'] : array();

			// Skip if the media is not deliverable via Cloudinary.
			if ( ! $delivery->is_deliverable( $media_id ) ) {
				continue;
			}

			// If the original URL is already a Cloudinary URL, use it directly; otherwise, generate the Cloudinary URL.
			$cloudinary_url = $media->is_cloudinary_url( $original_url )
				? $original_url
				: $media->cloudinary_url( $media_id, $media_size );

			// Build the CSS selector and rule.
			$css_selector = $post_css->get_element_unique_selector( $element ) . $background_data['suffix'];
			$css_rule     = array( 'background-image' => "url('$cloudinary_url')" );

			// Retrieve the specific media query rule for non-desktop devices.
			$media_query = null;
			$device      = $background_data['device']; // either 'desktop', 'tablet' or 'mobile'.
			if ( 'desktop' !== $device ) {
				$media_query = array( 'max' => $device );
			}

			$post_css->get_stylesheet()->add_rules( $css_selector, $css_rule, $media_query );
		}
	}
}
