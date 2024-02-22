<?php
/**
 * Relate class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;
use Cloudinary\Relate\Relationship;
use WP_Query;

/**
 * Class Relate
 */
class Relate {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Relate constructor.
	 *
	 * @param Plugin $plugin Instance of the main plugin.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->media  = $plugin->get_component( 'media' );
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 */
	protected function register_hooks() {
		add_action( 'cloudinary_upgrade_asset', array( $this, 'upgrade_relation' ), 10, 2 );
		add_filter( 'found_posts', array( $this, 'warm_cache' ), 10, 2 );
	}

	/**
	 * Warm the cache for found posts.
	 *
	 * @param int      $found_posts The number of posts found.
	 * @param WP_Query $query       The WP_Query instance (passed by reference).
	 *
	 * @return int
	 */
	public function warm_cache( $found_posts, $query ) {
		if ( ! empty( $found_posts ) && 'attachment' === $query->query_vars['post_type'] && ! empty( $query->posts ) ) {
			Relationship::preload( $query->posts );
		}

		return $found_posts;
	}

	/**
	 * Upgrade an asset relation.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $version       The version upgrading to.
	 */
	public function upgrade_relation( $attachment_id, $version ) {
		$asset_plugin_version = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['plugin_version'], true );
		if ( ! empty( $asset_plugin_version ) && version_compare( $asset_plugin_version, $version, '<' ) ) {
			$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['transformation'] );
		}
	}

	/**
	 * Update transformations for an asset.
	 *
	 * @param int               $attachment_id   The attachment ID.
	 * @param array|string|null $transformations The transformations.
	 */
	public static function update_transformations( $attachment_id, $transformations ) {
		$relationship = Relationship::get_relationship( $attachment_id );
		if ( is_array( $transformations ) ) {
			$transformations = Api::generate_transformation_string( $transformations, $relationship->asset_type );
		}
		$relationship->transformations = $transformations;
		$relationship->save();
	}

	/**
	 * Get the transformations for an asset.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $as_string     Set the output to a string.
	 *
	 * @return array|string
	 */
	public static function get_transformations( $attachment_id, $as_string = false ) {
		static $media;
		if ( ! $media ) {
			$media = get_plugin_instance()->get_component( 'media' );
		}

		$relationship    = Relationship::get_relationship( $attachment_id );
		$transformations = $relationship->transformations;
		if ( ! $as_string ) {
			$transformations = ! empty( $transformations ) ? $media->get_transformations_from_string( $transformations, $relationship->asset_type ) : array();
		}

		return $transformations;
	}
}
