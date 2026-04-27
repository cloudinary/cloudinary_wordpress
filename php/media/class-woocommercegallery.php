<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Media;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

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
	 * The media instance.
	 *
	 * @var Media
	 */
	private $media;

	/**
	 * Init woo gallery.
	 *
	 * @param Gallery $gallery Gallery instance.
	 * @param Media   $media   Media instance.
	 */
	public function __construct( Gallery $gallery, Media $media ) {
		$this->gallery = $gallery;
		$this->media   = $media;

		if ( self::woocommerce_active() ) {
			$this->setup_rest_hooks();

			if ( $this->enabled() ) {
				$this->setup_hooks();
			}
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
	 * Setup hooks for the REST API integration.
	 */
	public function setup_rest_hooks() {
		add_filter( 'rest_request_before_callbacks', array( $this, 'pre_process_product_images' ), 10, 3 );
	}

	/**
	 * Setup hooks for the gallery.
	 */
	public function setup_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gallery_library' ) );

		add_filter(
			'cloudinary_gallery_html_container',
			static function () {
				return '.woocommerce-product-gallery__wrapper';
			}
		);

		if ( ! is_admin() && self::woocommerce_active() ) {
			add_filter( 'woocommerce_single_product_image_thumbnail_html', '__return_empty_string' );
		}

		add_filter( 'cloudinary_enqueue_gallery_script', array( $this, 'maybe_enqueue_scripts' ) );
	}

	/**
	 * Pre-process product images in REST API requests to resolve Cloudinary URLs to existing
	 * media library attachment IDs, preventing unnecessary sideloads and duplicate assets.
	 *
	 * @param WP_REST_Response|null $response The response object or null.
	 * @param WP_REST_Server        $handler  The request handler.
	 * @param WP_REST_Request       $request  The request object.
	 *
	 * @return WP_REST_Response|null
	 */
	public function pre_process_product_images( $response, $handler, $request ) {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Ignore requests to other API endpoints.
		if (
			false === strpos( $route, '/wc/' )
			|| false === strpos( $route, '/products' )
			|| ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true )
		) {
			return $response;
		}

		// We only care about requests that include images.
		$images = $request->get_param( 'images' );
		if ( empty( $images ) || ! is_array( $images ) ) {
			return $response;
		}

		$modified = false;

		foreach ( $images as $index => $image ) {
			// If the image ID is already passed, WooCommerce will be able to find the corresponding attachment from the Media Library.
			if ( ! empty( $image['id'] ) ) {
				continue;
			}

			$src = isset( $image['src'] ) ? esc_url_raw( $image['src'] ) : '';

			// We only care about images with a cloudinary URL.
			if ( ! $src || ! $this->media->is_cloudinary_url( $src ) ) {
				continue;
			}

			$attachment_id = $this->find_attachment_by_cloudinary_url( $src );

			// Apply the ID so that WooCommerce assigns the existing attachment.
			if ( ! is_null( $attachment_id ) ) {
				$images[ $index ]['id'] = $attachment_id;
				$modified               = true;
			}
		}

		if ( $modified ) {
			$request->set_param( 'images', $images );
		}

		return $response;
	}

	/**
	 * Find an existing media library attachment that corresponds to a Cloudinary URL.
	 *
	 * The URL may include transformation segments, so the lookup proceeds in three steps:
	 * exact sync key match, bare public ID match, then base key match.
	 *
	 * @param string $url A Cloudinary asset URL.
	 *
	 * @return int|null Attachment ID, or null if not found.
	 */
	private function find_attachment_by_cloudinary_url( $url ) {
		// Step 1: exact sync key — handles URLs that already exist verbatim in the library.
		$attachment_id = $this->media->get_id_from_url( $url );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		$public_id = $this->media->get_public_id_from_url( $url );
		if ( ! $public_id ) {
			return null;
		}

		// Step 2: bare public ID — matches assets uploaded from WordPress without transformations.
		$linked = $this->media->get_linked_attachments( $public_id );
		if ( ! empty( $linked ) ) {
			return array_shift( $linked );
		}

		// Step 3: base key — matches assets imported from Cloudinary.
		$base_id = $this->media->get_id_from_sync_key( 'base_' . $public_id );

		return $base_id ? $base_id : null;
	}
}
