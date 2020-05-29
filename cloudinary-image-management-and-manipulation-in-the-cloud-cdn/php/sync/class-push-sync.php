<?php
/**
 * Push Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Connect\Api;
use Cloudinary\Sync;

/**
 * Class Push_Sync
 *
 * Push media library to Cloudinary.
 */
class Push_Sync {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds a list of already built options array, since it can be heavy action.
	 *
	 * @var array
	 */
	private $upload_options = array();

	/**
	 * Hold the list or available sync types.
	 *
	 * @var  array
	 */
	private $sync_types;

	/**
	 * Push_Sync constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;

		// Define the sync types and their option keys.
		$sync_types       = array(
			'file'        => 'upload',
			'breakpoints' => 'explicit',
			'options'     => 'context',
			'folder'      => 'upload',
			'cloud_name'  => 'upload',
		);
		$this->sync_types = apply_filters( 'cloudinary_sync_types', $sync_types );

		$this->register_hooks();
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['attachments'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_get_queue_status' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		$endpoints['sync'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_start_sync' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		$endpoints['process'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_push_attachments' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_verify_nonce' ),
		);

		return $endpoints;
	}

	/**
	 * General nonce based auth.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function rest_verify_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_param( 'nonce' );

		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Admin permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public function rest_can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get status of the current queue via REST API.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_queue_status() {

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->plugin->components['sync']->managers['queue']->get_queue_status(),
			)
		);
	}

	/**
	 * Starts a sync backbround process.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed|\WP_REST_Response
	 */
	public function rest_start_sync( \WP_REST_Request $request ) {

		$stop  = $request->get_param( 'stop' );
		$queue = $this->plugin->components['sync']->managers['queue']->get_queue();
		if ( empty( $queue['pending'] ) || ! empty( $stop ) ) {
			$this->plugin->components['sync']->managers['queue']->stop_queue();

			return $this->rest_get_queue_status(); // Nothing to sync.
		}
		$this->plugin->components['sync']->managers['queue']->start_queue();

		return $this->call_thread();

	}

	/**
	 * Pushes attachments via WP REST API.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed|\WP_REST_Response
	 */
	public function rest_push_attachments( \WP_REST_Request $request ) {
		// Get thread ID.
		$last_id     = $request->get_param( 'last_id' );
		$last_result = $request->get_param( 'last_result' );

		// Get attachment_id in case this is a single direct request to upload.
		$attachments = $request->get_param( 'attachment_ids' );

		// If not a single request, process based on queue.
		if ( ! empty( $attachments ) ) {

			// If a single specified ID, push and return response.
			$ids  = array_map( 'intval', $attachments );
			$stat = $this->push_attachments( $ids );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $stat,
				)
			);
		}

		// Process queue based.
		if ( ! empty( $last_id ) && ! empty( $last_result ) ) {
			$this->plugin->components['sync']->managers['queue']->mark( $last_id, $last_result );
		}

		if ( ! $this->plugin->components['sync']->managers['queue']->is_running() ) { // Check it wasn't stopped.
			return $this->rest_get_queue_status();
		}
		$post_id = $this->plugin->components['sync']->managers['queue']->get_post();
		// No post, end of queue.
		if ( empty( $post_id ) ) {
			$this->plugin->components['sync']->managers['queue']->stop_queue();

			return $this->rest_get_queue_status();
		}

		// Check if there is a Cloudinary ID in case this was synced on-demand before being processed by the queue.
		add_filter( 'cloudinary_on_demand_sync_enabled', '__return_false' ); // Disable the on-demand sync since we want the status.
		add_filter( 'cloudinary_id', '__return_false' ); // Disable the on-demand sync since we want the status.
		if ( false === $this->plugin->components['media']->cloudinary_id( $post_id ) ) {
			$stat = $this->push_attachments( array( $post_id ) );
			if ( ! empty( $stat['processed'] ) ) {
				$result = 'done';
			} else {
				$result = 'error';
			}
		} else {
			/**
			 * If a Cloudinary ID was found, set as done.
			 * In the case of an upgrade, This would have been pushed to a background conversion.
			 */
			$result = 'done';
		}

		return $this->call_thread( $post_id, $result );

	}

	/**
	 * Start a new call.
	 *
	 * @param int    $last_id     The last ID that was processed.
	 * @param string $last_result The last result.
	 *
	 * @return mixed|\WP_REST_Response.
	 */
	private function call_thread( $last_id = null, $last_result = null ) {

		// Add last results.
		$params = array();
		if ( null !== $last_id && null !== $last_result ) {
			$params['last_id']     = $last_id;
			$params['last_result'] = $last_result;
		}
		// Setup background call to cintinue the queue.
		$this->plugin->components['api']->background_request( 'process', $params );

		return $this->rest_get_queue_status();
	}

	/**
	 * @param int|\WP_Post $attachment The attachment post or id to get resource type for.
	 *
	 * @return string
	 */
	public function get_resource_type( $attachment ) {
		if ( is_numeric( $attachment ) ) {
			$attachment = get_post( $attachment );
		}

		// Deal with compound mime types.
		$type = explode( '/', $attachment->post_mime_type );

		return array_shift( $type );
	}

	/**
	 * Get the type of sync the attachment should do.
	 *
	 * @param int|\WP_Post $attachment The attachment to get the required sync type for.
	 *
	 * @return string|\WP_Error
	 */
	private function get_sync_type( $attachment ) {
		if ( is_numeric( $attachment ) ) {
			$attachment = get_post( $attachment );
		}

		$type = 'upload';
		// Check for explicit (has public_id, but no breakpoints).
		$attachment_signature = $attachment->{Sync::META_KEYS['signature']};
		if ( empty( $attachment_signature ) ) {
			if ( ! empty( $attachment->{Sync::META_KEYS['public_id']} ) ) {
				// Has a public id but no signature, explicit update to complete download.
				$type = 'explicit';
			}
			// fallback to upload.
		} else {
			// Has signature. Compare and find if different.
			$required_signature = $this->plugin->components['sync']->generate_signature( $attachment->ID );
			foreach ( $required_signature as $key => $signature ) {
				if ( ( ! isset( $attachment_signature[ $key ] ) || $attachment_signature[ $key ] !== $signature ) && isset( $this->sync_types[ $key ] ) ) {
					return $this->sync_types[ $key ];
				}
			}
			// If it gets here, the signature is identical, so set as no update.
			$type = new \WP_Error( 'attachment_synced', __( 'Attachment is already fully synced.', 'cloudinary' ) );
		}

		// Return the type.
		return $type;
	}

	/**
	 * Prepare an attachment for upload.
	 *
	 * @param int|\WP_Post $post      The attachment to prepare.
	 * @param bool         $down_sync Flag to determine if a missing file starts a downsync.
	 *
	 * @return array|\WP_Error
	 */
	public function prepare_upload( $post, $down_sync = false ) {

		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( empty( $post ) ) {
			return new \WP_Error( 'attachment_post_get', __( 'Could not retrieve the attachment post.', 'cloudinary' ) );
		}

		if ( empty( $this->upload_options[ $post->ID ] ) ) {

			if ( 'attachment' !== $post->post_type ) {
				return new \WP_Error( 'attachment_post_expected', __( 'An attachment post was expected.', 'cloudinary' ) );
			}

			// First check if this has a file and it can be uploaded.
			$file = get_attached_file( $post->ID );
			if ( empty( $file ) ) {
				return new \WP_Error( 'attachment_no_file', __( 'Attachment did not have a file.', 'cloudinary' ) );
			}

			if ( ! file_exists( $file ) ) {
				$file_size = 0;
				// May be an old upload type.
				$src = get_post_meta( $post->ID, '_wp_attached_file', true );
				if ( $this->plugin->components['media']->is_cloudinary_url( $src ) ) {
					// Download first maybe.
					if ( true === $down_sync ) {
						$download = $this->plugin->components['sync']->managers['download']->down_sync( $post->ID );
						if ( is_wp_error( $download ) ) {
							update_post_meta( $post->ID, Sync::META_KEYS['sync_error'], $download->get_error_message() );

							return new \WP_Error( 'attachment_download_error', $download->get_error_message() );
						}
						$file      = get_attached_file( $post->ID );
						$file_size = filesize( $file );
					}
				}
			} else {
				$file_size = filesize( $file );
			}

			$resource_type = $this->get_resource_type( $post );
			$max_size      = ( 'image' === $resource_type ? 'max_image_size' : 'max_video_size' );

			if ( $file_size > $this->plugin->components['connect']->usage[ $max_size ] ) {
				$max_size_hr = size_format( $this->plugin->components['connect']->usage[ $max_size ] );
				// translators: variable is file size.
				$error = sprintf( __( 'File size exceeds the maximum of %s. This media asset will be served from WordPress.', 'cloudinary' ), $max_size_hr );
				$this->plugin->components['media']->delete_post_meta( $post->ID, Sync::META_KEYS['pending'] ); // Remove Flag.

				return new \WP_Error( 'upload_error', $error );
			}

			// If it's got a public ID, then this is an explicit update.
			$public_id  = $post->{Sync::META_KEYS['public_id']}; // use the __get method on the \WP_Post to get post_meta.
			$dirs       = wp_get_upload_dir();
			$cld_folder = false;
			$folder     = trailingslashit( $dirs['cloudinary_folder'] );
			if ( '/' === $dirs['cloudinary_folder'] ) {
				$folder = '';
			}
			if ( empty( $public_id ) ) {
				$file_info = pathinfo( $file );
				$public_id = $folder . $file_info['filename'];
			}

			// Check if cloudinary folder is in public_id.
			$parts = explode( '/', $public_id );
			if ( untrailingslashit( $dirs['cloudinary_folder'] ) === $parts[0] ) {
				$cld_folder = $dirs['cloudinary_folder'];
			}


			// Prepare upload options.
			$options = array(
				'unique_filename' => false,
				'resource_type'   => $resource_type,
				'public_id'       => $public_id,
				'context'         => array(
					'caption' => esc_attr( $post->post_title ),
					'alt'     => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				),
			);

			// If in cloudinary folder, add the posts id to context.
			if ( false !== $cld_folder ) {
				$options['context']['wp_id'] = $post->ID;
			}

			// Add breakpoints if we have an image.
			$breakpoints = false;
			if ( 'image' === $resource_type ) {
				$meta = wp_get_attachment_metadata( $post->ID );
				// Get meta image size if non exists.
				if ( empty( $meta ) ) {
					$meta          = array();
					$imagesize     = getimagesize( $file );
					$meta['width'] = $imagesize[0];
				}
				$max_width = $this->plugin->components['media']->get_max_width();
				// Add breakpoints request options.
				if ( ! empty( $this->plugin->config['settings']['global_transformations']['enable_breakpoints'] ) ) {
					$options['responsive_breakpoints'] = array(
						'create_derived' => true,
						'bytes_step'     => $this->plugin->config['settings']['global_transformations']['bytes_step'],
						'max_images'     => $this->plugin->config['settings']['global_transformations']['breakpoints'],
						'max_width'      => $meta['width'] < $max_width ? $meta['width'] : $max_width,
						'min_width'      => $this->plugin->config['settings']['global_transformations']['min_width'],
					);
					$transformations                   = $this->plugin->components['media']->get_transformation_from_meta( $post->ID );
					if ( ! empty( $transformations ) ) {
						$options['responsive_breakpoints']['transformation'] = Api::generate_transformation_string( $transformations );
					}
					$breakpoints = array(
						'public_id'              => $options['public_id'],
						'type'                   => 'upload',
						'responsive_breakpoints' => $options['responsive_breakpoints'],
						'context'                => $options['context'],
					);
				}
			}

			/**
			 * Filter the options to allow other plugins to add requested options for uploading.
			 *
			 * @param array            $options  The options array.
			 * @param \WP_Post         $post     The attachment post.
			 * @param \Cloudinary\Sync $instance The sync object instance.
			 *
			 * @return array
			 */
			$options = apply_filters( 'cloudinary_upload_options', $options, $post, $this );

			// Combine context option to string.
			$options['context'] = http_build_query( $options['context'], null, '|' );
			if ( ! empty( $breakpoints ) && is_array( $breakpoints['context'] ) ) {
				$breakpoints['context'] = http_build_query( $breakpoints['context'], null, '|' );
			}

			$return = array(
				'file'        => $file,
				'folder'      => $cld_folder,
				'breakpoints' => array(),
				'options'     => $options,
			);
			if ( ! empty( $breakpoints ) ) {
				$return['breakpoints'] = $breakpoints;
			}
			$this->upload_options[ $post->ID ] = $return;

		}

		return $this->upload_options[ $post->ID ];
	}

	/**
	 * Push \WP_Post items in array to Cloudinary.
	 *
	 * Provides $large param for supporting video uploads. If post_mime_type is 'video'
	 * then large will be assumed.
	 *
	 * @see https://cloudinary.com/documentation/php_image_and_video_upload#php_image_upload
	 * @see https://cloudinary.com/documentation/php_image_and_video_upload#php_video_upload
	 *
	 * @param mixed $attachments Array of \WP_Post (hopefully with `post_type='attachment'`.
	 *
	 * @return array|\WP_Error
	 */
	public function push_attachments( $attachments ) {

		/**
		 * Holds the file names for successful and failed attachments.
		 *
		 * Also includes `start` time, `end` time and `duration`.
		 *
		 * @var array $stats
		 */
		$stats = array(
			'success'   => array(),
			'fail'      => array(),
			'start'     => time(),
			'total'     => count( $attachments ),
			'processed' => 0,
		);

		// Go over each attachment.
		foreach ( $attachments as $attachment ) {
			$attachment = get_post( $attachment );
			$upload     = $this->prepare_upload( $attachment->ID, true );

			// Filter out any attachments with problematic options.
			if ( is_wp_error( $upload ) ) {

				/**
				 * Attachment is an error.
				 *
				 * @var \WP_Error $upload
				 */
				$stats['fail'][] = $upload->get_error_message();
				continue;
			}


			// Determine Sync type needed.
			$sync_type = $this->get_sync_type( $attachment );

			// Skip if sync type is an error.
			if ( is_wp_error( $sync_type ) ) {
				/**
				 * Sync type is an error.
				 *
				 * @var \WP_Error $sync_type
				 */
				$stats['fail'][] = $sync_type->get_error_message();
				continue;
			}

			// 100MB restriction on normal upload.
			$do_large = 'video' === $upload['options']['resource_type'] ? true : false;

			if ( ! $do_large ) {

				if ( 'explicit' === $sync_type ) {
					// Explicit update.
					$args = array(
						'public_id' => $upload['options']['public_id'],
						'type'      => 'upload',
					);
					if ( ! empty( $upload['options']['responsive_breakpoints'] ) ) {
						$args['responsive_breakpoints'] = $upload['options']['responsive_breakpoints'];
					}
					if ( ! empty( $upload['options']['context'] ) ) {
						$args['context'] = $upload['options']['context'];
					}
					$result = $this->plugin->components['connect']->api->explicit( $args );
				} else {
					// dynamic sync type..
					$result = $this->plugin->components['connect']->api->{$sync_type}( $upload['file'], $upload['options'] );
				}
			} else {
				// Large Upload.
				$result = $this->plugin->components['connect']->api->upload_large( $upload['file'], $upload['options'] );
			}

			// Exceptions are handled by the Upload wrapper class and returned as \WP_Error, so check for it.
			if ( is_wp_error( $result ) ) {
				$error           = $result->get_error_message();
				$stats['fail'][] = $error;
				$this->plugin->components['media']->update_post_meta( $attachment->ID, Sync::META_KEYS['sync_error'], $error );
				continue;
			}

			// Successful upload, so lets update meta.
			if ( is_array( $result ) && ( array_key_exists( 'public_id', $result ) || array_key_exists( 'public_ids', $result ) ) ) {
				$meta_data = array(
					Sync::META_KEYS['signature'] => $this->plugin->components['sync']->generate_signature( $attachment->ID ),
				);

				// We only have a version if updating a file or an upload.
				if ( ! empty( $result['version'] ) ) {
					$meta_data[ Sync::META_KEYS['version'] ] = $result['version'];
				}
				$this->plugin->components['media']->delete_post_meta( $attachment->ID, Sync::META_KEYS['pending'] );
				$this->plugin->components['media']->delete_post_meta( $attachment->ID, Sync::META_KEYS['sync_error'], false );
				if ( ! empty( $this->plugin->config['settings']['global_transformations']['enable_breakpoints'] ) ) {
					if ( ! empty( $result['responsive_breakpoints'] ) ) { // Images only.
						$meta_data[ Sync::META_KEYS['breakpoints'] ] = $result['responsive_breakpoints'][0]['breakpoints'];
					} elseif ( 'image' === $upload['options']['resource_type'] && 'explicit' === $sync_type ) {
						// Remove records of breakpoints.
						delete_post_meta( $attachment->ID, Sync::META_KEYS['breakpoints'] );
					}
					if ( empty( $upload['options']['responsive_breakpoints']['transformation'] ) ) {
						// a transformation breakpoints only ever happens on a down sync.
						$sync_key                     = md5( $upload['options']['public_id'] );
						$meta_data[ $sync_key ]       = true;
						$meta_data[ '_' . $sync_key ] = true;
						update_post_meta( $attachment->ID, $sync_key, true );
						// Add base ID.
						update_post_meta( $attachment->ID, '_' . $sync_key, true );
					}
				}
				$stats['success'][]                    = $attachment->post_title;
				$meta                                  = wp_get_attachment_metadata( $attachment->ID, true );
				$meta[ Sync::META_KEYS['cloudinary'] ] = $meta_data;
				wp_update_attachment_metadata( $attachment->ID, $meta );
				$this->plugin->components['media']->update_post_meta( $attachment->ID, Sync::META_KEYS['public_id'], $upload['options']['public_id'] );
				// Search and update link references in content.
				$content_search = new \WP_Query( array( 's' => 'wp-image-' . $attachment->ID, 'fields' => 'ids', 'posts_per_page' => 100 ) );
				if ( ! empty( $content_search->found_posts ) ) {
					$content_posts = array_unique( $content_search->get_posts() ); // ensure post only gets updated once.
					foreach ( $content_posts as $content_id ) {
						wp_update_post( array( 'ID' => $content_id ) ); // Trigger an update, internal filters will filter out remote URLS.
					}
				}
			}

			$stats['processed'] += 1;
		}

		$stats['end']      = time();
		$stats['duration'] = (int) $stats['end'] - (int) $stats['start'];

		return $stats;
	}
}
