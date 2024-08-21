<?php
/**
 * Integrations class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Integrations;

use Cloudinary\Plugin;

/**
 * Abstract class Integrations
 */
abstract class Integrations {
	/**
	 * The plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Integrations constructor.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function __construct( $plugin ) {
		if ( ! $this->can_enable() ) {
			return;
		}

		$this->plugin = $plugin;
		$this->register_hooks();
	}

	/**
	 * Check if the integration can be enabled.
	 *
	 * @return bool
	 */
	abstract public function can_enable();

	/**
	 * Register hooks for the integration.
	 */
	abstract public function register_hooks();
}
