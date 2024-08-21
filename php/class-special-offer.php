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
		if ( ! $this->is_29_offer_available() ) {
			return $settings;
		}

		$settings[0][] = array(
			array(
				'type'       => 'tag',
				'element'    => 'div',
				'content'    => __( 'Special offer', 'cloudinary' ),
				'attributes' => array(
					'class' => array(
						'cld-special-offer',
					),
				),
			),
			array(
				'type'        => 'panel',
				'title'       => __( 'Get a $29 plan', 'cloudinary' ),
				'description' => __( 'Contact us', 'cloudinary' ),
				'collapsible' => 'closed',
				array(
					'type'    => 'tag',
					'element' => 'p',
					'content' => __( 'Get 100GB for $29.', 'cloudinary' ),
				),
				array(
					'type'    => 'link',
					'content' => __( 'Lets get it started', 'cloudinary' ),
					'url'     => static function () {
						$current_user = wp_get_current_user();
						$plugin       = get_plugin_instance();
						$cloud_name   = $plugin->components['connect']->get_cloud_name();

						return add_query_arg(
							array(
								'tf_anonymous_requester_email' => $current_user->user_email,
								'tf_22246877'                  => $current_user->display_name,
								'tf_360007219560'              => $cloud_name,
								'tf_360017815680'              => 'help_with_plans',
								'tf_subject'                   => __( 'Special offer for $29 plan', 'cloudinary' ),
								'tf_description'               => __( 'I would like to get the $29 plan', 'cloudinary' ),
							),
							'https://support.cloudinary.com/hc/en-us/requests/new'
						);
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
	protected function is_29_offer_available() {
		$last_usage = get_option( Connect::META_KEYS['last_usage'], array( 'plan' => '' ) );

		return 'free' !== strtolower( $last_usage['plan'] );
	}
}
