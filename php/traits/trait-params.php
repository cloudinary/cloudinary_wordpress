<?php
/**
 * Params Trait handles using of setting parameters.
 *
 * @package   Cloudinary\Traits
 */

namespace Cloudinary\Traits;

use Cloudinary\Plugin;

/**
 * Trait Params_Trait
 *
 * @package Cloudinary\Traits
 */
trait Params_Trait {

	/**
	 * Holds the params.
	 *
	 * @var array
	 */
	protected $params;

	/**
	 * Holds the separator.
	 *
	 * @var string
	 */
	public $separator = '.';

	/**
	 * Whether to sanitize slugs.
	 *
	 * @var boolean
	 */
	protected $should_sanitize_slugs = false;

	/**
	 * Sets the params recursively.
	 *
	 * @param array $parts The parts to set.
	 * @param array $param The param being set.
	 * @param mixed $value The value to set.
	 *
	 * @return mixed
	 */
	protected function set_param_array( $parts, $param, $value ) {

		$new = $param;
		$key = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			$param = isset( $param[ $key ] ) ? $param[ $key ] : array();
			$value = $this->set_param_array( $parts, $param, $value );
		}
		if ( null === $value ) {
			unset( $new[ $key ] );

			return $new;
		}

		if ( is_array( $new ) ) {
			if ( '' === $key ) {
				$new[] = $value;
			} else {
				$new[ $key ] = $value;
			}
		} else {
			// There's likely a problem here, as we're setting an array key on a non-array.
			$new = array();
		}
		// @TODO: I took ksort out, need to check that it doesn't have any problems being.

		return $new;
	}

	/**
	 * Set a parameter and value to the setting.
	 *
	 * @param string $param Param key to set.
	 * @param mixed  $value The value to set.
	 *
	 * @return $this
	 */
	public function set_param( $param, $value = null ) {
		$sanitized_param = $this->sanitize_slug( $param );
		$parts           = explode( $this->separator, $sanitized_param );
		$param           = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			if ( ! isset( $this->params[ $param ] ) ) {
				$this->params[ $param ] = array();
			}
			$value = $this->set_param_array( $parts, $this->params[ $param ], $value );
		}

		$this->params[ $param ] = $value;

		if ( is_null( $value ) ) {
			$this->remove_param( $param );
		}

		return $this;
	}

	/**
	 * Set the whole params array.
	 *
	 * @param array $params The params to set.
	 */
	protected function set_params( array $params ) {

		foreach ( $params as $param => $value ) {
			$this->set_param( $param, $value );
		}
	}

	/**
	 * Remove a parameter.
	 *
	 * @param string $param Param key to set.
	 *
	 * @return $this
	 */
	public function remove_param( $param ) {

		$parts = explode( $this->separator, $param );
		$param = array_pop( $parts );
		if ( ! empty( $parts ) ) {
			$main   = implode( $this->separator, $parts );
			$parent = $this->get_param( $main );
			unset( $parent[ $param ] );
			$this->set_param( $main, $parent );
		} else {
			unset( $this->params[ $param ] );
		}

		return $this;
	}

	/**
	 * Sanitize a slug.
	 *
	 * @param string $slug The slug to sanitize.
	 *
	 * @return string
	 */
	protected function sanitize_slug( $slug ) {
		if ( ! $this->should_sanitize_slugs || ! str_contains( $slug, $this->separator ) ) {
			return $slug;
		}

		$sanitized = array_map( 'sanitize_file_name', explode( $this->separator, $slug ) );

		return implode( $this->separator, $sanitized );
	}

	/**
	 * Get a param from a chained lookup.
	 *
	 * @param string $param_slug The slug to get.
	 *
	 * @return mixed
	 */
	protected function get_array_param( $param_slug ) {

		$parts = explode( $this->separator, ltrim( $param_slug, $this->separator ) );
		$param = $this->params;
		while ( ! empty( $parts ) ) {
			if ( ! is_array( $param ) ) {
				// Objects cannot be mapped, so we return the object, for requester to verify.
				if ( ! is_object( $param ) ) {
					$param = null; // Set to null to indicate invalid.
				}
				break;
			}
			// Lets break here, if theres a _type and it's not an array.
			if ( isset( $param['_type'] ) && 'array' !== $param['_type'] ) {
				break;
			}
			$part    = array_shift( $parts );
			$default = null;
			if ( '' === $part ) {
				$default = array( null );
			}
			$param = isset( $param[ $part ] ) ? $param[ $part ] : $default;
		}

		return $param;
	}

	/**
	 *
	 * Check if a param exists.
	 *
	 * @param string $param_slug The param to check.
	 *
	 * @return bool
	 */
	public function has_param( $param_slug ) {

		$param = $this->get_param( $param_slug );

		return ! is_null( $param );
	}

	/**
	 * Check if has any params.
	 *
	 * @return bool
	 */
	public function has_params() {

		return ! empty( $this->params );
	}

	/**
	 * Get params param.
	 *
	 * @param string $param   The param to get.
	 * @param mixed  $default The default value for this param is a value is not found.
	 *
	 * @return mixed|self
	 */
	public function get_param( $param, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		$param = $this->sanitize_slug( $param );

		$value = $this->get_array_param( $param );
		if ( is_array( $value ) && isset( $value['_value'] ) ) {
			$value = $value['_value']; // Get a value based if a param was set, then a later deeper was set.
		}

		return ! is_null( $value ) ? $value : $default;
	}

	/**
	 * Get the whole params.
	 *
	 * @return array
	 */
	public function get_params() {

		return array_filter( (array) $this->params, array( $this, 'is_public' ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Check if a param is public.
	 *
	 * @param string $key The param key.
	 *
	 * @return bool
	 */
	protected function is_public( $key ) {

		return is_string( $key ) ? '@' !== $key[0] : true;
	}
}
