<?php
/**
 * Page UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Utils;

/**
 * Page Class Component
 *
 * @package Cloudinary\UI
 */
class Page extends Panel {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|header/|tabs/|form|notice/|body|/body|settings/|/form|/wrap';

	/**
	 * Filter the form parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function form( $struct ) {
		if ( $this->setting->has_param( 'has_tabs' ) ) {
			return null;
		}
		$form_atts            = array(
			'method'     => 'post',
			'action'     => 'options.php',
			'novalidate' => 'novalidate',
		);
		$struct['attributes'] = wp_parse_args( $struct['attributes'], $form_atts );

		// Don't run action if page has tabs, since the page actions will be different for each tab.
		$struct['children'] = $this->page_actions();
		$struct['content']  = wp_nonce_field( $this->get_option_name() . '-options', '_wpnonce', true, false );

		return $struct;
	}

	/**
	 * Filter the notice part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function notice( $struct ) {
		if ( Utils::get_active_setting() !== $this->setting ) {
			return null;
		}
		$html = array();

		if ( empty( $this->setting->get_admin_notices() ) ) {
			return $struct;
		}

		foreach ( $this->setting->get_admin_notices() as $setting ) {
			$html[] = $setting->get_component()->render();
			$setting->set_param( 'enabled', false );
		}
		$struct['element'] = null;
		$struct['content'] = self::compile_html( $html );

		return $struct;
	}

	/**
	 * Creates the options page and action inputs.
	 *
	 * @return array
	 */
	protected function page_actions() {

		$option_name = $this->get_option_name();
		$inputs      = array(
			'option_page' => $this->get_part( 'input' ),
			'action'      => $this->get_part( 'input' ),
		);
		// Set the attributes for the field.
		$option_atts                         = array(
			'type'  => 'hidden',
			'name'  => 'option_page',
			'value' => $option_name,
		);
		$inputs['option_page']['attributes'] = $option_atts;

		// Set the attributes for the field action.
		$action_atts = array(
			'type'  => 'hidden',
			'name'  => 'action',
			'value' => 'update',
		);
		// Create the action input.
		$inputs['action']['attributes'] = $action_atts;

		// Set to active.
		$inputs['action']['content']      = true;
		$inputs['option_page']['content'] = true;

		return $inputs;
	}

	/**
	 * Get the option name for this component.
	 *
	 * @return string
	 */
	protected function get_option_name() {
		// Get the options setting input field.
		$option_name = $this->setting->get_option_name();
		if ( $this->setting->has_param( 'has_tabs' ) ) {
			$option_name = Utils::get_active_setting()->get_option_name();
		}

		return $option_name;
	}

	/**
	 * Filter the Tabs part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function tabs( $struct ) {

		if ( $this->setting->has_param( 'has_tabs' ) && 1 < count( $this->setting->get_settings( 'page' ) ) ) {
			$struct['element']             = 'ul';
			$struct['attributes']['class'] = array(
				'cld-page-tabs',
			);
			$struct['children']            = $this->get_tabs();
		}

		return $struct;
	}

	/**
	 * Get the tab parts structure.
	 *
	 * @return array
	 */
	protected function get_tabs() {

		$tabs = array();
		foreach ( $this->setting->get_settings() as $setting ) {
			if ( ! $setting->get_component()->is_enabled() ) {
				continue;
			}
			// Create the tab wrapper.
			$tab                        = $this->get_part( 'li' );
			$tab['attributes']['class'] = array(
				'cld-page-tabs-tab',
			);

			if ( $setting->has_param( 'is_active' ) ) {
				$tab['attributes']['class'][] = 'is-active';
			}

			// Create the link.
			$link                       = $this->get_part( 'a' );
			$link['content']            = $setting->get_param( 'menu_title', $setting->get_param( 'page_title' ) );
			$link['attributes']['href'] = $setting->get_component()->get_url();

			// Add tab to list.
			$tab['children'][ $setting->get_slug() ] = $link;
			$tabs[ $setting->get_slug() ]            = $tab;
		}

		return $tabs;
	}

	/**
	 * Get the URL for this page.
	 *
	 * @return string
	 */
	public function get_url() {

		$args = array(
			'page' => $this->setting->get_slug(),
		);
		if ( $this->setting->has_parent() && $this->setting->get_parent()->has_param( 'has_tabs' ) ) {
			$args['tab']  = $args['page'];
			$args['page'] = $this->setting->get_parent()->get_slug();
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Filter the header part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function header( $struct ) {
		if ( $this->setting->has_param( 'page_header' ) ) {
			$struct['element'] = null;
			$struct['content'] = $this->setting->get_param( 'page_header' )->render_component();
		}

		return $struct;
	}

	/**
	 * Filter the settings based on active tab.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function settings( $struct ) {

		if ( $this->setting->has_param( 'has_tabs' ) && $this->setting->has_param( 'active_tab' ) ) {
			$struct['content'] = $this->setting->get_param( 'active_tab' )->render_component();

			return $struct;
		}

		return parent::settings( $struct );
	}
}

