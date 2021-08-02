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

		$this->components = array(
			'delivery'               => array(
				'class'   => 'Cloudinary\Delivery',
				'name'    => __( 'New Delivery method', 'cloudinary' ),
				'options' => array(),
			),
			'responsive_breakpoints' => array(
				'class'   => array( 'Cloudinary\Delivery\Lazy_Load', 'Cloudinary\Delivery\Responsive_Breakpoints' ),
				'name'    => __( 'New Lazy Load and Responsive Breakpoints', 'cloudinary' ),
				'options' => array(),
				'deps'    => array( 'delivery' ),
			),
			'assets'                 => array(
				'class'   => array( 'Cloudinary\Assets' ),
				'name'    => __( 'Non-media library assets', 'cloudinary' ),
				'options' => array(),
				'deps'    => array( 'delivery' ),
			),
		);

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
			 * @return {bool}
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
