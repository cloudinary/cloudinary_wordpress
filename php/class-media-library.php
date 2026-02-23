<?php
/**
 * Media_Library class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use WP_Screen;

/**
 * Class Media_Library
 */
class Media_Library extends Extension {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the page handle.
	 *
	 * @var string
	 */
	protected $handle;

	/**
	 * Holds teh component slug.
	 */
	const MEDIA_LIBRARY_SLUG = 'cloudinary-media-library';

	/**
	 * Setup the component
	 */
	public function setup() {
		// Setup the main page.
		$this->handle = add_menu_page(
			__( 'Cloudinary Media Library', 'cloudinary' ),
			__( 'Cloudinary DAM', 'cloudinary' ),
			Utils::user_can( 'manage_dam' ) ? 'exist' : false, // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			self::MEDIA_LIBRARY_SLUG,
			array( $this, 'render' ),
			'dashicons-cloudinary-dam',
			'81.6'
		);
	}

	/**
	 * Render the page template.
	 */
	public function render() {
		require CLDN_PATH . 'ui-definitions/components/media-library.php';
	}

	/**
	 * Check if this class is active.
	 *
	 * @return bool True if active False if not.
	 */
	public function is_active() {
		$screen = get_current_screen();

		return $screen instanceof WP_Screen && $screen->base === $this->handle;
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets() {
		$media = $this->plugin->get_component( 'media' );
		wp_enqueue_script( 'cloudinary' );

		$params = array(
			'fetch_url' => Utils::rest_url( REST_API::BASE . '/asset' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		);

		$this->plugin->add_script_data( 'dam', $params );
		$media->editor_assets();
	}
}
