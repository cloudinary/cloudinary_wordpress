<?php
/**
 * Cache Status UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Cache;
use Cloudinary\Cache\Cache_Point;
use Cloudinary\Utils;

/**
 * Cache Status Component to render plan status.
 *
 * @package Cloudinary\UI
 */
class Cache_Status extends Media_Status {

	/** Holds the cache point instance.
	 *
	 * @var Cache_Point
	 */
	protected $cache;

	/**
	 * Filter the plan box part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function box_status( $struct ) {

		/**
		 * The cache component.
		 *
		 * @var \Cloudinary\Cache $cache
		 */
		$cache        = $this->plugin->get_component( 'cache' );
		$this->cache  = $cache->cache_point;
		$cache_points = $this->cache->get_active_cache_points();

		$title            = $this->get_part( 'h3' );
		$title['content'] = __( 'Assets cached to Cloudinary', 'cloudinary' );

		$struct['element']           = 'div';
		$struct['children']['title'] = $title;

		// Table header.
		$header_point                           = $this->get_part( 'th' );
		$header_point['content']                = __( 'Cache Point', 'cloudinary' );
		$header_items                           = $this->get_part( 'th' );
		$header_items['content']                = __( 'Cached items', 'cloudinary' );
		$header_items['attributes']['style']    = 'text-align:center;';
		$header_row                             = $this->get_part( 'tr' );
		$header_row['children']['cache_point']  = $header_point;
		$header_row['children']['cached_items'] = $header_items;
		$table_head                             = $this->get_part( 'thead' );
		$table_head['children']['row']          = $header_row;

		// Table body rows.
		$table_body = $this->get_part( 'tbody' );
		foreach ( $cache_points as $cache_point ) {
			$items = $this->cache->get_cache_point_cache( $cache_point->ID );

			$point_cell            = $this->get_part( 'td' );
			$point_cell['content'] = wp_basename( untrailingslashit( $cache_point->post_title ) );

			$items_cell                        = $this->get_part( 'td' );
			$items_cell['content']             = ' ' . $items['total'] . ' ';
			$items_cell['attributes']['style'] = 'text-align:center;';

			$row                                        = $this->get_part( 'tr' );
			$row['children']['cache_point']             = $point_cell;
			$row['children']['cached_items']            = $items_cell;
			$table_body['children'][ $cache_point->ID ] = $row;
		}

		$table                        = $this->get_part( 'table' );
		$table['attributes']['class'] = array( 'widefat', 'striped' );
		$table['children']['head']    = $table_head;
		$table['children']['body']    = $table_body;
		$struct['children']['table']  = $table;

		return $struct;
	}
}
