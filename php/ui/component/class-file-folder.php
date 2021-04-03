<?php
/**
 * File Folder Tree UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;
use Cloudinary\UI\Branch;

/**
 * Class Color Component
 *
 * @package Cloudinary\UI
 */
class File_Folder extends On_Off {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'tree|primary_input/|ul|folder/|/ul|/tree';

	/**
	 * Flags the component as a primary.
	 *
	 * @var Setting | null
	 */
	protected $primary = null;

	/**
	 * Holds the tree object.
	 *
	 * @var Branch
	 */
	protected $tree;

	/**
	 * Holds the handler types.
	 *
	 * @var array
	 */
	protected $handler_files = array();

	/**
	 * Get the folder part struct.
	 *
	 * @param array $struct The structure.
	 *
	 * @return mixed
	 */
	protected function folder( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'tree',
		);

		$struct['children']['tree']        = $this->tree->render();
		$struct['attributes']['data-slug'] = $this->setting->get_slug();

		return $struct;
	}

	/**
	 * Get the tree part struct.
	 *
	 * @param array $struct The structure.
	 *
	 * @return mixed
	 */
	protected function tree( $struct ) {
		$paths       = (array) $this->setting->get_param( 'paths', array() );
		$checked     = (array) $this->setting->get_value();
		$clean_value = array();
		$base_path   = $this->setting->get_param( 'base_path' );
		$this->tree  = new Branch( $this->setting->get_slug() . '_root' );
		$this->tree->set_master( $this->setting->get_param( 'master' ) );
		$handlers = $this->setting->get_param( 'file_types', array() );

		foreach ( $paths as $path ) {
			$full_path = trailingslashit( $base_path ) . $path;
			$parts     = explode( '/', ltrim( $path, '/' ) );
			$previous  = $this->tree;
			$length    = count( $parts ) - 1;
			foreach ( $parts as $index => $folder ) {

				$previous = $previous->get_path( $folder );
				if ( $length === $index ) {
					$previous->value  = $full_path;
					$previous->parent = $this->setting->get_slug();
					if ( in_array( $full_path, $checked, true ) ) {
						$previous->checked = true;
						$clean_value[]     = $full_path;
					}
					$ext = pathinfo( $folder, PATHINFO_EXTENSION );
					if ( isset( $handlers[ $ext ] ) ) {
						$previous->set_master( $handlers[ $ext ] );
					}
				}
			}
		}
		$this->setting->set_param( 'clean_value', $clean_value );

		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'tree';

		return $struct;
	}

	/**
	 * Structure for the primary input.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return mixed
	 */
	protected function primary_input( $struct ) {

		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'hidden';
		$struct['attributes']['name']  = $this->get_name();
		$struct['attributes']['id']    = $this->setting->get_slug();
		$struct['attributes']['value'] = wp_json_encode( $this->setting->get_param( 'clean_value', array() ) );
		$struct['render']              = true;

		return $struct;
	}

	/**
	 * Set the master control.
	 *
	 * @param string $master The slug of the master setting.
	 * @param string $slug   The slug of the setting to be controlled.
	 */
	protected function set_master( $master, $slug ) {
		$master = $this->setting->find_setting( $master );
		$list   = $master->get_param( 'master', array() );
		$list[] = $slug;
		$master->set_param( 'master', $list );
	}

	/**
	 * Decode the serialised value.
	 *
	 * @param string $value The string to decode.
	 *
	 * @return array|bool|string
	 */
	public function sanitize_value( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		return array_map( 'esc_url', (array) $value );
	}
}
