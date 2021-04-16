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
		$this->config = $this->media->get_settings()->get_value( 'video_settings' );

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
		unset( $attr['id'], $attr['width'], $attr['height'], $attr['controls'] );

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
	 * Register assets for the player for preview.
	 */
	public function admin_enqueue_scripts() {
		$current_screen    = get_current_screen();
		$requiring_screens = array(
			'cloudinary_page_media',
			'edit-tags',
			'term',
		);

		if ( null === $current_screen ) {
			return;
		}

		if ( in_array( $current_screen->base, $requiring_screens, true ) ) {
			wp_register_style( 'cld-player', 'https://unpkg.com/cloudinary-video-player@' . self::PLAYER_VER . '/dist/cld-video-player.min.css', null, self::PLAYER_VER );
			wp_register_script( 'cld-core', 'https://unpkg.com/cloudinary-core@' . self::CORE_VER . '/cloudinary-core-shrinkwrap.min.js', null, self::CORE_VER, true );
			wp_register_script( 'cld-player', 'https://unpkg.com/cloudinary-video-player@' . self::PLAYER_VER . '/dist/cld-video-player.min.js', array( 'cld-core' ), self::PLAYER_VER, true );
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
	public function filter_video_block_render_block( $block_content, array $block ) {
		if ( 'core/video' === $block['blockName'] ) {
			remove_filter( 'render_block', array( $this, 'filter_video_block_render_block' ), 10, 2 );

			$filtered_block = $this->filter_video_block_pre_render( $block, $block );
			$block_content  = render_block( $filtered_block );

			add_filter( 'render_block', array( $this, 'filter_video_block_render_block' ), 10, 2 );
		}

		return $block_content;
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
					unset( $attributes['src'], $attributes['controls'] );
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
		$controls  = $this->media->get_settings()->get_value( 'video_controls' );
		$autoplay  = $this->media->get_settings()->get_value( 'video_autoplay_mode' );

		// If we don't show controls, we need to autoplay the video.
		if ( 'off' === $controls ) {
			$autoplay = 'on-scroll';
		}

		// Setup the base params.
		$params = array(
			'public_id'  => $public_id,
			'cloud_name' => $this->media->plugin->get_component( 'connect' )->get_cloud_name(),
			'player'     => array(
				'fluid'    => 'true',
				'controls' => 'on' === $controls ? 'true' : 'false',
			),
			'source'     => array(
				'source_types' => array(),
			),
		);
		// Check for transformations.
		$transformations = $this->media->get_transformations( $attachment_id, array(), $overwrite_transformations );
		if ( ! empty( $transformations ) ) {
			$params['source']['transformation'] = $transformations;
		}
		// Set the source_type.
		$video = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $video['fileformat'] ) ) {
			$params['source']['source_types'][] = $video['fileformat'];
			unset( $attributes[ $video['fileformat'] ] );
		}
		// Add cname if present.
		if ( ! empty( $this->media->credentials['cname'] ) ) {
			$params['cloudinary'] = array(
				'cname'       => $this->media->credentials['cname'],
				'private_cdn' => 'true',
			);
		}
		// Set the autoplay.
		// Some browsers require Autoplay to be muted — https://developers.google.com/web/updates/2016/07/autoplay.
		switch ( $autoplay ) {
			case 'always':
				$params['player']['muted']    = 'true';
				$params['player']['autoplay'] = 'true';
				break;
			case 'on-scroll':
				$params['player']['muted']         = 'true';
				$params['player']['autoplay_mode'] = 'on-scroll';
				break;
			default:
		}

		// Set the poster.
		if ( isset( $attributes['poster'] ) ) {
			$poster_id = $this->media->get_public_id_from_url( $attributes['poster'] );
			if ( $poster_id ) {
				$params['source']['poster'] = $poster_id;
			}
			unset( $attributes['poster'] );
		}
		// Add the player version to use.
		$params['vpv'] = self::PLAYER_VER;
		// Build URL.
		$params['player'] = wp_parse_args( $attributes, $params['player'] );
		$url              = add_query_arg( $params, 'https://player.cloudinary.com/embed/' );

		// Build the Player HTML.
		$tag_args = array(
			'type'       => 'tag',
			'element'    => 'figure',
			'attributes' => array(
				'class' => $this->get_video_classes( $video ),
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
						'src'             => $url,
						'width'           => $video['width'],
						'height'          => $video['height'],
						'allow'           => 'autoplay; fullscreen; encrypted-media; picture-in-picture',
						'allowfullscreen' => true,
						'frameborder'     => 0,
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
	 * Get the video classes.
	 * Try to find a match with WordPress aspect ratio.
	 *
	 * @param array $video The video metadata array.
	 *
	 * @return array
	 */
	protected function get_video_classes( $video ) {
		$classes = array(
			'wp-block-embed',
			'is-type-video',
		);

		$sizes = array(
			'wp-embed-aspect-21-9' => array(
				'width'  => 21,
				'height' => 9,
			),
			'wp-embed-aspect-18-9' => array(
				'width'  => 18,
				'height' => 9,
			),
			'wp-embed-aspect-16-9' => array(
				'width'  => 16,
				'height' => 9,
			),
			'wp-embed-aspect-4-3'  => array(
				'width'  => 4,
				'height' => 3,
			),
			'wp-embed-aspect-1-1'  => array(
				'width'  => 1,
				'height' => 1,
			),
			'wp-embed-aspect-9-16' => array(
				'width'  => 9,
				'height' => 16,
			),
			'wp-embed-aspect-1-2'  => array(
				'width'  => 1,
				'height' => 2,
			),
		);

		$extra = array();

		foreach ( $sizes as $size => $dimensions ) {
			if ( $video['width'] / $video['height'] === $dimensions['width'] / $dimensions['height'] ) {
				$extra = array(
					'wp-has-aspect-ratio',
					$size,
				);
				break;
			}
		}

		return array_merge( $classes, $extra );
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
			if ( has_filter( 'render_block_data' ) ) {
				add_filter( 'render_block_data', array( $this, 'filter_video_block_pre_render' ), 10, 2 );
			} else {
				// The render_block_data filter was only introduced on WP 5.1.0. This is the fallback for 5.0.*.
				add_filter( 'render_block', array( $this, 'filter_video_block_render_block' ), 10, 2 );
			}
		}

		// Add inline scripts for gutenberg.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 5 );
	}
}
