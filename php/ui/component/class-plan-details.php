<?php
/**
 * Plan UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use function Cloudinary\get_plugin_instance;
use Cloudinary\UI\Component;
use Cloudinary\Settings;
use Cloudinary\Settings\Setting;
use Cloudinary\Connect;

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
		$data = $this->plugin_settings->get_value('last_usage' );

		return $this->make_item( __( 'Plan', 'cloudinary' ), $data['plan'], $this->dir_url . 'css/images/cloud.svg', $struct );
	}

	protected function make_item( $title, $description, $icon, $struct ) {
		$struct['element'] = 'div';

		// Icon.
		$icon_part                      = $this->get_part( 'img' );
		$icon_part['render']            = true;
		$icon_part['attributes']['src'] = $icon;
		$struct['children']['icon']     = $icon_part;

		// Title.
		$title_part                          = $this->get_part( 'div' );
		$title_part['attributes']['class'][] = 'description';
		$title_part['content']               = $title;
		$struct['children']['title']         = $title_part;

		//Description.
		$description_part                          = $this->get_part( 'div' );
		$description_part['attributes']['class'][] = 'cld-title';
		$description_part['content']               = $description;
		$struct['children']['description']         = $description_part;

		return $struct;
	}
}
