<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\Assets;
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
	 * Holds the assets instance.
	 *
	 * @var Assets
	 */
	protected $assets;

	/**
	 * Holds the script export data.
	 *
	 * @var array
	 */
	protected $export = array();

	/**
	 * Register table structures as components.
	 */
	public function setup() {
		$this->assets = get_plugin_instance()->get_component( 'assets' );
		$this->setting->set_param( 'columns', $this->build_headers() );
		$this->setting->set_param( 'rows', $this->build_rows() );
		$this->setting->set_param( 'file_lists', $this->slugs );
		$this->export = array(
			'update_url' => rest_url( REST_API::BASE . '/disable_cache_items' ),
			'fetch_url'  => rest_url( REST_API::BASE . '/show_cache' ),
			'purge_url'  => rest_url( REST_API::BASE . '/purge_cache' ),
			'purge_all'  => rest_url( REST_API::BASE . '/purge_all' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
		);
		parent::setup();
	}

	/**
	 * Build the header.
	 *
	 * @return \array[][]
	 */
	protected function build_headers() {
		$header_columns = array(
			'title_column'  => array(
				array(
					'slug'        => $this->get_title_slug(),
					'type'        => 'on_off',
					'default'     => 'on',
					'description' => $this->setting->get_param( 'title', '' ),
					'master'      => $this->setting->get_param( 'master', array() ),
				),
			),
			'apply_changes' => array(
				'attributes' => array(
					'style' => 'text-align:right;',
				),
			),
		);

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
			$asset = $this->assets->get_asset_parent( $row['url'] );

			$row_params[ $slug ]             = $this->build_column( $row );
			$row_params[ $slug . '_spacer' ] = array();

			if ( empty( $asset ) ) {
				$row_params[ $slug . '_tree' ] = array(
					'title_column' => array(
						'condition' => array(
							$slug             => true,
							'toggle_' . $slug => true,
						),
						'content'   => __( 'No files cached.', 'cloudinary' ),
					),
				);
				continue;
			}
			$row_params[ $slug . '_tree' ] = array(
				'title_column' => array(
					'attributes' => array(
						'class' => array(
							'closed',
							'tree',
						),
					),
					'condition'  => array(
						$slug => true,
					),
					array(
						'element'    => 'table',

						'attributes' => array(
							'class' => array(
								'striped',
								'widefat',
							),
						),
						array(
							'element' => 'thead',
							array(
								'element' => 'tr',
								array(
									'element' => 'th',
									array(
										'element'      => 'input',
										'tooltip_text' => __( 'Delete selected cached items.', 'cloudinary' ),
										'attributes'   => array(
											'id'           => $slug . '_deleter',
											'type'         => 'checkbox',
											'style'        => 'margin:0 4px 0 0;',
											'data-tooltip' => $slug . '_delete_tip',
										),
									),
									array(
										'element'    => 'span',
										'content'    => __( 'Select cache items to invalidate.', 'cloudinary' ),
										'attributes' => array(
											'id'    => $slug . '_delete_tip',
											'class' => array(
												'hidden',
											),
										),
									),
									array(
										'element'    => 'input',
										'attributes' => array(
											'type'  => 'search',
											'id'    => $slug . '_search',
											'class' => array(
												'cld-search',
											),
										),
									),
									array(
										'element'    => 'button',
										'content'    => __( 'Search', 'cloudinary' ),
										'attributes' => array(
											'type'  => 'button',
											'id'    => $slug . '_reload',
											'class' => array(
												'cld-reload',
												'button',
												'button-small',
											),
										),
									),
								),
								array(
									'element'    => 'th',
									'attributes' => array(
										'style' => 'text-align:right;',
									),
								),
							),
						),
						array(
							'element'    => 'tbody',
							'attributes' => array(
								'data-cache-point' => $asset->post_title,
								'data-browser'     => 'toggle_' . $slug,
								'data-slug'        => $slug,
								'data-apply'       => 'apply_' . $slug,
								'class'            => array(
									'tree-branch',
									'striped',
								),
							),
							'render'     => true,
						),

					),
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

		$column = array(
			'title_column'  => array(
				array(
					'slug'      => $row['slug'],
					'title'     => $this->setting->get_value( $this->get_title_slug() ),
					'type'      => 'on_off',
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
					'condition'        => array(
						$row['slug'] => true,
					),
				),
				array(
					'type'             => 'icon_toggle',
					'slug'             => 'off_' . $row['slug'],
					'description_left' => $row['title'],
					'off'              => ' ',
					'on'               => ' ',
					'default'          => 'off',
					'condition'        => array(
						$row['slug'] => false,
					),
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
			'apply_changes' => array(
				'attributes' => array(
					'style' => 'text-align:right;height:26px;',
				),
				array(
					'slug'       => 'apply_' . $row['slug'],
					'type'       => 'button',
					'value'      => $this->setting->get_param( 'title', '' ),
					'attributes' => array(
						'data-changes' => array(),
					),
					'style'      => array(
						'button-primary',
						'closed',
					),
					'label'      => __( 'Apply changes', 'cloudinary' ),
				),
			),
		);

		return $column;
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
	 * Output the export  script before rendering.
	 */
	protected function pre_render() {
		wp_add_inline_script( 'cloudinary', 'var CLDCACHE = ' . wp_json_encode( $this->export ), 'before' );
		parent::pre_render();
	}
}
