<?php
/**
 * Plan UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Connect;
use Cloudinary\Settings;
use Cloudinary\Settings\Setting;
use Cloudinary\Sync\Sync_Queue;
use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Plan Component to render plan details.
 *
 * @package Cloudinary\UI
 */
class Plan_Details extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'plan/|units/|extra/|requests/|assets/|mo/';

	/**
	 * Holds the plugins settings.
	 *
	 * @var Settings
	 */
	protected $plugin_settings;
	/**
	 * Holds the plugin url.
	 *
	 * @var string
	 */
	protected $dir_url;

	/**
	 * Plan constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		parent::__construct( $setting );
		$plugin                = get_plugin_instance();
		$this->dir_url         = $plugin->dir_url;
		$this->plugin_settings = $plugin->settings;
	}

	/**
	 * Filter the plan wrapper parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function plan( $struct ) {
		$data       = $this->plugin_settings->get_value( 'last_usage' );
		$connection = get_plugin_instance()->get_component( 'connect' );

		if ( ! $connection instanceof Connect ) {
			return $struct;
		}

		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'cld-plan';

		$struct['children']['plan'] = $this->make_item( __( 'Plan', 'cloudinary' ), $data['plan'], $this->dir_url . 'css/images/star.svg' );

		if ( $connection->get_usage_stat( 'credits', 'limit' ) ) {
			$struct = $this->plan_credit( $struct, $connection );
		} else {
			$struct = $this->plan_classic( $struct, $connection );
		}

		$struct['children']['requests'] = $this->make_item( __( 'Total Requests', 'cloudinary' ), number_format_i18n( $data['requests'] ), $this->dir_url . 'css/images/requests.svg' );
		$struct['children']['assets']   = $this->make_item( __( 'Optimized assets', 'cloudinary' ), number_format_i18n( Sync_Queue::get_optimized_assets() ), $this->dir_url . 'css/images/image.svg' );

		return $struct;
	}

	/**
	 * The metrics for Credit base accounts.
	 *
	 * @param array   $struct     The array structure.
	 * @param Connect $connection The connect instance.
	 *
	 * @return array
	 */
	protected function plan_credit( $struct, $connection ) {
		$units      = $connection->get_usage_stat( 'credits', 'limit' );
		$units_used = $connection->get_usage_stat( 'credits', 'usage' );
		$remaining  = $units - $units_used;


		$usage                                 = number_format_i18n( $units ) . ' per month / ' . number_format_i18n( $units_used, 2 ) . ' used';
		$struct['children']['units']           = $this->make_item( __( 'Plan Units', 'cloudinary' ), $usage, $this->dir_url . 'css/images/units.svg' );
		$struct['children']['remaining_units'] = $this->make_item( __( 'Remaining Units', 'cloudinary' ), number_format_i18n( $remaining, 2 ), $this->dir_url . 'css/images/units-plus.svg' );

		return $struct;
	}

	/**
	 * The metrics for Classic base accounts.
	 *
	 * @param array   $struct     The array structure.
	 * @param Connect $connection The connect instance.
	 *
	 * @return array
	 */
	protected function plan_classic( $struct, $connection ) {
		$struct['children']['storage']         = $this->make_item( __( 'Storage', 'cloudinary' ), $connection->get_usage_stat( 'storage', 'used_percent' ) . '%', $this->dir_url . 'css/images/cloud.svg' );
		$struct['children']['transformations'] = $this->make_item( __( 'Transformations', 'cloudinary' ), $connection->get_usage_stat( 'transformations', 'used_percent' ) . '%', $this->dir_url . 'css/images/transformation.svg' );
		$struct['children']['bandwidth']       = $this->make_item( __( 'Bandwidth', 'cloudinary' ), $connection->get_usage_stat( 'bandwidth', 'used_percent' ) . '%', $this->dir_url . 'css/images/bandwidth.svg' );


		return $struct;
	}

	/**
	 * Make an icon item.
	 *
	 * @param string $title       The title.
	 * @param string $description The description.
	 * @param string $icon        The icon url.
	 *
	 * @return array
	 */
	protected function make_item( $title, $description, $icon ) {
		$struct                          = $this->get_part( 'div' );
		$struct['attributes']['class'][] = 'cld-plan-item';
		// Icon.
		$icon_part                      = $this->get_part( 'img' );
		$icon_part['render']            = true;
		$icon_part['attributes']['src'] = $icon;
		$struct['children']['icon']     = $icon_part;

		$content                          = $this->get_part( 'div' );
		$content['attributes']['class'][] = 'cld-plan-item-content';
		// Title.
		$title_part                          = $this->get_part( 'div' );
		$title_part['attributes']['class'][] = 'description';
		$title_part['content']               = $title;
		$content['children']['title']        = $title_part;

		// Description.
		$description_part                          = $this->get_part( 'div' );
		$description_part['attributes']['class'][] = 'cld-title';
		$description_part['content']               = $description;
		$content['children']['description']        = $description_part;

		$struct['children']['content'] = $content;

		return $struct;
	}
}
