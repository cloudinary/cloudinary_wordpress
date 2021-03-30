<?php
/**
 * Branch UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI;

use Cloudinary\UI;

/**
 *  Component.
 *
 * @package Cloudinary\UI
 */
class Branch {

	/**
	 * Holds all the paths.
	 *
	 * @var array
	 */
	public $paths = array();

	/**
	 * Holds the name.
	 *
	 * @var string|null
	 */
	public $name = null;

	/**
	 * Holds the ID of the master input.
	 *
	 * @var array()
	 */
	public $master = array();

	/**
	 * Holds the full path.
	 *
	 * @var string
	 */
	public $value = '';

	/**
	 * Holds the unique ID
	 *
	 * @var string
	 */
	public $id = null;

	/**
	 * Holds if the value is checked.
	 *
	 * @var bool
	 */
	public $checked = false;

	/**
	 * Holds the list of the masters.
	 *
	 * @var array
	 */
	public $handlers = array();

	/**
	 * Holds total size of the files in this branch.
	 *
	 * @var int
	 */
	public $branch_size = 0;

	/**
	 * Holds the parent field.
	 *
	 * @var string
	 */
	public $parent;

	/**
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param string $name The name for this branch.
	 */
	public function __construct( $name = 'root' ) {
		$this->name = basename( $name );
		$this->id   = $name;
	}

	/**
	 * Set a master.
	 *
	 * @param string $master The master control ID.
	 */
	public function set_master( $master ) {
		if ( ! empty( $master ) && ! in_array( $master, $this->master, true ) ) {
			$this->master[] = $master;
		}
	}

	/**
	 * Get the path part.
	 *
	 * @param string $part Part to try get.
	 *
	 * @return Branch
	 */
	public function get_path( $part ) {
		if ( ! isset( $this->paths[ $part ] ) ) {
			$this->paths[ $part ]           = new Branch( $this->id . '/' . $part );
			$this->paths[ $part ]->handlers = $this->handlers;
		}

		return $this->paths[ $part ];
	}

	/**
	 * Get the toggle part.
	 *
	 * @return array
	 */
	public function get_switch() {
		$struct                        = array();
		$struct['element']             = 'label';
		$struct['attributes']['class'] = array(
			'cld-input-on-off-control',
			'mini',
		);
		$struct['children']['input']   = $this->input();
		$struct['children']['slider']  = $this->slider();

		return $struct;
	}

	/**
	 * Get the toggle part.
	 *
	 * @return array
	 */
	public function toggle() {
		$struct                        = array();
		$struct['element']             = 'label';
		$struct['attributes']['class'] = array(
			'cld-input-icon-toggle-control',
			'mini',
		);
		$input                         = $this->input();
		$input['attributes']['id']     = $this->id . '_toggle';
		if ( $input['attributes']['data-master'] ) {
			unset( $input['attributes']['data-master'] );
		}
		$input['attributes']['data-bind-trigger'] = $this->id . '_toggle';
		$struct['children']['input']              = $input;

		$slider                        = $this->slider();
		$slider['element']             = 'i';
		$slider['attributes']['class'] = array(
			'cld-input-icon-toggle-control-slider',
		);
		$slider['children']['on']      = $this->get_toggle_icon( 'icon-on dashicons-arrow-up' );
		$slider['children']['off']     = $this->get_toggle_icon( 'icon-off dashicons-arrow-down' );
		$struct['children']['slider']  = $slider;

		$name                       = $this->get_name();
		$name['attributes']['for']  = $this->id . '_toggle';
		$struct['children']['name'] = $name;

		return $struct;
	}

	/**
	 * Get a toggle icon part.
	 *
	 * @param string $icon Ican class.
	 *
	 * @return array
	 */
	protected function get_toggle_icon( $icon ) {
		$struct                        = array();
		$struct['element']             = 'i';
		$struct['attributes']['class'] = array(
			$icon,
			'dashicons',
		);
		$struct['render']              = true;

		return $struct;
	}

	/**
	 * Filter the name parts structure.
	 *
	 * @return array
	 */
	protected function get_name() {
		$struct                          = array();
		$struct['element']               = 'label';
		$struct['attributes']['class'][] = 'description';
		$struct['attributes']['for']     = $this->id;
		$struct['content']               = $this->name;
		$file_size                       = '';
		if ( ! empty( $this->value ) ) {
			$file_size = size_format( $this->branch_size );
		}
		$struct['children']['size'] = array(
			'element'    => 'span',
			'content'    => $file_size,
			'render'     => true,
			'attributes' => array(
				'id'    => $this->id . '_size_wrapper',
				'class' => array(
					'file-size',
					'description',
					'small',
				),
			),
		);

		return $struct;
	}

	/**
	 * Filter the slider parts structure.
	 *
	 * @return array
	 */
	public function slider() {
		$struct                        = array();
		$struct['element']             = 'span';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'cld-input-on-off-control-slider',
		);
		$struct['attributes']['style'] = array();

		return $struct;
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @return array
	 */
	public function input() {
		$struct                        = array();
		$struct['element']             = 'input';
		$struct['attributes']['id']    = $this->id;
		$struct['attributes']['type']  = 'checkbox';
		$struct['attributes']['value'] = $this->value;
		if ( ! empty( $this->value ) ) {
			$struct['attributes']['data-file'] = true;
			if ( file_exists( $this->value ) ) {
				$filesize           = filesize( $this->value );
				$this->branch_size += $filesize;
			}
		}
		$struct['attributes']['data-size'] = $this->branch_size;
		if ( $this->parent ) {
			$struct['attributes']['data-parent'] = $this->parent;
		}
		if ( $this->checked ) {
			$struct['attributes']['checked'] = $this->checked;
		}
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'cld-ui-input',
		);
		if ( ! empty( $this->master ) ) {
			$struct['attributes']['data-master'] = wp_json_encode( $this->master );
		}

		return $struct;
	}

	/**
	 * Render the parts together.
	 *
	 * @param bool $root Flag to signal a root render.
	 *
	 * @return array|null
	 */
	public function render( $root = true ) {

		$children = array();
		foreach ( $this->paths as $key => $branch ) {
			$key = ! empty( $branch->paths ) ? 'a_' . $branch->id : 'b_' . $branch->id;
			if ( ! $root ) {
				$branch->set_master( $this->id );
			}
			$children[ $key ]   = $branch->render( false );
			$this->branch_size += $branch->branch_size;
		}
		if ( ! empty( $children ) ) {
			ksort( $children );
			$child_branches = array(
				'element'    => 'ul',
				'attributes' => array(
					'class' => array(
						'tree-branch',
					),
				),
				'children'   => $children,
			);
			if ( $root ) {
				return $child_branches;
			}
		}
		$name = $this->toggle();
		if ( empty( $this->paths ) ) {
			$name = $this->get_name();
		}
		$struct = array(
			'element'    => 'li',
			'id'         => $this->id,
			'name'       => $this->name,
			'value'      => $this->value,
			'checked'    => false,
			'attributes' => array(
				'class' => array(
					'tree-trunk',
				),
			),
			'children'   => array(
				'switch' => $this->get_switch(),
				'name'   => $name,
			),
		);

		if ( ! empty( $child_branches ) ) {
			$child_branches['attributes'] = array(
				'data-condition' => wp_json_encode( array( $this->id . '_toggle' => true ) ),
				'class'          => array(
					'cld-ui-conditional',
					'closed',
					'tree-branch',
				),
			);

			$struct['children']['branches'] = $child_branches;
		}

		return $struct;
	}

	/**
	 * Get the IDS used under this.
	 *
	 * @return array
	 */
	public function get_ids() {
		$ids = array();
		foreach ( $this->paths as $key => $branch ) {
			$ids[] = $branch->id;
		}

		return $ids;
	}

}
