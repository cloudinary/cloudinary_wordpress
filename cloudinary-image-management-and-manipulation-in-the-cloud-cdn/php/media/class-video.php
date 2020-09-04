<?php
/**
 * Video class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

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
	 * @var     \Cloudinary\Media Instance of the plugin.
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
	 * Determines if the video player is active.
	 *
	 * @var bool
	 */
	private $player_enabled = false;


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
	const PLAYER_VER = '1.4.0';

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
	 * @param \Cloudinary\Media $media The plugin.
	 */
	public function __construct( \Cloudinary\Media $media ) {
		$this->media  = $media;
		$this->config = $this->media->plugin->config['settings']['global_video_transformations'];

		$this->setup_hooks();
	}

	/**
	 * Checks if the Cloudinary player is enabled.
	 *
	 * @return bool
	 */
	public function player_enabled() {
		return $this->player_enabled;
	}

	/**
	 * $this->>register_scripts();
	 * Initialises the Cloudinary player if it's enabled and if video content is found.
	 */
	public function init_player() {

		if ( 'cld' === $this->config['video_player'] && ! is_admin() ) {
			// Check content has a video to enqueue assets in correct location.
			while ( have_posts() ) {
				the_post();
				$has_video  = $this->media->filter->get_video_shortcodes( get_the_content() );
				$video_tags = $this->media->filter->get_media_tags( get_the_content(), 'video' );
				if ( ! empty( $has_video ) || ! empty( $video_tags ) ) {
					// Setup initial scripts.
					wp_enqueue_style( 'cld-player' );
					wp_enqueue_style( 'cld-player-local', $this->media->plugin->dir_url . 'css/video.css', null, self::PLAYER_VER );
					wp_enqueue_script( 'cld-player' );

					// Init cld script object.
					$cld = array(
						'cloud_name' => $this->media->credentials['cloud_name'],
					);
					if ( ! empty( $this->media->credentials['cname'] ) ) {
						$cld['cname']       = $this->media->credentials['cname'];
						$cld['private_cdn'] = true;
					}
					$code = 'var cld = cloudinary.Cloudinary.new(' . wp_json_encode( $cld ) . ');';
					wp_add_inline_script( 'cld-player', $code );

					// Enable video for output.
					$this->player_enabled = true;
					break; // Exit since we determined that a video is present.
				}
			}
			// Reset wp_query.
			rewind_posts();
		}
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

		// If not CLD video init, return default.
		if ( false === $this->player_enabled ) {
			return $html;
		};
		// Check for override flag.
		$overwrite_transformations = false;
		if ( ! empty( $attr['cldoverwrite'] ) ) {
			$overwrite_transformations = true;
		}
		// Check for a cloudinary url, or prep sync if not found.
		$cloudinary_url = $this->media->cloudinary_url( $attr['id'], false, false, null, $overwrite_transformations );
		if ( ! $this->media->plugin->components['sync']->is_synced( $attr['id'] ) ) {
			// If the asset is not synced, then the metadata will not be complete since v1 didn't save any.
			// Return html for now since cloudinary_url will queue it up for syncing in the background.
			return $html;
		}

		// Queue video.
		$video           = wp_get_attachment_metadata( $attr['id'] );
		$transformations = $this->media->get_transformations_from_string( $cloudinary_url, 'video' );
		$args            = array();

		if ( isset( $attr['autoplay'] ) ) {
			$args['autoplay'] = 'true' === $attr['autoplay'];
			$args['muted'] = 'true' === $attr['autoplay'];
		}
		if ( isset( $attr['loop'] ) ) {
			$args['loop'] = 'true' === $attr['loop'];
		}
		// Transformations.
		if ( ! empty( $transformations ) ) {
			$args['transformation'] = $transformations;
		}
		$args['overwrite_transformations'] = $overwrite_transformations;
		// Size settings.
		$size = '';
		if ( ! empty( $attr['width'] ) ) {
			$size        .= ' width="' . esc_attr( $attr['width'] ) . '"';
			$args['size'] = true;
		}
		if ( ! empty( $attr['height'] ) ) {
			$size        .= ' height="' . esc_attr( $attr['height'] ) . '"';
			$args['size'] = true;
		}
		$instance = $this->queue_video_config( $attr['id'], $attr[ $video['fileformat'] ], $video['fileformat'], $args );

		// Replace with video tag.
		return '<video class="cld-fluid" id="cloudinary-video-' . esc_attr( $instance ) . '"' . $size . '></video>';
	}

	/**
	 * Filter video tags and queue them for the player.
	 *
	 * @param string $content HTML content of the post.
	 *
	 * @return mixed
	 */
	public function filter_video_tags( $content ) {

		$video_tags = $this->media->filter->get_media_tags( $content, 'video' );
		foreach ( $video_tags as $tag ) {
			$args = array();

			// Catch poster.
			$poster_url = $this->media->filter->get_poster_from_tag( $tag );
			if ( false !== $poster_url ) {

				$poster_id             = $this->media->get_id_from_url( $poster_url );
				$cloudinary_id         = $this->media->cloudinary_id( $poster_id );
				$cloudinary_url        = $this->media->cloudinary_url( $poster_id );
				$transformations       = $this->media->get_transformations_from_string( $cloudinary_url );
				$args['posterOptions'] = array(
					'publicId' => $cloudinary_id,
				);
				if ( ! empty( $transformations ) ) {
					$args['posterOptions']['transformation'] = $transformations;
				}
			}

			$url = $this->media->filter->get_url_from_tag( $tag );
			if ( false === $url ) {
				continue;
			}
			$attachment_id = $this->media->filter->get_id_from_tag( $tag );
			if ( empty( $attachment_id ) ) {
				continue; // Missing or no attachment ID found.
			}
			// Enable Autoplay for this video.
			if ( false !== strpos( $tag, 'autoplay' ) ) {
				$args['autoplayMode'] = $this->config['video_autoplay_mode']; // if on, use defined mode.
				$args['muted'] = 'always' === $this->config['video_autoplay_mode'];
			}
			// Enable Loop.
			if ( false !== strpos( $tag, 'loop' ) ) {
				$args['loop'] = true;
			}
			// If there is no controls, it has been turned off.
			if ( false !== strpos( $tag, 'controls' ) ) {
				$args['controls'] = true;
			}
			// If there is a muted, it has been turned on.
			if ( false !== strpos( $tag, 'muted' ) ) {
				$args['muted'] = true;
			}
			// If preload.
			if ( preg_match( '/preload=\"([^\"]*)\"/i', $tag, $found ) ) {
				$args['preload'] = $found[1];
			}
			// Add transformations if found.
			$classes                   = $this->media->filter->get_classes( $tag ); // check if this is a transformation overwrite.
			$overwrite_transformations = false;
			if ( false !== strpos( $classes, 'cld-overwrite' ) ) {
				$overwrite_transformations = true;
			}
			$args['overwrite_transformations'] = $overwrite_transformations;

			$cloudinary_url  = $this->media->cloudinary_url( $attachment_id, false, false, null, $overwrite_transformations );
			// Bail replacing the video URL for cases where it doesn't exist.
			// Cases are, for instance, when the file size is larger than the API limits — free accounts.
			if ( ! empty( $cloudinary_url ) ) {
				$transformations = $this->media->get_transformations_from_string( $cloudinary_url, 'video' );
				if ( ! empty( $transformations ) ) {
					$args['transformation'] = $transformations;
				}
				$video = wp_get_attachment_metadata( $attachment_id );
				if ( $this->player_enabled() ) {
					$instance = $this->queue_video_config( $attachment_id, $url, $video['fileformat'], $args );
					// Remove src and replace with an ID.
					$new_tag = str_replace( 'src="' . $url . '"', 'id="cloudinary-video-' . esc_attr( $instance ) . '"', $tag );
					$content = str_replace( $tag, $new_tag, $content );
				} else {
					// Just replace URL.
					$content = str_replace( $url, $cloudinary_url, $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Output init scripts in footer for videos.
	 */
	public function print_video_scripts() {

		if ( $this->player_enabled() && ! empty( $this->attachments ) ) {

			$cld_videos = array();
			foreach ( $this->attachments as $instance => $video ) {
				// @todo - ping the URL to ensure it has transformation available, else update an eager.
				$cloudinary_id = $this->media->get_public_id( $video['id'] );
				$default       = array(
					'publicId'    => $cloudinary_id,
					'sourceTypes' => array( $video['format'] ), // @todo Make this based on eager items as mentioned above.
					'autoplay'    => 'off' !== $this->config['video_autoplay_mode'] ? true : false,
					'loop'        => 'on' === $this->config['video_loop'] ? true : false,
				);

				$valid_autoplay_modes = [ 'never', 'always', 'on-scroll' ];
				if ( $default['autoplay'] && in_array( $this->config['video_autoplay_mode'], $valid_autoplay_modes, true ) ) {
					$default['autoplayMode'] = $this->config['video_autoplay_mode'];
				}

				$config = wp_parse_args( $video['args'], $default );

				if ( empty( $config['size'] ) && ! empty( $config['transformation'] ) && ! $this->media->get_crop_from_transformation( $config['transformation'] ) ) {
					$config['fluid'] = true;
				}
				
				$config['controls'] = 'on' === $this->config['video_controls'] ? true : false;
				$cld_videos[ $instance ] = $config;
			}

			if ( empty( $cld_videos ) ) {
				return;
			}

			ob_start();
			?>
			var cldVideos = <?php echo wp_json_encode( $cld_videos ); ?>;

			for ( var videoInstance in cldVideos ) {
				var cldConfig = cldVideos[ videoInstance ];
				var cldId = 'cloudinary-video-' + videoInstance;
				cld.videoPlayer( cldId, cldConfig );
			}

			window.addEventListener( 'load', function() {
				for ( var videoInstance in cldVideos ) {
					var cldId = 'cloudinary-video-' + videoInstance;
					var videoContainer = document.getElementById( cldId );
					var videoElement = videoContainer.getElementsByTagName( 'video' );

					if ( videoElement.length === 1 ) {
						videoElement = videoElement[0];
						videoElement.style.width = '100%';
						<?php if ( $this->config['video_freeform'] ): ?>

						if ( 
							videoElement.src.indexOf( '<?php echo esc_js( $this->config['video_freeform'] ) ?>' ) === -1 &&
							! cldVideos[videoInstance]['overwrite_transformations']
						) {
							videoElement.src = videoElement.src.replace(
								'upload/',
								'upload/<?php echo esc_js( $this->config['video_freeform'] ) ?>/'
							);
						}
						<?php endif ?>

					}
				}
			} );
			<?php
			$script = ob_get_clean();

			wp_add_inline_script(
				'cld-player',
				$script
			);
		}
	}

	/**
	 * Enqueue BLock Assets
	 */
	public function enqueue_block_assets() {
		wp_enqueue_script( 'cloudinary-block', $this->media->plugin->dir_url . 'js/block-editor.js', null, $this->media->plugin->version, true );
		wp_add_inline_script( 'cloudinary-block', 'var CLD_VIDEO_PLAYER = ' . wp_json_encode( $this->config ), 'before' );
	}

	/**
	 * Register assets for the player.
	 */
	public static function register_scripts_styles() {
		wp_register_style( 'cld-player', 'https://unpkg.com/cloudinary-video-player@' . self::PLAYER_VER . '/dist/cld-video-player.min.css', null, self::PLAYER_VER );
		wp_register_script( 'cld-core', 'https://unpkg.com/cloudinary-core@' . self::CORE_VER . '/cloudinary-core-shrinkwrap.min.js', null, self::CORE_VER, true );
		wp_register_script( 'cld-player', 'https://unpkg.com/cloudinary-video-player@' . self::PLAYER_VER . '/dist/cld-video-player.min.js', array( 'cld-core' ), self::PLAYER_VER, true );
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

		if ( 'core/video' === $source_block['blockName'] ) {
			$classes = 'cld-fluid';
			if ( ! empty( $source_block['attrs']['overwrite_transformations'] ) ) {
				$classes .= ' cld-overwrite';
			}
			if ( ! empty( $source_block['attrs']['id'] ) ) {
				$classes .= ' wp-video-' . $source_block['attrs']['id'];
			}
			foreach ( $block['innerContent'] as &$content ) {

				$video_tags = $this->media->filter->get_media_tags( $content );
				foreach ( $video_tags as $tag ) {
					if ( false !== strpos( $tag, 'class="' ) ) {
						$content = str_replace( 'class="', 'class="' . $classes . ' ', $content );
					} else {
						$content = str_replace( '<video ', '<video class="' . $classes . '" ', $content );
					}

				}
			}
		}

		return $block;
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {
		add_filter( 'wp_video_shortcode_override', array( $this, 'filter_video_shortcode' ), 10, 2 );

		if ( ! is_admin() ) {
			add_filter( 'the_content', array( $this, 'filter_video_tags' ) );
			// Filter for block rendering.
			add_filter( 'render_block_data', array( $this, 'filter_video_block_pre_render' ), 10, 2 );
		}
		
		add_action( 'wp_print_styles', array( $this, 'init_player' ) );
		add_action( 'wp_footer', array( $this, 'print_video_scripts' ) );

		// Add inline scripts for gutenberg.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );
		$this->register_scripts_styles();
	}
}
