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
	 * Holds the Delivery instance.
	 *
	 * @var Delivery
	 */
	protected $delivery;

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
	 * Holds the delivery type.
	 *
	 * @var string
	 */
	protected $type = 'image';

	/**
	 * Delivery_Feature constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin   = $plugin;
		$this->media    = $plugin->get_component( 'media' );
		$this->delivery = $plugin->get_component( 'delivery' );

		add_action( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );
		add_action( 'cloudinary_init_settings', array( $this, 'setup' ) );
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		$this->config = $this->settings->get_value( $this->settings_slug );
		if ( $this->filter_is_active() ) {
			$this->maybe_enqueue_assets();
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
			add_filter( "cloudinary_pre_{$this->type}_tag", array( $this, 'add_features' ), $this->priority );
			add_action( 'wp_print_scripts', array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {
		return $tag_element;
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! Utils::is_admin();
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
		return $this->filter_is_active() && 'on' === $this->config[ $this->enable_slug ];
	}

	/**
	 * Add the settings.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {
		return $pages;
	}

	/**
	 * Setup the class.
	 */
	public function setup() {
		$this->settings = $this->plugin->settings;
		$this->init();
	}

	/**
	 * Filter is active.
	 *
	 * @return bool
	 */
	public function filter_is_active() {
		$parts      = explode( '\\', get_called_class() );
		$class_name = strtolower( array_pop( $parts ) );
		$is_active  = $this->is_active();

		/**
		 * Filter to check if the feature is active.
		 *
		 * @hook  cloudinary_is_{$class_name}_active
		 * @since 3.0.4
		 *
		 * @param $is_active {bool} Flag if active.
		 */
		return apply_filters( "cloudinary_is_{$class_name}_active", $is_active );
	}
}
