<?php
/**
 * Filters of HTML content for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Connect\Api;
use Cloudinary\Media;
use Cloudinary\Utils;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Filter.
 *
 * Handles filtering of HTML content.
 */
class Filter {

	/**
	 * Holds the Media instance.
	 *
	 * @since   0.1
	 *
	 * @var     Media Instance of the plugin.
	 */
	private $media;

	/**
	 * Filter constructor.
	 *
	 * @param Media $media The plugin.
	 */
	public function __construct( Media $media ) {
		$this->media = $media;
		$this->setup_hooks();
	}

	/**
	 * Get all image and video tags in the content to prepare for filtering.
	 *
	 * @param string $content HTML content.
	 * @param string $tags    List of tags to get.
	 *
	 * @return array The media tags found.
	 */
	public function get_media_tags( $content, $tags = 'img|video' ) {
		$images = array();

		if ( preg_match_all( '#(?P<tags><(' . $tags . ')[^>]*?>){1}#is', $content, $found ) ) {

			$count = count( $found[0] );
			for ( $i = 0; $i < $count; $i ++ ) {
				$images[ $i ] = $found['tags'][ $i ];
			}
		}

		return $images;
	}

	/**
	 * Get all video shortcodes in the content to prepare for filtering.
	 *
	 * @param string $html HTML content.
	 *
	 * @return array The shortcodes found.
	 */
	public function get_video_shortcodes( $html ) {
		$shortcodes = array(
			'video',
		);
		$return     = array();
		$regex      = get_shortcode_regex( $shortcodes );
		if ( preg_match_all( '/' . $regex . '/s', $html, $matches ) ) {
			foreach ( $matches[0] as $index => $match ) {
				$return[] = array(
					'html' => $match,
					'args' => $matches[3][ $index ],
				);
			}
		}

		return $return;
	}

	/**
	 * Get the attachment ID from the media tag.
	 *
	 * @param string $asset The media tag.
	 * @param string $type  The type.
	 * @return int|false
	 */
	public function get_id_from_tag( $asset, $type = 'wp-image-|wp-video-' ) {
		$attachment_id = false;
		// Get attachment id from class name.
		if ( preg_match( '#class=["|\']?[^"\']*(' . $type . ')([\d]+)[^"\']*["|\']?#i', $asset, $found ) ) {
			$attachment_id = intval( $found[2] );
		}

		return $attachment_id;
	}

	/**
	 * Get the URL from a tag.
	 *
	 * @param string $asset The tag to set the srcs for.
	 *
	 * @return string|bool The asset URL or false if not found.
	 */
	public function get_url_from_tag( $asset ) {

		$url = false;
		if ( preg_match( '/src=\"([^\"]*)\"/i', $asset, $found ) ) {
			$url = $found[1];
		}

		return $url;
	}

	/**
	 * Get the Poster URL from a tag.
	 *
	 * @param string $asset The tag to get the poster for.
	 *
	 * @return string|bool The asset URL or false if not found.
	 */
	public function get_poster_from_tag( $asset ) {

		$url = false;
		if ( preg_match( '/poster=\"([^\"]*)\"/i', $asset, $found ) ) {
			$url = $found[1];
		}

		return $url;
	}

	/**
	 * Get the size from an image tag.
	 *
	 * @param string $image The image tag.
	 *
	 * @return string|array|bool The image size string, or array of width and height or false if not found.
	 */
	public function get_size_from_image_tag( $image ) {

		$size = array();
		if ( preg_match( '#class=["|\']?[^"\']*size-([^"\'\s]+)[^"\']*["|\']?#i', $image, $found ) ) {
			$size = $found[1];
		} else {
			// Find via URL.
			$url = $this->get_url_from_tag( $image );
			if ( ! empty( $url ) ) {
				$size = $this->media->get_size_from_url( $url );
			}
		}

		return $size;
	}

	/**
	 * Get the classes for the tag.
	 *
	 * @param string $image The image html tag to find classes in.
	 *
	 * @return bool|mixed
	 */
	public function get_classes( $image ) {
		if ( preg_match( '/class="([^=]*)"/', $image, $classes ) ) {
			return $classes[1];
		}

		return false;
	}

	/**
	 * Get the crop from an image tag.
	 *
	 * @param string $image The image tag.
	 *
	 * @return array The image size array.
	 */
	public function get_crop_from_image_tag( $image ) {

		$size = array();
		if ( preg_match( '#width=["|\']?([\d%]+)["|\']?#i', $image, $width ) ) {
			$size[] = $width[1];
		}

		if ( preg_match( '#height=["|\']?([\d%]+)["|\']?#i', $image, $height ) ) {
			$size[] = $height[1];
		}

		return $size;
	}

	/**
	 * Filter out Cloudinary video URL when saving to the DB.
	 *
	 * @param string $content The content to filter.
	 *
	 * @return string
	 */
	public function filter_video_shortcodes( $content ) {

		$shortcodes = $this->get_video_shortcodes( $content );
		$exts       = wp_get_video_extensions();
		foreach ( $shortcodes as $shortcode ) {
			$args = shortcode_parse_atts( $shortcode['args'] );

			// Bypass empty args shortcodes.
			if ( empty( $args ) ) {
				continue;
			}

			// Get the format.
			list( $format ) = array_intersect( $exts, array_keys( $args ) );
			if ( null !== $format ) {
				$url = $args[ $format ];
				if ( empty( $args['id'] ) ) {
					$attachment_id = $this->media->get_id_from_url( $url );
					if ( empty( $attachment_id ) ) {
						break; // No ID can be found. could be a remote source.
					}
					$args['id'] = $attachment_id;
				}
				if ( ! empty( $args['transformations'] ) && ! $this->media->is_cloudinary_url( $url ) ) {

					$transformations           = $this->media->get_transformations_from_string( $args['transformations'] );
					$overwrite_transformations = false;
					if ( ! empty( $args['cldoverwrite'] ) && 'true' === $args['cldoverwrite'] ) {
						$overwrite_transformations = true;
					}
					$new_url = $this->media->cloudinary_url( $args['id'], false, $transformations, null, $overwrite_transformations );
				} else {
					$new_url = wp_get_attachment_url( $args['id'] );
				}
				$content = str_replace( $url, $new_url, $content );
			}
		}

		return $content;
	}

	/**
	 * If the post is a web story or AMP-powered, take care of the special AMP tags.
	 *
	 * @param array $data The post data array to save.
	 *
	 * @return array
	 */
	public function prepare_amp_posts( $data ) {
		if ( ! Utils::is_webstory_post_type( $data['post_type'] ) && ! Utils::is_amp( $data['post_content'] ) ) {
			return $data;
		}

		$data['post_content'] = $this->filter_out_local( wp_unslash( $data['post_content'] ), 'amp-img|source' );

		return $data;
	}

	/**
	 * Filter out Cloudinary URL when saving to the DB.
	 *
	 * @param array $data The post data array to save.
	 *
	 * @return array
	 */
	public function filter_out_cloudinary( $data ) {
		// Check whether this page is powered by AMP or is a web story. Bail if that's the case.
		if ( Utils::is_webstory_post_type( $data['post_type'] ) || Utils::is_amp( $data['post_content'] ) ) {
			return $data;
		}

		$content = trim( wp_unslash( $data['post_content'] ) );
		$assets  = $this->get_media_tags( $content );

		foreach ( $assets as $asset ) {
			$url           = $this->get_url_from_tag( $asset );
			$attachment_id = $this->get_id_from_tag( $asset );
			if ( false === $attachment_id ) {
				$attachment_id = $this->media->get_id_from_url( $url );
				if ( empty( $attachment_id ) ) {
					continue;
				}
			}
			if ( wp_attachment_is_image( $attachment_id ) ) {
				$size      = $this->get_size_from_image_tag( $asset );
				$local_url = wp_get_attachment_image_url( $attachment_id, $size );
			} else {
				$local_url = wp_get_attachment_url( $attachment_id );
			}
			// Skip since there is no local available.
			if ( $this->media->is_cloudinary_url( $local_url ) ) {
				continue;
			}
			// Replace old tag.
			$content = str_replace( $url, $local_url, $content );

		}
		$data['post_content'] = wp_slash( $this->filter_video_shortcodes( $content ) );

		return $data;
	}

	/**
	 * Filter content to replace local src urls with Cloudinary urls.
	 *
	 * @param string $content The content to filter urls.
	 * @param string $tags    The tags to look out for in the post content.
	 *
	 * @return string The filtered content.
	 */
	public function filter_out_local( $content, $tags = 'img' ) {

		$assets = $this->get_media_tags( $content, $tags );
		foreach ( $assets as $asset ) {

			$url           = $this->get_url_from_tag( $asset );
			$attachment_id = $this->get_id_from_tag( $asset );

			// Check if this is not already a cloudinary url and if is not in the sync folder, for Cloudinary only storage cases.
			if ( $this->media->is_cloudinary_url( $url ) && ! $this->media->is_cloudinary_sync_folder( $url ) ) {
				// Is a content based ID. If has a cloudinary ID, it's from an older plugin version.
				// Check if has an ID, and push update to reset.
				if ( ! empty( $attachment_id ) && ! $this->media->plugin->components['sync']->is_synced( $attachment_id ) ) {
					$this->media->cloudinary_id( $attachment_id ); // Start an on-demand sync.
				}

				continue; // Already a cloudinary URL. Possibly from a previous version. Will correct on post update after synced.
			}

			if ( false === $attachment_id ) {
				$attachment_id = $this->media->get_id_from_url( $url );
				if ( empty( $attachment_id ) ) {
					continue; // Can't find an id, skip.
				}
			}
			$cloudinary_id = $this->media->cloudinary_id( $attachment_id );
			if ( empty( $cloudinary_id ) ) {
				continue; // No cloudinary ID.
			}
			$transformations = array();
			$query           = wp_parse_url( $url, PHP_URL_QUERY );
			if ( ! empty( $query ) && false !== strpos( $query, 'cld_params' ) ) {
				// Has params in src.
				$args = array();
				wp_parse_str( $query, $args );
				$transformations = $this->media->get_transformations_from_string( $args['cld_params'] );
			}
			// Get the WP size from the class name.
			$wp_size = $this->media->get_crop( $url, $attachment_id );
			if ( false === $wp_size ) {
				// No class name, so get size from the width and height tags.
				$wp_size = $this->get_crop_from_image_tag( $asset );
			}

			// Get a cloudinary URL.
			$classes                   = $this->get_classes( $asset ); // check if this is a transformation overwrite.
			$overwrite_transformations = false !== strpos( $classes, 'cld-overwrite' );

			$cloudinary_url = $this->media->cloudinary_url( $attachment_id, $wp_size, $transformations, null, $overwrite_transformations );

			if ( $url === $cloudinary_url ) {
				continue;
			}

			// Replace old tag.
			$new_tag = str_replace( $url, $cloudinary_url, $asset );

			// Add front end features.
			if ( ! is_admin() ) {
				// Check if there is a class set. ( for srcset images in case of a manual url added ).
				if ( false === strpos( $new_tag, ' class=' ) ) {
					// Add in the class name.
					$new_tag = str_replace( '/>', ' class="wp-image-' . $attachment_id . '"/>', $new_tag );
				}
				// Apply lazy loading attribute.
				if ( apply_filters( 'wp_lazy_loading_enabled', true ) && false === strpos( $new_tag, 'loading="lazy"' ) ) {
					$new_tag = str_replace( '/>', ' loading="lazy" />', $new_tag );
				}

				// If Cloudinary player is active, this is replaced there.
				if ( ! $this->media->video->player_enabled() ) {
					$poster = $this->get_poster_from_tag( $asset );
					if ( false !== $poster ) {
						$post_attachment_id = $this->media->get_id_from_url( $poster );
						$cloudinary_url     = $this->media->cloudinary_url( $post_attachment_id );
						$new_tag            = str_replace( $poster, $cloudinary_url, $new_tag );
					}
				}
				$new_tag = $this->media->apply_srcset( $new_tag, $attachment_id, $overwrite_transformations );
			}
			// Additional URL change for backgrounds etc..
			$content = str_replace( array( $asset, $url ), array( $new_tag, $cloudinary_url ), $content );
		}

		return $this->filter_video_shortcodes( $content );
	}

	/**
	 * Return a Cloudinary URL for an attachment used in JS.
	 *
	 * @param array $attachment The attachment response array.
	 *
	 * @return array
	 * @uses filter:wp_prepare_attachment_for_js
	 */
	public function filter_attachment_for_js( $attachment ) {
		$cloudinary_id = $this->media->get_cloudinary_id( $attachment['id'] );

		if ( $cloudinary_id ) {
			$transformations = array();

			if ( ! empty( $attachment['transformations'] ) ) {
				$transformations = $attachment['transformations'];
			} else {
				$attachment['transformations'] = $this->media->get_transformation_from_meta( $attachment['id'] );
			}

			$attachment['url']       = $this->media->cloudinary_url( $attachment['id'], false, $transformations );
			$attachment['public_id'] = $attachment['type'] . '/upload/' . $this->media->get_public_id( $attachment['id'] );

			if ( empty( $attachment['transformations'] ) ) {
				$transformations = $this->media->get_transformation_from_meta( $attachment['id'] );

				if ( $transformations ) {
					$attachment['transformations'] = $transformations;
				}
			}

			// Ensure the sizes has the transformations and are converted URLS.
			if ( ! empty( $attachment['sizes'] ) ) {
				foreach ( $attachment['sizes'] as &$size ) {
					$size['url'] = $this->media->convert_url( basename( $size['url'] ), $attachment['id'], $transformations );
				}
			}
		}

		return $attachment;
	}

	/**
	 * Return a Cloudinary URL for an attachment used in a REST REQUEST.
	 *
	 * @param WP_REST_Response $attachment The attachment array to be used in JS.
	 *
	 * @return WP_REST_Response
	 * @uses filter:rest_prepare_attachment
	 */
	public function filter_attachment_for_rest( $attachment ) {
		if ( ! isset( $attachment->data['id'] ) ) {
			return $attachment;
		}

		$cloudinary_id = $this->media->cloudinary_id( $attachment->data['id'] );

		if ( $cloudinary_id ) {
			$attachment->data['source_url'] = $this->media->cloudinary_url( $attachment->data['id'], false );
		}

		$has_transformations = ! empty( $this->media->get_transformation_from_meta( $attachment->data['id'] ) );
		if ( $has_transformations ) {
			$attachment->data['transformations'] = $has_transformations;
		}

		return $attachment;
	}

	/**
	 * Filter the image tag being sent to the editor to include transformations.
	 *
	 * @param string $html       The image tag.
	 * @param int    $id         The attachment id.
	 * @param array  $attachment The attachment array.
	 *
	 * @return mixed
	 */
	public function transform_to_editor( $html, $id, $attachment ) {

		if ( '<img' === substr( $html, 0, 4 ) ) {

			// Add overwrite class is set.
			if ( ! empty( $attachment['cldoverwrite'] ) ) {
				$classes_attribute = $this->get_classes( $html );
				$classes           = explode( ' ', $classes_attribute );
				if ( ! in_array( 'cld-overwrite', $classes, true ) ) {
					$classes[] = 'cld-overwrite';
				}
				$html = str_replace( 'class="' . $classes_attribute . '"', 'class="' . implode( ' ', $classes ) . '"', $html );
			}

			// Change url if transformations exist.
			if ( ! empty( $attachment['transformations'] ) ) {
				// Ensure there is a Cloudinary URL.
				$url     = $this->get_url_from_tag( $html );
				$new_url = $this->media->cloudinary_url( $id, $attachment['image-size'], $attachment['transformations'] );
				if ( false !== $new_url ) {
					$html = str_replace( $url, $new_url, $html );
				}
			}
		} elseif ( '[video' === substr( $html, 0, 6 ) ) {
			// Do shortcode.
			if ( ! empty( $attachment['cldoverwrite'] ) ) {
				$html = str_replace( '[video', '[video cldoverwrite="true"', $html );
			}
		}

		return $html;
	}

	/**
	 * Filter the video shortcode.
	 *
	 * @param string $html       The HTML to filter.
	 * @param int    $id         The attachment id.
	 * @param array  $attachment The attachment array.
	 *
	 * @return mixed
	 */
	public function filter_video_embeds( $html, $id, $attachment ) {

		$shortcodes = $this->get_video_shortcodes( $html );
		foreach ( $shortcodes as $shortcode ) {
			// Add ID.
			$new_atts = $shortcode['args'] . ' id="' . esc_attr( $id ) . '"';

			// Add defaults.
			$settings = $this->media->get_settings()->get_value( 'video_settings' );
			if ( 'off' !== $settings['video_autoplay_mode'] ) {
				$new_atts .= ' autoplay="true"';
			}
			if ( 'on' === $settings['video_controls'] ) {
				$new_atts .= ' controls="true"';
			}
			if ( 'on' === $settings['video_loop'] ) {
				$new_atts .= ' loop="true"';
			}
			if ( ! empty( $attachment['transformations'] ) ) {
				$transformation_string = Api::generate_transformation_string( $attachment['transformations'], 'video' );
				$new_atts             .= ' transformations="' . esc_attr( $transformation_string ) . '"';
			}
			$html = str_replace( $shortcode['args'], $new_atts, $html );
		}

		return $html;
	}

	/**
	 * Filter out local urls in an 'edit' context rest request ( i.e for Gutenberg ).
	 *
	 * @param WP_REST_Response $response The post data array to save.
	 * @param WP_Post          $post     The current post.
	 * @param WP_REST_Request  $request  The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function pre_filter_rest_content( $response, $post, $request ) {
		$context = $request->get_param( 'context' );
		if ( 'edit' === $context ) {
			$data                   = $response->get_data();
			$content                = $data['content']['raw'];
			$data['content']['raw'] = $this->filter_out_local( $content );

			// Handle meta if missing due to custom-fields not being supported.
			if ( ! isset( $data['meta'] ) ) {
				$data['meta'] = $request->get_param( 'meta' );
				if ( null === $data['meta'] ) {
					// If null, meta param doesn't exist, so it's not a save edit, but a load edit.
					$disable = get_post_meta( $post->ID, Global_Transformations::META_FEATURED_IMAGE_KEY, true );
					// Add the value to the data meta.
					$data['meta'][ Global_Transformations::META_FEATURED_IMAGE_KEY ] = $disable;
				} else {
					// If the param was found, its a save edit, to update the meta data.
					update_post_meta( $post->ID, Global_Transformations::META_FEATURED_IMAGE_KEY, (bool) $data['meta'][ Global_Transformations::META_FEATURED_IMAGE_KEY ] );
				}
			}
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Conditionally remove editors in post context to prevent users editing images in WP.
	 *
	 * @param array $editors List of available editors.
	 *
	 * @return array
	 */
	public function disable_editors_maybe( $editors ) {

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && 'post' === $screen->base ) {
				$editors = array();
			}
		}

		return $editors;
	}

	/**
	 * Returns the overwrite template for the insert media panel.
	 *
	 * @return string
	 */
	private function template_overwrite_insert() {
		return '<# if( data.attachment.attributes.transformations ) { #>
			<div class="setting cld-overwrite">
				<label>
					<span>' . esc_html__( 'Overwrite Global Transformations', 'cloudinary' ) . '</span>
					<input type="checkbox" data-setting="cldoverwrite" value="true"<# if ( data.model.cldoverwrite ) { #> checked="checked"<# } #> />
				</label>
			</div>
		<# } #>';
	}

	/**
	 * Returns the overwrite template for the insert video media panel.
	 *
	 * @return string
	 */
	private function template_overwrite_insert_video() {
		return '<# if( \'video\' === data.type && data.attachment.attributes.transformations ) { #>
			<div class="setting cld-overwrite">
				<label>
					<span>' . esc_html__( 'Overwrite Global Transformations', 'cloudinary' ) . '</span>
					<input type="checkbox" data-setting="cldoverwrite" value="true"<# if ( data.model.cldoverwrite ) { #> checked="checked"<# } #> />
				</label>
			</div>
		<# } #>';
	}

	/**
	 * Returns the overwrite template for the edit media panel.
	 *
	 * @return string
	 */
	private function template_overwrite_edit() {
		return '<# if( data.attachment.transformations ) {  #>
			<div class="setting cld-overwrite">
				<label>
					<span>&nbsp;</span>
					<input type="checkbox" data-setting="cldoverwrite" value="true" <# if ( data.model.cldoverwrite ) { #>checked="checked"<# } #> />
					' . esc_html__( 'Overwrite Global Transformations', 'cloudinary' ) . '
				</label>
			</div>
		<# } #>';
	}

	/**
	 * Returns the overwrite template for the video edit media panel.
	 *
	 * @return string
	 */
	private function template_overwrite_video_edit() {
		return '<# if( data.model.transformations ) {  #>
			<div class="setting cld-overwrite">
				<label>
					<input type="checkbox" data-setting="cldoverwrite" value="true" <# if ( data.model.cldoverwrite ) { #>checked="checked"<# } #> />
					' . esc_html__( 'Overwrite Global Transformations', 'cloudinary' ) . '
				</label>
			</div>
		<# } #>';
	}

	/**
	 * Inject out templates into the media templates.
	 */
	public function overwrite_template_inject() {
		// Catch the output buffer so we can alter the templates.
		$template = ob_get_clean();
		// Replace template.
		$str_label      = '<label class="setting align">';
		$str_div        = '<div class="setting align">';
		$str_container  = strpos( $template, $str_div ) !== false ? $str_div : '<fieldset class="setting-group">';
		$str_vid_edit   = '<# if ( ! _.isEmpty( data.model.poster ) ) { #>';
		$str_vid_insert = '<# if ( \'undefined\' !== typeof data.sizes ) { #>';
		$template       = str_replace( $str_label, $this->template_overwrite_insert() . $str_label, $template );
		$template       = str_replace( $str_container, $this->template_overwrite_edit() . $str_container, $template );
		$template       = str_replace( $str_vid_edit, $this->template_overwrite_video_edit() . $str_vid_edit, $template );
		$template       = str_replace( $str_vid_insert, $this->template_overwrite_insert_video() . $str_vid_insert, $template );

		echo $template; // phpcs:ignore XSS ok
	}

	/**
	 * Start output buffer to catch templates used in media modal if media templates are called.
	 */
	public function catch_media_templates_maybe() {
		// Only start output buffer if wp_print_media_templates is queued.
		if ( has_action( 'admin_footer', 'wp_print_media_templates' ) ) {

			ob_start();
			// Prepare output buffer filtering..
			add_action( 'print_media_templates', array( $this, 'overwrite_template_inject' ), 11 );
		}
	}

	/**
	 * Fallback for render_block_data filter.
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function filter_image_block_render_block( $block_content, array $block ) {
		if ( 'core/image' === $block['blockName'] && ! empty( $block['attrs']['overwrite_transformations'] ) ) {
			$block_content = str_replace( 'wp-image-' . $block['attrs']['id'], 'wp-image-' . $block['attrs']['id'] . ' cld-overwrite', $block_content );
		}

		return $block_content;
	}

	/**
	 * Add filters for Rest API handling.
	 */
	public function init_rest_filters() {
		// Gutenberg compatibility.
		add_filter( 'rest_prepare_attachment', array( $this, 'filter_attachment_for_rest' ) );
		$types = get_post_types_by_support( 'editor' );
		foreach ( $types as $type ) {
			$post_type = get_post_type_object( $type );
			// Check if this is a rest supported type.
			if ( true === $post_type->show_in_rest ) {
				// Add filter only to rest supported types.
				add_filter( 'rest_prepare_' . $type, array( $this, 'pre_filter_rest_content' ), 10, 3 );
			}
		}
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {
		// Filter URLS within content.
		add_action( 'wp_insert_post_data', array( $this, 'filter_out_cloudinary' ) );
		add_action( 'wp_insert_post_data', array( $this, 'prepare_amp_posts' ), 11 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'filter_attachment_for_js' ), 11 );

		// Add support for custom header.
		add_filter( 'get_header_image_tag', array( $this, 'filter_out_local' ) );

		// Add transformations.
		add_filter( 'media_send_to_editor', array( $this, 'transform_to_editor' ), 10, 3 );

		// Filter video codes.
		add_filter( 'media_send_to_editor', array( $this, 'filter_video_embeds' ), 10, 3 );

		// Enable Rest filters.
		add_action( 'rest_api_init', array( $this, 'init_rest_filters' ) );

		// Remove editors to prevent users from manually editing images in WP.
		add_filter( 'wp_image_editors', array( $this, 'disable_editors_maybe' ) );

		// Add checkbox to media modal template.
		add_action( 'admin_footer', array( $this, 'catch_media_templates_maybe' ), 9 );

		// Filter for block rendering.
		add_filter( 'render_block', array( $this, 'filter_image_block_render_block' ), 10, 2 );


		// Filter out locals and responsive images setup.
		if ( $this->media->can_filter_out_local() || is_admin() ) {
			// Filtering out locals.
			add_filter( 'the_editor_content', array( $this, 'filter_out_local' ) );
			add_filter( 'the_content', array( $this, 'filter_out_local' ), 100 );
			// Cancel out breakpoints till later.
			add_filter(
				'wp_img_tag_add_srcset_and_sizes_attr',
				function ( $add, $image, $context, $attachment_id ) {
					$use = true;
					if ( $this->media->has_public_id( $attachment_id ) && apply_filters( 'cloudinary_filter_out_local', true ) ) {
						$use = false;
					}

					return $use;
				},
				10,
				4
			);
		}
	}
}
