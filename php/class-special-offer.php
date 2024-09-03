<?php
/**
 * Special Offer Class.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Class Special_Offer
 */
class Special_Offer {
	/**
	 * The plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Special_Offer constructor.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->register_hooks();
	}

	/**
	 * Register hooks for the Special Offer.
	 */
	public function register_hooks() {
		add_filter( 'cloudinary_admin_sidebar', array( $this, 'filtered_settings' ) );
	}

	/**
	 * Filter the settings.
	 *
	 * @param array $settings The settings.
	 *
	 * @return array
	 */
	public function filtered_settings( $settings ) {
		if ( ! $this->is_special_offer_available() ) {
			return $settings;
		}

		$settings[0][] = array(
			array(
				'type'       => 'tag',
				'element'    => 'div',
				'content'    => __( 'Special Offer!', 'cloudinary' ),
				'attributes' => array(
					'class' => array(
						'cld-special-offer',
					),
				),
			),
			array(
				'type'        => 'panel',
				'title'       => __( 'Get a small $29 plan', 'cloudinary' ),
				'collapsible' => 'closed',
				array(
					'type'    => 'div',
					'content' => $this->get_special_offer_content(),
				),
				array(
					'type'    => 'link',
					'content' => __( 'Get started', 'cloudinary' ),
					'url'     => function () {
						$args = array(
							'tf_360017815680' => 'help_with_plans',
							'tf_subject'      => sprintf(
								// translators: The plugin version.
								esc_attr( __( 'Request to Purchase the WordPress plugin version %s Small Plan', 'cloudinary' ) ),
								$this->plugin->version
							),
							'tf_description'  => esc_attr( __( "Hello,<br><br>I'm interested in purchasing the monthly Small plan for $29/month. Could you please provide me with the next steps to complete the purchase?<br><br>Thank you!", 'cloudinary' ) ),
						);
						return Utils::get_support_link( $args );
					},
					'target'  => '_blank',
				),
			),
		);

		return $settings;
	}

	/**
	 * Check if the user is eligible for the $29 offer.
	 *
	 * @return bool
	 */
	protected function is_special_offer_available() {
		$last_usage = get_option( Connect::META_KEYS['last_usage'], array( 'plan' => '' ) );

		return 'free' === strtolower( $last_usage['plan'] );
	}

	/**
	 * Get Special Offer content.
	 *
	 * @return string
	 */
	protected function get_special_offer_content() {
		ob_start();
		include $this->plugin->dir_path . 'php/templates/special-offer.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		return ob_get_clean();
	}
}
