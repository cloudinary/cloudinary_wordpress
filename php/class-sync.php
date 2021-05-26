<?php
/**
 * Sync manages all of the sync components for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;
use Cloudinary\Component\Setup;
use Cloudinary\Settings\Setting;
use Cloudinary\Sync\Delete_Sync;
use Cloudinary\Sync\Download_Sync;
use Cloudinary\Sync\Push_Sync;
use Cloudinary\Sync\Sync_Queue;
use Cloudinary\Sync\Upload_Sync;
use WP_Error;

/**
 * Class Sync
 */
class Sync implements Setup, Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Contains all the different sync components.
	 *
	 * @var Delete_Sync[]|Push_Sync[]|Upload_Sync[]
	 */
	public $managers;

	/**
	 * Contains the sync base structure and callbacks.
	 *
	 * @var array
	 */
	protected $sync_base_struct;

	/**
	 * Contains the sync types and  callbacks.
	 *
	 * @var array
	 */
	protected $sync_types;

	/**
	 * Holds a list of unsynced images to push on end.
	 *
	 * @var array
	 */
	private $to_sync = array();

	/**
	 * Holds the settings stlug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'sync_media';

	/**
	 * Holds the sync settings object.
	 *
	 * @var Setting
	 */
	public $settings;

	/**
	 * Holds the meta keys for sync meta to maintain consistency.
	 */
	const META_KEYS = array(
		'pending'        => '_cloudinary_pending',
		'signature'      => '_sync_signature',
		'version'        => '_cloudinary_version',
		'plugin_version' => '_plugin_version',
		'breakpoints'    => '_cloudinary_breakpoints',
		'delivery'       => '_cloudinary_delivery',
		'public_id'      => '_public_id',
		'transformation' => '_transformations',
		'sync_error'     => '_sync_error',
		'cloudinary'     => '_cloudinary_v2',
		'folder_sync'    => '_folder_sync',
		'suffix'         => '_suffix',
		'syncing'        => '_cloudinary_syncing',
		'downloading'    => '_cloudinary_downloading',
		'process_log'    => '_process_log',
		'storage'        => '_cloudinary_storage',
		'queued'         => '_cloudinary_sync_queued',
	);

	/**
	 * Push_Sync constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin               = $plugin;
		$this->managers['push']     = new Push_Sync( $this->plugin );
		$this->managers['upload']   = new Upload_Sync( $this->plugin );
		$this->managers['download'] = new Download_Sync( $this->plugin );
		$this->managers['delete']   = new Delete_Sync( $this->plugin );
		$this->managers['queue']    = new Sync_Queue( $this->plugin );
	}

	/**
	 * Setup assets/scripts.
	 */
	public function enqueue_assets() {
		if ( $this->plugin->settings->get_param( 'connected' ) ) {
			$data = array(
				'restUrl' => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			);
			wp_add_inline_script( 'cloudinary', 'var cloudinaryApi = ' . wp_json_encode( $data ), 'before' );
		}
	}

	/**
	 * Register Assets.
	 */
	public function register_assets() {
		if ( $this->plugin->settings->get_param( 'connected' ) ) {
			// Setup the sync_base_structure.
			$this->setup_sync_base_struct();
			// Setup sync types.
			$this->setup_sync_types();
		}
	}

	/**
	 * Is the component Active.
	 */
	public function is_active() {
		return $this->settings && $this->settings->has_param( 'is_active' );
	}

	/**
	 * Checks if an asset has been synced and up to date.
	 *
	 * @param int $attachment_id The attachment id to check.
	 *
	 * @return bool
	 */
	public function been_synced( $attachment_id ) {

		$public_id = $this->managers['media']->has_public_id( $attachment_id );
		$meta      = wp_get_attachment_metadata( $attachment_id );

		return ! empty( $public_id ) || ! empty( $meta['cloudinary'] ); // From v1.
	}

	/**
	 * Checks if an asset is synced and up to date.
	 *
	 * @param int $post_id The post id to check.
	 *
	 * @return bool
	 */
	public function is_synced( $post_id ) {
		$expecting = $this->generate_signature( $post_id );

		if ( ! is_wp_error( $expecting ) ) {
			$signature = $this->get_signature( $post_id );
			// Sort to align orders for comparison.
			ksort( $signature );
			ksort( $expecting );
			if ( ! empty( $signature ) && ! empty( $expecting ) && $expecting === $signature ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if sync type is required for rendering a Cloudinary URL.
	 *
	 * @param string $type          The type to check.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_required( $type, $attachment_id ) {
		$return = false;
		if ( isset( $this->sync_base_struct[ $type ]['required'] ) ) {
			if ( is_callable( $this->sync_base_struct[ $type ]['required'] ) ) {
				$return = call_user_func( $this->sync_base_struct[ $type ]['required'], $attachment_id );
			} else {
				$return = $this->sync_base_struct[ $type ]['required'];
			}
		}

		return $return;
	}

	/**
	 * Generate a signature based on whats required for a full sync.
	 *
	 * @param int  $attachment_id The Attachment id to generate a signature for.
	 * @param bool $cache         Flag to specify if a cached signature is to be used or build a new one.
	 *
	 * @return string|bool
	 */
	public function generate_signature( $attachment_id, $cache = true ) {
		static $signatures = array(); // cache signatures.
		if ( ! empty( $signatures[ $attachment_id ] ) && true === $cache ) {
			$return = $signatures[ $attachment_id ];
		} else {
			$return = $this->sync_base( $attachment_id );
			// Add to signature cache.
			$signatures[ $attachment_id ] = $return;
		}

		return $return;
	}

	/**
	 * Check if an asset can be synced.
	 *
	 * @param int    $attachment_id The attachment ID to check if it  can be synced.
	 * @param string $type          The type of sync to attempt.
	 *
	 * @return bool
	 */
	public function can_sync( $attachment_id, $type = 'file' ) {

		$can = $this->is_auto_sync_enabled();

		if ( $this->is_pending( $attachment_id ) ) {
			$can = false;
		} elseif ( $this->been_synced( $attachment_id ) ) {
			$can = true;
		}

		if ( ! $this->managers['media']->is_local_media( $attachment_id ) ) {
			$can = false;
		}

		// Can sync only syncable delivery types.
		if ( ! $this->is_syncable( $attachment_id ) ) {
			$can = false;
		}

		/**
		 * Filter to allow changing if an asset is allowed to be synced.
		 * Return a WP Error with reason why it can't be synced.
		 *
		 * @param int $attachment_id The attachment post ID.
		 *
		 * @return bool|\WP_Error
		 */
		return apply_filters( 'cloudinary_can_sync_asset', $can, $attachment_id, $type );
	}

	/**
	 * Get the last version this asset was synced with.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function get_sync_version( $attachment_id ) {
		$version = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['plugin_version'], true );

		return $version . '-' . $this->plugin->version;
	}

	/**
	 * Get the current sync signature of an asset.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $cached        Flag to specify if a cached signature is to be used or build a new one.
	 *
	 * @return array
	 */
	public function get_signature( $attachment_id, $cached = true ) {
		static $signatures = array(); // Cache signatures already fetched.

		$return = array();
		if ( ! empty( $signatures[ $attachment_id ] ) && true === $cached ) {
			$return = $signatures[ $attachment_id ];
		} else {
			$signature = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['signature'], true );
			if ( empty( $signature ) ) {
				$signature = array();
			}

			// Remove any old or outdated signature items. against the expected.
			$signature                    = array_intersect_key( $signature, $this->sync_types );
			$signatures[ $attachment_id ] = $return;
			$return                       = wp_parse_args( $signature, $this->sync_types );
		}

		/**
		 * Filter the get signature of the asset.
		 *
		 * @hook   cloudinary_get_signature
		 *
		 * @param $return        {array} The attachment signature.
		 * @param $attachment_id {int}   The attachment ID.
		 *
		 * @return {array}
		 */
		$return = apply_filters( 'cloudinary_get_signature', $return, $attachment_id );

		return $return;
	}

	/**
	 * Generate a new Public ID for an asset.
	 *
	 * @param int $attachment_id The attachment ID for the new public ID.
	 *
	 * @return string|null
	 */
	public function generate_public_id( $attachment_id ) {

		$cld_folder = $this->managers['media']->get_cloudinary_folder();
		if ( function_exists( 'wp_get_original_image_path' ) && wp_attachment_is_image( $attachment_id ) ) {
			$file = wp_get_original_image_path( $attachment_id );
		} else {
			$file = get_attached_file( $attachment_id );
		}
		$file_info = pathinfo( $file );
		$public_id = $cld_folder . $file_info['filename'];

		return ltrim( $public_id, '/' );
	}

	/**
	 * Is syncable asset.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_syncable( $attachment_id ) {
		$syncable = false;
		if (
			$this->managers['media']->is_media( $attachment_id )
			&& in_array(
				$this->managers['media']->get_media_delivery( $attachment_id ),
				$this->managers['media']->get_syncable_delivery_types(),
				true
			)
		) {
			$syncable = true;
		}

		return $syncable;
	}

	/**
	 * Register a new sync type.
	 *
	 * @param string $type        Sync type key. Must not exceed 20 characters and may
	 *                            only contain lowercase alphanumeric characters, dashes,
	 *                            and underscores. See sanitize_key().
	 * @param array  $structure   {
	 *                            Array of arguments for registering a sync type.
	 *
	 * @type   callable      $generate    Callback method that generates the values to be used to sign a state.
	 *                                    Returns a string or array.
	 *
	 * @type   callable      $validate    Optional Callback method that validates the need to have the sync type applied.
	 *                                    returns Bool.
	 *
	 * @type   int           $priority    Priority in which the type takes place. Lower is higher priority.
	 *                                    i.e a download should happen before an upload so download is lower in the chain.
	 *
	 * @type   callable      $sync        Callback method that handles the sync. i.e uploads the file, adds meta data, etc..
	 *
	 * @type   string        $state       State class to be added to the status icon in media library.
	 *
	 * @type string|callback $note        The status text displayed next to a syncing asset in the media library.
	 *                                    Can be a callback if the note needs to be dynamic. see type folder.
	 *
	 * }
	 */
	public function register_sync_type( $type, $structure ) {

		// Apply a default to ensure parts exist.
		$default = array(
			'generate' => '__return_null',
			'validate' => null,
			'priority' => 50,
			'sync'     => '__return_null',
			'state'    => 'sync',
			'note'     => __( 'Synchronizing asset with Cloudinary', 'cloudinary' ),
		);

		$this->sync_base_struct[ $type ] = wp_parse_args( $structure, $default );
	}

	/**
	 * Get built-in structures that form an assets entire sync state. This holds methods for building signatures for each state of synchronization.
	 * These can be extended via 3rd parties by adding to the structures with custom types and generation and sync methods.
	 */
	public function setup_sync_base_struct() {

		$base_struct = array(
			'upgrade'      => array(
				'generate' => array( $this, 'get_sync_version' ), // Method to generate a signature.
				'validate' => array( $this, 'been_synced' ),
				'priority' => 0,
				'sync'     => array( $this->managers['media']->upgrade, 'convert_cloudinary_version' ),
				'state'    => 'info syncing',
				'note'     => __( 'Upgrading from previous version', 'cloudinary' ),
				'realtime' => true,
			),
			'download'     => array(
				'generate' => '__return_false',
				'validate' => function ( $attachment_id ) {
					$file = get_attached_file( $attachment_id );

					return ! file_exists( $file );
				},
				'priority' => 1,
				'sync'     => array( $this->managers['download'], 'download_asset' ),
				'state'    => 'info downloading',
				'note'     => __( 'Downloading from Cloudinary', 'cloudinary' ),
			),
			'file'         => array(
				'generate' => array( $this, 'generate_file_signature' ),
				'priority' => 5.1,
				'sync'     => array( $this->managers['upload'], 'upload_asset' ),
				'validate' => function ( $attachment_id ) {
					return ! $this->managers['media']->has_public_id( $attachment_id );
				},
				'state'    => 'uploading',
				'note'     => __( 'Uploading to Cloudinary', 'cloudinary' ),
				'required' => true, // Required to complete URL render flag.
			),
			'folder'       => array(
				'generate' => array( $this->managers['media'], 'get_cloudinary_folder' ),
				'validate' => array( $this->managers['media'], 'is_folder_synced' ),
				'priority' => 10,
				'sync'     => array( $this->managers['upload'], 'upload_asset' ),
				'state'    => 'info syncing',
				'note'     => function () {
					return sprintf(
					/* translators: %s folder name */
						__( 'Copying to folder %s.', 'cloudinary' ),
						untrailingslashit( $this->managers['media']->get_cloudinary_folder() )
					);
				},
				'required' => true, // Required to complete URL render flag.
			),
			'public_id'    => array(
				'generate' => array( $this->managers['media'], 'get_public_id' ),
				'validate' => function ( $attachment_id ) {
					$public_id = $this->managers['media']->has_public_id( $attachment_id );

					return false === $public_id;
				},
				'priority' => 20,
				'sync'     => array( $this->managers['media']->upgrade, 'convert_cloudinary_version' ), // Rename.
				'state'    => 'info syncing',
				'note'     => __( 'Updating metadata', 'cloudinary' ),
				'required' => true,
			),
			'breakpoints'  => array(
				'generate' => array( $this->managers['media'], 'get_breakpoint_options' ),
				'priority' => 25,
				'sync'     => array( $this->managers['upload'], 'explicit_update' ),
				'validate' => function ( $attachment_id ) {
					$delivery = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['delivery'] );

					return empty( $delivery ) || 'upload' === $delivery;
				},
				'state'    => 'info syncing',
				'note'     => __( 'Updating breakpoints', 'cloudinary' ),
			),
			'options'      => array(
				'generate' => array( $this->managers['media'], 'get_upload_options' ),
				'priority' => 30,
				'sync'     => array( $this->managers['upload'], 'context_update' ),
				'state'    => 'info syncing',
				'note'     => __( 'Updating metadata', 'cloudinary' ),
			),
			'cloud_name'   => array(
				'generate' => array( $this->managers['connect'], 'get_cloud_name' ),
				'priority' => 5.5,
				'sync'     => array( $this->managers['upload'], 'upload_asset' ),
				'state'    => 'uploading',
				'note'     => __( 'Uploading to new cloud name.', 'cloudinary' ),
				'required' => true,
			),
			'meta_cleanup' => array(
				'generate' => function ( $attachment_id ) {
					$meta = $this->managers['media']->get_post_meta( $attachment_id );

					$return = false;
					foreach ( $meta as $key => $value ) {
						if ( get_post_meta( $attachment_id, $key, true ) === $value ) {
							$return = true;
							break;
						}
					}

					return $return;
				},
				'priority' => 100, // Always be the highest.
				'sync'     => function ( $attachment_id ) {
					$meta = $this->managers['media']->get_post_meta( $attachment_id );
					foreach ( $meta as $key => $value ) {
						if ( self::META_KEYS['public_id'] === $key ) {
							$this->managers['media']->delete_post_meta( $attachment_id, $key );
							continue;
						}
						delete_post_meta( $attachment_id, $key, $value );
					}
					$this->set_signature_item( $attachment_id, 'meta_cleanup' );
				},
				'required' => true,
				'realtime' => true,
			),
		);

		/**
		 * Filter the sync base structure to allow other plugins to sync component callbacks.
		 *
		 * @param array $base_struct The base sync structure.
		 *
		 * @return array
		 */
		$base_struct = apply_filters( 'cloudinary_sync_base_struct', $base_struct );

		// Register each sync type.
		foreach ( $base_struct as $type => $structure ) {
			$this->register_sync_type( $type, $structure );
		}

		/**
		 * Do action for setting up sync types.
		 *
		 * @param \Cloudinary\Sync $this The sync object.
		 */
		do_action( 'cloudinary_register_sync_types', $this );
	}

	/**
	 * Setup the sync types in priority order based on sync struct.
	 */
	public function setup_sync_types() {

		$sync_types = array();
		foreach ( $this->sync_base_struct as $type => $struct ) {
			if ( is_callable( $struct['sync'] ) ) {
				$sync_types[ $type ] = floatval( $struct['priority'] );
			}
		}

		asort( $sync_types );

		$this->sync_types = $sync_types;
	}

	/**
	 * Get a method from a sync type.
	 *
	 * @param string $type   The sync type to get from.
	 * @param string $method The method to get from the sync type.
	 *
	 * @return callable|null
	 */
	public function get_sync_type_method( $type, $method ) {
		$return = null;
		if ( isset( $this->sync_base_struct[ $type ][ $method ] ) && is_callable( $this->sync_base_struct[ $type ][ $method ] ) ) {
			$return = $this->sync_base_struct[ $type ][ $method ];
		}

		return $return;
	}

	/**
	 * Run a sync method on and attachment_id.
	 *
	 * @param string $type          The sync type to run.
	 * @param string $method        The method to run.
	 * @param int    $attachment_id The attachment ID to run method against.
	 *
	 * @return mixed
	 */
	public function run_sync_method( $type, $method, $attachment_id ) {
		$return     = null;
		$run_method = $this->get_sync_type_method( $type, $method );
		if ( $run_method ) {
			$return = call_user_func( $run_method, $attachment_id );
		}

		return $return;
	}

	/**
	 * Generate a single sync type signature for an asset.
	 *
	 * @param string $type          The sync type to run.
	 * @param int    $attachment_id The attachment ID to run method against.
	 *
	 * @return mixed
	 */
	public function generate_type_signature( $type, $attachment_id ) {
		$return     = null;
		$run_method = $this->get_sync_type_method( $type, 'generate' );
		if ( $run_method ) {
			$value = call_user_func( $run_method, $attachment_id );
			if ( ! is_wp_error( $value ) ) {
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$return = md5( $value );
			}
		}

		return $return;
	}

	/**
	 * Prepares and asset for sync comparison by getting all sync types
	 * and running the generate methods for each type.
	 *
	 * @param int|\WP_Post $post The attachment to prepare.
	 *
	 * @return array|\WP_Error
	 */
	public function sync_base( $post ) {

		if ( ! $this->managers['media']->is_media( $post ) ) {
			return new \WP_Error( 'attachment_post_expected', __( 'An attachment post was expected.', 'cloudinary' ) );
		}

		$return = array();
		foreach ( array_keys( $this->sync_types ) as $type ) {
			$return[ $type ] = $this->generate_type_signature( $type, $post );
		}

		/**
		 * Filter the sync base to allow other plugins to add requested sync components for the sync signature.
		 *
		 * @param array    $options The options array.
		 * @param \WP_Post $post    The attachment post.
		 * @param \Cloudinary\Sync The sync object instance.
		 *
		 * @return array
		 */
		$return = apply_filters( 'cloudinary_sync_base', $return, $post );

		return $return;
	}

	/**
	 * Prepare an asset to be synced, maybe.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string | null
	 */
	public function maybe_prepare_sync( $attachment_id ) {

		$type = $this->get_sync_type( $attachment_id );
		if ( $type && $this->can_sync( $attachment_id, $type ) ) {
			$this->add_to_sync( $attachment_id );
		}

		return $type;
	}

	/**
	 * Get the type of sync, with the lowest priority for this asset.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $cached        Flag to specify if a cached signature is to be used or build a new one.
	 *
	 * @return string|null
	 */
	public function get_sync_type( $attachment_id, $cached = true ) {
		if ( ! $this->managers['media']->is_media( $attachment_id ) ) {
			return null; // Ignore non media items.
		}
		$return               = null;
		$required_signature   = $this->generate_signature( $attachment_id, $cached );
		$attachment_signature = $this->get_signature( $attachment_id, $cached );
		if ( is_array( $required_signature ) ) {
			$sync_items = array_filter(
				$attachment_signature,
				function ( $item, $key ) use ( $required_signature ) {
					return $item !== $required_signature[ $key ];
				},
				ARRAY_FILTER_USE_BOTH
			);
			$ordered    = array_intersect_key( $this->sync_types, $sync_items );
			if ( ! empty( $ordered ) ) {
				$types  = array_keys( $ordered );
				$type   = array_shift( $types );
				$return = $this->validate_sync_type( $type, $attachment_id );
			}
		}

		return $return;
	}

	/**
	 * Validate the asset needs the sync type to be run, and generate a valid signature if not.
	 *
	 * @param string $type          The sync type to validate.
	 * @param int    $attachment_id The attachment ID to validate against.
	 *
	 * @return string|null
	 */
	public function validate_sync_type( $type, $attachment_id ) {
		// Validate that this sync type applied (for optional types like upgrade).
		if ( false === $this->run_sync_method( $type, 'validate', $attachment_id ) ) {
			// If invalid, save the new signature.
			$this->set_signature_item( $attachment_id, $type );

			$type = $this->get_sync_type( $attachment_id, false ); // Set cache to false to get the new signature.
		} else {
			// Check if this is a realtime process.
			if ( ! empty( $this->sync_base_struct[ $type ]['realtime'] ) ) {
				$this->run_sync_method( $type, 'sync', $attachment_id );
				$type = $this->get_sync_type( $attachment_id, false ); // Set cache to false to get the new signature.
			}
		}

		return $type;
	}

	/**
	 * Checks the status of the media item.
	 *
	 * @param array $status        Array of state and note.
	 * @param int   $attachment_id The attachment id.
	 *
	 * @return array
	 */
	public function filter_status( $status, $attachment_id ) {

		if ( $this->been_synced( $attachment_id ) || ( $this->is_pending( $attachment_id ) && $this->get_sync_type( $attachment_id ) ) ) {
			$sync_type = $this->get_sync_type( $attachment_id );
			if ( ! empty( $sync_type ) && isset( $this->sync_base_struct[ $sync_type ] ) ) {
				// check process log in case theres an error.
				$log = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['process_log'] );
				if ( ! empty( $log[ $sync_type ] ) && is_wp_error( $log[ $sync_type ] ) ) {
					// Use error instead of sync note.
					$status['state'] = 'error';
					$status['note']  = $log[ $sync_type ]->get_error_message();
				} else {
					$status['state'] = $this->sync_base_struct[ $sync_type ]['state'];
					$status['note']  = $this->sync_base_struct[ $sync_type ]['note'];
					if ( is_callable( $status['note'] ) ) {
						$status['note'] = call_user_func( $status['note'], $attachment_id );
					}
				}
			}

			// Check if there's an error.
			$has_error = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['sync_error'], true );
			if ( ! empty( $has_error ) && $this->get_sync_type( $attachment_id ) ) {
				$status['state'] = 'error';
				$status['note']  = $has_error;
			}
		}

		return $status;
	}

	/**
	 * Add media state to display syncing info.
	 *
	 * @param array    $media_states List of the states.
	 * @param \WP_Post $post         The current attachment post.
	 *
	 * @return array
	 */
	public function filter_media_states( $media_states, $post ) {

		$status = apply_filters( 'cloudinary_media_status', array(), $post->ID );
		if ( ! empty( $status ) ) {
			$media_states[] = $status['note'];
		}

		return $media_states;
	}

	/**
	 * Check if the attachment is pending an upload sync.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 *
	 * @return bool
	 */
	public function is_pending( $attachment_id ) {
		// Check if it's not already in the to sync array.
		if ( ! in_array( $attachment_id, $this->to_sync, true ) ) {
			$is_pending = get_post_meta( $attachment_id, self::META_KEYS['pending'], true );
			if ( empty( $is_pending ) || $is_pending < time() - 5 * 60 ) {
				// No need to delete pending meta, since it will be updated with the new timestamp anyway.
				return false;
			}
		}

		return true;
	}

	/**
	 * Add an attachment ID to the to_sync array.
	 *
	 * @param int $attachment_id The attachment ID to add.
	 */
	public function add_to_sync( $attachment_id ) {
		if ( ! in_array( $attachment_id, $this->to_sync, true ) ) {
			// Flag image as pending to prevent duplicate upload.
			update_post_meta( $attachment_id, self::META_KEYS['pending'], time() );
			$this->to_sync[] = $attachment_id;
		}
	}

	/**
	 * Update signatures of types that match the specified types sync method. This prevents running the same method repeatedly.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $type          The type of sync.
	 */
	public function sync_signature_by_type( $attachment_id, $type ) {
		$current_sync_method = $this->sync_base_struct[ $type ]['sync'];

		// Go over all other types that share the same sync method and include them here.
		foreach ( $this->sync_base_struct as $sync_type => $struct ) {
			if ( $struct['sync'] === $current_sync_method ) {
				$this->set_signature_item( $attachment_id, $sync_type );
			}
		}
	}

	/**
	 * Set an item to the signature set.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $type          The sync type.
	 * @param null   $value         The value.
	 */
	public function set_signature_item( $attachment_id, $type, $value = null ) {

		// Get the core meta.
		$meta = $this->managers['media']->get_post_meta( $attachment_id, self::META_KEYS['signature'], true );
		if ( empty( $meta ) ) {
			$meta = array();
		}
		// Set the specific value.
		if ( is_null( $value ) ) {
			// Generate a new value based on generator.
			$value = $this->generate_type_signature( $type, $attachment_id );
		}
		$meta[ $type ] = $value;
		$this->managers['media']->update_post_meta( $attachment_id, self::META_KEYS['signature'], $meta );
	}

	/**
	 * Initialize the background sync on requested images needing to be synced.
	 */
	public function init_background_upload() {
		if ( ! empty( $this->to_sync ) ) {
			$this->managers['queue']->add_to_queue( $this->to_sync, 'autosync' );
			$this->managers['queue']->start_threads( 'autosync' );
		}
	}

	/**
	 * Filter the Cloudinary Folder.
	 *
	 * @param string $value The set folder.
	 * @param string $slug  The setting slug.
	 *
	 * @return string
	 */
	public function filter_get_cloudinary_folder( $value, $slug ) {
		if ( '.' === $value && 'cloudinary_folder' === $slug ) {
			$value = '';
		}

		return $value;
	}

	/**
	 * Filter the signature.
	 *
	 * @param array $signature     The signature array.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array|bool|string|WP_Error
	 */
	public function get_signature_syncable_type( $signature, $attachment_id ) {

		if ( ! $this->is_syncable( $attachment_id ) ) {
			$signature = $this->generate_signature( $attachment_id );
		}

		return $signature;
	}

	/**
	 * Checks if auto sync feature is enabled.
	 *
	 * @return bool
	 */
	public function is_auto_sync_enabled() {

		if ( 'on' === $this->plugin->settings->get_value( 'auto_sync' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete Cloudinary meta for the attachment ID.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function delete_cloudinary_meta( $attachment_id ) {
		// Update attachment meta.
		$meta = wp_get_attachment_metadata( $attachment_id, true );
		unset( $meta[ self::META_KEYS['cloudinary'] ] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		// Cleanup postmeta.
		$queued = get_post_meta( $attachment_id, self::META_KEYS['queued'] );
		delete_post_meta( $attachment_id, self::META_KEYS['pending'] );
		delete_post_meta( $attachment_id, self::META_KEYS['queued'] );
		delete_post_meta( $attachment_id, self::META_KEYS['suffix'] );
		delete_post_meta( $attachment_id, self::META_KEYS['public_id'] );
		delete_post_meta( $attachment_id, $queued );

		// Signatures cleanup.
		$signatures = $this->get_signature( $attachment_id );
		foreach ( $signatures as $signature ) {
			delete_post_meta( $attachment_id, "_{$signature}" );
		}
	}

	/**
	 * Additional component setup.
	 */
	public function setup() {

		if ( $this->plugin->settings->get_param( 'connected' ) ) {

			// Show sync status.
			add_filter( 'cloudinary_media_status', array( $this, 'filter_status' ), 10, 2 );
			add_filter( 'display_media_states', array( $this, 'filter_media_states' ), 10, 2 );
			// Hook for on demand upload push.
			add_action( 'shutdown', array( $this, 'init_background_upload' ) );

			$this->managers['upload']->setup();
			$this->managers['delete']->setup();
			$this->managers['download']->setup();
			$this->managers['push']->setup();
			// Setup additional components.
			$this->managers['media']   = $this->plugin->components['media'];
			$this->managers['connect'] = $this->plugin->components['connect'];
			$this->managers['api']     = $this->plugin->components['api'];

			// Register Settings.
			$this->register_settings();
			// Setup sync queue.
			$this->managers['queue']->setup( $this );

			add_filter( 'cloudinary_setting_get_value', array( $this, 'filter_get_cloudinary_folder' ), 10, 2 );
			add_filter( 'cloudinary_get_signature', array( $this, 'get_signature_syncable_type' ), 10, 2 );
		}
	}

	/**
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {

		$args = array(
			'type'        => 'page',
			'menu_title'  => __( 'Sync', 'cloudinary' ),
			'option_name' => 'cloudinary_sync_media',
			'priority'    => 9,
			array(
				'type'  => 'panel',
				'title' => __( 'Sync Settings', 'cloudinary' ),
				array(
					'type'         => 'radio',
					'title'        => __( 'Sync method', 'cloudinary' ),
					'tooltip_text' => __(
						'Auto sync: Ensures that all of your WordPress assets are automatically synced with Cloudinary when they are added to the WordPress Media Library. Manual sync: Assets must be synced manually using the WordPress Media Library',
						'cloudinary'
					),
					'slug'         => 'auto_sync',
					'no_cached'    => true,
					'default'      => 'on',
					'options'      => array(
						'on'  => __( 'Auto sync', 'cloudinary' ),
						'off' => __( 'Manual sync', 'cloudinary' ),
					),
				),
				array(
					'type'        => 'sync',
					'title'       => __( 'Bulk sync all your WordPress assets to Cloudinary', 'cloudinary' ),
					'tooltip_off' => __( 'Manual sync is enabled. Individual assets must be synced manually using the WordPress Media Library.', 'cloudinary' ),
					'tooltip_on'  => __( 'An optional one-time operation to manually synchronize all WordPress Media to Cloudinary.', 'cloudinary' ),
					'queue'       => $this->managers['queue'],
				),
				array(
					'type'              => 'text',
					'slug'              => 'cloudinary_folder',
					'title'             => __( 'Cloudinary folder path', 'cloudinary' ),
					'default'           => '.',
					'attributes'        => array(
						'input' => array(
							'placeholder' => __( 'e.g.: wordpress_assets/', 'cloudinary' ),
						),
					),
					'tooltip_text'      => __(
						'Specify the folder in your Cloudinary account where WordPress assets are uploaded to. All assets uploaded to WordPress from this point on will be synced to the specified folder in Cloudinary. Leave blank to use the root of your Cloudinary library.',
						'cloudinary'
					),
					'sanitize_callback' => array( '\Cloudinary\Media', 'sanitize_cloudinary_folder' ),
				),
				array(
					'type'         => 'select',
					'slug'         => 'offload',
					'title'        => __( 'Storage', 'cloudinary' ),
					'tooltip_text' => __(
						'Choose where to store your assets. Assets stored in both Cloudinary and WordPress will enable local WordPress delivery if the Cloudinary plugin is disabled or uninstalled. Storing assets with WordPress in lower resolution will save on local WordPress storage and enable low resolution local WordPress delivery if the plugin is disabled. Storing assets with Cloudinary only will require additional steps to enable backwards compatibility.',
						'cloudinary'
					),
					'default'      => 'dual_full',
					'options'      => array(
						'dual_full' => __( 'Cloudinary and WordPress', 'cloudinary' ),
						'dual_low'  => __( 'Cloudinary and WordPress (low resolution)', 'cloudinary' ),
						'cld'       => __( 'Cloudinary only', 'cloudinary' ),
					),
				),
			),
			array(
				'type' => 'submit',
			),
		);

		return $args;
	}

	/**
	 * Generate the real file attachment path for the file sync type signature.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function generate_file_signature( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		return basename( $path );
	}

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {

		$settings_params = $this->settings();
		$this->settings  = $this->plugin->settings->create_setting( $this->settings_slug, $settings_params );

		// Move setting to media.
		$media_settings = $this->managers['media']->get_settings();
		$media_settings->add_setting( $this->settings );
	}
}
