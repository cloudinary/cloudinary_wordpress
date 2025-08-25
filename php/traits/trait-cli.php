<?php
/**
 * Cloudinary CLI Sync Traits.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Traits;

use Cloudinary\Plugin;
use Cloudinary\Sync;

/**
 * CLI class.
 *
 * @since   2.5.1
 */
trait CLI_Trait {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   2.5.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the base query args.
	 *
	 * @since   2.5.1
	 *
	 * @var array
	 */
	protected $base_query_args = array(
		'post_type'              => 'attachment',
		'post_status'            => 'inherit',
		'fields'                 => 'ids',
		'posts_per_page'         => 100,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'paged'                  => 1,
	);

	/**
	 * Is sync complete.
	 *
	 * @since 3.0.3
	 *
	 * @var bool
	 */
	protected $is_complete = false;

	/**
	 * Verbose mode flog.
	 *
	 * @since 3.0.3
	 *
	 * @var bool
	 */
	protected $is_verbose = false;

	/**
	 * Export debug output flag.
	 *
	 * @since 3.0.3
	 *
	 * @var bool
	 */
	protected $is_export = false;

	/**
	 * Is debug mode on.
	 * Either verbose or export flags.
	 *
	 * @since 3.0.3
	 *
	 * @var bool
	 */
	protected $is_debug_enabled = false;

	/**
	 * Clean up flag.
	 *
	 * @since 3.2.0
	 *
	 * @var bool
	 */
	protected $clean_up = false;

	/**
	 * CLI Cloudinary Setup.
	 *
	 * @since   2.5.1
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function setup_cloudinary( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Output the Intro.
	 *
	 * @since   2.5.1
	 * @link    http://patorjk.com/software/taag/#p=display&c=echo&f=Calvin%20S&t=Cloudinary%20CLI
	 */
	protected function do_intro() {
		static $intro;
		if ( ! $intro ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( '╔═╗┬  ┌─┐┬ ┬┌┬┐┬┌┐┌┌─┐┬─┐┬ ┬  ╔═╗╦  ╦' );
			\WP_CLI::log( '║  │  │ ││ │ ││││││├─┤├┬┘└┬┘  ║  ║  ║' );
			\WP_CLI::log( '╚═╝┴─┘└─┘└─┘─┴┘┴┘└┘┴ ┴┴└─ ┴   ╚═╝╩═╝╩' );
			$intro = true;
		}
	}

	/**
	 * Syncs assets with Cloudinary.
	 * ## EXAMPLES
	 *
	 *     wp cloudinary sync
	 *
	 * ## OPTIONS
	 * [--verbose]
	 * : Whether to show extra information on unsynced and errored attachments.
	 *
	 * [--export]
	 * : Whether to export CSV files with unsynced and errored attachments.
	 *
	 * [--clean-up]
	 * : Whether to clean up all the error flags.
	 *
	 * @when    after_wp_load
	 * @since   2.5.1
	 *
	 * @param array $args       Ignored.
	 * @param array $assoc_args Associative array of associative arguments.
	 *
	 * @return void
	 */
	public function sync( $args, $assoc_args ) {

		// Check if analyzed first.
		if ( empty( get_option( '_cld_cli_analyzed' ) ) ) {
			$this->analyze();
		}

		// Warmup flags after the first analyze..
		$this->is_verbose       = ! empty( $assoc_args['verbose'] );
		$this->is_export        = ! empty( $assoc_args['export'] );
		$this->is_debug_enabled = empty( $args ) && ( $this->is_verbose || $this->is_export );
		$this->clean_up         = ! empty( $assoc_args['clean-up'] );

		// Initial Query.
		$query_args = $this->base_query_args;
		// phpcs:ignore WordPress.DB.SlowDBQuery
		$query_args['meta_query'] = array(
			'AND',
			array(
				'key'     => '_cld_unsynced',
				'compare' => 'EXISTS',
			),
		);

		// Get assets that need to be synced.
		$query = new \WP_Query( $query_args );
		$this->do_process( $query, 'sync', false );
		if ( ! $query->have_posts() ) {
			\WP_CLI::log( \WP_CLI::colorize( '%gAll assets synced.%n' ) );
			delete_option( '_cld_cli_analyzed' );
		}
	}

	/**
	 * Analyze assets with Cloudinary.
	 *
	 * ## OPTIONS
	 * [--verbose]
	 * : Whether to show extra information on unsynced and errored attachments.
	 *
	 * [--export]
	 * : Whether to export CSV files with unsynced and errored attachments.
	 *
	 * [--clean-up]
	 * : Whether to clean up all the error flags.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cloudinary analyze
	 *
	 * @when    after_wp_load
	 * @since   2.5.1
	 *
	 * @param array $args       Ignored.
	 * @param array $assoc_args Associative array of associative arguments.
	 *
	 * @return void
	 */
	public function analyze( $args = null, $assoc_args = null ) {

		static $did_cleanup = false;

		// Warmup flags if called as command.
		if ( ! is_null( $args ) && ! is_null( $assoc_args ) ) {
			$this->is_verbose       = ! empty( $assoc_args['verbose'] );
			$this->is_export        = ! empty( $assoc_args['export'] );
			$this->is_debug_enabled = empty( $args ) && ( $this->is_verbose || $this->is_export );
			$this->clean_up         = ! empty( $assoc_args['clean-up'] );
		}

		// Initial query.
		$query_args = $this->base_query_args;
		$query      = new \WP_Query( $query_args );

		// Kill all _cld_ related meta.
		delete_post_meta_by_key( '_cld_unsynced' );
		delete_option( '_cld_cli_analyzed' );

		// Do process.
		$this->do_process( $query, 'analyze' );

		// Clean up all the error flags.
		if ( $this->clean_up && ! $did_cleanup ) {
			$did_cleanup = true;

			\WP_CLI::log( \WP_CLI::colorize( '%gCleaning up the error flags.%n' ) );
			delete_post_meta_by_key( Sync::META_KEYS['sync_error'] );
		}
	}

	/**
	 * Do a process on the query.
	 *
	 * @since   2.5.1
	 *
	 * @param \WP_Query $query    The initial query object.
	 * @param string    $process  The process to do.
	 * @param bool      $paginate Flag to paginate.
	 */
	protected function do_process( &$query, $process, $paginate = true ) {
		$this->do_intro();

		// Bail early.
		if ( ! method_exists( $this, "process_{$process}" ) ) {
			\WP_CLI::log( \WP_CLI::colorize( "%Invalid Process: {$process}.%n" ) );

			return;
		}

		// Already complete the cycle. If there are non synced items, chances are that we won't be able to sync them.
		if ( $this->is_complete ) {
			return;
		}

		if ( method_exists( $this, $process ) ) {
			// Setup process.
			$total   = $query->found_posts;
			$process = "process_{$process}";
			do {
				$posts = $query->get_posts();
				$this->{$process}( $posts, $total );

				// Free up memory.
				$this->stop_the_insanity();

				// Sleep for a catchup.
				sleep( 1 );

				// Paginate.
				$query_args = $query->query_vars;
				if ( true === $paginate ) {
					++$query_args['paged'];
				}
				$query = new \WP_Query( $query_args );
			} while ( $query->have_posts() );
		}
		\WP_CLI::line( '' );
	}

	/**
	 * Sync Assets.
	 *
	 * @param array $posts Array of Post IDs to process.
	 * @param int   $total Count of total posts to process.
	 */
	protected function process_sync( $posts, $total ) {
		static $bar, $done;
		if ( ! $bar && ! empty( $posts ) ) {
			\WP_CLI::log( \WP_CLI::colorize( '%gSyncing assets%n' ) );
			$bar  = \WP_CLI\Utils\make_progress_bar( 'Syncing ' . $total . ' assets', $total, 10 );
			$done = 0;
		}
		foreach ( $posts as $index => $asset ) {
			++$done; // Set $done early to not show 0 of x.
			$file     = get_attached_file( $asset );
			$filename = self::pad_name( wp_basename( $file ), 20, ' ', '*' );
			$bar->tick( 1, 'Syncing (' . ( $done ) . ' of ' . $total . ') : ' . $filename );
			if (
				! $this->plugin->get_component( 'sync' )->is_synced( $asset, true )
				&& $this->plugin->get_component( 'media' )->is_uploadable_media( $asset )
				&& $this->plugin->get_component( 'sync' )->is_syncable( $asset )
			) {
				$this->plugin->get_component( 'sync' )->managers['push']->process_assets( $asset );
			}
			delete_post_meta( $asset, '_cld_unsynced', true );
		}
		// Done message - reanalyze.
		if ( $done === $total ) {
			$bar->tick( 0, 'Sync Completed.' );
			$bar->finish();
			$bar = null;
			\WP_CLI::line( '' );
			$this->analyze();
			$this->is_complete = true;
			delete_option( '_cld_cli_analyzed' );
		}
	}

	/**
	 * Analyze and mark assets that need to be synced.
	 *
	 * @since   2.5.1
	 *
	 * @param array $posts Array of Post IDs to process.
	 * @param int   $total Count of total posts to process.
	 */
	protected function process_analyze( $posts, $total ) {
		static $bar, $done, $info;

		if ( ! $bar ) {
			\WP_CLI::log( \WP_CLI::colorize( '%gAnalyzing ' . $total . ' assets:%n' ) );
			$bar  = \WP_CLI\Utils\make_progress_bar( '', $total, 10 );
			$done = 0;
			$info = array(
				'_cld_unsupported' => 0,
				'_cld_synced'      => 0,
				'_cld_unsynced'    => 0,
			);
		}

		$unsynced_attachments = array();
		$errored_attachments  = array();

		foreach ( $posts as $asset ) {
			++$done;
			$key = '_cld_unsupported';
			if (
				$this->plugin->get_component( 'media' )->is_uploadable_media( $asset )
				&& $this->plugin->get_component( 'sync' )->is_syncable( $asset )
			) {
				// Add a key.
				$key = '_cld_synced';
				if ( ! $this->plugin->get_component( 'sync' )->is_synced( $asset, true ) ) {
					$key = '_cld_unsynced';
					add_post_meta( $asset, $key, true, true );
					if ( $this->is_debug_enabled ) {
						$unsynced_attachments[ $asset ] = array(
							'attachment_id' => $asset,
							'edit_url'      => $this->get_edit_link( $asset ),
						);
					}
				}

				if ( $this->is_debug_enabled ) {
					$maybe_error = get_post_meta( $asset, Sync::META_KEYS['sync_error'], true );
					if ( ! empty( $maybe_error ) ) {
						$errored_attachments[ $asset ] = array(
							'attachment_id' => $asset,
							'message'       => $maybe_error,
							'edit_url'      => $this->get_edit_link( $asset ),
						);

						// Asset is unsynced because it errored.
						unset( $unsynced_attachments[ $asset ] );
					}
				}
			}
			++$info[ $key ];
			$bar->tick( 1, $done . ' of ' . $total . ' |' );
		}
		// Done message.
		if ( $done === $total ) {
			$bar->tick( 0, $total . ' Analyzed |' );
			$bar->finish();
			$bar = null;
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%gSynced%n      :' ) . ' ' . $info['_cld_synced'] );
			\WP_CLI::log( \WP_CLI::colorize( '%yUn-synced%n   :' ) . ' ' . $info['_cld_unsynced'] );
			\WP_CLI::log( \WP_CLI::colorize( '%rUnsupported%n :' ) . ' ' . $info['_cld_unsupported'] );

			if ( $this->is_debug_enabled ) {
				$this->maybe_do_verbose( $unsynced_attachments, $errored_attachments );
				$this->maybe_do_export( $unsynced_attachments, $errored_attachments );
			}
			update_option( '_cld_cli_analyzed', true, false );
		}
	}

	/**
	 * Pad a file name to fit within max chars.
	 *
	 * @param string $name        The name to pad.
	 * @param int    $max_length  The max length of the  filename.
	 * @param string $pad_char    The pad char to use when name is less of the max.
	 * @param string $concat_char The char to use when shortening names to fit.
	 *
	 * @return string
	 */
	protected static function pad_name( $name, $max_length, $pad_char = '.', $concat_char = '*' ) {
		$name_length = strlen( $name );
		$prefix      = null;
		if ( $name_length > $max_length ) {
			$diff          = $name_length - $max_length;
			$concat_length = $diff > 3 ? 3 : $diff;
			$usable_length = $max_length - $concat_length;
			$front         = substr( $name, 0, floor( $usable_length / 2 ) );
			$back          = substr( $name, strlen( $name ) - ceil( $usable_length / 2 ) );
			$name          = $front . implode( array_fill( 0, $concat_length, $concat_char ) ) . $back;
		}
		$used_length = $max_length - strlen( $name );
		if ( 0 < $used_length ) {
			$prefix = implode( array_fill( 0, $used_length, $pad_char ) );
		}
		$out = $prefix . $name;

		return $out;
	}

	/**
	 * Maybe does a verbose output.
	 *
	 * @param array $unsynced_attachments The unsynced attachments data.
	 * @param array $errored_attachments  The errored attachments data.
	 *
	 * @return void
	 */
	protected function maybe_do_verbose( $unsynced_attachments, $errored_attachments ) {
		if ( $this->is_verbose ) {
			if ( ! empty( $unsynced_attachments ) || ! empty( $errored_attachments ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( \WP_CLI::colorize( '%R-- Verbose report --%n' ) );
			} else {
				\WP_CLI::log( '' );
				\WP_CLI::log( \WP_CLI::colorize( '%G-- Nothing to report --%n' ) );
			}

			if ( ! empty( $unsynced_attachments ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( \WP_CLI::colorize( '%yThe following assets are unsynced with Cloudinary:%n' ) );
				foreach ( $unsynced_attachments as $attachment ) {
					\WP_CLI::log( '' );
					\WP_CLI::log( \WP_CLI::colorize( '%yAttachment ID:%n' ) . ' ' . $attachment['attachment_id'] );
					\WP_CLI::log( \WP_CLI::colorize( '%yEdit page    :%n' ) . ' ' . $attachment['edit_url'] );
				}
			}

			if ( ! empty( $errored_attachments ) ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( \WP_CLI::colorize( '%yThe following assets:%n' ) );
				foreach ( $errored_attachments as $attachment ) {
					\WP_CLI::log( '' );
					\WP_CLI::log( \WP_CLI::colorize( '%rAttachment ID:%n' ) . ' ' . $attachment['attachment_id'] );
					\WP_CLI::log( \WP_CLI::colorize( '%rMessage      :%n' ) . ' ' . $attachment['message'] );
					\WP_CLI::log( \WP_CLI::colorize( '%rEdit page    :%n' ) . ' ' . $attachment['edit_url'] );
				}
			}
		}
	}

	/**
	 * Maybe prepares the export.
	 *
	 * @param array $unsynced_attachments    The unsynced attachments data.
	 * @param array $errored_attachments The errored attachments data.
	 *
	 * @return void
	 */
	protected function maybe_do_export( $unsynced_attachments, $errored_attachments ) {
		if ( $this->is_export ) {
			if ( ! empty( $unsynced_attachments ) ) {
				$this->export_csv( $unsynced_attachments, 'unsynced' );
			}

			if ( ! empty( $errored_attachments ) ) {
				$this->export_csv( $errored_attachments, 'errored' );
			}
		}
	}

	/**
	 * Export a CSV with the contextual data.
	 *
	 * @param array  $data The faulty attachments data.
	 * @param string $type The file content type.
	 *
	 * @return void
	 */
	protected function export_csv( $data, $type ) {
		$upload   = wp_get_upload_dir();
		$filename = trailingslashit( $upload['path'] ) . sanitize_file_name( 'cloudinary-' . $type . '-' . time() . '.csv' );
		$fp       = fopen( $filename, 'wb+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		foreach ( $data as $fields ) {
			fputcsv( $fp, $fields ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		}

		fclose( $fp );

		\WP_CLI::log( '' );
		\WP_CLI::success( sprintf( 'File created: %s', $filename ) );
	}

	/**
	 * Get the attachment Edit link without roles verification.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	protected function get_edit_link( $attachment_id ) {
		$post             = get_post( $attachment_id );
		$post_type_object = get_post_type_object( $post->post_type );
		$url              = '';
		if ( $post_type_object instanceof \WP_Post_Type ) {
			$url = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) );
		}

		return $url;
	}
}
