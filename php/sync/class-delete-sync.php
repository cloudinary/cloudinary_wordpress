<?php
/**
 * Delete Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;

/**
 * Class Delete_Sync.
 *
 * Push media to Cloudinary on upload.
 */
class Delete_Sync {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	protected $plugin;

	/**
	 * Delete_Sync constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_action( 'delete_attachment', array( $this, 'delete_asset' ), 10 );
		add_filter( 'user_has_cap', array( $this, 'can_delete_asset' ), 10, 3 );
	}

	/**
	 * Checks if an image is synced before allowing a user to have rights to delete the file.
	 *
	 * @param array $all_caps All capabilities for the user.
	 * @param array $caps     Current requested capabilities.
	 * @param array $args     Additional args for the check.
	 *
	 * @return array
	 */
	public function can_delete_asset( $all_caps, $caps, $args ) {

		if ( 3 === count( $args ) ) {
			// The args are indexed, list them in named variables to better understand.
			list( $request_cap, , $post_id ) = $args;

			if ( $this->plugin->components['media']->is_media( $post_id ) && 'delete_post' === $request_cap && ! empty( $all_caps['delete_posts'] ) ) {

				// Check if is pending.
				if ( ! $this->plugin->components['sync']->is_synced( $post_id ) && $this->plugin->components['sync']->is_pending( $post_id ) ) {
					// Check for errors.
					$has_error = $this->plugin->components['media']->get_post_meta( $post_id, Sync::META_KEYS['sync_error'], true );
					if ( empty( $has_error ) ) {
						$all_caps['delete_posts'] = false;
						$action                   = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
						if ( ! empty( $action ) && 'delete' === $action ) {
							wp_die( esc_html__( 'Sorry, you can’t delete an asset until it has fully synced with Cloudinary. Try again once syncing is complete.', 'cloudinary' ) );
						}
					}
				}
			}
		}

		return $all_caps;
	}

	/**
	 * Delete an asset on Cloudinary.
	 *
	 * @param int $post_id The post id to delete asset for.
	 */
	public function delete_asset( $post_id ) {
		// In some environments, the $post_id is a string, failing ahead on a strict compare.
		// For that reason we need to ensure the variable type.
		$post_id = absint( $post_id );

		if ( $this->plugin->components['sync']->is_synced( $post_id ) ) {

			// check if this is not a transformation base image.
			$public_id = $this->plugin->components['media']->get_public_id( $post_id, true );
			$linked    = $this->plugin->components['media']->get_linked_attachments( $public_id );
			if ( count( $linked ) > 1 ) {
				// There are other attachments sharing this public_id, so skip it.
				return;
			}
			if ( count( $linked ) === 1 && $post_id !== $linked[0] ) {
				// Something odd is up. skip it in case.
				return;
			}
			// Next we need to check that the file is in the cloudinary folder.
			$path              = trim( pathinfo( $public_id, PATHINFO_DIRNAME ), '.' );
			$cloudinary_folder = $this->plugin->settings->get_value( 'cloudinary_folder' );
			if ( $cloudinary_folder === $path ) {
				$type    = $this->plugin->components['media']->get_resource_type( $post_id );
				$options = array(
					'public_id'  => $public_id,
					'invalidate' => true, // clear from CDN cache as well.
				);
				// Not a background request, since the post could be deleted before the background request hits causing it to not find the post and therefore not finding the public_id
				// using the public_id directly in a background call, would make validation complicated since there is no longer a post to validate against.
				$this->plugin->components['connect']->api->destroy( $type, $options );
			}
		}
	}

	/**
	 * Setup this component.
	 */
	public function setup() {
		$this->register_hooks();
	}
}
