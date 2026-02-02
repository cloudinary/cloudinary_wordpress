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
	 * List of Elementor background image settings keys.
	 *
	 * @var array
	 */
	const ELEMENTOR_BACKGROUND_IMAGES = array(
		'background_image',
		'background_hover_image',
		'background_image_tablet',
		'background_hover_image_tablet',
		'background_image_mobile',
		'background_hover_image_mobile',
		'background_overlay_image',
		'background_overlay_hover_image',
		'background_overlay_image_tablet',
		'background_overlay_hover_image_tablet',
		'background_overlay_image_mobile',
		'background_overlay_hover_image_mobile',
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
		if ( ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}

		$settings = $element->get_settings_for_display();
		$media    = $this->plugin->get_component( 'media' );
		$delivery = $this->plugin->get_component( 'delivery' );

		if ( ! $media || ! $delivery ) {
			return;
		}

		foreach ( self::ELEMENTOR_BACKGROUND_IMAGES as $background_key ) {
			$background   = null;
			$is_container = false;

			if ( isset( $settings[ $background_key ] ) ) {
				// Elementor section/column elements store background settings without a leading underscore.
				$background   = $settings[ $background_key ];
				$is_container = true;
			} elseif ( isset( $settings[ '_' . $background_key ] ) ) {
				// Elementor basic elements (e.g. heading) store background settings with a leading underscore.
				$background = $settings[ '_' . $background_key ];
			}

			// If this specific background setting is not set, we can skip it and check for the next setting.
			if ( empty( $background ) || empty( $background['id'] ) ) {
				continue;
			}

			$media_id   = $background['id'];
			$media_size = isset( $background['size'] ) ? $background['size'] : array();

			// Skip if the media is not deliverable via Cloudinary.
			if ( ! $delivery->is_deliverable( $media_id ) ) {
				continue;
			}

			// Generate the Cloudinary URL.
			$cloudinary_url = $media->cloudinary_url( $media_id, $media_size );

			// If URL generation failed, we should leave the original URL within the CSS.
			if ( empty( $cloudinary_url ) ) {
				continue;
			}

			$unique_selector = $this->find_unique_selector( $post_css, $element );
			// If we can't find a unique selector via Elementor's internal API, we can't do any replacement.
			if ( null === $unique_selector ) {
				return;
			}

			// Build the CSS selector and rule for background image replacement.
			$is_hover     = ( strpos( $background_key, 'hover' ) !== false );
			$is_overlay   = ( strpos( $background_key, 'overlay' ) !== false );
			$css_selector = $this->build_background_image_css_selector( $unique_selector, $is_container, $is_hover, $is_overlay );
			$css_rule     = array( 'background-image' => "url('$cloudinary_url')" );

			// Retrieve the specific media query rule for non-desktop devices based on the setting key.
			$media_query = null;
			if ( strpos( $background_key, 'tablet' ) !== false ) {
				$media_query = array( 'max' => 'tablet' );
			} elseif ( strpos( $background_key, 'mobile' ) !== false ) {
				$media_query = array( 'max' => 'mobile' );
			}

			$success = $this->override_elementor_css_rule( $post_css, $css_selector, $css_rule, $media_query );
			if ( ! $success ) {
				// If we couldn't override the CSS rule, likely due to Elementor internal API changes, we should stop further processing.
				return;
			}
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

	/**
	 * Find the unique selector for an Elementor element.
	 * Double-checks if the method exists before calling it, to ensure compatibility with different Elementor versions.
	 *
	 * @param Post         $post_css The post CSS object.
	 * @param Element_Base $element  The Elementor element.
	 *
	 * @return string|null
	 */
	private function find_unique_selector( $post_css, $element ) {
		if ( ! method_exists( $element, 'get_unique_selector' ) ) {
			return null;
		}

		return $post_css->get_element_unique_selector( $element );
	}

	/**
	 * Override the Elementor CSS rule for a specific selector.
	 * Double-checks if the method exists before calling it, to ensure compatibility with different Elementor versions.
	 *
	 * @param Post       $post_css     The post CSS object.
	 * @param string     $css_selector The CSS selector.
	 * @param array      $css_rule     The CSS rule to apply.
	 * @param array|null $media_query  The media query conditions. Null for default (desktop) styles.
	 *
	 * @return bool True if the rule could be overridden, false if the internal Elementor methods aren't available.
	 */
	private function override_elementor_css_rule( $post_css, $css_selector, $css_rule, $media_query ) {
		if ( ! method_exists( $post_css, 'get_stylesheet' ) ) {
			return false;
		}

		$stylesheet = $post_css->get_stylesheet();
		if ( ! method_exists( $stylesheet, 'add_rules' ) ) {
			return false;
		}

		$stylesheet->add_rules( $css_selector, $css_rule, $media_query );
		return true;
	}

	/**
	 * Build the full CSS selector for background image replacement.
	 * We try to match the exact Elementor formatting and rules, so that our CSS overrides the previous rules,
	 * instead of adding new rules within the CSS which may not apply for specific edge cases (e.g. specific child elements).
	 *
	 * @param string $unique_selector The unique selector for the element.
	 * @param bool   $is_container    Whether the element is a container (section/column).
	 * @param bool   $is_hover        Whether the background is for hover state.
	 * @param bool   $is_overlay      Whether the background is for an overlay.
	 *
	 * @return string
	 */
	private function build_background_image_css_selector( $unique_selector, $is_container, $is_hover, $is_overlay ) {
		if ( $is_overlay ) {
			// Overlay backgrounds need to target multiple pseudo-elements and child elements.
			$overlay_selector = sprintf(
				'%1$s%2$s::before,
				%1$s%2$s > .elementor-background-video-container::before,
				%1$s%2$s > .e-con-inner > .elementor-background-video-container::before,
				%1$s > .elementor-background-slideshow%2$s::before,
				%1$s > .e-con-inner > .elementor-background-slideshow%2$s::before',
				$unique_selector,
				$is_hover ? ':hover' : ''
			);

			// For non-hover overlays, we need to also target motion effects layers.
			if ( ! $is_hover ) {
				$overlay_selector = sprintf(
					'%1$s,
					%2$s > .elementor-motion-effects-container > .elementor-motion-effects-layer::before',
					$overlay_selector,
					$unique_selector
				);
			}

			// Replace any newline and extra spaces to match the exact Elementor formatting.
			return preg_replace( '/\s+/', ' ', $overlay_selector );
		}
		// For hover backgrounds, we simply append :hover to the unique selector.
		if ( $is_hover ) {
			return $unique_selector . ':hover';
		}

		// For non-container elements, we can return the unique selector as is.
		if ( ! $is_container ) {
			return $unique_selector;
		}

		// For container elements, we need to target both the element itself and its motion effects layers.
		return sprintf(
			'%1$s:not(.elementor-motion-effects-element-type-background), %1$s > .elementor-motion-effects-container > .elementor-motion-effects-layer',
			$unique_selector
		);
	}
}
