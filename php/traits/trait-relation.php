<?php
/**
 * Relation Trait offers using the relationship table.
 *
 * @package   Cloudinary\Traits
 */

namespace Cloudinary\Traits;

use Cloudinary;
use function Cloudinary\get_plugin_instance;

/**
 * Trait Relation
 *
 * @package Cloudinary\Traits
 */
trait Relation_Trait {

	/**
	 * Holds the query_relations method.
	 *
	 * @method array query_relations( array $public_ids, array $urls = array() )
	 *
	 * @var callable
	 */
	protected $query_relations;

	/**
	 * Get a delivery relation.
	 */
	protected function setup_relations() {

		if ( ! $this->query_relations ) {
			$delivery              = get_plugin_instance()->get_component( 'delivery' );
			$this->query_relations = array( $delivery, 'query_relations' );
		}
	}
}
