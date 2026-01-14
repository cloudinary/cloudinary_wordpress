<?php
/**
 * Relationship class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Relate;

use Cloudinary\Sync;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 * Class Relationship
 *
 * @property string|null $post_state
 * @property string|null $public_hash
 * @property string|null $public_id
 * @property string|null $signature
 * @property string|null $transformations
 * @property string|null $text_overlay
 * @property string|null $image_overlay
 * @property string|null $sized_url
 * @property string|null $media_context
 */
class Relationship {

	/**
	 * The relationship post id.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Flag to save the relationship on shutdown.
	 *
	 * @var bool
	 */
	protected $save_on_shutdown = false;

	/**
	 * Holds the asset type.
	 *
	 * @var string
	 */
	public $asset_type;

	/**
	 * Holds the media context.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * The constructor.
	 *
	 * @param int $post_id The relationship post id.
	 */
	public function __construct( $post_id ) {
		$this->post_id    = (int) $post_id;
		$this->asset_type = get_plugin_instance()->get_component( 'media' )->get_media_type( $this->post_id );
		$this->context    = Utils::get_media_context( $post_id );
	}

	/**
	 * Get the relationship data.
	 *
	 * @return array|null
	 */
	public function get_data() {
		$key = $this->post_id . $this->context;

		$cache_data = wp_cache_get( $key, Sync::META_KEYS['cloudinary'] );

		if ( $cache_data ) {
			return $cache_data;
		}
		global $wpdb;
		$table_name = Utils::get_relationship_table();

		$sql  = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE post_id = %d AND media_context = %s", $this->post_id, $this->context ); // phpcs:ignore WordPress.DB
		$data = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB

		self::set_cache( $this->post_id, $this->context, $data );

		return $data;
	}

	/**
	 * Set a relationship data value.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 */
	public function __set( $key, $value ) {
		$data         = $this->get_data();
		$data[ $key ] = $value;
		self::set_cache( $this->post_id, $this->context, $data );
	}

	/**
	 * Get a relationship data value.
	 *
	 * @param string $key The key.
	 *
	 * @return mixed The value.
	 */
	public function __get( $key ) {
		$return     = null;
		$data_cache = $this->get_data();

		if ( isset( $data_cache[ $key ] ) ) {
			$return = $data_cache[ $key ];
		}

		return $return;
	}

	/**
	 * Check the existence of a relationship data value.
	 *
	 * @param string $key The key.
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		$data_cache = $this->get_data();

		return isset( $data_cache[ $key ] );
	}

	/**
	 * Set the save on shutdown flag.
	 */
	public function save() {
		if ( ! $this->save_on_shutdown ) {
			$this->save_on_shutdown = true;
			add_action( 'shutdown', array( $this, 'do_save' ) );
			add_action( 'shutdown', array( $this, 'flush_cache' ), 100 );
		}
	}

	/**
	 * Save the relationship data.
	 *
	 * @return bool
	 */
	public function do_save() {
		global $wpdb;
		$data   = $this->get_data();
		$update = false;

		if ( ! empty( $data['id'] ) ) {
			$update = $wpdb->update( Utils::get_relationship_table(), $data, array( 'id' => $data['id'] ), array( '%s' ), array( '%d' ) );// phpcs:ignore WordPress.DB
		}

		return $update;
	}

	/**
	 * Flush the cache.
	 *
	 * @return void
	 */
	public function flush_cache() {
		do_action( 'cloudinary_flush_cache', false );
	}

	/**
	 * Preload bulk relationships.
	 *
	 * @param array $post_ids The post ids.
	 */
	public static function preload( $post_ids ) {
		// Check if the post_ids are objects.
		$maybe_ids = array_column( $post_ids, 'ID' );
		if ( ! empty( $maybe_ids ) ) {
			// If so, make sure to just use the IDs.
			$post_ids = $maybe_ids;
		}
		global $wpdb;
		$table_name = Utils::get_relationship_table();
		// Do the public_ids.
		$list  = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$where = "post_id IN ( {$list} )";

		$sql   = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE {$where}", $post_ids ); // phpcs:ignore WordPress.DB
		$posts = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB

		foreach ( $posts as $post ) {
			$media_context = Utils::get_media_context( $post['post_id'] );
			self::set_cache( $post['post_id'], $media_context, $post );
		}
	}

	/**
	 * Set the relationship data cache.
	 *
	 * @param int    $post_id       The post id.
	 * @param string $media_context The media context.
	 * @param array  $data          The data.
	 */
	public static function set_cache( $post_id, $media_context, $data ) {
		$key = $post_id . $media_context;

		return wp_cache_set( $key, $data, Sync::META_KEYS['cloudinary'] );
	}

	/**
	 * Delete the relationship.
	 */
	public function delete() {
		global $wpdb;
		$table_name = Utils::get_relationship_table();
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_name,
			array(
				'post_id'       => $this->post_id,
				'media_context' => $this->context,
			),
			array(
				'%d',
				'%s',
			)
		);
		$this->delete_cache();
	}

	/**
	 * Delete the relationship cache.
	 */
	public function delete_cache() {
		$key = $this->post_id . $this->media_context;

		wp_cache_delete( $key, Sync::META_KEYS['cloudinary'] );
	}

	/**
	 * Get a relationship object.
	 *
	 * @param int $post_id The relationship post id.
	 *
	 * @return Relationship|null The relationship object or null if not found.
	 */
	public static function get_relationship( $post_id ) {

		static $cache = array();
		if ( ! isset( $cache[ $post_id ] ) ) {
			$cache[ $post_id ] = new self( $post_id );
		}

		return $cache[ $post_id ];
	}

	/**
	 * Get the attachment IDs by the public ID.
	 *
	 * @param string $public_id The public ID.
	 *
	 * @return array
	 */
	public static function get_ids_by_public_id( $public_id ) {
		global $wpdb;

		$table_name    = Utils::get_relationship_table();
		$media_context = Utils::get_media_context();
		$ids           = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table_name} WHERE public_id = %s AND media_context = %s", $public_id, $media_context ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map( 'intval', $ids );
	}

	/**
	 * Get the relationship in a different context.
	 *
	 * @param string $context The context.
	 *
	 * @return Relationship|null
	 */
	public function get_contextualized_relationship( $context ) {
		$relationship = null;
		if ( ! empty( $this->context ) && $this->context !== $context ) {
			global $wpdb;
			$table_name = Utils::get_relationship_table();

			$sql     = $wpdb->prepare( "SELECT post_id FROM {$table_name} WHERE url_hash = %s AND media_context = %s", $this->url_hash, $context ); // phpcs:ignore WordPress.DB
			$post_id = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB

			if ( $post_id ) {
				$relationship          = self::get_relationship( $post_id );
				$relationship->context = $context;
			}
		}

		return $relationship;
	}
}
