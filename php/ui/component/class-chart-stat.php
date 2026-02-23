<?php
/**
 * Row UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Line state Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Chart_Stat extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'title/|wrap|canvas|/wrap';

	/**
	 * Holds the historical array.
	 *
	 * @var array
	 */
	protected $history;

	/**
	 * Holds the connect instance.
	 *
	 * @var \Cloudinary\Connect
	 */
	protected $connect;

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->connect = get_plugin_instance()->get_component( 'connect' );
		$this->history = $this->connect->history( $this->setting->get_param( 'days', 7 ) );
	}

	/**
	 * Gets the title structs.
	 *
	 * @param array $struct The title struct.
	 *
	 * @return array
	 */
	protected function title( $struct ) {
		$struct            = parent::title( $struct );
		$struct['element'] = 'h4';

		return $struct;
	}

	/**
	 * Gets the canvas structs.
	 *
	 * @param array $struct The canvas struct.
	 *
	 * @return array
	 */
	protected function canvas( $struct ) {
		$stat                               = $this->setting->get_param( 'stat' );
		$struct['render']                   = true;
		$struct['attributes']['data-chart'] = 'line';
		$struct['attributes']['data-color'] = $this->setting->get_param( 'color', '#304ec4' );
		$data                               = array();
		$dates                              = array();
		foreach ( $this->history as $date => $stats ) {
			if ( ! is_array( $stats ) ) {
				continue;
			}
			$data[]  = $stats[ $stat ]['usage'];
			$dates[] = date_i18n( 'j M', strtotime( $date ) );
		}
		$struct['attributes']['data-data']  = wp_json_encode( array_reverse( $data ) );
		$struct['attributes']['data-dates'] = wp_json_encode( array_reverse( $dates ) );
		$struct['attributes']['width']      = '400px';
		$struct['attributes']['height']     = '300px';

		return $struct;
	}
}
