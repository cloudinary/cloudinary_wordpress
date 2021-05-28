<?php
/**
 * Upgrades from a Legecy version of Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Sync;

/**
 * Class Filter.
 *
 * Handles filtering of HTML content.
 */
class Upgrade {

	/**
	 * Holds the Media instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Media Instance of the plugin.
	 */
	private $media;

	/**
	 * Holds the Sync instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Sync Instance of the plugin.
	 */
	private $sync;

	/**
	 * Filter constructor.
	 *
	 * @param \Cloudinary\Media $media The plugin.
	 */
	public function __construct( \Cloudinary\Media $media ) {
		$this->media = $media;
		$this->sync  = $media->plugin->components['sync'];
		$this->setup_hooks();
	}

	/**
	 * Convert an image post that was created from Cloudinary v1.
	 *
	 * @param int $attachment_id The attachment ID to convert.
	 *
	 * @return string Cloudinary ID
	 */
	public function convert_cloudinary_version( $attachment_id ) {

		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( wp_http_validate_url( $file ) ) {
			// Version 1 upgrade.
			$path                  = wp_parse_url( $file, PHP_URL_PATH );
			$media                 = $this->media;
			$parts                 = explode( '/', ltrim( $path, '/' ) );
			$cloud_name            = null;
			$asset_version         = 1;
			$asset_transformations = array();
			$id_parts              = array();
			$public_id             = $this->get_fetch_public_id( $path, $attachment_id );
			foreach ( $parts as $val ) {
				if ( empty( $val ) ) {
					continue;
				}
				if ( is_null( $cloud_name ) ) {
					// Cloudname will always be the first item.
					$cloud_name = md5( $val );
					continue;
				}
				if ( in_array( $val, array( 'images', 'image', 'video', 'upload', 'fetch' ), true ) ) {
					continue;
				}
				$transformation_maybe = $media->get_transformations_from_string( $val );
				if ( ! empty( $transformation_maybe ) ) {
					$asset_transformations = $transformation_maybe;
					continue;
				}
				if ( substr( $val, 0, 1 ) === 'v' && is_numeric( substr( $val, 1 ) ) ) {
					$asset_version = substr( $val, 1 );
					continue;
				}

				// Filter out file name.
				$path = pathinfo( $val, PATHINFO_FILENAME );
				if ( ! in_array( $path, $id_parts, true ) ) {
					$id_parts[] = pathinfo( $val, PATHINFO_FILENAME );
				}
			}
			// Build public_id.
			$parts = array_filter( $id_parts );
			if ( empty( $public_id ) ) {
				$public_id = implode( '/', $parts );
			}
			update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['version'], $asset_version );
			if ( ! empty( $asset_transformations ) ) {
				$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['transformation'], $asset_transformations );
			}
			$this->sync->set_signature_item( $attachment_id, 'cloud_name', $cloud_name );
		} else {
			// v2 upgrade.
			$public_id = $this->media->get_public_id( $attachment_id, true );
			$suffix    = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );
			if ( ! empty( $suffix ) ) {
				// Has suffix. Get delete and cleanup public ID.
				if ( false !== strpos( $public_id, $suffix ) ) {
					$public_id = str_replace( $suffix, '', $public_id );
				}
				$public_id .= $suffix;
				$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['suffix'] );
				update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
			}
			// Check folder sync in order.
			if ( $this->media->is_folder_synced( $attachment_id ) ) {
				$public_id_folder = ltrim( dirname( $this->media->get_public_id( $attachment_id ) ) );
				$test_signature   = md5( false );
				$folder_signature = md5( $public_id_folder );
				$signature        = $this->sync->get_signature( $attachment_id );
				if ( $folder_signature !== $test_signature && $test_signature === $signature['folder'] ) {
					// The test signature is a hashed false, which is how non-folder-synced items got hashed.
					// Indicating this is broken link.
					$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'] );
					$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['sync_error'] ); // Remove any errors from upgrade. they are outdated.
					$this->sync->set_signature_item( $attachment_id, 'folder' );
				}
			}
		}
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['plugin_version'], $this->media->plugin->version );
		$this->sync->set_signature_item( $attachment_id, 'upgrade' );
		$this->sync->set_signature_item( $attachment_id, 'public_id' );
		$this->sync->set_signature_item( $attachment_id, 'storage' );
		// Update Sync keys.
		$sync_key        = $public_id;
		$transformations = $this->media->get_transformation_from_meta( $attachment_id );
		if ( ! empty( $transformations ) ) {
			$sync_key .= wp_json_encode( $transformations );
		}
		update_post_meta( $attachment_id, '_' . md5( $sync_key ), true );
		update_post_meta( $attachment_id, '_' . md5( 'base_' . $public_id ), true );
		// Get a new uncached signature.
		$this->sync->get_signature( $attachment_id, true );

		return $public_id;
	}

	/**
	 * Maybe the upgraded attachment is a fetch image.
	 *
	 * @param string $path          The attachment path.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_fetch_public_id( $path, $attachment_id ) {
		$parts = explode( '/image/fetch/', $path );

		if ( ! empty( $parts[1] ) ) {
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['delivery'], 'fetch' );
			return $parts[1];
		}

		return '';
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {

		// Add a redirection to the new plugin settings, from the old plugin.
		if ( is_admin() ) {
			add_action(
				'admin_menu',
				function () {
					global $plugin_page;
					if ( ! empty( $plugin_page ) && false !== strpos( $plugin_page, 'cloudinary-image-management-and-manipulation-in-the-cloud-cdn' ) ) {
						wp_safe_redirect( admin_url( '?page=cloudinary' ) );
						die;
					}
				}
			);
		}
	}
}
