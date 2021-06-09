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
			'delivery' => array(
				'class'   => 'Cloudinary\Delivery',
				'name'    => __( 'New Delivery method', 'cloudinary' ),
				'options' => array(),
			),
		);

		foreach ( $this->components as $key => $data ) {
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
				$this->plugin->components[ $key ] = new $data['class']( $this->plugin );
			}
		}
	}
}
