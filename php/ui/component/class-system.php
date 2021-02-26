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

		add_action( 'admin_init', array( $this, 'maybe_generate_report' ) );
	}

	/**
	 * Holds the report data.
	 *
	 * @var array
	 */
	protected $report_data = array();

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
		$p1['content'] = __( 'The Cloudinary report is enabled. You can now download the report and submit it privately to your support person in Cloudinary.', 'cloudinary' );
		$p2['content'] = __( 'This report will contain information about:', 'cloudinary' );
		$p3['content'] = __( 'Disabling reporting will cleanup your tracked items.', 'cloudinary' );

		$default = array(
			__( 'Your system environment — site URL, WordPress version, PHP version, and PHP loaded extensions;', 'cloudinary' ),
			__( 'Your theme;', 'cloudinary' ),
			__( 'Your active plugins;', 'cloudinary' ),
			__( 'Your Cloudinary settings;', 'cloudinary' ),
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
	 * Filter the report parts structure.
	 */
	protected function generate_report() {
		// Add system.
		$this->system();
		// Add theme.
		$this->theme();
		// Add plugins.
		$this->plugins();
		// Add posts.
		$this->posts();
		// Add config.
		$this->config();
	}

	/**
	 * Build the system report.
	 */
	protected function system() {
		$system_data = array(
			'home'           => get_bloginfo( 'url' ),
			'wordpress'      => get_bloginfo( 'version' ),
			'php'            => PHP_VERSION,
			'php_extensions' => get_loaded_extensions(),
		);
		$this->add_report_block( 'system_status', $system_data );
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
		$this->add_report_block( 'theme_status', $theme_data );
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
			$plugin_data['plugins'][] = get_plugin_data( $plugin );
		}
		$this->add_report_block( 'plugins_report', $plugin_data );
	}

	/**
	 * Build the posts report.
	 */
	protected function posts() {

		$report_items = get_option( Report::REPORT_KEY, array() );
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
				$this->add_report_block( 'media_report', $media_data );
			}
			if ( ! empty( $post_data ) ) {
				$this->add_report_block( 'post_report', $post_data );
			}
		}
	}

	/**
	 * Build the config report.
	 */
	protected function config() {
		$config = $this->setting->get_root_setting()->get_value();
		unset( $config['connect'] );
		$config['gallery'] = $this->plugin->get_component( 'media' )->gallery->get_config();
		$this->add_report_block( 'config_report', $config );
	}

	/**
	 * Create a report block setting.
	 *
	 * @param string $slug The slug.
	 * @param array  $data The data.
	 */
	protected function add_report_block( $slug, $data ) {
		$this->report_data[ $slug ] = $data;
	}

	/**
	 * Maybe generate the report.
	 */
	public function maybe_generate_report() {
		$download = filter_input( INPUT_GET, 'generate_report', FILTER_VALIDATE_BOOLEAN );
		if ( $download ) {
			$this->generate_report();
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
			echo wp_json_encode( $this->report_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}
	}
}
