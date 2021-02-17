<?php
/**
 * System UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Support;

/**
 * System report Component.
 *
 * @package Cloudinary\UI
 */
class System extends Panel {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'report|settings/|input/';

	/**
	 * Holds the report data.
	 *
	 * @var array
	 */
	protected $report_data = array();

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'hidden';
		$struct['attributes']['name']  = 'system_report';
		$struct['attributes']['value'] = wp_json_encode( $this->report_data );

		return $struct;
	}

	/**
	 * Filter the report parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function report( $struct ) {

		$struct['element'] = null;
		// Add theme.
		$this->theme();
		// Add plugins.
		$this->plugins();
		// Add posts.
		$this->posts();
		// Add config.
		$this->config();

		return $struct;
	}

	/**
	 * Build the posts report.
	 */
	protected function posts() {

		$report_items = get_option( Support::REPORT_KEY, array() );
		$report_items = array_unique( $report_items );
		if ( ! empty( $report_items ) ) {
			$post_data  = array();
			$media_data = array();
			foreach ( $report_items as $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( 'attachment' === $post_type ) {
					$data                   = wp_get_attachment_metadata( $post_id );
					$data['all_meta']       = get_post_meta( $post_id );
					$media_data[ $post_id ] = $data;
				} else {
					$data                  = get_post( $post_id, ARRAY_A );
					$data['post_meta']     = get_post_meta( $post_id );
					$post_data[ $post_id ] = $data;
				}
			}
			if ( ! empty( $media_data ) ) {
				$this->add_report_block( __( 'Media', 'cloudinary' ), 'media_report', $media_data );
			}
			if ( ! empty( $post_data ) ) {
				$this->add_report_block( __( 'Posts', 'cloudinary' ), 'post_report', $post_data );
			}
		}
	}

	/**
	 * Build the theme report.
	 */
	protected function theme() {
		$active_theme = wp_get_theme();
		$theme_data   = array(
			'name'        => $active_theme->get( 'Name' ),
			'version'     => $active_theme->get( 'Version' ),
			'author'      => $active_theme->get( 'Author' ),
			'author_url'  => $active_theme->get( 'AuthorURI' ),
			'child_theme' => is_child_theme(),
		);
		$this->add_report_block( __( 'Theme', 'cloudinary' ), 'theme_status', $theme_data );

	}

	/**
	 * Build the config report.
	 */
	protected function config() {
		$struct['element'] = null;

		$config = $this->setting->get_root_setting()->get_value();
		unset( $config['connect'] );
		$config['gallery']['gallery_config'] = json_decode( $config['gallery']['gallery_config'], JSON_PRETTY_PRINT );
		$this->add_report_block( __( 'Config', 'cloudinary' ), 'config_report', $config );
	}

	/**
	 * Build the plugins report.
	 */
	protected function plugins() {

		$plugin_data = array(
			'must_use' => wp_get_mu_plugins(),
			'plugins'  => array(),
		);
		$active      = wp_get_active_and_valid_plugins();
		foreach ( $active as $plugin ) {
			$plugin_data[] = get_plugin_data( $plugin );
		}
		$this->add_report_block( __( 'Plugins', 'cloudinary' ), 'plugins_report', $plugin_data );
	}

	/**
	 * Create a report block setting.
	 *
	 * @param string $title The title.
	 * @param string $slug  The slug.
	 * @param array  $data  The data.
	 */
	protected function add_report_block( $title, $slug, $data ) {
		$this->report_data[ $slug ] = $data;
		$content                    = wp_strip_all_tags( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		$theme                      = array(
			'type'        => 'group',
			'title'       => $title,
			'collapsible' => 'closed',
			array(
				'type'       => 'tag',
				'element'    => 'pre',
				'attributes' => array(
					'style' => 'overflow:auto;',
				),
				'content'    => $content,
			),
		);
		$this->setting->create_setting( $slug, $theme, $this->setting );
	}

	/**
	 * Setup the component parts, and build the download report, if needed.
	 */
	public function setup_component_parts() {
		parent::setup_component_parts();
		$options = array(
			'options' => function ( $data ) {
				return json_decode( $data, ARRAY_A );
			},
		);
		$data    = filter_input( INPUT_POST, 'system_report', FILTER_CALLBACK, $options );
		if ( $data ) {
			$timestamp = time();
			$filename  = "cloudinary-report-{$timestamp}.json";
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( "Content-Disposition: attachment; filename={$filename}" );
			header( 'Content-Transfer-Encoding: text' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			echo wp_json_encode( $data, JSON_PRETTY_PRINT );
			exit;
		}
	}
}
