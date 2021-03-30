<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use Cloudinary\Utils;

/**
 * HTML Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Folder_Table extends Table {

	/**
	 * Holds the slugs for the file lists.
	 *
	 * @var array
	 */
	protected $slugs = array();

	/**
	 * Register table structures as components.
	 */
	protected function register_components() {
		$this->setting->set_param( 'columns', $this->build_headers() );
		$this->setting->set_param( 'rows', $this->build_rows() );
		$this->setting->set_param( 'file_lists', $this->slugs );
		parent::register_components();
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
	 * Build the rows.
	 *
	 * @return array
	 */
	protected function build_rows() {
		$roots      = $this->setting->get_param( 'root_paths', array() );
		$row_params = array();
		foreach ( $roots as $root => $path ) {
			$files                           = $this->get_files( $path );
			$slug                            = sanitize_file_name( $root );
			$row_params[ $slug ]             = $this->build_column( $root, $slug, $path );
			$row_params[ $slug . '_spacer' ] = array();
			$content                         = array(
				'content' => __( 'No assets found.', 'cloudinary' ),
			);
			if ( ! empty( $files ) ) {
				$content       = array(
					'slug'       => $slug . '_files',
					'type'       => 'file_folder',
					'base_path'  => $path,
					'paths'      => $files,
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
	 * @param string $title The title.
	 * @param string $slug  The slug.
	 * @param string $path  The path.
	 *
	 * @return array
	 */
	protected function build_column( $title, $slug, $path ) {
		$active = empty( $this->get_files( $path ) );
		$column = array(
			'title_column' => array(
				array(
					'slug'     => $slug,
					'type'     => 'on_off',
					'disabled' => $active,
					'master'   => array(
						$this->get_title_slug(),
					),
				),
				array(
					'type'             => 'icon_toggle',
					'slug'             => 'toggle_' . $slug,
					'description_left' => $title,
					'off'              => 'dashicons-arrow-down',
					'on'               => 'dashicons-arrow-up',
				),
				array(
					'type'       => 'tag',
					'element'    => 'span',
					'content'    => '',
					'render'     => true,
					'attributes' => array(
						'id'    => 'name_' . $slug . '_size_wrapper',
						'class' => array(
							'file-size',
							'small',
						),
					),
				),
			),
		);

		$column_filters = $this->build_column_filters( $slug, $path );

		$column = array_merge( $column, $column_filters );

		return $column;
	}

	/**
	 * Build the filters columns.
	 *
	 * @param string $slug The slug.
	 * @param string $path The path.
	 *
	 * @return array
	 */
	protected function build_column_filters( $slug, $path ) {
		$filters        = $this->setting->get_param( 'filters', array() );
		$column_filters = array();
		foreach ( $filters as $filter => $types ) {
			$filter_slug = $this->get_filter_slug( $filter );
			if ( ! $this->path_has_filter_types( $path, $filter ) ) {
				$column_filters[ $filter_slug ] = array();
				continue;
			}
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
						'id'    => $filter_slug . '_' . $slug . '_size_wrapper',
						'class' => array(
							'file-size',
							'small',
						),
					),
				),
				array(
					'type'   => 'on_off',
					'slug'   => $filter_slug . '_' . $slug,
					'master' => array(
						$filter_slug,
						$slug,
					),
				),
			);
		}

		return $column_filters;
	}

	/**
	 * Get the files for this path.
	 *
	 * @param string $path The path.
	 *
	 * @return array|mixed
	 */
	protected function get_files( $path ) {
		$files = get_transient( $this->get_files_transient_key( $path ) );
		if ( empty( $files ) ) {
			$types = $this->get_filter_types();
			$files = Utils::get_files( $path, array_keys( $types ), true );
			set_transient( $this->get_files_slug(), $files, 60 );
		}

		return $files;
	}

	/**
	 * Checks if the path has any file type that match the filter.
	 *
	 * @param string $path The path.
	 * @param string $type The type to check.
	 *
	 * @return bool
	 */
	protected function path_has_filter_types( $path, $type ) {

		static $filtered_types = array();

		// Cache the values.
		$type = $this->get_filter_slug( $type );
		if ( ! isset( $filtered_types[ $path ] ) ) {

			$all_types               = $this->get_filter_types();
			$files                   = $this->get_files( $path );
			$files_types             = array_map(
				function ( $file ) use ( $all_types ) {
					$ext = pathinfo( $file, PATHINFO_EXTENSION );

					return isset( $all_types[ $ext ] ) ? $all_types[ $ext ] : null;
				},
				$files
			);
			$files_types             = array_filter( $files_types );
			$files_types             = array_unique( $files_types );
			$filtered_types[ $path ] = array_fill_keys( $files_types, true );
		}

		return isset( $filtered_types[ $path ][ $type ] );
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

	/**
	 * Get the transient key for storing files.
	 *
	 * @param string $path The path to get the key for.
	 *
	 * @return string
	 */
	protected function get_files_transient_key( $path ) {
		$key = wp_json_encode( $this->get_filter_types( $path ) );

		return md5( $key . '_' . $path );
	}
}
