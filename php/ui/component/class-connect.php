<?php
/**
 * Connect UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use function Cloudinary\get_plugin_instance;

/**
 * Connect Component.
 *
 * @package Cloudinary\UI
 */
class Connect extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'connect';

	/**
	 * Holder the Connect object.
	 *
	 * @var Connect
	 */
	protected $connect;

	/**
	 * Plan constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$plugin = get_plugin_instance();

		$this->connect = $plugin->get_component( 'connect' );

		parent::__construct( $setting );
	}

	/**
	 * Filter the connected message.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function connect( array $struct ) {
		$struct['element'] = 'div';

		$icon                        = $this->get_part( 'span' );
		$icon['render']              = true;
		$icon['attributes']['class'] = array(
			'dashicons',
			'dashicons-yes-alt',
		);

		$user  = wp_get_current_user();
		$intro = $this->get_part( 'h3' );
		// translators: The users name.
		$intro['content'] = sprintf( __( 'Hi %s', 'cloudinary' ), $user->display_name );

		// Add message part.
		$message            = $this->get_part( 'span' );
		$message['content'] = sprintf( __( "You're successfully connected to Cloudinary.", 'cloudinary' ), $this->connect->get_cloud_name() );

		// Add wrapper for intro and message.
		$text                        = $this->get_part( 'span' );
		$text['children']['intro']   = $intro;
		$text['children']['message'] = $message;

		// Add disconnect button.
		$button                               = $this->get_part( 'button' );
		$button['content']                    = __( 'Disconnect', 'cloudinary' );
		$button['attributes']['type']         = 'submit';
		$button['attributes']['name']         = 'connect[cloudinary_url]';
		$button['attributes']['data-confirm'] = __( 'Are you sure you want to disconnect from Cloudinary?', 'cloudinary' );
		$button['attributes']['value']        = '';
		$button['attributes']['class']        = array(
			'button',
			'button-primary',
		);

		// Combine all parts.
		$struct['attributes']['class'] = array(
			'cld-connection-box',
		);
		$struct['children']['icon']    = $icon;
		$struct['children']['text']    = $text;
		$struct['children']['button']  = $button;

		return $struct;
	}
}
