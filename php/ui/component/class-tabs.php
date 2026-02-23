<?php
/**
 * Tabs UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Tab Component.
 *
 * @package Cloudinary\UI
 */
class Tabs extends Page {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|tab_set/|/wrap';

	/**
	 * Filter the Tabs part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function tab_set( $struct ) {

		$struct['element']             = 'ul';
		$struct['attributes']['class'] = array(
			'cld-page-tabs',
		);
		$struct['children']            = $this->get_tabs();

		return $struct;
	}

	/**
	 * Get the tab parts structure.
	 *
	 * @return array
	 */
	protected function get_tabs() {

		$tabs = array();
		foreach ( $this->setting->get_param( 'tabs', array() ) as $index => $tab_conf ) {

			// Create the tab wrapper.
			$tab                        = $this->get_part( 'li' );
			$tab['attributes']['class'] = array(
				'cld-page-tabs-tab',
			);

			// Create the link.
			$link                           = $this->get_part( 'button' );
			$link['content']                = $tab_conf['text'];
			$link['attributes']['data-tab'] = $tab_conf['id'];

			if ( empty( $tabs ) ) {
				$link['attributes']['class'][] = 'is-active';
			}

			// Add tab to list.
			$tab['children'][ $index ] = $link;
			$tabs[ $index ]            = $tab;
		}

		return $tabs;
	}
}
