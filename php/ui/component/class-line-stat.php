<?php
/**
 * Line Stat Component.
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
class Line_Stat extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|title|titles|used/|avail/|/titles|line/|/title|/wrap';

	/**
	 * Holds the used persentage.
	 *
	 * @var string
	 */
	protected $used_percent;

	/**
	 * Holds the limit.
	 *
	 * @var string
	 */
	protected $limit;

	/**
	 * Holds the formatted limit.
	 *
	 * @var string
	 */
	protected $limit_formatted;

	/**
	 * Holds the used total.
	 *
	 * @var string
	 */
	protected $used;

	/**
	 * Holds the available total.
	 *
	 * @var string
	 */
	protected $avail;

	/**
	 * Holds the used (left) text.
	 *
	 * @var string
	 */
	protected $used_text;

	/**
	 * Holds teh avail (right) text.
	 *
	 * @var string
	 */
	protected $avail_text;

	/**
	 * Holds the connect instance.
	 *
	 * @var Connect
	 */
	protected $connect;

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->set_stats();
		$used  = $this->limit * $this->used_percent / 100;
		$avail = $this->limit - $used;
		if ( $this->setting->get_param( 'format_size' ) ) {
			$used                  = empty( $used ) ? 0 : $used;
			$avail                 = empty( $avail ) ? 0 : $avail;
			$this->limit_formatted = size_format( $this->limit );
			$this->used            = size_format( $used, 1 );
			$this->avail           = size_format( $avail, 2 );
		} else {
			$this->limit_formatted = number_format_i18n( $this->limit );
			$this->used            = number_format_i18n( $used );
			$this->avail           = number_format_i18n( $avail );
		}

		$this->set_texts();
	}

	/**
	 * Set the end texts.
	 */
	protected function set_texts() {
		/* translators: %s is the amount used. */
		$this->used_text = sprintf( '%s used', $this->used );

		/* translators: %s is the amount available. */
		$this->avail_text = sprintf( '%s available', $this->avail );
	}

	/**
	 * Set the usage stats.
	 */
	protected function set_stats() {
		$this->connect      = get_plugin_instance()->get_component( 'connect' );
		$this->limit        = $this->connect->get_usage_stat( $this->setting->get_param( 'stat' ), 'limit' );
		$this->used_percent = $this->connect->get_usage_stat( $this->setting->get_param( 'stat' ), 'used_percent' );
	}

	/**
	 * Gets the title structs.
	 *
	 * @param array $struct The title struct.
	 *
	 * @return array
	 */
	protected function title( $struct ) {
		$struct                          = parent::title( $struct );
		$struct['content']              .= ': ' . $this->limit_formatted;
		$struct['attributes']['class'][] = 'cld-progress-header';

		return $struct;
	}

	/**
	 * Gets the titles structs.
	 *
	 * @param array $struct The titles struct.
	 *
	 * @return array
	 */
	protected function titles( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'cld-progress-header-titles';

		return $struct;
	}

	/**
	 * Gets the used structs.
	 *
	 * @param array $struct The used struct.
	 *
	 * @return array
	 */
	protected function used( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'cld-progress-header-titles-left';
		$struct['content']               = $this->used_text;

		return $struct;
	}

	/**
	 * Gets the avail structs.
	 *
	 * @param array $struct The avail struct.
	 *
	 * @return array
	 */
	protected function avail( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'cld-progress-header-titles-right';
		$struct['content']               = $this->avail_text;

		return $struct;
	}

	/**
	 * Gets the line structs.
	 *
	 * @param array $struct The line struct.
	 *
	 * @return array
	 */
	protected function line( $struct ) {
		$struct['element']                     = 'div';
		$struct['attributes']['class'][]       = 'cld-progress-line';
		$struct['attributes']['data-progress'] = 'line';
		$struct['attributes']['data-value']    = $this->used_percent;
		$struct['attributes']['data-color']    = $this->setting->get_param( 'color', '#304ec4' );
		$struct['render']                      = true;

		return $struct;
	}
}
