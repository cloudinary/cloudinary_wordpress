<?php
/**
 * Editor UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary;
use Cloudinary\Media;
use Cloudinary\REST_API;
use Cloudinary\Settings\Setting;
use Cloudinary\UI\Component;
use Cloudinary\Editor as MediaEditor;
use WP_Post;
use function Cloudinary\get_plugin_instance;

/**
 * Row Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Editor extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */

	protected $blueprint = 'wrap|preview/|edit_holder/|edit_control/|controls|closer/|restore/|save/|/controls|/wrap';

	/**
	 * Holds the current attachment.
	 *
	 * @var WP_Post
	 */
	protected $attachment;

	/**
	 * Holds the media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Render component for a setting.
	 * Component constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$this->attachment = $setting->get_param( 'attachment' );
		$this->media      = get_plugin_instance()->get_component( 'media' );
		parent::__construct( $setting );
	}

	/**
	 * Filter the wrap part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {
		$struct['attributes']['id'] = 'cloudinary-media-editor';
		$struct['attributes']['style'] = 'margin-top:10px;border:solid 1px; border-color: rgb(218, 225, 233);background-color:#fff;';

		return $struct;
	}

	/**
	 * Filter the preview part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {

		$attachment_id              = $this->attachment->ID;
		$src                        = wp_get_attachment_url( $attachment_id );
		$struct['render']           = true;
		$struct['element']          = 'div';
		$struct['attributes']['id'] = 'cloudinary-media-preview';
		$type                       = $this->media->get_resource_type( $attachment_id );
		if ( 'image' === $type ) {
			$struct['element']           = 'img';
			$struct['attributes']['src'] = $src;
		} elseif ( 'video' === $type ) {

			$meta              = wp_get_attachment_metadata( $attachment_id );
			$atts              = array(
				'width'             => $meta['width'],
				'height'            => $meta['height'],
				$meta['fileformat'] => $src,
				'id'                => $attachment_id,
				'controls'          => true,
			);
			$params            = self::build_attributes( $atts );
			$struct['content'] = do_shortcode( "[video {$params}][/video]" );
		}
		$struct['attributes']['style'] = 'max-width:100%';

		return $struct;
	}

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function edit_holder( $struct ) {
		$struct['render']              = true;
		$struct['element']             = 'div';
		$struct['attributes']['id']    = 'cloudinary-editor';
		$struct['attributes']['style'] = 'display:none;';

		return $struct;
	}

	/**
	 * Filter the edit control part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function edit_control( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['id']    = 'edit-start-wrap';
		$struct['attributes']['style'] = 'padding:6px 6px 8px 6px;';
		$struct['attributes']['class'] = array(
			'cld-row',
		);

		// Button.
		$button                        = $this->get_part( 'button' );
		$button['attributes']['type']  = 'button';
		$button['attributes']['id']    = 'edit-start';
		$button['attributes']['style'] = 'display:none;';
		$button['attributes']['class'] = array(
			'button',
		);

		$type = strstr( $this->attachment->post_mime_type, '/', true );
		// Translators: placeholder is resource type.
		$default_label     = sprintf( __( 'Edit %s', 'cloudinary' ), $type );
		$button['content'] = $this->setting->get_param( 'edit_label', $default_label );

		$struct['children']['edit'] = $button;

		return $struct;
	}

	/**
	 * Filter the controls part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function controls( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['id']    = 'edit-controls-wrap';
		$struct['attributes']['class'] = array(
			'cld-row',
		);
		$struct['attributes']['style'] = 'justify-content: space-between;display:none;padding:6px 6px 8px 6px;';

		return $struct;
	}

	/**
	 * Filter the closer part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function closer( $struct ) {
		$struct['element']             = 'button';
		$struct['attributes']['type']  = 'button';
		$struct['attributes']['id']    = 'edit-close';
		$struct['attributes']['class'] = array(
			'button',
		);

		$struct['content'] = __( 'Close', 'cloudinary' );

		return $struct;
	}

	/**
	 * Filter the save part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function save( $struct ) {
		$struct['element']             = 'button';
		$struct['attributes']['type']  = 'button';
		$struct['attributes']['id']    = 'edit-save';
		$struct['attributes']['class'] = array(
			'button',
		);

		$struct['content'] = __( 'Save changes', 'cloudinary' );

		return $struct;
	}

	/**
	 * Filter the save part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function restore( $struct ) {
		$struct['element']             = 'button';
		$struct['attributes']['type']  = 'button';
		$struct['attributes']['id']    = 'edit-restore';
		$struct['attributes']['style'] = 'display:none;';
		$struct['attributes']['class'] = array(
			'button',
		);
		$type                          = strstr( $this->attachment->post_mime_type, '/', true );
		// Translators: placeholder is resource type.
		$default_label     = sprintf( __( 'Restore %s', 'cloudinary' ), $type );
		$struct['content'] = $this->setting->get_param( 'restore_label', $default_label );

		return $struct;
	}

	/**
	 * Check if component is enabled.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return parent::is_enabled() && ! empty( $this->attachment );
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {

		$attachment_id = $this->attachment->ID;
		$asset = require $this->media->plugin->dir_path . 'js/image-editor.asset.php';

		wp_enqueue_script( 'image-editor', $this->media->plugin->dir_url . 'js/image-editor.js', $asset['dependencies'], $asset['version'], true );

		$data = array(
			'assetID'        => $attachment_id,
			'publicId'       => $this->media->get_public_id( $attachment_id ),
			'resourceType'   => $this->media->get_resource_type( $attachment_id ),
			'saveUrl'        => rest_url( REST_API::BASE . '/edit_asset' ),
			'restoreUrl'     => rest_url( REST_API::BASE . '/restore_asset' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'transformation' => $this->media->get_transformations( $attachment_id ),
			'cloudName'      => $this->media->credentials['cloud_name'],
			'original'       => MediaEditor::is_original( $attachment_id ),
		);

		$this->media->plugin->add_script_data( 'editor', $data );
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		parent::pre_render();
		?>
		<style>
			.wp_attachment_holder{
				display : none;
			}
		</style>
		<?php

	}
}
