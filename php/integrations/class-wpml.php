<?php
/**
 * WPML integration class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Integrations;

use Cloudinary\Cron;
use Cloudinary\Relate\Relationship;
use Cloudinary\Utils;
use WPML\Auryn\InjectionException;
use WPML\FP\Obj;
use WPML\Records\Translations as TranslationRecords;
use function WPML\Container\make;
use function Cloudinary\get_plugin_instance;

/**
 * Class WPML
 */
class WPML extends Integrations {

	/**
	 * The transient key.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wpml_cloudinary';

	/**
	 * The limit of duplicated assets to sync per cron run.
	 *
	 * @var int
	 */
	const LIMIT = 200;

	/**
	 * The interval of the cron in seconds.
	 *
	 * @var int
	 */
	const INTERVAL = 60;

	/**
	 * Check if the integration can be enabled.
	 *
	 * @return bool
	 */
	public function can_enable() {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Register hooks for the integration.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wpml_media_create_duplicate_attachment', array( $this, 'flag_duplicated_assets' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'wp_generate_attachment_metadata' ), 10, 3 );
		add_action( 'cloudinary_ready', array( $this, 'setup_cron' ) );
		add_filter( 'cloudinary_media_context', array( $this, 'add_wpml_context' ), 10, 2 );
		add_filter( 'cloudinary_media_context_query', array( $this, 'filter_media_context_query' ) );
		add_filter( 'cloudinary_media_context_things', array( $this, 'filter_media_context_things' ) );
		add_filter( 'cloudinary_home_url', array( $this, 'home_url' ) );
		add_filter( 'cloudinary_rest_url', array( $this, 'rest_url' ), 10, 3 );
		add_action( 'cloudinary_edit_asset_permalink', array( $this, 'add_locale' ) );
		add_filter( 'cloudinary_contextualized_post_id', array( $this, 'contextualized_post_id' ) );
		add_filter( 'wpml_admin_language_switcher_items', array( $this, 'language_switcher_items' ) );
	}

	/**
	 * Flag WPML duplicated assets.
	 *
	 * @param int $original_attachment_id   The original attachment ID.
	 * @param int $duplicated_attachment_id The duplicated attachment ID.
	 *
	 * @return void
	 */
	public function flag_duplicated_assets( $original_attachment_id, $duplicated_attachment_id ) {
		$data                              = array_filter( (array) get_transient( self::TRANSIENT_KEY ) );
		$data[ $original_attachment_id ][] = $duplicated_attachment_id;

		set_transient( self::TRANSIENT_KEY, $data );
	}

	/**
	 * Restore WPML duplicated assets metadata.
	 *
	 * @param array  $metadata      The attachment metadata.
	 * @param int    $attachment_id The attachment ID.
	 * @param string $context       The context.
	 *
	 * @return array
	 * @throws InjectionException When the WPML_Model_Attachments service is not available.
	 */
	public function wp_generate_attachment_metadata( $metadata, $attachment_id, $context ) {

		if ( 'create' !== $context ) {
			return $metadata;
		}

		$original_attachment_id = $attachment_id;
		$data                   = get_transient( self::TRANSIENT_KEY );
		// Cast to array to avoid string-offset fatals on PHP 8.x when the
		// transient holds an unexpected scalar.
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// This is a duplicated attachment. Let's restore the metadata via WPML.
		if ( ! empty( $data[ $original_attachment_id ] ) ) {
			foreach ( $data[ $original_attachment_id ] as $key => $duplicated_attachment_id ) {
				make( 'WPML_Model_Attachments' )->duplicate_post_meta_data( $original_attachment_id, $duplicated_attachment_id );

				// Prepare clean up data.
				unset( $data[ $original_attachment_id ][ $key ] );
			}

			if ( empty( $data[ $original_attachment_id ] ) && isset( $data[ $original_attachment_id ] ) ) {
				unset( $data[ $original_attachment_id ] );
			}

			// Do clean up data.
			if ( ! empty( $data ) ) {
				set_transient( self::TRANSIENT_KEY, $data );
			} else {
				delete_transient( self::TRANSIENT_KEY );
			}
		}

		return $metadata;
	}

	/**
	 * Setup the cron.
	 *
	 * @return void
	 */
	public function setup_cron() {
		$results = $this->get_unynced();
		$plugin  = get_plugin_instance();
		$cron    = $plugin->get_component( 'cron' );

		// If there aren't items left to sync, we don't need to register the cron.
		if ( ! empty( $results ) && $cron instanceof Cron ) {
			Cron::register_process( 'wpml_unsynced', array( $this, 'load_actions' ), self::INTERVAL );
		}
	}

	/**
	 * Add the WPML context to the media.
	 *
	 * @param null|string $context       The context.
	 * @param null|int    $attachment_id The attachment ID.
	 *
	 * @return string|null
	 */
	public function add_wpml_context( $context, $attachment_id ) {
		if ( is_null( $attachment_id ) || 'attachment' === get_post_type( $attachment_id ) ) {
			$context = apply_filters( 'wpml_current_language', null );
		}

		return $context;
	}

	/**
	 * Filter the media context query.
	 *
	 * @return string
	 */
	public function filter_media_context_query() {
		return 'media_context IN ( %s, %s )';
	}

	/**
	 * Filter the media context things to query.
	 *
	 * @param array $things The things to query for.
	 *
	 * @return array
	 */
	public function filter_media_context_things( $things ) {
		$things[] = Utils::get_media_context();

		return $things;
	}

	/**
	 * Get the home URL.
	 * Typically WPML will return the home URL with the current language as subdirectory.
	 * For dealing with static assets, we need the home URL without the language subdirectory.
	 *
	 * @return string
	 */
	public function home_url() {
		return get_option( 'home' );
	}

	/**
	 * Rebuild the REST URL so it carries the current language correctly in every WPML
	 * negotiation mode.
	 *
	 * WPML hooks WordPress's `home_url` filter to inject the browsing language into every
	 * generated URL. Since `rest_url()` applies that filter to the REST base before the
	 * endpoint path is appended, a non-default language corrupts the URL, e.g.
	 * `/wp-json/?lang=fr/cloudinary/v1/queue` instead of `/wp-json/cloudinary/v1/queue`.
	 * This silently breaks the background sync loopback request: it "succeeds" by hitting
	 * the REST index instead of the intended route, so queued assets never finish syncing.
	 *
	 * First, `WPML_URL_Filters::remove_global_hooks()`/`add_global_hooks()` (WPML's own
	 * supported way of getting a clean, language-unfiltered URL, also used by WPML's Google
	 * Site Kit and canonical-URL compatibility code) strips that `home_url` mangling.
	 *
	 * That alone is only enough in directory/domain negotiation mode, where WPML separately
	 * hooks core's `rest_url` filter directly (`WPML_URL_Converter_Subdir_Strategy`/
	 * `_Domain_Strategy`) to re-insert the language after the full URL is built - a hook
	 * `remove_global_hooks()` doesn't touch. In "language as a parameter" mode, nothing does
	 * that (`WPML_Lang_Parameter_Filters` only hooks `request`/`get_pagenum_link`/
	 * `wp_link_pages_link`), so the clean URL would be missing `?lang=` entirely - not just for
	 * this loopback request, but for every browser-facing endpoint built via `Utils::rest_url()`
	 * (asset fetch/save, cache purge, analytics, UI state), silently running them under the
	 * default language's context instead. Re-applying the language via `wpml_permalink` -
	 * WPML's documented public filter, routing to `WPML_URL_Converter::convert_url()` - fixes
	 * that: it's a no-op when the URL already carries the right language (directory/domain
	 * mode), and appends `?lang=` correctly when it doesn't (parameter mode).
	 *
	 * @param string      $rest_url The REST url, already corrupted by WPML's `home_url` filter.
	 * @param string      $path     The REST path that was requested.
	 * @param string|null $scheme   The scheme used for the REST url.
	 *
	 * @return string
	 */
	public function rest_url( $rest_url, $path, $scheme ) {
		if ( ! class_exists( 'WPML_URL_Filters' ) || ! function_exists( 'WPML\Container\make' ) ) {
			return $rest_url;
		}

		$url_filters = make( 'WPML_URL_Filters' );
		if ( ! method_exists( $url_filters, 'remove_global_hooks' ) || ! method_exists( $url_filters, 'add_global_hooks' ) ) {
			return $rest_url;
		}

		$url_filters->remove_global_hooks();
		try {
			$clean_rest_url = rest_url( $path, $scheme );
		} finally {
			$url_filters->add_global_hooks();
		}

		return apply_filters( 'wpml_permalink', $clean_rest_url, apply_filters( 'wpml_current_language', null ) );
	}

	/**
	 * Add the locale to the edit asset link.
	 * This will ensure that the asset is edited in the correct language.
	 *
	 * @param string $permalink The permalink.
	 *
	 * @return string
	 */
	public function add_locale( $permalink ) {
		return add_query_arg( 'lang', apply_filters( 'wpml_current_language', null ), $permalink );
	}

	/**
	 * Get the contextualized post id.
	 *
	 * @param int $post_id The attachment id.
	 *
	 * @return int
	 */
	public function contextualized_post_id( $post_id ) {
		if ( 'attachment' !== get_post_type( $post_id ) ) {
			return $post_id;
		}

		return apply_filters( 'wpml_object_id', $post_id, 'attachment' );
	}

	/**
	 * Update the link for the Cloudinary Assets item on the admin bar language switcher.
	 *
	 * @param array $languages_links The language switcher items.
	 *
	 * @return array
	 */
	public function language_switcher_items( $languages_links ) {
		foreach ( $languages_links as $language => &$link ) {
			$args       = array();
			$query_args = wp_parse_url( $link['url'], PHP_URL_QUERY );
			parse_str( $query_args, $args );

			// Check if we are in the context of editing an asset.
			if (
				empty( $args['page'] )
				|| 'cloudinary' !== $args['page']
				|| empty( $args['section'] )
				|| 'edit-asset' !== $args['section']
				|| empty( $args['asset'] )
			) {
				break;
			}

			$relationship            = new Relationship( (int) $args['asset'] );
			$contextual_relationship = $relationship->get_contextualized_relationship( $language );

			if ( ! empty( $contextual_relationship ) ) {
				$link['url'] = add_query_arg( 'asset', $contextual_relationship->post_id, $link['url'] );
			}
		}

		return $languages_links;
	}

	/**
	 * Register the cron action at a late stage to ensure that WPML is loaded.
	 *
	 * @return void
	 */
	public function load_actions() {
		add_action( 'shutdown', array( $this, 'maybe_sync_media' ) );
	}

	/**
	 * Sync the pending media.
	 *
	 * @return void
	 * @throws InjectionException When the WPML_Model_Attachments service is not available.
	 */
	public function maybe_sync_media() {
		$results = $this->get_unynced();

		// If there isn't any item left to sync, we don't need to keep the cron.
		if ( empty( $results ) ) {
			$plugin = get_plugin_instance();
			$cron   = $plugin->get_component( 'cron' );

			if ( $cron instanceof Cron ) {
				$cron->unregister_schedule( 'wpml_unsynced' );
			}

			return;
		}

		foreach ( $results as $duplicated_attachment_id ) {
			// The TRID of the duplicated attachment, as it acts as the canonical source the asset, so we can get the original attachment ID.
			$trid                   = apply_filters( 'wpml_element_trid', null, $duplicated_attachment_id, 'post_attachment' );
			$original_attachment_id = Obj::prop( 'element_id', TranslationRecords::getSourceByTrid( $trid ) );

			// Use WPML internals to update the metadata.
			make( 'WPML_Model_Attachments' )->duplicate_post_meta_data( $original_attachment_id, $duplicated_attachment_id );

			// Flag the attachment as synced.
			update_post_meta( $duplicated_attachment_id, '_wpml_cld', 1 );
		}
	}

	/**
	 * Get the unsynced media.
	 *
	 * @return array|null
	 */
	protected function get_unynced() {
		static $results = null;

		if ( is_array( $results ) ) {
			return $results;
		}

		global $wpdb;

		$sql = "SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE (
				(
					meta_key = '_wp_attachment_metadata' AND meta_value = ''
				)
				OR (
					meta_key = '_wp_attached_file' AND meta_value = ''
				)
			)
			AND post_id NOT IN (
				SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpml_cld'
			)
			LIMIT %d";

		$query   = $wpdb->prepare( $sql, self::LIMIT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = array_map( 'intval', $wpdb->get_col( $query ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results;
	}
}
