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

			if ( empty( $tabs ) ) {
				$tab['attributes']['class'][] = 'is-active';
			}

			// Create the link.
			$link                       = $this->get_part( 'a' );
			$link['content']            = $tab_conf['text'];
			$link['attributes']['href'] = $tab_conf['id'];

			// Add tab to list.
			$tab['children'][ $index ] = $link;
			$tabs[ $index ]            = $tab;
		}

		return $tabs;
	}

}
