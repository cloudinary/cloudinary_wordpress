<?php
/**
 * System UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Plugin;
use Cloudinary\Report;
use Cloudinary\Settings\Setting;

use function Cloudinary\get_plugin_instance;

/**
 * System report Component.
 *
 * @package Cloudinary\UI
 */
class System extends Panel {

	/**
	 * Holds the Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the Report instance.
	 *
	 * @var Report
	 */
	protected $report;

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'state|button';

	/**
	 * System constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		parent::__construct( $setting );
		$this->plugin = get_plugin_instance();
		$this->report = $this->plugin->get_component( 'report' );

		add_action( 'admin_init', array( $this, 'maybe_generate_report' ) );
	}

	/**
	 * Filter the report state.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function state( $struct ) {

		$p1            = $this->get_part( 'p' );
		$p2            = $this->get_part( 'p' );
		$p3            = $this->get_part( 'p' );
		$p1['content'] = __( 'The Cloudinary system information report is enabled. You can now download the realtime report and, if required, share it privately with your Cloudinary support contact.', 'cloudinary' );
		$p2['content'] = __( 'This report will contain information about:', 'cloudinary' );
		$p3['content'] = __( 'Disabling reporting will cleanup your tracked items.', 'cloudinary' );

		$default = array(
			__( 'Your system environment — site URL, WordPress version, PHP version, and PHP loaded extensions.', 'cloudinary' ),
			__( 'Your theme.', 'cloudinary' ),
			__( 'Your active plugins.', 'cloudinary' ),
			__( 'Your Cloudinary settings.', 'cloudinary' ),
		);

		$struct['element']           = 'div';
		$struct['children'][]        = $p1;
		$struct['children'][]        = $p2;
		$struct['children']['items'] = $this->get_part( 'ul' );

		foreach ( $default as $item ) {
			$struct['children']['items']['children'][] = $this->get_list_item( $item );
		}

		$items = $this->get_items();
		if ( ! empty( $items ) ) {
			$struct['children']['items']['children'][] = $this->get_list_item( __( 'Raw data about:', 'cloudinary' ) );
			$struct['children']['items']['children'][] = $items;
			$struct['children'][]                      = $p3;
		}

		return $struct;
	}

	/**
	 * Filter the download button.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function button( $struct ) {
		$url = add_query_arg(
			array(
				'generate_report' => true,
			),
			$this->setting->get_option_parent()->get_component()->get_url()
		);

		$button               = $this->get_part( 'a' );
		$button['content']    = __( 'Download report', 'cloudinary' );
		$button['attributes'] = array(
			'href'   => $url,
			'target' => '_blank',
			'class'  => array(
				'button',
				'button-secondary',
			),
		);

		$struct['element']            = 'div';
		$struct['children']['button'] = $button;

		return $struct;
	}

	/**
	 * Get the tracked items structure.
	 *
	 * @return array
	 */
	protected function get_items() {

		$items = $this->plugin->get_component( 'report' )->get_report_items();

		if ( ! empty( $items ) ) {
			$output = array();

			foreach ( $items as $item ) {
				$output[ get_post_type( $item ) ][] = sprintf(
					'<a href="%1$s" title="%2$s" target="_blank">%3$s</a>',
					get_edit_post_link( $item ),
					__( 'Edit item', 'cloudinary' ),
					get_the_title( $item )
				);
			}

			$items = $this->get_part( 'ul' );

			array_walk(
				$output,
				function ( $items_array, $key ) use ( &$items ) {
					$post_type = get_post_type_object( $key );

					if ( ! is_null( $post_type ) ) {
						$items['children'][]                    = $this->get_list_item( $post_type->label );
						$items['children'][ $post_type->label ] = $this->get_part( 'ul' );

						foreach ( $items_array as $item ) {
							$items['children'][ $post_type->label ]['children'][] = $this->get_list_item( $item );
						}
					}
				}
			);

			ksort( $items );
		}

		return $items;
	}

	/**
	 * Get the LI item.
	 *
	 * @param string $item The item content.
	 *
	 * @return array
	 */
	protected function get_list_item( $item ) {
		$li            = $this->get_part( 'li' );
		$li['content'] = $item;

		return $li;
	}

	/**
	 * Maybe generate the report.
	 */
	public function maybe_generate_report() {
		$download = filter_input( INPUT_GET, 'generate_report', FILTER_VALIDATE_BOOLEAN );
		if ( $download ) {
			$report = $this->report->get_report_data();
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( "Content-Disposition: attachment; filename={$report['filename']}" );
			header( 'Content-Transfer-Encoding: text' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			echo wp_json_encode( $report['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}
	}
}
