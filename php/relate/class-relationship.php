<?php
/**
 * Relationship class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Relate;

use Cloudinary\Utils;

/**
 * Class Relationship
 */
class Relationship {

	/**
	 * The relationship post id.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * The relationship data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The constructor.
	 *
	 * @param int $post_id The relationship post id.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
		$this->load();
	}

	/**
	 * Load the relationship data.
	 */
	protected function load() {
		global $wpdb;
		$table_name = Utils::get_relationship_table();

		$sql        = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE post_id = %d", $this->post_id ); // phpcs:ignore WordPress.DB
		$this->data = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Set a relationship data value.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 */
	public function __set( $key, $value ) {
		if ( isset( $this->data[ $key ] ) ) {
			$this->data[ $key ] = $value;
		}
	}

	/**
	 * Get a relationship data value.
	 *
	 * @param string $key The key.
	 *
	 * @return mixed The value.
	 */
	public function __get( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}

	/**
	 * Save the relationship data.
	 *
	 * @return bool
	 */
	public function save() {
		global $wpdb;

		return $wpdb->update( Utils::get_relationship_table(), $this->data, array( 'id' => $this->data['id'] ), array( '%s' ), array( '%d' ) );// phpcs:ignore WordPress.DB
	}

	/**
	 * Get a relationship object.
	 *
	 * @param int $post_id The relationship post id.
	 *
	 * @return Relationship|null The relationship object or null if not found.
	 */
	public static function get_relationship( $post_id ) {
		static $cache = array();
		if ( ! isset( $cache[ $post_id ] ) ) {
			$cache[ $post_id ] = new self( $post_id );
		}

		return $cache[ $post_id ];
	}
}
