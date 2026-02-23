<?php
/**
 * SVG Support Extension for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Sync;
use WP_Error;
use WP_Post;

/**
 * Class extension
 */
class SVG extends Delivery_Feature {

	/**
	 * Holds the connect instance.
	 *
	 * @var \Cloudinary\Connect
	 */
	protected $connect;

	/**
	 * Holds the enabler slug.
	 *
	 * @var string
	 */
	protected $enable_slug = 'svg_support';

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'media_display';

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
	 * Add the ext for SVG to the ext2type.
	 *
	 * @param array $types List of file types.
	 *
	 * @return array
	 */
	public function add_svg_ext_type( $types ) {
		$types['image'][] = 'svg';

		return $types;
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {

		if ( 'svg' === $tag_element['format'] ) {
			$transformation = $this->media->get_transformations_from_string( $tag_element['atts']['data-transformations'] );
			$this->media->set_transformation( $transformation, 'fetch_format', 'svg' );
			$this->media->set_transformation( $transformation, 'flags', 'sanitize' );
			$tag_element['atts']['data-transformations'] = Api::generate_transformation_string( $transformation );
			$tag_element['atts']['src']                  = $this->media->cloudinary_url( $tag_element['id'], 'raw', $transformation, $tag_element['atts']['data-public-id'], true );
		}

		return $tag_element;
	}

	/**
	 * Validate if a file is an XML SVG.
	 *
	 * @param string      $file              Path to the file.
	 * @param null|string $original_filename Optional original filename.
	 *
	 * @return bool
	 */
	public function validate_svg_file( $file, $original_filename = null ) {
		$valid = false;
		if ( empty( $original_filename ) ) {
			$original_filename = $file;
		}
		$ext = Utils::pathinfo( $original_filename, PATHINFO_EXTENSION );
		if ( $ext && 'svg' === strtolower( $ext ) ) {
			libxml_use_internal_errors();
			$data = simplexml_load_file( $file );
			if ( 'svg' === $data->getName() ) {
				$width   = $data->attributes()['width'];
				$height  = $data->attributes()['height'];
				$viewbox = $data->attributes()['viewBox'];
				if ( ! empty( $viewbox ) ) {
					$viewbox = explode( ' ', $viewbox );
				}
				if ( ( ! empty( $width ) && ! empty( $height ) ) || ( ! empty( $viewbox[2] ) && ! empty( $viewbox[3] ) ) ) {
					$valid = true;
				}
			}
		}

		return $valid;
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
		if ( 'svg' === Utils::pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$wp_check_filetype_and_ext['ext']  = false;
			$wp_check_filetype_and_ext['type'] = false;
			if ( true === $this->validate_svg_file( $file, $filename ) ) {
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
	 * Remove eager transformations f_auto,q_auto for SVGs.
	 *
	 * @param array   $options    Upload options array.
	 * @param WP_Post $attachment The attachment post.
	 *
	 * @return array
	 */
	public function remove_svg_eagers( $options, $attachment ) {
		if ( static::is_svg( $attachment->ID ) ) {
			unset( $options['eager'], $options['eager_async'] );
		}

		return $options;
	}

	/**
	 * Make SVGs upload eager and sanitized.
	 *
	 * @param array $args          The default upload args.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function upload_args( $args, $attachment_id ) {

		if ( static::is_svg( $attachment_id ) ) {
			$args['body']['resource_type'] = 'auto';
			$args['body']['eager']         = 'fl_sanitize';
		}
		return $args;
	}

	/**
	 * Maybe setup SVG metadata.
	 *
	 * @param int            $attachment_id The attachment ID.
	 * @param array|WP_Error $result        The upload result.
	 */
	public function maybe_setup_metadata( $attachment_id, $result ) {

		if ( is_array( $result ) && static::is_svg( $attachment_id ) ) {
			$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$meta      = wp_get_attachment_metadata( $attachment_id );
			if ( empty( $meta ) ) {
				$meta = array();
			}
			$meta['file']   = $file_path;
			$meta['width']  = $result['width'];
			$meta['height'] = $result['height'];
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta );
		}
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		// It's always active if on.
		return 'on' === $this->config[ $this->enable_slug ];
	}

	/**
	 * Disable SVG conversion.
	 * If SVG support is active, we don't want to convert SVGs to other formats.
	 *
	 * @param array $base_types The base types for conversion.
	 *
	 * @return array
	 */
	public function disable_svg_conversion( $base_types ) {
		if ( $this->is_active() && ! empty( $base_types['svg'] ) ) {
			unset( $base_types['svg'] );
		}

		return $base_types;
	}

	/**
	 * Setup the component
	 */
	public function setup_hooks() {

		// Init instances.
		$this->connect = $this->plugin->get_component( 'connect' );
		$this->media   = $this->plugin->get_component( 'media' );

		// Add filters.
		add_filter( 'upload_mimes', array( $this, 'add_svg_mime' ) ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
		add_filter( 'ext2type', array( $this, 'add_svg_ext_type' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_svg_type' ), 10, 4 );
		add_filter( 'cloudinary_allowed_extensions', array( $this, 'allow_svg_for_cloudinary' ) );
		add_filter( 'cloudinary_upload_options', array( $this, 'remove_svg_eagers' ), 10, 2 );
		add_filter( 'cloudinary_upload_args', array( $this, 'upload_args' ), 10, 2 );
		add_filter( 'cloudinary_convert_media_types', array( $this, 'disable_svg_conversion' ) );

		// Add actions.
		add_action( 'cloudinary_uploaded_asset', array( $this, 'maybe_setup_metadata' ), 10, 2 );
	}

	/**
	 * Check if an attachment is an SVG.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public static function is_svg( $attachment_id ) {
		return 'image/svg+xml' === get_post_mime_type( $attachment_id );
	}
}
