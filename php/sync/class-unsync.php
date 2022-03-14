<?php
/**
 * Unsync class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Plugin;
use Cloudinary\Sync;
use Cloudinary\Media;
use Cloudinary\Sync\Storage;
use WP_Post;

/**
 * Class Unsync
 */
class Unsync {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the Sync instance.
	 *
	 * @var Sync
	 */
	protected $sync;

	/**
	 * Holds the Storage instance.
	 *
	 * @var Storage
	 */
	protected $storage;

	/**
	 * Unsync constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Holds the unsync action keys.
	 */
	const UNSYNC_ACTION = 'cloudinary-unsync';

	/**
	 * Holds the unsync/resync toggle action keys.
	 */
	const UNSYNC_TOGGLE = 'cloudinary-sync-toggle';

	/**
	 * Register any hooks that this component needs.
	 */
	public function setup() {
		$this->media   = $this->plugin->get_component( 'media' );
		$this->sync    = $this->plugin->get_component( 'sync' );
		$this->storage = $this->plugin->get_component( 'storage' );

		if ( 'off' === $this->plugin->settings->get_value( 'auto_sync' ) ) {
			add_action( 'attachment_submitbox_misc_actions', array( $this, 'single_action' ), 11 );
			add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 11, 3 );
			add_filter( 'media_row_actions', array( $this, 'add_inline_action' ), 10, 2 );
			add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		}
	}

	/**
	 * Adds the deliver checkbox on the single image edit screen.
	 *
	 * @param WP_Post $attachment The attachment post object.
	 */
	public function single_action( $attachment ) {
		// Set url for action handling.
		$action_url = add_query_arg(
			array(
				'action'   => self::UNSYNC_TOGGLE,
				'media[]'  => $attachment->ID,
				'_wpnonce' => wp_create_nonce( 'bulk-media' ),
			),
			'upload.php'
		);
		$link_text  = $this->sync->been_synced( $attachment->ID ) ? $this->get_action_text() : __( 'Sync with Cloudinary', 'cloudinary' );
		$status     = $this->sync->filter_media_states( array(), $attachment );
		?>
		<div class="misc-pub-section misc-pub-sync-unsync">
			<?php if ( ! empty( $status ) ) : ?>
				<?php echo esc_html( array_shift( $status ) ); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $link_text ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles bulk actions for attachments.
	 *
	 * @param string $location The location to redirect after.
	 * @param string $action   The action to handle.
	 * @param array  $post_ids Post ID's to action.
	 *
	 * @return string
	 */
	public function handle_bulk_actions( $location, $action, $post_ids ) {

		$actions = array(
			self::UNSYNC_ACTION,
			self::UNSYNC_TOGGLE,
		);

		if ( in_array( $action, $actions, true ) ) {
			switch ( $action ) {
				case self::UNSYNC_ACTION:
					$post_ids = array_filter( $post_ids, array( $this->sync, 'been_synced' ) );
					foreach ( $post_ids as $id ) {
						$this->unsync_attachment( $id );
					}
					break;
				case self::UNSYNC_TOGGLE:
					$attachment_id = array_shift( $post_ids );
					if ( $this->sync->been_synced( $attachment_id ) ) {
						$this->unsync_attachment( $attachment_id );
					} else {
						$this->sync->add_to_sync( $attachment_id );
					}
					$location = get_edit_post_link( $attachment_id, 'edit' );
					break;

			}

			// This action is documented in `class-delivery.php`.
			do_action( 'cloudinary_flush_cache' );
		}

		return $location;
	}

	/**
	 * Add an inline action for manual sync.
	 *
	 * @param array    $actions All actions.
	 * @param \WP_Post $post    The current post object.
	 *
	 * @return array
	 */
	public function add_inline_action( $actions, $post ) {
		if ( $this->sync->been_synced( $post->ID ) ) {

			// Set url for action handling.
			$action_url = add_query_arg(
				array(
					'action'   => self::UNSYNC_ACTION,
					'media[]'  => $post->ID,
					'_wpnonce' => wp_create_nonce( 'bulk-media' ),
				),
				'upload.php'
			);

			// Add link th actions.
			$actions[ self::UNSYNC_ACTION ] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%2$s</a>',
				$action_url,
				$this->get_action_text()
			);
		}

		return $actions;
	}

	/**
	 * Add bulk actions.
	 *
	 * @param array $actions Current actions to add to.
	 *
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$new_action = array(
			self::UNSYNC_ACTION => $this->get_action_text(),
		);
		$actions    = array_merge( $new_action, $actions );

		return $actions;
	}

	/**
	 * Get the action text.
	 *
	 * @return string
	 */
	protected function get_action_text() {
		return __( 'Unsync from Cloudinary', 'cloudinary' );
	}

	/**
	 * Unsync an attachment from cloudinary.
	 *
	 * @param int $attachment_id The attachment to unsync.
	 */
	public function unsync_attachment( $attachment_id ) {

		$this->sync->set_pending( $attachment_id );
		$cloudinary_id = $this->media->get_cloudinary_id( $attachment_id );
		$url           = $this->media->cloudinary_url( $attachment_id, 'raw', array(), $cloudinary_id, true );
		$url           = remove_query_arg( '_i', $url );
		$storage       = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['storage'], true );
		if ( 'cld' === $storage || 'dual_low' === $storage ) {
			if ( 'dual_low' === $storage ) {
				$this->storage->remove_local_assets( $attachment_id );
			}

			$file_maybe = get_attached_file( $attachment_id );
			if ( ! file_exists( $file_maybe ) ) {
				remove_filter( 'wp_unique_filename', array( $this->storage, 'unique_filename' ), 10 );
				$date     = get_post_datetime( $attachment_id );
				$download = $this->sync->managers['download']->download_asset( $attachment_id, $url, $date->format( 'Y/m' ) );
				add_filter( 'wp_unique_filename', array( $this->storage, 'unique_filename' ), 10, 3 );
				if ( is_wp_error( $download ) ) {
					wp_die( esc_html( $download->get_error_message() ) );
				}
			}
		}

		// Remove meta data.
		$sync_key  = $this->media->get_public_id_from_url( $url, true );
		$public_id = $this->media->get_public_id( $attachment_id );
		// Delete sync keys.
		delete_post_meta( $attachment_id, '_' . md5( $sync_key ), true );
		delete_post_meta( $attachment_id, '_' . md5( 'base_' . $public_id ), true );
		foreach ( Sync::META_KEYS as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
		$this->sync->set_signature_item( $attachment_id, 'file' );
		/**
		 * Action unsyncing an attachment.
		 *
		 * @hook   cloudinary_unsync_asset
		 * @since  3.0.0
		 *
		 * @param $attachment_id {int}    The attachment ID.
		 */
		do_action( 'cloudinary_unsync_asset', $attachment_id );
	}
}
