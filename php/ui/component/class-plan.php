<?php
/**
 * Plan UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use Cloudinary\Connect;
use function Cloudinary\get_plugin_instance;

/**
 * Plan Component to render plan status.
 *
 * @package Cloudinary\UI
 */
class Plan extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'title/|plan_box|plan_heading/|plan_summary/|/plan_box';

	/**
	 * Holder the Connect object.
	 *
	 * @var Connect
	 */
	protected $connection;

	/**
	 * Plan constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$this->connection = get_plugin_instance()->get_component( 'connect' );

		parent::__construct( $setting );
	}

	/**
	 * Setup action before rendering.
	 */
	protected function pre_render() {
		$this->setting->set_param( 'plan_heading', $this->connection->get_usage_stat( 'plan' ) );
	}

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function title( $struct ) {

		$struct['element'] = 'h2';

		return parent::title( $struct );
	}

	/**
	 * Filter the plan box parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function plan_box( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-panel-inner',
		);

		return $struct;
	}

	/**
	 * Filter the plan heading structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function plan_heading( array $struct ) {
		$struct['element']             = 'h3';
		$struct['attributes']['class'] = array(
			'cld-plan',
		);

		return $struct;
	}

	/**
	 * Filter the plan summary parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function plan_summary( $struct ) {

		$summary            = $this->get_part( 'h4' );
		$summary['content'] = $this->get_plan_description();

		$detail            = $this->get_part( 'span' );
		$detail['content'] = __( '1 Credit =', 'cloudinary' );

		$struct['children']['title'] = $summary;
		$struct['children']['span']  = $detail;
		$struct['children']['ul']    = $this->credits_content();
		$struct['element']           = 'div';

		return $struct;
	}

	/**
	 * Filter the plan wrapper parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function plan_wrap( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-panel-inner',
		);

		return $struct;
	}

	/**
	 * Get plan description.
	 *
	 * @return string
	 */
	protected function get_plan_description() {
		$description = __( 'Pay as you go', 'cloudinary' );
		$limit       = $this->connection->get_usage_stat( 'credits', 'limit' );
		if ( $limit ) {
			// translators: The number of monthly credits.
			$description = sprintf( _n( '%d Monthly Credit', '%d Monthly Credits', $limit, 'cloudinary' ), $limit );
		}

		return $description;
	}

	/**
	 * Creates the bullet points of the plan.
	 *
	 * @return array
	 */
	protected function credits_content() {

		$points = $this->get_part( 'ul' );
		$items  = array(
			__( '1,000 Transformations', 'cloudinary' ),
			__( '1 GB Storage', 'cloudinary' ),
			__( '1 GB Bandwidth', 'cloudinary' ),
		);
		$li     = $this->get_part( 'li' );
		foreach ( $items as $item ) {
			$child                = $li;
			$child['content']     = $item;
			$points['children'][] = $child;
		}

		return $points;
	}
}
