<?php
/**
 * Table UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;

/**
 * Class Table Component
 *
 * @package Cloudinary\UI
 */
class Table extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'settings';

	/**
	 * Holds the registered columns.
	 *
	 * @var array
	 */
	protected $columns = array();

	/**
	 * Register table structures as components.
	 */
	public function setup() {
		$table = array(
			'element'    => 'table',
			'attributes' => array(
				'class' => array(
					'widefat',
					'striped',
					'cld-table',
				),
			),
			$this->build_head(),
			$this->build_body(),
		);
		$this->setting->get_setting( $this->setting->get_slug() . '_table' )->setup_setting( $table );
	}

	/**
	 * Build html params for the thead.
	 *
	 * @return array
	 */
	protected function build_head() {
		$columns = $this->setting->get_param( 'columns', array() );

		$header = array(
			'element' => 'thead',
			array(
				'element' => 'tr',
				$this->head_columns( $columns ),
			),

		);

		return $header;
	}

	/**
	 * Build html params for the header columns.
	 *
	 * @param array $columns The columns to build.
	 *
	 * @return array
	 */
	protected function head_columns( $columns ) {
		$header_columns = array();
		foreach ( $columns as $slug => $column ) {
			$this->columns[] = $slug;
			$new_column      = array(
				'element'    => 'th',
				'attributes' => array(
					'class' => array(
						'cld-table-th',
					),
				),
			);
			if ( is_array( $column ) ) {
				$new_column = array_merge( $new_column, $column );
			} else {
				$new_column['content'] = $column;
			}

			$header_columns[] = $new_column;
		}

		return $header_columns;
	}

	/**
	 * Build the html params for the tbody.
	 *
	 * @return array
	 */
	protected function build_body() {
		$body = array(
			'element' => 'tbody',
		);
		$rows = $this->setting->get_param( 'rows', array() );
		foreach ( $rows as $slug => $row ) {
			$body[] = $this->body_row( $slug, $row );
		}

		return $body;
	}

	/**
	 * Build the html params for a body row.
	 *
	 * @param string $slug The slug for the body row.
	 * @param array  $row  The row data.
	 *
	 * @return array
	 */
	protected function body_row( $slug, $row ) {
		$next_colspan = 1;

		$columns = array();
		foreach ( $this->columns as $column ) {

			$col_slug                     = $slug . '_' . $column;
			$columns[ $col_slug ]['slug'] = $col_slug;
			if ( isset( $row[ $column ] ) ) {
				$child_row                          = $this->get_part( 'td' );
				$child_row                          = array_merge( $child_row, $row[ $column ] );
				$child_row['attributes']['colspan'] = $next_colspan;
				if ( 1 < $next_colspan ) {
					$next_colspan = 1;
				}
				$columns[ $col_slug ] = $child_row;
			} elseif ( isset( $previous ) ) {
				if ( ! isset( $columns[ $previous ]['attributes'] ) ) {
					$columns[ $previous ]['attributes'] = array(
						'colspan' => 0,
					);
				}
				$columns[ $previous ]['attributes']['colspan'] ++;
				continue;
			} else {
				$next_colspan ++;
			}

			$previous = $col_slug;
		}

		$params            = array_values( $columns );
		$params['element'] = 'tr';

		return $params;
	}

}
