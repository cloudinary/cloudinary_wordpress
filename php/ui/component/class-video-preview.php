<?php
/**
 * Video Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;

/**
 * Video preview component.
 *
 * @package Cloudinary\UI
 */
class Video_Preview extends Image_Preview {

	/**
	 * Preview type.
	 *
	 * @var string
	 */
	protected $preview_type = 'video';

	/**
	 * Holds the demo file name.
	 *
	 * @var string
	 */
	protected $demo_file = '/dog.mp4';

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'cld-player' );
		wp_enqueue_script( 'cld-player' );
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {
		$struct                           = parent::preview( $struct );
		$struct['element']                = 'video';
		$struct['attributes']['width']    = '427';
		$struct['attributes']['height']   = '240';
		$struct['attributes']['controls'] = 'controls';

		return $struct;
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		$url         = CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO;
		$preview_src = $url . 'w_600/';
		$sample      = '/dog.mp4';
		$script_data = array(
			'url'         => $url,
			'preview_url' => $preview_src,
			'file'        => $sample,
			'error'       => esc_html__( 'Invalid transformations or error loading preview.', 'cloudinary' ),
			'valid_types' => \Cloudinary\Connect\Api::$transformation_index['video'],
		);

		wp_add_inline_script( 'cloudinary', 'CLD_GLOBAL_TRANSFORMATIONS.video = ' . wp_json_encode( $script_data ), 'before' );

		$player   = array();
		$player[] = 'var cld = cloudinary.Cloudinary.new({ cloud_name: \'demo\' });';
		$player[] = 'var samplePlayer = cld.videoPlayer(\'sample-video\', { fluid : true } );';
		wp_add_inline_script( 'cld-player', implode( $player ) );
	}
}
