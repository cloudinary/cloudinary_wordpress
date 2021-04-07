<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\Cache\Cache_Point;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 * HTML Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Folder_Table extends Table {

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	public $capture = true;

	/**
	 * Holds the slugs for the file lists.
	 *
	 * @var array
	 */
	protected $slugs = array();

	/**
	 * Holds the cache point object.
	 *
	 * @var Cache_Point
	 */
	protected $cache_point;

	/**
	 * Register table structures as components.
	 */
	public function setup() {
		$this->cache_point = get_plugin_instance()->get_component( 'cache' )->cache_point;
		$this->setting->set_param( 'columns', $this->build_headers() );
		$this->setting->set_param( 'rows', $this->build_rows() );
		$this->setting->set_param( 'file_lists', $this->slugs );
		parent::setup();
	}

	/**
	 * Build the header.
	 *
	 * @return \array[][]
	 */
	protected function build_headers() {
		$header_columns = array(
			'title_column' => array(
				array(
					'slug'        => $this->get_title_slug(),
					'type'        => 'on_off',
					'default'     => 'on',
					'description' => $this->setting->get_param( 'title', '' ),
				),
			),
		);
		$filters        = $this->setting->get_param( 'filters', array() );
		foreach ( $filters as $filter => $types ) {
			$slug                    = $this->get_filter_slug( $filter );
			$header_columns[ $slug ] = array(
				'attributes' => array(
					'style' => 'text-align:right;width:20%;',
				),
				array(
					'slug'             => $slug,
					'type'             => 'on_off',
					'description_left' => $filter,
					'master'           => array(
						$this->get_title_slug(),
					),
				),
			);
		}

		return $header_columns;
	}

	/**
	 * Get the rows and build required params.
	 *
	 * @return  array
	 */
	protected function get_rows() {
		$roots       = $this->setting->get_param( 'root_paths', array() );
		$row_default = array(
			'title'    => null,
			'src_path' => null,
			'url'      => null,
		);
		$rows        = array();
		foreach ( $roots as $slug => $row ) {
			$row             = wp_parse_args( $row, $row_default );
			$row['slug']     = $slug;
			$row['src_path'] = str_replace( ABSPATH, '', $row['src_path'] );
			// Add to list.
			$rows[ $slug ] = $row;
		}

		return $rows;
	}

	/**
	 * Build the rows.
	 *
	 * @return array
	 */
	protected function build_rows() {

		$row_params = array();
		$rows       = $this->get_rows();
		foreach ( $rows as $slug => $row ) {
			$url                             = rest_url( REST_API::BASE . '/browse' );
			$row_params[ $slug ]             = $this->build_column( $row );
			$row_params[ $slug . '_spacer' ] = array();
			$content                         = array(
				'content' => __( 'No assets found.', 'cloudinary' ),
			);
			$row_params[ $slug . '_tree' ]   = array(
				'title_column' => array(
					'attributes' => array(
						'class' => array(
							'closed',
							'tree',
						),
					),
					array(
						'element'    => 'ul',
						'attributes' => array(
							'data-url'     => $url,
							'data-path'    => $row['src_path'],
							'data-browser' => 'toggle_' . $slug,
							'class'        => array(
								'tree-branch',
							),
						),
					),
				),
			);
		}

		return $row_params;
	}

	/**
	 * Build the rows.
	 *
	 * @return array
	 */
	protected function old_build_rows() {

		$row_params = array();
		$rows       = $this->get_rows();
		foreach ( $rows as $slug => $row ) {
			$row_params[ $slug ]             = $this->build_column( $row );
			$row_params[ $slug . '_spacer' ] = array();
			$content                         = array(
				'content' => __( 'No assets found.', 'cloudinary' ),
			);
			if ( ! empty( $row['files'] ) ) {
				$content       = array(
					'slug'       => $slug . '_files',
					'type'       => 'file_folder',
					'base_path'  => $row['path'],
					'action'     => 'selection',
					'file_types' => $this->get_filter_types( $slug ),
				);
				$this->slugs[] = $slug . '_files';
			}
			$row_params[ $slug . '_tree' ] = array(
				'title_column' => array(
					'condition' => array(
						'toggle_' . $slug => true,
					),
					$content,
				),
			);
		}

		return $row_params;
	}

	/**
	 * Build a single column.
	 *
	 * @param array $row The row array to column for.
	 *
	 * @return array
	 */
	protected function build_column( $row ) {

		$disabled = empty( $row['files'] );
		$column   = array(
			'title_column' => array(
				array(
					'slug'      => $row['slug'],
					'title'     => $this->setting->get_value( $this->get_title_slug() ),
					'type'      => 'on_off',
					'disabled'  => $disabled,
					'default'   => 'on',
					'base_path' => $row['src_path'],
					'action'    => 'all_selector',
					'master'    => array(
						$this->get_title_slug(),
					),
				),
				array(
					'type'             => 'icon_toggle',
					'slug'             => 'toggle_' . $row['slug'],
					'description_left' => $row['title'],
					'off'              => 'dashicons-arrow-down',
					'on'               => 'dashicons-arrow-up',
					'default'          => 'off',
				),
				array(
					'type'       => 'tag',
					'element'    => 'span',
					'content'    => '',
					'render'     => true,
					'attributes' => array(
						'id'    => 'name_' . $row['slug'] . '_size_wrapper',
						'class' => array(
							'file-size',
							'small',
						),
					),
				),
			),
		);

		return $column;
	}

	/**
	 * Build the filters columns.
	 *
	 * @param array $row The row params.
	 *
	 * @return array
	 */
	protected function build_column_filters( $row ) {
		$filters        = $this->setting->get_param( 'filters', array() );
		$column_filters = array();
		foreach ( $filters as $filter => $types ) {
			if ( ! $row['filters'][ $filter ] ) {
				continue;
			}
			$filter_slug                    = $this->get_filter_slug( $filter );
			$column_filters[ $filter_slug ] = array(
				'attributes' => array(
					'style' => 'text-align:right;width:20%;',
				),
				array(
					'type'       => 'tag',
					'element'    => 'span',
					'content'    => '',
					'render'     => true,
					'attributes' => array(
						'id'    => $filter_slug . '_' . $row['slug'] . '_size_wrapper',
						'class' => array(
							'file-size',
							'small',
						),
					),
				),
				array(
					'type'   => 'on_off',
					'slug'   => $filter_slug . '_' . $row['slug'],
					'master' => array(
						$filter_slug,
						$row['slug'],
					),
				),
			);
		}

		return $column_filters;
	}

	/**
	 * Get the files for this path.
	 *
	 * @param string $path    The path.
	 * @param string $version The path version.
	 * @param array  $types   The types to filter by.
	 *
	 * @return array|mixed
	 */
	protected function get_files( $path, $version, $types = array() ) {
		$transient_key = md5( $path );
		$paths         = get_transient( $transient_key );
		if ( empty( $paths ) || $version !== $paths['version'] || $types !== $paths['types'] ) {
			$paths                      = array(
				'version'           => $version,
				'types'             => $types,
				'unique_extensions' => array(),
			);
			$paths['files']             = Utils::get_files( $path, array_keys( $types ), true );
			$paths['unique_extensions'] = Utils::get_unique_extensions( $paths['files'] );
			set_transient( $transient_key, $paths );
		}

		return $paths;
	}

	/**
	 * Gets the supported filters from a list of extensions.
	 *
	 * @param array $extensions The list of extensions to check with.
	 *
	 * @return array
	 */
	protected function get_supported_filters( $extensions ) {
		$filters   = $this->setting->get_param( 'filters', array() );
		$supported = array();
		foreach ( $filters as $filter => $types ) {
			$match                = array_intersect( $extensions, $types );
			$supported[ $filter ] = ! empty( $match );
		}

		return $supported;
	}

	/**
	 * Get the filter types.
	 *
	 * @param null|string $slug The optional slug.
	 *
	 * @return array
	 */
	protected function get_filter_types( $slug = null ) {
		$filters      = $this->setting->get_param( 'filters', array() );
		$filter_types = array();
		foreach ( $filters as $filter => $types ) {
			$filter_slug = $this->get_filter_slug( $filter );
			foreach ( $types as $type ) {
				$filter_types[ $type ] = $filter_slug;
				if ( $slug ) {
					$filter_types[ $type ] .= '_' . $slug;
				}
			}
		}

		return $filter_types;
	}

	/**
	 * Get the table slug.
	 *
	 * @return string
	 */
	protected function get_table_slug() {
		return $this->setting->get_slug() . '_table';
	}

	/**
	 * Get the title slug.
	 *
	 * @return string
	 */
	protected function get_title_slug() {
		return $this->setting->get_slug() . '_title';
	}

	/**
	 * Get the filter slug.
	 *
	 * @param string $filter The filter to get slug for.
	 *
	 * @return string
	 */
	protected function get_filter_slug( $filter ) {
		$slug = sanitize_key( $filter );

		return $slug . '_filter';
	}
}
