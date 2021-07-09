<?php
/**
 * Delivery Feature abstract.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;
use Cloudinary\Settings\Setting;

/**
 * Class Delivery_Feature
 *
 * @package Cloudinary
 */
abstract class Delivery_Feature implements Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
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
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'delivery_feature';

	/**
	 * Holds the enabler slug.
	 *
	 * @var string
	 */
	protected $enable_slug = 'use_delivery_feature';

	/**
	 * Holds the settings.
	 *
	 * @var Setting
	 */
	protected $settings;

	/**
	 * Holds the config.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The feature application priority.
	 *
	 * @var int
	 */
	protected $priority = 10;

	/**
	 * Delivery_Feature constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		$this->media  = $plugin->get_component( 'media' );

		add_action( 'cloudinary_init_settings', array( $this, 'setup' ) );
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		$this->config = $this->settings->get_value();
		if ( $this->is_active() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		}
		if ( $this->is_enabled() ) {
			$this->setup_hooks();
		}
	}

	/**
	 * Setup hooks used when enabled.
	 */
	protected function setup_hooks() {

	}

	/**
	 * Enqueue assets if active and enabled.
	 */
	public function maybe_enqueue_assets() {
		if ( $this->is_enabled() ) {
			// Add filter to add features.
			add_filter( 'cloudinary_pre_image_tag', array( $this, 'add_features' ), $this->priority, 3 );
			$this->enqueue_assets();
		}
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array  $tag_element   The tag element set.
	 * @param int    $attachment_id The attachment id.
	 * @param string $original_tag  The original html tag.
	 *
	 * @return array
	 */
	public function add_features( $tag_element, $attachment_id, $original_tag ) {
		return $tag_element;
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! is_admin();
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
	}

	/**
	 * Check to see if Breakpoints are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return isset( $this->config[ $this->enable_slug ] ) && 'on' === $this->config[ $this->enable_slug ];
	}

	/**
	 * Create the settings.
	 */
	protected function create_settings() {
		$this->settings = $this->plugin->settings->create_setting( $this->settings_slug, $this->settings() );
	}

	/**
	 * Setup the class.
	 */
	public function setup() {
		$this->create_settings();
		$this->register_settings();
		$this->init();
	}

	/**
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {
		return array();
	}

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {
	}
}
