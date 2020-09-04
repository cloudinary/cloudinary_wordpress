<?php
/**
 * Filters of HTML content for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Connect\Api;

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
	 * @var     \Cloudinary\Media Instance of the plugin.
	 */
	private $media;

	/**
	 * Filter constructor.
	 *
	 * @param \Cloudinary\Media $media The plugin.
	 */
	public function __construct( \Cloudinary\Media $media ) {
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
	 *
	 * @return int|false
	 */
	public function get_id_from_tag( $asset ) {
		$attachment_id = false;
		// Get attachment id from class name.
		if ( preg_match( '#class=["|\']?[^"\']*(wp-image-|wp-video-)([\d]+)[^"\']*["|\']?#i', $asset, $found ) ) {
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
	 * Filter out Cloudinary URL when saving to the DB.
	 *
	 * @param array $data The post data array to save.
	 *
	 * @return array
	 */
	public function filter_out_cloudinary( $data ) {

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
			$inherit_transformations = $this->media->get_transformation_from_meta( $attachment_id );
			$transformations         = $this->media->get_transformations_from_string( $url );
			$transformations         = array_filter( $transformations );
			if ( ! empty( $transformations ) && $inherit_transformations !== $transformations ) {
				$transformations = Api::generate_transformation_string( $transformations );
				$local_url       = add_query_arg( 'cld_params', $transformations, $local_url );
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
	 *
	 * @return string The filtered content.
	 */
	public function filter_out_local( $content ) {
		$assets = $this->get_media_tags( $content, 'img' );
		foreach ( $assets as $asset ) {

			$url           = $this->get_url_from_tag( $asset );
			$attachment_id = $this->get_id_from_tag( $asset );

			// Check if this is not already a cloudinary url.
			if ( $this->media->is_cloudinary_url( $url ) ) {
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
			$wp_size = $this->get_size_from_image_tag( $asset );
			if ( false === $wp_size ) {
				// No class name, so get size from the width and height tags.
				$wp_size = $this->get_crop_from_image_tag( $asset );
				if ( empty( $wp_size ) ) {
					$wp_size = 'full'; // Fallback to full if nothing is found at all.
				}
			}

			// Get a cloudinary URL.
			$clean                     = ! is_admin(); // Front facing images must not contain a wpsize url variable.
			$classes                   = $this->get_classes( $asset ); // check if this is a transformation overwrite.
			$overwrite_transformations = false;
			if ( false !== strpos( $classes, 'cld-overwrite' ) ) {
				$overwrite_transformations = true;
			}
			$cloudinary_url = $this->media->cloudinary_url( $attachment_id, $wp_size, $transformations, null, $overwrite_transformations, $clean );

			if ( $url === $cloudinary_url ) {
				continue;
			}

			// Replace old tag.
			$new_tag = str_replace( $url, $cloudinary_url, $asset );

			// Check if there is a class set. ( for srcset images in case of a manual url added ).
			if ( false === strpos( $new_tag, ' class=' ) && ! is_admin() ) {
				// Add in the class name.
				$new_tag = str_replace( '/>', ' class="wp-image-' . $attachment_id . '"/>', $new_tag );
			}

			// Apply lazy loading attribute
			if (
				apply_filters( 'wp_lazy_loading_enabled', true ) &&
				false === strpos( $new_tag, 'loading="lazy"' ) &&
				$clean
			) {
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

			$content = str_replace( $asset, $new_tag, $content );
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
	 *
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
	 * @param \WP_REST_Response $attachment The attachment array to be used in JS.
	 *
	 * @return \WP_REST_Response
	 * @uses filter:rest_prepare_attachment
	 *
	 */
	public function filter_attachment_for_rest( $attachment ) {
		if ( ! isset( $attachment->data['id'] ) ) {
			return $attachment;
		}

		$cloudinary_id = $this->media->cloudinary_id( $attachment->data['id'] );

		if ( $cloudinary_id ) {
			$attachment->data['source_url'] = $this->media->cloudinary_url( $attachment->data['id'], false );
		}

		if ( $has_transformations = ! empty( $this->media->get_transformation_from_meta( $attachment->data['id'] ) ) ) {
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
			$settings = $this->media->plugin->config['settings']['global_video_transformations'];
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
				$transformation_string = Api::generate_transformation_string( $attachment['transformations'] );
				$new_atts             .= ' transformations="' . esc_attr( $transformation_string ) . '"';
			}
			$html = str_replace( $shortcode['args'], $new_atts, $html );
		}

		return $html;
	}

	/**
	 * Filter out local urls in an 'edit' context rest request ( i.e for Gutenburg ).
	 *
	 * @param \WP_REST_Response $response The post data array to save.
	 * @param \WP_Post          $post     The current post.
	 * @param \WP_REST_Request  $request  The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function pre_filter_rest_content( $response, $post, $request ) {
		$context = $request->get_param( 'context' );
		if ( 'edit' === $context ) {
			$data                   = $response->get_data();
			$content                = $data['content']['raw'];
			$data['content']['raw'] = $this->filter_out_local( $content );

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
					<span>' . esc_html__( 'Overwrite Transformations', 'cloudinary' ) . '</span>
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
					<span>' . esc_html__( 'Overwrite Transformations', 'cloudinary' ) . '</span>
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
					' . esc_html__( 'Overwrite Transformations', 'cloudinary' ) . '
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
					' . esc_html__( 'Overwrite Transformations', 'cloudinary' ) . '
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
	 * Filter an image block to add the class for cld-overriding.
	 *
	 * @param array $block        The current block structure.
	 * @param array $source_block The source, unfiltered block structure.
	 *
	 * @return array
	 */
	public function filter_image_block_pre_render( $block, $source_block ) {

		if ( 'core/image' === $source_block['blockName'] ) {
			if ( ! empty( $source_block['attrs']['overwrite_transformations'] ) ) {
				foreach ( $block['innerContent'] as &$content ) {
					$content = str_replace( 'wp-image-' . $block['attrs']['id'], 'wp-image-' . $block['attrs']['id'] . ' cld-overwrite', $content );
				}
			}
		}

		return $block;
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {
		// Filter URLS within content.
		add_action( 'wp_insert_post_data', array( $this, 'filter_out_cloudinary' ) );
		add_filter( 'the_editor_content', array( $this, 'filter_out_local' ) );
		add_filter( 'the_content', array( $this, 'filter_out_local' ), 9 ); // Early to hook before responsive srcsets.
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'filter_attachment_for_js' ), 11 );

		// Add support for custom header.
		add_filter( 'get_header_image_tag', array( $this, 'filter_out_local' ) );

		// Add transformations.
		add_filter( 'media_send_to_editor', array( $this, 'transform_to_editor' ), 10, 3 );
		// Filter video codes.
		add_filter( 'media_send_to_editor', array( $this, 'filter_video_embeds' ), 10, 3 );

		// Gutenberg compatibility.
		add_filter( 'rest_prepare_attachment', array( $this, 'filter_attachment_for_rest' ) );
		$types  = get_post_types_by_support( 'editor' );
		$filter = $this;
		array_map(
			function ( $type ) use ( $filter ) {
				add_filter( 'rest_prepare_' . $type, array( $filter, 'pre_filter_rest_content' ), 10, 3 );
			},
			$types
		);

		// Remove editors to prevent users from manually editing images in WP.
		add_filter( 'wp_image_editors', array( $this, 'disable_editors_maybe' ) );

		// Add checkbox to media modal template.
		add_action( 'admin_footer', array( $this, 'catch_media_templates_maybe' ), 9 );

		// Filter for block rendering.
		add_filter( 'render_block_data', array( $this, 'filter_image_block_pre_render' ), 10, 2 );
	}
}
