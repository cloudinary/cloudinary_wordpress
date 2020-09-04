<?php
/**
 * Global Transformations class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

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
	 * Holds the fields defined in settings.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	private $fields;

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
	 * Global Transformations constructor.
	 *
	 * @param \Cloudinary\Media $media The plugin.
	 */
	public function __construct( \Cloudinary\Media $media ) {
		$this->media            = $media;
		$settings               = $this->media->plugin->components['settings']->get_ui();
		$this->globals['image'] = $this->media->plugin->config['settings']['global_transformations'];
		$this->globals['video'] = $this->media->plugin->config['settings']['global_video_transformations'];
		$image_fields           = array_filter(
			$settings['pages']['global_transformation']['tabs']['global_transformations']['fields'],
			function ( $field ) {
				return ! empty( $field['contextual'] );
			}
		);

		$video_fields = array_filter(
			$settings['pages']['global_transformation']['tabs']['global_video_transformations']['fields'],
			function ( $field ) {
				return ! empty( $field['contextual'] );
			}
		);

		$this->fields = array_merge( $image_fields, $video_fields );

		$this->setup_hooks();
	}

	/**
	 * Add fields to Add taxonomy term screen.
	 */
	public function add_taxonomy_fields() {
		$template_file = $this->media->plugin->template_path . 'taxonomy-transformation-fields.php';
		if ( file_exists( $template_file ) ) {
			include $template_file; // phpcs:ignore
		}
	}

	/**
	 * Add fields to Edit taxonomy term screen.
	 *
	 * @param \WP_Term $term The tern being edited.
	 */
	public function edit_taxonomy_fields( $term ) {
		$template_file = $this->media->plugin->template_path . 'taxonomy-term-transformation-fields.php';
		if ( file_exists( $template_file ) ) {
			include $template_file; // phpcs:ignore
		}
	}

	/**
	 * Save the meta data for the term.
	 *
	 * @param int $term_id The term ID.
	 */
	public function save_taxonomy_custom_meta( $term_id ) {

		$setting_slug = $this->media->plugin->components['settings']->setting_slug();
		$args         = array(
			$setting_slug => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_REQUIRE_ARRAY,
			),
		);
		$input        = filter_input_array( INPUT_POST, $args );
		foreach ( $this->fields as $field_slug => $field ) {
			if ( ! isset( $field['contextual'] ) || ! isset( $input[ $setting_slug ][ $field_slug ] ) ) {
				continue;
			}
			$value = $input[ $setting_slug ][ $field_slug ];
			if ( ! empty( $field['choices'] ) && ! isset( $field['choices'][ $value ] ) ) {
				$value = null; // Reset the value if not a choice.
			}

			// Update the metadata.
			$meta_key = self::META_ORDER_KEY . '_' . $field_slug;
			update_term_meta( $term_id, $meta_key, $value );
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
		$keys      = array_keys( $this->fields );
		$meta_data = array();
		foreach ( $keys as $key ) {
			$meta_key          = self::META_ORDER_KEY . '_' . $key;
			$meta_data[ $key ] = get_term_meta( $term_id, $meta_key, true );
		}

		// Clear out empty items.
		$meta_data = array_filter( $meta_data );

		return $meta_data;
	}

	/**
	 * Get the transformations.
	 *
	 * @param string $type The type to get.
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
						$term_id         = filter_input( INPUT_GET, 'tag_ID', FILTER_DEFAULT );
						$transformations = $this->get_term_transformations( $term_id, $type );
						break;
					case 'toplevel_page_cloudinary':
						$transformations = $this->globals[ $type ];
						break;
					default:
						$transformations = array_map(
							function ( $value ) {
								return null;
							},
							$this->globals[ $type ]
						);

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
	 * @return array
	 */
	public function get_taxonomy_transformations( $type ) {
		$return_transformations = '';
		if ( in_the_loop() ) {
			$post = get_post();
			if ( ! empty( $post ) ) {
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
		}

		return $return_transformations;
	}

	/**
	 * Check if the image has a post taxonomy overwrite.
	 *
	 * @return bool
	 */
	public function is_taxonomy_overwrite() {
		$apply_type = false;
		if ( in_the_loop() ) {
			$post       = get_post();
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
			add_meta_box( 'cld-taxonomy-order', __( 'Taxonomy Order', 'cloudinary' ), array( $this, 'render_ordering_box' ), null, 'side', 'core' );
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

		$out   = array();
		$out[] = '<div class="cld-tax-order">';
		$out[] = '<ul class="cld-tax-order-list" id="cld-tax-items">';
		$out[] = '<li class="cld-tax-order-list-item no-items">' . esc_html__( 'No terms added', 'cloudinary' ) . '</li>';
		$terms = $this->get_terms( $post->ID );
		if ( ! empty( $terms ) ) {
			foreach ( (array) $terms as $item ) {
				$out[] = $this->make_term_sort_item( $item['value'], $item['term']->name );
			}
		}
		$out[] = '</ul>';
		// Get apply Type.
		$type  = get_post_meta( $post->ID, self::META_APPLY_KEY . '_terms', true );
		$out[] = '<label class="cld-tax-order-list-type"><input ' . checked( 'overwrite', $type, false ) . ' type="checkbox" value="overwrite" name="cld_apply_type" />' . __( 'Overwrite', 'cloudinary' ) . '</label>';
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
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_REQUIRE_ARRAY,
			),
			'cld_apply_type' => array(
				'filter' => FILTER_SANITIZE_STRING,
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
					return current_user_can( 'edit_posts' );
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
				esc_html__( 'Overwrite Transformations', 'cloudinary' )
			);
		}

		return $content;
	}

	/**
	 * Updates appropriate meta for overwriting transformations of a featured image.
	 *
	 * @param int $post_id
	 */
	public function save_overwrite_transformations_featured_image( $post_id ) {
		$field_value = filter_input( INPUT_POST, self::META_FEATURED_IMAGE_KEY, FILTER_VALIDATE_BOOLEAN );
		if ( ! is_null( $field_value ) ) {
			update_post_meta( $post_id, self::META_FEATURED_IMAGE_KEY, $field_value );
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

		// Register Meta.
		$this->register_featured_overwrite();
	}
}
