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
	 * Holds the key to generate the report and download.
	 */
	const REPORT_DOWNLOAD_KEY = 'generate-report';

	/**
	 * Holds the report page/section slug.
	 */
	const REPORT_SLUG = 'system-report';

	/**
	 * Report constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );
	}

	/**
	 * Init component on connection.
	 */
	public function init() {
		add_action( 'cloudinary_settings_save_setting_reporting.enable_report', array( $this, 'init_reporting' ), 10, 3 );
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
			$this->maybe_generate_report();
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
		delete_option( Sync::META_KEYS['debug'] );

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
			// Add scripts.
			$this->enqueue_scripts();
			?>
			<div id="meta-data"></div>
			<?php
		}
	}

	/**
	 * Get the attachment data.
	 *
	 * @param null|int $post_id The post ID.
	 *
	 * @return array
	 */
	public function get_attachment_data( $post_id = null ) {
		global $wpdb;

		$post       = get_post( $post_id );
		$media      = $this->plugin->get_component( 'media' );
		$meta       = get_post_meta( $post->ID );
		$cld        = (array) get_post_meta( $post->ID, Sync::META_KEYS['cloudinary'], true );
		$logs       = (array) $media->get_process_logs( $post->ID );
		$attachment = (array) wp_get_attachment_metadata( $post->ID );
		$guid       = get_the_guid( $post->ID );

		unset( $meta[ Sync::META_KEYS['cloudinary'] ], $meta[ Sync::META_KEYS['process_log'] ], $meta['_wp_attachment_metadata'] );
		array_walk(
			$meta,
			static function ( &$row ) {
				$row = reset( $row );
			}
		);
		$meta = array_map( 'maybe_unserialize', $meta );

		if (
			empty( $meta[ Sync::META_KEYS['local_size'] ] )
			|| empty( $meta[ Sync::META_KEYS['remote_size'] ] )
		) {
			$this->plugin->get_component( 'storage' )->size_sync( $post->ID );
		}

		$wpdb->cld_table = Utils::get_relationship_table();
		$media_context   = Utils::get_media_context( $post->ID );
		$prepare         = $wpdb->prepare(
			"SELECT * FROM {$wpdb->cld_table} WHERE post_id = %d AND media_context = %s;",
			$post->ID,
			$media_context
		);
		$relationship    = $wpdb->get_row( $prepare ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		ksort( $attachment );
		ksort( $meta );

		return array_merge(
			array( Sync::META_KEYS['cloudinary'] => $cld ),
			array( 'relationship' => $relationship ),
			array( Sync::META_KEYS['process_log_legacy'] => $logs ),
			array( 'attachment_metadata' => $attachment ),
			array( 'metadata' => $meta ),
			array( 'guid' => $guid )
		);
	}

	/**
	 * Enabled method check.
	 *
	 * @return bool
	 */
	public function enabled() {
		return 'on' === $this->settings->get_value( 'enable_report' );
	}

	/**
	 * Check if component is disabled.
	 *
	 * @return bool
	 */
	public function disabled() {
		return ! $this->enabled();
	}

	/**
	 * Add page section to pages structure.
	 *
	 * @param array $pages The current pages structure.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {
		$pages['reporting'] = array(
			'page_title'          => __( 'System Report', 'cloudinary' ),
			'section'             => self::REPORT_SLUG,
			'slug'                => 'reporting',
			'priority'            => 1,
			'requires_connection' => true,
			'sidebar'             => true,
			'settings'            => array(
				array(
					'type'        => 'panel',
					'title'       => __( 'System information report', 'cloudinary' ),
					'option_name' => 'setup',
					array(
						'description' => __( 'Enable report', 'cloudinary' ),
						'type'        => 'on_off',
						'slug'        => 'enable_report',
					),
					array(
						'type'    => 'tag',
						'element' => 'div',
						'content' => $this->get_report_body(),
						'enabled' => array( $this, 'disabled' ),
					),
					array(
						'type'    => 'system',
						'enabled' => array( $this, 'enabled' ),
					),
				),
			),
		);

		return $pages;
	}

	/**
	 * Upgrade method for version changes.
	 *
	 * @param string $previous_version The previous version number.
	 * @param string $new_version      The New version number.
	 */
	public function upgrade_settings( $previous_version, $new_version ) {
		if ( $previous_version < '3.0.0' ) {
			$previous = $this->settings->get_value( 'setup.enable_report' );
			$current  = $this->settings->get_value( 'enable_report' );
			if ( $current !== $previous ) {
				$this->settings->set_pending( 'enable_report', $previous );
				$this->settings->save();
			}
		}
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
		// Add debug log.
		$this->degbug_log();

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
	 * Convert a file path to a slug (folder/file.extension).
	 *
	 * @param string $path The file path.
	 *
	 * @return string
	 */
	protected function file_path_to_slug( $path ) {
		return wp_basename( dirname( $path ) ) . '/' . wp_basename( $path );
	}

	/**
	 * Build the plugins report.
	 */
	protected function plugins() {

		$plugin_data = array(
			'must_use' => array_map( array( $this, 'file_path_to_slug' ), wp_get_mu_plugins() ),
			'plugins'  => array(),
		);
		$active      = wp_get_active_and_valid_plugins();
		foreach ( $active as $plugin ) {
			$slug                                    = $this->file_path_to_slug( $plugin );
			$plugin_data['plugins'][ $slug ]         = get_plugin_data( $plugin, false, false );
			$plugin_data['plugins'][ $slug ]['slug'] = $slug;
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
			$media      = $this->plugin->get_component( 'media' );
			foreach ( $report_items as $post_id ) {
				$post_type = get_post_type( $post_id );
				if ( 'attachment' === $post_type ) {
					$media_data[ $post_id ] = $this->get_attachment_data( $post_id );
				} else {
					$data      = get_post( $post_id, ARRAY_A );
					$post_meta = get_post_meta( $post_id );

					foreach ( $post_meta as $key => $meta ) {
						$data['post_meta'][ $key ] = array_map( 'maybe_unserialize', $meta );
					}

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
		unset( $config['cloudinary']['connect'], $config['connect'], $config['connection'] );
		// The Gallery setting might not be set, so we need ensure it exists before using it.
		if ( $this->plugin->get_component( 'media' )->gallery ) {
			$config['gallery'] = $this->plugin->get_component( 'media' )->gallery->get_config();
		}
		$this->add_report_block( 'config_report', $config );
	}

	/**
	 * Add debug log report.
	 */
	protected function degbug_log() {
		$this->add_report_block( 'debug_log', Utils::get_debug_messages() );
	}

	/**
	 * Maybe generate the report.
	 */
	public function maybe_generate_report() {
		$page     = Utils::get_sanitized_text( 'page' );
		$section  = Utils::get_sanitized_text( 'section' );
		$download = filter_input( INPUT_GET, self::REPORT_DOWNLOAD_KEY, FILTER_VALIDATE_BOOLEAN );
		if ( $download && 'cloudinary_help' === $page && 'system-report' === $section && Utils::user_can( 'system_report' ) ) {
			$report = $this->get_report_data();
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( "Content-Disposition: attachment; filename={$report['filename']}" );
			header( 'Content-Transfer-Encoding: text' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			echo wp_json_encode( $report['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'cloudinary-syntax-highlight', $plugin->dir_url . 'js/syntax-highlight.js', array(), $plugin->version, true );
		wp_add_inline_script( 'cloudinary-syntax-highlight', 'var CLD_METADATA = ' . wp_json_encode( $this->get_attachment_data() ) );
		wp_enqueue_style( 'cloudinary-syntax-highlight', $this->plugin->dir_url . 'css/syntax-highlight.css', array(), $this->plugin->version );
	}
}
