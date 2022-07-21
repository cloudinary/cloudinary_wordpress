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
		$list     = array();
		foreach ( $messages as $key => $message ) {
			if ( is_array( $message ) ) {
				$list[] = $key . ':';
				$list[] = implode( "\n", $message );
				$list[] = '----------';

			} else {
				$list[] = $message;
			}
		}
		$struct['content'] = implode( "\n", $list );

		return $struct;
	}
}
