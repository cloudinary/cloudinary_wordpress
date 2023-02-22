<?php
/**
 * Bypass class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Plugin;
use Cloudinary\Sync;
use Cloudinary\Media;
use Cloudinary\Settings;
use WP_Post;

/**
 * Class Bypass
 */
class Bypass {

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
	 * Holds the main settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Flag to determine if bypassing is allowed.
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Holds the bypass action keys.
	 */
	const BYPASS_KEYS = array(
		'wp'  => 'wp-delivery',
		'cld' => 'cld-delivery',
	);

	/**
	 * Bypass constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		// Hook into settings init, to get settings ans start hooks.
		add_action( 'cloudinary_init_settings', array( $this, 'init_settings' ) );
	}

	/**
	 * Setup the filters and actions.
	 */
	protected function setup_hooks() {
		// Start delivery bypasses if enabled.
		if ( $this->enabled ) {
			add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_filter( 'media_row_actions', array( $this, 'add_inline_action' ), 10, 2 );
			add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
			add_action( 'attachment_submitbox_misc_actions', array( $this, 'delivery_actions' ), 11 );
			add_filter( 'wp_insert_attachment_data', array( $this, 'handle_save_attachment_delivery' ), 10, 2 );
			add_filter( 'cloudinary_can_sync_asset', array( $this, 'can_sync' ), 10, 2 );
			add_filter( 'cloudinary_cache_media_asset', array( $this, 'can_sync' ), 10, 2 );
			add_filter( 'cloudinary_media_status', array( $this, 'filter_status' ), 11, 2 );
		}
	}

	/**
	 * Init the main settings.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function init_settings( $plugin ) {
		$this->media    = $plugin->get_component( 'media' );
		$this->settings = $plugin->settings;
		$this->enabled  = 'on' === $this->settings->get_value( 'auto_sync' ) && 'cld' !== $this->settings->get_value( 'offload' );
		$this->setup_hooks();
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
		$delivery = null;
		switch ( $action ) {
			case self::BYPASS_KEYS['wp']:
				$delivery = true;
				break;
			case self::BYPASS_KEYS['cld']:
				$delivery = false;
				break;
		}

		if ( ! is_null( $delivery ) ) {
			foreach ( $post_ids as $id ) {
				if ( true === $delivery ) {
					$this->media->sync->managers['unsync']->unsync_attachment( $id );
				}
				$this->set_attachment_delivery( $id, $delivery );
			}
			// This action is documented in `class-delivery.php`.
			do_action( 'cloudinary_flush_cache' );
		}

		return $location;
	}

	/**
	 * Get the actions for delivery types.
	 *
	 * @param int|null $attachment_id The attachment ID to get action or generic action.
	 *
	 * @return array
	 */
	protected function get_actions( $attachment_id = null ) {
		$actions = array();

		if (
			null === $attachment_id
			|| $this->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id )
		) {
			$actions = array(
				self::BYPASS_KEYS['wp'] => __( 'Deliver from WordPress', 'cloudinary' ),
			);
		}

		return $actions;
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

		$bypassed = $this->is_bypassed( $post->ID );
		$action   = $bypassed ? self::BYPASS_KEYS['cld'] : self::BYPASS_KEYS['wp'];
		$messages = $this->get_actions( $post->ID );
		if ( ! empty( $messages[ $action ] ) ) {

			// Set url for action handling.
			$action_url = add_query_arg(
				array(
					'action'   => $action,
					'media[]'  => $post->ID,
					'_wpnonce' => wp_create_nonce( 'bulk-media' ),
				),
				'upload.php'
			);

			// Add link th actions.
			$actions[ $action ] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%2$s</a>',
				$action_url,
				$messages[ $action ]
			);
		}

		return $actions;
	}

	/**
	 * Handle saving the deliver setting in single attachment edit.
	 *
	 * @param array $post_data The post array (unused).
	 * @param array $data      The submitted data to save.
	 *
	 * @return array
	 */
	public function handle_save_attachment_delivery( $post_data, $data ) {
		$this->set_attachment_delivery( $data['ID'], isset( $data['attachment_delivery'] ) );

		return $post_data;
	}

	/**
	 * Update an attachments delivery.
	 *
	 * @param int  $attachment_id The attachment ID to update.
	 * @param bool $bypass        True to bypass and deliver from WordPress, false deliver from Cloudinary.
	 */
	public function set_attachment_delivery( $attachment_id, $bypass ) {
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['bypass'], $bypass );
		$this->media->sync->set_signature_item( $attachment_id, 'delivery' );
	}

	/**
	 * Add bulk actions.
	 *
	 * @param array $actions Current actions to add to.
	 *
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$cloudinary_actions = $this->get_actions();

		return array_merge( $cloudinary_actions, $actions );
	}

	/**
	 * Filter states to indicate delivery when bypassed.
	 *
	 * @param array $status        The current status.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function filter_status( $status, $attachment_id ) {
		if ( ! empty( $status ) && $this->is_bypassed( $attachment_id ) ) {
			$actions = $this->get_actions( $attachment_id );
			$status  = array(
				'state' => 'info',
				'note'  => $actions[ self::BYPASS_KEYS['wp'] ],
			);
		}

		return $status;
	}

	/**
	 * Adds the deliver checkbox on the single image edit screen.
	 *
	 * @param WP_Post $attachment The attachment post object.
	 */
	public function delivery_actions( $attachment ) {
		$actions = $this->get_actions( $attachment->ID );
		if ( ! empty( $actions[ self::BYPASS_KEYS['wp'] ] ) ) :
			?>
			<div class="misc-pub-section misc-pub-delivery">
				<label><input type="checkbox" name="attachment_delivery" value="true" <?php checked( true, $this->is_bypassed( $attachment->ID ) ); ?> />
					<?php echo esc_html( $actions[ self::BYPASS_KEYS['wp'] ] ); ?>
				</label>
			</div>
			<?php
		endif;
	}

	/**
	 * Filter if the asset can be synced if it's bypassed.
	 *
	 * @param bool $can           The current flag to sync.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function can_sync( $can, $attachment_id ) {
		if ( $this->is_bypassed( $attachment_id ) ) {
			$can = false;
		}

		return $can;
	}

	/**
	 * Check if an attachment is bypassing Cloudinary.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_bypassed( $attachment_id ) {
		return (bool) $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['bypass'], true );
	}
}
