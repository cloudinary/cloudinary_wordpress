<?php
/**
 * Data Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Data Component to hold data.
 *
 * @package Cloudinary\UI
 */
class Data extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = '';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	protected static $capture = true;

	/**
	 * Return stored data.
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	public function sanitize_value( $value ) {
		return $value;
	}
}
