<?php
/**
 * Cloudinary settings test.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Plugin Exception class.
 */
class Test extends Settings_Component {

	/**
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {
		$args = array(
			'type'        => 'page',
			'menu_title'  => __( 'Settings test', 'cloudinary' ),
			'option_name' => 'test_settings',
			'priority'    => 20,
			'tabs'        => array(
				'fields'     => array(
					'type'       => 'page',
					'page_title' => __( 'Input Settings', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Settings with inputs', 'cloudinary' ),
						'icon'  => $this->plugin->dir_url . 'css/gallery.svg',
						array(
							'type'  => 'group',
							'title' => __( 'Checks and radios', 'cloudinary' ),
							array(
								'type'        => 'color',
								'title'       => __( 'Thing', 'cloudinary' ),
								'slug'        => 'sjkdhf',
								'description' => __( 'Dome', 'cloudinary' ),
							),
							array(
								'type'        => 'radio',
								'title'       => __( 'Radio Group', 'cloudinary' ),
								'slug'        => 'radio_test',
								'default'     => 'manual',
								'options'     => array(
									'auto'   => __( 'Auto Sync', 'cloudinary' ),
									'manual' => __( 'Manual Sync', 'cloudinary' ),
								),
								'description' => __( 'Radio options', 'cloudinary' ),
							),
							array(
								'type'        => 'checkbox',
								'title'       => __( 'Checkboxes', 'cloudinary' ),
								'slug'        => 'checkbox_group',
								'default'     => array(
									'one',
									'three',
									'five',
								),
								'options'     => array(
									'one'   => __( 'Option 1', 'cloudinary' ),
									'two'   => __( 'Option 2', 'cloudinary' ),
									'three' => __( 'Option 3', 'cloudinary' ),
									'four'  => __( 'Option 4', 'cloudinary' ),
									'five'  => __( 'Option 5', 'cloudinary' ),
								),
								'description' => __( 'Checkboxes options', 'cloudinary' ),
							),
							array(
								'type'        => 'checkbox',
								'title'       => __( 'Checkboxes Inline', 'cloudinary' ),
								'slug'        => 'checkbox_group_inline',
								'default'     => 'three',
								'inline'      => true,
								'options'     => array(
									'one'   => __( 'Option 1', 'cloudinary' ),
									'two'   => __( 'Option 2', 'cloudinary' ),
									'three' => __( 'Option 3', 'cloudinary' ),
								),
								'description' => __( 'Checkboxes with inline options', 'cloudinary' ),
							),
						),
						array(
							'type'        => 'group',
							'title'       => __( 'Toggles', 'cloudinary' ),
							'collapsible' => 'open',
							array(
								'type'         => 'on_off',
								'title'        => __( 'Toggle - default off', 'cloudinary' ),
								'tooltip_text' => __( 'Hello', 'cloudinary' ),
								'slug'         => 'toggle_off',
								'description'  => __( 'Toggle with default off', 'cloudinary' ),
							),
							array(
								'type'        => 'on_off',
								'title'       => __( 'Toggle - default on', 'cloudinary' ),
								'slug'        => 'toggle_on',
								'description' => __( 'Toggle with default on', 'cloudinary' ),
								'default'     => true,
							),
						),
						array(
							'type'      => 'group',
							'title'     => __( 'Text', 'cloudinary' ),
							'condition' => array(
								'toggle_on' => true,
							),
							array(
								'type'        => 'text',
								'title'       => __( 'Single text field', 'cloudinary' ),
								'slug'        => 'text_standard',
								'description' => __( 'Standard text field.', 'cloudinary' ),
							),
							array(
								'type'        => 'text',
								'title'       => __( 'Setting the text type', 'cloudinary' ),
								'slug'        => 'text_standard_type',
								'description' => __( 'Text field as date.', 'cloudinary' ),
								'attributes'  => array(
									'type' => 'date',
								),
							),
							array(
								'type'        => 'number',
								'title'       => __( 'Number box', 'cloudinary' ),
								'slug'        => 'number_box',
								'description' => __( 'Input for capturing numbers.', 'cloudinary' ),
							),
							array(
								'type'        => 'textarea',
								'title'       => __( 'Text box', 'cloudinary' ),
								'slug'        => 'text_box',
								'description' => __( 'Text area for paragraph.', 'cloudinary' ),
							),
						),
					),
					array(
						'type' => 'submit',
					),
				),
				'contents'   => array(
					'type'       => 'page',
					'page_title' => __( 'Content Settings', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Settings with content', 'cloudinary' ),
						'icon'  => $this->plugin->dir_url . 'css/video.svg',
						array(
							'content' => $this->get_content(),
							'clear'   => '',
						),
					),
				),
				'col_test'   => array(
					'type'       => 'page',
					'page_title' => __( 'Columns', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Columns example', 'cloudinary' ),
						'icon'  => $this->plugin->dir_url . 'css/crop.svg',
						array(
							'type' => 'row',
							array(
								'type'  => 'column',
								'width' => '50%',
								array(
									'type'  => 'text',
									'title' => 'Firstname',
								),
							),
							array(
								'type'  => 'column',
								'width' => '50%',
								array(
									'type'  => 'text',
									'title' => 'last name',
								),
							),
						),
					),
					array(
						'type' => 'submit',
					),
				),
			),
		);

		return $args;
	}

	/**
	 * Get demo content for old settings.
	 *
	 * @return string
	 */
	protected function get_content() {
		ob_start();
		?>
		<h2>This is html</h2>
		<?php

		return ob_get_clean();
	}
}
