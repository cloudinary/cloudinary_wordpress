<?php
/**
 * Frame UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Frame Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Frame extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'settings';
}
