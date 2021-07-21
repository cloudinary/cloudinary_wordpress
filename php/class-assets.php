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
use Cloudinary\Utils;

/**
 * Class Assets
 *
 * @package Cloudinary
 */
class Assets extends Settings_Component {

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
	protected $media;

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
	 * Holds a list of found urls of asset parents.
	 *
	 * @var array
	 */
	protected $found_urls;

	/**
	 * Holds the ID's of assets.
	 *
	 * @var array
	 */
	protected $asset_ids;
	/**
	 * Holds the list of cache points requiring meta updates.
	 *
	 * @var array
	 */
	public $meta_updates = array();

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
	 * Assets constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->media  = $plugin->get_component( 'media' );
		$this->init();
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
		add_filter( 'cloudinary_is_local_asset_url', array( $this, 'check_asset' ), 10, 2 );
		add_filter( 'cloudinary_delivery_get_id', array( $this, 'get_asset_id_from_tag' ), 10, 2 );
		add_filter( 'cloudinary_is_media', array( $this, 'is_media' ), 10, 2 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'add_sync_type' ) );
		add_filter( 'update_post_metadata', array( $this, 'update_meta' ), 10, 4 );
		add_filter( 'add_post_metadata', array( $this, 'update_meta' ), 10, 4 );
		add_filter( 'get_post_metadata', array( $this, 'get_meta' ), 10, 3 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_meta' ), 10, 4 );
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'no_sizes' ), PHP_INT_MAX, 3 );
		// Actions.
		add_action( 'cloudinary_init_settings', array( $this, 'setup' ) );
		add_action( 'pre_get_posts', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'add_url_replacements' ), 20 );
		add_action( 'shutdown', array( $this, 'meta_updates' ) );

	}

	/**
	 * Check if the post is a asset post type.
	 *
	 * @param int $post_id The ID to check.
	 *
	 * @return bool
	 */
	public static function is_asset_type( $post_id ) {
		return self::POST_TYPE_SLUG === get_post_type( $post_id );
	}

	/**
	 * Filter out sizes for assets.
	 *
	 * @param array $new_sizes     The sizes to remove.
	 * @param array $image_meta    The image meta.
	 * @param int   $attachment_id The asset ID.
	 *
	 * @return array
	 */
	public function no_sizes( $new_sizes, $image_meta, $attachment_id ) {
		if ( self::is_asset_type( $attachment_id ) ) {
			$new_sizes = array();
		}

		return $new_sizes;
	}

	/**
	 * Update our cache point meta data.
	 *
	 * @hook update_post_metadata
	 *
	 * @param null|bool $check      The check to allow short circuit of get_metadata.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 *
	 * @return bool|null
	 */
	public function update_meta( $check, $object_id, $meta_key, $meta_value ) {

		if ( self::is_asset_type( $object_id ) ) {
			$meta = $this->get_meta_cache( $object_id );
			if ( ! isset( $meta[ $meta_key ] ) || $meta_value !== $meta[ $meta_key ] ) {
				$meta[ $meta_key ] = $meta_value;
				$this->set_meta_cache( $object_id, $meta );
			}
		}

		return $check;
	}

	/**
	 * Delete our cache point meta data.
	 *
	 * @param null|bool $check      The check to allow short circuit of get_metadata.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 *
	 * @return bool
	 */
	public function delete_meta( $check, $object_id, $meta_key, $meta_value ) {

		if ( self::is_asset_type( $object_id ) ) {
			$meta = $this->get_meta_cache( $object_id );
			if ( isset( $meta[ $meta_key ] ) && ( $meta[ $meta_key ] === $meta_value || empty( $meta_value ) ) ) {
				unset( $meta[ $meta_key ] );
				$this->set_meta_cache( $object_id, $meta );
			}
		}

		return $check;
	}

	/**
	 * Get our cache point meta data.
	 *
	 * @param null|bool $check     The check to allow short circuit of get_metadata.
	 * @param int       $object_id The object ID.
	 * @param string    $meta_key  The meta key.
	 *
	 * @return mixed
	 */
	public function get_meta( $check, $object_id, $meta_key ) {

		if ( self::is_asset_type( $object_id ) ) {
			$meta  = $this->get_meta_cache( $object_id );
			$value = null;
			if ( empty( $meta_key ) ) {
				$value = $meta;
			} elseif ( isset( $meta[ $meta_key ] ) ) {
				$value[] = $meta[ $meta_key ];
			}

			if ( ! is_null( $value ) ) {
				// Only return if we found meta, else allow to go into core meta.
				return $value;
			}
		}

		return $check;
	}

	/**
	 * Get meta data for a cache point.
	 *
	 * @param int $object_id The post ID.
	 *
	 * @return mixed
	 */
	protected function get_meta_cache( $object_id ) {
		$meta = wp_cache_get( $object_id, 'cloudinary_asset' );
		if ( ! $meta ) {
			$post = get_post( $object_id );
			$meta = json_decode( $post->post_content, true );
			wp_cache_add( $object_id, $meta, 'cloudinary_asset' );
		}

		return $meta;
	}

	/**
	 * Set meta data for a cache point.
	 *
	 * @param int   $object_id The post ID.
	 * @param mixed $meta      The meta to set.
	 *
	 * @return bool
	 */
	protected function set_meta_cache( $object_id, $meta ) {
		if ( ! in_array( $object_id, $this->meta_updates, true ) ) {
			$this->meta_updates[] = $object_id;
		}

		return wp_cache_replace( $object_id, $meta, 'cloudinary_asset' );
	}

	/**
	 * Compiles all metadata and preps upload at shutdown.
	 */
	public function meta_updates() {

		foreach ( $this->meta_updates as $id ) {

			$meta = $this->get_meta_cache( $id );

			$params = array(
				'ID'           => $id,
				'post_content' => wp_json_encode( $meta ),
			);
			wp_update_post( $params );
		}
		// Prep the upload for un-synced items.
		if ( ! empty( $this->to_upload ) ) {
			$api = $this->cache->plugin->get_component( 'api' );
			if ( $api ) {
				$api->background_request( 'upload_cache', array( 'ids' => $this->to_upload ), 'POST' );
			}
		}
	}

	/**
	 * Set urls to be replaced.
	 */
	public function add_url_replacements() {
		if ( $this->asset_ids ) {
			foreach ( $this->asset_ids as $url => $id ) {
				$cloudinary_url = $this->media->cloudinary_url( $id );
				if ( $cloudinary_url ) {
					String_Replace::replace( $url, $cloudinary_url );
				}
			}
		}
	}

	/**
	 * Connect our post type to the sync query, to allow it to be queued.
	 *
	 * @param \WP_Query $query The Query.
	 */
	public function connect_post_type( $query ) {
		if ( ! $query->is_main_query() && 'attachment' === $query->get( 'post_type' ) && ! empty( $query->get( 'cloudinary_sync' ) ) ) {
			$query->set( 'post_type', array( 'attachment', self::POST_TYPE_SLUG ) );
		}
	}

	/**
	 * Register an asset path.
	 *
	 * @param string $path    The path/URL to register.
	 * @param string $version The version.
	 */
	public static function register_asset_path( $path, $version ) {
		$assets = get_plugin_instance()->get_component( 'assets' );
		if ( $assets ) {
			$asset_path = $assets->get_asset_parent( $path );
			if ( null === $asset_path ) {
				$asset_parent_id = $assets->create_asset_parent( $path, $version );
				if ( is_wp_error( $asset_parent_id ) ) {
					return; // Bail.
				}
				$asset_path = get_post( $asset_parent_id );
			}
			// Check and update version if needed.
			if ( $assets->media->get_post_meta( $asset_path->ID, Sync::META_KEYS['version'], true ) !== $version ) {
				$assets->media->update_post_meta( $asset_path->ID, Sync::META_KEYS['version'], $version );
			}
			$assets->activate_parent( $path );
		}
	}

	/**
	 * Activate a parent asset path.
	 *
	 * @param string $url The path to activate.
	 */
	public function activate_parent( $url ) {
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$this->active_parents[ $url ] = $this->asset_parents[ $url ];
		}
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
		$args      = array(
			'post_title'  => $path,
			'post_name'   => md5( $path ),
			'post_type'   => self::POST_TYPE_SLUG,
			'post_status' => 'publish',
		);
		$parent_id = wp_insert_post( $args );
		if ( $parent_id ) {
			$this->media->update_post_meta( $parent_id, Sync::META_KEYS['version'], $version );
		}

		return $parent_id;
	}

	/**
	 * Generate the signature for sync.
	 *
	 * @param int $asset_id The attachment/asset ID.
	 *
	 * @return string
	 */
	public function generate_file_signature( $asset_id ) {
		$asset = get_post( $asset_id );

		// The signature is the URL + the parents version. As the version  changes, the signature is invalid, and re-synced.
		return $asset->post_title . $this->media->get_post_meta( $asset->post_parent, Sync::META_KEYS['version'], true );
	}

	/**
	 * Upload an asset.
	 *
	 * @param int $asset_id The asset ID to upload.
	 *
	 * @return array|\WP_Error
	 */
	public function upload( $asset_id ) {
		$connect           = $this->plugin->get_component( 'connect' );
		$asset             = get_post( $asset_id );
		$path              = trim( wp_normalize_path( str_replace( home_url(), '', $asset->post_title ) ), '/' );
		$info              = pathinfo( $path );
		$cloudinary_folder = $this->media->get_cloudinary_folder( true );
		$public_id         = $cloudinary_folder . $info['dirname'] . '/' . $info['filename'];
		$options           = array(
			'unique_filename' => false,
			'overwrite'       => true,
			'resource_type'   => $this->media->get_resource_type( $asset_id ),
			'public_id'       => $public_id,
		);
		$result            = $connect->api->upload( $asset_id, $options, array() );
		if ( ! is_wp_error( $result ) && isset( $result['public_id'] ) ) {
			$this->media->update_post_meta( $asset_id, Sync::META_KEYS['public_id'], $result['public_id'] );
			$this->media->update_post_meta( $asset_id, Sync::META_KEYS['version'], $result['version'] );
			$this->media->sync->set_signature_item( $asset_id, 'file' );
			$this->media->sync->set_signature_item( $asset_id, 'cld_asset' );
			$this->media->sync->set_signature_item( $asset_id, 'cloud_name' );
			$this->media->sync->set_signature_item( $asset_id, 'storage' );
			$this->media->sync->set_signature_item( $asset_id, 'download' );
			$this->media->sync->set_signature_item( $asset_id, 'options' );
		}

		return $result;
	}

	/**
	 * Register our sync type.
	 *
	 * @param array $structs The structure of all sync types.
	 *
	 * @return array
	 */
	public function add_sync_type( $structs ) {
		$structs['cld_asset'] = array(
			'generate' => array( $this, 'generate_file_signature' ),
			'priority' => 2,
			'sync'     => array( $this, 'upload' ),
			'validate' => function ( $attachment_id ) {
				return Assets::POST_TYPE_SLUG === get_post_type( $attachment_id );
			},
			'state'    => 'uploading',
			'note'     => __( 'Uploading to Cloudinary', 'cloudinary' ),
			'required' => true,
		);

		return $structs;
	}

	/**
	 * Init asset parents.
	 */
	protected function init_asset_parents() {

		$args                = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'post_parent'    => 0,
			'posts_per_page' => 100,
			'post_status'    => 'publish',
		);
		$query               = new \WP_Query( $args );
		$this->asset_parents = array();
		foreach ( $query->get_posts() as $post ) {
			$this->asset_parents[ $post->post_title ] = $post;
		}
	}

	/**
	 * Check if the non-local URL should be added as an asset.
	 *
	 * @param bool   $is_local The is_local flag.
	 * @param string $url      The URL to check.
	 *
	 * @return bool
	 */
	public function check_asset( $is_local, $url ) {

		if ( false === $is_local ) {

			foreach ( $this->active_parents as $asset_parent ) {
				if (
					substr( $url, 0, strlen( $asset_parent->post_title ) ) === $asset_parent->post_title
					&& true === $this->syncable_asset( basename( $url ) )
				) {
					$is_local                                = true;
					$this->found_urls[ $asset_parent->ID ][] = $url;
					break;
				}
			}
		}

		return $is_local;
	}

	/**
	 * Check if the asset is syncable.
	 *
	 * @param string $filename The filename to check.
	 *
	 * @return bool
	 */
	protected function syncable_asset( $filename ) {
		// Check with paths.
		$allowed_kinds = array(
			'image',
			'audio',
			'video',
		);
		$type          = wp_check_filetype( $filename );

		return false !== $type['type'] && in_array( strstr( $type['type'], '/', true ), $allowed_kinds );
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
		if ( self::is_asset_type( $asset_id ) ) {
			$dirs = wp_get_upload_dir();
			$file = str_replace( trailingslashit( $dirs['basedir'] ), ABSPATH, $file );
		}

		return $file;
	}

	/**
	 * Check to see if the post is a media item.
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
	 * Build asset ID's from found urls, and create missing items.
	 */
	public function build_asset_ids() {

		$names     = array();
		$to_create = array();
		foreach ( $this->found_urls as $parent => $urls ) {
			foreach ( $urls as $url ) {
				$names[]           = md5( $url );
				$to_create[ $url ] = $parent;
			}
		}

		$args  = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'posts_per_page' => 100,
			'post_status'    => 'inherit',
			'post_name__in'  => $names,
		);
		$query = new \WP_Query( $args );
		foreach ( $query->get_posts() as $post ) {
			$this->asset_ids[ $post->post_title ] = $post->ID;
			unset( $to_create[ $post->post_title ] );
		}
		if ( ! empty( $to_create ) ) {
			foreach ( $to_create as $url => $parent ) {
				$this->create_asset( $url, $parent );
			}
		}
	}

	/**
	 * Get an asset parent.
	 *
	 * @param string $url The URL of the parent.
	 *
	 * @return \WP_Post|null
	 */
	public function get_asset_parent( $url ) {
		$url    = trailingslashit( $url );
		$parent = null;
		if ( isset( $this->asset_parents[ $url ] ) ) {
			$parent = $this->asset_parents[ $url ];
		}

		return $parent;
	}

	/**
	 * Get an asset item.
	 *
	 * @param string $url The asset url.
	 *
	 * @return null|\WP_Post
	 */
	public function get_asset_id( $url ) {
		if ( is_null( $this->asset_ids ) ) {
			$this->build_asset_ids();
		}

		return isset( $this->asset_ids[ $url ] ) ? $this->asset_ids[ $url ] : null;
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
		$file_path = str_replace( home_url(), untrailingslashit( ABSPATH ), $url );
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		$hash_name   = md5( $url );
		$wp_filetype = wp_check_filetype( basename( $url ), wp_get_mime_types() );
		$file_string = str_replace( ABSPATH, '', $file_path );
		$data        = array(
			'_wp_attached_file' => $file_string,
		);
		$args        = array(
			'post_title'     => $url,
			'post_content'   => wp_json_encode( $data ),
			'post_name'      => $hash_name,
			'post_mime_type' => $wp_filetype['type'],
			'post_type'      => self::POST_TYPE_SLUG,
			'post_parent'    => $parent_id,
			'post_status'    => 'inherit',
		);
		$id          = wp_insert_post( $args );

		// Create attachment meta.
		wp_generate_attachment_metadata( $id, $file_path );

		// Init the auto sync.
		$this->media->cloudinary_id( $id );

		return $id;
	}

	/**
	 * Try get an asset ID from an asset tag.
	 *
	 * @param int    $id    The ID from the filter.
	 * @param string $asset The asset HTML tag.
	 *
	 * @return false|int
	 */
	public function get_asset_id_from_tag( $id, $asset ) {

		if ( false === $id && ! empty( $this->found_urls ) ) {

			$atts = Utils::get_tag_attributes( $asset );
			if ( ! empty( $atts['src'] ) ) {
				$url    = Delivery::clean_url( $atts['src'] );
				$has_id = $this->get_asset_id( $url );
				if ( ! empty( $has_id ) ) {
					$id = $has_id;
				}
			}
		}

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
		$this->post_type = register_post_type( self::POST_TYPE_SLUG, $args );
	}

	/**
	 * Setup the class.
	 */
	public function setup() {

		$settings = $this->settings->get_settings_by_type( 'folder_table' );
		foreach ( $settings as $setting ) {
			$paths = $setting->get_param( 'root_paths' );
			foreach ( $paths as $slug => $conf ) {
				if ( 'on' === $this->settings->get_value( $slug ) ) {
					self::register_asset_path( trailingslashit( $conf['url'] ), $conf['version'] );
				}
			}
		}
	}

	/**
	 * Returns the setting definitions.
	 *
	 * @return array|null
	 */
	public function settings() {
		$args = array(
			'type'       => 'page',
			'menu_title' => __( 'Site Cache', 'cloudinary' ),
			'tabs'       => array(
				'main_cache_page' => array(
					'page_title' => __( 'Site Cache', 'cloudinary' ),
					array(
						'slug'       => 'cache_paths',
						'type'       => 'panel',
						'title'      => __( 'Cache Settings', 'cloudinary' ),
						'attributes' => array(
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
							'type'         => 'on_off',
							'slug'         => 'enable_full_site_cache',
							'title'        => __( 'Full CDN', 'cloudinary' ),
							'tooltip_text' => __(
								'Deliver all assets from Cloudinary.',
								'cloudinary'
							),
							'description'  => __( 'Enable caching site assets.', 'cloudinary' ),
							'default'      => 'off',
						),
						array(
							'slug' => 'cache_plugins',
							'type' => 'frame',
							$this->add_plugin_settings(),
						),
						array(
							'slug' => 'cache_themes',
							'type' => 'frame',
							$this->add_theme_settings(),
						),
						array(
							'slug' => 'cache_wordpress',
							'type' => 'frame',
							$this->add_wp_settings(),
						),
						array(
							'slug' => 'cache_content',
							'type' => 'frame',
							$this->add_content_settings(),
						),
					),
					array(
						'type'       => 'submit',
						'attributes' => array(
							'wrap' => array(
								'class' => array(
									'full-width',
								),
							),
						),
					),
				),
			),
		);

		return $args;
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array
	 */
	protected function get_plugins_table() {

		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();
		$rows    = array();
		foreach ( $active as $plugin_path ) {
			$dir    = basename( dirname( $plugin_path ) );
			$plugin = $dir . '/' . basename( $plugin_path );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}
			$slug          = sanitize_file_name( $plugin );
			$plugin_url    = plugins_url( $plugin );
			$details       = $plugins[ $plugin ];
			$rows[ $slug ] = array(
				'title'   => $details['Name'],
				'url'     => dirname( $plugin_url ),
				'version' => $details['Version'],
			);
		}

		return array(
			'slug'       => 'plugin_files',
			'type'       => 'folder_table',
			'title'      => __( 'Plugin', 'cloudinary' ),
			'master'     => array(
				'cache_all_plugins',
			),
			'root_paths' => $rows,
		);

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
				'slug'        => 'cache_all_plugins',
				'description' => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
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
		$rows = array();
		// Active Theme.
		foreach ( $themes as $theme ) {
			$theme_location = $theme->get_stylesheet_directory();
			$theme_slug     = basename( dirname( $theme_location ) ) . '/' . basename( $theme_location );
			$slug           = sanitize_file_name( $theme_slug );
			$rows[ $slug ]  = array(
				'title'   => $theme->get( 'Name' ),
				'url'     => $theme->get_stylesheet_directory_uri(),
				'version' => $theme->get( 'Version' ),
			);
		}

		return array(
			'slug'       => 'theme_files',
			'type'       => 'folder_table',
			'title'      => __( 'Theme', 'cloudinary' ),
			'root_paths' => $rows,
			'master'     => array(
				'cache_all_themes',
			),
		);
	}

	/**
	 * Add Theme Settings page.
	 */
	protected function add_theme_settings() {

		$theme_setup = $this->get_theme_table();
		$params      = array(
			'type'        => 'panel',
			'title'       => __( 'Themes', 'cloudinary' ),
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
				'slug'        => 'cache_all_themes',
				'description' => __( 'Deliver all assets from active theme.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
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

		$rows    = array();
		$version = get_bloginfo( 'version' );
		// Admin folder.
		$rows['wp_admin'] = array(
			'title'   => __( 'WordPress Admin', 'cloudinary' ),
			'url'     => admin_url(),
			'version' => $version,
		);
		// Includes folder.
		$rows['wp_includes'] = array(
			'title'   => __( 'WordPress Includes', 'cloudinary' ),
			'url'     => includes_url(),
			'version' => $version,
		);

		return array(
			'slug'       => 'wordpress_files',
			'type'       => 'folder_table',
			'title'      => __( 'WordPress', 'cloudinary' ),
			'root_paths' => $rows,
			'master'     => array(
				'cache_all_wp',
			),
		);
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_wp_settings() {

		$wordpress_setup = $this->get_wp_table();
		$params          = array(
			'type'        => 'panel',
			'title'       => __( 'WordPress', 'cloudinary' ),
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
				'slug'        => 'cache_all_wp',
				'description' => __( 'Deliver all assets from WordPress core.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
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

		$rows               = array();
		$uploads            = wp_get_upload_dir();
		$rows['wp_content'] = array(
			'title'   => __( 'Uploads', 'cloudinary' ),
			'url'     => $uploads['baseurl'],
			'version' => 0,
		);

		return array(
			'slug'       => 'content_files',
			'type'       => 'folder_table',
			'title'      => __( 'Content', 'cloudinary' ),
			'root_paths' => $rows,
			'master'     => array(
				'cache_all_content',
			),
		);
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_content_settings() {

		$content_setup = $this->get_content_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Content', 'cloudinary' ),
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
				'slug'        => 'cache_all_content',
				'description' => __( 'Deliver all content assets from WordPress Media Library.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
				),
			),
			array(
				'type' => 'group',
				$content_setup,
			),
		);

		return $params;
	}

}
