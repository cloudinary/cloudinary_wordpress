<?php
/**
 * Plan UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use function Cloudinary\get_plugin_instance;
use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use Cloudinary\Connect;

/**
 * Plan Status Component to render plan status.
 *
 * @package Cloudinary\UI
 */
class Plan_Status extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'title/|stat_boxes|box_storage/|box_transformations/|box_bandwidth/|/stat_boxes';

	/**
	 * Holder the Connect object.
	 *
	 * @var Connect
	 */
	protected $connection;

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
		$plugin           = get_plugin_instance();
		$this->connection = $plugin->get_component( 'connect' );
		$this->dir_url    = $plugin->dir_url;

		parent::__construct( $setting );
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
	protected function stat_boxes( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'stat-boxes',
		);

		return $struct;
	}

	/**
	 * Box Storage structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function box_storage( array $struct ) {
		$title            = $this->get_part( 'h3' );
		$title['content'] = __( 'Storage', 'cloudinary' );

		$icon                      = $this->get_part( 'icon' );
		$icon['element']           = 'img';
		$icon['attributes']['src'] = $this->dir_url . 'css/images/cloud.svg';
		$icon['render']            = true;

		$limit                        = $this->get_part( 'span' );
		$limit['attributes']['class'] = array(
			'limit',
		);
		$limit['content']             = size_format( $this->connection->get_usage_stat( 'storage', 'limit' ) );

		$usage                        = $this->get_part( 'span' );
		$usage['attributes']['class'] = array(
			'usage',
		);
		$usage['content']             = $this->connection->get_usage_stat( 'storage', 'used_percent' ) . '%';


		$struct = $this->get_struct( $struct, $icon, $title, $limit, $usage );

		return $struct;
	}

	/**
	 * Box Transformations structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function box_transformations( array $struct ) {
		$title            = $this->get_part( 'h3' );
		$title['content'] = __( 'Transformations', 'cloudinary' );

		$icon                      = $this->get_part( 'icon' );
		$icon['element']           = 'img';
		$icon['attributes']['src'] = $this->dir_url . 'css/images/transformation.svg';
		$icon['render']            = true;

		$limit                        = $this->get_part( 'span' );
		$limit['attributes']['class'] = array(
			'limit',
		);
		$limit['content']             = number_format_i18n( $this->connection->get_usage_stat( 'transformations', 'limit' ) );

		$usage                        = $this->get_part( 'span' );
		$usage['attributes']['class'] = array(
			'usage',
		);
		$usage['content']             = $this->connection->get_usage_stat( 'transformations', 'used_percent' ) . '%';

		$struct = $this->get_struct( $struct, $icon, $title, $limit, $usage );

		return $struct;
	}

	/**
	 * Box Bandwidth structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function box_bandwidth( array $struct ) {
		$title            = $this->get_part( 'h3' );
		$title['content'] = __( 'Bandwidth', 'cloudinary' );

		$icon                      = $this->get_part( 'icon' );
		$icon['element']           = 'img';
		$icon['attributes']['src'] = $this->dir_url . 'css/images/bandwidth.svg';
		$icon['render']            = true;

		$limit                        = $this->get_part( 'span' );
		$limit['attributes']['class'] = array(
			'limit',
		);
		$limit['content']             = size_format( $this->connection->get_usage_stat( 'bandwidth', 'limit' ) );

		$usage                        = $this->get_part( 'span' );
		$usage['attributes']['class'] = array(
			'usage',
		);
		$usage['content']             = $this->connection->get_usage_stat( 'bandwidth', 'used_percent' ) . '%';

		$struct = $this->get_struct( $struct, $icon, $title, $limit, $usage );

		return $struct;
	}

	/**
	 * Get the box struct.
	 *
	 * @param array $struct The array structure.
	 * @param array $icon   The icon URL.
	 * @param array $title  The title.
	 * @param array $limit  The limit.
	 * @param array $usage  The usage.
	 *
	 * @return array
	 */
	protected function get_struct( array $struct, array $icon, array $title, array $limit, array $usage ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'box',
		);
		$struct['children']['icon']    = $icon;
		$struct['children']['title']   = $title;
		$struct['children']['limit']   = $limit;
		$struct['children']['usage']   = $usage;

		return $struct;
	}
}
