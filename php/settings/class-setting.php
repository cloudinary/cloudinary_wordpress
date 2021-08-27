<?php
/**
 * Cloudinary represents a single setting node point.
 *
 * @package   Cloudinary\Settings
 */

namespace Cloudinary\Settings;

use Cloudinary\Traits\Params_Trait;
use Cloudinary\Settings;
use Cloudinary\UI\Component;

/**
 * Class Setting
 *
 * @package Cloudinary\Settings
 */
class Setting {

	use Params_Trait;

	/**
	 * Holds the settings component.
	 *
	 * @var Component
	 */
	protected $component;
	/**
	 * Holds the setting type.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Holds the root parent.
	 *
	 * @var Settings
	 */
	protected $root;

	/**
	 * Holds the slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Holds the list of children.
	 *
	 * @var self[]
	 */
	protected $children = array();

	/**
	 * Holds the parent.
	 *
	 * @var self
	 */
	protected $parent;

	/**
	 * Setting constructor.
	 *
	 * @param string   $slug   The setting slug.
	 * @param Settings $root   The root setting.
	 * @param array    $params Optional Params.
	 */
	public function __construct( $slug, $root = null, $params = array() ) {

		if ( is_null( $root ) ) {
			$root = new Settings( $slug, $params );
		}
		$this->root = $root;
		$this->slug = $slug;
		if ( ! empty( $params ) ) {
			$this->set_params( $params );
		}
	}

	/**
	 * Set the parent setting.
	 *
	 * @param string $parent The slug of the parent setting.
	 */
	public function set_parent( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Get the parent.
	 *
	 * @return Settings|Setting
	 */
	public function get_parent() {
		return $this->root->find_setting( $this->parent );
	}

	/**
	 * Set the setting type.
	 *
	 * @param string $type The parent setting.
	 */
	public function set_type( $type ) {
		$this->type = $type;
	}

	/**
	 * Get the setting type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the settings component.
	 *
	 * @return Component
	 */
	public function get_component() {
		if ( ! $this->component ) {
			foreach ( $this->children as $child ) {
				$child->get_component();
			}
			$this->component = Component::init( $this );
		}

		return $this->component;
	}

	/**
	 * Get the child settings.
	 *
	 * @return Setting[]
	 */
	public function get_settings() {
		return $this->children;
	}

	/**
	 * Get the child components.
	 *
	 * @return array
	 */
	public function get_params_recursive() {

		$params = $this->get_params();
		foreach ( $this->children as $slug => $child ) {
			$params['settings'][ $slug ] = $child->get_params_recursive();
		}

		return $params;
	}

	/**
	 * Magic method to chain directly to the child settings by slug.
	 *
	 * @param string $name The name/slug of the child setting.
	 *
	 * @return Setting|null
	 */
	public function __get( $name ) {

		$value = false;
		if ( '_' === $name[0] ) {
			$value = true;
			$name  = ltrim( $name, '_' );
		}
		if ( ! isset( $this->children[ $name ] ) ) {
			$this->children[ $name ] = $this->root->add( $this->slug . $this->separator . $name );
		}
		$return = $this->children[ $name ];
		if ( $value ) {
			$return = $return->get_value();
		}

		return $return;
	}

	/**
	 * Magic method to set a child setting's value.
	 *
	 * @param string $name  The setting name being set.
	 * @param mixed  $value The value to set.
	 */
	public function __set( $name, $value ) {

		$this->{$name}->set( $value );
	}

	/**
	 * Add a child component.
	 *
	 * @param self $component The component to add.
	 *
	 * @return Setting|\WP_Error
	 */
	public function add( $component ) {

		$parts                   = explode( $this->separator, $component->get_slug() );
		$slug                    = array_pop( $parts );
		$this->children[ $slug ] = $component;

		return $component;
	}

	/**
	 * Get the component slug.
	 *
	 * @return string
	 */
	public function get_slug() {

		return $this->slug;
	}

	/**
	 * Flatten the setting.
	 *
	 * @return array
	 */
	public function flatten() {

		return array(
			$this->get_slug() => $this->get_value(),
		);
	}

	/**
	 * Get the value of the setting.
	 *
	 * @return mixed
	 */
	public function get_value() {

		return $this->root->get_value( $this->slug );
	}

	/**
	 * Set the value of the setting.
	 *
	 * @param mixed $value The value to set.
	 */
	public function set_value( $value ) {

		$this->root->set_value( $this->slug, $value );
	}

	/**
	 * Check if the setting has a parent.
	 *
	 * @return bool
	 */
	public function has_parent() {
		return ! empty( $this->parent );
	}

	/**
	 * Check if the setting has settings.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return ! empty( $this->children );
	}

	/**
	 * Get the main option name.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return $this->root->get_slug();
	}

	/**
	 * Get the root setting.
	 *
	 * @return Settings
	 */
	public function get_root_setting() {
		return $this->root;
	}

	/**
	 * Find a setting.
	 *
	 * @param string $slug The slug to find.
	 *
	 * @return Setting
	 */
	public function find_setting( $slug ) {
		return $this->root->find_setting( $slug );
	}

	/**
	 * Save the setting value.
	 */
	public function save() {

		$this->root->save();
	}
}
