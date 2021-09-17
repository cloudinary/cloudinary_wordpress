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
	protected $components;

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		$this->components = array();

		foreach ( $this->components as $key => $data ) {

			if ( ! empty( $data['deps'] ) && empty( array_intersect( $data['deps'], array_keys( $this->plugin->components ) ) ) ) {
				continue;
			}

			/**
			 * Filter to enable beta features for testing.
			 *
			 * @hook    cloudinary_beta
			 * @default false
			 *
			 * @param $enable  {bool}   Flag to enable beta features.
			 * @param $feature {string} Optional feature type.
			 * @param $data    {array}  The beta feature data.
			 *
			 * @return  {bool}
			 */
			if ( apply_filters( 'cloudinary_beta', false, $key, $data ) ) {
				foreach ( (array) $data['class'] as $class ) {
					$namespace                         = explode( '\\', $class );
					$name                              = strtolower( array_pop( $namespace ) );
					$this->plugin->components[ $name ] = new $class( $this->plugin );
				}
			}
		}
	}
}
