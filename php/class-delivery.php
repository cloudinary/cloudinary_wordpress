<?php
/**
 * Cloudinary Delivery for delivery of cloudinary assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Media\Filter;
use Cloudinary\Media\Global_Transformations;
use Cloudinary\UI\Component\HTML;
use Cloudinary\Delivery\Bypass;
use Cloudinary\Relate\Relationship;

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

	/**
	 * Holds a list of found and valid urls.
	 *
	 * @var array
	 */
	public $found_urls = array();

	/**
	 * Holds a list of known urls.
	 *
	 * @var array
	 */
	public $known = array();

	/**
	 * Holds the list of unknown URLS.
	 *
	 * @var array
	 */
	public $unknown = array();

	/**
	 * Holds a list of known urls with public_ids.
	 *
	 * @var array
	 */
	public $usable = array();

	/**
	 * Holds a list of known urls without public_ids.
	 *
	 * @var array
	 */
	public $unusable = array();

	/**
	 * The meta data cache key to store URLS.
	 *
	 * @var string
	 */
	const META_CACHE_KEY = '_cld_replacements';

	/**
	 * Holds the captured post contexts
	 *
	 * @var array
	 */
	protected $post_contexts = array();

	/**
	 * Flag for doing metadata adds or updates.
	 *
	 * @var bool
	 */
	protected $doing_metadata = false;

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
	}

	/**
	 * Init the class when cloudinary is connected.
	 */
	public function init() {
		$this->plugin->components['replace'] = new String_Replace( $this->plugin );
		$this->media                         = $this->plugin->get_component( 'media' );
		add_filter( 'cloudinary_filter_out_local', '__return_false' );
		add_action( 'update_option_cloudinary_media_display', array( $this, 'clear_cache' ) );
		add_action( 'cloudinary_flush_cache', array( $this, 'do_clear_cache' ) );
		add_action( 'cloudinary_unsync_asset', array( $this, 'unsync_size_relationship' ) );
		add_action( 'before_delete_post', array( $this, 'delete_size_relationship' ) );
		add_action( 'delete_attachment', array( $this, 'delete_size_relationship' ) );
		add_action( 'cloudinary_register_sync_types', array( $this, 'register_sync_type' ), 30 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'maybe_unset_attributes' ), 10, 3 );
		add_action(
			'the_post',
			function ( $post ) {
				$this->post_contexts[] = $post->ID;
			}
		);

		// Add Bypass options.
		$this->bypass = new Bypass( $this->plugin );

		// Add relation checking on front.
		if ( ! is_admin() ) {
			add_filter( 'wp_get_attachment_url', array( $this, 'ensure_relation' ), 10, 2 );
		}
	}

	/**
	 * Determine if attributes should be added to image tags.
	 *
	 * @param WP_REST_Response     $response The response object.
	 * @param WP_REST_Server       $handler  The request handler.
	 * @param WP_REST_Request|null $request The request object, if available.
	 *
	 * @return WP_REST_Response
	 */
	public function maybe_unset_attributes( $response, $handler, $request ) {
		$route = $request->get_route();

		if (
			(bool) $request->get_header( 'x-cld-fetch-from-editor' )
			|| (
				false !== strpos( $route, 'wp/v2/media' )
				&& 'edit' === $request->get_param( 'context' )
			)
		) {
			add_filter( 'cloudinary_skip_parse_element', '__return_true' );
		}

		return $response;
	}

	/**
	 * Maybe filter out Cloudinary URLs in post meta.
	 *
	 * @param null|bool $check      Whether to allow adding metadata for the given type.
	 * @param int       $object_id  The ID of the object metadata is for.
	 * @param string    $meta_key   The Metadata key.
	 * @param mixed     $meta_value Metadata value.
	 *
	 * @return null|bool
	 */
	public function maybe_filter_out_metadata( $check, $object_id, $meta_key, $meta_value ) {

		$internal_keys = array_merge(
			Sync::META_KEYS,
			array(
				self::META_CACHE_KEY,
			)
		);

		// Don't filter out metadata if we're dealing with Cloudinary internals.
		if ( in_array( $meta_key, $internal_keys, true ) ) {
			return $check;
		}

		if ( $this->doing_metadata ) {
			return $check;
		}

		$this->doing_metadata = true;
		$current_filter       = current_filter();

		list( $action, $object ) = explode( '_', $current_filter );

		$process_meta_value = $this->filter_out_cloudinary( $meta_value );

		if ( $process_meta_value !== $meta_value ) {
			$meta_value = $process_meta_value;
			$check      = call_user_func( "{$action}_{$object}_meta", $object_id, $meta_key, $meta_value );
		}

		$this->doing_metadata = false;

		return $check;
	}

	/**
	 * Filter out Cloudinary URLS and replace with local.
	 *
	 * @param string $content The content to filter.
	 *
	 * @return string
	 */
	public function filter_out_cloudinary( $content ) {

		static $globals;

		if ( ! $globals ) {
			$image   = $this->media->apply_default_transformations( array(), 'image' );
			$video   = $this->media->apply_default_transformations( array(), 'video' );
			$globals = array(
				'image' => Api::generate_transformation_string( $image, 'image' ),
				'video' => Api::generate_transformation_string( $video, 'video' ),
			);
		}
		$unslashed       = false;
		$working_content = $content;
		if ( is_string( $working_content ) && ! is_numeric( $working_content ) ) {
			$maybe_encoded = json_decode( $working_content, false );
			if ( ! is_null( $maybe_encoded ) ) {
				$working_content = $maybe_encoded;
			}
		}
		if ( String_Replace::is_iterable( $working_content ) ) {
			$working_content = $this->plugin->components['replace']->flatten( $working_content );
		} else {
			$unslash_maybe = wp_unslash( $working_content );
			$unslashed     = $unslash_maybe !== $working_content;
			if ( $unslashed ) {
				$working_content = $unslash_maybe;
			}
		}
		$base_urls       = array_unique( Utils::extract_urls( $working_content ) );
		$cloudinary_urls = array_filter( $base_urls, array( $this->media, 'is_cloudinary_url' ) ); // clean out empty urls.
		$urls            = array();
		if ( empty( $cloudinary_urls ) ) {
			return $content;
		}
		foreach ( $cloudinary_urls as $url ) {
			$public_id = $this->media->get_public_id_from_url( $url );
			if ( ! empty( $public_id ) ) {
				$urls[ $public_id ] = $url;
			}
		}

		$results = Utils::query_relations( array_keys( $urls ) );
		String_Replace::reset();
		foreach ( $results as $result ) {
			if ( ! isset( $urls[ $result['public_id'] ] ) ) {
				continue;
			}

			$original_url = $urls[ $result['public_id'] ];
			// Get merged transformations including overlays.
			$merged_transformations = Relate::get_transformations( $result['post_id'], true );
			if ( ! empty( $merged_transformations ) ) {
				$original_url = str_replace( $merged_transformations . '/', '/', $original_url );
			}
			$size            = $this->media->get_size_from_url( $original_url );
			$transformations = $this->media->get_transformations_from_string( $original_url );
			if ( 'image' === $this->media->get_resource_type( $result['post_id'] ) && ! $this->media->is_preview_only( $result['post_id'] ) ) {
				$attachment_url = wp_get_attachment_image_url( $result['post_id'], $size );
			} else {
				$attachment_url = wp_get_attachment_url( $result['post_id'] );
			}
			$query_args = array();
			wp_parse_str( wp_parse_url( $original_url, PHP_URL_QUERY ), $query_args );
			if ( ! empty( $query_args['cld_overwrite'] ) ) {
				$attachment_url = add_query_arg( 'cld_overwrite', true, $attachment_url );
			}
			if ( ! empty( $transformations ) ) {
				$transformations = array_filter(
					$transformations,
					static function ( $item ) {
						return ! isset( $item['crop'] ) && ! isset( $item['width'] ) && ! isset( $item['height'] );
					}
				);
				$transformations = Api::generate_transformation_string( $transformations );
				if ( ! empty( $transformations ) && ! in_array( $transformations, $globals, true ) ) {
					$attachment_url = add_query_arg( 'cld_params', $transformations, $attachment_url );
				}
			}

			String_Replace::replace( $urls[ $result['public_id'] ], $attachment_url );
		}

		$content = String_Replace::do_replace( $content );

		return $content;
	}

	/**
	 * Ensure that an asset has a relation on front end.
	 *
	 * @param string $url           The URL of the asset.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function ensure_relation( $url, $attachment_id ) {
		static $urls = array();
		if ( empty( $urls[ $attachment_id ] ) && ! $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['relationship'], true ) ) {
			$urls[ $attachment_id ] = true;
			$this->sync->get_sync_type( $attachment_id );
		}

		return $url;
	}

	/**
	 * Generate the delivery signature.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function generate_signature( $attachment_id ) {
		static $sql;
		if ( ! $sql ) {
			$sql = Utils::get_table_sql();
		}
		$public_id    = null;
		$relationship = Relationship::get_relationship( $attachment_id );
		if ( $relationship instanceof Relationship ) {
			$public_id = $relationship->public_id;
		}

		$sizes              = $this->get_sized( $attachment_id );
		$settings_signature = self::get_settings_signature();
		$relation_signature = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['relationship'], true );

		return wp_json_encode( $sizes ) . $public_id . $sql . $settings_signature . $relation_signature . Utils::get_media_context( $attachment_id );
	}

	/**
	 * Is attachment deliverable in the FE.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_deliverable( $attachment_id ) {
		$is = false;

		if ( wp_attachment_is_image( $attachment_id ) && 'on' === $this->plugin->settings->get_value( 'image_delivery' ) ) {
			$is = true;
		}

		if ( ! $is && wp_attachment_is( 'video', $attachment_id ) && 'on' === $this->plugin->settings->get_value( 'video_delivery' ) ) {
			$is = true;
		}

		// Ensure that the attachment has dimensions to be delivered.
		if ( $is ) {
			$meta = wp_get_attachment_metadata( $attachment_id, true );
			$is   = ! empty( $meta['width'] ) && ! empty( $meta['height'] );

			// Webm audio files don't have width and height.
			if ( ! $is && ! empty( $meta['mime_type'] ) && 'audio/webm' === $meta['mime_type'] ) {
				$is = true;
			}
		}

		if ( ! $is ) {
			$is = ! wp_attachment_is_image( $attachment_id ) && ! wp_attachment_is( 'video', $attachment_id );
		}

		$svg = $this->plugin->get_component( 'svg' );

		if ( ! $is && $svg->is_active() && $svg::is_svg( $attachment_id ) && 'on' === $this->plugin->settings->get_value( 'image_delivery' ) ) {
			$is = true;
		}

		/**
		 * Filter deliverable attachments.
		 *
		 * @hook   cloudinary_is_deliverable
		 *
		 * @param $is            {bool} The default value.
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_deliverable', $is, $attachment_id );
	}

	/**
	 * Create delivery entries.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function create_delivery( $attachment_id ) {
		$relationship    = Relationship::get_relationship( $attachment_id );
		$transformations = null;
		// Preserve pre-existing transformations.
		if ( $relationship instanceof Relationship ) {
			$transformations = Relate::get_transformations( $attachment_id, true );
			if ( empty( $transformations ) ) {
				$transformations = null;
			}
		}
		$this->delete_size_relationship( $attachment_id );
		$size      = $this->get_sized( $attachment_id );
		$public_id = $this->media->has_public_id( $attachment_id ) ? $this->media->get_public_id( $attachment_id ) : null;
		$base      = $this->get_content_path();
		$sized_url = '';
		$wh        = '0x0'; // phpcs:ignore PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found, PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
		// Some attachments do not have Sizes.
		if ( ! empty( $size ) ) {
			$sized_url = $size['sized_url'];
			$wh        = $size['size'];
		}

		if ( empty( $sized_url ) ) {
			$sized_url = Utils::get_path_from_url( wp_get_attachment_url( $attachment_id ), true );
		}
		self::create_size_relation( $attachment_id, $sized_url, $wh, $base );
		// Update public ID and type.
		self::update_size_relations_public_id( $attachment_id, $public_id );
		self::update_size_relations_state( $attachment_id, 'inherit' );
		self::update_size_relations_transformations( $attachment_id, $transformations );
		$this->sync->set_signature_item( $attachment_id, 'delivery' );
	}

	/**
	 * Sync method for the relation type.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function update_relation( $attachment_id ) {
		$public_id = $this->media->has_public_id( $attachment_id ) ? $this->media->get_public_id( $attachment_id ) : null;
		// Update public ID and type.
		self::update_size_relations_public_id( $attachment_id, $public_id );
		$this->sync->set_signature_item( $attachment_id, 'relation' );
	}

	/**
	 * Add our delivery sync type.
	 */
	public function register_sync_type() {
		$structure = array(
			'asset_state' => 0,
			'generate'    => '__return_false',
			'priority'    => 0.5,
			'sync'        => array( $this, 'create_delivery' ),
			'validate'    => array( $this, 'is_deliverable' ),
			'state'       => '',
			'note'        => '',
			'realtime'    => true,
		);
		$this->sync->register_sync_type( 'delivery', $structure );

		$structure = array(
			'asset_state' => 0,
			'generate'    => array( $this, 'generate_signature' ), // Method to generate a signature.
			'priority'    => 50,
			'sync'        => array( $this, 'update_relation' ),
			'validate'    => array( $this, 'is_deliverable' ),
			'state'       => '',
			'note'        => '',
			'realtime'    => true,
		);
		$this->sync->register_sync_type( 'relation', $structure );
	}

	/**
	 * Get the base content path.
	 *
	 * @return string
	 */
	protected function get_content_path() {
		$dirs = wp_get_upload_dir();

		return ltrim( wp_parse_url( trailingslashit( $dirs['baseurl'] ), PHP_URL_PATH ), '/' );
	}

	/**
	 * Remove a delivery relationship on delete of a post.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function delete_size_relationship( $attachment_id ) {
		$relationship = Relationship::get_relationship( $attachment_id );
		$relationship->delete();

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Disable a delivery relationship on unsync.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function unsync_size_relationship( $attachment_id ) {
		self::update_size_relations_public_id( $attachment_id, null );
		self::update_size_relations_state( $attachment_id, 'disable' );
		self::update_size_relations_transformations( $attachment_id, null );
	}

	/**
	 * Get the different sizes for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function get_sized( $attachment_id ) {
		static $sizes = array(), $registered_sizes;
		if ( ! $registered_sizes && is_callable( 'wp_get_registered_image_subsizes' ) ) {
			$registered_sizes = wp_get_registered_image_subsizes();
		}
		if ( empty( $sizes[ $attachment_id ] ) ) {
			$sizes[ $attachment_id ] = array();
			$meta                    = wp_get_attachment_metadata( $attachment_id, true );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				// Keep the full URL for cloudinary_assets.
				if ( Assets::POST_TYPE_SLUG === get_post_type( $attachment_id ) ) {
					$local_url = Utils::clean_url( $this->media->local_url( $attachment_id ), true );
				} else {
					$local_url = Utils::get_path_from_url( $this->media->local_url( $attachment_id ), true );
				}
				$sizes[ $attachment_id ] = array(
					'sized_url' => $local_url,
					'size'      => $meta['width'] . 'x' . $meta['height'],
				);

				return $sizes[ $attachment_id ];
			}
		}

		return $sizes[ $attachment_id ];
	}

	/**
	 * Update relationship public ID.
	 *
	 * @param int         $attachment_id The attachment ID.
	 * @param null|string $public_id     The public ID.
	 */
	public static function update_size_relations_public_id( $attachment_id, $public_id ) {
		$relationship = Relationship::get_relationship( $attachment_id );

		if ( $relationship instanceof Relationship ) {
			$relationship->public_id     = $public_id;
			$relationship->public_hash   = md5( (string) $public_id );
			$relationship->signature     = self::get_settings_signature();
			$relationship->media_context = Utils::get_media_context( $attachment_id );
			$relationship->save();
		}
	}

	/**
	 * Update relationship status.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $state         The state to set.
	 */
	public static function update_size_relations_state( $attachment_id, $state ) {
		$relationship = Relationship::get_relationship( $attachment_id );

		if ( $relationship instanceof Relationship ) {
			$relationship->post_state = $state;
			$relationship->save();
		}

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Update relationship transformations.
	 *
	 * @param int         $attachment_id   The attachment ID.
	 * @param string|null $transformations The transformations to set.
	 */
	public static function update_size_relations_transformations( $attachment_id, $transformations ) {
		Relate::update_transformations( $attachment_id, $transformations );
	}

	/**
	 * Delete unneeded sizes in bulk by ID.
	 *
	 * @param array $ids The IDs to delete.
	 */
	public static function delete_bulk_size_relations( $ids ) {
		global $wpdb;
		$ids       = (array) $ids;
		$list      = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$tablename = Utils::get_relationship_table();
		$sql       = "DELETE from {$tablename} WHERE id IN( {$list} )";
		$prepared  = $wpdb->prepare( $sql, $ids ); // phpcs:ignore WordPress.DB

		$wpdb->query( $prepared );// phpcs:ignore WordPress.DB

		do_action( 'cloudinary_flush_cache' );
	}

	/**
	 * Create a size relationship.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $sized_url     The sized url.
	 * @param string $size          The size in (width)x(height) format.
	 * @param string $parent_path   The path of the parent if external.
	 *
	 * @return false|int
	 */
	public static function create_size_relation( $attachment_id, $sized_url, $size = '0x0', $parent_path = '' ) { // phpcs:ignore PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found, PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
		global $wpdb;
		static $media;
		if ( ! $media ) {
			$media = get_plugin_instance()->get_component( 'media' );
		}
		$type            = 'attachment' === get_post_type( $attachment_id ) ? 'media' : 'asset';
		$resource        = $media->get_resource_type( $attachment_id );
		$width_height    = explode( 'x', $size );
		$transformations = $media->get_post_meta( $attachment_id, Sync::META_KEYS['transformation'], true );
		if ( ! is_null( $transformations ) ) {
			$media->delete_post_meta( $attachment_id, Sync::META_KEYS['transformation'] );
		}

		$data = array(
			'post_id'         => $attachment_id,
			'parent_path'     => $parent_path,
			'sized_url'       => $sized_url,
			'media_context'   => Utils::get_media_context( $attachment_id ),
			'width'           => $width_height[0] ? $width_height[0] : 0,
			'height'          => $width_height[1] ? $width_height[1] : 0,
			'format'          => Utils::pathinfo( $sized_url, PATHINFO_EXTENSION ),
			'sync_type'       => $type,
			'post_state'      => 'inherit',
			'transformations' => ! empty( $transformations ) ? Api::generate_transformation_string( $transformations, $resource ) : null,
			'signature'       => self::get_settings_signature(),
			'url_hash'        => md5( $sized_url ),
			'parent_hash'     => md5( $parent_path ),
		);

		$insert_id = false;
		$created   = $wpdb->replace( Utils::get_relationship_table(), $data ); // phpcs:ignore WordPress.DB
		if ( 0 < $created ) {
			$insert_id = $wpdb->insert_id;
			$media->update_post_meta( $attachment_id, Sync::META_KEYS['relationship'], self::get_settings_signature() );
		}

		return $insert_id;
	}

	/**
	 * Get a signature of the current settings that result in a sync check.
	 *
	 * @return string string
	 */
	public static function get_settings_signature() {
		static $signature;
		if ( ! $signature ) {
			$settings  = get_plugin_instance()->settings->get_value( 'cloudinary_url', 'sync_media' );
			$signature = md5( wp_json_encode( $settings ) );
		}

		return $signature;
	}

	/**
	 * Setup early needed hooks.
	 */
	protected function setup_hooks() {
		// Add filters.
		add_filter( 'content_save_pre', array( $this, 'filter_out_cloudinary' ) );
		add_action( 'save_post', array( $this, 'remove_replace_cache' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'catch_urls' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( $this, 'process_featured_image' ), 100, 3 );

		add_filter( 'cloudinary_current_post_id', array( $this, 'get_current_post_id' ) );
		add_filter( 'the_content', array( $this, 'add_post_id' ) );
		add_action( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 10, 2 );

		$metadata = Utils::METADATA;

		foreach ( $metadata['actions'] as $action ) {
			foreach ( $metadata['objects'] as $object ) {
				$inline_action = str_replace( '{object}', $object, $action );
				add_action( $inline_action, array( $this, 'maybe_filter_out_metadata' ), 10, 4 );
			}
		}

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

		/**
		 * Filter to provide option to omit prefetch.
		 *
		 * @hook   cloudinary_dns_prefetch_types
		 * @since 3.2.12
		 * @default array ( 'dns-prefetch', 'preconnect' )
		 *
		 * @param $types {array} The types of resource hints to use.
		 *
		 * @return {array} The modified resource hints to use.
		 */
		$resource_hints = apply_filters( 'cloudinary_dns_prefetch_types', array( 'dns-prefetch', 'preconnect' ) );

		if ( in_array( $relation_type, $resource_hints, true ) ) {
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
	 * @param bool $hard Whether to hard flush the cache.
	 *
	 * @hook cloudinary_flush_cache
	 */
	public function do_clear_cache( $hard = true ) {
		delete_post_meta_by_key( self::META_CACHE_KEY );

		if ( $hard ) {
			wp_cache_flush();
		}
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

		// Reset internals.
		$this->known      = array();
		$this->unknown    = array();
		$this->found_urls = array();
		$this->unusable   = array();

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
	 * @param string $html          The image the HTML to add to.
	 * @param int    $post_id       Ignored.
	 * @param int    $attachment_id The attachment_id.
	 *
	 * @return string
	 */
	public function process_featured_image( $html, $post_id, $attachment_id ) {

		if ( empty( $html ) ) {
			return $html; // Ignore empty tags.
		}

		$tags = $this->get_media_tags( $html, 'img' );
		$tags = array_map( array( $this, 'parse_element' ), $tags );
		$tags = array_filter( $tags );
		foreach ( $tags as $tag_element ) {
			// Get tag element.
			$tag_element['id']              = $attachment_id;
			$tag_element['context']         = $post_id;
			$tag_element['atts']['class'][] = 'wp-image-' . $attachment_id;
			$tag_element['atts']['class'][] = 'wp-post-' . $post_id;

			if ( true === (bool) get_post_meta( $post_id, Global_Transformations::META_FEATURED_IMAGE_KEY, true ) ) {
				$tag_element['atts']['class'][] = 'cld-overwrite';
			}

			$new_tag = HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );
			$html    = str_replace( $tag_element['original'], $new_tag, $html );
		}

		return $html;
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
	 */
	public function find_attachment_size_urls() {

		global $wpdb;
		$dirs    = wp_get_upload_dir();
		$baseurl = Utils::clean_url( $dirs['baseurl'] );
		$search  = array();

		foreach ( $this->unknown as $url ) {
			$url      = ltrim( str_replace( $baseurl, '', $url ), '/' );
			$search[] = $url;
		}

		$search = array_unique( $search );
		$in     = implode( ',', array_fill( 0, count( $search ), '%s' ) );

		// Prepare a query to find all in a single request.
		$sql = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in}) limit 1000", // phpcs:ignore WordPress.DB
			$search
		);

		$key       = md5( $sql );
		$cached    = wp_cache_get( $key );
		$auto_sync = $this->sync->is_auto_sync_enabled();

		if ( false === $cached ) {
			$cached  = array();
			$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

			if ( $results ) {
				foreach ( $results as $result ) {
					/**
					 * Get the contextualized post id.
					 *
					 * @hook cloudinary_contextualized_post_id
					 * @since 3.2.0
					 *
					 * @param $post_id {int} The post ID.
					 *
					 * @return {int}
					 */
					$post_id = apply_filters( 'cloudinary_contextualized_post_id', $result->post_id );

					if ( ! $this->is_deliverable( $post_id ) ) {
						continue;
					}
					// If we are here, it means that an attachment in the media library doesn't have a delivery for the url.
					// Reset the signature for delivery and add to sync, to update it.
					$this->create_delivery( $post_id );

					if ( true === $auto_sync ) {
						$this->sync->add_to_sync( $post_id );
					}
					$size           = $this->get_sized( $post_id );
					$key            = ! empty( $size['sized_url'] ) ? $size['sized_url'] : wp_get_attachment_url( $post_id );
					$cached[ $key ] = (int) $post_id;
				}
			}
			wp_cache_add( $key, $cached );
		}

		$this->known   = array_merge( $this->known, $cached );
		$this->unknown = array_diff_key( $this->unknown, $this->known );
	}

	/**
	 * Get all the caches from found contexts.
	 *
	 * @return array
	 */
	protected function get_context_cache() {
		$cached = array();
		foreach ( $this->post_contexts as $id ) {
			$has_cache = get_post_meta( $id, self::META_CACHE_KEY, true );
			if ( ! empty( $has_cache ) ) {
				foreach ( $has_cache as $type => $cache ) {
					if ( ! isset( $cached[ $type ] ) ) {
						$cached[ $type ] = array();
					}
					$cached[ $type ] = array_merge( $cached[ $type ], $cache );
				}
			}
		}

		return $cached;
	}

	/**
	 * Get all image and video tags that match our found urls.
	 *
	 * @param string $content HTML content.
	 * @param string $tags    List of tags to get.
	 *
	 * @return array The media tags found.
	 */
	public function get_media_tags( $content, $tags = 'img|video' ) {
		$media = array();
		if ( preg_match_all( '#(?P<tags><(' . $tags . ')[^>]*\>){1}#is', $content, $found ) ) {
			$count = count( $found[0] );
			for ( $i = 0; $i < $count; $i++ ) {
				$media[ $i ] = $found['tags'][ $i ];
			}
		}

		return $media;
	}

	/**
	 * Convert media tags from Local to Cloudinary, and register with String_Replace.
	 *
	 * @param string $content The HTML to find tags and prep replacement in.
	 * @param string $context The content of the content.
	 *
	 * @return array
	 */
	public function convert_tags( $content, $context = 'view' ) {
		$has_cache = $this->get_context_cache();
		$type      = is_ssl() ? 'https' : 'http';
		if ( Utils::is_amp() ) {
			$type = 'amp';
		}
		if ( 'view' === $context && ! empty( $has_cache[ $type ] ) ) {
			$cached = $has_cache[ $type ];
		}

		$tags = $this->get_media_tags( $content, 'img|video|article|source' );
		$tags = array_map( array( $this, 'parse_element' ), $tags );
		$tags = array_filter( $tags );

		$replacements = array();
		$aliases      = array();
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

			// We only rebuild tags in the view context.
			if ( 'view' === $context ) {
				// Use cached item if found.
				if ( isset( $cached[ $set['original'] ] ) ) {
					$replacements[ $set['original'] ] = $cached[ $set['original'] ];
				} else {
					// Register replacement.
					$replacements[ $set['original'] ] = $this->rebuild_tag( $set );
				}
			}
			$this->current_post_id = null;

		}

		// Create aliases for urls where were found, but not found with an ID in a tag.
		// Create the Full/Scaled items first.
		foreach ( $this->known as $url => $relation ) {
			if ( empty( $relation['public_id'] || $url === $relation['public_id'] ) ) {
				continue; // We don't need the public_id relation item.
			}

			if ( 'disable' === $relation['post_state'] ) {
				continue; // We should not deliver disabled items.
			}

			$base           = $type . ':' . $url;
			$public_id      = ! is_admin() ? $relation['public_id'] . '.' . $relation['format'] : null;
			// Get merged transformations including overlays.
			$merged_transformations = Relate::get_transformations( $relation['post_id'], true );
			$cloudinary_url = $this->media->cloudinary_url( $relation['post_id'], array(), $merged_transformations, $public_id );
			if ( empty( $cloudinary_url ) ) {
				continue;
			}
			if ( ! empty( $relation['slashed'] ) && $relation['slashed'] ) {
				$aliases[ $base . '?_i=AA' ] = addcslashes( $cloudinary_url, '/' );
				$aliases[ $base . '?' ]      = addcslashes( $cloudinary_url . '&', '/' );
				$aliases[ $base ]            = addcslashes( $cloudinary_url, '/' );
			} else {
				$aliases[ $base . '?_i=AA' ] = $cloudinary_url;
				$aliases[ $base . '?' ]      = $cloudinary_url . '&';
				$aliases[ $base ]            = $cloudinary_url;
			}
		}

		// Create the sized found relations second.
		foreach ( $this->found_urls as $url => $sizes ) {
			if ( ! isset( $this->known[ $url ] ) || empty( $this->known[ $url ]['public_id'] ) ) {
				continue;
			}
			$base      = $type . ':' . $url;
			$relation  = $this->known[ $url ];
			$public_id = ! is_admin() ? $relation['public_id'] . '.' . $relation['format'] : null;
			// Get merged transformations including overlays.
			$merged_transformations = Relate::get_transformations( $relation['post_id'], true );
			foreach ( $sizes as $size => $file_name ) {
				$local_url = path_join( dirname( $base ), $file_name );
				if ( isset( $cached[ $local_url ] ) ) {
					$aliases[ $local_url ] = $cached[ $local_url ];
					continue;
				}

				$cloudinary_url = $this->media->cloudinary_url( $relation['post_id'], explode( 'x', $size ), $merged_transformations, $public_id );
				// The asset is not ready. Carry on.
				if ( empty( $cloudinary_url ) ) {
					continue;
				}

				$aliases[ $local_url . '?' ] = $cloudinary_url . '&';
				$aliases[ $local_url ]       = $cloudinary_url;

				// Some URLs might be slashed, but the found_urls does not have that information.
				$aliases[ addcslashes( $local_url . '?', '/' ) ] = addcslashes( $cloudinary_url . '&', '/' );
				$aliases[ addcslashes( $local_url, '/' ) ]       = addcslashes( $cloudinary_url, '/' );
			}
		}

		// Move aliases to the end of the run, after images.
		if ( ! empty( $aliases ) ) {
			$replacements = array_merge( $replacements, $aliases );
		}

		// Sort by length, so we replace the longest first and prevent early replacements.
		$keys = array_map( 'strlen', array_keys( $replacements ) );
		array_multisort( $keys, SORT_DESC, $replacements );

		// Update the post meta cache.
		if ( is_singular() ) {
			$has_cache          = array();
			$has_cache[ $type ] = $replacements;
			update_post_meta( get_the_ID(), self::META_CACHE_KEY, $has_cache );
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
		if ( 'video' === $tag_element['tag'] ) {
			// Video is handled different with sizes, so we dont set default widths and heights.
			$default = array();
		}
		// Add default.
		$tag_element['atts'] = wp_parse_args( $tag_element['atts'], $default );
		// Add format if it's set.
		if ( isset( $tag_element['format'] ) ) {
			$tag_element['atts']['data-format'] = $tag_element['format'];
		}

		/**
		 * Maybe skip the classes for th tag element.
		 *
		 * @hook  cloudinary_tag_skip_classes
		 * @since 3.2.4
		 * @default {false}
		 *
		 * @param $skip        {bool} True to unset attributes.
		 * @param $tag_element {array} The tag element.
		 *
		 * @retun {bool}
		 */
		$skip_classes = apply_filters( 'cloudinary_tag_skip_classes', false, $tag_element );

		// Add wp-{media-type}-{id} class name.
		if (
			! $skip_classes
			&&
			(
			empty( $tag_element['atts']['class'] )
			||
			(
				is_array( $tag_element['atts']['class'] )
				&&
				! in_array( 'wp-' . $tag_element['type'] . '-' . $tag_element['id'], $tag_element['atts']['class'], true )
			)
			)
		) {
			$tag_element['atts']['class']   = ! empty( $tag_element['atts']['class'] ) ? $tag_element['atts']['class'] : array();
			$tag_element['atts']['class'][] = 'wp-' . $tag_element['type'] . '-' . $tag_element['id'];
		}

		$size = array();

		// Get size.
		if ( 'video' !== $tag_element['tag'] ) {
			$size = array(
				$tag_element['atts']['width'],
				$tag_element['atts']['height'],
			);
		}

		if ( ! empty( $tag_element['atts']['src'] ) ) {
			$has_wp_size = $this->media->get_crop( $tag_element['atts']['src'], $tag_element['id'] );
			if ( ! empty( $has_wp_size ) ) {
				$size = $has_wp_size;
			}
		}
		// Unset srcset and sizes.
		unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );

		$public_id = $tag_element['atts']['data-public-id'];

		// Get cloudinary URL.
		$url = $this->media->cloudinary_url(
			$tag_element['id'],
			$size,
			$tag_element['transformations'],
			$public_id,
			$tag_element['overwrite_transformations']
		);

		// Set the src.
		$tag_element['atts']['src'] = $url;
		// Convert any URLS that exist in attributes ( 3rd party lazyload etc..).
		foreach ( $tag_element['atts'] as &$att ) {
			if ( is_array( $att ) ) {
				continue;
			}
			$parts = array_filter( explode( ' ', $att ) );
			foreach ( $parts as &$part ) {
				if ( $this->validate_url( $part ) ) {
					$has_wp_size = $this->media->get_crop( $part, $tag_element['id'] );
					$size        = array();
					if ( ! empty( $has_wp_size ) ) {
						$size = $has_wp_size;
					}
					$part = $this->media->cloudinary_url(
						$tag_element['id'],
						$size,
						$tag_element['transformations'],
						$public_id,
						$tag_element['overwrite_transformations']
					);
				}
			}
			$att = implode( ' ', $parts );
		}

		/**
		 * Unset the attributes to avoid block errors in the admin.
		 *
		 * @hook  cloudinary_unset_attributes
		 * @since 3.2.4
		 * @default {false}
		 *
		 * @param $unset       {bool} True to unset attributes.
		 * @param $tag_element {array} The tag element.
		 *
		 * @retun {bool}
		 */
		if ( apply_filters( 'cloudinary_unset_attributes', false, $tag_element ) ) {
			unset(
				$tag_element['atts']['data-public-id'],
				$tag_element['atts']['data-format'],
				$tag_element['atts']['width'],
				$tag_element['atts']['height'],
				$tag_element['atts']['data-crop'],
				$tag_element['atts']['srcset'],
				$tag_element['atts']['data-responsive']
			);

			return $tag_element;
		}

		// Add transformations attribute.
		$transformations = $this->media->get_transformations_from_string( $tag_element['atts']['src'] );
		if ( false !== $this->media->get_crop_from_transformation( $transformations ) ) {
			array_shift( $transformations );
		}
		$tag_element['atts']['data-transformations'] = API::generate_transformation_string( $transformations, $tag_element['type'] );

		if ( Utils::user_can( 'status' ) && 'on' === $this->plugin->settings->image_settings->_overlay ) {
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
			} elseif ( 'image' === $tag_element['type'] ) {
				$this->plugin->get_component( 'storage' )->size_sync( $tag_element['id'] );
			}

			$base_url = $this->plugin->settings->get_url( 'edit_asset' );

			/**
			 * Filter the permalink for the edit asset link.
			 *
			 * @hook   cloudinary_edit_asset_permalink
			 * @since  3.2.0
			 *
			 * @param $permalink {string} The permalink.
			 *
			 * @return {string}
			 */
			$permalink = apply_filters( 'cloudinary_edit_asset_permalink', add_query_arg( 'asset', $tag_element['id'], $base_url ) );

			$tag_element['atts']['data-permalink'] = $permalink;
		}

		$tag_element['atts']['data-version'] = $this->media->get_cloudinary_version( $tag_element['id'] );

		/**
		 * Bypass Cloudinary's SEO URLs.
		 *
		 * @hook   cloudinary_bypass_seo_url
		 * @since  3.1.5
		 *
		 * @param $bypass_seo_url {bool} Whether to bypass SEO URLs.
		 *
		 * @return {bool}
		 */
		$bypass_seo_url = apply_filters( 'cloudinary_bypass_seo_url', false );

		$tag_element['atts']['data-seo'] = ! $bypass_seo_url;

		$resource_type = in_array( $tag_element['type'], array( 'image', 'video' ), true ) ? $tag_element['type'] : 'raw';

		$args = array(
			'delivery'      => $this->media->get_media_delivery( $tag_element['id'] ),
			'resource_type' => $resource_type,
		);

		$tag_element['atts']['data-public-id'] = $this->plugin->get_component( 'connect' )->api->get_public_id( $tag_element['id'], $args );

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
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$relationship = Relationship::get_relationship( $tag_element['id'] );
				// Check overwrite.
				$meta['overwrite_transformations'] = $tag_element['overwrite_transformations'];
				$meta['cloudinary_id']             = $relationship->public_id;
				$meta['transformations']           = $tag_element['transformations'];
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
		}

		/**
		 * Filter the tag element.
		 *
		 * @hook   cloudinary_pre_image_tag | cloudinary_pre_video_tag
		 * @since  2.7.5
		 *
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		$tag_element = apply_filters( "cloudinary_pre_{$tag_element['type']}_tag", $tag_element );

		// Setup new tag.
		$replace = HTML::build_tag( $tag_element['tag'], $tag_element['atts'] );

		// If the original tag used single quotes, we need to use them in the new tag.
		$single_quotes = (bool) preg_match( '/=\s*\'/', $tag_element['original'] );
		if ( $single_quotes ) {
			$replace = str_replace( '"', "'", $replace );
		}

		/**
		 * Filter the new built tag element.
		 *
		 * @hook   cloudinary_image_tag | cloudinary_video_tag
		 * @since  3.0.0
		 *
		 * @param $replace     {string} The new HTML tag.
		 * @param $tag_element {array}  The tag_element (tag + attributes array).
		 *
		 * @return {array}
		 */
		return apply_filters( "cloudinary_{$tag_element['type']}_tag", $replace, $tag_element );
	}

	/**
	 * Parse an html element into tag, and attributes.
	 *
	 * @param string $element The HTML element.
	 *
	 * @return array|null
	 */
	public function parse_element( $element ) {
		/**
		 * Filter to skip parsing an element.
		 *
		 * @hook  cloudinary_skip_parse_element
		 * @since 3.2.6
		 * @default {false}
		 *
		 * @param $skip    {bool}  True to skip parsing.
		 * @param $element {string} The element to parse.
		 *
		 * @return {bool}
		 */
		if ( apply_filters( 'cloudinary_skip_parse_element', false, $element ) ) {
			return null;
		}

		static $post_context = 0;

		$config = $this->plugin->settings->get_value( 'image_settings' );

		/**
		 * Enable the Crop and Gravity control settings.
		 *
		 * @hook  cloudinary_enable_crop_and_gravity_control
		 * @since 3.1.3
		 * @default {false}
		 *
		 * @param $enabeld {bool} Is the Crop and Gravity control enabled?
		 *
		 * @retrun {bool}
		 */
		$enabled_crop_gravity     = apply_filters( 'cloudinary_enable_crop_and_gravity_control', true );
		$has_sized_transformation = $enabled_crop_gravity && ! empty( $config['crop_sizes'] );

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
			'width'                     => 0,
			'height'                    => 0,
			'base_url'                  => '',
		);
		// Cleanup element.
		$element = trim( $element, '</>' );

		// Break element up.
		$attributes         = shortcode_parse_atts( $element );
		$tag_element['tag'] = array_shift( $attributes );

		// Skip audio source tags.
		if ( ! empty( $attributes['type'] ) && 0 === strpos( $attributes['type'], 'audio/' ) && 'source' === $tag_element['tag'] ) {
			return null;
		}

		// Context Switch Check.
		if ( 'article' === $tag_element['tag'] ) {
			if ( ! empty( $attributes['id'] ) && false !== strpos( $attributes['id'], 'post-' ) ) {
				$post_context = intval( substr( $attributes['id'], 5 ) );
			}

			return null;
		}
		$tag_element['type'] = in_array( $tag_element['tag'], array( 'img', 'source' ), true ) ? 'image' : $tag_element['tag'];
		$third_party_change  = Utils::maybe_get_third_party_changes( $attributes, $tag_element['tag'] );
		if ( ! empty( $third_party_change ) ) {
			Utils::log( $third_party_change, 'third-party-loading' );

			return null;
		}
		$raw_url                 = 'source' === $tag_element['tag'] && ! empty( $attributes['srcset'] ) ? $attributes['srcset'] : $attributes['src'];
		$url                     = $this->maybe_unsize_url( Utils::clean_url( $this->sanitize_url( $raw_url ) ) );
		$tag_element['base_url'] = $url;
		// Track back the found URL.
		if ( $this->media->is_cloudinary_url( $raw_url ) ) {
			$public_id = $this->media->get_public_id_from_url( $raw_url );
			if ( isset( $this->known[ $public_id ] ) ) {

				$url               = $this->known[ $public_id ]['sized_url'];
				$attributes['src'] = $url;
			}
		}
		$tag_element['context'] = $post_context;
		if ( ! empty( $this->known[ $url ] ) && ! empty( $this->known[ $url ]['public_id'] ) ) {
			$item = $this->known[ $url ];
			// Get merged transformations including overlays.
			$merged_transformations = Relate::get_transformations( $item['post_id'], true );
			if ( ! empty( $merged_transformations ) ) {
				$tag_element['transformations'] = $this->media->get_transformations_from_string( $merged_transformations, $tag_element['type'] );
			}
			// Get the public ID and append the extension if it's missing.
			$public_id = $item['public_id'];
			if ( strrchr( $public_id, '.' ) !== '.' . $item['format'] ) {
				$public_id .= '.' . $item['format'];
			}
			$tag_element['id']            = (int) $item['post_id'];
			$tag_element['width']         = ! empty( $attributes['width'] ) ? $attributes['width'] : $item['width'];
			$tag_element['height']        = ! empty( $attributes['height'] ) ? $attributes['height'] : $item['height'];
			$attributes['data-public-id'] = $public_id;
			$tag_element['format']        = $item['format'];

			if ( in_array( $tag_element['tag'], array( 'img', 'source' ), true ) ) {
				// Check if this is a crop or a scale.
				$has_size = $this->media->get_size_from_url( $this->sanitize_url( $raw_url ) );
				if ( ! empty( $has_size ) && ! empty( $item['height'] ) ) {
					$file_ratio     = round( $has_size[0] / $has_size[1], 2 );
					$original_ratio = round( $item['width'] / $item['height'], 2 );
					if ( $file_ratio !== $original_ratio ) {
						$attributes['data-crop'] = $file_ratio;
					}
					if ( $has_sized_transformation ) {
						$crop_size             = array(
							'width'  => $has_size[0],
							'height' => $has_size[1],
						);
						$image_transformations = $this->media->get_crop_transformations( $tag_element['id'], $crop_size );
						if ( $image_transformations ) {
							$attributes['data-transformation-crop'] = $image_transformations;
						}
					}
				}
			}
		}

		if ( ! empty( $attributes['class'] ) ) {
			if ( preg_match( '/wp-post-(\d+)/', $attributes['class'], $match ) ) {
				$tag_element['context'] = (int) $match[1];
				$post_context           = $tag_element['context'];
			}

			$attributes['class'] = explode( ' ', $attributes['class'] );
			if ( in_array( 'cld-overwrite', $attributes['class'], true ) ) {
				$tag_element['overwrite_transformations'] = true;
			}
		} else {
			$attributes['class'] = array(
				'wp-post-' . $tag_element['context'],
			);
		}
		// Add overwrite transformations class if needed.
		if ( false !== strpos( $raw_url, 'cld_overwrite' ) ) {
			$attributes['class'][]                    = 'cld-overwrite';
			$tag_element['overwrite_transformations'] = true;
		}

		$inline_transformations = $this->get_transformations_maybe( $raw_url );
		if ( $inline_transformations ) {
			// Ensure that we don't get duplicated transformations.
			$tag_element['transformations'] = array_unique( array_merge( $tag_element['transformations'], $inline_transformations ), SORT_REGULAR );
		}

		// Check if ID was found, and upgrade if needed.
		if ( empty( $tag_element['id'] ) ) {
			// Old method to aid in upgrade from 2.7.7.
			$maybe_id = $this->media->filter->get_id_from_tag( $tag_element['original'] );
			if ( ! empty( $maybe_id ) ) {
				$cloudinary_id_maybe = $this->media->cloudinary_id( $maybe_id );
				if ( ! empty( $cloudinary_id_maybe ) ) {
					$tag_element['id']                     = $maybe_id;
					$meta                                  = wp_get_attachment_metadata( $maybe_id );
					$tag_element['width']                  = ! empty( $meta['width'] ) ? $meta['width'] : 0;
					$tag_element['height']                 = ! empty( $meta['height'] ) ? $meta['height'] : 0;
					$tag_element['atts']['data-public-id'] = $cloudinary_id_maybe;
					$tag_element['format']                 = Utils::pathinfo( $cloudinary_id_maybe, PATHINFO_EXTENSION );
				}
			}
		}

		// Check for loading attribute.
		if ( ! empty( $attributes['loading'] ) ) {
			$tag_element['loading'] = $attributes['loading'];
		}

		// Set atts.
		$tag_element['atts'] = wp_parse_args( $attributes, $tag_element['atts'] );

		/**
		 * Filter the tag element.
		 *
		 * @hook   cloudinary_parse_element
		 * @since  3.0.9
		 *
		 * @param $tag_element {array} The tag element.
		 *
		 * @return {array} The tag element.
		 */
		$tag_element = apply_filters( 'cloudinary_parse_element', $tag_element );

		return $tag_element;
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
	 * Checks if a url path is for a local content directory.
	 *
	 * @param string $url The url to check.
	 *
	 * @return bool
	 */
	protected function is_content_dir( $url ) {
		static $base = '';

		if ( empty( $base ) ) {
			$dirs = wp_upload_dir();
			$base = wp_parse_url( $dirs['baseurl'], PHP_URL_PATH );
		}

		$path     = wp_parse_url( dirname( $url ), PHP_URL_PATH );
		$is_local = 0 === strpos( $path, $base );

		if ( $is_local ) {
			$dirname = trim( substr( $path, strlen( $base ), 8 ), DIRECTORY_SEPARATOR );
			if ( empty( $dirname ) || preg_match( '/\d{4}\/\d{2}/', $dirname ) ) {
				$is_local = true;
			} elseif ( ! empty( $dirname ) ) {
				$is_local = false;
			}
		}

		/**
		 * Filter if the url is a local asset.
		 *
		 * @hook   cloudinary_is_content_dir
		 * @since  2.7.6
		 *
		 * @param $is_local {bool}   If the url is a local asset.
		 * @param $url      {string} The url.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_content_dir', $is_local, $url );
	}

	/**
	 * Check if the file type is allowed to be uploaded.
	 *
	 * @param string $ext The filetype extension.
	 *
	 * @return bool
	 */
	protected function is_allowed_type( $ext ) {
		static $allowed_types = array();
		if ( empty( $allowed_types ) ) {
			$compatible_types = $this->media->get_compatible_media_types();
			// Check with paths.
			$types = wp_get_ext_types();
			foreach ( $compatible_types as $type ) {
				if ( isset( $types[ $type ] ) ) {
					$allowed_types = array_merge( $allowed_types, $types[ $type ] );
				}
			}
			/**
			 * Filter the allowed file extensions to be delivered.
			 *
			 * @hook   cloudinary_allowed_extensions
			 * @since  3.0.0
			 *
			 * @param $allowed_types {array}  Array of allowed file extensions.
			 *
			 * @return {array}
			 */
			$allowed_types = apply_filters( 'cloudinary_allowed_extensions', $allowed_types );
		}

		return in_array( $ext, $allowed_types, true );
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
			$home = wp_parse_url( Utils::home_url( '/' ) );
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false; // If host is empty, it's a false positive url.
		}
		if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
			return false; // exclude base domains.
		}
		$ext = Utils::pathinfo( $parts['path'], PATHINFO_EXTENSION );
		if ( empty( $ext ) || ! $this->is_allowed_type( $ext ) ) {
			return false;
		}

		return $parts['host'] === $home['host'] ? $this->is_content_dir( $url ) : $this->media->can_upload_from_host( $parts['host'] );
	}

	/**
	 * Set url usability.
	 *
	 * @param object    $item      The item object result.
	 * @param null|bool $auto_sync If auto_sync is on.
	 */
	protected function set_usability( $item, $auto_sync = null ) {

		/**
		 * Filter the found item to allow usability to be altered.
		 *
		 * @hook   cloudinary_set_usable_asset
		 * @since  3.0.2
		 *
		 * @param $item {array} The found asset array.
		 *
		 * @return {array}
		 */
		$item = apply_filters( 'cloudinary_set_usable_asset', $item );

		/**
		 * The URL to be searched for and prepared to be delivered by Cloudinary.
		 *
		 * @hook   cloudinary_content_url
		 * @since  3.1.6
		 *
		 * @param $url {string} The default content_url.
		 *
		 * @return {string}
		 */
		$content_url = apply_filters( 'cloudinary_content_url', content_url() );

		// Cloudinary assets have a full URL.
		if ( 'asset' === $item['sync_type'] ) {
			$url = $item['sized_url'];
		} else {
			$url = Utils::clean_url( $content_url ) . $item['sized_url'];
		}

		/**
		 * The URL to be searched for and prepared to be delivered by Cloudinary.
		 *
		 * @hook   cloudinary_delivery_searchable_url
		 * @since  3.1.6
		 *
		 * @param $url         {string} The URL to be searched for and prepared to be delivered by Cloudinary.
		 * @param $item        {array}  The found asset array.
		 * @param $content_url {string} The content URL.
		 *
		 * @return {string}
		 */
		$url = apply_filters( 'cloudinary_delivery_searchable_url', $url, $item, $content_url );

		$found = array();

		// If there's no public ID then don't pollute the found items.
		if ( ! empty( $item['public_id'] ) ) {
			$found[ $item['public_id'] ] = $item;
		}
		$scaled                     = Utils::make_scaled_url( $url );
		$descaled                   = Utils::descaled_url( $url );
		$scaled_slashed             = addcslashes( $scaled, '/' );
		$descaled_slashed           = addcslashes( $descaled, '/' );
		$found[ $scaled ]           = $item;
		$found[ $descaled ]         = $item;
		$found[ $scaled_slashed ]   = array_merge( $item, array( 'slashed' => true ) );
		$found[ $descaled_slashed ] = array_merge( $item, array( 'slashed' => true ) );

		if ( ! $this->is_deliverable( $item['post_id'] ) ) {
			$this->unusable = array_merge( $this->unusable, $found );

			return;
		}

		$this->known = array_merge( $this->known, $found );

		if ( 'disable' === $item['post_state'] ) {
			return;
		}

		$is_media = 'media' === $item['sync_type'];
		$is_asset = 'inherit' !== $item['post_state'];

		if ( true === $auto_sync && true === $is_media && true === $is_asset ) {
			// Auto sync on - synced as asset - take over.
			$this->sync->delete_cloudinary_meta( $item['post_id'] );
			$this->sync->add_to_sync( $item['post_id'] );
		} elseif ( true === $auto_sync && true === $is_media && empty( $item['public_id'] ) ) {
			// Un-synced media item with auto sync on. Add to sync.
			$this->sync->add_to_sync( $item['post_id'] );
		} elseif ( ! empty( $item['public_id'] ) ) {
			// Most likely an asset with a public ID.
			$this->usable[ $url ] = $url;
			if ( self::get_settings_signature() !== $item['signature'] ) {
				$sync_type = $this->sync->get_sync_type( $item['post_id'] );
				if ( $sync_type ) {
					$this->sync->add_to_sync( $item['post_id'] );
					if ( $this->sync->is_required( $sync_type, $item['post_id'] ) ) {
						// Can't render this, so lets remove it from usable list.
						unset( $this->usable[ $url ] );
					}
				}
			}
		} else {
			// This is an asset or media without a public id.
			$this->unusable[ $item['sized_url'] ] = $item;
		}
	}

	/**
	 * Sanitize a url.
	 *
	 * @param string $url URL to sanitize.
	 *
	 * @return string|null
	 */
	protected function sanitize_url( $url ) {

		// Catch mixed URLs.
		if ( 5 < strlen( $url ) && false !== strpos( $url, 'https://', 5 ) ) {
			$url = substr( $url, strpos( $url, 'https://', 5 ) );
		}

		// Remove Query string.
		$url = strtok( $url, '?' );

		// check the url is more than a domain.
		if ( ! filter_var( utf8_uri_encode( $url ), FILTER_VALIDATE_URL ) || 3 > substr_count( rtrim( $url, '/' ), '/' ) ) {
			$url = null;
		}

		return $url;
	}

	/**
	 * Maybe remove a size from a URL.
	 *
	 * @param string $url The url to remove size from.
	 *
	 * @return string
	 */
	public function maybe_unsize_url( $url ) {
		$file = Utils::pathinfo( $url, PATHINFO_FILENAME );
		$dash = ltrim( strrchr( $file, '-' ), '-' );
		if (
			! empty( $dash )
			&& 1 === substr_count( $dash, 'x' )
			&& is_numeric( str_replace( 'x', '', $dash ) )
			&& 2 === count( array_filter( explode( 'x', $dash ) ) )
		) {
			$sized                                = wp_basename( $url );
			$url                                  = str_replace( '-' . $dash, '', $url );
			$scaled                               = Utils::make_scaled_url( $url );
			$this->found_urls[ $url ][ $dash ]    = $sized;
			$this->found_urls[ $scaled ][ $dash ] = $sized;
		}

		return $url;
	}

	/**
	 * Prepare the delivery for filtering URLS.
	 *
	 * @param string $content The content html.
	 */
	public function prepare_delivery( $content ) {
		$content    = wp_unslash( $content );
		$all_urls   = array_unique( Utils::extract_urls( $content ) );
		$base_urls  = array_filter( array_map( array( $this, 'sanitize_url' ), $all_urls ) );
		$clean_urls = array_map( array( 'Cloudinary\Utils', 'clean_url' ), $base_urls );
		$urls       = array_filter( $clean_urls, array( $this, 'validate_url' ) );
		$decoded    = array_map( 'urldecode', $urls );

		// De-size.
		$desized = array_unique( array_map( array( $this, 'maybe_unsize_url' ), $urls ) );
		$scaled  = array_unique( array_map( array( Utils::class, 'make_scaled_url' ), $desized ) );
		$urls    = array_unique( array_merge( $desized, $scaled, $decoded ) );
		$urls    = array_values( $urls ); // resets the index.

		$public_ids = array();
		// Lets only look for Cloudinary URLs on the frontend.
		if ( ! Utils::is_admin() ) {
			// clean out empty urls.
			$cloudinary_urls = array_filter( $base_urls, array( $this->media, 'is_cloudinary_url' ) ); // clean out empty urls.
			// Clean URLS for search.
			$all_public_ids = array_filter( array_map( array( $this->media, 'get_public_id_from_url' ), $cloudinary_urls ) );
			$public_ids     = array_unique( $all_public_ids );
		}
		if ( empty( $urls ) && empty( $public_ids ) ) {
			return; // Bail since theres nothing.
		}

		$paths = array_map( array( Utils::class, 'get_path_from_url' ), $urls );

		// Get the results that include the public IDs, the paths (from Media Library), and urls for additional assets.
		$results = Utils::query_relations( $public_ids, array_merge( $paths, $urls ) );

		$auto_sync = $this->sync->is_auto_sync_enabled();
		foreach ( $results as $result ) {
			$this->set_usability( $result, $auto_sync );
		}
		// Set unknowns.
		$this->unknown = $this->filter_unknown_urls( $urls );
	}

	/**
	 * Filter URLs to determine which are truly unknown, considering image size variations.
	 *
	 * This method treats image size variations (e.g., example-300x224.png) as "known"
	 * if their base image (e.g., example.png) exists in the known URLs, while still
	 * catching genuinely unknown URLs.
	 *
	 * @param array $urls All URLs found in content.
	 *
	 * @return array Array of genuinely unknown URLs.
	 */
	protected function filter_unknown_urls( $urls ) {
		$known_keys = array_keys( $this->known );

		if ( empty( $known_keys ) ) {
			return $urls;
		}

		$known_lookup      = array_flip( $known_keys );
		$potential_unknown = array_diff( $urls, $known_keys );

		if ( empty( $potential_unknown ) ) {
			return array();
		}

		$truly_unknown = array();

		foreach ( $potential_unknown as $url ) {
			// Check if this might be a sized variation of a known image.
			$base_url = $this->maybe_unsize_url( $url );

			// If base image is known, skip this variation.
			if ( isset( $known_lookup[ $base_url ] ) ) {
				continue;
			}

			// Check scaled version if base wasn't found and URL was actually "unsized".
			if ( $base_url !== $url ) {
				$scaled_url = Utils::make_scaled_url( $base_url );
				if ( isset( $known_lookup[ $scaled_url ] ) ) {
					continue; // Scaled version is known, skip this variation.
				}
			}

			// This URL is genuinely unknown.
			$truly_unknown[] = $url;
		}

		/**
		 * Filter the list of truly unknown URLs after filtering out image size variations.
		 *
		 * @hook   cloudinary_filter_unknown_urls
		 * @since  3.2.12
		 *
		 * @param array $truly_unknown The filtered list of unknown URLs.
		 * @param array $urls          The original list of all URLs.
		 * @param array $known_keys    The list of known URL keys.
		 *
		 * @return array The filtered list of unknown URLs.
		 */
		return apply_filters( 'cloudinary_filter_unknown_urls', $truly_unknown, $urls, $known_keys );
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 * @param string $context The content of the content.
	 */
	public function catch_urls( $content, $context = 'view' ) {

		// Check if we are saving. If so, bail.
		// This is to prevent the replacement from happening in the shutdown, signaling content changes in the editor.
		if ( $this->plugin->get_component( 'replace' )->doing_save() ) {
			return;
		}
		$this->init_delivery();
		$this->prepare_delivery( $content );

		if ( ! empty( $this->known ) ) {
			$known = $this->convert_tags( $content, $context );
			// Replace the knowns.
			foreach ( $known as $src => $replace ) {
				String_Replace::replace( $src, $replace );
			}
		}
		// Attempt to get the unknowns.
		if ( ! empty( $this->unknown ) ) {
			$this->find_attachment_size_urls();
		}
	}
}
