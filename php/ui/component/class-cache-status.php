<?php
/**
 * Cache Status UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Cache;
use Cloudinary\Cache\Cache_Point;

/**
 * Cache Status Component to render plan status.
 *
 * @package Cloudinary\UI
 */
class Cache_Status extends media_status {

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

		$cache        = $this->plugin->get_component( 'cache' );
		$this->cache  = $cache->cache_point;
		$cache_points = $this->cache->get_active_cache_points();

		$title            = $this->get_part( 'h3' );
		$title['content'] = __( 'Assets cached to Cloudinary', 'cloudinary' );

		$struct['element'] = 'div';
		$table             = array(
			'type'    => 'table',
			'columns' => array(
				'cache_point'  => __( 'Cache Point', 'cloudinary' ),
				'cached_items' => array(
					'content'    => __( 'Cached items', 'cloudinary' ),
					'attributes' => array(
						'style' => 'text-align:center;',
					),
				),
			),
			'rows'    => array(),
		);
		foreach ( $cache_points as $cache_point ) {
			$items                             = $this->cache->get_cache_point_cache( $cache_point->ID );
			$table['rows'][ $cache_point->ID ] = array(
				'cache_point'  => array(
					'content' => basename( untrailingslashit( $cache_point->post_title ) ),
				),
				'cached_items' => array(
					'content'    => ' ' . $items['total'] . ' ',
					'attributes' => array(
						'style' => 'text-align:center;',
					),
				),
			);
		}
		$table_obj         = $this->setting->create_setting( 'cached_status', $table, $this->setting );
		$struct['content'] = $table_obj->render_component();

		return $struct;
	}
}
