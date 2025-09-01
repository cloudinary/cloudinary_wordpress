<?php
/**
 * URL class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Traits\Relation_Trait;
use Cloudinary\URL\Cloudinary;
use Cloudinary\URL\WordPress;
use Cloudinary\Delivery;

/**
 * Class URL
 */
class URL implements Setup {

	use Relation_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Global Image transformations.
	 *
	 * @var string
	 */
	protected $image_transformations;

	/**
	 * Holds the Global Video transformations.
	 *
	 * @var string
	 */
	protected $video_transformations;

	/**
	 * Holds a list of the Cloudinary URL objects.
	 *
	 * @var array
	 */
	protected $cloudinary_urls = array();

	/**
	 * Holds a list of the WordPress URL objects.
	 *
	 * @var array
	 */
	protected $wordpress_urls = array();

	/**
	 * URL constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->setup_relations();
		add_action( 'cloudinary_init_settings', array( $this, 'init_settings' ) );
	}

	/**
	 * Init settings.
	 */
	public function init_settings() {
		$this->image_transformations = $this->plugin->settings->get_value( 'image_format', 'image_quality', 'image_freeform' );
		$this->video_transformations = $this->plugin->settings->get_value( 'video_format', 'video_quality', 'video_freeform' );
	}

	/**
	 * Set up the object.
	 */
	public function setup() {
	}

	/**
	 * Get a Cloudinary URL object.
	 *
	 * @param string $url The Cloudinary URL to break apart.
	 *
	 * @return array
	 */
	public function cloudinary_url( $url ) {
		if ( ! isset( $this->cloudinary_urls[ $url ] ) ) {
			$this->cloudinary_urls[ $url ] = new Cloudinary( $url );
		}

		return $this->cloudinary_urls[ $url ];
	}

	/**
	 * Init an array of cloudinary urls
	 *
	 * @param array $urls An array of cloudinary URLs.
	 */
	public function cloudinary_urls( $urls ) {
		foreach ( $urls as $url ) {
			$this->cloudinary_url( $url );
		}
	}

	/**
	 * Get all the cloudinary URL objects.
	 *
	 * @return Cloudinary[]
	 */
	public function get_cloudinary_urls() {
		return $this->cloudinary_urls;
	}

	/**
	 * Break apart a WordPress URL
	 *
	 * @param string $url The WordPress URL to break apart.
	 *
	 * @return array
	 */
	public function wordpress_url( $url ) {
		if ( ! isset( $this->wordpress_urls[ $url ] ) ) {
			$this->wordpress_urls[ $url ] = new WordPress( $url );
		}

		return $this->wordpress_urls[ $url ];
	}

	/**
	 * Init am array of WordPress URLs.
	 *
	 * @param array $urls Array of WordPress URLs.
	 */
	public function wordpress_urls( $urls ) {
		$method    = $this->query_relations;
		$relations = $method( array(), $urls );
		foreach ( $relations as $relation ) {
			$wp_object = $this->wordpress_url( $relation['sized_url'] );
			$wp_object->set_relation( $relation );
			$wp_object->cloudinary_url();
		}
	}

	/**
	 * Get all the WordPress URL objects.
	 *
	 * @return WordPress[]
	 */
	public function get_wordpress_urls() {
		return $this->wordpress_urls;
	}
}
