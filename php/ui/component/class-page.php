<?php
/**
 * Page UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Page Class Component
 *
 * @package Cloudinary\UI
 */
class Page extends Panel {


	/**
	 * Create the start of the tabs wrapper.
	 *
	 * @return string
	 */
	protected function start_tabs() {
		$atts = array(
			'class' => array(
				'settings-ui-component',
				'settings-ui-component-tabs',
			),
		);

		return '<nav ' . $this->build_attributes( $atts ) . ' >';
	}

	/**
	 * Render the tabs index.
	 */
	protected function tab_bar() {
		$html[] = $this->start_tabs();
		$active = $this->get_active_setting();
		$url    = add_query_arg( array( 'page' => $this->setting->get_parent()->get_slug() ), admin_url( 'admin.php' ) );
		foreach ( $this->setting->get_settings() as $setting ) {
			$url       = add_query_arg( array( 'tab' => $setting->get_slug() ), $url );
			$link_atts = array(
				'href'  => $url,
				'class' => array(
					'settings-ui-component-tabs-tab',
				),
			);
			if ( $active === $setting ) {
				$link_atts['class'][] = 'active';
			}
			$html[] = '<a ' . $this->build_attributes( $link_atts ) . ' >';
			$html[] = $setting->get_param( 'title' );
			$html[] = '</a>';
		}
		$html[] = $this->end_tabs();

		return self::compile_html( $html );
	}

	/**
	 * Creates the Content/Input HTML.
	 *
	 * @return string
	 */
	protected function content() {


		$option_name = $this->get_option_name();
		settings_errors( $option_name );
		// Set the attributes for the field.
		$option = array(
			'type'  => 'hidden',
			'name'  => 'option_page',
			'value' => $option_name,
		);
		// Set the attributes for the field action.
		$action = array(
			'type'  => 'hidden',
			'name'  => 'action',
			'value' => 'update',
		);
		$html   = array(
			'<input ' . $this->build_attributes( $option ) . ' />',
			'<input ' . $this->build_attributes( $action ) . ' />',
			wp_nonce_field( $option_name . '-options', '_wpnonce', true, false ),
		);

		if ( $this->setting->has_parent() && $this->setting->has_param( 'has_tabs' ) && 1 < $this->setting->get_setting_slugs() ) {
			$html[] = $this->tab_bar();
		}

		return self::compile_html( $html );
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
			$option_name = $this->get_active_setting()->get_option_name();
		}

		return $option_name;
	}

	/**
	 * Start the component Wrapper.
	 *
	 * @return string
	 */
	protected function start_wrapper() {

		$form_atts = array(
			'method'     => 'post',
			'action'     => 'options.php',
			'novalidate' => 'novalidate',
			'class'      => 'render-trigger',
		);
		$html      = array(
			'<div ' . $this->build_attributes( $this->get_attributes( 'wrapper' ) ) . '>',
			'<h1>' . $this->setting->get_param( 'page_title' ) . '</h1>',
			'<form ' . $this->build_attributes( $form_atts ) . ' >',
		);

		return self::compile_html( $html );
	}

	/**
	 * Create the end of the wrapper HTML.
	 *
	 * @return string
	 */
	protected function end_wrapper() {
		$html = array(
			'</form>',
			'</div>',
		);

		return self::compile_html( $html );
	}

	/**
	 * Create the end of the tabs wrapper.
	 *
	 * @return string
	 */
	protected function end_tabs() {
		return '</nav>';
	}

	/**
	 * Render the settings of the active tab.
	 *
	 * @return string
	 */
	protected function settings() {
		if ( $this->setting->has_param( 'has_tabs' ) ) {
			return $this->get_active_setting()->render_component();
		}

		return parent::settings();
	}
}

