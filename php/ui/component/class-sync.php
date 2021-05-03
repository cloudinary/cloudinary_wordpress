<?php
/**
 * Sync Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Sync Component to hold data.
 *
 * @package Cloudinary\UI
 */
class Sync extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|icon/|div|label|title|tooltip/|/title|/label|/div|status/|action/|/wrap';

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function status( $struct ) {

		$to_sync = $this->count_to_sync();

		$struct['attributes']['class'] = array(
			'notification',
			'dashicons-before',
		);

		if ( empty( $to_sync ) || $this->setting->get_param( 'queue' )->is_enabled() ) {
			$struct['element'] = 'div';

			// Set basis.
			$state      = 'notification-success';
			$icon       = 'dashicons-yes-alt';
			$state_text = __( 'All assets are synced', 'cloudinary' );

			if ( $this->setting->get_param( 'queue' )->is_enabled() ) {
				$state      = 'notification-syncing';
				$icon       = 'dashicons-update';
				$state_text = __( 'Syncing now', 'cloudinary' );
			}

			$message                         = $this->get_part( 'span' );
			$message['content']              = $state_text;
			$struct['attributes']['class'][] = $state;
			$struct['attributes']['class'][] = $icon;

			$struct['children']['message'] = $message;
		}

		return $struct;
	}

	/**
	 * Filter the action part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function action( $struct ) {

		if ( empty( $this->count_to_sync() ) ) {
			return null;
		}

		$struct['element'] = 'a';
		$href              = $this->setting->find_setting( 'sync_media' )->get_component()->get_url();
		$args              = array();

		if ( ! $this->setting->get_param( 'queue' )->is_enabled() ) {
			$args['enable-bulk'] = true;
			$struct['content']   = $this->setting->get_param( 'enable_text', __( 'Sync Now', 'cloudinary' ) );

		} else {
			$args['disable-bulk']          = true;
			$struct['content']             = $this->setting->get_param( 'disable_text', __( 'Stop Sync', 'cloudinary' ) );
			$struct['attributes']['style'] = 'margin:21px;';
		}
		$struct['attributes']['class'][] = 'button';
		if ( 'off' === $this->setting->find_setting( 'auto_sync' )->get_value() ) {
			$struct['attributes']['disabled'] = 'disabled';
		} else {
			$href                         = add_query_arg( $args, $href );
			$struct['attributes']['href'] = $href;
		}
		$struct['render'] = true;

		return $struct;
	}

	/**
	 * Filter the label parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function label( $struct ) {
		$struct = parent::label( $struct );

		if ( 'off' === $this->setting->find_setting( 'auto_sync' )->get_value() ) {
			$struct['attributes']['class'][] = 'disabled';
		}

		return $struct;
	}

	/**
	 * Filter the tooltip parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function tooltip( $struct ) {
		$param = 'tooltip_on';
		if ( 'off' === $this->setting->find_setting( 'auto_sync' )->get_value() ) {
			$param = 'tooltip_off';
		}
		$this->setting->set_param( 'tooltip_text', $this->setting->get_param( $param ) );

		return parent::tooltip( $struct );
	}

	/**
	 * Get the total of unsynced assets.
	 *
	 * @return int
	 */
	protected function count_to_sync() {
		static $count;
		if ( ! is_null( $count ) ) {
			return $count;
		}
		$params = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore
				array(
					'key'     => \Cloudinary\Sync::META_KEYS['public_id'],
					'compare' => 'NOT EXISTS',
				),

			),
		);
		$query = new \WP_Query( $params );
		$count = $query->found_posts;

		return $count;
	}
}
