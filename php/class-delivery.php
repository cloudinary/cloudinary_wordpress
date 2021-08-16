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
use Cloudinary\Sync;
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
	 * Holds the Sync component.
	 *
	 * @var Sync
	 */
	protected $sync;

	/**
	 * Hold the Post ID.
	 *
	 * @var null|int
	 */
	protected $current_post_id = null;

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
		add_filter( 'cloudinary_filter_out_local', '__return_false' );
		add_action( 'update_option_cloudinary_media_display', array( $this, 'clear_cache' ) );
		add_action( 'cloudinary_flush_cache', array( $this, 'clear_cache' ) );
	}

	/**
	 * Setup early needed hooks.
	 */
	protected function setup_hooks() {
		// Add filters.
		add_action( 'save_post', array( $this, 'remove_replace_cache' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'catch_urls' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'process_featured_image' ), 100, 3 );

		add_filter( 'cloudinary_current_post_id', array( $this, 'get_current_post_id' ) );
		add_filter( 'the_content', array( $this, 'add_post_id' ) );
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_action( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 10, 2 );

		// Clear cache on taxonomy update.
		$taxonomies = get_taxonomies( array( 'show_ui' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "saved_{$taxonomy}", array( $this, 'clear_cache' ) );
		}
	}

	/**
	 * Add DNS prefetch link tag for assets.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for, e.g. 'preconnect' or 'prerender'.
	 *
	 * @return array
	 */
	public function dns_prefetch( $urls, $relation_type ) {

		if ( 'dns-prefetch' === $relation_type || 'preconnect' === $relation_type ) {
			$urls[] = $this->media->base_url;
		}

		return $urls;
	}

	/**
	 * Clear cached meta.
	 */
	public function clear_cache() {

		delete_post_meta_by_key( self::META_CACHE_KEY );
	}

	/**
	 * Add the Post ID to images and videos.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	public function add_post_id( $content ) {

		return str_replace(
			array(
				'wp-image-',
				'wp-video-',
			),
			array(
				'wp-post-' . get_the_ID() . ' wp-image-',
				'wp-post-' . get_the_ID() . ' wp-video-',
			),
			$content
		);
	}

	/**
	 * Get the current post ID.
	 *
	 * @return int|null
	 */
	public function get_current_post_id() {

		return $this->current_post_id ? $this->current_post_id : null;
	}

	/**
	 * Setup component.
	 */
	public function setup() {

		$this->filter = $this->media->filter;
		$this->sync   = $this->media->sync;

		$this->setup_hooks();
	}

	/**
	 * Init delivery.
	 */
	protected function init_delivery() {

		add_filter( 'wp_calculate_image_srcset', array( $this->media, 'image_srcset' ), 10, 5 );

		/**
		 * Action indicating that the delivery is starting.
		 *
		 * @hook  cloudinary_pre_image_tag
		 * @since 2.7.5
		 *
		 * @param $delivery {Delivery} The delivery object.
		 */
		do_action( 'cloudinary_init_delivery', $this );
	}

	/**
	 * Add classes to the featured image tag.
	 *
	 * @param string $html          The image tah HTML to add to.
	 * @param int    $post_id       Ignored.
	 * @param int    $attachment_id The attachment_id.
	 *
	 * @return string
	 */
	public function process_featured_image( $html, $post_id, $attachment_id ) {

		// Get tag element.
		$tag_element                    = $this->parse_element( $html );
		$tag_element['id']              = $attachment_id;
		$tag_element['context']         = $post_id;
		$tag_element['atts']['class'][] = 'wp-image-' . $attachment_id;
		$tag_element['atts']['class'][] = 'wp-post-' . $post_id;

		if ( true === (bool) get_post_meta( $post_id, Global_Transformations::META_FEATURED_IMAGE_KEY, true ) ) {
			$tag_element['atts']['class'][] = 'cld-overwrite';
		}

		return HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );
	}

	/**
	 * Delete the content replacement cache data.
	 *
	 * @param int $post_id The post ID to remove cache from.
	 */
	public function remove_replace_cache( $post_id ) {

		delete_post_meta( $post_id, self::META_CACHE_KEY );
	}

	/**
	 * Get all sizes URLS for an attachment.
	 *
	 * @param int $attachment_id Get the image size URLS from an attachment ID.
	 *
	 * @return array
	 */
	public function get_attachment_size_urls( $attachment_id ) {

		$urls    = array();
		$meta    = wp_get_attachment_metadata( $attachment_id );
		$baseurl = wp_get_attachment_url( $attachment_id );
		if ( false === $baseurl ) {
			return $urls;
		}
		$base             = trailingslashit( dirname( $baseurl ) );
		$urls[ $baseurl ] = $this->media->cloudinary_url( $attachment_id );
		// Ignore getting 'original_image' since this isn't used in the front end.
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $data ) {
				if ( isset( $urls[ $base . $data['file'] ] ) ) {
					continue;
				}
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
					if ( $this->sync->is_synced( $result->post_id ) ) {
						$found = array_merge( $found, $this->get_attachment_size_urls( $result->post_id ) );
					}
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
	 * @param string $content The HTML to find tags and prep replacement in.
	 *
	 * @return array
	 */
	public function convert_tags( $content ) {
		$cached = array();
		if ( is_singular() ) {
			$cache_key = self::META_CACHE_KEY;
			$has_cache = get_post_meta( get_the_ID(), $cache_key, true );
			$type      = is_ssl() ? 'https' : 'http';
			if ( ! empty( $has_cache ) && ! empty( $has_cache[ $type ] ) ) {
				$cached = $has_cache[ $type ];
			}
		}

		$tags         = $this->filter->get_media_tags( $content );
		$tags         = array_map( array( $this, 'parse_element' ), array_unique( $tags ) );
		$replacements = array();
		foreach ( $tags as $set ) {

			// Check cache and skip if needed.
			if ( isset( $replacements[ $set['original'] ] ) ) {
				continue;
			}
			/**
			 * Filter id from the tag.
			 *
			 * @hook   cloudinary_delivery_get_id
			 * @since  2.7.6
			 *
			 * @param $attachment_id {int}    The attachment ID.
			 * @param $html_tag      {string} The html tag.
			 * @param $type          {string} The asset type.
			 *
			 * @return {int|false}
			 */
			$set['id'] = apply_filters( 'cloudinary_delivery_get_id', $set['id'], $set['original'], $set['type'] );
			if ( empty( $set['id'] ) || ! $this->media->cloudinary_id( $set['id'] ) ) {
				continue;
			}
			$this->current_post_id = $set['context'];
			// Use cached item if found.
			if ( isset( $cached[ $set['original'] ] ) ) {
				$replacements[ $set['original'] ] = $cached[ $set['original'] ];
			} else {
				// Register replacement.
				$replacements[ $set['original'] ] = $this->rebuild_tag( $set );
			}
			$this->current_post_id = null;
		}

		// Update the post meta cache.
		if ( isset( $cache_key ) && isset( $type ) ) {
			if ( empty( $has_cache ) ) {
				$has_cache = array();
			}
			$has_cache[ $type ] = $replacements;
			update_post_meta( get_the_ID(), $cache_key, $has_cache );
		}

		return $replacements;
	}

	/**
	 * Rebuild a tag with cloudinary urls.
	 *
	 * @param array $tag_element The original HTML tag.
	 *
	 * @return string
	 */
	public function rebuild_tag( $tag_element ) {

		/**
		 * Filter the tag element.
		 *
		 * @hook   cloudinary_pre_image_tag
		 * @since  2.7.5
		 *
		 * @param $tag_element   {array}  The tag_element ( tag + attributes array).
		 * @param $attachment_id {int}    The attachment ID.
		 * @param $element       {string} The original HTML tag.
		 *
		 * @return {array}
		 */
		$tag_element = apply_filters( 'cloudinary_pre_image_tag', $tag_element, $tag_element['id'], $tag_element['original'] );

		if ( 'wp' === $tag_element['delivery'] ) {

			$image_meta = wp_get_attachment_metadata( $tag_element['id'] );
			// Check overwrite.
			$image_meta['overwrite_transformations'] = $tag_element['cld-overwrite'];

			// Try add srcset if not present.
			$element = wp_image_add_srcset_and_sizes( $tag_element['original'], $image_meta, $tag_element['id'] );
			$atts    = Utils::get_tag_attributes( $element );

			if ( ! empty( $atts['srcset'] ) ) {
				$tag_element['atts']['srcset'] = $atts['srcset'];
			}
			if ( ! empty( $atts['sizes'] ) ) {
				$tag_element['atts']['sizes'] = $atts['sizes'];
			}

			// Get size.
			$size = $this->get_size_from_atts( $tag_element['atts'] );

			// Get transformations if present.
			$transformations = $this->get_transformations_maybe( $tag_element['atts']['src'] );

			// Get cloudinary URL, only if overwrite or has inline transformations. Catch all will replace standard urls.
			$tag_element['atts']['src'] = $this->media->cloudinary_url( $tag_element['id'], $size, $transformations, null, $image_meta['overwrite_transformations'] );
		}
		// Setup new tag.
		$replace = HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );

		return $replace;
	}

	/**
	 * Parse an html element into tag, and attributes.
	 *
	 * @param string $element The HTML element.
	 *
	 * @return array
	 */
	public function parse_element( $element ) {

		$tag_element = array(
			'tag'           => '',
			'atts'          => array(),
			'original'      => $element,
			'cld-overwrite' => false,
			'context'       => 0,
			'id'            => 0,
			'type'          => '',
			'delivery'      => 'wp',
		);
		// Cleanup element.
		$element = trim( $element, '</>' );

		// Break element up.
		$tag_element['atts'] = shortcode_parse_atts( $element );
		$tag_element['tag']  = array_shift( $tag_element['atts'] );
		if ( ! empty( $tag_element['atts']['class'] ) ) {
			$tag_element['atts']['class'] = explode( ' ', $tag_element['atts']['class'] );
			foreach ( $tag_element['atts']['class'] as $class ) {
				if ( 0 === strpos( $class, 'wp-video-' ) ) {
					$tag_element['id']   = intval( substr( $class, 9 ) );
					$tag_element['type'] = 'video';
				}
				if ( 0 === strpos( $class, 'wp-image-' ) ) {
					$tag_element['id']   = intval( substr( $class, 9 ) );
					$tag_element['type'] = 'image';
				}
				if ( 0 === strpos( $class, 'wp-post-' ) ) {
					$tag_element['context'] = intval( substr( $class, 8 ) );
				}
			}
			if ( in_array( 'cld-overwrite', $tag_element['atts']['class'], true ) ) {
				$tag_element['cld-overwrite'] = true;
			}
		}

		return $tag_element;
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
	 * Checks if a url is for a local asset.
	 *
	 * @param string $url The url to check.
	 *
	 * @return bool
	 */
	protected function is_local_asset_url( $url ) {
		static $base = '';
		if ( empty( $base ) ) {
			$dirs = wp_upload_dir();
			$base = $dirs['baseurl'];
		}

		$is_local = substr( $url, 0, strlen( $base ) ) === $base;

		/**
		 * Filter if the url is a local asset.
		 *
		 * @hook   cloudinary_pre_image_tag
		 * @since  2.7.6
		 *
		 * @param $is_local {bool}   If the url is a local asset.
		 * @param $url      {string} The url.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_local_asset_url', $is_local, $url );
	}

	/**
	 * Clean a url: adds scheme if missing, removes query and fragments.
	 *
	 * @param string $url The URL to clean.
	 *
	 * @return string
	 */
	public static function clean_url( $url ) {
		$default = array(
			'scheme' => '',
			'host'   => '',
			'path'   => '',
		);
		$parts   = wp_parse_args( wp_parse_url( $url ), $default );

		return $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
	}

	/**
	 * Filter out excluded urls.
	 *
	 * @param string $url The url to filter out.
	 *
	 * @return bool
	 */
	public function validate_url( $url ) {
		static $home;
		if ( ! $home ) {
			$home = wp_parse_url( home_url( '/' ) );
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false; // If host is empty, it's a false positive url.
		}
		if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
			return false; // exclude base domains.
		}
		$ext = pathinfo( $parts['path'], PATHINFO_EXTENSION );
		if ( $parts['host'] === $home['host'] && empty( $ext ) || 'php' === $ext ) {
			return false; // Local urls without an extension or ending in PHP will not be media.
		}

		return true;
	}

	/**
	 * Get urls from HTML.
	 *
	 * @param string $content The content html.
	 *
	 * @return array
	 */
	protected function get_urls( $content ) {
		$base_urls = array_map( array( $this, 'clean_url' ), wp_extract_urls( $content ) );
		$urls      = array_filter( array_unique( $base_urls ), array( $this, 'validate_url' ) ); // clean out empty urls.
		$urls      = array_filter( $urls, array( $this, 'is_local_asset_url' ) );

		return $urls;
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 */
	public function catch_urls( $content ) {
		$this->init_delivery();
		$urls    = $this->get_urls( $content );
		$known   = $this->convert_tags( $content );
		$urls    = array_filter( $urls, array( 'Cloudinary\String_Replace', 'string_not_set' ) );
		$unknown = array_diff( $urls, array_keys( $known ) );
		if ( ! empty( $unknown ) ) {
			$known = array_merge( $known, $this->find_attachment_size_urls( $unknown ) );
		}
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}
	}
}
