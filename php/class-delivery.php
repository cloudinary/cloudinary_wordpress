<?php
/**
 * Cloudinary Delivery for delivery of cloudinary assets.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Media\Filter;

/**
 * Plugin Delivery class.
 */
class Delivery implements Setup {

	/**
	 * Holds the core plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the Media\Filter component.
	 *
	 * @var Filter
	 */
	protected $filter;

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->media  = $this->plugin->get_component( 'media' );
		$this->filter = $this->media->filter;
		$this->setup_hooks();
	}

	/**
	 * Setup early needed hooks.
	 */
	protected function setup_hooks() {
		add_filter( 'cloudinary_filter_out_local', '__return_false' );
	}

	/**
	 * Setup component.
	 */
	public function setup() {
		add_action( 'cloudinary_string_replace', array( $this, 'catch_urls' ) );
	}

	/**
	 * Get the attachment ID from the media tag.
	 *
	 * @param string $html The HTML.
	 *
	 * @return int[]
	 */
	public function get_id_from_tags( $html ) {
		$attachment_ids = array();
		// Get attachment id from class name.
		if ( preg_match_all( '#class=["|\']?[^"\']*(wp-image-|wp-video-)([\d]+)[^"\']*["|\']?#i', $html, $found ) ) {
			$attachment_ids = array_map( 'intval', $found[2] );
		}

		return $attachment_ids;
	}

	/**
	 * Get known urls from image tags that have a wp-image-id.
	 *
	 * @param string $content The HTML content to get known urls from.
	 *
	 * @return array
	 */
	public function get_known_urls( $content ) {
		$ids  = $this->get_id_from_tags( $content );
		$urls = array();
		foreach ( $ids as $id ) {
			if ( ! $this->media->cloudinary_id( $id ) ) {
				continue;
			}
			$urls = array_merge( $urls, $this->get_attachment_size_urls( $id ) );
		}

		return $urls;
	}

	/**
	 * Get all sizes URLS for an attachment.
	 *
	 * @param int $attachment_id Get the image size URLS from an attachment ID.
	 *
	 * @return array
	 */
	public function get_attachment_size_urls( $attachment_id ) {
		$urls             = array();
		$meta             = wp_get_attachment_metadata( $attachment_id );
		$dirs             = wp_get_upload_dir();
		$base             = trailingslashit( $dirs['baseurl'] );
		$baseurl          = wp_get_attachment_url( $attachment_id );
		$urls[ $baseurl ] = $this->media->cloudinary_url( $attachment_id );
		// Ignore getting 'original_image' since this isn't used in the front end.
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $data ) {
				$urls[ $base . $data['file'] ] = $this->media->cloudinary_url( $attachment_id, array( $data['width'], $data['height'] ) );
			}
		}

		return $urls;
	}

	/**
	 * Find the attachment sizes from a list of URLS.
	 *
	 * @param array $urls URLS to find attachments for.
	 *
	 * @return array
	 */
	public function find_attachment_size_urls( $urls ) {
		global $wpdb;
		$dirs   = wp_get_upload_dir();
		$search = array();
		$found  = array();
		foreach ( $urls as $url ) {
			$url = ltrim( str_replace( $dirs['baseurl'], '', $url ), '/' );
			if ( ! preg_match( '/(-(\d+)x(\d+))\./i', $url, $match ) ) {
				$search[] = $url;
				continue;
			}
			$search[] = str_replace( $match[1], '', $url );
			$search[] = str_replace( $match[1], '-scaled', $url );
		}

		$in = implode( ',', array_fill( 0, count( $search ), '%s' ) );

		$sql    = $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value IN ({$in})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$search
		);
		$key    = md5( $sql );
		$cached = wp_cache_get( $key );
		if ( false === $cached ) {
			$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$post_id = null;
			if ( $results ) {
				foreach ( $results as $result ) {
					$found = array_merge( $found, $this->get_attachment_size_urls( $result->post_id ) );
				}
			}
			$cached = $found;
			wp_cache_set( $key, $found );
		}

		return $cached;
	}

	/**
	 * Catch attachment URLS from HTML content.
	 *
	 * @param string $content The HTML to catch URLS from.
	 */
	public function catch_urls( $content ) {
		$known = $this->get_known_urls( $content );
		$urls  = wp_extract_urls( $content );
		$dirs  = wp_get_upload_dir();
		$urls  = array_map(
			function ( $url ) use ( $dirs ) {

				if ( false === strpos( $url, $dirs['baseurl'] ) ) {
					return null;
				}

				return $url;
			},
			$urls
		);

		$urls    = array_filter( $urls );
		$unknown = array_diff( $urls, array_keys( $known ) );
		if ( ! empty( $unknown ) ) {
			$known = array_merge( $known, $this->find_attachment_size_urls( $unknown ) );
		}
		foreach ( $known as $src => $replace ) {
			String_Replace::replace( $src, $replace );
		}
	}
}
