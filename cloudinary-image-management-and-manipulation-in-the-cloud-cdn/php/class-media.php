<?php
/**
 * Media class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Media\Filter;
use Cloudinary\Media\Upgrade;
use Cloudinary\Media\Global_Transformations;
use Cloudinary\Media\Video;

/**
 * Class Media
 */
class Media implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the base Cloudinary url.
	 *
	 * @since   0.1
	 *
	 * @var     string.
	 */
	private $base_url;

	/**
	 * Holds the Cloudinary folder.
	 *
	 * @since   0.1
	 *
	 * @var     string.
	 */
	private $cloudinary_folder;

	/**
	 * Holds the found Cloudinary ID's
	 *
	 * @since   0.1
	 *
	 * @var     array.
	 */
	private $cloudinary_ids = array();

	/**
	 * Cloudinary credentials.
	 *
	 * @var array.
	 */
	public $credentials;

	/**
	 * Cloudinary url filtering instance.
	 *
	 * @var \Cloudinary\Media\Filter.
	 */
	public $filter;

	/**
	 * Cloudinary upgrade instance.
	 *
	 * @var \Cloudinary\Media\Upgrade.
	 */
	public $upgrade;

	/**
	 * Cloudinary global transformations.
	 *
	 * @var \Cloudinary\Media\Global_Transformations.
	 */
	public $global_transformations;

	/**
	 * Video filter instance.
	 *
	 * @var \Cloudinary\Media\Video.
	 */
	public $video;

	/**
	 * Sync instance.
	 *
	 * @var \Cloudinary\Sync
	 */
	public $sync;

	/**
	 * Flag if in image_downsize function to prevent overload.
	 *
	 * @var bool
	 */
	private $in_downsize = false;

	/**
	 * Holds the max image width registered in WordPress.
	 *
	 * @var int
	 */
	private $max_width;

	/**
	 * Flag to determine if the Featured Image is currently being rendered.
	 *
	 * @var bool|int
	 */
	private $doing_featured_image = false;

	/**
	 * Media constructor.
	 *
	 * @param Plugin $plugin The global plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get an array of compatible media types that are used by Cloudinary.
	 *
	 * @return array
	 */
	public function get_compatible_media_types() {

		$media_types = array(
			'image',
			'video',
			'audio',
			'application',
		);

		/**
		 * Filter the default Cloudinary Media Types.
		 *
		 * @param array $types The default media types array.
		 *
		 * @return array
		 */
		return apply_filters( 'cloudinary_media_types', $media_types );
	}

	/**
	 * Get convertible extensions and converted file types.
	 *
	 * @return array
	 */
	public function get_convertible_extensions() {


		// Add preferred formats in future.
		$base_types = array(
			'psd'  => 'jpg',
			'ai'   => 'jpg',
			'eps'  => 'jpg',
			'ps'   => 'jpg',
			'ept'  => 'jpg',
			'eps3' => 'jpg',
			'indd' => 'jpg',
			'webp' => 'gif',
			'bmp'  => 'jpg',
			'flif' => 'jpg',
			'gltf' => 'jpg',
			'heif' => 'jpg',
			'heic' => 'jpg',
			'ico'  => 'png',
			'svg'  => 'png',
			'tga'  => 'jpg',
			'tiff' => 'jpg',
			'tif'  => 'jpg',
		);

		/**
		 * Filter the base types for conversion.
		 *
		 * @param array $base_types The base conversion types array.
		 */
		return apply_filters( 'cloudinary_convert_media_types', $base_types );
	}

	/**
	 * Check if a file type is compatibile with Cloudinary & WordPress.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_file_compatible( $file ) {

		$types        = $this->get_compatible_media_types();
		$filename     = pathinfo( $file, PATHINFO_BASENAME );
		$mime         = wp_check_filetype( $filename );
		$type         = strstr( $mime['type'], '/', true );
		$conversions  = $this->get_convertible_extensions();
		$convertibles = array_keys( $conversions );

		return in_array( $type, $types, true ) && ! in_array( $mime['ext'], $convertibles );
	}

	/**
	 * Check if the attachment is a media file.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 *
	 * @return bool
	 */
	public function is_media( $attachment_id ) {
		$is_media = false;
		if ( 'attachment' === get_post_type( $attachment_id ) && wp_get_attachment_metadata( $attachment_id ) ) {
			$media_types = $this->get_compatible_media_types();
			$type        = $this->get_media_type( $attachment_id );
			$is_media    = in_array( $type, $media_types );
		}

		return $is_media;
	}

	/**
	 * Convert media extension.
	 *
	 * @param string $filename The file to convert.
	 *
	 * @return string|null
	 */
	public function convert_media_extension( $filename ) {

		$conversion_types = $this->get_convertible_extensions();
		$info             = pathinfo( $filename );
		$extension        = strtolower( $info['extension'] );
		$convert          = 'jpg'; // Default handler.

		if ( ! empty( $conversion_types[ $extension ] ) ) {
			$convert = $conversion_types[ $extension ];
		}

		$filename = trailingslashit( $info['dirname'] ) . $info['filename'] . '.' . $convert;

		return $filename;
	}

	/**
	 * Checks if the file is for preview only. True = only render sizes converted.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 *
	 * @return bool
	 */
	public function is_preview_only( $attachment_id ) {
		$base_types = array(
			'pdf',
			'psd',
		);

		/**
		 * Filter the file types that are preview only.
		 *
		 * @param array $base_types The base preview types.
		 */
		$preview_types = apply_filters( 'cloudinary_preview_types', $base_types );
		$mime          = wp_check_filetype( get_attached_file( $attachment_id ) );

		return in_array( $mime['ext'], $preview_types, true );
	}

	/**
	 * Get a resource type based on file. (Cloudinary v1 remove mime type in post data).
	 *
	 * @param string $file The file to get type for.
	 *
	 * @return string
	 */
	public function get_file_type( $file ) {
		$file = pathinfo( $file, PATHINFO_BASENAME );
		$mime = wp_check_filetype( $file );

		return strstr( $mime['type'], '/', true );
	}

	/**
	 * Get a resource type based on attachment_id.
	 *
	 * @param \WP_Post|int $attachment_id The attachment ID or object.
	 *
	 * @return string
	 */
	public function get_media_type( $attachment_id ) {
		return $this->get_file_type( get_attached_file( $attachment_id ) );
	}

	/**
	 * Remove the crop size from a url.
	 *
	 * @param string $url The url to remove the crop from.
	 *
	 * @return string The uncropped url.
	 */
	public function uncropped_url( $url ) {
		$cropped = $this->get_size_from_url( $url );
		if ( false !== $cropped ) {
			$file             = pathinfo( $url );
			$crop             = '-' . implode( 'x', $cropped );
			$file['filename'] = substr( $file['filename'], 0, strlen( $file['filename'] ) - strlen( $crop ) );
			$url              = $file['dirname'] . '/' . $file['filename'] . '.' . $file['extension'];
		}

		return $url;
	}

	/**
	 * Attempt to get an attachment_id from a url.
	 *
	 * @param string $url The url of the file.
	 *
	 * @return int The attachment id or 0 if not found.
	 */
	public function get_id_from_url( $url ) {
		if ( $this->is_cloudinary_url( $url ) ) {
			$path  = wp_parse_url( $url, PHP_URL_PATH );
			$parts = explode( '/', ltrim( $path, '/' ) );
			// Need to find the version part as anything after this is the public id.
			foreach ( $parts as $part ) {
				array_shift( $parts ); // Get rid of the first element.
				if ( 'v' === substr( $part, 0, 1 ) && is_numeric( substr( $part, 1 ) ) ) {
					break; // Stop removing elements.
				}
			}

			// The remaining items should be the file.
			$file            = implode( '/', $parts );
			$pathinfo        = pathinfo( $file );
			$public_id       = trim( $pathinfo['dirname'] . '/' . $pathinfo['filename'], './' );
			$sync_key        = $public_id;
			$transformations = $this->get_transformations_from_string( $url );
			if ( ! empty( $transformations ) ) {
				$sync_key .= wp_json_encode( $transformations );
			}
			$attachment_id = $this->get_id_from_sync_key( $sync_key );

		} else {

			// Clear out any params.
			if ( wp_parse_url( $url, PHP_URL_QUERY ) ) {
				$url = strstr( $url, '?', true );
			}
			// Local URL.
			$url = $this->uncropped_url( $url );

			// Remove the base URL so we can match it to the post meta.
			$dirs = wp_get_upload_dir();
			$file = ltrim( substr( $url, strlen( $dirs['baseurl'] ) + 1 ), '/' ); // Keep the slash off.

			if ( function_exists( 'wpcom_vip_attachment_url_to_postid' ) ) {
				$attachment_id = wpcom_vip_attachment_url_to_postid( $file );
			} else {
				$attachment_id = attachment_url_to_postid( $file ); //phpcs:ignore
			}
		}

		return $attachment_id;

	}

	/**
	 * Attempt to get an attachment_id from a sync key.
	 *
	 * @param string $sync_key Key for matching a post_id.
	 * @param bool   $all      Flag to return all found ID's.
	 *
	 * @return int|array|false The attachment id or id's, or false if not found.
	 */
	public function get_id_from_sync_key( $sync_key, $all = false ) {

		$meta_query = array(
			array(
				'key'     => '_' . md5( $sync_key ),
				'compare' => 'EXISTS',
			),
		);
		$query_args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => $meta_query, // phpcs:ignore
		);

		$query         = new \WP_Query( $query_args );
		$ids           = $query->get_posts();
		$attachment_id = $ids;

		if ( ! empty( $ids ) && false === $all ) {
			// Essentially we should only have a single so use the first.
			$attachment_id = array_shift( $ids );
		}

		return $attachment_id;
	}

	/**
	 * Get all ID's linked to a public_id.
	 *
	 * @param string $public_id Key for matching a post_id.
	 *
	 * @return array
	 */
	public function get_linked_attachments( $public_id ) {

		$meta_query = array(
			array(
				'key'     => '_' . md5( $public_id ),
				'compare' => 'EXISTS',
			),
		);
		$query_args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => $meta_query, // phpcs:ignore
		);

		$query = new \WP_Query( $query_args );
		$ids   = $query->get_posts();

		return $ids;
	}

	/**
	 * Determine crop based on filename.
	 *
	 * @param string $url The url to get sizes from.
	 *
	 * @return array | bool Array of width and height else false if not found.
	 */
	public function get_size_from_url( $url ) {
		$return = false;
		// Check if its a cloudinary URL.
		if ( $this->is_cloudinary_url( $url ) ) {
			$transformations = $this->get_transformations_from_string( $url );
			foreach ( $transformations as $transformation ) {
				if ( ! empty( $transformation['crop'] ) && ! empty( $transformation['width'] ) && ! empty( $transformation['height'] ) ) {
					$return = array(
						$transformation['width'],
						$transformation['height'],
					);
					break;
				}
			}
		} else {
			$file     = pathinfo( $url );
			$end_part = substr( strrchr( $file['filename'], '-' ), 1 );
			if ( false !== $end_part || false !== strpos( $end_part, 'x' ) ) {

				$size_parts = explode( 'x', $end_part );
				$size_int   = array_map( 'intval', $size_parts );
				$size       = array_filter( $size_int, 'is_int' );
				if ( ! empty( $size ) && 2 === count( $size ) ) {
					$return = $size;
				}
			}
		}

		return $return;
	}

	/**
	 * Get crop size of an image.
	 *
	 * @param string $url           The url to get crop for.
	 * @param int    $attachment_id image attachment id.
	 *
	 * @return array|bool The width and height of the crop, or false if size is custom.
	 */
	public function get_crop( $url, $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['sizes'] ) ) {
			// Try and match the file name from the sizes meta data to prevent false positives from filenames that have numbers separated by an x.
			$file             = wp_basename( $url ); // We only need the base name to check.
			$additional_sizes = wp_get_additional_image_sizes();
			foreach ( $meta['sizes'] as $size_name => $size ) {
				if ( $file === $size['file'] ) {
					$cropped = false;
					if ( isset( $additional_sizes[ $size_name ]['crop'] ) ) {
						$cropped = $additional_sizes[ $size_name ]['crop'];
					}
					// Make the WP Size array.
					$wp_size = array(
						'wpsize' => $size_name,
						'width'  => $size['width'],
						'height' => $size['height'],
						'crop'   => $cropped ? 'fill' : 'scale',
					);
					if ( $cropped ) {
						$wp_size['gravity'] = 'auto';
					}

					return $wp_size;
				}
			}
		}

		return false;
	}

	/**
	 * Set a transformation that is a single type only: quality, format.
	 *
	 * @param array    $transformations The transformation set to check.
	 * @param string   $type            The type of transformation to set.
	 * @param string   $value           The value of the transformation.
	 * @param int|bool $index           The index of the transformation array to set at.
	 */
	public function set_transformation( &$transformations, $type, $value, $index = false ) {
		if ( false === $index ) {
			$index = $this->get_transformation( $transformations, $type );
			if ( false === $index ) {
				$index = count( $transformations ); // Not found and no index set, append to transformation chain.
			}
		}
		$transformations[ $index ][ $type ] = $value;
	}

	/**
	 * Check if a transformation exists.
	 *
	 * @param array  $transformations The transformation set to check.
	 * @param string $type            The type of transformation to check for.
	 *
	 * @return bool
	 */
	public function get_transformation( $transformations, $type ) {
		foreach ( $transformations as $index => $transformation ) {
			if ( isset( $transformation[ $type ] ) ) {
				return $index;
			}
		}

		return false;
	}

	/**
	 * Extract the crop size part of a transformation that was done in the DAM widget.
	 *
	 * @param array      $transformations The transformations to get crop from.
	 * @param array|bool $crop            Optional crop size with width and height to balance transformations against.
	 *
	 * @return array|bool
	 */
	public function get_crop_from_transformation( $transformations, $crop = false ) {
		if ( empty( $transformations ) ) {
			return false;
		}
		$viable_parts = array_filter(
			$transformations,
			function ( $part ) {
				$keys   = array_keys( $part );
				$return = false; // phpcs:ignore
				foreach ( $keys as $key ) {
					if ( in_array( $key, array( 'overlay', 'underlay' ), true ) ) {
						return false; // end immediately since overlay and underlay has internal crops.
					}
					if ( in_array( $key, array( 'crop', 'width', 'height' ), true ) ) {
						$return = true;
					}
				}

				return $return;
			}
		);
		if ( ! empty( $viable_parts ) ) {
			// A final image size is determined by the last crop element.
			$size = array_pop( $viable_parts );
			if ( ! empty( $crop ) ) {
				$size = $this->balance_crop( $crop, $size );
			}

			return $size;
		}

		return false;
	}

	/**
	 * Convert a url param based transformation string into an array.
	 *
	 * @param string $str  The transformation string.
	 * @param string $type The type of transformation string.
	 *
	 * @return array The array of found transformations within the string.
	 */
	public function get_transformations_from_string( $str, $type = 'image' ) {

		$params = \Cloudinary\Connect\Api::$transformation_index[ $type ];

		$transformation_chains = explode( '/', $str );
		$transformations       = array();
		foreach ( $transformation_chains as $index => $chain ) {
			if ( false !== strpos( $chain, 'wpsize' ) ) {
				continue; // A wpsize is not a transformation.
			}
			$items = explode( ',', $chain );
			foreach ( $items as $item ) {
				$item = trim( $item );
				foreach ( $params as $param => $type ) {
					if ( substr( $item, 0, strlen( $param ) + 1 ) === $param . '_' ) {
						$transformations[ $index ][ $type ] = substr( $item, strlen( $param ) + 1 );
					}
				}
			}
		}

		return array_values( $transformations ); // Reset the keys.
	}

	/**
	 * Get a cloudinary URL for an attachment.
	 *
	 * @param string $url           The current url.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string Cloudinary URL.
	 */
	public function attachment_url( $url, $attachment_id ) {
		// Previous v1 and Cloudinary only storage.
		$previous_url = strpos( $url, untrailingslashit( $this->base_url ) );
		if ( false !== $previous_url ) {
			return substr( $url, $previous_url );
		}
		if ( ! doing_action( 'wp_insert_post_data' ) && false === $this->in_downsize ) {
			if ( $this->cloudinary_id( $attachment_id ) ) {
				$url = $this->cloudinary_url( $attachment_id );
			}
		}

		return $url;
	}

	/**
	 * Apply default image transformations before building the URL.
	 *
	 * @param array  $transformations The set of transformations.
	 * @param string $type            Default transformations type.
	 *
	 * @return array
	 */
	public function apply_default_transformations( array $transformations, $type = 'image' ) {

		// Base image level.
		$new_transformations = array(
			'image'  => \Cloudinary\Connect\Api::generate_transformation_string( $transformations, $type ),
			'global' => array(),
			'tax'    => array(),
			'qf'     => array(),
		);
		// Get Taxonomies.
		$new_transformations['tax'] = $this->global_transformations->get_taxonomy_transformations( $type );
		if ( ! $this->global_transformations->is_taxonomy_overwrite() ) {
			// Get Lowest level.
			$global  = $this->global_transformations->globals[ $type ];
			$default = array();
			if ( 'video' === $type ) {
				if ( isset( $global['video_limit_bitrate'] ) && 'on' === $global['video_limit_bitrate'] ) {
					$default['bit_rate'] = $global['video_bitrate'] . 'k';
				}
			} else {
				if ( 'auto' === $global[ $type . '_format' ] ) {
					$default['fetch_format'] = 'auto';
				}
				if ( isset( $global[ $type . '_quality' ] ) ) {
					$default['quality'] = $global[ $type . '_quality' ] !== 'none' ? $global[ $type . '_quality' ] : null;
				} else {
					$default['quality'] = 'auto';
				}
			}
			$default                   = array_filter( $default ); // Clear out empty settings.
			$new_transformations['qf'] = \Cloudinary\Connect\Api::generate_transformation_string( array( $default ), $type );
			// Add freeform global transformations.
			$freeform_type = $type . '_freeform';
			if ( ! empty( $global[ $freeform_type ] ) ) {
				$new_transformations['global'][] = trim( $global[ $freeform_type ] );
			}

			$new_transformations['global'] = implode( '/', $new_transformations['global'] );

		}
		// Clean out empty parts, and join into a sectioned string.
		$new_transformations = array_filter( $new_transformations );
		$new_transformations = implode( '/', $new_transformations );
		// Take sectioned string, and create a transformation array set.
		$transformations = $this->get_transformations_from_string( $new_transformations );
		/**
		 * Filter the default cloudinary transformations.
		 *
		 * @param array $defaults The default transformations array.
		 *
		 * @return array
		 */
		$defaults = apply_filters(
			'cloudinary_default_transformations',
			$transformations
		);

		return $defaults;
	}

	/**
	 * Generate a Cloudinary URL based on attachment ID and required size.
	 *
	 * @param int          $attachment_id             The id of the attachment.
	 * @param array|string $size                      The wp size to set for the URL.
	 * @param array        $transformations           Set of transformations to apply to this url.
	 * @param string       $cloudinary_id             Optional forced cloudinary ID.
	 * @param bool         $overwrite_transformations Flag url is a breakpoint URL to stop re-applying default transformations.
	 * @param bool         $clean                     Flag to present a clean url (With out a WP size variable.
	 *
	 * @return string The converted URL.
	 */
	public function cloudinary_url( $attachment_id, $size = array(), $transformations = array(), $cloudinary_id = null, $overwrite_transformations = false, $clean = false ) {

		if ( ! ( $cloudinary_id ) ) {
			$cloudinary_id = $this->cloudinary_id( $attachment_id );
			if ( ! $cloudinary_id ) {
				return null;
			}
		}
		if ( empty( $transformations ) ) {
			$transformations = $this->get_transformation_from_meta( $attachment_id );
		}
		// Get the attachment resource type.
		$resource_type = $this->get_media_type( $attachment_id );
		// Setup initial args for cloudinary_url.
		$pre_args = array(
			'secure'        => is_ssl(),
			'version'       => $this->get_cloudinary_version( $attachment_id ),
			'resource_type' => $resource_type,
		);

		// Check size and correct if string or size.
		if ( is_string( $size ) || ( is_array( $size ) && 3 === count( $size ) ) ) {
			$intermediate = image_get_intermediate_size( $attachment_id, $size );
			if ( is_array( $intermediate ) ) {
				$size = $this->get_crop( $intermediate['url'], $attachment_id );
			}
		}
		if ( false === $overwrite_transformations ) {
			$overwrite_transformations = $this->maybe_overwrite_featured_image( $attachment_id );
		}
		/**
		 * Filter the Cloudinary transformations.
		 *
		 * @param array $transformations Array of transformation options.
		 * @param int   $attachment_id   The id of the asset.
		 *
		 * @return array
		 */
		$pre_args['transformation'] = apply_filters( 'cloudinary_transformations', $transformations, $attachment_id );
		// Defaults are only to be added on front, main images ( not breakpoints, since these are adapted down), and videos.
		if ( ( ! defined( 'REST_REQUEST' ) || false === REST_REQUEST ) && ! is_admin() && false === $overwrite_transformations ) {
			$pre_args['transformation'] = $this->apply_default_transformations( $pre_args['transformation'], $resource_type );
		}

		// Make a copy as not to destroy the options in \Cloudinary::cloudinary_url().
		$args = $pre_args;
		$url  = $this->plugin->components['connect']->api->cloudinary_url( $cloudinary_id, $args, $size, $clean );

		// Check if this type is a preview only type. i.e PDF.
		if ( ! empty( $size ) && $this->is_preview_only( $attachment_id ) ) {
			$url = $this->convert_media_extension( $url );
		}

		/**
		 * Filter the final Cloudinary URL.
		 *
		 * @param string $url           The Cloudinary URL.
		 * @param int    $attachment_id The id of the attachment.
		 * @param array  $pre_args      The arguments used to create the url.
		 *
		 * @return string
		 */
		return apply_filters( 'cloudinary_converted_url', $url, $attachment_id, $pre_args );
	}

	/**
	 * Add domain to subdir.
	 *
	 * @param array $dirs The internal directory structures.
	 *
	 * @return array Altered array of paths.
	 */
	public function upload_dir( $dirs ) {

		$dirs['cloudinary_folder'] = $this->get_cloudinary_folder();

		return $dirs;
	}

	/**
	 * Get the setup Cloudinary Folder.
	 *
	 * @return string
	 */
	public function get_cloudinary_folder() {
		$folder = '';
		if ( ! empty( $this->cloudinary_folder ) ) {
			$folder = trailingslashit( $this->cloudinary_folder );
		}

		return $folder;
	}

	/**
	 * Get a public ID.
	 *
	 * @param int  $attachment_id The Attachment ID.
	 * @param bool $suffixed      Flag to get suffixed version of ID.
	 *
	 * @return string
	 */
	public function get_public_id( $attachment_id, $suffixed = false ) {
		// Check for a public_id.
		if ( $this->has_public_id( $attachment_id ) ) {
			$public_id = $this->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );
			if ( $this->is_folder_synced( $attachment_id ) ) {
				$public_id = $this->get_cloudinary_folder() . pathinfo( $public_id, PATHINFO_BASENAME );
			}
			if ( true === $suffixed ) {
				$public_id .= $this->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );
			}
		} else {
			$public_id = $this->sync->generate_public_id( $attachment_id );
		}

		return $public_id;
	}

	/**
	 * Check if an attachment has a public ID.
	 *
	 * @param int $attachment_id The Attachment ID.
	 *
	 * @return bool
	 */
	public function has_public_id( $attachment_id ) {
		return ! empty( $this->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true ) );
	}

	/**
	 * Get a Cloudinary ID which includes the file format extension.
	 *
	 * @param int $attachment_id The Attachment ID.
	 *
	 * @return string|null
	 */
	public function get_cloudinary_id( $attachment_id ) {

		$cloudinary_id = null;
		// A cloudinary_id is a public_id with a file extension.
		if ( $this->has_public_id( $attachment_id ) ) {
			$public_id = $this->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );
			// Since this is based on saved and not a dynamic, append a suffix.
			$public_id .= $this->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );
			// Get the file, and use the same extension.
			$file = get_attached_file( $attachment_id );
			// @todo: Make this use the globals, overrides, and application conversion.
			$extension = pathinfo( $file, PATHINFO_EXTENSION );
			if ( wp_attachment_is_image( $attachment_id ) ) {
				$settings = $this->global_transformations->globals['image'];
				if ( ! in_array( $settings['image_format'], array( 'none', 'auto' ), true ) ) {
					$extension = $settings['image_format'];
				}
			}
			$cloudinary_id = $public_id . '.' . $extension;
		}

		return $cloudinary_id;
	}

	/**
	 * Get a Cloudinary ID
	 *
	 * @param int $attachment_id The ID to get Cloudinary id for.
	 *
	 * @return string|null the ID or null if not existing.
	 */
	public function cloudinary_id( $attachment_id ) {

		if ( ! $this->is_media( $attachment_id ) ) {
			return null;
		}
		// Return cached ID if we've already gotten it before.
		if ( isset( $this->cloudinary_ids[ $attachment_id ] ) ) {
			return $this->cloudinary_ids[ $attachment_id ];
		}
		if ( ! $this->sync->is_synced( $attachment_id ) && ! defined( 'REST_REQUEST' ) ) {
			$sync_type = $this->sync->maybe_prepare_sync( $attachment_id );
			// Check sync type allows for continued rendering. i.e meta update, breakpoints etc, will still allow the URL to work,
			// Where is type "file" will not since it's still being uploaded.
			if ( ! is_null( $sync_type ) && $this->sync->is_required( $sync_type, $attachment_id ) ) {
				return null; // Return and render local URLs.
			}
		}

		$cloudinary_id = $this->get_cloudinary_id( $attachment_id );

		/**
		 * Filter to  validate the Cloudinary ID to allow extending it's availability.
		 *
		 * @param string|bool $cloudinary_id The public ID from Cloudinary, or false if not found.
		 * @param int         $attachment_id The id of the asset.
		 *
		 * @return string|bool
		 */
		$cloudinary_id = apply_filters( 'validate_cloudinary_id', $cloudinary_id, $attachment_id );

		/**
		 * Action the Cloudinary ID to allow extending it's availability.
		 *
		 * @param string|bool $cloudinary_id The public ID from Cloudinary, or false if not found.
		 * @param int         $attachment_id The id of the asset.
		 */
		do_action( 'cloudinary_id', $cloudinary_id, $attachment_id );
		// Cache ID to prevent multiple lookups.
		$this->cloudinary_ids[ $attachment_id ] = $cloudinary_id;

		return $cloudinary_id;
	}

	/**
	 * Filter the requested image and return image source.
	 *
	 * @param null         $image         The null image value for short circuit check.
	 * @param int          $attachment_id The ID of the attachment.
	 * @param string|array $size          The requested size of the image.
	 *
	 * @return array The image array of size and url.
	 * @uses filter:image_downsize
	 *
	 */
	public function filter_downsize( $image, $attachment_id, $size ) {
		// Don't do this while saving.
		if ( true === $this->in_downsize || doing_action( 'wp_insert_post_data' ) || wp_attachment_is( 'video', $attachment_id ) ) {
			return $image;
		}

		$cloudinary_id = $this->cloudinary_id( $attachment_id );

		if ( $cloudinary_id ) {
			$this->in_downsize = true;
			$intermediate      = image_get_intermediate_size( $attachment_id, $size );
			if ( is_array( $intermediate ) ) {
				// Found an intermediate size.
				$image = array(
					$this->convert_url( $intermediate['file'], $attachment_id, array(), false ),
					$intermediate['width'],
					$intermediate['height'],
					true,
				);
			}
			$this->in_downsize = false;

			// Preview formats.
			if ( empty( $image ) && $this->is_preview_only( $attachment_id ) ) {
				$image = array(
					$this->cloudinary_url( $attachment_id, $size, array(), $cloudinary_id ),
					$size[0],
					$size[1],
					false,
				);
			}
		}

		return $image;
	}

	/**
	 * Convert an attachment URL to a Cloudinary one.
	 *
	 * @param string $url                       Url to convert.
	 * @param int    $attachment_id             Attachment ID.
	 * @param array  $transformations           Optional transformations.
	 * @param bool   $overwrite_transformations Flag url as having an overwrite transformation.
	 *
	 * @return string Converted URL.
	 */
	public function convert_url( $url, $attachment_id, $transformations = array(), $overwrite_transformations = true ) {

		if ( $this->is_cloudinary_url( $url ) ) {
			return $url; // Already is a cloudinary URL, just return.
		}
		$size = $this->get_crop( $url, $attachment_id );

		return $this->cloudinary_url( $attachment_id, $size, $transformations, null, $overwrite_transformations, true );
	}

	/**
	 * Get the responsive breakpoints for the image.
	 *
	 * @param array  $sources       The original sources array.
	 * @param array  $size_array    The size array.
	 * @param string $image_src     The original image source.
	 * @param array  $image_meta    The image meta array.
	 * @param int    $attachment_id The attachment id.
	 *
	 * @return array Altered or same sources array.
	 */
	public function image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$cloudinary_id = $this->cloudinary_id( $attachment_id );
		if ( ! $cloudinary_id ) {
			return $sources; // Return WordPress default sources.
		}
		// Get transformations from URL.
		$transformations = $this->get_transformations_from_string( $image_src );
		// Use Cloudinary breakpoints for same ratio.

		if ( 'on' === $this->plugin->config['settings']['global_transformations']['enable_breakpoints'] && wp_image_matches_ratio( $image_meta['width'], $image_meta['height'], $size_array[0], $size_array[1] ) ) {
			$meta = $this->get_post_meta( $attachment_id, Sync::META_KEYS['breakpoints'], true );
			if ( ! empty( $meta ) ) {
				// Since srcset is primary and src is a fallback, we need to set the first srcset with the main image.
				$sources = array(
					$size_array[0] => array(
						'url'        => $image_src,
						'descriptor' => 'w',
						'value'      => $size_array[0],
					),
				);
				// Check if the image has a crop.
				$crop = $this->get_crop_from_transformation( $transformations );
				if ( ! empty( $crop ) ) {
					// Remove the crop from the transformation.
					$transformations = array_filter(
						$transformations,
						function ( $item ) use ( $crop ) {
							return $item !== $crop;
						}
					);
				}
				foreach ( $meta as $breakpoint ) {

					$size                            = array(
						'crop'  => 'scale',
						'width' => $breakpoint['width'],
					);
					$sources[ $breakpoint['width'] ] = array(
						'url'        => $this->cloudinary_url( $attachment_id, $size, $transformations, $cloudinary_id, true ),
						'descriptor' => 'w',
						'value'      => $breakpoint['width'],
					);
				}
				krsort( $sources, SORT_NUMERIC );

				return $sources;
			}
		}

		// Add the main size as the largest srcset src.
		$crop = $this->get_crop_from_transformation( $transformations );
		if ( ! empty( $crop ) ) {
			// A valid crop could be just a crop mode and an edge size. Either width or height, or both.
			$size             = ! empty( $crop['width'] ) ? $crop['width'] : $crop['height'];
			$type             = ! empty( $crop['width'] ) ? 'w' : 'h';
			$sources[ $size ] = array(
				'url'        => $image_src,
				'descriptor' => $type,
				'value'      => $size,
			);
		}
		// Use current sources, but convert the URLS.
		foreach ( $sources as &$source ) {
			if ( ! $this->is_cloudinary_url( $source['url'] ) ) {
				$source['url'] = $this->convert_url( $source['url'], $attachment_id, $transformations, true ); // Overwrite transformations applied, since the $transformations includes globals from the primary URL.
			}
		}

		return $sources;
	}

	/**
	 * Alter the image sizes metadata to match the Cloudinary ID so that WordPress can detect a matched source for responsive breakpoints.
	 *
	 * @param array  $image_meta    The image metadata array.
	 * @param array  $size_array    The size array.
	 * @param string $image_src     The image src.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function match_responsive_sources( $image_meta, $size_array, $image_src, $attachment_id ) {
		if ( wp_attachment_is_image( $attachment_id ) && ! empty( $image_meta['sizes'] ) ) {
			$cloudinary_id = $this->cloudinary_id( $attachment_id );
			if ( $cloudinary_id ) {
				// Set the file to the Cloudinary ID so that it will be matched.
				$image_meta['file'] = $cloudinary_id;
			}
		}

		return $image_meta;
	}

	/**
	 * Check if a url is a cloudinary url or not.
	 *
	 * @param string $url The url in question.
	 *
	 * @return bool
	 */
	public function is_cloudinary_url( $url ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$test_parts = wp_parse_url( $url );
		$cld_url    = $this->plugin->components['connect']->api->asset_url;

		return $test_parts['host'] === $cld_url;
	}

	/**
	 * Add media tab template.
	 */
	public function media_template() {
		?>
		<script type="text/html" id="tmpl-cloudinary-dam">
			<div id="cloudinary-dam-{{ data.controller.cid }}" class="cloudinary-widget-wrapper"></div>
		</script>
		<?php
	}

	/**
	 * Setup and include cloudinary assets for DAM widget.
	 */
	public function editor_assets() {

		// External assets.
		wp_enqueue_script( 'cloudinary-media-library', 'https://media-library.cloudinary.com/global/all.js', array(), $this->plugin->version, true );
		$params = array(
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'mloptions' => array(
				'cloud_name'    => $this->credentials['cloud_name'],
				'api_key'       => $this->credentials['api_key'],
				'cms_type'      => 'wordpress',
				'remove_header' => true,
				'integration'   => array(
					'type'     => 'wordpress_plugin',
					'platform' => 'WordPress ' . get_bloginfo( 'version' ),
					'version'  => $this->plugin->version,
				),
			),
		);

		// Set folder if needed.
		if ( ! empty( $this->cloudinary_folder ) ) {
			$params['mloptions']['folder'] = array( 'path' => $this->cloudinary_folder );
		}

		$params['mloptions']['insert_transformation'] = true;
		$params['mloptions']['inline_container']      = '#cloudinary-dam';

		wp_add_inline_script( 'cloudinary-media-library', 'var CLDN = ' . wp_json_encode( $params ), 'before' );
	}

	/**
	 * Create a new attachment post item.
	 *
	 * @param array  $asset     The asset arrah data.
	 * @param string $public_id The cloudinary public id.
	 *
	 * @return int|\WP_Error
	 */
	private function create_attachment( $asset, $public_id ) {

		// Create an attachment post.
		$file_path        = $asset['url'];
		$file_name        = basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
		$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
		$post_args        = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		// Disable Upload_Sync to avoid sync loop.
		add_filter( 'cloudinary_upload_sync_enabled', '__return_false' );
		// Create the attachment.
		$attachment_id = wp_insert_attachment( $post_args, false );

		$sync_key = $public_id;
		// Capture public_id. Use core update_post_meta since this attachment data doesnt exist yet.
		update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
		// Capture version number.
		update_post_meta( $attachment_id, Sync::META_KEYS['version'], $asset['version'] );
		if ( ! empty( $asset['transformations'] ) ) {
			// Save a combined key.
			$sync_key .= wp_json_encode( $asset['transformations'] );
			update_post_meta( $attachment_id, Sync::META_KEYS['transformation'], $asset['transformations'] );
		}
		// create a trackable key in post meta.
		update_post_meta( $attachment_id, '_' . md5( $sync_key ), true );
		// record a base to ensure primary isn't deleted.
		update_post_meta( $attachment_id, '_' . md5( 'base_' . $public_id ), true );

		return $attachment_id;
	}

	/**
	 * Balance a resize crop that's missing a height or width.
	 *
	 * @param array $size       The current size.
	 * @param array $shift_size The size to balance to.
	 *
	 * @return array
	 */
	public function balance_crop( $size, $shift_size ) {

		// Check if both width and height are present, and add missing dimension.
		if ( empty( $shift_size['height'] ) ) {
			$ratio_size           = wp_constrain_dimensions( $size['width'], $size['height'], $shift_size['width'] );
			$shift_size['height'] = $ratio_size[1];// Set the height.
		} elseif ( empty( $shift_size['width'] ) ) {
			// wp_constrain_dimensions only deals with width, so we pretend the image is a portrait to compensate.
			$ratio_size          = wp_constrain_dimensions( $size['height'], $size['width'], $shift_size['height'] );
			$shift_size['width'] = $ratio_size[0];// Set the width.
		}

		return $shift_size;
	}

	/**
	 * Get the asset payload and process it for importing.
	 *
	 * @return array
	 */
	public function get_asset_payload() {
		$args  = array(
			'asset' => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);
		$data  = filter_input_array( INPUT_POST, $args );
		$asset = array(
			'version'         => (int) filter_var( $data['asset']['version'], FILTER_SANITIZE_NUMBER_INT ),
			'public_id'       => filter_var( $data['asset']['public_id'], FILTER_SANITIZE_STRING ),
			'src'             => filter_var( $data['asset']['secure_url'], FILTER_SANITIZE_URL ),
			'url'             => filter_var( $data['asset']['secure_url'], FILTER_SANITIZE_URL ),
			'transformations' => array(),
			'meta'            => array(),
		);
		// Set sync key.
		$asset['sync_key'] = $asset['public_id'];
		if ( ! empty( $data['asset']['derived'] ) ) {
			$asset['url'] = filter_var( $data['asset']['derived'][0]['secure_url'], FILTER_SANITIZE_URL );
		}

		//convert_media_extension
		if ( ! $this->is_file_compatible( $asset['url'] ) ) {
			$asset['url'] = $this->convert_media_extension( $asset['url'] );
		}

		// Move all context data into the meta key.
		if ( ! empty( $data['asset']['context'] ) ) {
			array_walk_recursive( $data['asset']['context'], function ( $value, $key ) use ( &$asset ) {
				$asset['meta'][ $key ] = filter_var( $value, FILTER_SANITIZE_STRING );
			} );
		}

		// Check for transformations.
		$transformations = $this->get_transformations_from_string( $asset['url'] );
		if ( ! empty( $transformations ) ) {
			$asset['sync_key']        .= wp_json_encode( $transformations );
			$asset['transformations'] = $transformations;
		}
		// Attempt to find attachment ID.
		$asset['attachment_id'] = $this->get_id_from_sync_key( $asset['sync_key'] );

		return $asset;
	}

	/**
	 * Create and prepare a down sync asset from Cloudinary.
	 */
	public function down_sync_asset() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( wp_verify_nonce( $nonce, 'wp_rest' ) ) {


			$asset = $this->get_asset_payload();
			// Set a base array for pulling an asset if needed.
			$base_return = array(
				'fetch'         => rest_url( REST_API::BASE . '/asset' ),
				'uploading'     => true,
				'src'           => $asset['src'],
				'url'           => $asset['url'],
				'filename'      => basename( $asset['src'] ),
				'attachment_id' => $asset['attachment_id'],
			);
			if ( empty( $asset['attachment_id'] ) ) {
				$return                  = $base_return;
				$asset['attachment_id']  = $this->create_attachment( $asset, $asset['public_id'] );
				$return['attachment_id'] = $asset['attachment_id'];
			} else {
				// Capture the ALT Text.
				if ( ! empty( $asset['meta']['alt'] ) ) {
					$alt_text = wp_strip_all_tags( $asset['meta']['alt'] );
					update_post_meta( $asset['attachment_id'], '_wp_attachment_image_alt', $alt_text );
				}
				// Compare Version.
				$current_version = $this->get_cloudinary_version( $asset['attachment_id'] );
				if ( $current_version !== $asset['version'] ) {
					// Difference version, remove files, and downsync new files related to this asset.
					// If this is a different version, we should try find attachments with the base sync key and update the source.
					$ids    = $this->get_id_from_sync_key( 'base_' . $asset['public_id'], true );
					$resync = array();
					foreach ( $ids as $id ) {
						// Update the version to the asset.
						$this->update_post_meta( $id, Sync::META_KEYS['version'], $asset['version'] );
						// Get the storage state, and only set storage signature if we have local copies.
						$storage_state = $this->get_post_meta( $id, Sync::META_KEYS['storage'], true );
						if ( 'cld' !== $storage_state ) {
							// State is local and Cloudinary. So lets force the storage to downsync again.
							$this->update_post_meta( $id, Sync::META_KEYS['storage'], 'resync' );
							// Set signature for storage, since this will be more effective at downloading or not.
							$this->sync->set_signature_item( $id, 'storage', '' );
						}
						if ( $id !== $asset['attachment_id'] ) {
							$resync[] = wp_prepare_attachment_for_js( $id );
						}
					}
					// Use the primary ID as the main return, and add the resynced assets to that.
					$return           = wp_prepare_attachment_for_js( $asset['attachment_id'] );
					$return['resync'] = $resync;
				} else {
					$return              = wp_prepare_attachment_for_js( $asset['attachment_id'] );
					$return['public_id'] = $asset['public_id'];
				}
			}
			$return['transformations'] = $asset['transformations'];

			wp_send_json_success( $return );
		}

		return wp_send_json_error();
	}

	/**
	 * Insert the cloudinary status column.
	 *
	 * @param array $cols Array of columns.
	 *
	 * @return array
	 */
	public function media_column( $cols ) {

		$custom = array(
			'cld_status' => '<span class="dashicons-cloudinary"><span class="screen-reader-text">' . __( 'Cloudinary', 'cloudinary' ) . '</span></span>',
		);
		$offset = array_search( 'parent', array_keys( $cols ), true );
		if ( empty( $offset ) ) {
			$offset = 3; // Default location some where after author, in case another plugin removes parent column.
		}
		$cols = array_slice( $cols, 0, $offset ) + $custom + array_slice( $cols, $offset );

		return $cols;
	}

	/**
	 * Display the Cloudinary Column.
	 *
	 * @param string $column_name   The column name.
	 * @param int    $attachment_id The attachment id.
	 */
	public function media_column_value( $column_name, $attachment_id ) {
		if ( 'cld_status' === $column_name ) {
			if ( $this->is_media( $attachment_id ) ) {
				$status = array(
					'state' => 'inactive',
					'note'  => esc_html__( 'Not Synced', 'cloudinary' ),
				);
				add_filter( 'cloudinary_flag_sync', '__return_true' );
				if ( ! $this->cloudinary_id( $attachment_id ) ) {
					// If false, lets check why by seeing if the file size is too large.
					$file     = get_attached_file( $attachment_id ); // Get the file size to make sure it can exist in cloudinary.
					$max_size = ( wp_attachment_is_image( $attachment_id ) ? 'image_max_size_bytes' : 'video_max_size_bytes' );
					if ( file_exists( $file ) && filesize( $file ) > $this->plugin->components['connect']->usage['media_limits'][ $max_size ] ) {
						$max_size_hr = size_format( $this->plugin->components['connect']->usage['media_limits'][ $max_size ] );
						// translators: variable is file size.
						$status['note']  = sprintf( __( 'File size exceeds the maximum of %s. This media asset will be served from WordPress.', 'cloudinary' ), $max_size_hr );
						$status['state'] = 'error';
					}
				} else {
					$status = array(
						'state' => 'success',
						'note'  => esc_html__( 'Synced', 'cloudinary' ),
					);
				}
				remove_filter( 'cloudinary_flag_sync', '__return_true' );
				// filter status.
				$status = apply_filters( 'cloudinary_media_status', $status, $attachment_id );
				?>
				<span class="dashicons-cloudinary <?php echo esc_attr( $status['state'] ); ?>" title="<?php echo esc_attr( $status['note'] ); ?>"></span>
				<?php
			}
		}
	}

	/**
	 * Sanitize the Cloudinary Folder, and if empty, return the sanitized default.
	 *
	 * @param string $value The value to sanitize.
	 * @param array  $field The field settings array.
	 *
	 * @return string
	 */
	public static function sanitize_cloudinary_folder( $value, $field ) {
		$value = trim( $value );
		if ( empty( $value ) && ! empty( $field['default'] ) ) {
			$value = $field['default'];
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize the breakpoints, and if empty, return the sanitized default.
	 *
	 * @param string $value The value to sanitize.
	 * @param array  $field The field settings array.
	 *
	 * @return int
	 */
	public static function sanitize_breakpoints( $value, $field ) {
		if ( ! is_numeric( $value ) ) {
			$value = $field['default'];
		} elseif ( $value > $field['max'] ) {
			$value = $field['max'];
		} elseif ( $value < $field['min'] ) {
			$value = $field['min'];
		}

		return intval( $value );
	}

	/**
	 * Get the max image width registered in WordPress.
	 *
	 * @return int
	 */
	public function get_max_width() {
		if ( empty( $this->max_width ) ) {
			if ( ! empty( $this->plugin->config['settings']['global_transformations']['max_width'] ) ) {
				$this->max_width = $this->plugin->config['settings']['global_transformations']['max_width'];
			} else {
				$core_sizes       = array( 'thumbnail', 'medium', 'large', 'medium_large', 'large' );
				$additional_sizes = wp_get_additional_image_sizes();
				foreach ( $core_sizes as $size ) {
					$additional_sizes[ $size ] = get_option( $size . '_size_w' );
				}
				$sizes = array_map(
					function ( $item ) {
						if ( is_array( $item ) ) {
							$item = $item['width'];
						}

						return intval( $item );
					},
					$additional_sizes
				);
				rsort( $sizes );
				$this->max_width = array_shift( $sizes );
			}
		}

		return $this->max_width;
	}

	/**
	 * Get transformations from post meta for an attachment.
	 *
	 * @param int $post_id The post to get meta for.
	 *
	 * @return array
	 */
	public function get_transformation_from_meta( $post_id ) {
		$transformations = $this->get_post_meta( $post_id, Sync::META_KEYS['transformation'], true );
		if ( empty( $transformations ) ) {
			$transformations = array();
		}

		return $transformations;
	}

	/**
	 * Get Cloudinary related Post meta.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 * @param bool   $single  If single or not.
	 *
	 * @return mixed
	 */
	public function get_post_meta( $post_id, $key, $single = false ) {

		$meta_data = wp_get_attachment_metadata( $post_id, true );
		if ( ! is_array( $meta_data ) ) {
			return get_post_meta( $post_id, $key, $single );
		}
		if ( ! isset( $meta_data[ Sync::META_KEYS['cloudinary'] ] ) ) {
			$meta_data[ Sync::META_KEYS['cloudinary'] ] = array();
		}
		if ( ! empty( $meta_data[ Sync::META_KEYS['cloudinary'] ][ $key ] ) ) {
			$data = $meta_data[ Sync::META_KEYS['cloudinary'] ][ $key ];
		} else {
			$data = $this->build_cached_meta( $post_id, $key, $single );
		}

		return $data;
	}

	/**
	 * Build and return a cached cloudinary meta value.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 * @param bool   $single  If single or not.
	 *
	 * @return mixed
	 */
	public function build_cached_meta( $post_id, $key, $single ) {
		$data = get_post_meta( $post_id, $key, $single );
		if ( '' !== $data ) {
			$this->update_post_meta( $post_id, $key, $data );
		}

		return $data;
	}

	/**
	 * Update cloudinary metadata.
	 *
	 * @param int          $post_id The attachment ID.
	 * @param string       $key     The meta key to get.
	 * @param string|array $data    $the meta data to update.
	 */
	public function update_post_meta( $post_id, $key, $data ) {
		$meta_data = wp_get_attachment_metadata( $post_id, true );
		if ( is_array( $meta_data ) ) {
			if ( ! isset( $meta_data[ Sync::META_KEYS['cloudinary'] ] ) ) {
				$meta_data[ Sync::META_KEYS['cloudinary'] ] = array();
			}
			$meta_data[ Sync::META_KEYS['cloudinary'] ][ $key ] = $data;
			wp_update_attachment_metadata( $post_id, $meta_data );
		} else {
			// Update core mete data for consistency.
			update_post_meta( $post_id, $key, $data );
		}
	}

	/**
	 * Delete cloudinary metadata.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 */
	public function delete_post_meta( $post_id, $key ) {
		$meta_data = wp_get_attachment_metadata( $post_id, true );
		if ( is_array( $meta_data ) && isset( $meta_data[ Sync::META_KEYS['cloudinary'] ] ) && is_array( $meta_data[ Sync::META_KEYS['cloudinary'] ] ) ) {
			// Only do this side if has been set before.
			unset( $meta_data[ Sync::META_KEYS['cloudinary'] ][ $key ] );
			wp_update_attachment_metadata( $post_id, $meta_data );
		}
		// Delete meta data.
		delete_post_meta( $post_id, $key );
	}

	/**
	 * Get the breakpoint generation options for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function get_breakpoint_options( $attachment_id ) {
		// Add breakpoints if we have an image.
		$breakpoints = array();
		$settings    = $this->plugin->config['settings']['global_transformations'];

		if ( 'off' !== $settings['enable_breakpoints'] && wp_attachment_is_image( $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			// Get meta image size if non exists.
			if ( empty( $meta ) ) {
				$meta          = array();
				$imagesize     = getimagesize( get_attached_file( $attachment_id ) );
				$meta['width'] = $imagesize[0] ? $imagesize[0] : 0;
			}
			$max_width = $this->get_max_width();
			// Add breakpoints request options.
			$breakpoint_options = array(
				'create_derived' => true,
				'bytes_step'     => $settings['bytes_step'],
				'max_images'     => $settings['breakpoints'],
				'max_width'      => $meta['width'] < $max_width ? $meta['width'] : $max_width,
				'min_width'      => $settings['min_width'],
			);
			$transformations    = $this->get_transformation_from_meta( $attachment_id );
			if ( ! empty( $transformations ) ) {
				$breakpoints['transformation'] = Api::generate_transformation_string( $transformations );
			}
			$breakpoints = array(
				'public_id'              => $this->get_public_id( $attachment_id ),
				'type'                   => 'upload',
				'responsive_breakpoints' => $breakpoint_options,
				'context'                => $this->get_context_options( $attachment_id ),
			);

		}

		return $breakpoints;
	}

	/**
	 * Get the context options for an asset.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return array
	 */
	public function get_context_options( $attachment_id ) {

		$context_options = array(
			'caption' => esc_attr( get_the_title( $attachment_id ) ),
			'alt'     => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'guid'    => md5( get_the_guid( $attachment_id ) ),
		);

		// Check if this asset is a folder sync.
		$folder_sync = $this->get_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true );
		if ( ! empty( $folder_sync ) ) {
			$context_options['wp_id'] = $attachment_id;
		}

		/**
		 * Filter the options to allow other plugins to add requested options for uploading.
		 *
		 * @param array    $options The options array.
		 * @param \WP_Post $post    The attachment post.
		 * @param \Cloudinary\Sync The sync object instance.
		 *
		 * @return array
		 */
		$context_options = apply_filters( 'cloudinary_context_options', $context_options, get_post( $attachment_id ), $this );

		return http_build_query( $context_options, null, '|' );
	}

	/**
	 * Check if an asset is folder synced.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_folder_synced( $attachment_id ) {

		$return = true; // By default all assets in WordPress will be synced.
		if ( $this->sync->been_synced( $attachment_id ) ) {
			$return = ! empty( $this->get_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true ) );
		}

		return $return;
	}

	/**
	 * Get the media upload options as connected to Cloudinary.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function get_upload_options( $attachment_id ) {

		// Prepare upload options.
		$public_id = $this->get_public_id( $attachment_id );
		$folder    = ltrim( dirname( $public_id ), '.' );
		$options   = array(
			'unique_filename' => true,
			'overwrite'       => false,
			'resource_type'   => $this->get_media_type( $attachment_id ),
			'public_id'       => basename( $public_id ),
			'context'         => $this->get_context_options( $attachment_id ),
		);

		/**
		 * Filter the options to allow other plugins to add requested options for uploading.
		 *
		 * @param array    $options The options array.
		 * @param \WP_Post $post    The attachment post.
		 * @param \Cloudinary\Sync The sync object instance.
		 *
		 * @return array
		 */
		$options = apply_filters( 'cloudinary_upload_options', $options, get_post( $attachment_id ), $this );
		// Add folder to prevent folder contamination.
		if ( $this->is_folder_synced( $attachment_id ) ) {
			$options['public_id'] = $this->get_cloudinary_folder() . basename( $options['public_id'] );
		} elseif ( ! empty( $folder ) ) {
			// add in folder if not empty (not in root).
			$options['public_id'] = trailingslashit( $folder ) . basename( $options['public_id'] );
		}

		return $options;
	}

	/**
	 * Check if the current image is to be have the transformations overwritten.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function maybe_overwrite_featured_image( $attachment_id ) {
		$overwrite = false;
		if ( $this->doing_featured_image && $this->doing_featured_image === $attachment_id ) {
			$overwrite = (bool) $this->get_post_meta( get_the_ID(), Global_Transformations::META_FEATURED_IMAGE_KEY, true );
		}

		return $overwrite;
	}

	/**
	 * Set the flag indicating if the featured image is being done.
	 *
	 * @param int $post_id       The current post ID.
	 * @param int $attachment_id The thumbnail ID.
	 */
	public function set_doing_featured( $post_id, $attachment_id ) {
		$this->doing_featured_image = $attachment_id;
		add_action( 'end_fetch_post_thumbnail_html', function () {
			$this->doing_featured_image = false;
		} );
	}

	/**
	 * Get the cloudinary version of an attachment.
	 *
	 * @param int $attachment_id The attachment_ID.
	 *
	 * @return int
	 */
	public function get_cloudinary_version( $attachment_id ) {
		$version = (int) $this->get_post_meta( $attachment_id, Sync::META_KEYS['version'], true );

		return $version ? $version : 1;
	}

	/**
	 * Setup the hooks and base_url if configured.
	 */
	public function setup() {
		if ( $this->plugin->config['connect'] ) {

			$this->base_url          = $this->plugin->components['connect']->api->cloudinary_url();
			$this->credentials       = $this->plugin->components['connect']->get_credentials();
			$this->cloudinary_folder = $this->plugin->config['settings']['sync_media']['cloudinary_folder'] ? $this->plugin->config['settings']['sync_media']['cloudinary_folder'] : '';
			$this->sync              = $this->plugin->components['sync'];

			// Internal components.
			$this->filter                 = new Filter( $this );
			$this->upgrade                = new Upgrade( $this );
			$this->global_transformations = new Global_Transformations( $this );
			$this->video                  = new Video( $this );

			// Set the max image size registered in WordPress.
			$this->get_max_width();

			// Add media templates and assets.
			add_action( 'print_media_templates', array( $this, 'media_template' ) );
			add_action( 'wp_enqueue_media', array( $this, 'editor_assets' ) );
			add_action( 'wp_ajax_cloudinary-down-sync', array( $this, 'down_sync_asset' ) );

			// Filter to add cloudinary folder.
			add_filter( 'upload_dir', array( $this, 'upload_dir' ) );

			// Filter live URLS. (functions that return a URL).
			add_filter( 'wp_calculate_image_srcset', array( $this, 'image_srcset' ), 10, 5 );
			add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'match_responsive_sources' ), 10, 4 );
			add_filter( 'wp_get_attachment_metadata', array( $this, 'match_file_name_with_cloudinary_source' ), 10, 2 );
			add_filter( 'wp_get_attachment_url', array( $this, 'attachment_url' ), 10, 2 );
			add_filter( 'image_downsize', array( $this, 'filter_downsize' ), 10, 3 );

			// Filter and action the custom column.
			add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
			add_action( 'manage_media_custom_column', array( $this, 'media_column_value' ), 10, 2 );

			// Hook into Featured Image cycle.
			add_action( 'begin_fetch_post_thumbnail_html', array( $this, 'set_doing_featured' ), 10, 2 );
		}
	}

	/**
	 * Ensure the file in image meta is the same as the Cloudinary ID.
	 *
	 * @param array $image_meta    Meta information of the attachment.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function match_file_name_with_cloudinary_source( $image_meta, $attachment_id ) {
		if ( $this->has_public_id( $attachment_id ) ) {
			$cld_file = 'v' . $this->get_cloudinary_version( $attachment_id ) . '/' . $this->get_cloudinary_id( $attachment_id );
			if ( false === strpos( $image_meta['file'], $cld_file ) ) {
				$image_meta['file'] = $cld_file;
			}
		}

		return $image_meta;
	}
}
