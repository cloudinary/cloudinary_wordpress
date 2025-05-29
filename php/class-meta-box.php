<?php
/**
 * Meta_Box class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use WP_Post;

/**
 * Class Meta_Box
 */
class Meta_Box {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the metaboxes settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Meta_Box constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'init_hook' ) );
	}

	/**
	 * Initialize hooks for the meta box.
	 *
	 * @return void
	 */
	public function init_hook() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );

		$this->register_handlers();
	}

	/**
	 * Register meta box for image sizes.
	 */
	public function register_meta_box() {
		$this->setup();
		$settings = $this->get_config();
		foreach ( $settings as $slug => $setting ) {
			add_meta_box( $slug, $setting['title'], array( $this, 'render_metabox' ), $setting['screen'] );
		}
	}

	/**
	 * Register meta box save handlers.
	 */
	public function register_handlers() {
		$settings = $this->get_config();
		foreach ( $settings as $slug => $setting ) {
			add_action( 'edit_' . $setting['screen'], array( $this, 'handle_update' ) );
		}
	}

	/**
	 * Render a meta box.
	 *
	 * @param WP_Post $post The Post Object. unused.
	 * @param array   $args The metabox arguments.
	 */
	public function render_metabox( $post, $args ) {
		wp_enqueue_script( $this->plugin->slug );
		$slug = $args['id'];
		$box  = $this->settings->get_setting( $slug );
		$box->set_param( 'type', 'meta_box' );
		$media = $this->plugin->get_component( 'media' );
		if ( wp_attachment_is_image( $post ) ) {
			$public_id     = $media->get_public_id( $post->ID );
			$cloudinary_id = $media->get_cloudinary_id( $post->ID );
			if ( empty( $cloudinary_id ) ) {
				$cloudinary_id = '';
			}
			$file_id       = basename( $cloudinary_id );
			$cloudinary_id = path_join( $public_id, $file_id );
		} else {
			$cloudinary_id = $media->get_cloudinary_id( $post->ID );
		}
		$box->get_root_setting()->set_param( 'preview_id', $cloudinary_id );
		foreach ( $box->get_settings() as $setting ) {
			$setting->set_param( 'type', 'wrap' );
		}
		$box->get_component()->render( true );
	}

	/**
	 * Setup the metabox settings.
	 */
	public function setup() {
		$settings = $this->get_config();
		if ( ! empty( $settings ) ) {
			$config         = array(
				'type'     => 'meta_box',
				'storage'  => 'post_meta',
				'settings' => $settings,
			);
			$this->settings = new Settings( 'cloudinary_metaboxes', $config );
		}
	}

	/**
	 * Get the settings config.
	 *
	 * @return array
	 */
	public function get_config() {
		static $config;
		if ( ! $config ) {
			$config = include CLDN_PATH . 'ui-definitions/settings-metaboxes.php';
		}

		return $config;
	}

	/**
	 * Handle capture of settings.
	 */
	public function handle_update() {
		$this->setup();
		$active_slug = Utils::get_sanitized_text( 'cloudinary-active-metabox', INPUT_POST );
		if ( ! empty( $active_slug ) ) {
			$args   = array(
				$active_slug => array(
					'flags' => FILTER_REQUIRE_ARRAY,
				),
			);
			$saving = filter_input_array( INPUT_POST, $args, false );
			foreach ( $saving[ $active_slug ] as $key => $values ) {
				$this->settings->set_pending( $key, $values, $this->settings->get_value( $key ) );
			}
			$this->settings->save();
		}
	}
}
