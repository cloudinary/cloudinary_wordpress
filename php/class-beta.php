<?php
/**
 * Cloudinary Beta, to add functionality under a beta filter.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Plugin Beta class.
 */
class Beta {

	/**
	 * Holds the core plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds a list of Beta components.
	 *
	 * @var array
	 */
	protected $components = array(
		'cache'    => 'Cloudinary\Cache',
		'replace'  => 'Cloudinary\String_Replace',
		'delivery' => 'Cloudinary\Delivery',
	);

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		foreach ( $this->components as $key => $class ) {
			/**
			 * Filter to enable beta features for testing.
			 *
			 * @hook    cloudinary_beta
			 * @default false
			 *
			 * @param $enable  {bool} Flag to enable beta features.
			 * @param $feature {string} Optional feature type.
			 *
			 * @return  {bool}
			 */
			if ( apply_filters( 'cloudinary_beta', false, $key ) ) {
				$this->plugin->components[ $key ] = new $class( $this->plugin );
			}
		}
	}
}
