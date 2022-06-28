<?php
/**
 * Relate class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;
use Cloudinary\Relate\Relationship;

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
		add_filter( 'cloudinary_update_post_meta', array( $this, 'update_meta' ), 10, 4 );
		add_filter( 'cloudinary_delete_post_meta', array( $this, 'delete_meta' ), 10, 3 );
		add_filter( 'cloudinary_get_post_meta', array( $this, 'get_meta' ), 10, 3 );
		add_action( 'cloudinary_upgrade_asset', array( $this, 'upgrade_relation' ), 10, 2 );
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
			$meta = get_post_meta( $attachment_id, Sync::META_KEYS['cloudinary'], true );
			if ( ! empty( $meta[ Sync::META_KEYS['transformation'] ] ) ) {
				$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['transformation'], $meta[ Sync::META_KEYS['transformation'] ] );
			}
		}
	}

	/**
	 * Get relation meta.
	 *
	 * @param array  $check   The check flag.
	 * @param string $key     The meta key.
	 * @param int    $post_id The attachment ID.
	 *
	 * @retrun null|mixed
	 */
	public function get_meta( $check, $key, $post_id ) {
		if ( Sync::META_KEYS['transformation'] === $key || '' === $key ) {
			$relationship    = Relationship::get_relationship( $post_id );
			$check           = array();
			$transformations = $relationship->transformations;
			if ( ! empty( $transformations ) ) {
				$check = $this->media->get_transformations_from_string( $transformations );
			}
			if ( '' === $key ) {
				$meta                                      = get_post_meta( $post_id, Sync::META_KEYS['cloudinary'], true );
				$meta[ Sync::META_KEYS['transformation'] ] = $check;
				$check                                     = $meta;
			}
		}

		return $check;
	}

	/**
	 * Update meta in relationship table.
	 *
	 * @param array  $check   The check flag.
	 * @param mixed  $value   The meta value.
	 * @param string $key     The meta key.
	 * @param int    $post_id The attachment ID.
	 *
	 * @return bool|null
	 */
	public function update_meta( $check, $value, $key, $post_id ) {
		if ( Sync::META_KEYS['transformation'] === $key ) {
			$check = $this->update_transformations( $post_id, $value );
		}

		return $check;
	}

	/**
	 * Delete meta in relationship table.
	 *
	 * @param array  $check   The check flag.
	 * @param string $key     The meta key.
	 * @param int    $post_id The attachment ID.
	 *
	 * @return bool|null
	 */
	public function delete_meta( $check, $key, $post_id ) {
		if ( Sync::META_KEYS['transformation'] === $key ) {
			$check = $this->delete_transformations( $post_id );
		}

		return $check;
	}

	/**
	 * Delete transformations from the relationship table.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_transformations( $attachment_id ) {
		$relationship = Relationship::get_relationship( $attachment_id );
		$return       = false;
		if ( ! empty( $relationship ) ) {
			$relationship->transformations = null;
			$return                        = $relationship->save();
		}

		return $return;
	}

	/**
	 * Update transformations in relationship table.
	 *
	 * @param int          $post_id         The post id.
	 * @param array|string $transformations The array or string of transformations to update.
	 *
	 * @return null|bool
	 */
	public function update_transformations( $post_id, $transformations ) {
		$relationship = Relationship::get_relationship( $post_id );
		$return       = false;
		if ( ! empty( $relationship ) ) {
			$relationship->transformations = is_array( $transformations ) ? Api::generate_transformation_string( $transformations ) : $transformations;
			$return                        = $relationship->save();
		}

		return $return;
	}
}
