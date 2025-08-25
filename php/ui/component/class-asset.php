<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\Assets;
use Cloudinary\UI\Component;
use Cloudinary\Utils;
use Cloudinary\Settings\Setting;
use function Cloudinary\get_plugin_instance;

/**
 * HTML Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Asset extends Panel {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|header/|tbody|rows/|/tbody|/wrap';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	protected static $capture = true;

	/**
	 * Holds the assets instance.
	 *
	 * @var Assets
	 */
	protected $assets;

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {
		$struct['element']             = 'table';
		$struct['attributes']['class'] = array(
			'widefat',
			'striped',
			'cld-table',
		);

		return $struct;
	}

	/**
	 * Filter the header parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function header( $struct ) {
		$struct['element']                     = 'thead';
		$struct['children']['item']            = $this->get_part( 'th' );
		$struct['children']['item']['content'] = $this->setting->get_param( 'title' );

		return $struct;
	}

	/**
	 * Filter the row parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function rows( $struct ) {
		$struct['element'] = null;
		foreach ( $this->setting->get_settings() as $child ) {
			$struct['children'][ $child->get_slug() ] = $this->get_item_row( $child );
		}

		return $struct;
	}

	/**
	 * Get an item row.
	 *
	 * @param Setting $item The setting.
	 *
	 * @return array
	 */
	protected function get_item_row( $item ) {
		$state        = $this->state->get_state( $item->get_slug() . '.viewer', 'close' );
		$control      = new On_Off( $item );
		$panel_toggle = $item->find_setting( 'toggle_' . $item->get_param( 'slug' ) );
		$panel_toggle->set_param( 'title', $item->get_param( 'title' ) );
		$panel_toggle->set_param( 'on', 'dashicons-arrow-up' );
		$panel_toggle->set_param( 'off', 'dashicons-arrow-down' );
		$panel_toggle->set_param( 'condition', array( $item->get_slug() => true ) );
		$panel_toggle->set_value( 'open' === $state ? 'on' : 'off' );
		$title_toggle = new Icon_Toggle( $panel_toggle );
		$title_toggle->setup();

		$panel_title = $item->find_setting( 'title_' . $item->get_param( 'slug' ) );
		$panel_title->set_param( 'title', $item->get_param( 'title' ) );
		$panel_title->set_param( 'condition', array( $item->get_slug() => false ) );
		$title_holder = new Icon_Toggle( $panel_title );
		$title_holder->setup();

		$item->remove_param( 'title' );

		$item_container = $this->get_part( '' );

		// Control Row.
		$row                                = $this->get_part( 'tr' );
		$row['children']['item']            = $this->get_part( 'td' );
		$row['children']['item']['content'] = $control->render();

		// Open Toggle.
		$row['children']['item']['children']['title'] = $this->get_part( 'span' );

		// Add title toggle to content.
		$row['children']['item']['children']['title']['content'] = $title_toggle->render();

		// Append title holder after toggle.
		$row['children']['item']['children']['title']['content'] .= $title_holder->render();

		$item_container['children']['row'] = $row;

		// Content Row.
		$row                       = $this->get_part( 'tr' );
		$row['children']['assets'] = $this->get_part( 'td' );
		$row['children']['assets']['attributes']['id']         = $item->get_slug() . '.viewer';
		$row['children']['assets']['attributes']['data-state'] = $state;
		$row['children']['assets']['attributes']['class']      = array(
			'tree',
			'cld-ui-conditional',
			'open' === $state ? 'open' : 'closed',
		);

		$row['children']['assets']['children']['contents'] = $this->get_manager( $item );
		$item_container['children']['manager']             = $row;

		return $item_container;
	}

	/**
	 * Get the manager part.
	 *
	 * @param array $item The item to get the manager for.
	 *
	 * @return array
	 */
	protected function get_manager( $item ) {
		$slug                           = $item->get_slug();
		$manager                        = $this->get_part( 'table' );
		$manager['attributes']['class'] = array(
			'striped',
			'widefat',
		);

		// Header.
		$header                                 = $this->get_part( 'thead' );
		$header_row                             = $this->get_part( 'tr' );
		$header_search                          = $this->get_part( 'th' );
		$header_search['attributes']['colspan'] = 2;

		$delete_checkbox                        = $this->get_part( 'input' );
		$delete_checkbox['attributes']['id']    = $slug . '_deleter'; // @todo: get the ID.
		$delete_checkbox['attributes']['type']  = 'checkbox';
		$delete_checkbox['attributes']['style'] = array(
			'margin:0 4px 0 0;',
		);
		$search_box                             = $this->get_part( 'input' );
		$search_box['attributes']['id']         = $slug . '_search'; // @todo: get the ID.
		$search_box['attributes']['type']       = 'search';
		$search_box['attributes']['class']      = array(
			'cld-search',
		);

		$search_button                        = $this->get_part( 'button' );
		$search_button['attributes']['id']    = $slug . '_reload'; // @todo: get the ID.
		$search_button['content']             = __( 'Search', 'cloudinary' );
		$search_button['attributes']['type']  = 'button';
		$search_button['attributes']['class'] = array(
			'cld-reload',
			'button',
			'button-small',
		);

		$body               = $this->get_part( 'tbody' );
		$body['render']     = true;
		$url                = $item->get_param( 'url' );
		$body['attributes'] = array(
			'class'            => array(
				'tree-branch',
				'striped',
			),
			'data-cache-point' => $url,
			'data-browser'     => $item->find_setting( 'toggle_' . $item->get_param( 'slug' ) )->get_slug(),
			'data-slug'        => $slug,
			'data-apply'       => 'apply_' . $slug,
		);

		// Combine header parts.
		$header_search['children']['checkbox']      = $delete_checkbox;
		$header_search['children']['search_input']  = $search_box;
		$header_search['children']['search_button'] = $search_button;

		$header_row['children']['search'] = $header_search;
		$header_row['children']['action'] = $this->get_part( 'th' );
		$apply                            = $this->get_part( 'button' );
		$apply['content']                 = __( 'Apply Changes', 'cloudinary' );
		$apply['attributes']              = array(
			'id'           => 'apply_' . $slug,
			'data-changes' => array(),
			'class'        => array(
				'button-primary',
				'closed',
			),
			'style'        => array(
				'float: right; margin-left: 6px;',
			),
		);
		$apply['render']                  = true;
		$header_row['children']['action']['children']['apply'] = $apply;
		$header['children']['row']                             = $header_row;
		$manager['children']['header']                         = $header;
		$manager['children']['body']                           = $body;

		return $manager;
	}

	/**
	 * Register table structures as components.
	 */
	public function setup() {
		$this->assets = get_plugin_instance()->get_component( 'assets' );
		$this->setting->set_param( 'collapse', 'closed' );
		parent::setup();
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'cloudinary-asset-manager', $plugin->dir_url . 'js/asset-manager.js', array(), $plugin->version, true );
	}

	/**
	 * Export scripts on pre-render.
	 */
	protected function pre_render() {
		$plugin = get_plugin_instance();
		$export = array(
			'update_url' => Utils::rest_url( REST_API::BASE . '/disable_cache_items' ),
			'fetch_url'  => Utils::rest_url( REST_API::BASE . '/show_cache' ),
			'purge_url'  => Utils::rest_url( REST_API::BASE . '/purge_cache' ),
			'purge_all'  => Utils::rest_url( REST_API::BASE . '/purge_all' ),
			'save_url'   => Utils::rest_url( REST_API::BASE . '/save_asset' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
		);
		wp_add_inline_script( 'cloudinary', 'var CLDASSETS = ' . wp_json_encode( $export ), 'before' );

		$plugin->add_script_data( 'editor', $export );
		parent::pre_render();
	}
}
