<?php
/**
 * Cloudinary Report to collect data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use WP_Post;
use WP_Screen;

/**
 * Plugin report class.
 */
class Report extends Settings_Component implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the report data.
	 *
	 * @var array
	 */
	protected $report_data = array();

	/**
	 * Holds the option key for tracking reports.
	 */
	const REPORT_KEY = '_cloudinary_report';

	/**
	 * Report constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		add_action( 'cloudinary_settings_save_setting_enable_report', array( $this, 'init_reporting' ), 10, 3 );
		add_filter( 'media_row_actions', array( $this, 'add_inline_action' ), 50, 2 );
		add_filter( 'post_row_actions', array( $this, 'add_inline_action' ), 50, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_inline_action' ), 50, 2 );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'add_to_report' ), 10, 3 );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'add_to_report' ), 10, 3 );
	}

	/**
	 * Handles bulk actions for adding to report.
	 *
	 * @param string $location The location to redirect after.
	 * @param string $action   The action to handle.
	 * @param array  $post_ids Post ID's to action.
	 *
	 * @return string
	 */
	public function add_to_report( $location, $action, $post_ids ) {
		if ( 'cloudinary-report' === $action ) {
			$items = $this->get_report_items();
			foreach ( $post_ids as $id ) {
				if ( ! in_array( $id, $items, true ) ) {
					$items[] = (int) $id;
				}
			}
			update_option( self::REPORT_KEY, $items, false );
		}

		return $location;
	}

	/**
	 * Add an inline action for adding to report.
	 *
	 * @param array   $actions All actions.
	 * @param WP_Post $post    The current post object.
	 *
	 * @return array
	 */
	public function add_inline_action( $actions, $post ) {

		if ( 'on' === $this->settings->get_value( 'enable_report' ) ) {

			$screen = get_current_screen();

			if ( in_array( $post->ID, $this->get_report_items(), true ) ) {
				$actions['cloudinary-report'] = esc_html__( 'Added to the Cloudinary Report.', 'cloudinary' );
			} else {
				if ( $screen && 'upload' === $screen->id ) {

					$args = array(
						'action'   => 'cloudinary-report',
						'media[]'  => $post->ID,
						'_wpnonce' => wp_create_nonce( 'bulk-media' ),
					);

				} else {
					$args = array(
						'action'   => 'cloudinary-report',
						'post[]'   => $post->ID,
						'_wpnonce' => wp_create_nonce( 'bulk-posts' ),
					);
				}
				$action_url                   = add_query_arg( $args, '' );
				$title                        = esc_html__( 'Add to Cloudinary Report', 'cloudinary' );
				$actions['cloudinary-report'] = sprintf(
					'<a href="%1$s" aria-label="%2$s">%2$s</a>',
					$action_url,
					$title
				);
			}
		}

		return $actions;
	}

	/**
	 * Setup the component.
	 */
	public function setup() {
		if ( 'on' === $this->settings->get_value( 'enable_report' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'image_meta_viewer' ) );
		}
	}

	/**
	 * Init the report by clearing and preparing the report options.
	 *
	 * @param mixed $new_value The new value.
	 *
	 * @return mixed
	 */
	public function init_reporting( $new_value ) {
		delete_option( self::REPORT_KEY );

		return $new_value;
	}

	/**
	 * Add Meta view meta box.
	 */
	public function image_meta_viewer() {
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen || 'attachment' !== $screen->id ) {
			return;
		}

		add_meta_box(
			'meta-viewer',
			__( 'Cloudinary Metadata viewer', 'cloudinary' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post The post.
	 */
	public function render( $post ) {
		if ( 'attachment' === $post->post_type ) {
			$meta = wp_get_attachment_metadata( $post->ID );

			$args = array(
				'type'       => 'tag',
				'element'    => 'pre',
				'attributes' => array(
					'style' => 'overflow:auto;',
				),
				'content'    => wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			);
			$this->settings->create_setting( 'meta_viewer', $args )->get_component()->render( true );
		}
	}

	/**
	 * Get the settings structure.
	 *
	 * @return array
	 */
	public function settings() {
		return array(
			'type'       => 'page',
			'menu_title' => __( 'System Report', 'cloudinary' ),
			'tabs'       => array(
				'setup' => array(
					'page_title' => __( 'System Report', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'System information report', 'cloudinary' ),
						array(
							'title' => __( 'Enable report', 'cloudinary' ),
							'type'  => 'on_off',
							'slug'  => 'enable_report',
						),
						array(
							'type'    => 'tag',
							'element' => 'div',
							'content' => $this->get_report_body(),
							'enabled' => function () {
								$enabled = get_plugin_instance()->settings->get_value( 'enable_report' );
								return 'on' !== $enabled;
							},
						),
						array(
							'type'    => 'system',
							'enabled' => function () {
								$enabled = get_plugin_instance()->settings->get_value( 'enable_report' );
								return 'on' === $enabled;
							},
						),
					),
					array(
						'type' => 'submit',
					),
				),
			),
		);
	}

	/**
	 * Get items ID that are part of the report.
	 *
	 * @return array
	 */
	public function get_report_items() {
		static $items;

		if ( is_null( $items ) ) {
			$items = get_option( self::REPORT_KEY, array() );
		}

		return $items;
	}

	/**
	 * Get the message for disabled report.
	 *
	 * @return string
	 */
	protected function get_report_body() {
		ob_start();
		esc_attr_e( 'Enabling system information reporting will allow you to generate and download a realtime snapshot report. The report will be in JSON format and will include information about:', 'cloudinary' );
		?>
<ul>
	<li><?php esc_html_e( 'Current WordPress and Cloudinary configuration.', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'Currently installed plugins.', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'Any themes that are being used.', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'Any specifically selected media. These can be added to the report from the WordPress Media Library.', 'cloudinary' ); ?></li>
	<li><?php esc_html_e( 'Any specifically selected posts or pages. These can be added to the report from the relevant listing pages.', 'cloudinary' ); ?></li>
</ul>
		<?php
		return ob_get_clean();
	}


	/**
	 * Get the report data.
	 *
	 * @return array
	 */
	public function get_report_data() {
		$timestamp = time();

		return array(
			'filename' => "cloudinary-report-{$timestamp}.json",
			'data'     => $this->generate_report(),
		);
	}

	/**
	 * Create a report block setting.
	 *
	 * @param string $slug The slug.
	 * @param array  $data The data.
	 */
	public function add_report_block( $slug, $data ) {
		$this->report_data[ $slug ] = $data;
	}

	/**
	 * Filter the report parts structure.
	 *
	 * @return array
	 */
	protected function generate_report() {
		// Add system.
		$this->system();
		// Add theme.
		$this->theme();
		// Add plugins.
		$this->plugins();
		// Add posts.
		$this->posts();
		// Add config.
		$this->config();

		return $this->report_data;
	}

	/**
	 * Build the system report.
	 */
	protected function system() {
		$system_data = array(
			'home'           => get_bloginfo( 'url' ),
			'wordpress'      => get_bloginfo( 'version' ),
			'php'            => PHP_VERSION,
			'php_extensions' => get_loaded_extensions(),
		);
		$this->add_report_block( 'system_status', $system_data );
	}

	/**
	 * Build the theme report.
	 */
	protected function theme() {
		$active_theme = wp_get_theme();
		$theme_data   = array(
			'name'        => $active_theme->get( 'Name' ),
			'version'     => $active_theme->get( 'Version' ),
			'author'      => $active_theme->get( 'Author' ),
			'author_url'  => $active_theme->get( 'AuthorURI' ),
			'child_theme' => is_child_theme(),
		);
		$this->add_report_block( 'theme_status', $theme_data );
	}

	/**
	 * Build the plugins report.
	 */
	protected function plugins() {

		$plugin_data = array(
			'must_use' => wp_get_mu_plugins(),
			'plugins'  => array(),
		);
		$active      = wp_get_active_and_valid_plugins();
		foreach ( $active as $plugin ) {
			$plugin_data['plugins'][] = get_plugin_data( $plugin );
		}
		$this->add_report_block( 'plugins_report', $plugin_data );
	}

	/**
	 * Build the posts report.
	 */
	protected function posts() {

		$report_items = get_option( self::REPORT_KEY, array() );
		$report_items = array_unique( $report_items );
		if ( ! empty( $report_items ) ) {
			$post_data  = array();
			$media_data = array();
			foreach ( $report_items as $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( 'attachment' === $post_type ) {
					$data                   = wp_get_attachment_metadata( $post_id );
					$data['all_meta']       = get_post_meta( $post_id );
					$data['attachment']     = get_post( $post_id );
					$media_data[ $post_id ] = $data;
				} else {
					$data                  = get_post( $post_id, ARRAY_A );
					$data['post_meta']     = get_post_meta( $post_id );
					$post_data[ $post_id ] = $data;
				}
			}
			if ( ! empty( $media_data ) ) {
				$this->add_report_block( 'media_report', $media_data );
			}
			if ( ! empty( $post_data ) ) {
				$this->add_report_block( 'post_report', $post_data );
			}
		}
	}

	/**
	 * Build the config report.
	 */
	protected function config() {
		$config = $this->plugin->settings->get_root_setting()->get_value();
		unset( $config['connect'] );
		// The Gallery setting might not be set, so we need ensure it exists before using it.
		if ( $this->plugin->get_component( 'media' )->gallery ) {
			$config['gallery'] = $this->plugin->get_component( 'media' )->gallery->get_config();
		}
		$this->add_report_block( 'config_report', $config );
	}
}
