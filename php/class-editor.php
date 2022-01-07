<?php
/**
 * Editor class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use Cloudinary\Settings\Setting;
use \Cloudinary\Media;

/**
 * Class Editor
 */
class Editor implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Editor Settings UI
	 *
	 * @var Settings
	 */
	protected $editor;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the editing file paths to urls.
	 *
	 * @var array
	 */
	protected $editing = array();

	/**
	 * Slug for editor
	 *
	 * @var string
	 */
	const EDITOR_SLUG = '_cld_editor';

	/**
	 * Editor constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Setup the hooks and base_url if configured.
	 */
	public function setup() {
		if ( $this->plugin->settings->get_param( 'connected' ) ) {
			$this->media = $this->plugin->get_component( 'media' );
			add_action( 'edit_form_after_title', array( $this, 'edit_form_image_editor' ), 9 );
			$editor_params = array(
				'storage' => 'transient',
			);
			$this->editor  = new Settings( self::EDITOR_SLUG, $editor_params );
			add_action( 'current_screen', array( $this, 'init_editor' ) );
		}
	}

	/**
	 * Initialize the editor when needed.
	 */
	public function init_editor() {
		$screen = get_current_screen();
		if ( $screen && 'attachment' === $screen->id ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Register the endpoints.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['edit_asset']    = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_edit_asset' ),
			'permission_callback' => array( '\Cloudinary\REST_API', 'rest_can_manage_options' ),
			'args'                => array(),
		);
		$endpoints['restore_asset'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_restore_asset' ),
			'permission_callback' => array( '\Cloudinary\REST_API', 'rest_can_manage_options' ),
			'args'                => array(),
		);

		return $endpoints;
	}

	/**
	 * Ensure the resize file is the Cloudinary URL.
	 *
	 * @param string $file          The file path.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_resized_url( $file, $attachment_id ) {

		if ( isset( $this->editing[ $file ] ) ) {
			$file = $this->editing[ $file ];
		}

		return $file;
	}

	/**
	 * Edit an asset.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function rest_restore_asset( $request ) {

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = $request->get_param( 'ID' );

		wp_restore_image( $attachment_id );
		$this->media->sync->get_sync_type( $attachment_id, false );
		$this->media->sync->add_to_sync( $attachment_id );
		$public_id = $this->media->get_public_id( $attachment_id );
		$return    = array(
			'publicId'   => $public_id,
			'previewUrl' => $this->media->cloudinary_url( $attachment_id, 'raw' ),
			'original'   => self::is_original( $attachment_id ),
		);

		return rest_ensure_response( $return );
	}

	/**
	 * Edit an asset.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function rest_edit_asset( $request ) {

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id              = $request->get_param( 'ID' );
		$download_url               = $request->get_param( 'imageUrl' );
		$preview_url                = $download_url;
		$attachment_transformations = $this->media->get_transformations( $attachment_id, array(), true );
		if ( ! empty( $attachment_transformations ) ) {
			$attachment_transformations = Api::generate_transformation_string( $attachment_transformations );
			$download_url               = str_replace( trailingslashit( $attachment_transformations ), '', $download_url );
		}
		$_REQUEST['history'] = wp_json_encode( array( 'none' => true ) );
		$_REQUEST['target']  = 'all'; // @todo: Make selection for which to edit.

		$file                   = get_attached_file( $attachment_id );
		$url                    = wp_get_attachment_url( $attachment_id );
		$this->editing[ $file ] = $download_url;
		$this->editing[ $url ]  = $download_url;

		add_filter( 'load_image_to_edit_path', array( $this, 'get_resized_url' ), 10, 2 );
		wp_save_image( $attachment_id );
		$this->media->sync->get_sync_type( $attachment_id, false );
		$this->media->sync->add_to_sync( $attachment_id );
		$public_id = $this->media->get_public_id( $attachment_id );
		$return    = array(
			'publicId'   => $public_id,
			'previewUrl' => $preview_url,
			'original'   => self::is_original( $attachment_id ),
		);

		return rest_ensure_response( $return );
	}

	/**
	 * Embed the Cloudinary Editor.
	 *
	 * @param \WP_Post $attachment The attachment post.
	 */
	public function edit_form_image_editor( $attachment ) {
		if ( 'attachment' !== $attachment->post_type ) {
			return;
		}
		$struct = array(
			'type'       => 'editor',
			'attachment' => $attachment,
		);
		$editor = new Setting( 'editor', null, $struct );
		$editor->get_component()->render( true );
	}

	/**
	 * Check if the file is the original or an edited.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public static function is_original( $attachment_id ) {
		$sizes       = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$is_original = true;
		if ( ! empty( $sizes ) && ! empty( $sizes['full-orig'] ) ) {
			$meta        = wp_get_attachment_metadata( $attachment_id );
			$is_original = basename( $meta['file'] ) === $sizes['full-orig']['file'];
		}

		return $is_original;
	}
}
