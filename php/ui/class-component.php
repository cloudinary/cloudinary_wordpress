<?php
/**
 * Base UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI;

use Cloudinary\Settings;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Component {

	/**
	 * Holds the parent setting for this component.
	 *
	 * @var Settings\Setting
	 */
	protected $setting;

	/**
	 * Component wrapper attributes.
	 *
	 * @var array
	 */
	protected $attributes;

	/**
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param Settings\Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$this->setting = $setting;
		$this->setup_attributes();
	}

	/**
	 * Gets the UI Component's default attributes.
	 */
	private function setup_attributes() {
		$type_class = 'settings-ui-' . $this->setting->get_param( 'type', 'component' );
		$attributes = array(
			'wrapper'         => array(
				'class' => array(
					'wrap',
					'settings-ui-component',
					'settings-ui-' . $type_class,
					$type_class,
				),
			),
			'icon'            => array(
				'class' => array(
					'settings-ui-component-icon',
					$type_class,
				),
			),
			'icon_image'      => array(
				'class' => array(
					'settings-ui-component-icon-image',
					$type_class,
				),
			),
			'heading_wrapper' => array(
				'class' => array(
					'settings-ui-component-heading',
					$type_class,
				),
			),
			'heading'         => array(
				'class' => array(
					'settings-ui-component-heading-title',
					$type_class,
				),
			),
			'tooltip'         => array(
				'class' => array(
					'settings-ui-component-tooltip',
					'dashicons',
					'dashicons-editor-help',
					$type_class,
				),
			),
			'toggle_up'       => array(
				'class' => array(
					'settings-ui-component-collapsible',
					'dashicons',
					'dashicons-arrow-up-alt2',
					$type_class,
				),
			),
			'toggle_down'     => array(
				'class' => array(
					'settings-ui-component-collapsible',
					'dashicons',
					'dashicons-arrow-down-alt2',
					$type_class,
				),
			),
			'content_wrapper' => array(
				'class' => array(
					'settings-ui-component-content',
					$type_class,
				),
			),
			'content'         => array(
				'class' => array(
					'settings-ui-component-content',
					$type_class,
				),
			),
			'settings'        => array(
				'class' => array(
					'settings-ui-component-settings',
					$type_class,
				),
			),
			'prefix'          => array(
				'class' => array(
					'settings-ui-component-prefix',
					$type_class,
				),
			),
			'suffix'          => array(
				'class' => array(
					'settings-ui-component-suffix',
					$type_class,
				),
			),
			'description'     => array(
				'class' => array(
					'settings-ui-component-description',
					$type_class,

				),
			),
		);

		$this->attributes = apply_filters( 'get_component_attributes', $attributes, $this );
	}

	/**
	 * Get a specific set of attributes.
	 *
	 * @param string $attribute_point The key of the attribute point to get.
	 *
	 * @return array
	 */
	public function get_attributes( $attribute_point ) {
		$return = array();
		if ( isset( $this->attributes[ $attribute_point ] ) ) {
			$return = $this->attributes[ $attribute_point ];
		}
		// Check if the settings has a param attribute with this sub key and merge recursively.
		$setting_attributes = $this->setting->get_attributes( $attribute_point );
		if ( ! empty( $setting_attributes ) ) {
			$return = wp_parse_args( $setting_attributes, $return );
		}

		return $return;
	}

	/**
	 * Sanitize the value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_value( $value ) {
		return wp_kses_post( $value );
	}

	/**
	 * Renders the component.
	 */
	public function render() {

		$html = array();
		// Main Component Wrapper.
		$html[] = $this->start_wrapper();

		// Component heading/title.
		if ( $this->setting->has_param( 'title' ) ) {
			$html[] = $this->start_heading();
			$html[] = $this->heading();
			$html[] = $this->end_heading();
		}
		// Component content prefix.
		if ( $this->setting->has_param( 'prefix' ) ) {
			$html[] = $this->prefix();
		}
		// Component Content.
		$html[] = $this->content();

		// Component Suffix.
		if ( $this->setting->has_param( 'suffix' ) ) {
			$html[] = $this->suffix();
		}
		// Component description.
		if ( $this->setting->has_param( 'description' ) ) {
			$html[] = $this->description();
		}
		// Do settings.
		if ( $this->setting->has_settings() ) {
			$html[] = $this->settings();
		}
		// End component wrapper.
		$html[] = $this->end_wrapper();

		return self::compile_html( $html );
	}

	/**
	 * Start the component Wrapper.
	 *
	 * @return string
	 */
	protected function start_wrapper() {
		return '<div ' . $this->build_attributes( $this->get_attributes( 'wrapper' ) ) . ' >';
	}

	/**
	 * Start the component Wrapper.
	 *
	 * @return string
	 */
	protected function start_heading() {
		return '<div ' . $this->build_attributes( $this->get_attributes( 'heading_wrapper' ) ) . ' >';
	}

	/**
	 * Creates the Content/Input HTML.
	 *
	 * @return string
	 */
	protected function content() {
		$html = array(
			'<div ' . $this->build_attributes( $this->get_attributes( 'content' ) ) . '>',
			$this->setting->get_param( 'content' ),
			'</div>',
		);

		return self::compile_html( $html );
	}

	/**
	 * Creates the Header HTML.
	 *
	 * @return string
	 */
	protected function heading() {
		$html = array();
		if ( $this->setting->has_param( 'icon' ) ) {
			$html[] = $this->get_icon();
		}
		$html[] = '<h4 ' . $this->build_attributes( $this->get_attributes( 'heading' ) ) . ' >';
		$html[] = $this->setting->get_param( 'title' );
		if ( $this->setting->has_param( 'tooltip' ) ) {
			$html[] = $this->tooltip();
		}
		$html[] = '</h4>';

		return self::compile_html( $html );
	}

	/**
	 * Create the Tooltip HTML.
	 *
	 * @return string
	 */
	protected function tooltip() {
		$atts          = $this->get_attributes( 'tooltip' );
		$atts['title'] = $this->setting->get_param( 'tooltip' );

		return '<span ' . $this->build_attributes( $atts ) . ' />';
	}

	/**
	 * Create the end of the heading wrapper HTML.
	 *
	 * @return string
	 */
	protected function end_heading() {
		return '</div>';
	}

	/**
	 * Creates a Description HTML.
	 *
	 * @return string
	 */
	protected function description() {
		$html   = array();
		$html[] = '<span ' . $this->build_attributes( $this->get_attributes( 'description' ) ) . ' >';
		$html[] = $this->setting->get_param( 'description' );
		$html[] = '</span>';

		return self::compile_html( $html );
	}

	/**
	 * Create the end of the wrapper HTML.
	 *
	 * @return string
	 */
	protected function end_wrapper() {
		return '</div>';
	}

	/**
	 * Create a dashicon HTML.
	 *
	 * @param string $icon Dashicon slug.
	 *
	 * @return string
	 */
	protected function dashicon( $icon ) {
		$atts            = $this->get_attributes( 'dashicon' );
		$atts['class'][] = $icon;

		return '<span ' . $this->build_attributes( $atts ) . ' />';
	}

	/**
	 * Create an image based icon HTML.
	 *
	 * @param string $icon Image URL.
	 *
	 * @return string
	 */
	protected function image_icon( $icon ) {
		$image_atts        = $this->get_attributes( 'icon_image' );
		$image_atts['src'] = $icon;
		$html              = array();
		$html[]            = '<span ' . $this->build_attributes( $this->get_attributes( 'icon' ) ) . ' >';
		$html[]            = '<img ' . $this->build_attributes( $this->get_attributes( 'icon_image' ) ) . ' />';
		$html[]            = '</span>';

		return self::compile_html( $html );
	}

	/**
	 * Create an icon HTML part, based on the type of icon source.
	 *
	 * @return string
	 */
	protected function get_icon() {

		$icon   = $this->setting->get_param( 'icon' );
		$method = 'dashicon';
		if ( false === strpos( $icon, 'dashicons' ) ) {
			$method = 'image_icon';
		}

		return $this->$method( $icon );
	}

	/**
	 * Create a Prefix HTML part.
	 *
	 * @return string
	 */
	protected function prefix() {
		$html = array(
			'<span' . $this->build_attributes( $this->get_attributes( 'prefix' ) ) . ' >',
			$this->setting->get_param( 'prefix' ),
			'</span>',
		);

		return self::compile_html( $html );
	}

	/**
	 * Create a suffix HTML part.
	 *
	 * @return string
	 */
	protected function suffix() {
		$html = array(
			'<span' . $this->build_attributes( $this->get_attributes( 'suffix' ) ) . ' >',
			$this->setting->get_param( 'suffix' ),
			'</span>',
		);

		return self::compile_html( $html );
	}

	/**
	 * Creates the child settings component rendered HTML part.
	 *
	 * @return string
	 */
	protected function settings() {
		$html = array();
		foreach ( $this->setting->get_settings() as $setting ) {
			$html[] = $setting->render_component();
		}

		return self::compile_html( $html );
	}

	/**
	 * Builds and sanitizes attributes for an HTML tag.
	 *
	 * @param array $attributes Array of key value attributes to build.
	 *
	 * @return string
	 */
	protected function build_attributes( $attributes ) {

		$return = array();
		foreach ( $attributes as $attribute => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', $value );
			}
			$return[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $return );

	}

	/**
	 * Compiles HTML parts array into a string.
	 *
	 * @param array $html HTML parts array.
	 *
	 * @return string
	 */
	public static function compile_html( $html ) {
		return implode( '', $html );
	}

	/**
	 * Init the component.
	 *
	 * @param Settings\Setting $setting The setting object.
	 *
	 * @return self
	 */
	public static function init( $setting ) {

		$caller = get_called_class();
		$type   = $setting->get_param( 'type' );
		// Check what type this component needs to be.
		if ( is_null( $type ) ) {
			$setting->set_param( 'type', 'component' );
		} elseif ( is_callable( $type ) ) {
			$setting->set_param( 'callback', $setting->get_param( 'type' ) );
			$setting->set_param( 'type', 'custom' );
		}
		// Final check if type is callable component.
		if ( self::is_component_type( $type ) ) {
			$caller = $caller . '\\' . $setting->get_param( 'type' );
		}

		return new $caller( $setting );
	}

	/**
	 * Check if the type is a component.
	 *
	 * @param string $type The type to check.
	 *
	 * @return bool
	 */
	public static function is_component_type( $type ) {
		$caller = get_called_class();

		// Check that this type of component exists.
		return is_callable( array( $caller . '\\' . $type, 'init' ) );
	}
}
