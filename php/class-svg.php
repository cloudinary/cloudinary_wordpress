<?php
/**
 * SVG Support Extension for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use WP_Post;

/**
 * Class extension
 */
class SVG extends Extension {

	/**
	 * Holds the connect instance.
	 *
	 * @var \Cloudinary\Connect
	 */
	protected $connect;

	/**
	 * Holds the Media instance.
	 *
	 * @var \Cloudinary\Media
	 */
	protected $media;

	/**
	 * Holds the sync instance.
	 *
	 * @var \Cloudinary\Sync
	 */
	protected $sync;

	/**
	 * Add the correct mime type to WordPress.
	 *
	 * @param array $types List of allowed mimetypes.
	 *
	 * @return array
	 */
	public function add_svg_mime( $types ) {
		$types['svg'] = 'image/svg+xml';

		return $types;
	}

	/**
	 * Validate if a file is an XML SVG.
	 *
	 * @param string $file Path to the file.
	 *
	 * @return bool
	 */
	public function validate_svg_file( $file ) {
		$valid = false;
		libxml_use_internal_errors();
		$data = simplexml_load_file( $file );
		if ( 'svg' === $data->getName() ) {
			$width   = $data->attributes()['width'];
			$height  = $data->attributes()['height'];
			$viewbox = $data->attributes()['viewBox'];
			if ( ! empty( $viewbox ) ) {
				$viewbox = explode( ' ', $viewbox );
			}
			if ( ! empty( $width ) && ! empty( $height ) || ! empty( $viewbox[2] ) && ! empty( $viewbox[3] ) ) {
				$valid = true;
			}
		}

		return $valid;
	}

	/**
	 * Sanitize an SVG file with Cloudinary.
	 *
	 * @param string $file The file to sanitize.
	 *
	 * @return array|\WP_Error
	 */
	public function sanitize_svg( $file ) {
		$folder    = $this->media->get_cloudinary_folder();
		$public_id = '';
		if ( ! empty( $folder ) ) {
			$public_id = $folder . '/';
		}
		$public_id .= uniqid( 'tmp-svg' ) . pathinfo( $file, PATHINFO_FILENAME );

		if ( function_exists( 'curl_file_create' ) ) {
			$upload_file = curl_file_create( $file ); // phpcs:ignore
			$upload_file->setPostFilename( $file );
		} else {
			$upload_file = '@' . $file;
		}

		$public_id = wp_normalize_path( $public_id );

		$options = array(
			'file'          => $upload_file,
			'resource_type' => 'auto',
			'public_id'     => $public_id,
			'eager'         => 'fl_sanitize',
		);
		$result  = $this->connect->api->upload_cache( $options );

		if ( ! is_wp_error( $result ) ) {
			// stream sanitized data to file..
			wp_safe_remote_get(
				$result['eager'][0]['secure_url'],
				array(
					'timeout'  => 300, // phpcs:ignore
					'stream'   => true,
					'filename' => $file,
				)
			);

			$options = array(
				'public_id'  => $public_id,
				'invalidate' => true, // clear from CDN cache as well.
			);
			$this->connect->api->destroy( 'image', $options );
		}

		return $result;
	}

	/**
	 * The sanitize sync method.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array|\WP_Error
	 */
	public function sanitize_sync( $attachment_id ) {
		$file      = get_attached_file( $attachment_id );
		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$sanitized = $this->sanitize_svg( $file );
		if ( ! is_wp_error( $sanitized ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( empty( $meta ) ) {
				$meta = array();
			}
			$meta['file']   = $file_path;
			$meta['width']  = $sanitized['width'];
			$meta['height'] = $sanitized['height'];
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta );
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['transformation'], array( array( 'fetch_format' => 'svg' ) ) );
		}
		$this->sync->set_signature_item( $attachment_id, 'sanitize_svg' );

		return $sanitized;
	}

	/**
	 * Check that the file is in fact an SVG.
	 *
	 * @param array  $wp_check_filetype_and_ext The ext and type from WordPress.
	 * @param string $file                      The file path.
	 * @param string $filename                  The file name.
	 *
	 * @return array
	 */
	public function check_svg_type( $wp_check_filetype_and_ext, $file, $filename ) {
		if ( 'svg' === pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$wp_check_filetype_and_ext['ext']  = false;
			$wp_check_filetype_and_ext['type'] = false;
			if ( true === $this->validate_svg_file( $file ) ) {
				$wp_check_filetype_and_ext['ext']  = 'svg';
				$wp_check_filetype_and_ext['type'] = 'image/svg+xml';
			}
		}

		return $wp_check_filetype_and_ext;
	}

	/**
	 * Add svg to allowed types.
	 *
	 * @param array $types Allowed Cloudinary types.
	 *
	 * @return array
	 */
	public function allow_svg_for_cloudinary( $types ) {
		$types[] = 'svg';

		return $types;
	}

	/**
	 * Register the sanitize sync type.
	 */
	protected function register_sync_type() {
		$structure = array(
			'asset_state' => 0,
			'generate'    => '__return_true',
			'priority'    => 0.1,
			'sync'        => array( $this, 'sanitize_sync' ),
			'validate'    => function ( $attachment_id ) {
				return $this->validate_svg_file( get_attached_file( $attachment_id ) );
			},
			'state'       => 'info',
			'note'        => __( 'Sanitizing SVG', 'cloudinary' ),
			'realtime'    => false,
		);
		$this->sync->register_sync_type( 'sanitize_svg', $structure );
	}

	/**
	 * Remove eager transformations f_auto,q_auto for SVGs.
	 *
	 * @param array   $options    Upload options array.
	 * @param WP_Post $attachment The attachment post.
	 *
	 * @return array
	 */
	public function remove_svg_eagers( $options, $attachment ) {
		if ( 'image/svg+xml' === $attachment->post_mime_type ) {
			unset( $options['eager'], $options['eager_async'] );
		}

		return $options;
	}

	/**
	 * Setup the component
	 */
	public function setup() {

		// Init instances.
		$this->connect = $this->plugin->get_component( 'connect' );
		$this->media   = $this->plugin->get_component( 'media' );
		$this->sync    = $this->plugin->get_component( 'sync' );

		// Add filters.
		add_filter( 'upload_mimes', array( $this, 'add_svg_mime' ) ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_svg_type' ), 10, 4 );
		add_filter( 'cloudinary_allowed_extensions', array( $this, 'allow_svg_for_cloudinary' ) );
		add_filter( 'cloudinary_upload_options', array( $this, 'remove_svg_eagers' ), 10, 2 );
		$this->register_sync_type();
	}
}
