<?php
/**
 * Abstract UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI;

use Cloudinary\Settings\Setting;

/**
 * Abstract Component.
 *
 * @package Cloudinary\UI
 */
abstract class Component {

	/**
	 * Holds the components type.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Holds the parent setting for this component.
	 *
	 * @var Setting
	 */
	protected $setting;

	/**
	 * Holds the components build parts.
	 *
	 * @var array
	 */
	protected $build_parts;

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|header|icon/|title/|collapse/|/header|body|clear/|/body|settings/|/wrap';

	/**
	 * Holds a list of the Components used parts.
	 *
	 * @var array
	 */
	protected $used_parts;
	/**
	 * Holds the components built HTML parts.
	 *
	 * @var array
	 */
	protected $html = array();

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	public $capture = false;

	/**
	 * Holds the conditional logic sequence.
	 *
	 * @var array
	 */
	protected static $condition = array();

	/**
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$this->setting = $setting;
		$class         = strtolower( get_class( $this ) );
		$class_name    = substr( strrchr( $class, '\\' ), 1 );
		$this->type    = str_replace( '_', '-', $class_name );

		// Setup blueprint.
		$this->blueprint = $this->setting->get_param( 'blueprint', $this->blueprint );

		// Setup conditional logic.
		if ( $this->setting->has_param( 'condition' ) ) {
			$condition = $this->setting->get_param( 'condition' );
			foreach ( $condition as $slug => $value ) {
				$bound = $this->setting->get_root_setting()->get_setting( $slug, false );
				if ( ! is_null( $bound ) ) {
					$bound->set_param( 'attributes:input:data-bind-trigger', $bound->get_slug() );
				} else {
					$this->setting->set_param( 'condition', null );
				}
			}
		}

		// Setup the components parts for render.
		$this->setup_component_parts();

		// Add scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Setup the component.
	 */
	public function setup() {

	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
	}

	/**
	 * Magic caller to filter component parts dynamically.
	 *
	 * @param string $name The part name.
	 * @param array  $args array of args to pass to the filter.
	 *
	 * @return mixed
	 */
	public function __call( $name, $args ) {
		if ( empty( $args ) ) {
			return null;
		}
		$struct = $args[0];
		if ( $this->setting->has_param( $name ) ) {
			$struct['content'] = $this->setting->get_param( $name );
		}

		// Apply type to each structs.
		$struct['attributes']['class'][] = 'cld-' . $this->type;

		return $struct;
	}

	/**
	 * Setup the components build parts.
	 */
	protected function setup_component_parts() {

		$default_input_atts = array(
			'type'  => $this->type,
			'class' => array(),
		);
		$input_atts         = $this->setting->get_param(
			'attributes',
			array()
		);

		$build_parts = array(
			'wrap'        => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'header'      => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'icon'        => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'title'       => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'collapse'    => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'tooltip'     => array(
				'element'    => 'span',
				'attributes' => array(
					'class' => array(),
				),
			),
			'body'        => array(
				'element'    => 'p',
				'attributes' => array(
					'class' => array(),
				),
			),
			'clear'       => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(
						'clear',
					),
				),
			),
			'input'       => array(
				'element'    => 'input',
				'render'     => 'true',
				'attributes' => wp_parse_args( $input_atts, $default_input_atts ),
			),
			'settings'    => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'prefix'      => array(
				'element'    => 'span',
				'attributes' => array(
					'class' => array(),
				),
			),
			'suffix'      => array(
				'element'    => 'span',
				'attributes' => array(
					'class' => array(),
				),
			),
			'description' => array(
				'element'    => 'div',
				'attributes' => array(
					'class' => array(),
				),
			),
			'conditional' => array(
				'element'    => 'div',
				'attributes' => array(
					'data-condition' => wp_json_encode( $this->setting->get_param( 'condition', array() ) ),
				),
			),
		);

		/**
		 * Filter the components build parts.
		 *
		 * @param array $build_parts The build parts.
		 * @param self  $type        The component object.
		 *
		 * @return array
		 */
		$structs = apply_filters( 'setup_component_parts', $build_parts, $this );
		foreach ( $structs as $name => $struct ) {
			$struct['attributes']['class'][] = 'cld-ui-' . $name;
			$this->register_component_part( $name, $struct );
		}
	}

	/**
	 * Registers a new component part type.
	 *
	 * @param string $name   Name for the part type.
	 * @param array  $struct The array structure for the part type.
	 */
	public function register_component_part( $name, $struct ) {
		$base                       = array(
			'element'    => 'div',
			'attributes' => array(),
			'children'   => array(),
			'content'    => null,
		);
		$this->build_parts[ $name ] = wp_parse_args( $struct, $base );
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
	 * Check if component is enabled.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		$enabled = $this->setting->get_param( 'enabled', true );
		if ( is_callable( $enabled ) ) {
			$enabled = call_user_func( $enabled, $this );
		}

		return $enabled;
	}

	/**
	 * Renders the component.
	 *
	 * @param bool $echo Flag to echo output or return it.
	 *
	 * @return string
	 */
	public function render( $echo = false ) {
		// Setup the component.
		$this->pre_render();

		// Check if component is enabled.
		$enabled = $this->is_enabled();
		if ( false === $enabled ) {
			return null;
		}
		// Build the blueprint parts list.
		$blueprint   = $this->setting->get_param( 'blueprint', $this->blueprint );
		$build_parts = explode( '|', $blueprint );

		// Build the multi-dimensional array.
		$struct = $this->build_struct( $build_parts );
		$this->compile_structures( $struct );

		// Output html.
		$return = self::compile_html( $this->html );
		if ( false === $echo ) {
			return $return;
		}
		echo $return; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Build the structures from the build parts.
	 *
	 * @param array $parts Array of build parts to build.
	 *
	 * @return array
	 */
	protected function build_struct( &$parts ) {

		$struct = array();
		while ( ! empty( $parts ) ) {
			$part  = array_shift( $parts );
			$state = $this->get_state( $part );
			if ( 'close' === $state ) {
				return $struct;
			}
			$name                 = trim( $part, '/' );
			$part_struct          = $this->get_part( $name );
			$part_struct['state'] = $state;
			$part_struct['name']  = $name;
			$struct[ $name ]      = $this->{$name}( $part_struct );
			// Prepare struct array.
			$this->prepare_struct_array( $struct, $parts, $name );
		}

		return $struct;
	}

	/**
	 * Prepared struct for children and multiple element building.
	 *
	 * @param array  $struct The structure array.
	 * @param array  $parts  The parts of the component.
	 * @param string $name   The component part name.
	 */
	protected function prepare_struct_array( &$struct, &$parts, $name ) {
		if ( ! isset( $struct[ $name ] ) ) {
			return; // Bail if struct is missing.
		}
		if ( $this->is_struct_array( $struct[ $name ] ) ) {
			$base_struct = $struct[ $name ];
			unset( $struct[ $name ] );
			foreach ( $base_struct as $index => $struct_instance ) {
				$struct_name            = $struct_instance['name'] . '_inst_' . $index;
				$struct[ $struct_name ] = $struct_instance;
				$this->prepare_struct_array( $struct, $parts, $struct_name );
			}

			return;
		}
		// Build children.
		if ( 'open' === $struct[ $name ]['state'] ) {
			$struct[ $name ]['children'] += $this->build_struct( $parts );
		}
	}

	/**
	 * Check if the structure is an array of structures.
	 *
	 * @param array $struct The structure to check.
	 *
	 * @return bool
	 */
	protected function is_struct_array( $struct ) {
		return is_array( $struct ) && ! isset( $struct['state'] ) && isset( $struct[0] );
	}

	/**
	 * Go through the structures and compile.
	 *
	 * @param array $structure The components structures.
	 */
	protected function compile_structures( $structure ) {
		foreach ( $structure as $struct ) {
			$this->handle_structure( $struct['name'], $struct );
		}
	}

	/**
	 * Get a blueprint parts state.
	 *
	 * @param string $part The part name.
	 *
	 * @return string
	 */
	public function get_state( $part ) {
		$state = 'open';
		$pos   = strpos( $part, '/' );
		if ( is_int( $pos ) ) {
			switch ( $pos ) {
				case 0:
					$state = 'close';
					break;
				default:
					$state = 'void';
			}
		}

		return $state;
	}

	/**
	 * Get the components value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->setting->get_value();
	}

	/**
	 * Handles a structure part before rendering.
	 *
	 * @param string $name   The name of the part.
	 * @param array  $struct The parts structure.
	 */
	public function handle_structure( $name, $struct ) {
		if ( $this->has_content( $name, $struct ) ) {
			if ( ! empty( $struct['element'] ) && $this->setting->has_param( 'condition' ) ) {
				$struct = $this->conditional( $struct );
				$this->setting->set_param( 'condition', null );
			}
			$this->compile_part( $struct );
		}
	}

	/**
	 * Recursively check if the current structure has content.
	 *
	 * @param string | null $name   The name of the part.
	 * @param array         $struct The part structure.
	 *
	 * @return bool
	 */
	public function has_content( $name, $struct = array() ) {
		$return = ! empty( $struct['content'] ) || ! empty( $this->setting->get_param( $name ) ) || ! empty( $struct['render'] );
		if ( false === $return && ! empty( $struct['children'] ) ) {
			foreach ( $struct['children'] as $child => $child_struct ) {
				if ( true === $this->has_content( $child, $child_struct ) ) {
					$return = true;
					break;
				}
			}
		}

		return $return;
	}

	/**
	 * Build a component part.
	 *
	 * @param array $struct The component part structure array.
	 */
	public function compile_part( $struct ) {
		$this->open_tag( $struct );
		if ( ! self::is_void_element( $struct['element'] ) ) {
			$this->add_content( $struct['content'] );
			if ( ! empty( $struct['children'] ) ) {
				foreach ( $struct['children'] as $child ) {
					if ( ! is_null( $child ) ) {
						$this->handle_structure( $child['name'], $child );
					}
				}
			}
			$this->close_tag( $struct );
		}
	}

	/**
	 * Opens a new tag.
	 *
	 * @param array $struct The tag structure.
	 */
	protected function open_tag( $struct ) {
		if ( ! empty( $struct['element'] ) ) {
			$this->html[] = self::build_tag( $struct['element'], $struct['attributes'] );
		}
	}

	/**
	 * Closes an open tag.
	 *
	 * @param array $struct The tag structure.
	 */
	protected function close_tag( $struct ) {
		if ( ! empty( $struct['element'] ) ) {
			$this->html[] = self::build_tag( $struct['element'], $struct['attributes'], 'close' );
		}
	}

	/**
	 * Adds the content to the html.
	 *
	 * @param string $content The content to add.
	 */
	protected function add_content( $content ) {

		if ( ! is_string( $content ) && is_callable( $content ) ) {
			$this->html[] = call_user_func( $content );
		} else {
			$this->html[] = $content;
		}
	}

	/**
	 * Check if an element type is a void elements.
	 *
	 * @param string $element The element to check.
	 *
	 * @return bool
	 */
	public static function is_void_element( $element ) {
		$void_elements = array(
			'area',
			'base',
			'br',
			'col',
			'embed',
			'hr',
			'img',
			'input',
			'link',
			'meta',
			'param',
			'source',
			'track',
			'wbr',
		);

		return in_array( strtolower( $element ), $void_elements, true );
	}

	/**
	 * Build an HTML tag.
	 *
	 * @param string $element    The element to build.
	 * @param array  $attributes The attributes for the tags.
	 * @param string $state      The element state.
	 *
	 * @return string
	 */
	public static function build_tag( $element, $attributes = array(), $state = 'open' ) {

		$prefix_element = 'close' === $state ? '/' : '';
		$tag            = array();
		$tag[]          = $prefix_element . $element;
		if ( 'close' !== $state ) {
			$tag[] = self::build_attributes( $attributes );
		}
		$tag[] = self::is_void_element( $element ) ? '/' : null;

		return self::compile_tag( $tag );
	}

	/**
	 * Get a build part to construct.
	 *
	 * @param string $part The part name.
	 *
	 * @return array
	 */
	public function get_part( $part ) {
		$struct = array(
			'element'    => $part,
			'attributes' => array(),
			'children'   => array(),
			'state'      => null,
			'content'    => null,
			'name'       => $part,
		);
		if ( isset( $this->build_parts[ $part ] ) ) {
			$struct = wp_parse_args( $this->build_parts[ $part ], $struct );
		}
		if ( $this->setting->has_param( "attributes:{$part}" ) ) {
			$struct['attributes'] = wp_parse_args( $this->setting->get_param( "attributes:{$part}" ), $struct['attributes'] );
		}

		return $struct;
	}

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function title( $struct ) {
		$struct['content'] = $this->setting->get_param( 'title', $this->setting->get_param( 'page_title' ) );

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
		$struct['content'] = null;
		if ( $this->setting->has_param( 'tooltip_text' ) ) {

			$struct['render']                     = true;
			$struct['attributes']['class']        = array(
				'dashicons',
				'dashicons-info',
				'cld-tooltip',
			);
			$tooltip_id                           = 'tooltip_' . $this->setting->get_slug();
			$struct['attributes']['data-tooltip'] = $tooltip_id;

			$content                        = $this->get_part( 'span' );
			$content['content']             = $this->setting->get_param( 'tooltip_text' );
			$content['attributes']['id']    = $tooltip_id;
			$content['attributes']['class'] = 'hidden';
			$struct['children']['content']  = $content;
		}

		return $struct;
	}

	/**
	 * Filter the icon parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function icon( $struct ) {

		$icon   = $this->setting->get_param( 'icon' );
		$method = 'dashicon';
		if ( false === strpos( $icon, 'dashicons' ) ) {
			$method = 'image_icon';
		}

		return $this->$method( $struct );
	}

	/**
	 * Filter the dashicon parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function dashicon( $struct ) {
		$struct['element']               = 'span';
		$struct['attributes']['class'][] = 'dashicons';
		$struct['attributes']['class'][] = $this->setting->get_param( 'icon' );

		return $struct;
	}

	/**
	 * Filter the image icons parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function image_icon( $struct ) {
		$struct['element']           = 'img';
		$struct['attributes']['src'] = $this->setting->get_param( 'icon' );

		return $struct;
	}

	/**
	 * Filter the settings parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function settings( $struct ) {
		$struct['element'] = null;
		if ( $this->setting->has_parent() && $this->setting->has_settings() ) {
			$html = array();
			foreach ( $this->setting->get_settings() as $setting ) {
				$html[] = $setting->get_component()->render();
			}
			$struct['content'] = self::compile_html( $html );
		}

		return $struct;
	}

	/**
	 * Builds and sanitizes attributes for an HTML tag.
	 *
	 * @param array $attributes Array of key value attributes to build.
	 *
	 * @return string
	 */
	public static function build_attributes( $attributes ) {
		$return = array();
		foreach ( $attributes as $attribute => $value ) {
			if ( is_array( $value ) ) {
				if ( count( $value ) !== count( $value, COUNT_RECURSIVE ) ) {
					$value = wp_json_encode( $value );
				} else {
					$value = implode( ' ', $value );
				}
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
		$html = array_filter( $html );

		return implode( '', $html );
	}

	/**
	 * Compiles a tag from a parts array into a string.
	 *
	 * @param array $tag Tag parts array.
	 *
	 * @return string
	 */
	public static function compile_tag( $tag ) {
		$tag = array_filter( $tag );

		return '<' . implode( ' ', $tag ) . '>';
	}

	/**
	 * Init the component.
	 *
	 * @param Setting $setting The setting object.
	 *
	 * @return self
	 */
	final public static function init( $setting ) {

		$caller = get_called_class();
		$type   = $setting->get_param( 'type', 'tag' );
		// Final check if type is callable component.
		if ( ! is_string( $type ) || ! self::is_component_type( $type ) ) {
			// Check what type this component needs to be.
			if ( is_callable( $type ) ) {
				$setting->set_param( 'callback', $type );
				$setting->set_param( 'type', 'custom' );
				$type = 'custom';
			} else {
				// Set to a default HTML component if not found.
				$type = 'html';
			}
			$component = "{$caller}\\{$type}";
		} else {
			// Set Caller.
			$component = "{$caller}\\{$type}";
		}

		return new $component( $setting );
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

	/**
	 * Filter the conditional struct.
	 *
	 * @param array $struct The struct array.
	 *
	 * @return array
	 */
	protected function conditional( $struct ) {

		if ( $this->setting->has_param( 'condition' ) ) {
			$conditions = $this->setting->get_param( 'condition' );
			$results    = array();
			$class      = 'open';
			foreach ( $conditions as $slug => $value ) {
				$compare_value = $this->setting->find_setting( $slug )->get_value();
				$results[]     = $value === $compare_value;
			}
			$struct['attributes']['class'][] = 'cld-ui-conditional';

			if ( in_array( false, $results, true ) ) {
				$class = 'closed';
			}
			$struct['attributes']['class'][]        = $class;
			$struct['attributes']['data-condition'] = wp_json_encode( $this->setting->get_param( 'condition', array() ) );
		}

		return $struct;
	}

	/**
	 * Filter the body struct.
	 *
	 * @param array $struct The struct array.
	 *
	 * @return array
	 */
	protected function body( $struct ) {
		$struct['content'] = $this->setting->get_param( 'content' );

		return $struct;
	}

	/**
	 * Setup action before rendering.
	 */
	protected function pre_render() {
	}
}
