<?php
/**
 * Cloudinary API wrapper.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Connect;

use Cloudinary\Relate\Relationship;
use Cloudinary\Sync;
use Cloudinary\Utils;
use Cloudinary\Plugin;
use Cloudinary\Media;
use function Cloudinary\get_plugin_instance;

/**
 * Class API.
 *
 * Push media to Cloudinary on upload.
 */
class Api {

	/**
	 * The cloudinary credentials array.
	 *
	 * @var array
	 */
	public $credentials;

	/**
	 * Cloudinary Asset URL.
	 *
	 * @var string
	 */
	public $asset_url = 'res.cloudinary.com';

	/**
	 * Cloudinary API Version.
	 *
	 * @var string
	 */
	public $api_version = 'v1_1';

	/**
	 * Plugin Version
	 *
	 * @var string
	 */
	public $plugin_version;

	/**
	 * Holds the media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * List of cloudinary transformations.
	 *
	 * @var array
	 */
	public static $transformation_index = array(
		'image' => array(
			'a'   => 'angle',
			'ar'  => 'aspect_ratio',
			'b'   => 'background',
			'bo'  => 'border',
			'c'   => 'crop',
			'co'  => 'color',
			'dpr' => 'dpr',
			'du'  => 'duration',
			'e'   => 'effect',
			'eo'  => 'end_offset',
			'fl'  => 'flags',
			'h'   => 'height',
			'l'   => 'overlay',
			'o'   => 'opacity',
			'q'   => 'quality',
			'r'   => 'radius',
			'so'  => 'start_offset',
			't'   => 'named_transformation',
			'u'   => 'underlay',
			'vc'  => 'video_codec',
			'w'   => 'width',
			'x'   => 'x',
			'y'   => 'y',
			'z'   => 'zoom',
			'ac'  => 'audio_codec',
			'af'  => 'audio_frequency',
			'br'  => 'bit_rate',
			'cs'  => 'color_space',
			'd'   => 'default_image',
			'dl'  => 'delay',
			'dn'  => 'density',
			'f'   => 'fetch_format',
			'g'   => 'gravity',
			'p'   => 'prefix',
			'pg'  => 'page',
			'sp'  => 'streaming_profile',
			'vs'  => 'video_sampling',
			'if'  => 'if',
		),
		'video' => array(
			'w'   => 'width',
			'h'   => 'height',
			'c'   => 'crop',
			'ar'  => 'aspect_ratio',
			'g'   => 'gravity',
			'b'   => 'background',
			'e'   => 'effect',
			'l'   => 'overlay',
			'so'  => 'start_offset',
			'co'  => 'color',
			'eo'  => 'end_offset',
			'du'  => 'duration',
			'a'   => 'angle',
			'vs'  => 'video_sampling',
			'dl'  => 'delay',
			'vc'  => 'video_codec',
			'fps' => 'fps',
			'dpr' => 'dpr',
			'br'  => 'bit_rate',
			'ki'  => 'keyframe_interval',
			'sp'  => 'streaming_profile',
			'ac'  => 'audio_codec',
			'af'  => 'audio_frequency',
			'fl'  => 'flags',
			'f'   => 'fetch_format',
			'q'   => 'quality',
			'r'   => 'radius',
			'o'   => 'opacity',
			'if'  => 'if',
			'y'   => 'y_axis',
			'x'   => 'x_axis',
		),
	);

	/**
	 * Cloudinary qualified upload prefixes to date.
	 *
	 * @var string[]
	 */
	public static $qualified_upload_prefixes = array(
		'api.cloudinary.com',
		'api-eu.cloudinary.com',
		'api-ap.cloudinary.com',
	);

	/**
	 * Current pending url to overide the fields to post.
	 *
	 * @var string|null
	 */
	private $pending_url = array();

	/**
	 * API constructor.
	 *
	 * @param \Cloudinary\Connect $connect The connect object.
	 * @param string              $version The plugin version.
	 */
	public function __construct( $connect, $version ) {
		$this->credentials    = $connect->get_credentials();
		$this->plugin_version = $version;
		// Use CNAME.
		if ( ! empty( $this->credentials['cname'] ) ) {
			$this->asset_url = $this->credentials['cname'];
		}
		add_action( 'cloudinary_ready', array( $this, 'setup' ) );
	}

	/**
	 * Setup the API
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function setup( Plugin $plugin ) {
		$this->media = $plugin->get_component( 'media' );
	}

	/**
	 * Return an endpoint for a specific resource type.
	 *
	 * @param string $resource The resource type for the endpoint.
	 * @param string $function The function of the endpoint.
	 * @param bool   $endpoint Flag to get an endpoint or an asset url.
	 *
	 * @return string
	 */
	public function url( $resource, $function = null, $endpoint = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		$parts = array();

		if ( $endpoint ) {
			$parts[] = $this->get_upload_prefix();
			$parts[] = $this->api_version;
		} else {
			$parts[] = $this->asset_url;
		}

		if ( empty( $this->credentials['cname'] ) || $endpoint || 'false' === $this->credentials['private_cdn'] ) {
			$parts[] = $this->credentials['cloud_name'];
		}

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

		if ( false === $endpoint && 'image' === $resource && 'upload' === $function && ! $bypass_seo_url ) {
			$parts[] = 'images';
		} else {
			$parts[] = $resource;
			$parts[] = $function;
		}

		$parts = array_filter( $parts );
		$url   = implode( '/', $parts );

		return $url;
	}

	/**
	 * Generate a transformation string.
	 *
	 * @param array  $options The transformation options to generate from.
	 * @param string $type    The asset Type.
	 * @param string $context The context.
	 *
	 * @return string
	 */
	public static function generate_transformation_string( array $options, $type = 'image', $context = '' ) {
		if ( ! isset( self::$transformation_index[ $type ] ) ) {
			return '';
		}
		$transformation_index = self::$transformation_index[ $type ];
		$transformations      = array_map(
			function ( $item ) use ( $transformation_index ) {
				$transform = array();
				if ( is_string( $item ) ) {
					return $item;
				}

				foreach ( $item as $type => $value ) { // phpcs:ignore
					$key = array_search( $type, $transformation_index, true );
					if ( false !== $key ) {
						$transform[] = $key . '_' . $value;
					} elseif ( '$' === $type[0] ) {
						$transform[] = $type . '_' . $value;
					}
				}

				return implode( ',', $transform );
			},
			$options
		);

		// Prepare the eager transformations for the upload.
		if ( 'upload' === $context ) {
			foreach ( $transformations as &$transformation ) {
				if ( 0 <= strpos( $transformation, 'f_auto' ) ) {
					$parts = explode( ',', $transformation );
					unset( $parts[ array_search( 'f_auto', $parts, true ) ] );
					$remaining_transformations = implode( ',', $parts );
					$formats                   = array();

					if ( 'image' === $type ) {
						$formats = array(
							'f_avif',
							'f_webp',
							'f_webp,fl_awebp',
							'f_wdp',
							'f_jp2',
						);
					} elseif ( 'video' === $type ) {
						$formats = array(
							'f_webm,vc_vp9',
							'f_mp4,vc_h265',
							'f_mp4,vc_h264',
						);
					}

					/**
					 * Filter the upload eager formats.
					 *
					 * @hook cloudinary_upload_eager_formats
					 * @since 3.1.6
					 *
					 * @param $formats {array} The default formats.
					 * @param $type    {string} The asset type.
					 *
					 * @return {array}
					 */
					$formats = apply_filters( 'cloudinary_upload_eager_formats', $formats, $type );

					array_walk(
						$formats,
						static function ( &$i ) use ( $remaining_transformations ) {
							if ( $remaining_transformations ) {
								$i .= ",{$remaining_transformations}";
							}
						}
					);

					$transformation = implode( '|', $formats );
				}
			}
		}

		// Clear out empty parts.
		$transformations = array_filter( $transformations );

		return implode( '/', $transformations );
	}

	/**
	 * Generate a Cloudinary URL.
	 *
	 * @param string|null $public_id     The Public ID to get a url for.
	 * @param array       $args          Additional args.
	 * @param array       $size          The WP Size array.
	 * @param int|null    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function cloudinary_url( $public_id = null, $args = array(), $size = array(), $attachment_id = null ) {

		if ( null === $public_id ) {
			return 'https://' . $this->url( null, null );
		}
		$defaults = array(
			'resource_type' => 'image',
			'delivery'      => 'upload',
			'version'       => 'v1',
		);
		$args     = wp_parse_args( array_filter( $args ), $defaults );

		// check for version.
		if ( ! empty( $args['version'] ) && is_numeric( $args['version'] ) ) {
			$args['version'] = 'v' . $args['version'];
		}

		// Determine if we're dealing with a fetched.
		// ...or uploaded image and update the URL accordingly.
		$asset_endpoint = filter_var( $public_id, FILTER_VALIDATE_URL ) ? 'fetch' : $args['delivery'];

		$url_parts = array(
			'https:/',
			$this->url( $args['resource_type'], $asset_endpoint ),
		);

		$base = Utils::pathinfo( $public_id );
		// Add size.
		if ( ! empty( $size ) && is_array( $size ) ) {
			if ( ! empty( $size['transformation'] ) ) {
				$url_parts[] = $size['transformation'];
			}
			// add size to ID if scaled.
			if ( ! empty( $size['file'] ) ) {
				$public_id = str_replace( $base['basename'], $size['file'], $public_id );
			}
		}
		if ( ! empty( $args['transformation'] ) ) {
			$url_parts[] = self::generate_transformation_string( $args['transformation'], $args['resource_type'] );
		}

		if ( $attachment_id ) {
			$public_id = $this->get_public_id( $attachment_id, $args, $public_id );
		}

		$url_parts[] = $args['version'];
		$url_parts[] = $public_id;

		// Clear out empty parts.
		$url_parts = array_filter( $url_parts );

		return implode( '/', $url_parts );
	}

	/**
	 * Get the details of an asset by public ID.
	 *
	 * @param string $public_id The public_id to check.
	 * @param string $type      The asset type.
	 *
	 * @return array|\WP_Error
	 */
	public function get_asset_details( $public_id, $type ) {
		$url = $this->url( 'resources', $type . '/upload/' . $public_id, true );

		return $this->call( $url, array( 'body' => array() ), 'get' );
	}

	/**
	 * Upload a large asset in chunks.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $args          Array of upload options.
	 *
	 * @return array|\WP_Error
	 */
	public function upload_large( $attachment_id, $args ) {
		// Ensure we have the right file.
		if ( empty( $args['file'] ) ) {
			$args['file'] = get_attached_file( $attachment_id );
		}
		$tempfile = false;
		if ( false !== strpos( $args['file'], 'vip://' ) ) {
			$args['file'] = $this->create_local_copy( $args['file'] );
			if ( is_wp_error( $args['file'] ) ) {
				return $args['file'];
			}
			$tempfile = true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		WP_Filesystem();
		global $wp_filesystem;

		if ( ! in_array( $wp_filesystem->method, array( 'vip', 'direct' ), true ) ) {
			// We'll need to have direct file access to be able to read a chunked version to upload.
			// Perhaps we could use the URL upload method in this case?
			return new \WP_Error( 'upload_error', __( 'No direct access to file system.', 'cloudinary' ) );
		}

		// Since WP_Filesystem doesn't have a fread, we need to do it manually. However we'll still use it for writing.
		$src            = fopen( $args['file'], 'r' ); // phpcs:ignore
		$temp_file_name = wp_tempnam( uniqid( time() ) . '.' . Utils::pathinfo( $args['file'], PATHINFO_EXTENSION ) );
		$upload_id      = substr( sha1( uniqid( $this->credentials['api_secret'] . wp_rand() ) ), 0, 16 );
		$chunk_size     = 20000000;
		$index          = 0;
		$file_size      = filesize( $args['file'] );
		while ( ! feof( $src ) ) {
			$current_loc = $index * $chunk_size;
			if ( $current_loc >= $file_size ) {
				break;
			}
			$data = fread( $src, $chunk_size ); // phpcs:ignore
			file_put_contents( $temp_file_name, $data ); //phpcs:ignore

			clearstatcache( true, $temp_file_name );

			$temp_file_size = filesize( $temp_file_name );
			$range          = 'bytes ' . $current_loc . '-' . ( $current_loc + $temp_file_size - 1 ) . '/' . $file_size;

			$headers      = array(
				'Content-Range'      => $range,
				'X-Unique-Upload-Id' => $upload_id,
			);
			$args['file'] = $temp_file_name;
			$result       = $this->upload( $attachment_id, $args, $headers );
			if ( is_wp_error( $result ) ) {
				break;
			}
			++$index;
		}
		fclose( $src ); //phpcs:ignore
		unlink( $temp_file_name ); //phpcs:ignore
		if ( true === $tempfile ) {
			unlink( $args['file'] ); //phpcs:ignore
		}

		return $result;
	}

	/**
	 * Copy an asset from one Cloudinary location to another.
	 *
	 * @param int   $attachment_id Attachment ID to upload.
	 * @param array $args          Array of upload options.
	 *
	 * @return array|\WP_Error
	 */
	public function copy( $attachment_id, $args ) {
		$resource     = ! empty( $args['resource_type'] ) ? $args['resource_type'] : 'image';
		$url          = $this->url( $resource, 'upload', true );
		$args         = $this->clean_args( $args );
		$args['file'] = $this->media->raw_cloudinary_url( $attachment_id );
		$call_args    = array(
			'headers' => array(),
			'body'    => $args,
		);

		return $this->call( $url, $call_args, 'post' );
	}

	/**
	 * Upload an asset.
	 *
	 * @param int   $attachment_id Attachment ID to upload.
	 * @param array $args          Array of upload options.
	 * @param array $headers       Additional headers to use in upload.
	 * @param bool  $try_remote    Flag to try_remote upload.
	 *
	 * @return array|\WP_Error
	 */
	public function upload( $attachment_id, $args, $headers = array(), $try_remote = true ) {

		$resource            = ! empty( $args['resource_type'] ) ? $args['resource_type'] : 'image';
		$url                 = $this->url( $resource, 'upload', true );
		$args                = $this->clean_args( $args );
		$disable_https_fetch = get_transient( '_cld_disable_http_upload' );

		/**
		 * Whether to use the original image URL.
		 *
		 * @hook    cloudinary_use_original_image
		 * @since   3.1.8
		 * @default true
		 *
		 * @param $use_original  {bool} The default value.
		 * @param $attachment_id {int}  The attachment ID.
		 *
		 * @return  {bool}
		 */
		$use_original = apply_filters( 'cloudinary_use_original_image', true, $attachment_id );
		if ( $use_original && function_exists( 'wp_get_original_image_url' ) && wp_attachment_is_image( $attachment_id ) ) {
			$file_url = wp_get_original_image_url( $attachment_id );
		} else {
			$file_url = wp_get_attachment_url( $attachment_id );
		}

		if ( empty( $file_url ) ) {
			$disable_https_fetch = true;
		}

		if ( ! $this->media ) {
			$this->media = get_plugin_instance()->get_component( 'media' );
		}

		if ( $this->media && ! $this->media->is_uploadable_media( $attachment_id ) ) {
			$disable_https_fetch = false; // Remote can upload via url.
			// translators: variable is thread name and queue size.
			$action_message = sprintf( __( 'Uploading remote url:  %1$s.', 'cloudinary' ), $file_url );
			do_action( '_cloudinary_queue_action', $action_message );
		}
		$tempfile = false;
		if ( $this->media && $this->media->is_cloudinary_url( $file_url ) ) {
			// If this is a Cloudinary URL, then we can use it to fetch from that location.
			$disable_https_fetch = false;
		}
		// Check if we can try http file upload.
		if ( ( empty( $headers ) && empty( $disable_https_fetch ) && true === $try_remote ) || ! $this->media->is_uploadable_media( $attachment_id ) ) {
			$args['file'] = $file_url;
		} elseif ( ! $this->media->is_local_media( $attachment_id ) ) {
			$args['file'] = $file_url;
		} else {
			// We should have the file in args at this point, but if the transient was set, it will be defaulting here.
			if ( empty( $args['file'] ) ) {
				if ( wp_attachment_is_image( $attachment_id ) ) {
					$get_path_func = $use_original && function_exists( 'wp_get_original_image_path' ) ? 'wp_get_original_image_path' : 'get_attached_file';
					$args['file']  = call_user_func( $get_path_func, $attachment_id );
				} else {
					$args['file'] = get_attached_file( $attachment_id );
				}
			}
			// Headers indicate chunked upload.
			if ( empty( $headers ) && file_exists( $args['file'] ) ) {
				$size = filesize( $args['file'] );
				if ( 'video' === $resource || $size > 100000000 ) {
					return $this->upload_large( $attachment_id, $args );
				}
			}
			if ( false !== strpos( $args['file'], 'vip://' ) ) {
				$args['file'] = $this->create_local_copy( $args['file'] );
				if ( is_wp_error( $args['file'] ) ) {
					return $args['file'];
				}
				$tempfile = true;
			}
			// Attach File.
			if ( function_exists( 'curl_file_create' ) ) {
				$file         = $args['file'];
				$args['file'] = curl_file_create( $file ); // phpcs:ignore
				$args['file']->setPostFilename( $file );
			} else {
				$args['file'] = '@' . $args['file'];
			}
		}

		$call_args = array(
			'headers' => $headers,
			'body'    => $args,
		);

		/**
		 * Filter Cloudinary upload args.
		 *
		 * @hook cloudinary_upload_args
		 * @since 3.0.1
		 *
		 * @param $call_args     {array} The default args.
		 * @param $attachment_id {int}   The attachment ID.
		 */
		$call_args = apply_filters( 'cloudinary_upload_args', $call_args, $attachment_id );

		$result = $this->call( $url, $call_args, 'post' );
		// Hook in flag to allow for non accessible URLS.
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
			$code  = $result->get_error_code();
			/**
			 * If there's an error and the file is a URL in the error message,
			 * it's likely due to CURL or the location does not support URL file attachments.
			 * In this case, we'll flag and disable it and try again with a local file.
			 */
			if ( 404 !== $code && empty( $disable_https_fetch ) && false !== strpos( urldecode( $error ), $args['file'] ) ) {
				// URLS are not remotely available, try again as a file.
				set_transient( '_cld_disable_http_upload', true, DAY_IN_SECONDS );
				// Remove URL file.
				unset( $args['file'] );

				return $this->upload( $attachment_id, $args );
			}
		}
		if ( true === $tempfile && is_string( $args['file'] ) ) {
			unlink( $args['file'] ); //phpcs:ignore
		}

		/**
		 * Action after uploaded asset.
		 *
		 * @hook  cloudinary_uploaded_asset
		 * @since 3.0.1
		 *
		 * @param $attachment_id {int}            The attachment ID.
		 * @param $result        {array|WP_Error} The upload result.
		 */
		do_action( 'cloudinary_uploaded_asset', $attachment_id, $result );

		return $result;
	}

	/**
	 * Upload an cache item.
	 *
	 * @param array $args The upload parameters.
	 *
	 * @return array $the url to the cached item.
	 */
	public function upload_cache( $args ) {
		$call_args = array(
			'headers' => array(),
			'body'    => $args,
		);
		$url       = $this->url( 'auto', 'upload', true );

		return $this->call( $url, $call_args, 'post' );
	}

	/**
	 * Create a local copy of the file if stored remotely in VIP.
	 *
	 * @param string $file File name to copy.
	 *
	 * @return string|\WP_Error
	 */
	public function create_local_copy( $file ) {
		$file_copy = wp_tempnam( wp_basename( $file ) );
		$content   = file_get_contents( $file ); //phpcs:ignore

		if ( file_put_contents( $file_copy, $content ) ) { //phpcs:ignore
			$file = $file_copy;
		} else {
			return new \WP_Error( 'upload_fail', __( 'Could not get VIP file content', 'cloudinary' ) );
		}

		return $file;
	}

	/**
	 * Expicit update of an asset.
	 *
	 * @param array $args Array of options to update.
	 *
	 * @return array|\WP_Error
	 */
	public function explicit( $args ) {

		$url  = $this->url( 'image', 'explicit', true );
		$args = $this->clean_args( $args );

		return $this->call( $url, array( 'body' => $args ), 'post' );
	}

	/**
	 * Destroy an asset.
	 *
	 * @param string $type    The resource type to destroy.
	 * @param array  $options Array of options.
	 *
	 * @return array|\WP_Error
	 */
	public function destroy( $type, $options ) {

		$url = $this->url( $type, 'destroy', true );

		return $this->call( $url, array( 'body' => $options ), 'post' );
	}

	/**
	 * Rename an asset.
	 *
	 * @param string $type    The resource type to rename.
	 * @param array  $options Array of options.
	 *
	 * @return array|\WP_Error
	 */
	public function rename( $type, $options ) {

		$url = $this->url( $type, 'rename', true );

		return $this->call( $url, array( 'body' => $options ), 'post' );
	}

	/**
	 * Context update of an asset.
	 *
	 * @param array $args Array of options to update.
	 *
	 * @return array|\WP_Error
	 */
	public function context( $args ) {

		$url     = $this->url( $args['resource_type'], 'context', true );
		$options = array(
			'public_ids' => $args['public_id'],
			'context'    => $args['context'],
			'command'    => 'add',
		);
		$options = $this->clean_args( $options );

		return $this->call( $url, array( 'body' => $options ), 'post' );
	}

	/**
	 * Clean the args before sending to endpoint.
	 *
	 * @param array $args Array of args to clean.
	 *
	 * @return array
	 */
	public function clean_args( $args ) {

		return array_map(
			function ( $value ) {
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				if ( is_bool( $value ) ) {
					$value = true === $value ? '1' : '0';
				}

				return $value;
			},
			$args
		);
	}

	/**
	 * General Call based on tag.
	 *
	 * @param string $name Name of method to call.
	 * @param array  $args Array of parameters to pass to call.
	 *
	 * @return array|\WP_Error
	 */
	public function __call( $name, $args ) {
		$function = null;
		if ( ! empty( $args[0] ) ) {
			$function = $args[0];
		}
		$url       = $this->url( $name, $function, true );
		$method    = 'get';
		$send_args = array( 'body' => $args );
		if ( ! empty( $args[1] ) ) {
			$method = $args[1];
		}
		if ( ! empty( $args[2] ) ) {
			$send_args['body'] = $args[2];
		}

		return $this->call( $url, $send_args, $method );
	}

	/**
	 * Sign a request
	 *
	 * @param array $args Array of parameters to sign.
	 *
	 * @return array|\WP_Error
	 */
	public function sign( $args ) {

		// Sort parameters.
		ksort( $args );
		$args    = array_map(
			function ( $value, $key ) {
				$remove = array( 'file', 'resource_type', 'api_key' );
				if ( in_array( $key, $remove, true ) || '' === $value ) {
					return null;
				}
				if ( is_array( $value ) ) {
					$value = implode( ',', array_values( $value ) );
				}

				return $key . '=' . $value;
			},
			$args,
			array_keys( $args )
		);
		$to_sign = array_filter( $args );
		$string  = implode( '&', $to_sign );

		return sha1( $string . $this->credentials['api_secret'] );
	}

	/**
	 * Set the POSTFIELDS to the correct array type, not the string based.
	 *
	 * @param \Requests_Transport_cURL $handle  The transport handle to set.
	 * @param array                    $request The request array.
	 * @param string                   $url     The url to send to.
	 */
	public function set_data( $handle, $request, $url ) {
		// Ensure that this request is in fact ours.
		if ( $this->pending_url === $url ) {
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $request['body'] ); // phpcs:ignore
			$this->pending_url = null;
		}
	}

	/**
	 * Get Cloudinary upload prefix.
	 *
	 * @return string
	 */
	public function get_upload_prefix() {
		static $upload_prefix;

		if ( is_null( $upload_prefix ) ) {
			// Defaults to first hardcoded qualified domain.
			$upload_prefix = reset( self::$qualified_upload_prefixes );

			// Maybe use constant.
			if ( self::is_qualified_upload_prefix( CLOUDINARY_ENDPOINTS_API ) ) {
				$upload_prefix = self::get_hostname( CLOUDINARY_ENDPOINTS_API );
			}

			// Maybe use query argument.
			if (
				! empty( $this->credentials['upload_prefix'] )
				&& self::is_qualified_upload_prefix( $this->credentials['upload_prefix'] )
			) {
				$upload_prefix = self::get_hostname( $this->credentials['upload_prefix'] );
			}
		}

		return $upload_prefix;
	}

	/**
	 * Get the Cloudinary public_id.
	 *
	 * @param int         $attachment_id      The attachment ID.
	 * @param array       $args               The args.
	 * @param null|string $original_public_id The original public ID.
	 *
	 * @return string
	 */
	public function get_public_id( $attachment_id, $args = array(), $original_public_id = null ) {

		$relationship = Relationship::get_relationship( $attachment_id );
		$public_id    = null;

		if ( $relationship instanceof Relationship ) {
			$public_id = $relationship->public_id;
		}

		// On cases like the initial sync, we might not have a relationship, so we need to trust to requested public_id.
		if ( empty( $public_id ) ) {
			$public_id = $original_public_id;
		}

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

		if (
			$public_id
			&& ! $bypass_seo_url
			&& (
				// Get the SEO `public_id` for images with `upload` delivery.
				(
					! empty( $args['resource_type'] ) && 'image' === $args['resource_type']
					&& ! empty( $args['delivery'] ) && 'upload' === $args['delivery']
				)
				// Get the SEO `public_id` for PDFs as they are regarded as images.
				|| (
					! empty( $args['resource_type'] ) && 'image' === $args['resource_type']
					&& 'application' === $relationship->asset_type
				)
			)
		) {

			$parts    = explode( '/', $public_id );
			$filename = end( $parts );

			/**
			 * Filter the SEO public ID.
			 *
			 * @hook   cloudinary_seo_public_id
			 * @since  3.1.5
			 *
			 * @param $sufix          {string}       The public_id suffix.
			 * @param $relationship  {Relationship} The relationship.
			 * @param $attachment_id {int}          The attachment ID.
			 *
			 * @return {string}
			 */
			$suffix = apply_filters( 'cloudinary_seo_public_id', "{$filename}.{$relationship->format}", $relationship, $attachment_id );

			$public_id .= "/{$suffix}";
		}

		if ( 'video' === $relationship->asset_type ) {
			$public_id .= ".{$relationship->format}";
		}

		return $public_id;
	}

	/**
	 * Check if a string is a qualified `upload_prefix`.
	 *
	 * @param string $upload_prefix Upload prefix to check.
	 *
	 * @return bool
	 */
	protected static function is_qualified_upload_prefix( $upload_prefix ) {
		$hostname = self::get_hostname( $upload_prefix );

		return in_array( $hostname, self::$qualified_upload_prefixes, true );
	}

	/**
	 * Get the hostname from a string.
	 *
	 * @param string $upload_prefix Upload prefix to check.
	 *
	 * @return string
	 */
	protected static function get_hostname( $upload_prefix ) {
		$hostname = $upload_prefix;

		if ( filter_var( $upload_prefix, FILTER_VALIDATE_URL ) ) {
			$hostname = wp_parse_url( $upload_prefix, PHP_URL_HOST );
		}

		return $hostname;
	}

	/**
	 * Calls the API request.
	 *
	 * @param string $url    The url to call.
	 * @param array  $args   The optional arguments to send.
	 * @param string $method The call HTTP method.
	 *
	 * @return array|\WP_Error
	 */
	private function call( $url, $args = array(), $method = 'get' ) {
		$args['method']             = strtoupper( $method );
		$args['user-agent']         = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . ' (' . $this->plugin_version . ')';
		$args['headers']['referer'] = Utils::site_url();
		if ( 'GET' === $args['method'] ) {
			$url = 'https://' . $this->credentials['api_key'] . ':' . $this->credentials['api_secret'] . '@' . $url;
		} else {
			$url                       = 'https://' . $url;
			$args['body']['api_key']   = $this->credentials['api_key'];
			$args['body']['timestamp'] = time();
			// Sign request.
			$args['body']['signature'] = $this->sign( $args['body'] );
			ksort( $args['body'] );
			// Fix the data to not be a flattend fields string.
			add_action( 'http_api_curl', array( $this, 'set_data' ), 10, 3 );
			// Add url to list to allow it to be fixed.
			$this->pending_url = $url;
		}

		// Set a long-ish timeout since uploads can be 20mb+.
		$args['timeout'] = 90; // phpcs:ignore

		// Adjust timeout for additional eagers if image_freeform or video_freeform is set.
		if ( ! empty( $args['body']['resource_type'] ) ) {
			$freeform = $this->media->get_settings()->get_value( $args['body']['resource_type'] . '_freeform' );
			if ( ! empty( $freeform ) ) {
				$timeout_multiplier = explode( '/', $freeform );
				$args['timeout']   += 60 * count( $timeout_multiplier ); // phpcs:ignore
			}
		}

		$request = wp_remote_request( $url, $args );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$body   = wp_remote_retrieve_body( $request );
		$result = json_decode( $body, ARRAY_A );
		if ( empty( $result ) && ! empty( $body ) ) {
			return $body; // not json.
		}
		if ( ! empty( $result['error'] ) && ! empty( $result['error']['message'] ) ) {
			return new \WP_Error( $request['response']['code'], $result['error']['message'] );
		}

		return $result;
	}
}
