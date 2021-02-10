<?php
/**
 * System UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Frame Component to render components only.
 *
 * @package Cloudinary\UI
 */
class System extends Panel {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'pre|theme/|plugins/|status/|config/|/pre';

	protected function textarea( $struct ) {
		$struct['attributes']['rows'] = 20;
		$struct['attributes']['cols'] = 100;

		return $struct;
	}

	protected function status( $struct ) {
		$struct['element'] = null;
		$report_items      = get_option( '_cloudinary_report', array() );
		$report_items      = array_unique( $report_items );
		$report_data       = array();
		foreach ( $report_items as $post_id ) {
			$report_data[ $post_id ] = wp_get_attachment_metadata( $post_id );
		}
		$struct['content'] = __( '#Reported Images Data', 'cloudinary' );
		$struct['content'] .= "\r\n";
		$struct['content'] .= strip_tags( wp_json_encode( $report_data, JSON_PRETTY_PRINT ) );
		$struct['content'] .= "\r\n";
		$struct['content'] .= "\r\n";

		return $struct;
	}

	protected function theme( $struct ) {
		$struct['element'] = null;
		$active_theme      = wp_get_theme();
		$theme_data        = array(
			'name'        => $active_theme->Name,
			'version'     => $active_theme->Version,
			'author'      => $active_theme->get( 'Author' ),
			'author_url'  => $active_theme->get( 'AuthorURI' ),
			'child_theme' => is_child_theme(),
		);
		$struct['content'] = __( '#Theme Data', 'cloudinary' );
		$struct['content'] .= "\r\n";
		$struct['content'] .= strip_tags( wp_json_encode( $theme_data, JSON_PRETTY_PRINT ) );
		$struct['content'] .= "\r\n";
		$struct['content'] .= "\r\n";

		return $struct;
	}

	protected function config( $struct ) {
		$struct['element'] = null;

		$data = $this->setting->get_root_setting()->get_value();
		unset( $data['connect'] );
		$struct['content'] = __( '#Config Data', 'cloudinary' );
		$struct['content'] .= "\r\n";
		$struct['content'] .= strip_tags( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		$struct['content'] .= "\r\n";
		$struct['content'] .= "\r\n";

		return $struct;
	}

	protected function plugins( $struct ) {
		$struct['element'] = null;
		$plugin_data       = array(
			'must_use' => wp_get_mu_plugins(),
			'plugins'  => array(),
		);
		$plugins           = get_plugins();
		$active            = wp_get_active_and_valid_plugins();
		foreach ( $active as $plugin ) {
			$plugin_data[] = get_plugin_data( $plugin );
		}
		$struct['content'] = __( '#Plugins Data', 'cloudinary' );
		$struct['content'] .= "\r\n";
		$struct['content'] .= strip_tags( wp_json_encode( $plugin_data, JSON_PRETTY_PRINT ) );
		$struct['content'] .= "\r\n";

		return $struct;
	}
}
