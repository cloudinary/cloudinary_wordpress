<?php
/**
 * Asset Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Asset_Preview extends Asset {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'return_link/|preview/|edit|label|transformation/|/label|save/|/edit';
//	protected $blueprint = 'return_link/|image_preview/|preview/|edit|label|transformation/|/label|/edit|crop_sizes/|save/';

	protected $asset;

	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {

		$this->asset = (int) filter_input( INPUT_GET, 'asset', FILTER_SANITIZE_NUMBER_INT );
		$dataset     = $this->assets->get_asset( $this->asset, 'dataset' );

		$struct['element']                 = 'div';
		$struct['attributes']['id']        = 'cld-asset-edit';
		$struct['attributes']['data-item'] = $dataset;
		$struct['render']                  = true;

		return $struct;
	}

	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function edit( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-asset-edit',
		);

		return $struct;
	}

	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function label( $struct ) {

		$struct['element']             = 'label';
		$struct['content']             = $this->setting->get_param( 'label', __( 'Transformations', 'cloudinary' ) );
		$struct['attributes']['class'] = array(
			'cld-asset-preview-label',
		);

		return $struct;
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function transformation( $struct ) {

		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'text';
		$struct['attributes']['id']    = 'cld-asset-edit-transformations';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'regular-text',
			'cld-asset-preview-input',
		);

		return $struct;
	}

	protected function crop_sizes( $struct ) {
//		$meta             = wp_get_attachment_metadata( $this->asset );
//		$registered_sizes = Utils::get_registered_sizes();
//		$sizes            = array();
//

//		$registered_sizes = Utils::get_registered_sizes();
//		$sizes            = array();
//
//		foreach ( $registered_sizes as $key => $size ) {
//			$transformation = $size['crop'] ? 'c_fill' : 'c_scale';
//			$sizes[]        = array(
//				'type'        => 'on_off',
//				'slug'        => 'asset_disable_size_' . $key,
//				'title'       => $size['label'],
//				'description' => sprintf(
//					// translators: %s is the size.
//					__( 'Disable size cropping for %s.', 'cloudinary' ),
//					$size['label']
//				),
//				'default'     => 'off',
//				'attributes'  => array(
//					'data-context' => 'image',
//				)
//			);
//			$sizes[]        = array(
//				'type'      => 'group',
//				array(
//					'type'         => 'text',
//					'slug'         => 'size_' . $key,
//					'condition' => array(
//						'asset_disable_size_' . $key => false,
//					),
//					'tooltip_text' => sprintf(
//						// translators: %s is the size.
//						__( 'Custom cropping for %s.', 'cloudinary' ),
//						$size['label']
//					),
//					'attributes'   => array(
//						'placeholder' => $transformation,
//					),
//				),
//			);
//		}
//
//		$args     = array(
//			'storage'  => 'Post_Meta',
//			'settings' => array(
//				array(
//					'type'     => 'page',
//					'title'    => __( 'Sizes', 'cloudinary' ),
//					'settings' => array(
//						array(
//							'type'         => 'on_off',
//							'slug'         => 'asset_sized_transformations',
//							'title'        => __( 'Sized transformations', 'cloudinary' ),
//							'tooltip_text' => __(
//								'Enable transformations per registered image sizes.',
//								'cloudinary'
//							),
//							'description'  => __( 'Enable sized transformations.', 'cloudinary' ),
//							'default'      => 'off',
//						),
//						array(
//							'type'        => 'group',
//							'title'       => __( 'Sizes', 'cloudinary' ),
//							'collapsible' => true,
//							'condition'   => array(
//								'asset_sized_transformations' => true,
//							),
//							'settings' => $sizes,
//						),
//					)
//				),
//			)
//		);
//
//		$plugin = get_plugin_instance();
//		$set = $plugin->get_component( 'admin' )->init_components( $args, 'asset_size_transformations' );
//		$settings = $set->get_settings();
//		$settings = reset( $settings );
//		$struct['content'] = $settings->get_component()->render();
//
//
//		$struct['render'] = true;

		return $struct;
	}

	protected function build_crop_preview( $slug ) {
		$sizes = Utils::get_registered_sizes( $this->asset );
		$label = array(
			'element'    => 'label',
			'content'    => sprintf(
				__( 'Transformations for %s', 'cloudinary' ),
				$sizes[ $slug ]['label']
			),
			'attributes' => array(
				'for'   => 'cld-size-transformations-' . $slug,
				'class' => array(
					'cld-asset-preview-label',
				)
			)
		);
		var_dump( $this->setting->get_slug() );
		$toggle = new On_Off( $this->setting );

		//		$toggle = array(
////			'element'    => 'on_off',
//			'attributes' => array(
//				'type'  => 'text',
//				'id'    => 'cld-disable-transformations-' . $slug,
//				'class' => array(
//					'regular-text',
//				)
//			)
//		);
//		var_dump( $toggle);

		$input                           = array(
			'element'    => 'input',
			'attributes' => array(
				'type'  => 'text',
				'id'    => 'cld-size-transformations-' . $slug,
				'class' => array(
					'regular-text',
				)
			)
		);
		$struct['children'][]            = $label;
		$struct['children'][]['content'] = $toggle->render();
		$struct['children'][]            = $input;
		$struct['element']               = 'div';

		return $struct;
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function save( $struct ) {

		$struct['element']            = 'button';
		$struct['attributes']['type'] = 'button';
		$struct['attributes']['id']   = 'cld-asset-edit-save';

		$struct['render']              = true;
		$struct['content']             = $this->setting->get_param( 'save', __( 'Save', 'cloudinary' ) );
		$struct['attributes']['class'] = array(
			'button',
			'button-primary',
			'cld-asset-edit-button',
		);

		return $struct;
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'cloudinary-asset-edit', $plugin->dir_url . 'js/asset-edit.js', array(), $plugin->version, true );
	}

}
