<?php
/**
 * Cloudinary Delivery for delivery of cloudinary assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Media\Filter;
use Cloudinary\Media\Global_Transformations;
use Cloudinary\String_Replace;
use Cloudinary\UI\Component\HTML;
use WP_Post;

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
		add_action( 'update_option_cloudinary_media_display', array( $this, 'clear_cache' ) );
	}

	/**
	 * Clear cached meta.
	 */
	public function clear_cache() {
		delete_post_meta_by_key( self::META_CACHE_KEY );
	}

	/**
	 * Setup component.
	 */
	public function setup() {
		$this->filter = $this->media->filter;
		// Add filters.
		add_filter( 'the_content', array( $this, 'filter_local' ) );
		add_action( 'save_post', array( $this, 'remove_replace_cache' ), 10, 2 );
		add_action( 'cloudinary_string_replace', array( $this, 'catch_urls' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'rebuild_tag' ), 100, 5 );
	}

	/**
	 * Delete the content replacement cache data.
	 *
	 * @param int     $post_id The post ID to remove cache from.
	 * @param WP_Post $post    The post object.
	 */
	public function remove_replace_cache( $post_id, $post ) {
		delete_post_meta( $post_id, self::META_CACHE_KEY );
	}

	/**
	 * Filter out the local URLS from the content.
	 *
	 * @param string $content The HTML of the content to filter.
	 *
	 * @return string
	 */
	public function filter_local( $content ) {
		$post_id = get_the_ID();
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
	 *
	 * @return array
	 */
	public function convert_tags( $post_id, $content ) {
		$tags           = $this->filter->get_media_tags( $content );
		$replacements   = array();
		$attachment_ids = array();
		foreach ( $tags as $element ) {
			$attachment_id = $this->filter->get_id_from_tag( $element );
			if ( empty( $attachment_id ) ) {
				continue;
			}
			// Register replacement.
			$replacements[ $element ] = $this->rebuild_tag( $element, null, $attachment_id );
			$attachment_ids[]         = $attachment_id;
		}

		// Create other image sizes for ID's found.
		foreach ( $attachment_ids as $attachment_id ) {
			$urls = $this->get_attachment_size_urls( $attachment_id );
			foreach ( $urls as $local => $remote ) {
				$replacements[ $local ] = $remote;
			}
		}
		// Update the post meta cache.
		update_post_meta( $post_id, self::META_CACHE_KEY, $replacements );

		return $replacements;
	}

	/**
	 * Rebuild a tag with cloudinary urls.
	 *
	 * @param string   $element       The original HTML tag.
	 * @param null|int $post_id       The optional associated post ID.
	 * @param null|int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function rebuild_tag( $element, $post_id, $attachment_id ) {
		// Add our filter if not already added.
		if ( ! has_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ) ) ) {
			add_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10, 5 );
		}
		$element = trim( $element, '</>' );

		// Break element up.
		$atts = shortcode_parse_atts( $element );

		// Remove tag.
		$tag = array_shift( $atts );

		// Remove the old srcset if it has one.
		if ( isset( $atts['srcset'] ) ) {
			unset( $atts['srcset'] );
		}

		// Get overwrite flag.
		$overwrite = false;
		if ( is_null( $post_id ) && isset( $atts['class'] ) ) {
			$overwrite = (bool) strpos( $atts['class'], 'cld-overwrite' );
		} elseif ( $post_id ) {
			$overwrite = (bool) $this->media->get_post_meta( $post_id, Global_Transformations::META_FEATURED_IMAGE_KEY, true );
		}

		// Get size.
		$size = $this->get_size_from_atts( $atts );

		// Get transformations if present.
		$transformations = $this->get_transformations_maybe( $atts['src'] );

		// Create new src url.
		$atts['src'] = $this->media->cloudinary_url( $attachment_id, $size, $transformations, null, $overwrite );

		// Setup new tag.
		$replace = HTML::build_tag( $tag, $atts );

		// Add new srcset.
		return $this->media->apply_srcset( $replace, $attachment_id, $overwrite );
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

		$urls    = array_filter( array_filter( $urls ), array( 'Cloudinary\String_Replace', 'string_not_set' ) );
		$unknown = array_diff( $urls, array_keys( $known ) );
		if ( ! empty( $unknown ) ) {
			$known = array_merge( $known, $this->find_attachment_size_urls( $unknown ) );
		}
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}
	}
}
