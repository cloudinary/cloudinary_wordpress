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
	 * @var string|null
	 */
	public $master = null;

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
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param string $name The name for this branch.
	 */
	public function __construct( $name ) {
		$this->name = basename( $name );
		$this->id   = $name;
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
			$this->paths[ $part ]->master   = $this->master;
		}

		return $this->paths[ $part ];
	}

	/**
	 * Get the toggle part.
	 *
	 * @return array
	 */
	public function get_toggle() {
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
		}
		if ( $this->checked ) {
			$struct['attributes']['checked'] = $this->checked;
		}
		$struct['attributes']['data-parent'] = $this->master;
		$struct['attributes']['class']       = array(
			'cld-ui-input',
		);
		$struct['render']                    = true;
		if ( ! empty( $this->paths ) ) {
			$struct['attributes']['data-master'] = wp_json_encode( $this->get_ids() );
		}

		return $struct;
	}

	/**
	 * Render the parts together.
	 *
	 * @return array
	 */
	public function render() {

		$children = array();
		foreach ( $this->paths as $key => $branch ) {
			$children[ $branch->id ] = $branch->render();
		}

		$struct = array(
			'element'    => 'li',
			'id'         => $this->id,
			'name'       => $this->name,
			'value'      => $this->value,
			'checked'    => false,
			'master'     => $this->get_ids(),
			'attributes' => array(
				'class' => array(
					'tree-trunk',
				),
			),
			'children'   => array(
				'toggle' => $this->get_toggle(),
				'name'   => $this->get_name(),
			),
		);

		if ( ! empty( $children ) ) {
			$struct['children']['branches'] = array(
				'element'    => 'ul',
				'attributes' => array(
					'class' => array(
						'tree-branch',
					),
				),
				'children'   => $children,
			);
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
