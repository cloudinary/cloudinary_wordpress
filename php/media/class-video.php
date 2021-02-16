<?php
/**
 * Video class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Media;
use Cloudinary\Utils;

/**
 * Class Video.
 *
 * Handles video filtering.
 */
class Video {

	/**
	 * Holds the Media instance.
	 *
	 * @since   0.1
	 *
	 * @var     Media Instance of the plugin.
	 */
	private $media;

	/**
	 * Holds the video settings config.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	private $config;

	/**
	 * List of attachment ID's to enable.
	 *
	 * @var array
	 */
	private $attachments = array();

	/**
	 * Cloudinary Stable Player Version.
	 *
	 * @var string
	 */
	const PLAYER_VER = '1.5.1';

	/**
	 * Cloudinary Core Version.
	 *
	 * @var string
	 */
	const CORE_VER = '2.6.3';

	/**
	 * Meta key to store usable video transformations for an attachment.
	 *
	 * @var string
	 */
	const CLD_USABLE_TRANSFORMATIONS = '_cld_usable_transformations';

	/**
	 * Video constructor.
	 *
	 * @param Media $media The plugin.
	 */
	public function __construct( Media $media ) {
		$this->media  = $media;
		$this->config = $this->media->get_settings()->get_setting( 'video_settings' )->get_value();

		$this->setup_hooks();
	}

	/**
	 * Checks if the Cloudinary player is enabled.
	 *
	 * @return bool
	 */
	public function player_enabled() {
		return isset( $this->config['video_player'] ) && 'cld' === $this->config['video_player'] && ! is_admin();
	}

	/**
	 * Queue video tag for script init in footer.
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string       $url           The video URL.
	 * @param string|array $format        The video formats.
	 * @param array        $args          Args to be passed to video init.
	 *
	 * @return int
	 */
	private function queue_video_config( $attachment_id, $url, $format, $args = array() ) {

		if ( ! empty( $args['transformation'] ) && false === $this->validate_usable_transformations( $attachment_id, $args['transformation'] ) ) {
			unset( $args['transformation'] );
		}
		$this->attachments[] = array(
			'id'     => $attachment_id,
			'url'    => $url,
			'format' => $format,
			'args'   => $args,
		);

		return count( $this->attachments ) - 1;// Return the queue index.
	}

	/**
	 * Checks if the transformation is able to be applied to the video and removes it if not.
	 *
	 * @param int   $attachment_id   The attachment ID.
	 * @param array $transformations The transformations array.
	 *
	 * @return bool
	 */
	public function validate_usable_transformations( $attachment_id, $transformations ) {

		$key  = md5( wp_json_encode( $transformations ) );
		$keys = $this->media->get_post_meta( $attachment_id, self::CLD_USABLE_TRANSFORMATIONS, true );
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		// If the key is new and does not exists, check it against the server.
		if ( ! isset( $keys[ $key ] ) ) {
			$cloudinary_url = $this->media->cloudinary_url( $attachment_id );
			$response       = wp_remote_head( $cloudinary_url );
			$has_error      = wp_remote_retrieve_header( $response, 'x-cld-error' );
			if ( empty( $has_error ) ) {
				$keys[ $key ] = true;
			} else {
				$keys[ $key ] = false;

			}
			update_post_meta( $attachment_id, self::CLD_USABLE_TRANSFORMATIONS, $keys );
		}

		return $keys[ $key ];
	}

	/**
	 * Output and capture videos to be replaced with the Cloudinary Player.
	 *
	 * @param string $html Html code.
	 * @param array  $attr Array of attributes in shortcode.
	 *
	 * @return string
	 */
	public function filter_video_shortcode( $html, $attr ) {

		// Confirm we have an ID and it's synced.
		if ( empty( $attr['id'] ) || ! $this->media->has_public_id( $attr['id'] ) ) {
			return $html;
		}

		// If not CLD video init, return default.
		if ( ! $this->player_enabled() ) {
			if ( empty( $attr['cloudinary'] ) ) {
				$video                        = wp_get_attachment_metadata( $attr['id'] );
				$url                          = $this->media->cloudinary_url( $attr['id'] );
				$attr[ $video['fileformat'] ] = $url;
				$attr['cloudinary']           = true; // Flag Cloudinary to ensure we don't call it again.
				$html                         = wp_video_shortcode( $attr, $html );
			}

			return $html;
		}
		$attachment_id = $attr['id'];
		unset( $attr['id'] );
		unset( $attr['width'] );
		unset( $attr['height'] );

		$overwrite_transformations = ! empty( $attr['cldoverwrite'] );

		return $this->build_video_embed( $attachment_id, $attr, $overwrite_transformations );
	}

	/**
	 * Enqueue BLock Assets
	 */
	public function enqueue_block_assets() {
		wp_enqueue_script( 'cloudinary-block', $this->media->plugin->dir_url . 'js/block-editor.js', array(), $this->media->plugin->version, true );
		wp_add_inline_script( 'cloudinary-block', 'var CLD_VIDEO_PLAYER = ' . wp_json_encode( $this->config ), 'before' );
	}

	/**
	 * Filter a video block to add the class for cld-overriding.
	 *
	 * @param array $block        The current block structure.
	 * @param array $source_block The source, unfiltered block structure.
	 *
	 * @return array
	 */
	public function filter_video_block_pre_render( $block, $source_block ) {

		if ( 'core/video' === $source_block['blockName'] && ! empty( $source_block['attrs']['id'] ) && $this->media->has_public_id( $source_block['attrs']['id'] ) ) {
			$attachment_id             = $source_block['attrs']['id'];
			$overwrite_transformations = ! empty( $source_block['attrs']['overwrite_transformations'] );
			foreach ( $block['innerContent'] as &$content ) {
				$video_tags = $this->media->filter->get_media_tags( $content );
				$video_tag  = array_shift( $video_tags );
				$attributes = Utils::get_tag_attributes( $video_tag );
				if ( $this->player_enabled() ) {
					unset( $attributes['src'] );
					$content = $this->build_video_embed( $attachment_id, $attributes, $overwrite_transformations );
				} else {
					$url     = $this->media->cloudinary_url( $attachment_id );
					$content = str_replace( $attributes['src'], $url, $content );
				}
			}
		}

		return $block;
	}

	/**
	 * Build a new iframe embed for a video.
	 *
	 * @param int   $attachment_id             The attachment ID.
	 * @param array $attributes                Attributes to add to the embed.
	 * @param bool  $overwrite_transformations Flag to overwrite transformations.
	 *
	 * @return string|null
	 */
	protected function build_video_embed( $attachment_id, $attributes = array(), $overwrite_transformations = false ) {
		$public_id = $this->media->get_public_id( $attachment_id );
		$params    = array(
			'cloud_name' => $this->media->plugin->get_component( 'connect' )->get_cloud_name(),
			'public_id'  => $public_id,
			'controls'   => 'true',
			'fluid'      => 'true',
			'source'     => array(
				'transformation' => $this->media->get_transformations( $attachment_id, array(), $overwrite_transformations ),
			),
		);
		// Add cname if present.
		if ( ! empty( $this->media->credentials['cname'] ) ) {
			$params['cloudinary'] = array(
				'cname'       => $this->media->credentials['cname'],
				'private_cdn' => 'true',
			);
		}
		unset( $attributes['mp4'] );
		if ( isset( $attributes['poster'] ) ) {
			$poster_id = $this->media->get_public_id_from_url( $attributes['poster'] );
			if ( $poster_id ) {
				$params['poster'] = $poster_id;
			}
			unset( $attributes['poster'] );
		}
		$url = add_query_arg( $params, 'https://player.cloudinary.com/embed/' );
		$url = add_query_arg( $attributes, $url );

		$video    = wp_get_attachment_metadata( $attachment_id );
		$tag_args = array(
			'type'       => 'tag',
			'element'    => 'figure',
			'attributes' => array(
				'class' => array(
					'wp-block-embed',
					'is-type-video',
					'wp-embed-aspect-16-9',
					'wp-has-aspect-ratio',
				),
			),
			array(
				'type'       => 'tag',
				'element'    => 'div',
				'attributes' => array(
					'class' => array(
						'wp-block-embed__wrapper',
					),
				),
				array(
					'type'       => 'tag',
					'element'    => 'iframe',
					'attributes' => array(
						'src'         => $url,
						'width'       => $video['width'],
						'height'      => $video['height'],
						'allow'       => 'autoplay; fullscreen; encrypted-media; picture-in-picture',
						'allowfullscreen',
						'frameborder' => 0,
					),
				),
			),
		);

		$new_tag = $this->media->get_settings()->create_setting( $public_id, $tag_args );

		return $new_tag->get_component()->render();
	}

	/**
	 * Apply default video Quality and Format transformations.
	 *
	 * @param array $default The current default transformations.
	 *
	 * @return array
	 */
	public function default_video_transformations( $default ) {

		if ( 'on' === $this->config['video_limit_bitrate'] ) {
			$default['bit_rate'] = $this->config['video_bitrate'] . 'k';
		}
		if ( 'on' === $this->config['video_optimization'] ) {
			if ( 'auto' === $this->config['video_format'] ) {
				$default['fetch_format'] = 'auto';
			}
			if ( isset( $this->config['video_quality'] ) ) {
				$default['quality'] = 'none' !== $this->config['video_quality'] ? $this->config['video_quality'] : null;
			} else {
				$default['quality'] = 'auto';
			}
		}

		return $default;
	}

	/**
	 * Apply default video freeform transformations.
	 *
	 * @param array $default The current default transformations.
	 *
	 * @return array
	 */
	public function default_video_freeform_transformations( $default ) {
		if ( ! empty( $this->config['video_freeform'] ) ) {
			$default[] = trim( $this->config['video_freeform'] );
		}

		return $default;
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {
		add_filter( 'wp_video_shortcode_override', array( $this, 'filter_video_shortcode' ), 10, 2 );
		add_filter( 'cloudinary_default_qf_transformations_video', array( $this, 'default_video_transformations' ), 10 );
		add_filter( 'cloudinary_default_freeform_transformations_video', array( $this, 'default_video_freeform_transformations' ), 10 );
		if ( ! is_admin() ) {
			// Filter for block rendering.
			add_filter( 'render_block_data', array( $this, 'filter_video_block_pre_render' ), 10, 2 );
		}

		// Add inline scripts for gutenberg.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );
	}
}
