<?php
/**
 * Storage Options. Handles storing setting in WP Options.
 *
 * @package   Cloudinary\Settings\Storage
 */

namespace Cloudinary\Settings\Storage;

use Cloudinary\Media;
use WP_Post;
use function Cloudinary\get_plugin_instance;

/**
 * Class Meta
 *
 * @package Cloudinary\Settings\Storage
 */
class Post_Meta extends Storage {

	/**
	 * The plugin instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Post_Meta constructor.
	 *
	 * @param string $prefix Holds the storage prefix.
	 */
	public function __construct( $prefix ) {
		parent::__construct( $prefix );
		$plugin      = get_plugin_instance();
		$this->media = $plugin->get_component( 'media' );
	}

	/**
	 * Load the data from storage source.
	 *
	 * @param string $prefixed_slug The slug to load.
	 *
	 * @return mixed
	 */
	protected function load( $prefixed_slug ) {
		$post = get_post();
		$data = null;

		if ( $post instanceof WP_Post ) {
			$data = $this->media->get_post_meta( $post->ID, $prefixed_slug, true );
		}

		return $data;
	}

	/**
	 * Save the data to storage source.
	 *
	 * @param string $slug The slug of the setting storage to save.
	 *
	 * @return bool
	 */
	public function save( $slug ) {
		$save = false;
		$post = get_post();

		if ( $post instanceof WP_Post ) {
			$save = $this->media->update_post_meta( $post->ID, $this->prefix( $slug ), $this->get( $slug ) );
		}

		return $save;
	}

	/**
	 * Delete the data from storage source.
	 *
	 * @param string $slug The slug of the setting storage to delete.
	 *
	 * @return bool
	 */
	public function delete( $slug ) {
		$delete = false;
		$post   = get_post();

		if ( $post instanceof WP_Post ) {
			$delete = $this->media->delete_post_meta( $post->ID, $this->prefix( $slug ) );
		}

		return $delete;
	}
}
