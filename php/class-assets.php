<?php
/**
 * Cloudinary non media library assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Assets\Rest_Assets;
use Cloudinary\Connect\Api;
use Cloudinary\Sync;
use Cloudinary\Cron;
use Cloudinary\Traits\Params_Trait;
use Cloudinary\Utils;
use WP_Error;

/**
 * Class Assets
 *
 * @package Cloudinary
 */
class Assets extends Settings_Component {

	use Params_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	public $media;

	/**
	 * Holds the Delivery instance.
	 *
	 * @var Delivery
	 */
	public $delivery;

	/**
	 * Post type.
	 *
	 * @var \WP_Post_Type
	 */
	protected $post_type;

	/**
	 * Holds registered asset parents.
	 *
	 * @var \WP_Post[]
	 */
	protected $asset_parents;

	/**
	 * Holds active asset parents.
	 *
	 * @var \WP_Post[]
	 */
	protected $active_parents = array();

	/**
	 * Holds a list of found urls that need to be created.
	 *
	 * @var array
	 */
	protected $to_create;

	/**
	 * Holds the ID's of assets.
	 *
	 * @var array
	 */
	protected $asset_ids;

	/**
	 * Holds the Assets REST instance.
	 *
	 * @var Rest_Assets
	 */
	protected $rest;

	/**
	 * Holds the post type.
	 */
	const POST_TYPE_SLUG = 'cloudinary_asset';

	/**
	 * Holds the meta keys.
	 *
	 * @var array
	 */
	const META_KEYS = array(
		'excludes' => '_excluded_urls',
		'lock'     => '_asset_lock',
		'edits'    => '_edited_assets',
	);

	/**
	 * Static instance of this class.
	 *
	 * @var self
	 */
	public static $instance;

	/**
	 * Assets constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->should_sanitize_slugs = true;

		$this->media    = $plugin->get_component( 'media' );
		$this->delivery = $plugin->get_component( 'delivery' );
		// Add activation hooks.
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );
		self::$instance = $this;

		// Set separator.
		$this->separator = '/';
	}

	/**
	 * Init the class.
	 */
	public function init() {
		$this->register_post_type();
		$this->init_asset_parents();
		$this->register_hooks();
		$this->rest = new Rest_Assets( $this );
	}

	/**
	 * Register the hooks.
	 */
	protected function register_hooks() {

		// Filters.
		add_filter( 'cloudinary_is_content_dir', array( $this, 'check_asset' ), 10, 2 );
		add_filter( 'cloudinary_is_media', array( $this, 'is_media' ), 10, 2 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'add_sync_type' ) );
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'no_sizes' ), PHP_INT_MAX, 3 );
		add_filter( 'cloudinary_can_sync_asset', array( $this, 'can_sync' ), 10, 2 );
		add_filter( 'cloudinary_local_url', array( $this, 'local_url' ), 10, 2 );
		add_filter( 'cloudinary_is_folder_synced', array( $this, 'filter_folder_sync' ), 10, 2 );
		add_filter( 'cloudinary_asset_state', array( $this, 'filter_asset_state' ), 10, 2 );
		add_filter( 'cloudinary_set_usable_asset', array( $this, 'check_usable_asset' ) );
		// Actions.
		add_action( 'cloudinary_ready', array( $this, 'setup' ) );
		add_action( 'cloudinary_thread_queue_details_query', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_build_queue_query', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'add_url_replacements' ), 20 );
		add_action( 'shutdown', array( $this, 'meta_updates' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_cache' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'cloudinary_delete_asset', array( $this, 'purge_parent' ) );
	}

	/**
	 * Filter the asset state to allow syncing in manual.
	 *
	 * @param int $state         The current state.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function filter_asset_state( $state, $attachment_id ) {
		if ( self::is_asset_type( $attachment_id ) || ! $this->media->sync->been_synced( $attachment_id ) ) {
			$state = 0;
		}

		return $state;
	}

	/**
	 * Filter to ensure an asset type is never identified as folder synced.
	 *
	 * @param bool $is            Flag to indicate is folder synced.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function filter_folder_sync( $is, $attachment_id ) {
		if ( self::is_asset_type( $attachment_id ) ) {
			$is = false;
		}

		return $is;
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		if ( Utils::user_can( 'status' ) && 'on' === $this->plugin->settings->image_settings->_overlay ) {
			wp_enqueue_script( 'front-overlay', $this->plugin->dir_url . 'js/front-overlay.js', array(), $this->plugin->version, true );
			wp_enqueue_style( 'front-overlay', $this->plugin->dir_url . 'css/front-overlay.css', array(), $this->plugin->version );
		}
	}

	/**
	 * Get the local url for an asset.
	 *
	 * @hook cloudinary_local_url
	 *
	 * @param string|false $url      The url to filter.
	 * @param int          $asset_id The asset ID.
	 *
	 * @return string|false
	 */
	public function local_url( $url, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) ) {
			$url = get_the_title( $asset_id );
		}

		return $url;
	}

	/**
	 * Add Cloudinary menu to admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar The admin bar object.
	 */
	public function admin_bar_cache( $admin_bar ) {
		if ( ! Utils::user_can( 'status' ) || is_admin() ) {
			return;
		}

		if ( 'on' === $this->plugin->settings->image_settings->_overlay ) {
			$title = __( 'Disable Cloudinary status', 'cloudinary' );
		} else {
			$title = __( 'Enable Cloudinary status', 'cloudinary' );
		}

		$nonce   = wp_create_nonce( 'cloudinary-cache-overlay' );
		$overlay = array(
			'id'    => 'cloudinary-overlay',
			'title' => $title,
			'href'  => '?cloudinary-cache-overlay=' . $nonce,
			'meta'  => array(
				'title' => $title,
			),
		);
		$admin_bar->add_menu( $overlay );
	}

	/**
	 * Sets the autosync to work on cloudinary_assets even when the autosync is disabled.
	 *
	 * @hook cloudinary_can_sync_asset
	 *
	 * @param bool $can      The can sync check value.
	 * @param int  $asset_id The asset ID.
	 *
	 * @return bool
	 */
	public function can_sync( $can, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) && 'off' === $this->settings->get_value( 'auto_sync' ) && 'on' === $this->settings->get_value( 'content.enabled' ) ) {
			$can = true;
		}

		if ( $can && ! $this->plugin->get_component( 'delivery' )->is_deliverable( $asset_id ) ) {
			$can = false;
		}

		// If the asset is set to a 'disable' state, we do not allow syncing.
		if ( $can && self::is_asset_type( $asset_id ) && $this->is_disable_state( $asset_id ) ) {
			$can = false;
		}

		return $can;
	}

	/**
	 * Verify if the asset is set to a 'disable' state.
	 *
	 * @param int $asset_id The asset ID to check.
	 *
	 * @return bool
	 */
	protected function is_disable_state( $asset_id ) {
		global $wpdb;

		$wpdb->cld_table = Utils::get_relationship_table();
		$media_context   = Utils::get_media_context( $asset_id );
		$prepare         = $wpdb->prepare( "SELECT `post_state` FROM $wpdb->cld_table WHERE post_id = %d AND media_context = %s;", (int) $asset_id, $media_context ); // phpcs:ignore WordPress.DB.PreparedSQL
		$state           = $wpdb->get_var( $prepare ); // phpcs:ignore WordPress.DB

		return 'disable' === $state;
	}

	/**
	 * Check if the post is a asset post type.
	 *
	 * @param int $post_id The ID to check.
	 *
	 * @return bool
	 */
	public static function is_asset_type( $post_id ) {
		$post = get_post( $post_id );

		return ! in_array( $post, self::$instance->get_asset_parents(), true ) && self::POST_TYPE_SLUG === get_post_type( $post_id );
	}

	/**
	 * Filter out sizes for assets.
	 *
	 * @hook intermediate_image_sizes_advanced
	 *
	 * @param array    $new_sizes     The sizes to remove.
	 * @param array    $image_meta    The image meta.
	 * @param int|null $attachment_id The asset ID.
	 *
	 * @return array
	 */
	public function no_sizes( $new_sizes, $image_meta, $attachment_id = null ) {
		if ( is_null( $attachment_id ) ) {
			$attachment_id = $this->plugin->settings->get_param( '_currrent_attachment', 0 );
		}
		if ( self::is_asset_type( $attachment_id ) ) {
			$new_sizes = array();
		}

		return $new_sizes;
	}

	/**
	 * Compiles all metadata and preps upload at shutdown.
	 */
	public function meta_updates() {
		if ( $this->is_locked() ) {
			return;
		}

		if ( ! empty( $this->delivery->unusable ) ) {
			$assets = array();
			foreach ( $this->delivery->unusable as $unusable ) {
				if ( 'asset' === $unusable['sync_type'] && isset( $this->active_parents[ $unusable['parent_path'] ] ) && ! in_array( $unusable['post_id'], $assets, true ) ) {
					$asset_id = (int) $unusable['post_id'];
					if ( $this->media->sync->can_sync( $asset_id ) ) {
						$this->media->sync->set_signature_item( $asset_id, 'cld_asset', 'reset' );
						$this->media->sync->add_to_sync( $asset_id );
						$assets[] = $unusable['post_id'];
					}
				}
			}
		}

		// Create found asset that's not media library.
		if ( ! empty( $this->to_create ) && ! empty( $this->delivery->unknown ) ) {
			// Do not create assets if the image delivery is disabled.
			if ( 'on' === $this->plugin->settings->get_value( 'image_delivery' ) ) {
				foreach ( $this->delivery->unknown as $url ) {
					if ( isset( $this->to_create[ $url ] ) ) {
						$this->create_asset( $url, $this->to_create[ $url ] );
					}
				}
			}
		}
	}

	/**
	 * Set urls to be replaced.
	 *
	 * @hook cloudinary_string_replace
	 */
	public function add_url_replacements() {
		// Due to the output buffers, this can be called multiple times.
		if ( 1 < did_action( 'cloudinary_string_replace' ) ) {
			return;
		}

		$overlay = Utils::get_sanitized_text( 'cloudinary-cache-overlay' );
		$setting = $this->plugin->settings->image_settings->overlay;

		if ( $overlay && wp_verify_nonce( $overlay, 'cloudinary-cache-overlay' ) ) {
			$referrer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL );
			if ( $setting->get_value() === 'on' ) {
				$setting->save_value( 'off' );
			} else {
				$setting->save_value( 'on' );
			}
			wp_safe_redirect( $referrer );
			exit;
		}
	}

	/**
	 * Connect our post type to the sync query, to allow it to be queued.
	 *
	 * @hook cloudinary_thread_queue_details_query, cloudinary_build_queue_query
	 *
	 * @param array $query The Query.
	 *
	 * @return array
	 */
	public function connect_post_type( $query ) {

		$query['post_type'] = array_merge( (array) $query['post_type'], (array) self::POST_TYPE_SLUG );

		return $query;
	}

	/**
	 * Retrieve the assets settings.
	 *
	 * This method fetches the assets settings from the configuration.
	 * If the assets settings are empty or the settings are locked, it returns an empty array.
	 *
	 * @return array The assets settings array.
	 */
	public function get_assets_settings() {
		$assets = $this->settings->get_setting( 'assets' )->get_settings();

		if ( empty( $assets ) || $this->is_locked() ) {
			return array();
		}

		return $assets;
	}

	/**
	 * Update asset paths.
	 *
	 * @return void
	 */
	public function update_asset_paths() {
		$assets = $this->get_assets_settings();

		if ( empty( $assets ) ) {
			return;
		}

		foreach ( $assets as $asset ) {
			$paths = $asset->get_setting( 'paths' );
			foreach ( $paths->get_settings() as $path ) {
				if ( 'on' === $path->get_value() ) {
					$conf    = $path->get_params();
					$path    = urldecode( trailingslashit( $conf['url'] ) );
					$version = $conf['version'];

					$asset_path = $this->get_asset_parent( $path );

					if ( null === $asset_path ) {
						$asset_parent_id = $this->create_asset_parent( $path, $version );

						if ( is_wp_error( $asset_parent_id ) ) {
							return; // Bail.
						}

						$asset_path = get_post( $asset_parent_id );
					}

					// Check and update version if needed.
					if ( $this->media->get_post_meta( $asset_path->ID, Sync::META_KEYS['version'], true ) !== $version ) {
						$this->media->update_post_meta( $asset_path->ID, Sync::META_KEYS['version'], $version );
						$this->sync_parent( $asset_path->ID );
					}
				}
			}
		}
	}

	/**
	 * Activate parent assets based on the current settings and purges unused parent assets.
	 *
	 * @return void
	 */
	protected function activate_parents() {
		$assets = $this->get_assets_settings();

		if ( empty( $assets ) ) {
			return;
		}

		foreach ( $assets as $asset ) {
			$paths = $asset->get_setting( 'paths' );

			foreach ( $paths->get_settings() as $path ) {
				if ( 'on' === $path->get_value() ) {
					$conf = $path->get_params();
					self::activate_parent( urldecode( trailingslashit( $conf['url'] ) ) );
				}
			}
		}
		// Get the disabled items.
		foreach ( $this->asset_parents as $url => $parent ) {
			if ( isset( $this->active_parents[ $url ] ) ) {
				continue;
			}

			if ( ! $this->is_post_cloudinary_asset( $parent->ID ) ) {
				continue;
			}

			$this->purge_parent( $parent->ID );
			// Remove parent.
			wp_delete_post( $parent->ID );
		}
	}

	/**
	 * Check if a post is a Cloudinary asset.
	 *
	 * @param int $post_id The post ID to check.
	 *
	 * @return bool
	 */
	public function is_post_cloudinary_asset( $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE_SLUG ) {
			return true;
		}

		return false;
	}

	/**
	 * Activate a parent asset path.
	 *
	 * @param string $url The path to activate.
	 */
	public function activate_parent( $url ) {
		$url = $this->clean_path( $url );
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$this->active_parents[ $url ] = $this->asset_parents[ $url ];
			$this->set_param( trim( $url, $this->separator ), $this->asset_parents[ $url ] );
		}
	}

	/**
	 * Clean a path for saving as a title.
	 *
	 * @param string $path The path to clean.
	 *
	 * @return string
	 */
	public function clean_path( $path ) {
		$home = Utils::clean_url( trailingslashit( Utils::home_url() ) );
		$path = str_replace( $home, '', Utils::clean_url( $path ) );
		if ( empty( Utils::pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$path = urldecode( trailingslashit( $path ) );
		}

		return $path;
	}

	/**
	 * Create an asset parent.
	 *
	 * @param string $path    The path to create.
	 * @param string $version The version.
	 *
	 * @return int|\WP_Error
	 */
	public function create_asset_parent( $path, $version ) {
		$path      = $this->clean_path( $path );
		$args      = array(
			'post_title'  => $path,
			'post_name'   => md5( $path ),
			'post_type'   => self::POST_TYPE_SLUG,
			'post_status' => 'publish',
		);
		$parent_id = wp_insert_post( $args );
		if ( $parent_id ) {
			$this->media->update_post_meta( $parent_id, Sync::META_KEYS['version'], $version );
			$this->media->update_post_meta( $parent_id, self::META_KEYS['excludes'], array() );
			$this->asset_parents[ $path ] = get_post( $parent_id );
		}

		return $parent_id;
	}

	/**
	 * Process all child assets of a parent with a given callback.
	 *
	 * @param int      $parent_id The Asset parent to process.
	 * @param callable $callback  The callback function to execute on each post.
	 */
	private function process_parent_assets( $parent_id, $callback ) {
		if ( ! $this->is_post_cloudinary_asset( $parent_id ) ) {
			return;
		}

		$query_args     = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'posts_per_page'         => 100,
			'post_parent'            => $parent_id,
			'post_status'            => array( 'inherit', 'draft' ),
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$query          = new \WP_Query( $query_args );
		$previous_total = $query->found_posts;

		do {
			$this->lock_assets();
			$posts = $query->get_posts();

			foreach ( $posts as $post_id ) {
				call_user_func( $callback, $post_id );
			}

			$query_args = $query->query_vars;
			$query      = new \WP_Query( $query_args );
			if ( $previous_total === $query->found_posts ) {
				break;
			}
		} while ( $query->have_posts() );
	}

	/**
	 * Sync the assets of a parent.
	 *
	 * @param int $parent_id The Asset parent to sync.
	 */
	public function sync_parent( $parent_id ) {
		$this->process_parent_assets(
			$parent_id,
			function ( $post_id ) {
				if ( empty( $this->media->sync ) || ! $this->media->sync->can_sync( $post_id ) ) {
					return;
				}

				$this->media->sync->set_signature_item( $post_id, 'file', '' );
				$this->media->sync->set_signature_item( $post_id, 'cld_asset' );
				$this->media->sync->add_to_sync( $post_id );
			}
		);
	}

	/**
	 * Purge a single asset parent.
	 *
	 * @param int $parent_id The Asset parent to purge.
	 */
	public function purge_parent( $parent_id ) {
		$this->process_parent_assets(
			$parent_id,
			function ( $post_id ) {
				if ( ! $this->is_post_cloudinary_asset( $post_id ) ) {
					return;
				}

				wp_delete_post( $post_id );
			}
		);
	}

	/**
	 * Lock asset creation for performing things like purging that require no changes.
	 */
	public function lock_assets() {
		set_transient( self::META_KEYS['lock'], true, 10 );
	}

	/**
	 * Unlock asset creation.
	 */
	public function unlock_assets() {
		delete_transient( self::META_KEYS['lock'] );
	}

	/**
	 * Check if assets are locked.
	 *
	 * @return bool
	 */
	public function is_locked() {
		return get_transient( self::META_KEYS['lock'] );
	}

	/**
	 * Generate the signature for sync.
	 *
	 * @param int $asset_id The attachment/asset ID.
	 *
	 * @return string
	 */
	public function generate_file_signature( $asset_id ) {
		$path   = $this->clean_path( $this->media->local_url( $asset_id ) );
		$parent = $this->get_param( $path );
		$str    = $asset_id;
		if ( $parent ) {
			$str .= $parent->post_date;
		}

		return $str;
	}

	/**
	 * Generate the signature for sync storage.
	 *
	 * @param int $asset_id The attachment/asset ID.
	 *
	 * @return string
	 */
	public function generate_storage_signature( $asset_id ) {
		return $this->get_asset_storage_folder( $asset_id ) === $this->media->get_public_id( $asset_id );
	}

	/**
	 * Generate the signature for sync.
	 *
	 * @param int $attachment_id The attachment/asset ID.
	 *
	 * @return string
	 */
	public function generate_edit_signature( $attachment_id ) {
		$sig  = wp_json_encode( (array) get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true ) );
		$file = Utils::get_path_from_url( $this->media->local_url( $attachment_id ), true );

		return $sig . $file;
	}

	/**
	 * Upload an asset.
	 *
	 * @param int $asset_id The asset ID to upload.
	 *
	 * @return array|\WP_Error
	 */
	public function upload( $asset_id ) {
		$connect = $this->plugin->get_component( 'connect' );
		$options = array(
			'use_filename'  => true,
			'overwrite'     => false,
			'resource_type' => $this->media->get_resource_type( $asset_id ),
		);

		$folder       = untrailingslashit( $this->media->get_cloudinary_folder() );
		$asset_parent = self::POST_TYPE_SLUG === Utils::get_post_parent( $asset_id )->post_type;

		if ( ! empty( $asset_parent ) ) {
			$folder                     = $this->get_asset_storage_folder( get_the_title( $asset_id ) );
			$options['overwrite']       = true; // Ensure we maintain this path and filename.
			$options['unique_filename'] = false; // Ensure we don't append a suffix.
		}
		// Add folder.
		$options['folder'] = $folder;
		$result            = $connect->api->upload( $asset_id, $options, array() );
		if ( ! is_wp_error( $result ) && isset( $result['public_id'] ) ) {
			$this->media->update_post_meta( $asset_id, Sync::META_KEYS['public_id'], $result['public_id'] );
			Delivery::update_size_relations_public_id( $asset_id, $result['public_id'] );
			Delivery::update_size_relations_state( $asset_id, 'enable' );
			$this->media->sync->set_signature_item( $asset_id, 'file' );
			$this->media->sync->set_signature_item( $asset_id, 'cld_asset' );
			$this->media->sync->set_signature_item( $asset_id, 'asset_storage' );
			$this->plugin->get_component( 'storage' )->size_sync( $asset_id, $result['public_id'] );
		}

		return $result;
	}

	/**
	 * Get the storage location on Cloudinary, for an asset.
	 *
	 * @param int|string $url_id The url or asset ID.
	 *
	 * @return string
	 */
	public function get_asset_storage_folder( $url_id ) {
		if ( is_numeric( $url_id ) ) {
			$url_id = $this->media->local_url( $url_id );
		}
		$url    = $this->clean_path( $url_id );
		$domain = wp_parse_url( Utils::home_url(), PHP_URL_HOST );
		$folder = wp_normalize_path( dirname( trim( $url, './' ) ) );
		if ( ! empty( $domain ) ) {
			$folder = path_join( $domain, $folder );
		}

		return $folder;
	}

	/**
	 * Validate if sync type is valid.
	 *
	 * @param int $attachment_id The attachment id to validate.
	 *
	 * @return bool
	 */
	public function validate_asset_sync( $attachment_id ) {

		// Default is either a asset type or auto sync off, if it's a media library item.
		$valid = self::is_asset_type( $attachment_id );

		// Check to see if there is a parent. If there is, then the asset is enabled to be synced.
		if ( true === $valid ) {
			$parent = $this->find_parent( $attachment_id );
			if ( ! $parent ) {
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate if sync type is valid as an edited asset.
	 *
	 * @param int $attachment_id The attachment id to validate.
	 *
	 * @return bool
	 */
	public function validate_edited_asset_sync( $attachment_id ) {
		$valid = false;
		if ( ! self::is_asset_type( $attachment_id ) ) {
			$sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
			if ( ! empty( $sizes ) ) {
				$valid = true;
			}
		}

		return $valid;
	}

	/**
	 * Register our sync type.
	 *
	 * @hook  cloudinary_sync_base_struct
	 *
	 * @param array $structs The structure of all sync types.
	 *
	 * @return array
	 */
	public function add_sync_type( $structs ) {
		$structs['cld_asset'] = array(
			'generate'    => array( $this, 'generate_file_signature' ),
			'priority'    => 2,
			'sync'        => array( $this, 'upload' ),
			'validate'    => array( $this, 'validate_asset_sync' ),
			'state'       => 'disabled',
			'note'        => __( 'Caching', 'cloudinary' ),
			'required'    => true,
			'asset_state' => 0,
		);

		$structs['edited_asset'] = array(
			'generate' => array( $this, 'generate_edit_signature' ),
			'priority' => 5.3,
			'sync'     => array( $this, 'create_edited_asset' ),
			'validate' => array( $this, 'validate_edited_asset_sync' ),
			'state'    => 'disabled',
			'note'     => __( 'Creating shadow assets', 'cloudinary' ),
			'required' => false,
			'realtime' => true,
		);

		$structs['asset_storage'] = array(
			'generate'    => array( $this, 'generate_storage_signature' ),
			'priority'    => 19,
			'sync'        => array( $this, 'relocate_asset' ),
			'validate'    => array( $this, 'validate_asset_storage' ),
			'state'       => 'disabled',
			'note'        => __( 'Updating asset storage', 'cloudinary' ),
			'required'    => false,
			'asset_state' => 0,
		);

		return $structs;
	}

	/**
	 * Validate that the asset Storage matches the Public_id.
	 *
	 * @param int $asset_id The asset ID.
	 *
	 * @return bool
	 */
	public function validate_asset_storage( $asset_id ) {
		$valid = false;
		if ( self::is_asset_type( $asset_id ) && $this->media->has_public_id( $asset_id ) ) {
			$location  = $this->get_asset_storage_folder( $asset_id );
			$public_id = $this->media->get_public_id( $asset_id );
			$valid     = dirname( $public_id ) !== $location;
		}

		return $valid;
	}

	/**
	 * Relocate an non-media library asset.
	 *
	 * @param int $asset_id The asset to relocate.
	 *
	 * @return array|WP_Error
	 */
	public function relocate_asset( $asset_id ) {
		$connect       = $this->plugin->get_component( 'connect' );
		$local_url     = $this->media->local_url( $asset_id );
		$filename      = Utils::pathinfo( $local_url, PATHINFO_FILENAME );
		$new_public_id = $this->get_asset_storage_folder( $local_url );
		$type          = $this->media->get_resource_type( $asset_id );
		$params        = array(
			'from_public_id' => $this->media->get_public_id( $asset_id ),
			'to_public_id'   => path_join( $new_public_id, $filename ),
			'overwrite'      => true,
		);
		$result        = $connect->api->rename( $type, $params );
		if ( ! is_wp_error( $result ) && isset( $result['public_id'] ) ) {
			$this->media->update_post_meta( $asset_id, Sync::META_KEYS['public_id'], $result['public_id'] );
			Delivery::update_size_relations_public_id( $asset_id, $result['public_id'] );
			$this->media->sync->set_signature_item( $asset_id, 'asset_storage' );
		}

		return $result;
	}

	/**
	 * Create an edited asset which acts as a shadow media item for edits.
	 *
	 * @param int $attachment_id The attachment to create from.
	 *
	 * @return array
	 */
	public function create_edited_asset( $attachment_id ) {
		$sizes   = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$assets  = $this->media->get_post_meta( $attachment_id, self::META_KEYS['edits'], true );
		$current = wp_basename( get_attached_file( $attachment_id, true ) );

		if ( empty( $assets ) ) {
			$assets = array();
		}
		if ( ! empty( $sizes ) ) {
			$base = dirname( $this->media->local_url( $attachment_id ) );
			foreach ( $sizes as $size => $data ) {

				if ( 'full-' !== substr( $size, 0, 5 ) ) {
					continue;
				}

				$url = Utils::clean_url( path_join( $base, $data['file'] ) );
				if ( wp_basename( $url ) === $current ) {
					// Currently the original.
					if ( isset( $assets[ $url ] ) ) {
						// Restored from a crop.
						wp_delete_post( $assets[ $url ] );
						unset( $assets[ $url ] );
						$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['relationship'] );
					}
					continue;
				}
				if ( ! isset( $assets[ $url ] ) ) {
					$asset          = $this->create_asset( $url, $attachment_id );
					$assets[ $url ] = $asset;
				}
			}
			$this->media->update_post_meta( $attachment_id, self::META_KEYS['edits'], $assets );
		}
		$this->media->sync->set_signature_item( $attachment_id, 'edited_asset' );

		return $assets;
	}

	/**
	 * Init asset parents.
	 */
	protected function init_asset_parents() {

		$args                = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'post_parent'            => 0,
			'posts_per_page'         => 100,
			'paged'                  => 1,
			'post_status'            => 'publish',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$query               = new \WP_Query( $args );
		$this->asset_parents = array();

		do {
			foreach ( $query->get_posts() as $post ) {
				$this->asset_parents[ $post->post_title ] = $post;
			}
			$args = $query->query_vars;
			++$args['paged'];
			$query = new \WP_Query( $args );
		} while ( $query->have_posts() );
	}

	/**
	 * Check if the non-local URL should be added as an asset.
	 *
	 * @hook cloudinary_is_content_dir
	 *
	 * @param bool   $is_local The is_local flag.
	 * @param string $url      The URL to check.
	 *
	 * @return bool
	 */
	public function check_asset( $is_local, $url ) {
		if ( $is_local || ! $this->syncable_asset( $url ) ) {
			return $is_local;
		}

		$found = null;
		$try   = $this->clean_path( $url );
		while ( false !== strpos( $try, $this->separator ) ) {
			$try = substr( $try, 0, strripos( $try, $this->separator ) );
			if ( ! empty( $try ) && $this->has_param( $try ) ) {
				$found = $this->get_param( $try );
				break;
			}
		}
		if ( $found instanceof \WP_Post ) {
			$is_local = true;

			if ( $this->delivery->is_deliverable( $found->ID ) ) {
				$this->to_create[ $url ] = $found->ID;
			}
		}

		return $is_local;
	}

	/**
	 * Check the status of a Cloudinary Asset.
	 *
	 * @param array $asset The asset array.
	 *
	 * @return array
	 */
	public function check_usable_asset( $asset ) {
		if ( 'asset' === $asset['sync_type'] && ! empty( $asset['public_id'] ) ) {
			$storage_path = $this->get_asset_storage_folder( $asset['sized_url'] );
			if ( dirname( $asset['public_id'] ) !== $storage_path ) {
				$this->media->sync->add_to_sync( $asset['post_id'] ); // Add to sync.
			}
		}

		return $asset;
	}

	/**
	 * Check if the asset is syncable.
	 *
	 * @param string $filename The filename to check.
	 *
	 * @return bool
	 */
	protected function syncable_asset( $filename ) {
		static $allowed_kinds = array();
		if ( empty( $allowed_kinds ) ) {
			// Check with paths.
			$types         = wp_get_ext_types();
			$allowed_kinds = array_merge( $allowed_kinds, $types['image'], $types['audio'], $types['video'] );
		}
		$type = Utils::pathinfo( $filename, PATHINFO_EXTENSION );

		return in_array( $type, $allowed_kinds, true );
	}

	/**
	 * Get the asset src file.
	 *
	 * @param string $file     The file as from the filter.
	 * @param int    $asset_id The asset ID.
	 *
	 * @return string
	 */
	public function get_attached_file( $file, $asset_id ) {
		if ( self::is_asset_type( $asset_id ) && ! file_exists( $file ) ) {
			$dirs = wp_get_upload_dir();
			$file = str_replace( trailingslashit( $dirs['basedir'] ), ABSPATH, $file );
		}

		return $file;
	}

	/**
	 * Check to see if the post is a media item.
	 *
	 * @hook cloudinary_is_media
	 *
	 * @param bool $is_media      The is_media flag.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_media( $is_media, $attachment_id ) {
		if ( false === $is_media && self::is_asset_type( $attachment_id ) ) {
			$is_media = true;
		}

		return $is_media;
	}

	/**
	 * Get all asset parents.
	 *
	 * @return \WP_Post[]
	 */
	public function get_asset_parents() {
		$parents = array();
		if ( ! empty( $this->asset_parents ) ) {
			$parents = $this->asset_parents;
		}

		return $parents;
	}

	/**
	 * Get all asset parents.
	 *
	 * @return \WP_Post[]
	 */
	public function get_active_asset_parents() {
		$parents = array();
		if ( ! empty( $this->active_parents ) ) {
			$parents = $this->active_parents;
		}

		return $parents;
	}

	/**
	 * Find a parent for an asset.
	 *
	 * @param int $asset_id The asset id.
	 *
	 * @return \WP_Post|null;
	 */
	public function find_parent( $asset_id ) {
		$path   = $this->clean_path( $this->media->local_url( $asset_id ) );
		$parent = $this->get_param( $path );
		if ( empty( $parent ) ) {
			$parent = Utils::get_post_parent( $asset_id );
		}

		return $parent instanceof \WP_Post ? $parent : null;
	}

	/**
	 * Get an asset parent.
	 *
	 * @param string $url The URL of the parent.
	 *
	 * @return \WP_Post|null
	 */
	public function get_asset_parent( $url ) {
		$url    = $this->clean_path( $url );
		$parent = null;
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$parent = $this->asset_parents[ $url ];
		}

		return $parent;
	}

	/**
	 * Get a single asset primary record.
	 *
	 * @param int    $post_id The post ID to get.
	 * @param string $type    Type of return: Object or dataset.
	 *
	 * @return \WP_Post|array|null
	 */
	public function get_asset( $post_id, $type = 'object' ) {

		if ( 'object' === $type ) {
			return get_post( $post_id );
		}

		global $wpdb;

		$wpdb->cld_table = Utils::get_relationship_table();
		$media_context   = Utils::get_media_context( $post_id );
		$prepare         = $wpdb->prepare( "SELECT * FROM $wpdb->cld_table WHERE post_id = %d AND media_context = %s;", (int) $post_id, $media_context );
		$result          = $wpdb->get_row( $prepare, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		return $this->build_item( $result );
	}

	/**
	 * Build a single asset dataset.
	 *
	 * @param array $item The raw data for an asset.
	 *
	 * @return array
	 */
	public function build_item( $item ) {
		if ( empty( $item ) ) {
			return null;
		}
		$dirs     = wp_get_upload_dir();
		$max_size = 900;
		$parts    = explode( $item['parent_path'], $item['sized_url'] );
		$parts[0] = $dirs['baseurl'];

		$url     = $item['sized_url'];
		$size    = $item['width'] > $item['height'] ? array( 'width' => $max_size ) : array( 'height' => $max_size );
		$break   = null;
		$preview = wp_get_attachment_thumb_url( $item['post_id'] );
		if ( ! empty( $item['public_id'] ) ) {
			$preview = $this->media->cloudinary_url( $item['post_id'], null, array( $size ), $item['public_id'], true );
			$break   = $item['width'] > $item['height'] ? 'w_' . $max_size : 'h_' . $max_size;
			$parts   = explode( $break, $preview );
		}

		$args = array(
			'page'    => 'cloudinary',
			'section' => 'edit-asset',
			'asset'   => $item['post_id'],
		);

		$return = array(
			'ID'              => $item['post_id'],
			'key'             => $item['id'],
			'local_url'       => $item['sized_url'],
			'short_url'       => $url,
			'active'          => 'enable' === $item['post_state'],
			'preview'         => $preview,
			'data'            => $item,
			'base'            => $parts[0],
			'file'            => ! empty( $parts[1] ) ? $parts[1] : wp_basename( $item['sized_url'] ),
			'size'            => $break,
			'transformations' => $item['transformations'] ? $item['transformations'] : null,
			'text_overlay'    => $item['text_overlay'] ? $item['text_overlay'] : null,
			'image_overlay'   => $item['image_overlay'] ? $item['image_overlay'] : null,
			'edit_url'        => admin_url( add_query_arg( $args, 'admin.php' ) ),
		);

		return $return;
	}

	/**
	 * Create a new asset item.
	 *
	 * @param string $url       The assets url.
	 * @param int    $parent_id The asset parent ID.
	 *
	 * @return false|int|\WP_Error
	 */
	protected function create_asset( $url, $parent_id ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$full_url  = urldecode( Utils::home_url() . wp_parse_url( $url, PHP_URL_PATH ) );
		$file_path = urldecode( str_replace( Utils::home_url(), untrailingslashit( ABSPATH ), $full_url ) );
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		$base        = get_post( $parent_id )->post_title;
		$size        = getimagesize( $file_path );
		$size        = ! empty( $size[0] ) && ! empty( $size[1] ) ? $size[0] . 'x' . $size[1] : '0x0'; // phpcs:ignore PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound,PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found
		$hash_name   = md5( $url );
		$wp_filetype = wp_check_filetype( wp_basename( $url ), wp_get_mime_types() );
		$args        = array(
			'post_title'     => $url,
			'post_content'   => '',
			'post_name'      => $hash_name,
			'post_mime_type' => $wp_filetype['type'],
			'post_type'      => self::POST_TYPE_SLUG,
			'post_parent'    => $parent_id,
			'post_status'    => 'inherit',
		);
		$id          = wp_insert_post( $args );

		// Create attachment meta.
		update_attached_file( $id, $file_path );
		wp_generate_attachment_metadata( $id, $file_path );

		// Init the auto sync.
		Delivery::create_size_relation( $id, Utils::clean_url( $url, true ), $size, $base );
		Delivery::update_size_relations_state( $id, 'enable' );
		$this->media->sync->set_signature_item( $id, 'delivery' );
		$this->media->sync->get_sync_type( $id );
		$this->media->sync->add_to_sync( $id );

		return $id;
	}

	/**
	 * Register the post type.
	 */
	protected function register_post_type() {
		$args            = array(
			'label'               => __( 'Cloudinary Asset', 'cloudinary' ),
			'description'         => __( 'Post type to represent a non-media library asset.', 'cloudinary' ),
			'labels'              => array(),
			'supports'            => false,
			'hierarchical'        => true,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capability_type'     => 'page',
		);
		$this->post_type = register_post_type( self::POST_TYPE_SLUG, $args ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Setup the class.
	 *
	 * @hook cloudinary_init_settings
	 */
	public function setup() {
		if ( is_user_logged_in() && is_admin() && ! Utils::is_rest_api() ) {
			$this->update_asset_paths();
		}

		Cron::register_process( 'update_asset_paths', array( $this, 'update_asset_paths' ), DAY_IN_SECONDS );
		$this->activate_parents();
	}

	/**
	 * Returns the setting definitions.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {
		$pages['connect']['settings'][] = array(
			'type'                => 'panel',
			'title'               => __( 'Additional Asset Sync Settings', 'cloudinary' ),
			'slug'                => 'cache',
			'option_name'         => 'site_cache',
			'requires_connection' => true,
			'collapsible'         => 'open',
			'attributes'          => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'               => 'on_off',
				'slug'               => 'enable',
				'optimisation_title' => __( 'Additional asset sync settings', 'cloudinary' ),
				'tooltip_text'       => __( 'Enabling additional asset syncing will sync the toggled assets with Cloudinary to make use of advanced optimization and CDN delivery functionality.', 'cloudinary' ),
				'description'        => __( 'Enable additional asset syncing', 'cloudinary' ),
				'default'            => 'off',
			),
			array(
				'type'       => 'button',
				'slug'       => 'cld_purge_all',
				'attributes' => array(
					'type'        => 'button',
					'html_button' => array(
						'disabled' => 'disabled',
						'style'    => 'width: 100px',
					),
				),
				'label'      => 'Purge all',
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_plugin_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_theme_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_wp_settings(),
			),
			array(
				'slug' => 'assets',
				'type' => 'frame',
				$this->add_content_settings(),
			),
		);

		$pages['connect']['settings'][] = array(
			'type'        => 'panel',
			'title'       => __( 'External Asset Sync Settings', 'cloudinary' ),
			'option_name' => 'additional_domains',
			'collapsible' => 'open',
			array(
				'slug' => 'cache_external',
				'type' => 'frame',
				$this->add_external_settings(),
			),
		);

		return $pages;
	}

	/**
	 * Checks if the URL is an asset.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_asset( $url ) {
		global $wpdb;

		// Bail early if it's not an URL.
		if ( empty( parse_url( $url, PHP_URL_HOST ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			return false;
		}

		$url = Utils::clean_url( $url );

		$wpdb->cld_table = Utils::get_relationship_table();
		$prepare         = $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->cld_table WHERE sized_url = %s AND sync_type = 'asset';", $url );
		$result          = $wpdb->get_var( $prepare ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		return (bool) $result;
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array
	 */
	protected function get_plugins_table() {

		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();
		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Plugin', 'cloudinary' ),
			'main'  => array(
				'cache_all_plugins',
			),
		);
		foreach ( $active as $plugin_path ) {
			$dir    = wp_basename( dirname( $plugin_path ) );
			$plugin = $dir . '/' . wp_basename( $plugin_path );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}
			$slug       = sanitize_title_with_dashes( Utils::pathinfo( $plugin, PATHINFO_FILENAME ) );
			$plugin_url = plugins_url( $plugin );
			$details    = $plugins[ $plugin ];
			$rows[]     = array(
				'slug'    => $slug,
				'title'   => $details['Name'],
				'url'     => dirname( $plugin_url ),
				'version' => $details['Version'],
				'main'    => array(
					'plugins.enabled',
				),
			);
		}

		return $rows;
	}

	/**
	 * Add the plugin cache settings page.
	 */
	protected function add_plugin_settings() {

		$plugins_setup = $this->get_plugins_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Plugins', 'cloudinary' ),
			'collapsible' => 'closed',
			'slug'        => 'plugins',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$plugins_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the theme table.
	 *
	 * @return array
	 */
	protected function get_theme_table() {

		$theme  = wp_get_theme();
		$themes = array(
			$theme,
		);
		if ( $theme->parent() ) {
			$themes[] = $theme->parent();
		}
		$rows = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Theme', 'cloudinary' ),
			'main'  => array(
				'cache_all_themes',
			),
		);
		// Active Theme.
		foreach ( $themes as $theme ) {
			$theme_location = $theme->get_stylesheet_directory();
			$theme_slug     = wp_basename( dirname( $theme_location ) ) . '/' . wp_basename( $theme_location );
			$slug           = sanitize_title_with_dashes( Utils::pathinfo( $theme_slug, PATHINFO_FILENAME ) );
			$rows[]         = array(
				'slug'    => $slug,
				'title'   => $theme->get( 'Name' ),
				'url'     => $theme->get_stylesheet_directory_uri(),
				'version' => $theme->get( 'Version' ),
				'main'    => array(
					'themes.enabled',
				),
			);
		}

		return $rows;
	}

	/**
	 * Add Theme Settings page.
	 */
	protected function add_theme_settings() {

		$theme_setup = $this->get_theme_table();
		$params      = array(
			'type'        => 'panel',
			'title'       => __( 'Themes', 'cloudinary' ),
			'slug'        => 'themes',
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all assets from active theme.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$theme_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_wp_table() {

		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'WordPress', 'cloudinary' ),
			'main'  => array(
				'cache_all_wp',
			),
		);
		$version = get_bloginfo( 'version' );
		// Admin folder.
		$rows[] = array(
			'slug'    => 'wp_admin',
			'title'   => __( 'WordPress Admin', 'cloudinary' ),
			'url'     => admin_url(),
			'version' => $version,
		);
		// Includes folder.
		$rows[] = array(
			'slug'    => 'wp_includes',
			'title'   => __( 'WordPress Includes', 'cloudinary' ),
			'url'     => includes_url(),
			'version' => $version,
			'main'    => array(
				'wordpress.enabled',
			),
		);

		return $rows;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_wp_settings() {

		$wordpress_setup = $this->get_wp_table();
		$params          = array(
			'type'        => 'panel',
			'title'       => __( 'WordPress', 'cloudinary' ),
			'slug'        => 'wordpress',
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all assets from WordPress core.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$wordpress_setup,
			),
		);

		return $params;
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_content_table() {

		$uploads = wp_get_upload_dir();
		$rows    = array(
			'slug'  => 'paths',
			'type'  => 'asset',
			'title' => __( 'Content', 'cloudinary' ),
			'main'  => array(
				'cache_all_content',
			),
		);
		$rows[]  = array(
			'slug'    => 'wp_content',
			'title'   => __( 'Uploads', 'cloudinary' ),
			'url'     => $uploads['baseurl'],
			'version' => 0,
			'main'    => array(
				'content.enabled',
			),
		);

		return $rows;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_content_settings() {

		$content_setup = $this->get_content_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Content', 'cloudinary' ),
			'slug'        => 'content',
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'enabled',
				'description' => __( 'Deliver all content assets from WordPress Media Library.', 'cloudinary' ),
				'default'     => 'off',
				'main'        => array(
					'enable',
				),
			),
			array(
				'type' => 'group',
				$content_setup,
			),
		);

		return $params;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_external_settings() {

		$params = array(
			array(
				'type'         => 'on_off',
				'slug'         => 'external_assets',
				'description'  => __( 'Enable external assets', 'cloudinary' ),
				'tooltip_text' => __( 'Enabling external assets allows you to sync assets from specific external sources with Cloudinary.', 'cloudinary' ),
				'default'      => 'off',
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'external_assets' => true,
				),
				array(
					'type'         => 'tags_input',
					'title'        => __( 'Domains for each external source.', 'cloudinary' ),
					'slug'         => 'uploadable_domains',
					'format'       => 'host',
					'placeholder'  => __( 'Enter a domain', 'cloudinary' ),
					'tooltip_text' => __( 'Press ENTER or SPACE or type comma or tab to continue.', 'cloudinary' ),
				),
			),
		);

		return $params;
	}
}
