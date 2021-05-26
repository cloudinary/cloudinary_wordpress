<?php
/**
 * Frame UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Media\Gallery;
use function Cloudinary\get_plugin_instance;

/**
 * Frame Component to render components only.
 *
 * @package Cloudinary\UI
 */
class React extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'input/|app/|scripts/';

	/**
	 * Holds the component script.
	 *
	 * @var array
	 */
	protected $script;

	/**
	 * Filter the app part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function app( $struct ) {
		$struct['attributes']['id'] = 'app_gallery_' . $this->setting->get_slug();
		$struct['render']           = true;

		return $struct;
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct                       = parent::input( $struct );
		$struct['attributes']['id']   = 'gallery_settings_input';
		$struct['attributes']['type'] = 'hidden';

		$struct['attributes']['value'] = $this->setting->get_value();

		return $struct;
	}

	/**
	 * Filter the script parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function scripts( $struct ) {
		$struct['element'] = null;
		if ( $this->setting->has_param( 'script' ) ) {
			$script_default = array(
				'handle'    => $this->setting->get_slug(),
				'src'       => '',
				'depts'     => array(),
				'ver'       => $this->setting->get_root_setting()->get_param( 'version' ),
				'in_footer' => true,
			);
			$this->script   = wp_parse_args( $this->setting->get_param( 'script' ), $script_default );
		}

		return $struct;
	}

	/**
	 * Enqueue scripts for this component.
	 */
	public function enqueue_scripts() {
		if ( ! empty( $this->script ) ) {
			wp_enqueue_script( $this->script['slug'], $this->script['src'], $this->script['depts'], $this->script['ver'], $this->script['in_footer'] );
		}
	}

	/**
	 * Sanitize the value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return array|bool|null
	 */
	public function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		return json_decode( $value, true );
	}
}
