<?php
/**
 * Cloudinary non media library assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;
use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Class Assets
 *
 * @package Cloudinary
 */
class Assets {

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

		// Actions.
		add_action( 'cloudinary_init_settings', array( $this, 'setup' ) );
		add_action( 'pre_get_posts', array( $this, 'connect_post_type' ) );
		add_action( 'cloudinary_string_replace', array( $this, 'add_url_replacements' ), 20 );
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
	 * Connect our post type to the attachments post type, when we are doing sync and counting.
	 *
	 * @param \WP_Query $query The Query.
	 */
	public function connect_post_type( $query ) {
		if ( 'attachment' === $query->get( 'post_type' ) ) {
			// Check if its not the main query and theres a meta_query (which we use when syncing).
			if ( ! $query->is_main_query() && ! empty( $query->get( 'meta_query' ) ) ) {
				$query->set( 'post_type', array( 'attachment', self::POST_TYPE_SLUG ) );
			}
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
		$connect   = $this->plugin->get_component( 'connect' );
		$asset     = get_post( $asset_id );
		$path      = trim( wp_normalize_path( str_replace( home_url(), '', $asset->post_title ) ), '/' );
		$info      = pathinfo( $path );
		$public_id = $info['dirname'] . '/' . $info['filename'];
		$options   = array(
			'unique_filename' => false,
			'overwrite'       => true,
			'resource_type'   => $this->media->get_resource_type( $asset_id ),
			'public_id'       => $public_id,
		);
		$result    = $connect->api->upload( $asset_id, $options, array() );
		if ( ! is_wp_error( $result ) && isset( $result['public_id'] ) ) {
			$this->media->update_post_meta( $asset_id, Sync::META_KEYS['public_id'], $result['public_id'] );
			$this->media->sync->set_signature_item( $asset_id, 'file' );
			$this->media->sync->set_signature_item( $asset_id, 'cld_asset' );
			$this->media->sync->set_signature_item( $asset_id, 'cloud_name' );
			$this->media->sync->set_signature_item( $asset_id, 'storage' );
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
			foreach ( $this->asset_parents as $asset_parent ) {
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
		if ( '' === $file && self::POST_TYPE_SLUG === get_post_type( $asset_id ) ) {
			$post = get_post( $asset_id );
			$file = $post->post_content;
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
		if ( false === $is_media && self::POST_TYPE_SLUG === get_post_type( $attachment_id ) ) {
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

		$file_string = str_replace( home_url(), untrailingslashit( ABSPATH ), $url );
		if ( ! file_exists( $file_string ) ) {
			return false;
		}
		$hash_name   = md5( $url );
		$wp_filetype = wp_check_filetype( basename( $url ), wp_get_mime_types() );

		$args = array(
			'post_title'     => $url,
			'post_name'      => $hash_name,
			'post_mime_type' => $wp_filetype['type'],
			'post_type'      => self::POST_TYPE_SLUG,
			'post_parent'    => $parent_id,
			'post_status'    => 'inherit',
		);
		$id   = wp_insert_post( $args );

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

		// Setup plugins.
		$active_plugins = wp_get_active_and_valid_plugins();
		$plugins        = get_plugins();
		foreach ( $active_plugins as $plugin ) {
			$plugin_dir_file = basename( dirname( $plugin ) ) . '/' . basename( $plugin );
			$data            = $plugins[ $plugin_dir_file ];
			$url             = plugin_dir_url( $plugin_dir_file );
			self::register_asset_path( $url, $data['Version'] );
		}

		// Setup theme.
		$theme           = wp_get_theme();
		$style_sheet_url = trailingslashit( $theme->get_stylesheet_directory_uri() );
		$version         = $theme->get( 'Version' );
		self::register_asset_path( $style_sheet_url, $version );
		if ( $theme->parent() ) {
			$theme           = $theme->parent();
			$style_sheet_url = trailingslashit( $theme->get_stylesheet_directory_uri() );
			$version         = $theme->get( 'Version' );
			self::register_asset_path( $style_sheet_url, $version );
		}

	}
}
