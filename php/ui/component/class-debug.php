<?php
/**
 * Submit UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Debug extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|viewer/|/wrap';

	/**
	 * Filter the viewer parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function viewer( $struct ) {

		$struct['element'] = 'pre';

		$messages = Utils::get_debug_messages();
		$struct['content'] = implode( "\n", $messages );

		return $struct;
	}
}
