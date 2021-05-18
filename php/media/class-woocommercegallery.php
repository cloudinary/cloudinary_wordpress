<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

/**
 * Class WooCommerceGallery.
 *
 * Handles gallery for woo.
 */
class WooCommerceGallery {
	/**
	 * The gallery instance.
	 *
	 * @var Gallery
	 */
	private $gallery;

	/**
	 * Init woo gallery.
	 *
	 * @param Gallery $gallery Gallery instance.
	 */
	public function __construct( Gallery $gallery ) {
		$this->gallery = $gallery;

		if ( self::woocommerce_active() && $this->enabled() ) {
			$this->setup_hooks();
		}
	}

	/**
	 * Register frontend assets for the gallery.
	 */
	public function enqueue_gallery_library() {
		$product = wc_get_product();
		if ( empty( $product ) ) {
			return;
		}

		$images = (array) $product->get_gallery_image_ids();
		array_unshift( $images, get_post_thumbnail_id() );

		$assets = $this->gallery->get_image_data( array_filter( $images ) );

		if ( $assets ) {
			$json_assets = wp_json_encode( $assets );
			wp_add_inline_script( Gallery::GALLERY_LIBRARY_HANDLE, "CLD_GALLERY_CONFIG.mediaAssets = {$json_assets};" );
		}
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Whether the replacement toggle is on or off
	 *
	 * @return bool
	 */
	public function enabled() {
		return 'on' === $this->gallery->settings->get_value( 'gallery_woocommerce_enabled' );
	}

	/**
	 * Maybe enqueue the gallery scripts.
	 *
	 * @param bool $can Default value.
	 *
	 * @return bool
	 */
	public function maybe_enqueue_scripts( $can ) {
		if ( is_singular( 'product' ) ) {
			$can = true;
		}

		return $can;
	}

	/**
	 * Setup hooks for the gallery.
	 */
	public function setup_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gallery_library' ) );

		add_filter(
			'cloudinary_gallery_html_container',
			static function () {
				return '.woocommerce-product-gallery';
			}
		);

		if ( ! is_admin() && self::woocommerce_active() ) {
			add_filter( 'woocommerce_single_product_image_thumbnail_html', '__return_empty_string' );
		}

		add_filter( 'cloudinary_enqueue_gallery_script', array( $this, 'maybe_enqueue_scripts' ) );
	}
}
