<?php
/**
 * Admin class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component;

/**
 * Handles Cloudinary's admin settings.
 */
class Settings_Page implements Component\Assets, Component\Config, Component\Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 * @var     Plugin Instance of the global plugin.
	 */
	private $plugin;

	/**
	 * Settings page UI definition.
	 *
	 * @var array
	 */
	private $ui = array();

	/**
	 * Settings page slugs.
	 *
	 * @var array
	 */
	private $pages = array();

	/**
	 * Active page slug.
	 *
	 * @var string
	 */
	private $active_page;

	/**
	 * Settings pages handle/ID
	 *
	 * @var array
	 */
	public $handles;

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const SETTINGS_SLUG = 'settings';


	/**
	 * Initiate the plugin resources.
	 *
	 * @param object $plugin Instance of the plugin.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->ui     = apply_filters( 'cloudinary_settings_ui_definition', $this->get_ui(), $this );
	}

	/**
	 * Register the admin pages
	 *
	 * @since 0.1
	 */
	public function register_admin() {

		$count = 0; // Record notification alerts.
		if ( empty( $this->plugin->config['connect'] ) ) {
			$count ++;
		}
		$count_html = '';
		if ( ! empty( $count ) ) {
			$count_html = sprintf( ' <span class="update-plugins count-%d"><span class="update-count">%d</span></span>', $count, number_format_i18n( $count ) );
		}

		$this->handles[] = add_menu_page( $this->ui['page_title'], $this->ui['menu_title'] . $count_html, $this->ui['capability'], $this->ui['slug'], null, 'dashicons-cloudinary' );
		if ( ! empty( $this->ui['pages'] ) ) {
			foreach ( $this->ui['pages'] as $page ) {
				// If this page has "require_config" set, ensure we're fully connected to cloudinary.
				if (
					! empty( $page['requires_config'] ) &&
					(
						! $this->plugin->config['connect'] ||
						! $this->plugin->components['connect'] ||
						! $this->plugin->components['connect']->is_connected()
					)
				) {
					continue;
				}
				$this->handles[] = add_submenu_page( $this->ui['slug'], $page['page_title'], $page['menu_title'], $this->ui['capability'], $page['slug'], array( $this, 'render' ) );
			}
		}

	}

	/**
	 * Set notices on successful update of settings.
	 *
	 * @param mixed  $value     The new Value.
	 * @param mixed  $old_value The old value.
	 * @param string $setting   The setting key.
	 *
	 * @return mixed
	 */
	public function set_notices( $value, $old_value, $setting ) {
		$tab = $this->get_tab();
		if ( ! empty( $tab ) && $value !== $old_value ) {
			if ( is_wp_error( $value ) ) {
				add_settings_error( $setting, 'setting_notice', $value->get_error_message(), 'error' );

				return $old_value;
			} elseif ( ! empty( $tab['success_notice'] ) ) {
				add_settings_error( $setting, 'setting_notice', $tab['success_notice'], 'updated' );
			}

			// Delete the settings cache to allow for it to rebuild.
			delete_option( 'cloudinary_settings_cache' );
		}

		return $value;
	}

	/**
	 * Register the settings
	 *
	 * @since 0.1
	 */
	public function register_settings() {
		$page_slugs = array_keys( $this->pages );
		foreach ( $page_slugs as $page ) {
			$this->set_active_page( $page );
			$page = $this->get_page( $page );
			if ( ! empty( $page['tabs'] ) ) {
				$tabs = array_keys( $page['tabs'] );
				if ( ! empty( $tabs ) ) {
					array_map( array( $this, 'register_section' ), $tabs );
				}
			}
		}
		$this->set_active_page( null );
	}

	/**
	 * Register a setting tab section.
	 *
	 * @since 0.1
	 *
	 * @param string $tab_slug The slug for this tab section.
	 */
	private function register_section( $tab_slug ) {
		$tab          = $this->get_tab( $tab_slug );
		$setting_slug = $this->setting_slug( $tab['slug'] );
		$title        = ! empty( $tab['heading'] ) ? $tab['heading'] : null;
		$args         = array();

		if ( ! empty( $tab['sanitize_callback'] ) && is_callable( $tab['sanitize_callback'] ) ) {
			$args['sanitize_callback'] = $tab['sanitize_callback'];
		}

		register_setting( $setting_slug, $setting_slug, $args );

		add_filter( 'pre_update_site_option_' . $setting_slug, array( $this, 'set_notices' ), 10, 3 );
		add_filter( 'pre_update_option_' . $setting_slug, array( $this, 'set_notices' ), 10, 3 );

		add_settings_section(
			$setting_slug,
			$title,
			array( $this, 'load_section_content' ),
			$setting_slug
		);

		$this->register_section_fields( $tab, $setting_slug );
	}

	/**
	 * Register the fields for a tab section.
	 *
	 * @since 0.1
	 *
	 * @param array  $tab          The tab to register the section fields for.
	 * @param string $setting_slug The slug of the setting to register section for.
	 */
	private function register_section_fields( $tab, $setting_slug ) {

		foreach ( $tab['fields'] as $field_slug => $field ) {
			$field['slug'] = $field_slug;

			/**
			 * Filter the field just before rendering to allow field manipulation.
			 *
			 * @var array  $field        The field array.
			 * @var string $setting_slug The setting slug
			 */
			$field = apply_filters( 'cloudinary_render_field', $field, $tab['slug'] );

			if ( ! empty( $field['type'] ) && is_callable( $field['type'] ) ) {
				continue;
			}
			$type          = ! empty( $field['type'] ) ? $field['type'] : 'text';
			$args          = $field;
			$args['class'] = 'field-row-' . $field['slug'] . ' field-' . $type;
			if ( 'heading' !== $type ) {
				$args['label_for'] = 'field-' . $field['slug'];
			}
			add_settings_field(
				$tab['slug'] . '_' . $field_slug,
				$field['label'],
				array( $this, 'render_field' ),
				$setting_slug,
				$setting_slug,
				$args
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @since 0.1
	 */
	public function render() {

		$this->header();

		$this->tabs();

		$this->section();

		$this->footer();

	}

	/**
	 * Render a field in a tab section.
	 *
	 * @since 0.1
	 *
	 * @param array       $field            The field to render.
	 * @param string|null $value            The value to render.
	 * @param bool        $show_description Whether to render the description.
	 */
	public function render_field( $field, $value = null, $show_description = true ) {

		if ( null === $value ) {
			$value = $this->get_value( $field['slug'] );
		}
		$setting_slug = $this->setting_slug();
		$type         = empty( $field['type'] ) ? 'text' : $field['type'];
		$data_meta    = empty( $field['data_meta'] ) ? $field['slug'] : $field['data_meta'];
		if ( is_callable( $type ) ) {
			call_user_func_array( $type, $field );

			return;
		}
		// Conditions.
		$condition = 'false';
		if ( ! empty( $field['condition'] ) ) {
			$condition = wp_json_encode( $field['condition'] );
		}
		// Context reference data att.
		$context = null;
		if ( ! empty( $field['context'] ) ) {
			$context = $field['context'];
		}
		// Required Tag.
		$required = null;
		if ( ! empty( $field['required'] ) ) {
			$required = 'required';
		}

		if ( ! empty( $field['prefix'] ) ) {
			echo wp_kses_post( $field['prefix'] );
		}

		// switch field type.
		switch ( $type ) {
			case 'heading':
				break;
			case 'custom':
				if ( ! empty( $field['callback'] ) && is_callable( $field['callback'] ) ) {
					call_user_func( $field['callback'], $field );
				}
				break;
			case 'select':
				?>
				<select data-condition="<?php echo esc_attr( $condition ); ?>" class="cld-field regular-<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field['label_for'] ); ?>" name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]" data-meta="<?php echo esc_attr( $data_meta ); ?>" data-context="<?php echo esc_attr( $context ); ?>" <?php echo esc_attr( $required ); ?>>
					<?php foreach ( $field['choices'] as $key => $option ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_attr( $option ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;
			case 'number':
				?>
				<input data-condition="<?php echo esc_attr( $condition ); ?>" type="<?php echo esc_attr( $type ); ?>" class="cld-field regular-<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field['label_for'] ); ?>" name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]" min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $field['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>" data-context="<?php echo esc_attr( $context ); ?>" <?php echo esc_attr( $required ); ?>>
				<?php
				break;
			case 'checkbox':
				// Place a hidden field before it, to set unchecked value to off.
				?>
				<input type="hidden" name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]" value="off">
				<input type="<?php echo esc_attr( $type ); ?>" class="cld-field regular-<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field['label_for'] ); ?>" name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]" <?php if ( ! empty( $field['pattern'] ) ) : ?>pattern="<?php echo esc_attr( $field['pattern'] ); ?>"<?php endif; ?> data-condition="<?php echo esc_attr( $condition ); ?>" data-context="<?php echo esc_attr( $context ); ?>" <?php echo esc_attr( $required ); ?> <?php checked( 'on', $value ); ?>>
				<?php
				break;
			case 'radio':
				foreach ( $field['choices'] as $key => $option ) :
					?>
					<input
							type="<?php echo esc_attr( $type ); ?>"
							class="cld-field regular-<?php echo esc_attr( $type ); ?>"
							id="<?php echo esc_attr( $field['label_for'] . '_' . $key ); ?>"
							name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]"
						<?php if ( ! empty( $field['pattern'] ) ) : ?>
							pattern="<?php echo esc_attr( $field['pattern'] ); ?>"
						<?php endif; ?>
							data-condition="<?php echo esc_attr( $condition ); ?>"
							data-context="<?php echo esc_attr( $context ); ?>"
						<?php echo esc_attr( $required ); ?>
						<?php checked( $key, $value ); ?>
							value="<?php echo esc_attr( $key ); ?>"
					/>
					<label for="<?php echo esc_attr( $field['label_for'] . '_' . $key ); ?>"><?php esc_html_e( $option ); ?></label>
				<?php
				endforeach;
				break;
			case 'textarea':
				?>
				<textarea class="cld-field regular-<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field['label_for'] ); ?>" name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]" data-condition="<?php echo esc_attr( $condition ); ?>" data-context="<?php echo esc_attr( $context ); ?>" <?php echo esc_attr( $required ); ?>><?php echo esc_html( $value ); ?></textarea>
				<?php
				break;
			default:
				?>
				<input
					<?php echo empty( $field['placeholder'] ) ? '' : sprintf( 'placeholder="%s"', esc_attr( $field['placeholder'] ) ); ?>
						type="<?php echo esc_attr( $type ); ?>"
						class="cld-field regular-<?php echo esc_attr( $type ); ?>"
						id="<?php echo esc_attr( $field['label_for'] ); ?>"
						name="<?php echo esc_attr( $setting_slug ); ?>[<?php echo esc_attr( $field['slug'] ); ?>]"
					<?php if ( ! empty( $field['pattern'] ) ) : ?>
						pattern="<?php echo esc_attr( $field['pattern'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $field['disabled'] ) ) : ?>
						disabled="disabled"
					<?php endif; ?>
						data-condition="<?php echo esc_attr( $condition ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						data-context="<?php echo esc_attr( $context ); ?>"
					<?php echo esc_attr( $required ); ?>>
				<?php
				break;
		}
		if ( ! empty( $field['suffix'] ) ) {
			echo wp_kses_post( $field['suffix'] );
		}
		if ( ! empty( $field['error_notice'] ) ) {
			?>
			<p class="description error-notice" id="<?php echo esc_attr( $setting_slug ); ?>-error"><?php echo wp_kses_post( $field['error_notice'] ); ?></p>
			<?php
		}
		if ( ! empty( $field['description'] ) && $show_description ) {
			?>
			<p class="description" id="<?php echo esc_attr( $setting_slug ); ?>-description"><?php echo wp_kses_post( $field['description'] ); ?></p>
			<?php
		}
	}

	/**
	 * Get the value for a tab field.
	 *
	 * @since 0.1
	 *
	 * @param string $field_slug The field to get the value of.
	 * @param string $tab_slug   The tab slug to get settings for.
	 *
	 * @return array
	 */
	public function get_value( $field_slug, $tab_slug = null ) {
		$tab_config = $this->get_tab_config( $tab_slug );
		$value      = null;
		if ( isset( $tab_config[ $field_slug ] ) ) {
			$value = $tab_config[ $field_slug ];
		}

		return $value;
	}

	/**
	 * Get the settings for a page.
	 *
	 * @since 0.1
	 *
	 * @param string $page_slug The page slug to get settings for.
	 *
	 * @return array
	 */
	public function get_page_config( $page_slug = null ) {
		$page    = $this->get_page( $page_slug );
		$setting = array();
		if ( ! empty( $page['tabs'] ) ) {
			foreach ( $page['tabs'] as $tab ) {

				if ( ! empty( $tab ) ) {
					$setting_slug = $this->setting_slug( $tab['slug'] );
					$defaults     = array();
					foreach ( $tab['fields'] as $slug => $field ) {
						$defaults[ $slug ] = null;
						if ( ! empty( $field['default'] ) ) {
							$defaults[ $slug ] = $field['default'];
						}
					}
					$tab_setting             = get_option( $setting_slug, $defaults );
					$setting[ $tab['slug'] ] = $this->sanitize( $tab_setting, $tab['fields'] );
				}
			}
		}

		return $setting;
	}

	/**
	 * Get the settings for a tab.
	 *
	 * @since 0.1
	 *
	 * @param string $tab_slug The tab slug to get settings for.
	 *
	 * @return array
	 */
	public function get_tab_config( $tab_slug = null ) {
		$tab          = $this->get_tab( $tab_slug );
		$setting_slug = $this->setting_slug( $tab['slug'] );
		$defaults     = array();
		foreach ( $tab['fields'] as $slug => $field ) {
			$defaults[ $slug ] = null;
			if ( ! empty( $field['default'] ) ) {
				$defaults[ $slug ] = $field['default'];
			}
		}
		$setting = get_option( $setting_slug, $defaults );

		return $this->sanitize( $setting, $tab['fields'] );
	}

	/**
	 * Sanitize the settings.
	 *
	 * @since 0.1
	 *
	 * @param array $setting The setting array for the tab.
	 * @param array $fields  The fields for the setting.
	 *
	 * @return array
	 */
	public function sanitize( $setting, $fields ) {
		foreach ( $fields as $slug => $field ) {
			if ( isset( $setting[ $slug ] ) && ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				if ( is_array( $field['sanitize_callback'] ) ) {
					$setting[ $slug ] = call_user_func_array( $field['sanitize_callback'], array( $setting[ $slug ], $field ) );
				} else {
					$setting[ $slug ] = call_user_func( $field['sanitize_callback'], $setting[ $slug ] );
				}
			} else {
				if ( is_array( $field ) ) {
					array_walk_recursive(
						$field,
						static function ( $field_value ) {
							// WP 4.9 compatibility, as _sanitize_text_fields() didn't have this check yet, and this prevents an error.
							// @see https://github.com/WordPress/wordpress-develop/blob/b30baca3ca2feb7f44b3615262ca55fcd87ae232/src/wp-includes/formatting.php#L5307
							if ( is_object( $field_value ) || is_array( $field_value ) ) {
								return '';
							}

							return sanitize_text_field( $field_value );
						}
					);
				} else {
					$setting[ $slug ] = sanitize_text_field( $field );
				}
			}
			if ( empty( $setting[ $slug ] ) && ! empty( $field['default'] ) ) {
				$setting[ $slug ] = $field['default'];
			}
		}

		return $setting;
	}

	/**
	 * Output the header for the settings page.
	 *
	 * @since 0.1
	 */
	public function header() {
		// Include the settings page header template.
		$header_file_path = $this->plugin->template_path . self::SETTINGS_SLUG . '-header.php';
		if ( file_exists( $header_file_path ) ) {
			include $header_file_path; // phpcs:ignore
		}
	}

	/**
	 * Output the header for the settings page.
	 *
	 * @since 0.1
	 *
	 * @param string $tab_slug The slug of the tab tp get.
	 *
	 * @return array|null the array structure of the tab, or null if not found.
	 */
	public function get_tab( $tab_slug = null ) {
		if ( null === $tab_slug ) {
			$tab_slug = $this->active_tab();
		}
		$tab  = null;
		$page = $this->get_page();
		if ( ! empty( $page['tabs'] ) && ! empty( $page['tabs'][ $tab_slug ] ) ) {
			$tab = $page['tabs'][ $tab_slug ];
		}

		return $tab;
	}


	/**
	 * Get the current page config..
	 *
	 * @since 0.1
	 *
	 * @param string $page_slug The slug of the page tp get.
	 *
	 * @return array|null the array structure of the page, or null if not found.
	 */
	public function get_page( $page_slug = null ) {
		if ( null === $page_slug ) {
			$page_slug = $this->active_page();
		}
		$page          = null;
		$page_slug_key = $this->pages[ $page_slug ];
		if ( ! empty( $this->ui['pages'][ $page_slug_key ] ) ) {
			$page = $this->ui['pages'][ $page_slug_key ];
		}

		return $page;
	}

	/**
	 * Output the page tabs for the settings page.
	 *
	 * @since 0.1
	 */
	public function tabs() {
		$tabs_file_path = $this->plugin->template_path . self::SETTINGS_SLUG . '-tabs.php';
		if ( file_exists( $tabs_file_path ) ) {
			include $tabs_file_path; // phpcs:ignore
		}
	}

	/**
	 * Output the page sections.
	 *
	 * @since 0.1
	 *
	 * @param string $tab_slug The slug of the tab to build attributes for.
	 */
	public function build_tab_attributes( $tab_slug ) {
		$page = $this->get_page();
		$atts = array(
			'class' => 'nav-tab',
			'href'  => admin_url( 'admin.php?page=' . $page['slug'] . '&tab=' . $tab_slug ),
		);

		if ( $tab_slug === $this->active_tab() ) {
			$atts['class'] .= ' nav-tab-active';
		}
		$out = array();
		foreach ( $atts as $key => $value ) {
			$out[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		echo implode( ' ', $out ); // phpcs:ignore

	}

	/**
	 * Output the current tab section.
	 *
	 * @since 0.1
	 */
	public function section() {
		$section_file_path = $this->plugin->template_path . self::SETTINGS_SLUG . '-section.php';
		if ( file_exists( $section_file_path ) ) {
			include $section_file_path; // phpcs:ignore
		}
	}

	/**
	 * Output the current tab section.
	 *
	 * @since 0.1
	 */
	public function load_section_content() {
		$tab = $this->get_tab();
		if ( ! empty( $tab ) ) {
			$tab_slug     = str_replace( '_', '-', $tab['slug'] );
			$content_file = $this->plugin->dir_path . 'ui-definitions/tabs/' . $tab_slug . '-content.php'; // phpcs:ignore
			if ( file_exists( $content_file ) ) {
				include $content_file; // phpcs:ignore
			}
		}
	}

	/**
	 * Output the footer for the settings page.
	 *
	 * @since 0.1
	 */
	public function footer() {
		// Load a custom footer if it exists.
		$tab = $this->get_tab();
		if ( ! empty( $tab ) ) {
			$tab_slug    = str_replace( '_', '-', $tab['slug'] );
			$footer_file = $this->plugin->dir_path . 'ui-definitions/tabs/' . $tab_slug . '-footer.php'; // phpcs:ignore
			if ( file_exists( $footer_file ) ) {
				include $footer_file; // phpcs:ignore
			}
		}
		// Include the settings page header template.
		$footer_file_path = $this->plugin->template_path . self::SETTINGS_SLUG . '-footer.php';
		if ( file_exists( $footer_file_path ) ) {
			include $footer_file_path; // phpcs:ignore
		}
	}

	/**
	 * Get the ui structures.
	 *
	 * @since 0.1
	 * @return array
	 */
	public function get_ui() {
		$defaults = array(
			'save_button_label' => __( 'Save Changes' ),
			'capability'        => 'manage_options',
			'tabs'              => array(),
		);

		$settings_definition_file_path = $this->plugin->dir_path . 'ui-definitions/settings.php';
		if ( ! file_exists( $settings_definition_file_path ) ) {
			return $defaults;
		}

		$pre_ui = include $settings_definition_file_path; // phpcs:ignore
		$ui     = apply_filters( 'cloudinary_settings_ui_pre_definition', wp_parse_args( $pre_ui, $defaults ) );
		if ( ! empty( $ui['pages'] ) ) {
			foreach ( $ui['pages'] as $page_slug => $page ) {
				// Register Pages and slugs.
				$this->pages[ $page['slug'] ] = $page_slug;
				if ( ! empty( $page['tabs'] ) ) {
					$tabs = array();
					foreach ( $page['tabs'] as $tab_slug ) {
						$tabs[ $tab_slug ] = $this->load_tab_definition( $tab_slug );
					}
					$ui['pages'][ $page_slug ]['tabs'] = array_filter( $tabs );

				}
			}
		}

		return $ui;
	}

	/**
	 * Loads the definition file for a tab.
	 *
	 * @since  0.1
	 *
	 * @param string $tab_slug The slug of a tab to attempt to load.
	 *
	 * @return array|null The tab definition or null if not found.
	 */
	public function load_tab_definition( $tab_slug ) {

		$file_name     = self::SETTINGS_SLUG . '-' . str_replace( '_', '-', $tab_slug ) . '.php';
		$definition    = null;
		$tab_file_path = $this->plugin->dir_path . 'ui-definitions/tabs/' . $file_name;
		if ( file_exists( $tab_file_path ) ) {
			$default            = array(
				'title'       => $tab_slug,
				'description' => null,
				'assets'      => array(),
				'fields'      => array(),
			);
			$tab                = include $tab_file_path; // phpcs:ignore
			$definition         = wp_parse_args( $tab, $default );
			$definition['slug'] = $tab_slug; // force set slug to avoid errors.

		}

		return $definition;
	}


	/**
	 * Loads the config for the plugin from options and make it available to use.
	 *
	 * @since  0.1
	 * @return array The array of the config options stored.
	 */
	public function get_config() {
		$config = get_option( 'cloudinary_settings_cache', array() );
		if ( empty( $config ) ) {
			$page_slugs = array_keys( $this->pages );
			foreach ( $page_slugs as $page ) {
				$this->set_active_page( $page );
				$page_config = $this->get_page_config( $page );
				$config      = array_merge( $config, $page_config );
			}

			$this->set_active_page( null );
			update_option( 'cloudinary_settings_cache', $config );
		}

		return $config;
	}

	/**
	 * Setup hooks
	 *
	 * @action init, 10
	 * @since  0.1
	 */
	public function setup() {
		$this->active_page();
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
		// Bring in tab specific assets.
		if ( ! empty( $this->ui['tabs'] ) ) {
			foreach ( $this->ui['tabs'] as $tab_slug => $tab ) {
				if ( empty( $tab['assets'] ) ) {
					continue;
				}
				$this->register_tab_assets( $tab_slug );
			}
		}
	}

	/**
	 * Register assets to be used for the class.
	 *
	 * @param string $tab_slug The tab slug to register assets for.
	 */
	private function register_tab_assets( $tab_slug ) {

		$tab = $this->get_tab( $tab_slug );
		foreach ( $tab['assets'] as $type => $set ) {

			// Check structure of asset.
			if ( 'style' === $type ) {
				$call = 'wp_register_style';
			} else {
				$call = 'wp_register_script';
			}
			$this->ui['tabs'][ $tab['slug'] ]['assets'][ $type ] = $this->register_tab_asset( $set, $call );
		}
	}

	/**
	 * Register single tab asset.
	 *
	 * @param array    $set  The array of assets to register.
	 * @param callable $call The function to call to register asset.
	 *
	 * @return array
	 */
	public function register_tab_asset( $set, $call ) {
		foreach ( $set as $key => &$asset ) {
			$args = false;
			if ( ! is_int( $key ) ) {
				$handle = $key; // Key is handle for script.
			} else {
				$handle = false;
			}
			if ( is_array( $asset ) ) {
				// Array of params means a structured register script.
				if ( $handle ) {
					array_unshift( $asset, $handle ); // push handle for named script.
				}
				$args = $asset;
			} elseif ( is_string( $asset ) && filter_var( $asset, FILTER_VALIDATE_URL ) ) {
				// Just a url, is a simple script to add with no deps etc..
				if ( ! $handle ) {
					$handle = strtok( basename( $asset ), '.' );
				}
				$args = array(
					$handle,
					$asset,
					array( 'cloudinary-settings' ),
					$this->plugin->version,
				);
			}
			if ( $args ) {
				call_user_func_array( $call, $args );
				$asset = $handle;
			}
		}

		return $set;
	}

	/**
	 * Enqueue Assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'cloudinary-settings' );
		wp_enqueue_style( 'cloudinary-settings' );
		// Enqueue current tabs assets.
		$this->enqueue_active_page();
	}

	/**
	 * Enqueue active page assets.
	 */
	public function enqueue_active_page() {
		$page = $this->get_page();
		if ( ! empty( $page['asset_init'] ) && is_callable( $page['asset_init'] ) ) {
			call_user_func( $page['asset_init'], $this );
		}
		if ( ! empty( $page['assets'] ) ) {
			$this->enqueue_active_assets( $page['assets'] );
		}
		// Enqueue current tabs assets.
		$tab = $this->get_tab();
		if ( ! empty( $tab['assets'] ) ) {
			$this->enqueue_active_assets( $tab['assets'] );
		}
	}

	/**
	 * Enqueue active tabs assets.
	 *
	 * @param array $assets Assets to enqueue.
	 */
	public function enqueue_active_assets( $assets ) {
		foreach ( $assets as $type => $set ) {
			$call = 'script' === $type ? 'wp_enqueue_script' : 'wp_enqueue_style';
			array_map( $call, $set );
		}
	}

	/**
	 * Get the setting slug for a tab.
	 *
	 * @param string $tab_slug The slug of the tab to get setting slug for.
	 *
	 * @return string
	 */
	public function setting_slug( $tab_slug = null ) {
		$tab = $this->get_tab( $tab_slug );

		return $this->ui['slug'] . '_' . $tab['slug'];

	}

	/**
	 * Get the currently active tab.
	 *
	 * @return string|null
	 */
	public function active_tab() {
		$page = $this->get_page();
		if ( empty( $page['tabs'] ) ) {
			return null;
		}
		$tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		if ( ! $this->validate_tab( $tab ) ) { // Tab is invalid or not set, check if in a POST.
			$tab = filter_input( INPUT_POST, 'tab', FILTER_DEFAULT );
			if ( ! $this->validate_tab( $tab ) ) { // Tab is invalid or not set, load the default/first tab.
				$tab = array_keys( $page['tabs'] );
				$tab = array_shift( $tab );
			}
		}

		return $tab;
	}

	/**
	 * Get the currently active page.
	 *
	 * @return string
	 */
	public function active_page() {

		if ( empty( $this->active_page ) ) {
			$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
			if ( ! empty( $page ) && $this->validate_page( $page ) ) {
				$current_page = $page;
			} else {
				$page_keys    = array_keys( $this->pages );
				$current_page = array_shift( $page_keys );
			}
			$this->set_active_page( $current_page );
		}

		return $this->active_page;
	}

	/**
	 * Check if this class is active.
	 *
	 * @return bool True if active False if not.
	 */
	public function is_active() {
		$screen = get_current_screen();
		$return = false;
		if ( ! empty( $this->handles ) && $screen instanceof \WP_Screen ) {
			$return = in_array( $screen->base, $this->handles, true );
		}

		return $return;
	}

	/**
	 * Check if a tab has been registered in the ui.
	 *
	 * @param string $tab The tab to check.
	 *
	 * @return bool
	 */
	public function validate_tab( $tab ) {
		$page  = $this->get_page();
		$valid = false;
		if ( ! empty( $page['tabs'] ) ) {
			$all_tabs = array_keys( $page['tabs'] );
			$valid    = in_array( $tab, $all_tabs, true );
		}

		return $valid;
	}

	/**
	 * Check if a page has been registered in the ui.
	 *
	 * @param string $page The page slug to check.
	 *
	 * @return bool
	 */
	public function validate_page( $page ) {
		return isset( $this->pages[ $page ] );
	}

	/**
	 * Set the current active page.
	 *
	 * @param string|null $page_slug Set the current active page.
	 */
	public function set_active_page( $page_slug ) {
		if ( null === $page_slug || isset( $this->pages[ $page_slug ] ) ) {
			$this->active_page = $page_slug;
		}
	}

}
