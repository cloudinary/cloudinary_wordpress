<?php
/**
 * Image Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;
use Cloudinary\UI\Component;

/**
 * Image preview component.
 *
 * @package Cloudinary\UI
 */
class Image_Preview extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|notice/|preview_frame|title/|preview/|refresh/|spinner/|/preview_frame|url_frame|url/|/url_frame|/wrap';


	/**
	 * Holds the demo file name.
	 *
	 * @var string
	 */
	protected $demo_file = '/sample.jpg';

	/**
	 * Preview type.
	 *
	 * @var string
	 */
	protected $preview_type = 'image';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	public $capture = true;

	/**
	 * Filter the notice parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function notice( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['id']    = 'cld-preview-error';
		$struct['attributes']['class'] = array(
			'settings-alert',
			'settings-alert-error',
		);
		$struct['attributes']['style'] = 'display: none; margin-bottom: 10px;';
		$struct['render']              = true;

		return $struct;
	}

	/**
	 * Filter the preview_frame parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview_frame( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = "cld-{$this->preview_type}-preview-wrapper";

		return $struct;
	}


	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {
		$struct['element']           = 'img';
		$struct['attributes']['id']  = "sample-{$this->preview_type}";
		$struct['attributes']['src'] = '#';
		$struct['render']            = true;

		return $struct;
	}

	/**
	 * Filter the refresh parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function refresh( $struct ) {
		$struct['element']             = 'button';
		$struct['attributes']['type']  = 'button';
		$struct['attributes']['id']    = "refresh-{$this->preview_type}-preview";
		$struct['attributes']['class'] = array(
			'button-primary',
			'global-transformations-button',
		);
		$struct['content']             = __( 'Refresh Preview', 'cloudinary' );

		return $struct;
	}

	/**
	 * Filter the refresh parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function spinner( $struct ) {

		$struct['element']             = 'span';
		$struct['attributes']['id']    = "{$this->preview_type}-loader";
		$struct['attributes']['class'] = array(
			'spinner',
			'global-transformations-spinner',
		);
		$struct['render']              = true;

		return $struct;
	}

	/**
	 * Filter the refresh parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function url_frame( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'global-transformations-url';

		return $struct;
	}

	/**
	 * Filter the url parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function url( $struct ) {
		$struct['element']              = 'a';
		$struct['attributes']['class']  = array(
			'global-transformations-url-link',
		);
		$struct['attributes']['href']   = '#';
		$struct['attributes']['target'] = '_blank';
		$struct['content']              = '../';

		// Cloud path.
		$cloud_path                        = $this->get_part( 'span' );
		$cloud_path['content']             = "{$this->preview_type}/upload";
		$cloud_path['attributes']['class'] = array(
			'global-transformations-url-resource',
		);

		// Transformations.
		$transform_path                        = $this->get_part( 'span' );
		$transform_path['content']             = '/';
		$transform_path['attributes']['id']    = "transformation-sample-{$this->preview_type}";
		$transform_path['attributes']['class'] = array(
			'global-transformations-url-transformation',
		);

		// File Part.
		$file_part                        = $this->get_part( 'span' );
		$file_part['content']             = esc_html( $this->demo_file );
		$file_part['attributes']['class'] = array(
			'global-transformations-url-file',
		);

		// Build parts.
		$struct['children']['cloud']     = $cloud_path;
		$struct['children']['transform'] = $transform_path;
		$struct['children']['file']      = $file_part;

		return $struct;
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		$url         = 'https://res.cloudinary.com/demo/image/upload/';
		$preview_src = $url . 'w_600/';
		$sample      = '/sample.jpg';
		$script_data = array(
			'url'         => $url,
			'preview_url' => $preview_src,
			'file'        => $sample,
			'error'       => esc_html__( 'Invalid transformations or error loading preview.', 'cloudinary' ),
			/* translators: %s is the transformation that breaks the preview. */
			'warning'     => esc_html__( 'Preview cannot be generated because %s transformation was used.', 'cloudinary' ),
			'valid_types' => \Cloudinary\Connect\Api::$transformation_index['image'],
		);
		wp_add_inline_script( 'cloudinary', 'var CLD_GLOBAL_TRANSFORMATIONS = CLD_GLOBAL_TRANSFORMATIONS ? CLD_GLOBAL_TRANSFORMATIONS : {};', 'before' );
		wp_add_inline_script( 'cloudinary', 'CLD_GLOBAL_TRANSFORMATIONS.image = ' . wp_json_encode( $script_data ), 'before' );
		parent::pre_render();
	}
}
