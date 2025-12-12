<?php
/**
 * Global Transformations class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Connect\Api;
use Cloudinary\Relate;
use Cloudinary\Settings\Setting;
use Cloudinary\Sync;
use Cloudinary\REST_API;
use Cloudinary\Utils;
use WP_Post;

/**
 * Class Global Transformations.
 *
 * Handles Contextual Globals transformations for content.
 */
class Global_Transformations {

	/**
	 * Holds the Media instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Media Instance of the plugin.
	 */
	private $media;

	/**
	 * Holds the taxonomy fields defined in settings.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	private $taxonomy_fields;

	/**
	 * Holds the global settings (lowest level).
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	public $globals;

	/**
	 * Holds the order meta key to maintain consistency.
	 */
	const META_ORDER_KEY = 'cloudinary_transformations';

	/**
	 * Holds the apply type meta key to maintain consistency.
	 */
	const META_APPLY_KEY = 'cloudinary_apply_type';

	/**
	 * Holds the overwrite transformations for featured images meta key.
	 */
	const META_FEATURED_IMAGE_KEY = '_cloudinary_featured_overwrite';

	/**
	 * Holds the media settings.
	 *
	 * @var Setting
	 */
	protected $media_settings;

	/**
	 * Global Transformations constructor.
	 *
	 * @param \Cloudinary\Media $media The plugin.
	 */
	public function __construct( \Cloudinary\Media $media ) {
		$this->media            = $media;
		$this->media_settings   = $this->media->get_settings();
		$this->globals['image'] = $this->media_settings->get_setting( 'image_settings' );
		$this->globals['video'] = $this->media_settings->get_setting( 'video_settings' );
		// Set value to null, to rebuild data to get defaults.
		$field_slugs = array_keys( $this->globals['image']->get_value() );
		$field_slugs = array_merge( $field_slugs, array_keys( $this->globals['image']->get_value() ) );
		foreach ( $field_slugs as $slug ) {
			$setting = $this->media_settings->get_setting( $slug );
			if ( $setting->has_param( 'taxonomy_field' ) ) {
				$context = $setting->get_param( 'taxonomy_field.context', 'global' );
				if ( isset( $this->taxonomy_fields[ $context ] ) && in_array( $setting, $this->taxonomy_fields[ $context ], true ) ) {
					continue;
				}
				$priority = intval( $setting->get_param( 'taxonomy_field.priority', 10 ) ) * 1000;
				while ( isset( $this->taxonomy_fields[ $context ][ $priority ] ) ) {
					++$priority;
				}
				if ( ! isset( $this->taxonomy_fields[ $context ] ) ) {
					$this->taxonomy_fields[ $context ] = array();
				}
				$this->taxonomy_fields[ $context ][ $priority ] = $setting;
			}
		}

		foreach ( $this->taxonomy_fields as $context => $set ) {
			ksort( $this->taxonomy_fields[ $context ] );
		}
		$this->setup_hooks();
	}

	/**
	 * Add fields to Add taxonomy term screen.
	 */
	public function add_taxonomy_fields() {
		$template_file = $this->media->plugin->template_path . 'taxonomy-transformation-fields.php';
		if ( file_exists( $template_file ) ) {
			// Initialise the settings to be within the terms context, and not contain or alter the global setting value.
			$this->init_term_transformations();
			include $template_file; // phpcs:ignore
		}
	}

	/**
	 * Add fields to Edit taxonomy term screen.
	 */
	public function edit_taxonomy_fields() {
		$template_file = $this->media->plugin->template_path . 'taxonomy-term-transformation-fields.php';
		if ( file_exists( $template_file ) ) {
			// Initialise the settings to be within the terms context, and not contain or alter the global setting value.
			$this->init_term_transformations();
			include $template_file; // phpcs:ignore
		}
	}

	/**
	 * Save the meta data for the term.
	 *
	 * @param int $term_id The term ID.
	 */
	public function save_taxonomy_custom_meta( $term_id ) {

		foreach ( $this->taxonomy_fields as $context => $set ) {

			foreach ( $set as $setting ) {

				$meta_key = self::META_ORDER_KEY . '_' . $setting->get_param( 'slug' );
				$value    = $setting->get_submitted_value();

				// Check if it's option based.
				if ( $setting->has_param( 'options' ) ) {
					$options = $setting->get_param( 'options', array() );
					if ( ! in_array( $value, $options, true ) ) {
						$value = null;
					}
				}

				// If null, skip it.
				if ( is_null( $value ) ) {
					continue;
				}
				// Update the metadata.
				update_term_meta( $term_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Get transformations for a term.
	 *
	 * @param int    $term_id The term ID to get transformations for.
	 * @param string $type    The default transformations type.
	 *
	 * @return array
	 */
	private function get_term_transformations( $term_id, $type ) {
		$meta_data = array();
		if ( ! empty( $this->taxonomy_fields[ $type ] ) ) {
			foreach ( $this->taxonomy_fields[ $type ] as $setting ) {
				$slug               = $setting->get_param( 'slug' );
				$meta_key           = self::META_ORDER_KEY . '_' . $slug;
				$value              = get_term_meta( $term_id, $meta_key, true );
				$meta_data[ $slug ] = $value;
			}

			// Clear out empty items.
			$meta_data = array_filter( $meta_data );
		}

		return $meta_data;
	}

	/**
	 * Resets the taxonomy fields values.
	 */
	protected function reset_taxonomy_field_values() {
		foreach ( $this->taxonomy_fields as $context => $set ) {
			foreach ( $set as $setting ) {
				$setting->set_value( null );
			}
		}
	}

	/**
	 * Init term meta field values.
	 */
	public function init_term_transformations() {
		// Enqueue Cloudinary.
		$this->media->plugin->enqueue_assets();

		$this->reset_taxonomy_field_values();

		$types = array_keys( $this->taxonomy_fields );
		foreach ( $types as $type ) {
			$transformations = $this->get_transformations( $type );
			foreach ( $transformations as $slug => $transformation ) {
				$this->media_settings->get_setting( $slug )->set_value( $transformation );
			}
		}
	}

	/**
	 * Get the transformations.
	 *
	 * @param string $type The context type to get transformations for.
	 *
	 * @return array
	 */
	public function get_transformations( $type ) {

		$transformations = isset( $this->globals[ $type ] ) ? $this->globals[ $type ] : array();
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				// check screen context.
				switch ( $screen->base ) {
					case 'term':
						$term_id         = filter_input( INPUT_GET, 'tag_ID', FILTER_SANITIZE_NUMBER_INT );
						$transformations = $this->get_term_transformations( $term_id, $type );
						break;
					default:
						$transformations = array();
						break;
				}
			}
		}

		return $transformations;
	}

	/**
	 * Get the transformations of a posts taxonomies.
	 *
	 * @param string $type The type to get.
	 *
	 * @return string
	 */
	public function get_taxonomy_transformations( $type ) {
		static $cache = array();

		$post = $this->get_current_post();
		$key  = wp_json_encode( func_get_args() ) . ( $post ? $post->ID : 0 );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$return_transformations = '';
		if ( $post ) {
			$transformations = array();
			$terms           = $this->get_terms( $post->ID );
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $item ) {
					$transformation = $this->get_term_transformations( $item['term']->term_id, $type );
					if ( ! empty( $transformation[ $type . '_freeform' ] ) ) {
						$transformations[] = trim( $transformation[ $type . '_freeform' ] );
					}
				}
				// Join the freeform.
				$return_transformations = implode( '/', (array) $transformations );
			}
		}

		$cache[ $key ] = $return_transformations;

		return $cache[ $key ];
	}

	/**
	 * Check if the image has a post taxonomy overwrite.
	 *
	 * @return bool
	 */
	public function is_taxonomy_overwrite() {
		$apply_type = false;
		$post       = $this->get_current_post();
		if ( $post ) {
			$apply_type = get_post_meta( $post->ID, self::META_APPLY_KEY . '_terms', true );
		}

		return ! empty( $apply_type );
	}

	/**
	 * Load the preview field.
	 *
	 * @param bool $video Flag if this is a video preview.
	 */
	public function load_preview( $video = false ) {
		$file = 'transformation-preview';
		if ( true === $video ) {
			$file .= '-video';
		}
		require $this->media->plugin->template_path . $file . '.php'; // phpcs:ignore
	}

	/**
	 * Register Taxonomy Ordering.
	 *
	 * @param string   $type The post type (unused).
	 * @param \WP_Post $post The current post.
	 */
	public function taxonomy_ordering( $type, $post ) {
		if ( $this->has_public_taxonomies( $post ) ) {
			add_meta_box( 'cld-taxonomy-order', __( 'Cloudinary terms transformations', 'cloudinary' ), array( $this, 'render_ordering_box' ), null, 'side', 'core' );
		}
	}

	/**
	 * Check if the post has any public taxonomies.
	 *
	 * @param \WP_POST $post The post to check.
	 *
	 * @return bool
	 */
	public function has_public_taxonomies( $post ) {
		$taxonomies = get_object_taxonomies( $post, 'objects' );
		// Only get taxonomies that have a UI.
		$taxonomies = array_filter(
			$taxonomies,
			function ( $tax ) {
				return $tax->show_ui;
			}
		);

		return ! empty( $taxonomies );
	}

	/**
	 * Render the ordering metabox.
	 *
	 * @param \WP_Post $post the current Post.
	 */
	public function render_ordering_box( $post ) {
		// Show UI if has taxonomies.
		if ( $this->has_public_taxonomies( $post ) ) {
			echo $this->init_taxonomy_manager( $post ); // phpcs:ignore
		}
	}

	/**
	 * Get terms for the current post that has transformations.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array|false|int|\WP_Error|\WP_Term[]
	 */
	public function get_terms( $post_id ) {
		// Get terms for this post on load.
		$items = get_post_meta( $post_id, self::META_ORDER_KEY . '_terms', true );
		$terms = array();
		if ( ! empty( $items ) ) {
			$items = array_map(
				function ( $item ) {
					// Get the id.
					if ( false !== strpos( $item, ':' ) ) {
						$parts = explode( ':', $item );
						$term  = get_term_by( 'id', $parts[1], $parts[0] );

						if ( ! $term ) {
							$term = get_term_by( 'term_taxonomy_id', $parts[1], $parts[0] );
						}
					} else {
						// Something went wrong, and value was not an int and didn't contain a tax:slug string.
						return null;
					}

					// Return if term is valid.
					if ( $term instanceof \WP_Term ) {
						return array(
							'term'  => $term,
							'value' => $item,
						);
					}

					return null;
				},
				$items
			);
			$terms = array_filter( $items );
		} else {
			$taxonomies    = get_object_taxonomies( get_post_type( $post_id ) );
			$current_terms = wp_get_object_terms( $post_id, $taxonomies );
			if ( ! empty( $current_terms ) ) {
				$terms = array_map(
					function ( $term ) {
						$value = $term->taxonomy . ':' . $term->term_id;

						$item = array(
							'term'  => $term,
							'value' => $value,
						);

						return $item;
					},
					$current_terms
				);
			}
		}

		return $terms;
	}

	/**
	 * Make an item for ordering.
	 *
	 * @param int    $id   The term id.
	 * @param string $name The term name.
	 *
	 * @return string
	 */
	public function make_term_sort_item( $id, $name ) {
		$out = array(
			'<li class="cld-tax-order-list-item" data-item="' . esc_attr( $id ) . '">',
			'<span class="dashicons dashicons-menu cld-tax-order-list-item-handle"></span>',
			'<input class="cld-tax-order-list-item-input" type="hidden" name="cld_tax_order[]" value="' . $id . '">' . $name,
			'</li>',
		);

		return implode( $out );
	}

	/**
	 * Init the taxonomy ordering metabox.
	 *
	 * @param \WP_Post $post The current Post.
	 *
	 * @return string
	 */
	private function init_taxonomy_manager( $post ) {
		wp_enqueue_script( 'wp-api' );

		$terms = $this->get_terms( $post->ID );

		$out   = array();
		$out[] = '<div class="cld-tax-order">';
		$out[] = '<p style="font-size: 12px; font-style: normal; color: rgb( 117, 117, 117 );">' . esc_html__( 'If you placed custom transformations on these terms you may order them below. ', 'cloudinary' ) . '</li>';
		$out[] = '<ul class="cld-tax-order-list" id="cld-tax-items">';
		$out[] = '<li class="cld-tax-order-list-item no-items">' . esc_html__( 'No terms added', 'cloudinary' ) . '</li>';
		if ( ! empty( $terms ) ) {
			foreach ( (array) $terms as $item ) {
				$out[] = $this->make_term_sort_item( $item['value'], $item['term']->name );
			}
		}
		$out[] = '</ul>';

		// Get apply Type.
		if ( ! empty( $terms ) ) {
			$type  = get_post_meta( $post->ID, self::META_APPLY_KEY . '_terms', true );
			$out[] = '<label class="cld-tax-order-list-type"><input ' . checked( 'overwrite', $type, false ) . ' type="checkbox" value="overwrite" name="cld_apply_type" />' . esc_html__( 'Disable Cloudinary global transformations', 'cloudinary' ) . '</label>';
		}

		$out[] = '</div>';

		return implode( $out );
	}

	/**
	 * Save the taxonomy ordering meta.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_taxonomy_ordering( $post_id ) {
		$args = array(
			'cld_tax_order'  => array(
				'filter'  => FILTER_CALLBACK,
				'flags'   => FILTER_REQUIRE_ARRAY,
				'options' => 'sanitize_text_field',
			),
			'cld_apply_type' => array(
				'filter'  => FILTER_CALLBACK,
				'options' => 'sanitize_text_field',
			),
		);

		$taxonomy_order = filter_input_array( INPUT_POST, $args );

		if ( ! empty( $taxonomy_order['cld_tax_order'] ) ) {
			// Map to ID's where needed.
			$order = array_map(
				function ( $line ) {
					$parts = explode( ':', $line );
					if ( ! empty( $parts[1] ) && ! is_numeric( $parts[1] ) ) {
						// Tag based, find term ID.
						$line = null;
						$term = get_term_by( 'name', $parts[1], $parts[0] );
						if ( ! empty( $term ) ) {
							$line = $term->taxonomy . ':' . $term->term_id;
						}
					} elseif ( empty( $parts[1] ) ) {
						// strange '0' based section, remove to be safe.
						$line = null;
					}

					return $line;
				},
				$taxonomy_order['cld_tax_order']
			);
			$order = array_filter( $order );
			update_post_meta( $post_id, self::META_ORDER_KEY . '_terms', $order );
		} else {
			delete_post_meta( $post_id, self::META_ORDER_KEY . '_terms' );
		}
		if ( ! empty( $taxonomy_order['cld_apply_type'] ) ) {
			update_post_meta( $post_id, self::META_APPLY_KEY . '_terms', $taxonomy_order['cld_apply_type'] );
		} else {
			delete_post_meta( $post_id, self::META_APPLY_KEY . '_terms' );
		}
	}

	/**
	 * Register meta for featured image transformations overwriting.
	 *
	 * @return void
	 */
	public function register_featured_overwrite() {
		register_meta(
			'post',
			self::META_FEATURED_IMAGE_KEY,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'default'       => false,
				'type'          => 'boolean',
				'description'   => esc_html__( 'Flag on whether transformation should be overwritten for a featured image.', 'cloudinary' ),
				'auth_callback' => function () {
					return Utils::user_can( 'override_transformation', 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Add checkbox to override transformations for featured image.
	 *
	 * @param string $content       The content to be saved.
	 * @param int    $post_id       The post ID.
	 * @param int    $attachment_id The ID of the attachment.
	 *
	 * @return string
	 */
	public function classic_overwrite_transformations_featured_image( $content, $post_id, $attachment_id ) {
		if ( ! empty( $attachment_id ) ) {
			// Get the current value.
			$field_value = get_post_meta( $post_id, self::META_FEATURED_IMAGE_KEY, true );
			// Add hidden field and checkbox to the HTML.
			$content .= sprintf(
				'<p><label for="%1$s"><input type="hidden" name="%1$s" value="0" /><input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /> %3$s</label></p>',
				esc_attr( self::META_FEATURED_IMAGE_KEY ),
				checked( $field_value, 1, false ),
				esc_html__( 'Overwrite Global Transformations', 'cloudinary' )
			);
		}

		return $content;
	}

	/**
	 * Updates appropriate meta for overwriting transformations of a featured image.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_overwrite_transformations_featured_image( $post_id ) {
		$field_value = filter_input( INPUT_POST, self::META_FEATURED_IMAGE_KEY, FILTER_VALIDATE_BOOLEAN );
		if ( ! is_null( $field_value ) ) {
			update_post_meta( $post_id, self::META_FEATURED_IMAGE_KEY, $field_value );
		}
	}

	/**
	 * Get the current post.
	 *
	 * @return WP_Post|null
	 */
	public function get_current_post() {
		/**
		 * Filter the post ID.
		 *
		 * @hook    cloudinary_current_post_id
		 * @default null
		 *
		 * @return  {WP_Post|null}
		 */
		$post_id = apply_filters( 'cloudinary_current_post_id', null );

		if ( is_null( $post_id ) && ! in_the_loop() ) {
			return null;
		}

		return get_post( $post_id );
	}

	/**
	 * Insert the cloudinary status column.
	 *
	 * @param array $cols Array of columns.
	 *
	 * @return array
	 */
	public function transformations_column( $cols ) {

		$custom = array(
			'cld_transformations' => __( 'Transformation Effects', 'cloudinary' ),
		);
		$offset = array_search( 'parent', array_keys( $cols ), true );
		if ( empty( $offset ) ) {
			$offset = 4; // Default location some where after author, in case another plugin removes parent column.
		}
		$cols = array_slice( $cols, 0, $offset ) + $custom + array_slice( $cols, $offset );

		return $cols;
	}

	/**
	 * Display the Cloudinary Column.
	 *
	 * @param string $column_name   The column name.
	 * @param int    $attachment_id The attachment id.
	 */
	public function transformations_column_value( $column_name, $attachment_id ) {
		if ( 'cld_transformations' === $column_name && $this->media->sync->is_synced( $attachment_id, true ) ) {

			// Transformations are only available for Images and Videos.
			if (
				! in_array(
					$this->media->get_media_type( $attachment_id ),
					array(
						'image',
						'video',
					),
					true
				)
			) {
				return;
			}

			// If asset isn't deliverable, don't show transformations.
			if ( ! $this->media->plugin->get_component( 'delivery' )->is_deliverable( $attachment_id ) ) {
				return;
			}

			$item = $this->media->plugin->get_component( 'assets' )->get_asset( $attachment_id, 'dataset' );
			if ( ! empty( $item['data']['public_id'] ) ) {
				$text            = __( 'Add effects', 'cloudinary' );
				$transformations = Relate::get_transformations( $attachment_id, true );
				$text_overlay    = Relate::get_overlay( $attachment_id, 'text_overlay' );
				$image_overlay   = Relate::get_overlay( $attachment_id, 'image_overlay' );
				$args            = array(
					'page'    => 'cloudinary',
					'section' => 'edit-asset',
					'asset'   => $attachment_id,
				);
				$link            = add_query_arg( $args, 'admin.php' );

				if ( ! empty( $transformations ) || ! empty( $text_overlay ) || ! empty( $image_overlay ) ) {
					$text = __( 'Edit effects', 'cloudinary' );
				}
				?>
				<a href="<?php echo esc_url( $link ); ?>" class="cld_transformations__icons">
					<span class="cld_transformations__icon
					<?php
					if ( ! empty( $transformations ) ) {
						echo 'cld_transformations__icon--active';}
					?>
					">
						<svg viewBox="0 0 13 15" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M1.23333 14.1C0.888889 14.1 0.597222 13.9806 0.358333 13.7417C0.119444 13.5028 0 13.2111 0 12.8667C0 12.5222 0.119444 12.2306 0.358333 11.9917C0.597222 11.7528 0.888889 11.6333 1.23333 11.6333H11.7667C12.1111 11.6333 12.4028 11.7528 12.6417 11.9917C12.8806 12.2306 13 12.5222 13 12.8667C13 13.2111 12.8806 13.5028 12.6417 13.7417C12.4028 13.9806 12.1111 14.1 11.7667 14.1H1.23333ZM3 9.33333C2.73333 9.33333 2.54167 9.25278 2.425 9.09167C2.30833 8.93056 2.3 8.72778 2.4 8.48333L5.51667 0.683333C5.59444 0.494444 5.72778 0.333333 5.91667 0.2C6.10556 0.0666667 6.30556 0 6.51667 0C6.72778 0 6.92778 0.0666667 7.11667 0.2C7.30556 0.333333 7.43889 0.494444 7.51667 0.683333L10.6 8.46667C10.7 8.71111 10.6944 8.91667 10.5833 9.08333C10.4722 9.25 10.2778 9.33333 10 9.33333C9.87778 9.33333 9.75833 9.29444 9.64167 9.21667C9.525 9.13889 9.44445 9.03889 9.4 8.91667L8.61667 6.88333H4.36667L3.58333 8.93333C3.53889 9.04444 3.46389 9.13889 3.35833 9.21667C3.25278 9.29444 3.13333 9.33333 3 9.33333ZM4.78333 5.76667H8.2L6.53333 1.33333H6.46667L4.78333 5.76667Z" fill="currentColor"/>
						</svg>
					</span>

					<span class="cld_transformations__icon
					<?php
					if ( ! empty( $text_overlay ) ) {
						echo 'cld_transformations__icon--active';}
					?>
					">
						<svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M10.8667 9.86667V3.83333H4.83333V2.7H10.8667C11.1778 2.7 11.4444 2.81111 11.6667 3.03333C11.8889 3.25556 12 3.52222 12 3.83333V9.86667H10.8667ZM11.4333 14.7C11.2667 14.7 11.1306 14.6472 11.025 14.5417C10.9194 14.4361 10.8667 14.3 10.8667 14.1333V12H3.83333C3.52222 12 3.25556 11.8889 3.03333 11.6667C2.81111 11.4444 2.7 11.1778 2.7 10.8667V3.83333H0.566667C0.4 3.83333 0.263889 3.77778 0.158333 3.66667C0.0527777 3.55556 0 3.42222 0 3.26667C0 3.1 0.0527777 2.96389 0.158333 2.85833C0.263889 2.75278 0.4 2.7 0.566667 2.7H2.7V0.566667C2.7 0.4 2.75556 0.263889 2.86667 0.158333C2.97778 0.0527777 3.11111 0 3.26667 0C3.43333 0 3.56944 0.0527777 3.675 0.158333C3.78056 0.263889 3.83333 0.4 3.83333 0.566667V10.8667H14.1333C14.3 10.8667 14.4361 10.9222 14.5417 11.0333C14.6472 11.1444 14.7 11.2778 14.7 11.4333C14.7 11.6 14.6472 11.7361 14.5417 11.8417C14.4361 11.9472 14.3 12 14.1333 12H12V14.1333C12 14.3 11.9444 14.4361 11.8333 14.5417C11.7222 14.6472 11.5889 14.7 11.4333 14.7Z" fill="currentColor"/>
						</svg>
					</span>

					<span class="cld_transformations__icon
					<?php
					if ( ! empty( $image_overlay ) ) {
						echo 'cld_transformations__icon--active';}
					?>
					">
						<svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M13.7 8.47283C13.7 8.7795 13.587 9.04539 13.361 9.2705C13.1351 9.49572 12.8683 9.60833 12.5605 9.60833L5.22717 9.60833C4.9205 9.60833 4.65461 9.49572 4.4295 9.2705C4.20428 9.04539 4.09167 8.7795 4.09167 8.47283L4.09167 1.1395C4.09167 0.831722 4.20428 0.564889 4.4295 0.339C4.65461 0.113 4.9205 3.71033e-07 5.22717 3.57628e-07L12.5605 0C12.8683 -1.34534e-08 13.1351 0.112999 13.361 0.338999C13.587 0.564888 13.7 0.831722 13.7 1.1395V8.47283ZM12.5605 8.47283V1.1395L5.22717 1.1395L5.22717 8.47283L12.5605 8.47283ZM11.2262 11.6522L3.18333 11.6522C2.87556 11.6522 2.60872 11.5396 2.38283 11.3143C2.15683 11.0892 2.04383 10.8233 2.04383 10.5167L2.04383 2.47383C2.04383 2.31361 2.09944 2.17894 2.21067 2.06983C2.32189 1.96072 2.45794 1.90617 2.61883 1.90617C2.77972 1.90617 2.914 1.96072 3.02167 2.06983C3.12944 2.17894 3.18333 2.31361 3.18333 2.47383L3.18333 10.5167H11.2262C11.3864 10.5167 11.5211 10.5715 11.6302 10.6812C11.7393 10.7908 11.7938 10.9262 11.7938 11.0873C11.7938 11.2484 11.7393 11.3828 11.6302 11.4905C11.5211 11.5983 11.3864 11.6522 11.2262 11.6522ZM9.22617 13.7H1.1395C0.831722 13.7 0.56489 13.587 0.339001 13.361C0.113001 13.1351 3.71081e-07 12.8683 3.57628e-07 12.5605L0 4.47383C-7.00354e-09 4.31361 0.0548332 4.17894 0.1645 4.06983C0.274167 3.96072 0.410222 3.90617 0.572666 3.90617C0.735111 3.90617 0.870166 3.96072 0.977833 4.06983C1.08561 4.17894 1.1395 4.31361 1.1395 4.47383L1.1395 12.5605L9.22617 12.5605C9.38639 12.5605 9.52106 12.6153 9.63017 12.725C9.73928 12.8347 9.79383 12.9707 9.79383 13.1332C9.79383 13.2956 9.73928 13.4307 9.63017 13.5383C9.52106 13.6461 9.38639 13.7 9.22617 13.7Z" fill="currentColor"/>
							<path d="M9.17951 6.5152L8.35434 5.4027C8.30179 5.3417 8.23729 5.31053 8.16084 5.3092C8.0844 5.30787 8.02106 5.33787 7.97084 5.3992L7.13034 6.51853C7.07734 6.60209 7.07179 6.68631 7.11368 6.7712C7.15545 6.85609 7.2234 6.89853 7.31751 6.89853H11.7748C11.8635 6.89853 11.9301 6.85609 11.9747 6.7712C12.0192 6.68631 12.0151 6.60209 11.9622 6.51853L10.7668 4.93553C10.7155 4.87487 10.652 4.84453 10.5763 4.84453C10.5007 4.84453 10.4377 4.8752 10.3875 4.93653L9.17951 6.5152Z" fill="currentColor"/>
						</svg>
					</span>
				</a>

				<a href="<?php echo esc_url( $link ); ?>" data-transformation-item="<?php echo esc_attr( wp_json_encode( $item ) ); ?>"><?php echo esc_html( $text ); ?></a>

				<?php
			}
		}
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup_hooks() {
		$taxonomies = get_taxonomies( array( 'show_ui' => true ) );
		$global     = $this;
		array_map(
			function ( $taxonomy ) use ( $global ) {
				add_action( $taxonomy . '_add_form_fields', array( $global, 'add_taxonomy_fields' ) );
				add_action( $taxonomy . '_edit_form_fields', array( $global, 'edit_taxonomy_fields' ) );
				add_action( 'create_' . $taxonomy, array( $global, 'save_taxonomy_custom_meta' ) );
				add_action( 'edited_' . $taxonomy, array( $global, 'save_taxonomy_custom_meta' ) );
			},
			$taxonomies
		);

		// Add ordering metaboxes and featured overwrite.
		add_action( 'add_meta_boxes', array( $this, 'taxonomy_ordering' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_taxonomy_ordering' ), 10, 1 );
		add_action( 'save_post', array( $this, 'save_overwrite_transformations_featured_image' ), 10, 3 );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'classic_overwrite_transformations_featured_image' ), 10, 3 );

		// Filter and action the custom column.
		add_filter( 'manage_media_columns', array( $this, 'transformations_column' ), 11 );
		add_action( 'manage_media_custom_column', array( $this, 'transformations_column_value' ), 10, 2 );

		// Register Meta.
		$this->register_featured_overwrite();
	}
}
