<?php
/**
 * Defines the settings structure for the sidebar.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\UI\Component\Opt_Level;

/**
 * Defines the settings structure for the main header.
 *
 * @package Cloudinary
 */

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Account status', 'cloudinary' ),
		'description' => function () {
			$plugin     = get_plugin_instance();
			$data       = $plugin->settings->get_value( 'last_usage' );
			$cloud_name = $plugin->components['connect']->get_cloud_name();

			ob_start();
			?>
				<?php echo esc_html( $data['plan'] ); ?>
				<br />
				<span class="cld-ui-description-cloudname cloudname">@<?php echo esc_html( $cloud_name ); ?></span>
			<?php
			return ob_get_clean();
		},
		'collapsible' => 'open',
		array(
			'type'        => 'line_stat',
			'title'       => __( 'Storage', 'cloudinary' ),
			'stat'        => 'storage',
			'format_size' => true,
		),
		array(
			'type'  => 'line_stat',
			'title' => __( 'Transformations', 'cloudinary' ),
			'stat'  => 'transformations',
		),
		array(
			'type'        => 'line_stat',
			'title'       => __( 'Bandwidth', 'cloudinary' ),
			'stat'        => 'bandwidth',
			'format_size' => true,
		),
		array(
			'type'       => 'tag',
			'element'    => 'a',
			'content'    => __( 'View my account status', 'cloudinary' ),
			'attributes' => array(
				'href'   => 'https://cloudinary.com/console',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
				'class'  => array(
					'cld-link-button',
				),
			),
		),
	),
	array(
		'type'        => 'panel',
		'title'       => __( 'Optimization level', 'cloudinary' ),
		'description' => function () {
			$instance   = get_plugin_instance()->settings->get_setting( 'sidebar.1.0' );
			$percentage = $instance->get_component()->calculate_percentage() . '%';

			/* translators: %s is the percentage optimized. */
			return sprintf( __( '%s Optimized', 'cloudinary' ), $percentage );
		},
		'collapsible' => 'closed',
		array(
			'type' => 'opt_level',
		),
	),
);

return apply_filters( 'cloudinary_admin_sidebar', $settings );
