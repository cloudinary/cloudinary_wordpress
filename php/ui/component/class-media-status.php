<?php
/**
 * Media Status UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use function Cloudinary\get_plugin_instance;
use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use Cloudinary\Sync;
use Cloudinary\Media;
use Cloudinary\Cache;
use Cloudinary\Plugin;

/**
 * Media Sync Status Component to render plan status.
 *
 * @package Cloudinary\UI
 */
class Media_Status extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'title/|box_status/';

	/**
	 * Holds the plugin url.
	 *
	 * @var string
	 */
	protected $dir_url;

	/**
	 * Holds the media instance
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the plugin instance
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the media sync data setting.
	 *
	 * @var string
	 */
	protected $data;

	/**
	 * Plan constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {

		$this->plugin  = get_plugin_instance();
		$this->media   = $this->plugin->get_component( 'media' );
		$this->dir_url = $this->plugin->dir_url;

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
	 * Filter the plan box part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function box_status( $struct ) {

		if ( $this->is_all_sync() ) {
			$message            = $this->get_part( 'span' );
			$message['content'] = __( 'All assets are synced', 'cloudinary' );

			$struct['element']             = 'div';
			$struct['attributes']['class'] = array(
				'notification',
				'notification-success',
				'dashicons-before',
				'dashicons-yes-alt',
			);
			$struct['children']['message'] = $message;

		} else {
			$title            = $this->get_part( 'h3' );
			$title['content'] = __( 'Media assets are synced to Cloudinary', 'cloudinary' );

			$icon                      = $this->get_part( 'icon' );
			$icon['element']           = 'img';
			$icon['attributes']['src'] = $this->dir_url . 'css/upload.svg';
			$icon['render']            = true;

			$status                        = $this->get_part( 'span' );
			$status['attributes']['class'] = array(
				'status',
			);
			$status['content']             = sprintf(
			// translators: number of synced media of all.
				__( '%1$d of %2$d', 'cloudinary' ),
				$this->get_total_synced_media(),
				$this->get_total_of_media()
			);

			$struct['element']             = 'div';
			$struct['attributes']['class'] = array(
				'media-status-box',
			);
			$struct['children']['icon']    = $icon;
			$struct['children']['title']   = $title;
			$struct['children']['status']  = $status;
		}

		return $struct;
	}

	/**
	 * Check if all media is synced.
	 *
	 * @return bool
	 */
	protected function is_all_sync() {
		return ! ( $this->get_total_synced_media() < $this->get_total_of_media() );
	}

	/**
	 * Get number of media items synced.
	 *
	 * @return int
	 */
	protected function get_total_synced_media() {

		$params = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => Sync::META_KEYS['public_id'],
					'compare' => 'EXISTS',
				),

			),
		);
		$query = new \WP_Query( $params );

		return $query->found_posts;
	}

	/**
	 * Get the total of media items registered in WordPress.
	 *
	 * @return int
	 */
	protected function get_total_of_media() {

		$params = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
		);
		$query  = new \WP_Query( $params );

		return $query->found_posts;
	}
}
