<?php
/**
 * Media class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Assets;
use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Media\Filter;
use Cloudinary\Media\Global_Transformations;
use Cloudinary\Media\Upgrade;
use Cloudinary\Media\Video;
use Cloudinary\Media\WooCommerceGallery;
use Cloudinary\Relate\Relationship;
use WP_Error;
use WP_Query;

/**
 * Class Media
 */
class Media extends Settings_Component implements Setup {

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
	public $base_url;

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
	 * Gallery instance.
	 *
	 * @var \Cloudinary\Media\Gallery.
	 */
	public $gallery;

	/**
	 * WooCommerceGallery instance.
	 *
	 * @var \Cloudinary\Media\WooCommerceGallery
	 */
	public $woocommerce_gallery;

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
	 * Flag to determine if the Featured Image is currently being rendered.
	 *
	 * @var bool|int
	 */
	private $doing_featured_image = false;

	/**
	 * Holds the media settings slug.
	 *
	 * @var string
	 */
	const MEDIA_SETTINGS_SLUG = 'media_display';

	/**
	 * Holds the Global Video Transformations option key.
	 *
	 * @var string
	 */
	const GLOBAL_VIDEO_TRANSFORMATIONS = 'cloudinary_global_video_transformations';

	/**
	 * The Cloudinary Media Library filters.
	 *
	 * @var array
	 */
	protected $cloudinary_filters;

	/**
	 * Media constructor.
	 *
	 * @param Plugin $plugin The global plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'init_hook' ) );

		// Add upgrade hook, since setup methods are called after the connect upgrade has run.
		add_action( 'cloudinary_version_upgrade', array( $this, 'upgrade_media_settings' ) );
	}

	/**
	 * Initialize WordPress hooks for the Cloudinary media integration.
	 *
	 * @return void
	 */
	public function init_hook() {
		/**
		 * Filter the Cloudinary Media Library filters.
		 *
		 * @hook  cloudinary_media_filters
		 * @since 3.0.0
		 *
		 * @param $filters {array} The default filters.
		 */
		$this->cloudinary_filters = apply_filters(
			'cloudinary_media_filters',
			array(
				SYNC::META_KEYS['sync_error'] => __( 'Error', 'cloudinary' ),
				SYNC::META_KEYS['unsynced']   => __( 'Unsynced', 'cloudinary' ),
			)
		);
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
			'text',
			'document',
			'archive',
			'spreadsheet',
			'interactive',
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
	 * Get an array of syncable delivery types.
	 *
	 * @return array
	 */
	public function get_syncable_delivery_types() {
		$types = array(
			'upload',
		);

		/**
		 * Filter the delivery types that are able to sync.
		 *
		 * @param array $types The default syncable types.
		 *
		 * @return array
		 */
		return apply_filters( 'cloudinary_syncable_delivery_types', $types );
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
	 * Check if a file type is compatible with Cloudinary & WordPress.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_file_compatible( $file ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$original_file = $file;
		if ( $this->is_cloudinary_url( $file ) ) {
			$file = Utils::download_fragment( $file );
		}
		if ( file_is_displayable_image( $file ) ) {
			return true;
		}
		$types        = $this->get_compatible_media_types();
		$file         = wp_parse_url( $original_file, PHP_URL_PATH );
		$filename     = Utils::pathinfo( $file, PATHINFO_BASENAME );
		$mime         = wp_check_filetype( $filename );
		$type         = strstr( $mime['type'], '/', true );
		$conversions  = $this->get_convertible_extensions();
		$convertibles = array_keys( $conversions );

		return in_array( $type, $types, true ) && ! in_array( $mime['ext'], $convertibles, true );
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
		if ( 'attachment' === get_post_type( $attachment_id ) ) {
			$media_types = $this->get_compatible_media_types();
			$type        = $this->get_media_type( $attachment_id );
			$is_media    = in_array( $type, $media_types, true );
		}

		/**
		 * Filter the check if post is media.
		 *
		 * @hook    cloudinary_is_media
		 * @since   2.7.6
		 * @default false
		 *
		 * @param $is_media      {bool} Flag if is media.
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return  {bool}
		 */
		return apply_filters( 'cloudinary_is_media', $is_media, $attachment_id );
	}

	/**
	 * Check if the attachment is local.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 *
	 * @return bool
	 */
	public function is_local_media( $attachment_id ) {
		$local_host = wp_parse_url( Utils::site_url(), PHP_URL_HOST );
		$guid       = get_the_guid( $attachment_id );

		// Maybe GUID is a path.
		if ( ! filter_var( $guid, FILTER_VALIDATE_URL ) ) {
			$url = Utils::home_url( $guid );
			if ( $this->maybe_file_exist_in_url( $url ) ) {
				$guid = Utils::home_url( $guid );
			}
		}

		$media_host = wp_parse_url( $guid, PHP_URL_HOST );

		return $local_host === $media_host || $this->is_cloudinary_url( $guid );
	}

	/**
	 * Checks if asset URL is valid.
	 *
	 * @param string $url The URL to test.
	 *
	 * @return bool
	 */
	public function maybe_file_exist_in_url( $url ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$head = wp_safe_remote_head( $url );

		if ( is_wp_error( $head ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $head );

		return 200 === $code;
	}

	/**
	 * Check if the URL can use used to upload to Cloudinary.
	 *
	 * @param string $url_host The url host to check.
	 *
	 * @return bool
	 */
	public function can_upload_from_host( $url_host ) {
		static $additional_urls;

		$is_uploadable = false;

		if ( ! $additional_urls ) {
			$additional_urls = $this->settings->get_value( 'uploadable_domains' );
		}

		if ( ! empty( $additional_urls ) ) {
			$is_uploadable = in_array( $url_host, $additional_urls, true );
		}

		return $is_uploadable;
	}

	/**
	 * Check if the attachment is uploadable.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 *
	 * @return bool
	 */
	public function is_uploadable_media( $attachment_id ) {
		$is_uploadable = $this->is_local_media( $attachment_id );
		$guid          = get_the_guid( $attachment_id );
		$media_host    = wp_parse_url( $guid, PHP_URL_HOST );

		if ( ! $is_uploadable ) {
			$is_uploadable = $this->can_upload_from_host( $media_host );
		}

		/**
		 * Filter local media.
		 *
		 * @hook   cloudinary_is_uploadable_media
		 * @since  2.7.7
		 *
		 * @param $is_local   {bool}   The attachment ID.
		 * @param $media_host {string} The html tag.
		 *
		 * @return {bool}
		 */
		return apply_filters( 'cloudinary_is_uploadable_media', $is_uploadable, $media_host );
	}

	/**
	 * Is the media item size allowed to be uploaded.
	 * Checks against the account limits.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_oversize_media( $attachment_id ) {
		static $is_oversize = array();

		if ( isset( $is_oversize[ $attachment_id ] ) ) {
			return $is_oversize[ $attachment_id ];
		}

		$file_size = $this->get_attachment_file_size( $attachment_id );
		$max_size  = ( wp_attachment_is_image( $attachment_id ) ? 'image_max_size_bytes' : 'video_max_size_bytes' );
		$limit     = $this->plugin->components['connect']->usage['media_limits'][ $max_size ];

		$is_oversize[ $attachment_id ] = $file_size > $limit;

		if ( $is_oversize[ $attachment_id ] ) {
			$max_size    = ( wp_attachment_is_image( $attachment_id ) ? 'image_max_size_bytes' : 'video_max_size_bytes' );
			$max_size_hr = size_format( $this->plugin->components['connect']->usage['media_limits'][ $max_size ] );
			// translators: variable is file size.
			$message = sprintf( __( 'File size exceeds the maximum of %s. This media asset will be served from WordPress.', 'cloudinary' ), $max_size_hr );
			update_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], $message );
		}

		return $is_oversize[ $attachment_id ];
	}

	/**
	 * Get the filesize of an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function get_attachment_file_size( $attachment_id ) {

		$callback = 'get_attached_file';
		if (
			function_exists( 'wp_get_original_image_path' )
			&& wp_attachment_is_image( $attachment_id )
		) {
			$callback = 'wp_get_original_image_path';
		}

		$file = $callback( $attachment_id );
		if ( ! file_exists( $file ) ) {
			return 0;
		}
		$file_size = $this->get_post_meta( $attachment_id, Sync::META_KEYS['file_size'], true );
		if ( empty( $file_size ) ) {
			$file_size = filesize( $file );
			$this->update_post_meta( $attachment_id, Sync::META_KEYS['file_size'], $file_size );
		}

		return $file_size;
	}

	/**
	 * Get the Cloudinary delivery type.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_media_delivery( $attachment_id ) {
		$delivery = $this->get_post_meta( $attachment_id, Sync::META_KEYS['delivery'], true );

		if ( ! empty( $delivery ) ) {
			return $delivery;
		}

		return 'upload';
	}

	/**
	 * Check if an attachment has a delivery type.
	 *
	 * @param int $attachment_id The attachment to check.
	 *
	 * @return bool
	 */
	public function has_delivery_type( $attachment_id ) {
		return ! empty( $this->get_post_meta( $attachment_id, Sync::META_KEYS['delivery'], true ) );
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
		$info             = Utils::pathinfo( $filename );
		$convert          = 'jpg'; // Default handler.

		if ( ! empty( $info['extension'] ) ) {
			$extension = strtolower( $info['extension'] );

			if ( ! empty( $conversion_types[ $extension ] ) ) {
				$convert = $conversion_types[ $extension ];
			}
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
		$file = wp_parse_url( $file, PHP_URL_PATH );
		$file = Utils::pathinfo( $file, PATHINFO_BASENAME );
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
	 * Get the resource type.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_resource_type( $attachment_id ) {
		$media_type = $this->get_media_type( $attachment_id );

		switch ( $media_type ) {
			case 'application':
			case 'text':
				$type = 'raw';
				break;

			case 'audio':
				$type = 'video';
				break;

			default:
				$type = $media_type;
		}

		/**
		 * Filter the Cloudinary resource type for the attachment.
		 *
		 * @param string $type          The type.
		 * @param int    $attachment_id The attachment ID.
		 */
		$type = apply_filters( 'cloudinary_resource_type', $type, $attachment_id );

		return $type;
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
			$file             = Utils::pathinfo( $url );
			$crop             = '-' . implode( 'x', $cropped );
			$file['filename'] = substr( $file['filename'], 0, strlen( $file['filename'] ) - strlen( $crop ) );
			$url              = $file['dirname'] . '/' . $file['filename'] . '.' . $file['extension'];
		}

		return $url;
	}

	/**
	 * Fetch a public id from a cloudinary url.
	 *
	 * @param string $url         The url to fetch the public id from.
	 * @param bool   $as_sync_key Whether to return a plugin-based sync key, which is used to fetch an attachment id.
	 *
	 * @return string|null
	 */
	public function get_public_id_from_url( $url, $as_sync_key = false ) {
		if ( ! $this->is_cloudinary_url( $url ) ) {
			return null;
		}

		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$parts = explode( '/', ltrim( $path, '/' ) );

		$maybe_seo = array();
		$public_id = null;

		// Need to find the version part as anything after this is the public id.
		foreach ( $parts as $part ) {
			$maybe_seo[] = array_shift( $parts ); // Get rid of the first element.
			if ( 'v' === substr( $part, 0, 1 ) && is_numeric( substr( $part, 1 ) ) ) {
				break; // Stop removing elements.
			}
		}

		// Bail on incomplete url.
		if ( empty( $parts ) ) {
			return null;
		}

		// The remaining items should be the file.
		$file      = implode( '/', $parts );
		$path_info = Utils::pathinfo( $file );

		// Is SEO friendly URL.
		if ( in_array( 'images', $maybe_seo, true ) ) {
			$public_id = $path_info['dirname'];
		} elseif ( false !== strpos( $url, '/image/fetch/' ) ) {
			// Maybe the $file is already the URL - $url has the version.
			if ( filter_var( $file, FILTER_VALIDATE_URL ) ) {
				$public_id = $file;
			} else {
				// Capture the url without the version.
				$parts = explode( '/image/fetch/', $url );
				if ( 1 < count( $parts ) ) {
					$public_id = end( $parts );
				}
			}
		} else {
			$public_id = isset( $path_info['dirname'] ) && '.' !== $path_info['dirname'] ? $path_info['dirname'] . DIRECTORY_SEPARATOR . $path_info['filename'] : $path_info['filename'];

			if ( ! empty( $path_info['extension'] ) && in_array( 'raw', $maybe_seo, true ) ) {
				$public_id .= '.' . $path_info['extension'];
			}
		}
		$public_id = trim( $public_id, './' );

		if ( $as_sync_key ) {
			$transformations = $this->get_transformations_from_string( $url );
			$public_id      .= ! empty( $transformations ) ? wp_json_encode( $transformations ) : '';
		}

		return rawurldecode( $public_id );
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
			$sync_key      = $this->get_public_id_from_url( $url, true );
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
			$file     = Utils::pathinfo( $url );
			$end_part = substr( strrchr( $file['filename'], '-' ), 1 );
			if ( false !== $end_part && 1 === substr_count( $end_part, 'x' ) && is_numeric( str_replace( 'x', '', $end_part ) ) ) {

				$size_parts = explode( 'x', $end_part );
				$size_int   = array_map( 'intval', $size_parts );
				$size       = array_filter( $size_int );
				if ( ! empty( $size ) && 2 === count( $size ) ) {
					$return = $size;
				}
			}
		}

		if ( $return ) {
			$return = array_values( $return );
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
					$cropped = ! wp_image_matches_ratio(
					// PDFs do not always have width and height, but they do have full sizes.
					// This is important for the thumbnail crops on the media library.
						! empty( $meta['width'] ) ? $meta['width'] : ( ! empty( $meta['sizes']['full']['width'] ) ? $meta['sizes']['full']['width'] : 0 ),
						! empty( $meta['height'] ) ? $meta['height'] : ( ! empty( $meta['sizes']['full']['height'] ) ? $meta['sizes']['full']['height'] : 0 ),
						$size['width'],
						$size['height']
					);
					if ( isset( $additional_sizes[ $size_name ]['crop'] ) ) {
						$cropped = $additional_sizes[ $size_name ]['crop'];
					}
					// Make the WP Size array.
					$wp_size = array(
						'wpsize'         => $size_name,
						'file'           => $size['file'],
						'width'          => $size['width'],
						'height'         => $size['height'],
						'transformation' => 'c_scale',
					);
					if ( $cropped ) {
						// Special thumbnail size.
						if ( 'thumbnail' === $size_name ) {
							$wp_size['transformation'] = 'c_thumb,g_auto';
						}
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
	 * Get transformations for an attachment to use in a final URL.
	 *
	 * @param int   $attachment_id             The attachment ID.
	 * @param array $transformations           Base/starter set of transformations.
	 * @param bool  $overwrite_transformations Flag to indicate if default transformations should not be applied.
	 *
	 * @return array
	 */
	public function get_transformations( $attachment_id, $transformations = array(), $overwrite_transformations = false ) {
		static $cache = array();

		$key = $this->get_cache_key( func_get_args() );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		// If not provided, get transformations from the attachment meta.
		if ( empty( $transformations ) ) {
			$transformations = $this->get_transformation_from_meta( $attachment_id );
		}
		if ( false === $overwrite_transformations ) {
			$overwrite_transformations = $this->maybe_overwrite_featured_image( $attachment_id );
		}

		// Defaults are only to be added on front, main images ( not breakpoints, since these are adapted down), and videos.
		if ( false === $overwrite_transformations && ! Utils::is_admin() ) {
			$transformations = $this->apply_default_transformations( $transformations, $attachment_id );
		}

		/**
		 * Filter the Cloudinary transformations.
		 *
		 * @param array $transformations Array of transformation options.
		 * @param int   $attachment_id   The id of the asset.
		 *
		 * @return array
		 */
		$cache[ $key ] = apply_filters( 'cloudinary_transformations', $transformations, $attachment_id );

		return $cache[ $key ];
	}

	/**
	 * Get the crop transformation for the attachment.
	 *
	 * @param int|string $attachment_id The attachment ID or type.
	 * @param array      $size          The requested size width and height.
	 *
	 * @return string
	 */
	public function get_crop_transformations( $attachment_id, $size ) {
		static $transformations = array();
		$size_dim               = $size['width'] . 'x' . $size['height'];
		$key                    = $attachment_id . $size_dim;
		if ( empty( $transformations[ $key ] ) ) {

			if ( empty( $size['transformation'] ) ) {
				$size['transformation'] = 'c_scale';
			}
			$crops = $this->settings->get_value( 'crop_sizes' );
			if ( ! empty( $crops[ $size_dim ] ) ) {
				if ( '--' === $crops[ $size_dim ] ) {
					$size['transformation'] = '';
				} else {
					$size['transformation'] = $crops[ $size_dim ];
				}
			}

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
			$enabled_crop_and_gravity = apply_filters( 'cloudinary_enable_crop_and_gravity_control', true );

			// Check for custom crop.
			if ( is_numeric( $attachment_id ) && $enabled_crop_and_gravity ) {
				$meta_sizes = $this->get_post_meta( $attachment_id, 'cloudinary_metaboxes_crop_meta', true );
				if ( ! empty( $meta_sizes['single_crop_and_gravity']['single_sizes'] ) ) {
					$custom_sizes = $meta_sizes['single_crop_and_gravity']['single_sizes'];
					if ( ! empty( $custom_sizes[ $size_dim ] ) ) {
						if ( '--' === $custom_sizes[ $size_dim ] ) {
							$size['transformation'] = '';
						} else {
							$size['transformation'] = $custom_sizes[ $size_dim ];
						}
					}
				}
			}
			$transformations[ $key ] = 'w_' . $size['width'] . ',h_' . $size['height'];
			if ( ! empty( $size['transformation'] ) ) {
				$transformations[ $key ] .= ',' . $size['transformation'];
			}
		}

		return $transformations[ $key ];
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
	 * Extract transformations from string..
	 *
	 * @param string $str  The transformation string.
	 * @param string $type The type of transformation string.
	 *
	 * @return array The array of found transformations within the string.
	 */
	public static function extract_transformations_from_string( $str, $type = 'image' ) {
		static $media;
		if ( ! $media ) {
			$media = get_plugin_instance()->get_component( 'media' );
		}

		return $media->get_transformations_from_string( $str, $type );
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
		if ( ! isset( Api::$transformation_index[ $type ] ) ) {
			return array();
		}

		$params = Api::$transformation_index[ $type ];

		$transformation_chains = explode( '/', $str );
		$transformations       = array();
		foreach ( $transformation_chains as $index => $chain ) {
			$items = explode( ',', $chain );
			foreach ( $items as $item ) {
				$item = trim( $item );
				// After the asset version, there are no further transformations.
				if ( ! empty( $item ) && 'v' === $item[0] && is_numeric( substr( $item, 1 ) ) ) {
					break 2;
				}
				foreach ( $params as $param => $transformation ) {
					if ( substr( $item, 0, strlen( $param ) + 1 ) === $param . '_' ) {
						$transformations[ $index ][ $transformation ] = substr( $item, strlen( $param ) + 1 );
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

		if ( ! $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) {
			return $url;
		}

		// Previous v1 and Cloudinary only storage.
		if ( false !== strpos( $url, 'https://', 5 ) ) {
			$dirs = wp_get_upload_dir();

			return str_replace( trailingslashit( $dirs['baseurl'] ), '', $url );
		}

		if (
			false === $this->in_downsize
			&& ! doing_filter( 'content_save_pre' )
			&& ! Utils::is_saving_metadata()
			/**
			 * Filter doing upload.
			 * If so, return the default attachment URL.
			 *
			 * @param bool Default false.
			 *
			 * @return bool
			 */
			&& ! apply_filters( 'cloudinary_doing_upload', false )
		) {
			if ( ! $this->is_cloudinary_url( $url ) && $this->cloudinary_id( $attachment_id ) ) {
				$url = $this->cloudinary_url( $attachment_id );
			}
		}

		return $url;
	}

	/**
	 * Get the original size URL, when original_attachment_url is called, and it's a Cloudinary URL.
	 *
	 * @param string $url           The current url.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string Cloudinary URL.
	 */
	public function original_attachment_url( $url, $attachment_id ) {
		if ( $this->is_cloudinary_url( $url ) ) {
			$url = $this->raw_cloudinary_url( $attachment_id );
		}

		return $url;
	}

	/**
	 * Apply default image transformations before building the URL.
	 *
	 * @param array      $transformations    The set of transformations.
	 * @param int|string $attachment_id_type The attachment ID | or attachment type.
	 *
	 * @return array
	 */
	public function apply_default_transformations( array $transformations, $attachment_id_type ) {
		static $cache = array(), $freeform = array();

		$key = $this->get_cache_key( func_get_args() );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		/**
		 * Filter to allow bypassing defaults. Return false to not apply defaults.
		 *
		 * @param bool       $true               True to apply defaults.
		 * @param int|string $attachment_id_type The current attachment ID or type.
		 *
		 * @return bool
		 */
		if ( false === apply_filters( 'cloudinary_apply_default_transformations', true, $attachment_id_type ) ) {
			return $transformations;
		}
		$type = $attachment_id_type;
		if ( is_numeric( $attachment_id_type ) ) {
			$type = $this->get_media_type( $attachment_id_type );
		}
		// Base image level.
		$new_transformations = array(
			'video'  => array(),
			'image'  => Api::generate_transformation_string( $transformations, $type ),
			'tax'    => array(),
			'global' => array(),
			'qf'     => array(),
		);
		// Get Taxonomies.
		$new_transformations['tax'] = $this->global_transformations->get_taxonomy_transformations( $type );
		if ( ! $this->global_transformations->is_taxonomy_overwrite() ) {
			/**
			 * Filter the default Quality and Format transformations for the specific media type.
			 *
			 * @param array $defaults        The default transformations array.
			 * @param array $transformations The current transformations array.
			 *
			 * @return array
			 */
			$default                   = apply_filters( "cloudinary_default_qf_transformations_{$type}", array(), $transformations );
			$default                   = array_filter( $default ); // Clear out empty settings.
			$new_transformations['qf'] = Api::generate_transformation_string( array( $default ), $type );

			if ( empty( $freeform[ $type ] ) ) {
				/**
				 * Filter the default Freeform transformations for the specific media type.
				 *
				 * @param array $defaults        The default transformations array.
				 * @param array $transformations The current transformations array.
				 *
				 * @return array
				 */
				$freeform[ $type ] = apply_filters( "cloudinary_default_freeform_transformations_{$type}", array(), $transformations );
				$freeform[ $type ] = array_filter( $freeform[ $type ] ); // Clear out empty settings.
			}
			// Add freeform global transformations.
			if ( ! empty( $freeform[ $type ] ) ) {
				$new_transformations['global'] = implode( '/', $freeform[ $type ] );
			}
		}

		$streaming = $this->get_settings()->get_value( 'adaptive_streaming', 'adaptive_streaming_mode' );

		if ( 'video' === $type && 'on' === $streaming['adaptive_streaming'] ) {
			unset( $new_transformations['qf'] );
		}

		// Clean out empty parts, and join into a sectioned string.
		$new_transformations = array_filter( $new_transformations );
		$new_transformations = implode( '/', $new_transformations );
		// Take sectioned string, and create a transformation array set.
		$transformations = $this->get_transformations_from_string( $new_transformations, $type );
		/**
		 * Filter the default cloudinary transformations.
		 *
		 * @param array $defaults The default transformations array.
		 *
		 * @return array
		 */
		$cache[ $key ] = apply_filters(
			'cloudinary_default_transformations',
			$transformations
		);

		return $cache[ $key ];
	}

	/**
	 * Apply default  quality anf format image transformations.
	 *
	 * @param array $default The current default transformations.
	 *
	 * @return array
	 */
	public function default_image_transformations( $default ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

		$config = $this->settings->get_value( 'image_settings' );

		if ( 'on' === $config['image_optimization'] ) {
			if ( ! empty( $config['image_format'] ) && 'none' !== $config['image_format'] ) {
				$default['fetch_format'] = $config['image_format'];
			}
			if ( isset( $config['image_quality'] ) ) {
				$default['quality'] = 'none' !== $config['image_quality'] ? $config['image_quality'] : null;
			} else {
				$default['quality'] = 'auto';
			}
		}

		return $default;
	}

	/**
	 * Apply default image freeform transformations.
	 *
	 * @param array $default The current default transformations.
	 *
	 * @return array
	 */
	public function default_image_freeform_transformations( $default ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		$config = $this->settings->get_value( 'image_settings' );
		if ( ! empty( $config['image_freeform'] ) ) {
			$default[] = trim( $config['image_freeform'] );
		}

		return $default;
	}

	/**
	 * Get a cache key for static caching.
	 *
	 * @param array $args The arguments array to generate a key with.
	 *
	 * @return string
	 */
	protected function get_cache_key( $args ) {
		$args[] = $this->global_transformations->get_current_post();

		return md5( wp_json_encode( $args ) );
	}

	/**
	 * Generate a Cloudinary URL based on attachment ID and required size.
	 *
	 * @param int          $attachment_id             The id of the attachment.
	 * @param array|string $size                      The wp size to set for the URL.
	 * @param array|string $transformations           Set of transformations to apply to this url.
	 * @param string|null  $cloudinary_id             Optional forced cloudinary ID.
	 * @param bool         $overwrite_transformations Flag url is a breakpoint URL to stop re-applying default transformations.
	 *
	 * @return string The converted URL.
	 */
	public function cloudinary_url( $attachment_id, $size = array(), $transformations = array(), $cloudinary_id = null, $overwrite_transformations = false ) {
		static $cache = array();

		if ( ! $cloudinary_id ) {
			$cloudinary_id = $this->cloudinary_id( $attachment_id );
			if ( ! $cloudinary_id ) {
				return null;
			}
		}

		$public_id = $this->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );

		if ( ! empty( $public_id ) ) {
			$cloudinary_id = $public_id;
		}

		$args = array(
			$attachment_id,
			$size,
			$transformations,
			$cloudinary_id,
			$overwrite_transformations,
		);

		$key = $this->get_cache_key( array_filter( $args ) );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		// Get the attachment resource type.
		$resource_type = $this->get_resource_type( $attachment_id );
		// Setup initial args for cloudinary_url.
		$delivery = $this->get_media_delivery( $attachment_id );
		$pre_args = array(
			'secure'        => is_ssl(),
			'version'       => $this->get_cloudinary_version( $attachment_id ),
			'resource_type' => $resource_type,
			'delivery'      => $delivery,
		);
		$set_size = array();
		if ( 'upload' === $delivery ) {
			$set_size = $this->prepare_size( $attachment_id, $size );
		}
		// Prepare transformations.
		if ( ! empty( $transformations ) && is_string( $transformations ) ) {
			$transformations = $this->get_transformations_from_string( $transformations, $resource_type );
		}

		$pre_args['transformation'] = $this->get_transformations( $attachment_id, $transformations, $overwrite_transformations );

		// Make a copy as not to destroy the options in \Cloudinary::cloudinary_url().
		$args = $pre_args;
		$url  = $this->plugin->components['connect']->api->cloudinary_url( $cloudinary_id, $args, $set_size, $attachment_id );

		// Check if this type is a preview only type. i.e PDF.
		if ( ! empty( $set_size ) && $this->is_preview_only( $attachment_id ) ) {
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
		$url = apply_filters( 'cloudinary_converted_url', $url, $attachment_id, $pre_args );



		// Early bail for admin AJAX requests.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_admin() ) {
			$cache[ $key ] = $url;

			return $cache[ $key ];
		}

		// Add Cloudinary analytics.
		$cache[ $key ] = add_query_arg(
			array(
				'_i' => 'AA',
			),
			$url
		);

		return $cache[ $key ];
	}

	/**
	 * Get the local URL for an attachment.
	 *
	 * @param int $attachment_id The attachment ID to get.
	 *
	 * @return string|false
	 */
	public function local_url( $attachment_id ) {
		static $urls = array();
		if ( ! empty( $urls[ $attachment_id ] ) ) {
			return $urls[ $attachment_id ];
		}
		$this->in_downsize      = true;
		$urls[ $attachment_id ] = wp_get_attachment_url( $attachment_id );
		$this->in_downsize      = false;

		/**
		 * Filter local URL.
		 *
		 * @hook    cloudinary_local_url
		 * @since   3.0.0
		 *
		 * @param $url           {string|false} The local URL
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return  {string|false}
		 */
		return apply_filters( 'cloudinary_local_url', $urls[ $attachment_id ], $attachment_id );
	}

	/**
	 * Get the local URL for an attachment.
	 *
	 * @param int $attachment_id The attachment ID to get.
	 *
	 * @return string|false
	 */
	public function raw_cloudinary_url( $attachment_id ) {
		static $api;
		if ( ! $api ) {
			$api = $this->plugin->components['connect']->api;
		}

		$url = $this->get_post_meta( $attachment_id, Sync::META_KEYS['raw_url'], true );
		if ( empty( $url ) ) {
			$transformations = $this->get_transformation_from_meta( $attachment_id );
			$parts           = array(
				'https:/',
				$api->asset_url,
			);

			// We should use the cloud name on cname accounts.
			if ( empty( $this->credentials['cname'] ) ) {
				$parts[] = $api->credentials['cloud_name'];
			}

			$parts = array_merge(
				$parts,
				array(
					$this->get_resource_type( $attachment_id ),
					$this->get_media_delivery( $attachment_id ),
					$api::generate_transformation_string( $transformations ),
					'v' . $this->get_cloudinary_version( $attachment_id ),
					$this->get_cloudinary_id( $attachment_id ),
				)
			);

			$url = implode( '/', array_filter( $parts ) );
			$this->update_post_meta( $attachment_id, Sync::META_KEYS['raw_url'], $url );
		}

		/**
		 * Filter a base Cloudinary URL (no transformations).
		 *
		 * @hook    cloudinary_raw_url
		 * @since   3.0.0
		 *
		 * @param $url           {string|false} The local URL
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return  {string|false}
		 */
		return apply_filters( 'cloudinary_raw_url', $url, $attachment_id );
	}

	/**
	 * Prepare the Size array for the Cloudinary URL API.
	 *
	 * @param int          $attachment_id The attachment ID.
	 * @param array|string $size          The size array or slug.
	 *
	 * @return array|string
	 */
	public function prepare_size( $attachment_id, $size ) {
		if ( 'raw' === $size ) {
			return array();
		}
		// Check size and correct if string or size.
		if ( empty( $size ) || 'full' === $size ) {
			// Maybe get full size if scaled.
			$meta = wp_get_attachment_metadata( $attachment_id, true );
			if ( ! empty( $meta['original_image'] ) ) {
				$size = array(
					'width'  => $meta['width'],
					'height' => $meta['height'],
					'full'   => true,
				);
			}
		} elseif ( is_string( $size ) || ( is_array( $size ) && 3 === count( $size ) ) ) {
			$intermediate = image_get_intermediate_size( $attachment_id, $size );
			// PDF's do not have intermediate URL.
			if ( is_array( $intermediate ) && ! empty( $intermediate['url'] ) ) {
				$size = $this->get_crop( $intermediate['url'], $attachment_id );
			}
		} elseif ( array_keys( $size ) === array( 0, 1 ) ) {
			$size = array(
				'width'  => $size[0],
				'height' => $size[1],
			);
			if ( $size['width'] === $size['height'] ) {
				$size['transformation'] = 'c_fill,g_auto';
			}
		}

		// add Global crops.
		if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
			$size['transformation'] = $this->get_crop_transformations( $attachment_id, $size );
		}
		/**
		 * Filter Cloudinary size and crops
		 *
		 * @param array|string $size          The size array or slug.
		 * @param int          $attachment_id The attachment ID.
		 *
		 * @return array|string
		 */
		$size = apply_filters( 'cloudinary_prepare_size', $size, $attachment_id );

		return $size;
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
	 * @param bool $add_trailing_slash Whether to add trailing slash or not.
	 *
	 * @return string
	 */
	public function get_cloudinary_folder( $add_trailing_slash = true ) {
		$folder = '';

		if ( ! empty( $this->cloudinary_folder ) && '/' !== $this->cloudinary_folder ) {
			$folder = trim( $this->cloudinary_folder, '/' );

			if ( $add_trailing_slash ) {
				$folder = trailingslashit( $folder );
			}
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
			if ( true === $suffixed && ! empty( $this->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true ) ) ) {
				$suffix = $this->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );
				if ( false === strrpos( $public_id, $suffix ) ) {
					$public_id .= $suffix;
					$this->update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
				}
				$this->delete_post_meta( $attachment_id, Sync::META_KEYS['suffix'] );
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
		$new_id = $this->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );
		$id     = get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true );

		return ! empty( $new_id ) || ! empty( $id );
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
			$public_id = $this->get_public_id( $attachment_id, true );
			// Get the file, and use the same extension.
			$file = get_attached_file( $attachment_id );
			// @todo: Make this use the globals, overrides, and application conversion.
			$extension     = Utils::pathinfo( $file, PATHINFO_EXTENSION );
			$cloudinary_id = $public_id;
			$type          = $this->get_resource_type( $attachment_id );
			if ( in_array( $type, array( 'image', 'video' ), true ) ) {
				$format = $this->settings->find_setting( $type . '_format' )->get_value();
				if ( ! in_array( $format, array( 'none', 'auto' ), true ) ) {
					$extension = $format;
				}
				if ( 'fetch' !== $this->get_media_delivery( $attachment_id ) ) {
					$cloudinary_id = $public_id . '.' . $extension;
				}
			} elseif ( empty( Utils::pathinfo( $public_id, PATHINFO_EXTENSION ) ) ) {
				$cloudinary_id = $public_id . '.' . $extension;
			}
		}

		return $cloudinary_id;
	}

	/**
	 * Get a Cloudinary ID
	 *
	 * @param int $attachment_id The ID to get Cloudinary id for.
	 *
	 * @return string|false the ID or false if not existing.
	 */
	public function cloudinary_id( $attachment_id ) {
		static $cloudinary_ids = array();

		// Return cached ID if we've already gotten it before.
		if ( isset( $cloudinary_ids[ $attachment_id ] ) ) {
			return $cloudinary_ids[ $attachment_id ];
		}

		if ( ! $this->is_media( $attachment_id ) ) {
			$cloudinary_ids[ $attachment_id ] = false;

			return false;
		}

		if ( ! $this->sync->is_synced( $attachment_id ) && ! defined( 'REST_REQUEST' ) ) {
			$sync_type = $this->sync->maybe_prepare_sync( $attachment_id );
			// Check sync type allows for continued rendering. i.e meta update, breakpoints etc, will still allow the URL to work,
			// Where is type "file" will not since it's still being uploaded.
			if ( $this->sync->is_required( $sync_type, $attachment_id ) ) {
				// Cache ID to prevent multiple lookups.
				$cloudinary_ids[ $attachment_id ] = false;

				return false; // Return and render local URLs.
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

		$cloudinary_ids[ $attachment_id ] = $cloudinary_id;

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
	 */
	public function filter_downsize( $image, $attachment_id, $size ) {
		if ( ! $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) {
			return $image;
		}

		// Don't do this while saving.
		if ( true === $this->in_downsize || doing_filter( 'content_save_pre' ) || wp_attachment_is( 'video', $attachment_id ) || Utils::is_saving_metadata() ) {
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
	 * At the point of running wp_get_attachment_image_srcset, the $image_src Should be a Cloudinary URL, unless not synced.
	 * This will fix the $image_meta so that the there's a match $src_matched on wp_calculate_image_srcset.
	 *
	 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
	 * @param int[]  $size_array    {
	 *     An array of width and height values.
	 *
	 *     @type int $0 The width in pixels.
	 *     @type int $1 The height in pixels.
	 * }
	 * @param string $image_src     The 'src' of the image.
	 *
	 * @return array
	 */
	public function calculate_image_srcset_meta( $image_meta, $size_array, $image_src ) {
		if ( $this->is_cloudinary_url( $image_src ) ) {
			$image_meta['file'] = wp_parse_url( $image_src, PHP_URL_PATH );
		}

		// PDFs don't have sizes, so we need to inject them.
		if ( empty( $image_meta['width'] ) ) {
			$image_meta['width'] = $size_array[0];
		}

		if ( empty( $image_meta['height'] ) ) {
			$image_meta['height'] = $size_array[1];
		}

		return $image_meta;
	}

	/**
	 * Convert an attachment URL to a Cloudinary one.
	 *
	 * @param string      $url                       Url to convert.
	 * @param int         $attachment_id             Attachment ID.
	 * @param array       $transformations           Optional transformations.
	 * @param bool        $overwrite_transformations Flag url as having an overwrite transformation.
	 * @param string|null $cloudinary_id             The cloudinary ID if have one.
	 *
	 * @return string Converted URL.
	 */
	public function convert_url( $url, $attachment_id, $transformations = array(), $overwrite_transformations = true, $cloudinary_id = null ) {

		if ( $this->is_cloudinary_url( $url ) ) {
			return $url; // Already is a cloudinary URL, just return.
		}
		$size = $this->get_crop( $url, $attachment_id );

		return $this->cloudinary_url( $attachment_id, $size, $transformations, $cloudinary_id, $overwrite_transformations );
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

		if ( ! $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) {
			return $sources;
		}

		$cloudinary_id = isset( $image_meta['cloudinary_id'] ) ? $image_meta['cloudinary_id'] : $this->cloudinary_id( $attachment_id );
		if ( ! $cloudinary_id ) {
			return $sources; // Return WordPress default sources.
		}

		// Get transformations if any.
		$transformations = Relate::get_transformations( $attachment_id );

		// For cases where transformations are added via cld_params.
		if ( ! empty( $image_meta['transformations'] ) ) {
			$transformations = array_filter( array_merge( $transformations, $image_meta['transformations'] ) );
		}

		// Use Cloudinary breakpoints for same ratio.
		$image_meta['overwrite_transformations'] = ! empty( $image_meta['overwrite_transformations'] ) ? $image_meta['overwrite_transformations'] : false;

		if ( ! empty( $image_meta['width'] ) && ! empty( $image_meta['height'] ) && 'on' === $this->settings->get_setting( 'enable_breakpoints' )->get_value() && wp_image_matches_ratio( $image_meta['width'], $image_meta['height'], $size_array[0], $size_array[1] ) ) {
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
						'url'        => $this->cloudinary_url( $attachment_id, $size, $transformations, $cloudinary_id, $image_meta['overwrite_transformations'] ),
						'descriptor' => 'w',
						'value'      => $breakpoint['width'],
					);
				}
				krsort( $sources, SORT_NUMERIC );

				return $sources;
			}
		}

		// Handle unexpected sources variable type.
		if ( ! is_array( $sources ) ) {
			return $sources;
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
				$size          = $this->get_size_from_url( $source['url'] );
				$source['url'] = $this->cloudinary_url( $attachment_id, $size, $transformations, $cloudinary_id, $image_meta['overwrite_transformations'] );
			}
		}

		return $sources;
	}

	/**
	 * Check if a url is a cloudinary url or not.
	 *
	 * @param string $url The url in question.
	 *
	 * @return bool
	 */
	public function is_cloudinary_url( $url ) {
		if ( ! filter_var( utf8_uri_encode( $url ), FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$test_parts = wp_parse_url( $url );
		$cld_url    = wp_parse_url( $this->base_url, PHP_URL_HOST );

		return isset( $test_parts['path'] ) && false !== strpos( $test_parts['host'], $cld_url );
	}

	/**
	 * Check if a url is pointing to Cloudinary sync folder.
	 *
	 * @param string $url The tested URL.
	 *
	 * @return bool
	 */
	public function is_cloudinary_sync_folder( $url ) {
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$parts = explode( '/', $path );

		// Remove public id and file name.
		array_splice( $parts, - 2 );

		foreach ( $parts as $part ) {
			array_shift( $parts );
			if ( empty( $part ) ) {
				continue;
			}
			if ( 'v' === $part[0] && is_numeric( substr( $part, 1 ) ) ) {
				break;
			}
		}

		// Check for the Cloudinary folder.
		return implode( '/', $parts ) === $this->get_cloudinary_folder( false );
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
		$deps = wp_script_is( 'cld-core', 'registered' ) ? array( 'cld-core' ) : array();
		$this->plugin->register_assets(); // Ensure assets are registered.

		/**
		 * Filter the maximum number of files that can be imported from Cloudinary.
		 *
		 * @hook    cloudinary_max_files_import
		 * @since   3.1.3
		 *
		 * @param $max_files {int} The maximum number of files that can be imported from Cloudinary.
		 *
		 * @default 20
		 *
		 * @return  {int}
		 *
		 * @example
		 * <?php
		 *
		 * // Filter Cloudinary max files per import.
		 * add_filter(
		 *    'cloudinary_max_files_import',
		 *    static function() {
		 *        return 100;
		 *    }
		 * );
		 */
		$max_files = apply_filters( 'cloudinary_max_files_import', 20 );

		// External assets.
		wp_enqueue_script( 'cloudinary-media-modal', $this->plugin->dir_url . '/js/media-modal.js', null, $this->plugin->version, true );
		wp_enqueue_script( 'cloudinary-media-library', CLOUDINARY_ENDPOINTS_MEDIA_LIBRARY, $deps, $this->plugin->version, true );
		wp_enqueue_script( 'cloudinary-terms-order', $this->plugin->dir_url . '/js/terms-order.js', array( 'jquery' ), $this->plugin->version, true );
		wp_enqueue_style( 'cloudinary' );
		$params = array(
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'storage'   => $this->plugin->components['storage']->get_settings( 'offload' ),
			'mloptions' => array(
				'cloud_name'     => $this->credentials['cloud_name'],
				'api_key'        => $this->credentials['api_key'],
				'cms_type'       => 'wordpress',
				'insert_caption' => __( 'Import', 'cloudinary' ),
				'remove_header'  => true,
				'max_files'      => $max_files,
				'integration'    => array(
					'type'     => 'wordpress_plugin',
					'platform' => 'WordPress ' . get_bloginfo( 'version' ),
					'version'  => $this->plugin->version,
				),
			),
		);

		// Set folder if needed.
		$folder = $this->get_cloudinary_folder( false );
		if ( ! empty( $folder ) ) {
			$params['mloptions']['folder'] = array( 'path' => $folder );
		}

		$params['mloptions']['insert_transformation'] = true;
		$params['mloptions']['inline_container']      = '#cloudinary-dam';

		wp_add_inline_script( 'cloudinary-media-library', 'var CLDN = ' . wp_json_encode( $params ), 'before' );
	}

	/**
	 * Create a new attachment post item.
	 *
	 * @param array  $asset     The asset array data.
	 * @param string $public_id The cloudinary public id.
	 *
	 * @return int|WP_Error
	 */
	private function create_attachment( $asset, $public_id ) {

		// Create an attachment post.
		$file_path        = $asset['url'];
		$file_name        = wp_basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
		$attachment_title = sanitize_file_name( Utils::pathinfo( $file_name, PATHINFO_FILENAME ) );
		$post_args        = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Capture the Caption Text.
		if ( ! empty( $asset['meta']['caption'] ) ) {
			$post_args['post_excerpt'] = wp_strip_all_tags( $asset['meta']['caption'] );
		}

		// Disable Upload_Sync to avoid sync loop.
		add_filter( 'cloudinary_upload_sync_enabled', '__return_false' );
		// Create the attachment.
		$attachment_id = wp_insert_attachment( $post_args, false );

		$sync_key = $asset['sync_key'];
		// Capture public_id. Use core update_post_meta since this attachment data doesnt exist yet.
		$this->update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );

		// Capture version number.
		$this->update_post_meta( $attachment_id, Sync::META_KEYS['version'], $asset['version'] );
		if ( ! empty( $asset['transformations'] ) ) {
			// Save a combined key.
			$sync_key .= wp_json_encode( $asset['transformations'] );
			// Use post meta temporarily to store the transformations until the attachment gets a sync relationship.
			$this->update_post_meta( $attachment_id, Sync::META_KEYS['transformation'], $asset['transformations'] );
		}

		// Create a trackable key in post meta to allow getting the attachment id from URL with transformations.
		update_post_meta( $attachment_id, '_' . md5( $sync_key ), true );

		// Create a trackable key in post meta to allow getting the attachment id from URL.
		update_post_meta( $attachment_id, '_' . md5( 'base_' . $public_id ), true );

		// capture the delivery type.
		$this->update_post_meta( $attachment_id, Sync::META_KEYS['delivery'], $asset['type'] );
		// Capture the ALT Text.
		if ( ! empty( $asset['meta']['alt'] ) ) {
			$alt_text = wp_strip_all_tags( $asset['meta']['alt'] );
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

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
			'public_id'       => sanitize_text_field( $data['asset']['public_id'] ),
			'type'            => sanitize_text_field( $data['asset']['type'] ),
			'format'          => sanitize_text_field( $data['asset']['format'] ),
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

		// convert_media_extension.
		if ( ! $this->is_file_compatible( $asset['url'] ) ) {
			$asset['url'] = $this->convert_media_extension( $asset['url'] );
		}

		// Move all context data into the meta key.
		if ( ! empty( $data['asset']['context'] ) ) {
			array_walk_recursive(
				$data['asset']['context'],
				function ( $value, $key ) use ( &$asset ) {
					$asset['meta'][ $key ] = sanitize_text_field( $value );
				}
			);
		}

		// Check for transformations.
		$transformations = $this->get_transformations_from_string( $asset['url'] );
		if ( ! empty( $transformations ) ) {
			$asset['sync_key']       .= wp_json_encode( $transformations );
			$asset['transformations'] = $transformations;
		}

		// Check Format.
		$url_format = Utils::pathinfo( $asset['url'], PATHINFO_EXTENSION );
		if ( strtolower( $url_format ) !== strtolower( $asset['format'] ) ) {
			$asset['format']    = $url_format;
			$asset['sync_key'] .= $url_format;
		}

		// Attempt to find attachment ID.
		$asset['attachment_id'] = $this->get_id_from_sync_key( $asset['sync_key'] );
		$asset['instances']     = Relationship::get_ids_by_public_id( $asset['public_id'] );

		/**
		 * Filter the asset payload.
		 *
		 * @hook   cloudinary_asset_payload
		 * @since  3.1.3
		 *
		 * @param $asset {array} The asset payload.
		 * @param $data  {array} The raw data from the request.
		 *
		 * @return {array}
		 *
		 * @example
		 * <?php
		 *
		 * // Extend Cloudinary support for extra data.
		 * // Contextual metadata is passed by default.
		 * add_filter(
		 *    'cloudinary_asset_payload',
		 *    static function ( $asset, $payload ) {
		 *        // The structured keys on Cloudinary to use in WordPress.
		 *        $key = 'structured_key';
		 *
		 *        // Structural metadata. Beware of key collision with contextual metadata.
		 *        if ( ! empty ( $payload['asset']['metadata'][ $key ] ) ) {
		 *            // The sanitize function to use should be adequate to the data type.
		 *            $asset['meta'][ $key ] = sanitize_text_field( $payload['asset']['metadata'][ $key ] );
		 *        }
		 *
		 *        // The Cloudinary tags.
		 *        if ( ! empty ( $payload['asset']['tags'] ) ) {
		 *            $asset['tags'] = array_map( 'sanitize_text_field', $payload['asset']['tags'] );
		 *        }
		 *
		 *        return $asset;
		 *    },
		 *    10,
		 *    2
		 * );
		 */
		$asset = apply_filters( 'cloudinary_asset_payload', $asset, $data );

		return $asset;
	}

	/**
	 * Create and prepare a down sync asset from Cloudinary.
	 */
	public function down_sync_asset() {
		$nonce = Utils::get_sanitized_text( 'nonce', INPUT_POST );
		if ( is_user_logged_in() && wp_verify_nonce( $nonce, 'wp_rest' ) && current_user_can( 'upload_files' ) ) {
			$asset = $this->get_asset_payload();
			// Set a base array for pulling an asset if needed.
			$base_return = array(
				'fetch'         => Utils::rest_url( REST_API::BASE . '/asset' ),
				'uploading'     => true,
				'src'           => $asset['src'],
				'url'           => $asset['url'],
				'filename'      => wp_basename( $asset['src'] ),
				'attachment_id' => $asset['attachment_id'],
				'public_id'     => $asset['public_id'],
			);
			if ( empty( $asset['attachment_id'] ) ) {
				$return                  = $base_return;
				$asset['attachment_id']  = $this->create_attachment( $asset, $asset['public_id'] );
				$return['attachment_id'] = $asset['attachment_id'];
			} else {
				// Capture the ALT Text.
				if ( ! empty( $asset['meta']['alt'] ) ) {
					$alt_text = wp_strip_all_tags( $asset['meta']['alt'] );
					foreach ( $asset['instances'] as $id ) {
						update_post_meta( $id, '_wp_attachment_image_alt', $alt_text );
					}
				}
				// Capture the Caption Text.
				if ( ! empty( $asset['meta']['caption'] ) ) {
					$caption = wp_strip_all_tags( $asset['meta']['caption'] );
					foreach ( $asset['instances'] as $id ) {
						wp_update_post(
							array(
								'ID'           => $id,
								'post_excerpt' => $caption,
							)
						);
					}
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

			/**
			 * Action for the downloaded assets from Cloudinary Media Library.
			 *
			 * @hook  cloudinary_download_asset
			 * @since 3.1.3
			 *
			 * @param $asset  {array} The default filters.
			 * @param $return {array} The return payload.
			 *
			 * @example
			 * <?php
			 * add_action(
			 *    'cloudinary_download_asset',
			 *    static function ( $asset ) {
			 *        // Store metadata. Contextual and Structured metadata should be similar.
			 *        $key = 'metadata_key';
			 *        if ( ! empty( $asset['meta'][ $key ] ) && ! empty( $asset['attachment_id'] ) && 'attachment' === get_post_type( $asset['attachment_id'] ) ) {
			 *            update_post_meta( $asset['attachment_id'],  $key , $asset['meta'][ $key ] );
			 *        }
			 *
			 *        // Store the tags. The taxonomy needs to be assigned to the post type.
			 *        if ( ! empty( $asset['tags'] ) && ! empty( $asset['attachment_id'] ) && 'attachment' === get_post_type( $asset['attachment_id'] ) ) {
			 *            wp_set_post_terms(
			 *                $asset['attachment_id'],
			 *                $asset['tags']
			 *            );
			 *        }
			 *    }
			 * );
			 */
			do_action( 'cloudinary_download_asset', $asset, $return );

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
			if ( ! $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) :
				?>
				<span class="dashicons-cloudinary info" title="<?php esc_attr_e( 'The delivery for this asset is disabled.', 'cloudinary' ); ?>"></span>
				<?php
			elseif ( ! $this->is_uploadable_media( $attachment_id ) ) :
				?>
				<span class="dashicons-cloudinary info" title="<?php esc_attr_e( 'Not syncable. This is an external media.', 'cloudinary' ); ?>"></span>
				<?php
			elseif ( 'fetch' === $this->get_media_delivery( $attachment_id ) ) :
				?>
				<span class="dashicons-cloudinary info" title="<?php esc_attr_e( 'This media is Fetch type.', 'cloudinary' ); ?>"></span>
				<?php
			elseif ( 'sprite' === $this->get_media_delivery( $attachment_id ) ) :
				?>
				<span class="dashicons-cloudinary info" title="<?php esc_attr_e( 'This media is Sprite type.', 'cloudinary' ); ?>"></span>
				<?php
			elseif ( get_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], true ) || $this->is_oversize_media( $attachment_id ) ) :
				$title = get_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], true )
				?>
				<span class="dashicons-cloudinary error" title="<?php echo esc_attr( $title ); ?>"></span>
				<?php
			elseif ( $this->sync->is_syncable( $attachment_id ) && $this->is_uploadable_media( $attachment_id ) ) :
				$status = array(
					'state' => 'inactive',
					'note'  => esc_html__( 'Not Synced', 'cloudinary' ),
				);
				if ( $this->cloudinary_id( $attachment_id ) && 'upload' === $this->get_media_delivery( $attachment_id ) ) {
					$status = array(
						'state' => 'success',
						'note'  => esc_html__( 'Synced', 'cloudinary' ),
					);

					if ( wp_attachment_is_image( $attachment_id ) ) {
						if ( empty( get_post_meta( $attachment_id, Sync::META_KEYS['remote_size'], true ) ) ) {
							$this->plugin->get_component( 'storage' )->size_sync( $attachment_id );
						}
					}
				}
				// filter status.
				$status = apply_filters( 'cloudinary_media_status', $status, $attachment_id );
				?>
				<span class="dashicons-cloudinary <?php echo esc_attr( $status['state'] ); ?>" title="<?php echo esc_attr( $status['note'] ); ?>"></span>
				<?php
			endif;
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
	public function default_max_width() {
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
		$max_width = array_shift( $sizes );

		return $max_width;
	}

	/**
	 * Get the max image width registered in WordPress.
	 *
	 * @return int
	 */
	public function get_max_width() {
		return $this->settings->get_setting( 'max_width' )->get_value();
	}

	/**
	 * Get transformations from post meta for an attachment.
	 *
	 * @param int $post_id The post to get meta for.
	 *
	 * @return array
	 */
	public function get_transformation_from_meta( $post_id ) {
		return Relate::get_transformations( $post_id );
	}

	/**
	 * Get Cloudinary related Post meta.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 * @param bool   $single  If single or not.
	 * @param mixed  $default The default value if empty.
	 *
	 * @return mixed
	 */
	public function get_post_meta( $post_id, $key = '', $single = false, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

		$meta = get_post_meta( $post_id, Sync::META_KEYS['cloudinary'], true );
		if ( empty( $meta ) ) {
			/**
			 * Filter the meta if not found, in order to migrate from a legacy plugin.
			 *
			 * @hook   cloudinary_migrate_legacy_meta
			 * @since  2.7.5
			 *
			 * @param $attachment_id {int} The attachment ID.
			 *
			 * @return {array}
			 */
			$meta = apply_filters( 'cloudinary_migrate_legacy_meta', $post_id );
		}
		if ( '' !== $key ) {
			$meta = isset( $meta[ $key ] ) ? $meta[ $key ] : $default;
		}

		return $single ? $meta : (array) $meta;
	}

	/**
	 * Gets the process logs for the attachment.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $raw           The errors expanded and no readable time.
	 *
	 * @return array|mixed|null
	 */
	public function get_process_logs( $attachment_id, $raw = false ) {
		$logs = get_post_meta( $attachment_id, Sync::META_KEYS['process_log'], true );

		if ( empty( $logs ) ) {
			$logs = (array) $this->get_post_meta( $attachment_id, Sync::META_KEYS['process_log_legacy'], true, array() );
			add_post_meta( $attachment_id, Sync::META_KEYS['process_log'], $logs, true );

			$this->delete_post_meta( $attachment_id, Sync::META_KEYS['process_log_legacy'] );
		}

		foreach ( $logs as $signature => $log ) {
			if ( empty( $log ) ) {
				$logs[ $signature ] = array();
				continue;
			}
			if ( is_wp_error( $log ) ) {
				$logs[ $signature ]                 = array();
				$logs[ $signature ][ '_' . time() ] = array(
					'code'    => $log->get_error_code(),
					'message' => $log->get_error_message(),
				);
				continue;
			}
			foreach ( $log as $time => $entry ) {
				$time = ltrim( $time, '_' );

				// Cleanup 0'd logs.
				if ( 0 === (int) $time ) {
					unset( $logs[ $signature ][ "_{$time}" ] );
					continue;
				}

				$to_unset = null;

				// If timestamped request.
				if ( $raw ) {
					// Fix stored expanded time.
					if ( ! is_numeric( $time ) ) {
						$to_unset = $time;
						$time     = strtotime( $time );
					}
					$time = "_{$time}";
				} else { // Readable request.
					$to_unset = "_{$time}";
					$time     = gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
				}

				// Maybe cleanup log entries.
				if ( $to_unset ) {
					unset( $logs[ $signature ][ $to_unset ] );
				}

				$logs[ $signature ][ $time ] = $entry;

				if (
					is_array( $entry )
					&& ! empty( $entry['code'] )
					&& ! empty( $entry['message'] )
				) {
					$logs[ $signature ][ $time ] = $raw ? $entry : new WP_Error( $entry['code'], $entry['message'] );
				}
			}
		}

		return $logs;
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
	 *
	 * @return bool
	 */
	public function update_post_meta( $post_id, $key, $data ) {

		$meta = $this->get_post_meta( $post_id );
		if ( ! isset( $meta[ $key ] ) ) {
			$meta[ $key ] = '';
		}

		if ( $meta[ $key ] !== $data ) {
			$meta[ $key ] = $data;
		}

		return update_post_meta( $post_id, Sync::META_KEYS['cloudinary'], $meta );
	}

	/**
	 * Delete cloudinary metadata.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 *
	 * @return bool
	 */
	public function delete_post_meta( $post_id, $key ) {

		$meta = $this->get_post_meta( $post_id );
		if ( isset( $meta[ $key ] ) ) {
			unset( $meta[ $key ] );
		}

		return update_post_meta( $post_id, Sync::META_KEYS['cloudinary'], $meta );
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
		$settings    = $this->settings->get_value( 'responsive' );

		if ( 'on' === $settings['enable_breakpoints'] && wp_attachment_is_image( $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id, true );
			// Get meta image size if non exists.
			if ( empty( $meta ) ) {
				$meta          = array();
				$imagesize     = getimagesize( get_attached_file( $attachment_id ) );
				$meta['width'] = isset( $imagesize[0] ) ? $imagesize[0] : 0;
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
				$breakpoints['transformation'] = Api::generate_transformation_string( $transformations, 'image' );
			}
			$breakpoints = array(
				'public_id'              => $this->get_public_id( $attachment_id, true ),
				'type'                   => 'upload',
				'responsive_breakpoints' => $breakpoint_options,
				'context'                => $this->get_context_options( $attachment_id ),
			);
			// Check for suffix.
			$breakpoints['public_id'] .= $this->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );

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
		$caption = get_post( $attachment_id )->post_excerpt;

		if ( empty( $caption ) ) {
			$caption = get_the_title( $attachment_id );
		}
		$media_library_context = array(
			'caption' => esc_attr( $caption ),
			'alt'     => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'guid'    => md5( Utils::get_path_from_url( get_the_guid( $attachment_id ), true ) ),
		);
		$context_options       = array(
			'cld_wp_plugin' => 1,
			'wp_context'    => Utils::get_media_context( $attachment_id ),
		);
		if ( $this->is_folder_synced( $attachment_id ) ) {
			$context_options = wp_parse_args( $media_library_context, $context_options );
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
		foreach ( $context_options as $option => &$value ) {
			$value = str_replace( '=', '\=', $value );
			$value = str_replace( '|', '\|', $value );
			$value = $option . '=' . $value;
		}

		return implode( '|', $context_options );
	}

	/**
	 * Check if an asset is folder synced.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_folder_synced( $attachment_id ) {

		$is_folder_synced = true; // By default all assets in WordPress will be synced.
		if ( $this->sync->been_synced( $attachment_id ) ) {
			$is_folder_synced = ! empty( $this->get_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true ) );
		}

		/**
		 * Filter is folder synced flag.
		 *
		 * @param bool $is_folder_synced Flag value for is folder sync.
		 * @param int  $attachment_id    The attachment ID.
		 *
		 * @return bool
		 */
		$is_folder_synced = apply_filters( 'cloudinary_is_folder_synced', $is_folder_synced, $attachment_id );

		return (bool) $is_folder_synced;
	}

	/**
	 * Get the media upload options as connected to Cloudinary.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $context       The context to use.
	 *
	 * @return array
	 */
	public function get_upload_options( $attachment_id, $context = '' ) {

		// Prepare upload options.
		$public_id = $this->get_public_id( $attachment_id, true );
		$folder    = ltrim( dirname( $public_id ), '.' );
		$options   = array(
			'unique_filename' => true,
			'overwrite'       => false,
			'resource_type'   => $this->get_resource_type( $attachment_id ),
			'public_id'       => wp_basename( $public_id ),
			'context'         => $this->get_context_options( $attachment_id ),
		);

		if ( 'image' === $options['resource_type'] || 'video' === $options['resource_type'] ) {
			$options['eager']       = Api::generate_transformation_string( $this->apply_default_transformations( array(), $attachment_id ), $options['resource_type'], $context );
			$options['eager_async'] = 'video' === $options['resource_type'];
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
		$options = apply_filters( 'cloudinary_upload_options', $options, get_post( $attachment_id ), $this );
		// Add folder to prevent folder contamination.
		if ( $this->is_folder_synced( $attachment_id ) ) {
			$cld_folder              = $this->get_cloudinary_folder();
			$options['public_id']    = $cld_folder . wp_basename( $options['public_id'] );
			$options['asset_folder'] = $cld_folder;
		} elseif ( ! empty( $folder ) ) {
			// add in folder if not empty (not in root).
			$options['public_id'] = trailingslashit( $folder ) . wp_basename( $options['public_id'] );
		}
		$options['public_id'] = trim( $options['public_id'], '/.' );

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
		if ( $this->doing_featured_image && $this->doing_featured_image === (int) $attachment_id ) {
			$overwrite = (bool) get_post_meta( get_the_ID(), Global_Transformations::META_FEATURED_IMAGE_KEY, true );
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
		if ( $this->sync->is_synced( $attachment_id ) ) {
			$this->doing_featured_image = (int) $attachment_id;
		}
	}

	/**
	 * Maybe add responsive images to a post thumbnail.
	 *
	 * @param string $content       The content to alter.
	 * @param int    $post_id       The current post ID (unused).
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function maybe_srcset_post_thumbnail( $content, $post_id, $attachment_id ) {
		if ( ! $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) {
			return $content;
		}

		// Check the attachment is synced and does not already have a srcset (some themes do this already).
		if ( $this->doing_featured_image === $attachment_id ) {
			$overwrite_transformations  = $this->maybe_overwrite_featured_image( $attachment_id );
			$content                    = $this->apply_srcset( $content, $attachment_id, $overwrite_transformations );
			$this->doing_featured_image = false; // Reset featured.
		}

		return $content;
	}

	/**
	 * Apply srcset to an image tag.
	 *
	 * @param string $content                   The image tag.
	 * @param int    $attachment_id             The attachment ID.
	 * @param bool   $overwrite_transformations Flag to overwrite transformations.
	 *
	 * @return string
	 */
	public function apply_srcset( $content, $attachment_id, $overwrite_transformations = false ) {
		$cloudinary_id                           = $this->get_cloudinary_id( $attachment_id );
		$image_meta                              = wp_get_attachment_metadata( $attachment_id );
		$image_meta['file']                      = Utils::pathinfo( $cloudinary_id, PATHINFO_FILENAME ) . '/' . Utils::pathinfo( $cloudinary_id, PATHINFO_BASENAME );
		$image_meta['overwrite_transformations'] = $overwrite_transformations;

		return wp_image_add_srcset_and_sizes( $content, $image_meta, $attachment_id );
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

		if ( empty( $version ) ) {
			// This might be also an asset from the hidden post type (Assets::POST_TYPE_SLUG).
			$attachment = get_post( $attachment_id );

			if ( ! empty( $attachment ) && Assets::POST_TYPE_SLUG === $attachment->post_type && ! empty( $attachment->post_parent ) ) {
				$version = (int) preg_replace( '/\D/', '', $this->get_post_meta( (int) $attachment->post_parent, Sync::META_KEYS['version'], true ) );
			}
		}

		return $version ? $version : 1;
	}
	/**
	 * Upgrade media related settings, including global transformations etc.
	 *
	 * @uses action:cloudinary_version_upgrade
	 */
	public function upgrade_media_settings() {
		// Check that transformations is in default (hasn't been saved before).
		if ( empty( get_option( self::GLOBAL_VIDEO_TRANSFORMATIONS, null ) ) ) {
			// Setup default to CLD, since default changed from WP to CLD after 2.0.3.
			$video = array(
				'video_player' => 'cld',
			);
			update_option( self::GLOBAL_VIDEO_TRANSFORMATIONS, $video );
		}
	}

	/**
	 * Checks if local URLS can be filtered out.
	 *
	 * @return bool
	 */
	public function can_filter_out_local() {
		$can = true;
		if ( 'cld' !== $this->plugin->settings->find_setting( 'offload' )->get_value() ) {
			/**
			 * Filter to allow stopping filtering out local.
			 *
			 * @param bool $can True as default.
			 *
			 * @return bool
			 */
			$can = apply_filters( 'cloudinary_filter_out_local', true );
		}

		return $can;
	}

	/**
	 * Filters the new sizes to ensure non upload (sprites), don't get resized.
	 *
	 * @param array    $new_sizes     Array of sizes.
	 * @param array    $image_meta    Image metadata.
	 * @param int|null $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function manage_sizes( $new_sizes, $image_meta, $attachment_id = null ) {
		if ( is_null( $attachment_id ) ) {
			$attachment_id = $this->plugin->settings->get_param( '_currrent_attachment', 0 );
		}
		if ( $this->has_public_id( $attachment_id ) ) {
			// Get delivery type.
			$delivery = $this->get_media_delivery( $attachment_id );
			if ( 'upload' !== $delivery ) {
				// Only upload based deliveries will get intermediate sizes.
				$new_sizes = array();
			}
		}

		return $new_sizes;
	}

	/**
	 * Fix the PDF resource type.
	 *
	 * @link https://cloudinary.com/cookbook/convert_pdf_to_jpg
	 *
	 * @param string $type          The default type.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 **/
	public function pdf_resource_type( $type, $attachment_id ) {

		if ( 'application/pdf' === get_post_mime_type( $attachment_id ) ) {
			$type = 'image';
		}

		return $type;
	}

	/**
	 * Update the Query with the Cloudinary filters.
	 *
	 * @param WP_Query $query The query instance.
	 */
	public function apply_media_library_filters( $query ) {
		if ( is_admin() && $query->is_main_query() ) {
			$request = Utils::get_sanitized_text( 'cloudinary-filter' );

			if ( SYNC::META_KEYS['sync_error'] === $request ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query[] = array(
					'relation' => 'AND',
				);
				$meta_query[] = array(
					'key'     => $request,
					'compare' => 'EXISTS',
				);
				$query->set( 'meta_query', $meta_query );
			}

			if ( SYNC::META_KEYS['unsynced'] === $request ) {
				global $wpdb;
				$wpdb->cld_table = Utils::get_relationship_table();
				$result          = $wpdb->get_col( "SELECT post_id FROM $wpdb->cld_table WHERE public_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

				// phpcs:disable WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
				if ( ! empty( $result ) ) {
					$query->set( 'post__in', $result );
				} else {
					$query->set( 'post__in', array( 0 ) );
				}
				// phpcs:enable
			}
		}
	}

	/**
	 * The the Cloudinary's Media Library filters markup.
	 *
	 * @param string $post_type The post type slug.
	 */
	public function filter_media_library( $post_type ) {
		if ( 'attachment' === $post_type ) {
			$request = Utils::get_sanitized_text( 'cloudinary-filter' );
			?>
			<select name="cloudinary-filter" id="cloudinary-filter">
				<option value="none"><?php esc_html_e( 'No Cloudinary filters', 'cloudinary' ); ?></option>
				<?php foreach ( $this->cloudinary_filters as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $request ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
		}
	}

	/**
	 * Filter live URLS.
	 * Used in admin and in the REST API.
	 */
	public function add_live_url_filters() {
		add_filter( 'wp_calculate_image_srcset', array( $this, 'image_srcset' ), 10, 5 );
		add_filter( 'wp_get_attachment_url', array( $this, 'attachment_url' ), 10, 2 );
		add_filter( 'wp_get_original_image_url', array( $this, 'original_attachment_url' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'filter_downsize' ), 10, 3 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'calculate_image_srcset_meta' ), 10, 3 );

		// Hook into Featured Image cycle.
		add_action( 'begin_fetch_post_thumbnail_html', array( $this, 'set_doing_featured' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( $this, 'maybe_srcset_post_thumbnail' ), 10, 3 );
	}

	/**
	 * Setup the hooks and base_url if configured.
	 */
	public function setup() {
		if ( $this->plugin->settings->get_param( 'connected' ) ) {

			$this->base_url          = $this->plugin->components['connect']->api->cloudinary_url();
			$this->credentials       = $this->plugin->components['connect']->get_credentials();
			$this->cloudinary_folder = $this->settings->get_value( 'cloudinary_folder' );
			$this->sync              = $this->plugin->components['sync'];

			// Internal components.
			$this->global_transformations = new Global_Transformations( $this );
			$this->gallery                = $this->plugin->get_component( 'gallery' );
			$this->woocommerce_gallery    = new WooCommerceGallery( $this->gallery );
			$this->filter                 = new Filter( $this );
			$this->upgrade                = new Upgrade( $this );
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
			if ( Utils::is_admin() ) {
				$this->add_live_url_filters();
			}
			// Filter default image Quality and Format transformations.
			add_filter( 'cloudinary_default_qf_transformations_image', array( $this, 'default_image_transformations' ), 10 );
			add_filter( 'cloudinary_default_freeform_transformations_image', array( $this, 'default_image_freeform_transformations' ), 10 );

			// Filter and action the custom column.
			add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
			add_action( 'manage_media_custom_column', array( $this, 'media_column_value' ), 10, 2 );

			// Handle other delivery types.
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'manage_sizes' ), PHP_INT_MAX, 3 ); // High level to avoid other plugins breaking it.

			// Filter PDF resource type.
			add_filter( 'cloudinary_resource_type', array( $this, 'pdf_resource_type' ), 10, 2 );

			add_action( 'restrict_manage_posts', array( $this, 'filter_media_library' ) );
			add_action( 'pre_get_posts', array( $this, 'apply_media_library_filters' ) );
		}
	}

	/**
	 * Register sync settings.
	 *
	 * @return array
	 */
	public function settings() {

		$image_settings      = array();
		$video_settings      = array();
		$image_settings_file = $this->plugin->dir_path . 'ui-definitions/settings-image.php';
		$video_settings_file = $this->plugin->dir_path . 'ui-definitions/settings-video.php';

		if ( file_exists( $image_settings_file ) ) {
			$image_settings = include $image_settings_file; //phpcs:ignore
		}

		if ( file_exists( $video_settings_file ) ) {
			$video_settings = include $video_settings_file; //phpcs:ignore
		}

		$args = array(
			'type'       => 'page',
			'menu_title' => __( 'Media Settings', 'cloudinary' ),
			'tabs'       => array(
				self::MEDIA_SETTINGS_SLUG => array(
					'page_title' => __( 'Media Display', 'cloudinary' ),
					array(
						'type'      => 'info_box',
						'icon'      => $this->plugin->dir_url . 'css/images/transformation.svg',
						'title'     => __( 'Transformations', 'cloudinary' ),
						'text'      => __(
							'Cloudinary allows you to easily transform your images on-the-fly to any required format, style and dimension, and also optimizes images for minimal file size alongside high visual quality for an improved user experience and minimal bandwidth. You can do all of this by implementing dynamic image transformation and delivery URLs.',
							'cloudinary'
						),
						'url'       => 'https://cloudinary.com/documentation/transformation_reference',
						'link_text' => __( 'See examples', 'cloudinary' ),
					),
					$image_settings,
					$video_settings,
				),
			),
		);

		return $args;
	}

	/**
	 * Enabled method for version if settings are enabled.
	 *
	 * @param bool $enabled Flag to enable.
	 *
	 * @return bool
	 */
	public function is_enabled( $enabled ) {
		return $this->plugin->settings->get_param( 'connected' );
	}

	/**
	 * Upgrade settings from 2.4 to 2.5.
	 *
	 * @param string $previous_version Previous version.
	 * @param string $new_version      New version.
	 */
	public function upgrade_settings( $previous_version, $new_version ) {

		if ( 2.4 === $previous_version ) {
			// Setup new data from old.
			$images    = get_option( 'cloudinary_global_transformations', array() );
			$video     = get_option( self::GLOBAL_VIDEO_TRANSFORMATIONS, array() );
			$old_media = array_merge( $images, $video );
			$setting   = $this->settings->get_setting( 'media_display' );
			// Get the current defaults.
			$default = $setting->get_value();

			$media = wp_parse_args( $old_media, $default );
			// Update value.
			$setting->set_value( $media );
			// Save to DB.
			$setting->save_value();
		}
	}
}
