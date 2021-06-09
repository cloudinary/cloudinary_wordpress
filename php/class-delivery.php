<?php
/**
 * Cloudinary Delivery for delivery of cloudinary assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Media\Filter;
use Cloudinary\String_Replace;
use Cloudinary\UI\Component\HTML;

/**
 * Plugin Delivery class.
 */
class Delivery implements Setup {

	/**
	 * Holds the core plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the Media\Filter component.
	 *
	 * @var Filter
	 */
	protected $filter;

	/**
	 * The meta data cache key to store URLS.
	 *
	 * @var string
	 */
	const META_CACHE_KEY = '_cld_replacements';

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin                        = $plugin;
		$this->plugin->components['replace'] = new String_Replace( $this->plugin );
		$this->media                         = $this->plugin->get_component( 'media' );
		$this->setup_hooks();
	}

	/**
	 * Setup early needed hooks.
	 */
	protected function setup_hooks() {
		add_filter( 'cloudinary_filter_out_local', '__return_false' );
	}

	/**
	 * Setup component.
	 */
	public function setup() {
		add_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10, 5 );
		$this->filter = $this->media->filter;
		// Add filters.
		add_filter( 'the_content', array( $this, 'filter_local' ) );
		// @todo: Add filter for `cloudinary_string_replace` with method `convert_urls` To catch non ID's tags.
		add_action( 'save_post', array( $this, 'remove_replace_cache' ), 10, 2 );
	}

	/**
	 * Delete the content replacement cache data.
	 *
	 * @param int    $post_id The post ID to remove cache from.
	 * @param string $content The post content.
	 */
	public function remove_replace_cache( $post_id, $content ) {
		delete_post_meta( $post_id, self::META_CACHE_KEY );
		$this->convert_tags( $post_id, $content );
	}

	/**
	 * Filter out the local URLS from the content.
	 *
	 * @param string $content The HTML of the content to filter.
	 *
	 * @return string
	 */
	public function filter_local( $content ) {
		$post_id = get_queried_object_id();
		if ( ! empty( $post_id ) ) {
			$replacements = get_post_meta( $post_id, self::META_CACHE_KEY, true );
			if ( empty( $replacements ) ) {
				$replacements = $this->convert_tags( $post_id, $content );
			}
			foreach ( $replacements as $search => $replace ) {
				String_Replace::replace( $search, $replace );
			}
		}

		return $content;
	}

	/**
	 * Get the attachment ID from the media tag.
	 *
	 * @param string $html The HTML.
	 *
	 * @return int[]
	 */
	public function get_id_from_tags( $html ) {
		$attachment_ids = array();
		// Get attachment id from class name.
		if ( preg_match_all( '#class=["|\']?[^"\']*(wp-image-|wp-video-)([\d]+)[^"\']*["|\']?#i', $html, $found ) ) {
			$attachment_ids = array_map( 'intval', $found[2] );
		}

		return $attachment_ids;
	}

	/**
	 * Get known urls from image tags that have a wp-image-id.
	 *
	 * @param string $content The HTML content to get known urls from.
	 *
	 * @return array
	 */
	public function get_known_urls( $content ) {
		$ids  = $this->get_id_from_tags( $content );
		$urls = array();
		foreach ( $ids as $id ) {
			if ( ! $this->media->cloudinary_id( $id ) ) {
				continue;
			}
			$urls = array_merge( $urls, $this->get_attachment_size_urls( $id ) );
		}

		return $urls;
	}

	/**
	 * Get all sizes URLS for an attachment.
	 *
	 * @param int $attachment_id Get the image size URLS from an attachment ID.
	 *
	 * @return array
	 */
	public function get_attachment_size_urls( $attachment_id ) {
		$urls             = array();
		$meta             = wp_get_attachment_metadata( $attachment_id );
		$baseurl          = wp_get_attachment_url( $attachment_id );
		$base             = trailingslashit( dirname( $baseurl ) );
		$urls[ $baseurl ] = $this->media->cloudinary_url( $attachment_id );
		// Ignore getting 'original_image' since this isn't used in the front end.
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $data ) {
				$urls[ $base . $data['file'] ] = $this->media->cloudinary_url(
					$attachment_id,
					array(
						$data['width'],
						$data['height'],
					)
				);
			}
		}

		return $urls;
	}

	/**
	 * Find the attachment sizes from a list of URLS.
	 *
	 * @param array $urls URLS to find attachments for.
	 *
	 * @return array
	 */
	public function find_attachment_size_urls( $urls ) {
		global $wpdb;
		$dirs   = wp_get_upload_dir();
		$search = array();
		$found  = array();
		foreach ( $urls as $url ) {
			$url = ltrim( str_replace( $dirs['baseurl'], '', $url ), '/' );
			if ( ! preg_match( '/(-(\d+)x(\d+))\./i', $url, $match ) ) {
				$search[] = $url;
				continue;
			}
			$search[] = str_replace( $match[1], '', $url );
			$search[] = str_replace( $match[1], '-scaled', $url );
		}

		$in = implode( ',', array_fill( 0, count( $search ), '%s' ) );

		$sql    = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$search
		);
		$key    = md5( $sql );
		$cached = wp_cache_get( $key );
		if ( false === $cached ) {
			$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			if ( $results ) {
				foreach ( $results as $result ) {
					$found = array_merge( $found, $this->get_attachment_size_urls( $result->post_id ) );
				}
			}
			$cached = $found;
			wp_cache_set( $key, $found );
		}

		return $cached;
	}

	/**
	 * Convert media tags from Local to Cloudinary, and register with String_Replace.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $content The HTML to find tags and prep replacement in.
	 */
	public function convert_tags( $post_id, $content ) {

		$tags         = $this->filter->get_media_tags( $content );
		$replacements = array();
		foreach ( $tags as $element ) {
			$attachment_id = $this->filter->get_id_from_tag( $element );
			if ( empty( $attachment_id ) ) {
				continue;
			}

			// Get the tag type.
			$tag = strstr( trim( $element, '<' ), ' ', true );

			// Break element up.
			$atts = shortcode_parse_atts( $element );

			// Remove the old srcset if it has one.
			if ( isset( $atts['srcset'] ) ) {
				unset( $atts['srcset'] );
			}

			// Remove head and tail.
			array_shift( $atts );
			array_pop( $atts );

			// Get overwrite flag.
			$overwrite = (bool) strpos( $atts['class'], 'cld-overwrite' );

			// Get size.
			$size = $this->get_size_from_atts( $atts['class'] );

			// Get transformations if present.
			$transformations = $this->get_transformations_maybe( $atts['src'] );

			// Create new src url.
			$atts['src'] = $this->media->cloudinary_url( $attachment_id, $size, $transformations, null, $overwrite );

			// Setup new tag.
			$new_tag = array(
				$tag,
				HTML::build_attributes( $atts ),
			);

			$replace = HTML::compile_tag( $new_tag );

			// Add new srcset.
			$replace = $this->media->apply_srcset( $replace, $attachment_id, $overwrite );

			// Register replacement.
			$replacements[ $element ] = $replace;
		}

		// Update the post meta cache.
		update_post_meta( $post_id, self::META_CACHE_KEY, $replacements );

		return $replacements;
	}

	/**
	 * Get the size from the attributes.
	 *
	 * @param array $atts Attributes array.
	 *
	 * @return array
	 */
	protected function get_size_from_atts( $atts ) {
		$size = array();
		if ( ! empty( $atts['width'] ) ) {
			$size[] = $atts['width'];
		}
		if ( ! empty( $atts['height'] ) ) {
			$size[] = $atts['height'];
		}

		return $size;
	}

	/**
	 * Maybe get the inline transformations from an image url.
	 *
	 * @param string $url The image src url.
	 *
	 * @return array|null
	 */
	protected function get_transformations_maybe( $url ) {
		$transformations = null;
		$query           = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query ) && false !== strpos( $query, 'cld_params' ) ) {
			// Has params in src.
			$args = array();
			wp_parse_str( $query, $args );
			$transformations = $this->media->get_transformations_from_string( $args['cld_params'] );
		}

		return $transformations;
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 */
	public function catch_urls( $content ) {
		$known = array();
		$urls  = wp_extract_urls( $content );
		$dirs  = wp_get_upload_dir();
		$urls  = array_map(
			function ( $url ) use ( $dirs ) {

				if ( false === strpos( $url, $dirs['baseurl'] ) ) {
					return null;
				}

				return $url;
			},
			$urls
		);

		$urls    = array_filter( $urls );
		$unknown = array_diff( $urls, array_keys( $known ) );
		if ( ! empty( $unknown ) ) {
			$known = array_merge( $known, $this->find_attachment_size_urls( $unknown ) );
		}
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}
	}
}
