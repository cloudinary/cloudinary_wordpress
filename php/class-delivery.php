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
use Cloudinary\Delivery\Bypass;
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
	 * Holds the Bypass instance.
	 *
	 * @var Bypass
	 */
	protected $bypass;

	public $known = array();

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
		add_action( 'cloudinary_flush_cache', array( $this, 'do_clear_cache' ) );
		add_action( 'cloudinary_id', array( $this, 'set_deliverable_relation' ), 10, 2 );
		add_action( 'cloudinary_unsync_asset', array( $this, 'unsync_attachment' ) );
		add_action( 'delete_post', array( $this, 'unsync_attachment' ) );
		add_action( 'delete_attachment', array( $this, 'unsync_attachment' ) );
		// Add Bypass options.
		$this->bypass = new Bypass( $this->plugin );
	}

	/**
	 * Remove a delivery relationship on unsync or delete of post.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function unsync_attachment( $attachment_id ) {
		global $wpdb;
		if ( $this->media->is_media( $attachment_id ) ) {
			$wpdb->delete( Utils::get_relationship_table(), array( 'post_id' => $attachment_id ) ); // phpcs:ignore WordPress.DB
			$wpdb->delete( Utils::get_relationship_table(), array( 'parent_id' => $attachment_id ) );// phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Set deliverable relationship.
	 *
	 * @param string $cloudinary_id The cloudinary ID.
	 * @param int    $attachment_id The attachment ID.
	 */
	public function set_deliverable_relation( $cloudinary_id, $attachment_id ) {
		if ( ! empty( $cloudinary_id ) ) {
			$this->check_relationships( $attachment_id );
		}
	}

	/**
	 * Check the relationship of an asset.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function check_relationships( $attachment_id ) {

		$meta      = wp_get_attachment_metadata( $attachment_id, true );
		$local_url = self::clean_url( $this->media->local_url( $attachment_id ), true );
		$urls      = array(
			'primary_url' => $local_url,
			'sized_url'   => $local_url,
		);
		$sizes     = array(
			$meta['width'] . 'x' . $meta['height'] => $urls,
		);

		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				$size_key               = $size['width'] . 'x' . $size['height'];
				$sized_url              = $urls;
				$sized_url['sized_url'] = dirname( $local_url ) . '/' . $size['file'];
				$sizes[ $size_key ]     = $sized_url;
			}
		}
		$this->evaluate_size_relation( $attachment_id, $sizes );
	}

	/**
	 * Evaluate changes needed to the relationship.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $sizes         The size array.
	 */
	protected function evaluate_size_relation( $attachment_id, $sizes ) {
		static $relationships = array();
		if ( ! isset( $relationships[ $attachment_id ] ) ) {
			$current = get_post_meta( $attachment_id, Sync::META_KEYS['relationship'], true );
			if ( empty( $current ) ) {
				$current = array(
					'signature' => null,
					'items'     => array(),
				);
			}
			$relationships[ $attachment_id ] = array(
				'sizes'     => $current,
				'public_id' => $this->media->get_public_id( $attachment_id ),
			);
		}

		$current_sizes = $relationships[ $attachment_id ]['sizes'];
		$public_id     = $relationships[ $attachment_id ]['public_id'];
		$signature     = md5( wp_json_encode( $sizes ) . $public_id );
		if ( $signature === $current_sizes['signature'] ) {
			return; // Nothing changed.
		}

		$new_sizes = array(
			'signature' => $signature,
			'items'     => array(),
		);

		$update_public_ids = false;
		foreach ( $sizes as $size => $urls ) {
			if ( ! isset( $current_sizes['items'][ $size ] ) ) {
				$type                        = $this->sync->full_sync( $attachment_id ) ? 'media' : 'asset';
				$new_sizes['items'][ $size ] = array(
					'id'        => $this->create_size_relation( $attachment_id, $public_id, $urls['primary_url'], $urls['sized_url'], $size, $type ),
					'public_id' => $public_id,
				);
				continue;
			} elseif ( isset( $current_sizes['items'][ $size ] ) && $public_id !== $current_sizes['items'][ $size ]['public_id'] ) {
				$update_public_ids = true; // Exists but does not match public_id.
			}
			// No changes on this one.
			$new_sizes['items'][ $size ] = $current_sizes['items'][ $size ];
			unset( $current_sizes['items'][ $size ] );
		}

		// If we still have items here, the sizes have been removed, so lets destroy them.
		if ( ! empty( $current_sizes['items'] ) ) {
			$ids = array();
			foreach ( $current_sizes['items'] as $old_size ) {
				$ids[] = $old_size['id'];
			}
			self::delete_size_relations( $ids );
		}

		// Update public_ids.
		if ( false !== $update_public_ids ) {
			self::update_size_relations_public_id( $attachment_id, $public_id );
		}
		update_post_meta( $attachment_id, Sync::META_KEYS['relationship'], $new_sizes );
	}

	/**
	 * Update relationship pugblic ID.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $public_id     The public ID.
	 */
	static public function update_size_relations_public_id( $attachment_id, $public_id ) {
		global $wpdb;
		$wpdb->update( Utils::get_relationship_table(), array( 'public_id' => $public_id ), array( 'post_id' => $attachment_id ) );// phpcs:ignore WordPress.DB
	}

	/**
	 * Delete unneeded sizes.
	 *
	 * @param array $ids The IDs to delete.
	 */
	public static function delete_size_relations( $ids ) {
		global $wpdb;
		$ids       = (array) $ids;
		$list      = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$tablename = Utils::get_relationship_table();
		$sql       = "DELETE from {$tablename} WHERE id IN( {$list} )";
		$prepared  = $wpdb->prepare( $sql, $ids ); // phpcs:ignore WordPress.DB

		$wpdb->query( $prepared );// phpcs:ignore WordPress.DB

	}

	/**
	 * Create a size relationship.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $public_id     The public ID.
	 * @param string $primary_url   The primary (full) URL.
	 * @param string $sized_url     The sized url.
	 * @param string $size          The size in (width)x(height) format.
	 * @param string $type          The type of sync.
	 *
	 * @return false|int
	 */
	public static function create_size_relation( $attachment_id, $public_id, $primary_url, $sized_url, $size, $type ) {
		global $wpdb;
		$parent_id    = has_post_parent( $attachment_id ) ? get_post_parent( $attachment_id )->ID : $attachment_id;
		$width_height = explode( 'x', $size );
		$data         = array(
			'post_id'     => $attachment_id,
			'parent_id'   => $parent_id,
			'public_id'   => $public_id,
			'primary_url' => $primary_url,
			'sized_url'   => $sized_url,
			'width'       => $width_height[0] ? $width_height[0] : 0,
			'height'      => $width_height[1] ? $width_height[1] : 0,
			'format'      => pathinfo( $primary_url, PATHINFO_EXTENSION ),
			'sync_type'   => $type,
		);

		$insert_id = false;
		if ( 0 < $wpdb->insert( Utils::get_relationship_table(), $data ) ) { // phpcs:ignore WordPress.DB
			$insert_id = $wpdb->insert_id;
		}

		return $insert_id;
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

		/**
		 * Action to flush delivery caches.
		 *
		 * @hook   cloudinary_flush_cache
		 * @since  3.0.0
		 */
		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Delete cached metadata.
	 *
	 * @hook cloudinary_flush_cache
	 */
	public function do_clear_cache() {
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
		 * @hook  cloudinary_init_delivery
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
	 * Find the attachment sizes from a list of URLS.
	 *
	 * @param array $urls URLS to find attachments for.
	 *
	 * @return array
	 */
	public function find_attachment_size_urls( $urls ) {

		global $wpdb;
		$dirs    = wp_get_upload_dir();
		$search  = array();
		$baseurl = self::clean_url( $dirs['baseurl'] );
		foreach ( $urls as $url ) {
			$url = ltrim( str_replace( $baseurl, '', $url ), '/' );
			if ( ! preg_match( '/(-(\d+)x(\d+))\./i', $url, $match ) ) {
				$search[] = $url;
				continue;
			}
			$search[] = str_replace( $match[1], '', $url );
			$search[] = str_replace( $match[1], '-scaled', $url );
		}

		$in = implode( ',', array_fill( 0, count( $search ), '%s' ) );

		// Prepare a query to find all in a single request.
		$sql = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in})",
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$search
		);

		$key    = md5( $sql );
		$cached = wp_cache_get( $key );
		if ( false === $cached ) {
			$cached  = array();
			$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			if ( $results ) {
				foreach ( $results as $result ) {
					if ( $this->sync->is_auto_sync_enabled() ) {
						$this->sync->add_to_sync( $result->post_id );
					}
					$url            = self::clean_url( $this->media->local_url( $result->post_id ) );
					$cached[ $url ] = (int) $result->post_id;
					$meta           = wp_get_attachment_metadata( $result->post_id, true );
					if ( ! empty( $meta['sizes'] ) ) {
						foreach ( $meta['sizes'] as $size ) {
							$size_url            = dirname( $url ) . '/' . $size['file'];
							$cached[ $size_url ] = (int) $result->post_id;
						}
					}
				}
			}
			wp_cache_add( $key, $cached );
		}

		$this->known = array_merge( $this->known, $cached );
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

		$tags = $this->filter->get_media_tags( $content );
		$tags = array_map( array( $this, 'parse_element' ), array_unique( $tags ) );

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
			 * @param $tag_element   {array}  The tag element.
			 *
			 * @return {int|false}
			 */
			$set['id'] = apply_filters( 'cloudinary_delivery_get_id', $set['id'], $set );
			if ( empty( $set['id'] ) ) {
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
	 * Cleanup and standardize the tag element structure.
	 *
	 * @param array $tag_element The tag element.
	 *
	 * @return array
	 */
	protected function standardize_tag( $tag_element ) {

		$default = array(
			'width'  => $tag_element['width'],
			'height' => $tag_element['height'],
		);
		// Add default.
		$tag_element['atts'] = wp_parse_args( $tag_element['atts'], $default );

		// Add wp-{media-type}-{id} class name.
		if ( empty( $tag_element['atts']['class'] ) || ! in_array( 'wp-' . $tag_element['type'] . '-' . $tag_element['id'], $tag_element['atts']['class'] ) ) {
			$tag_element['atts']['class'][] = 'wp-' . $tag_element['type'] . '-' . $tag_element['id'];
		}

		// Get size.
		$size = $this->get_size_from_atts( $tag_element['atts'] );

		// Unset srcset and sizes.
		unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );

		// Get cloudinary URL.
		$tag_element['atts']['src'] = $this->media->cloudinary_url(
			$tag_element['id'],
			$size,
			$tag_element['transformations'],
			$tag_element['atts']['data-public-id'],
			$tag_element['overwrite_transformations']
		);

		if ( 'on' === $this->plugin->settings->image_settings->_overlay ) {
			$local_size = get_post_meta( $tag_element['id'], Sync::META_KEYS['local_size'], true );
			if ( empty( $local_size ) && file_exists( get_attached_file( $tag_element['id'] ) ) ) {
				$local_size = filesize( get_attached_file( $tag_element['id'] ) );
			}
			$remote_size                           = get_post_meta( $tag_element['id'], Sync::META_KEYS['remote_size'], true );
			$tag_element['atts']['data-filesize']  = size_format( $local_size );
			$tag_element['atts']['data-optsize']   = size_format( $remote_size );
			$tag_element['atts']['data-optformat'] = get_post_meta( $tag_element['id'], Sync::META_KEYS['remote_format'], true );
			if ( ! empty( $local_size ) && ! empty( $remote_size ) ) {
				$diff                                = $local_size - $remote_size;
				$tag_element['atts']['data-percent'] = round( $diff / $local_size * 100, 1 );
			}
			$tag_element['atts']['data-permalink'] = get_edit_post_link( $tag_element['id'] );
		}

		return $tag_element;
	}

	/**
	 * Rebuild a tag with cloudinary urls.
	 *
	 * @param array $tag_element The original HTML tag.
	 *
	 * @return string
	 */
	public function rebuild_tag( $tag_element ) {

		$tag_element = $this->standardize_tag( $tag_element );

		/**
		 * Filter to allow stopping default srcset generation.
		 *
		 * @hook   cloudinary_apply_breakpoints
		 * @since  3.0.0
		 * @default {true}
		 *
		 * @param $apply {bool}  True to apply, false to skip.
		 *
		 * @return {bool}
		 */
		if ( apply_filters( 'cloudinary_apply_breakpoints', true ) ) {
			$meta = wp_get_attachment_metadata( $tag_element['id'] );
			// Check overwrite.
			$meta['overwrite_transformations'] = $tag_element['overwrite_transformations'];

			// Add new srcset.
			$element = wp_image_add_srcset_and_sizes( $tag_element['original'], $meta, $tag_element['id'] );

			$atts = Utils::get_tag_attributes( $element );
			if ( ! empty( $atts['srcset'] ) ) {
				$tag_element['atts']['srcset'] = $atts['srcset'];
			}
			if ( ! empty( $atts['sizes'] ) ) {
				$tag_element['atts']['sizes'] = $atts['sizes'];
			}
		}

		/**
		 * Filter the tag element.
		 *
		 * @hook   cloudinary_pre_image_tag
		 * @since  2.7.5
		 *
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		$tag_element = apply_filters( 'cloudinary_pre_image_tag', $tag_element );

		// Setup new tag.
		$replace = HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );

		/**
		 * Filter the new built tag element.
		 *
		 * @hook   cloudinary_image_tag
		 * @since  3.0.0
		 *
		 * @param $replace     {string} The new HTML tag.
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		return apply_filters( 'cloudinary_image_tag', $replace, $tag_element );
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
			'tag'                       => '',
			'atts'                      => array(),
			'original'                  => $element,
			'overwrite_transformations' => false,
			'context'                   => 0,
			'id'                        => 0,
			'type'                      => '',
			'delivery'                  => 'wp',
			'breakpoints'               => true,
			'transformations'           => array(),
		);
		// Cleanup element.
		$element = trim( $element, '</>' );

		// Break element up.
		$attributes          = shortcode_parse_atts( $element );
		$tag_element['tag']  = array_shift( $attributes );
		$tag_element['type'] = 'img' === $tag_element['tag'] ? 'image' : $tag_element['tag'];
		$url                 = isset( $attributes['src'] ) ? self::clean_url( $attributes['src'] ) : '';
		if ( ! empty( $this->known[ $url ] ) && ! empty( $this->known[ $url ]->public_id ) ) {
			$tag_element['id']            = $this->known[ $url ]->post_id;
			$tag_element['width']         = $this->known[ $url ]->width;
			$tag_element['height']        = $this->known[ $url ]->height;
			$attributes['data-public-id'] = $this->known[ $url ]->public_id;
		}
		if ( ! empty( $attributes['class'] ) ) {
			$attributes['class'] = explode( ' ', $attributes['class'] );
			if ( in_array( 'cld-overwrite', $attributes['class'], true ) ) {
				$tag_element['overwrite_transformations'] = true;
			}
		}

		$tag_element['transformations'] = $this->get_transformations_maybe( $attributes['src'] );

		// Set atts.
		$tag_element['atts'] = wp_parse_args( $attributes, $tag_element['atts'] );

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

		$is_local = substr( $url, 0, strlen( $base ) ) === $base && $this->sync->is_auto_sync_enabled();

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
	 * @param string $url         The URL to clean.
	 * @param bool   $scheme_less Flag to clean out scheme.
	 *
	 * @return string
	 */
	public static function clean_url( $url, $scheme_less = true ) {
		$default = array(
			'scheme' => '',
			'host'   => '',
			'path'   => '',
		);
		$parts   = wp_parse_args( wp_parse_url( $url ), $default );
		$url     = '//' . $parts['host'] . $parts['path'];
		if ( false === $scheme_less ) {
			$url = $parts['scheme'] . ':' . $url;
		}

		return $url;
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
		global $wpdb;
		$base_urls = array_map( array( $this, 'clean_url' ), wp_extract_urls( $content ) );
		$urls      = array_filter( array_unique( $base_urls ), array( $this, 'validate_url' ) ); // clean out empty urls.

		$list      = implode( ', ', array_fill( 0, count( $urls ), '%s' ) );
		$tablename = Utils::get_relationship_table();
		$sql       = "SELECT * from {$tablename} WHERE sized_url IN( {$list} )";
		$prepared  = $wpdb->prepare( $sql, $urls ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cache_key = md5( $prepared );
		$results   = wp_cache_get( $cache_key, 'cld_delivery' );
		if ( empty( $results ) ) {
			$results = $wpdb->get_results( $prepared );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_add( $cache_key, $results, 'cld_delivery' );
		}

		$usable = array();
		foreach ( $results as $result ) {
			$this->known[ $result->sized_url ] = $result;
			if ( ! empty( $result->public_id ) ) {
				$usable[] = $result->sized_url;
			}
			if ( 'asset' === $result->sync_type && $this->sync->is_auto_sync_enabled() ) {
				// Perform an auto sync.
				$this->sync->add_to_sync( $result->post_id );
			}
		}
		$urls = array_diff( $urls, $usable );
		$urls = array_filter( $urls, array( $this, 'is_local_asset_url' ) );

		return $urls;
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 */
	public function catch_urls( $content ) {

		$this->init_delivery();
		$urls  = $this->get_urls( $content );
		$known = $this->convert_tags( $content );
		$urls  = array_filter( $urls, array( 'Cloudinary\String_Replace', 'string_not_set' ) );
		if ( ! empty( $urls ) ) {
			$this->find_attachment_size_urls( $urls );
		}
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}
	}
}
