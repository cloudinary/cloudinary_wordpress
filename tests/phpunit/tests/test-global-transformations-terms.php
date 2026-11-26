<?php
/**
 * Tests for the term transformations of Cloudinary\Media\Global_Transformations.
 *
 * @package Cloudinary
 */

use Cloudinary\Media\Global_Transformations;

/**
 * Covers the term meta save hooks and Global_Transformations::get_terms().
 */
class Test_Global_Transformations_Terms extends WP_UnitTestCase {

	/**
	 * The Global_Transformations instance under test.
	 *
	 * The plugin only creates it once connected to Cloudinary, so the test builds its own. Its hooks
	 * are restored after each test.
	 *
	 * @var Global_Transformations
	 */
	protected $global_transformations;

	/**
	 * Build the Global_Transformations instance.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->global_transformations = new Global_Transformations( \Cloudinary\get_plugin_instance()->get_component( 'media' ) );
	}

	/**
	 * Get the Global_Transformations instance under test.
	 *
	 * @return Global_Transformations
	 */
	protected function get_global_transformations() {
		return $this->global_transformations;
	}

	/**
	 * The term transformations are saved on edit_{$taxonomy}, before edited_term and saved_term,
	 * so plugins syncing term meta on those hooks (e.g. WPML) get the new values.
	 *
	 * @return void
	 */
	public function test_term_meta_saved_before_edited_term() {
		$callback = array( $this->get_global_transformations(), 'save_taxonomy_custom_meta' );

		$this->assertNotFalse( has_action( 'create_category', $callback ) );
		$this->assertNotFalse( has_action( 'edit_category', $callback ) );
		$this->assertFalse( has_action( 'edited_category', $callback ) );
	}

	/**
	 * The stored term ordering is returned as is without contextualization.
	 *
	 * @return void
	 */
	public function test_get_terms_returns_stored_terms() {
		$post_id = self::factory()->post->create();
		$first   = self::factory()->category->create();
		$second  = self::factory()->category->create();
		update_post_meta( $post_id, Global_Transformations::META_ORDER_KEY . '_terms', array( 'category:' . $second, 'category:' . $first ) );

		$terms = array_values( $this->get_global_transformations()->get_terms( $post_id ) );

		$this->assertSame( array( $second, $first ), array_map( 'intval', wp_list_pluck( wp_list_pluck( $terms, 'term' ), 'term_id' ) ) );
		$this->assertSame( array( 'category:' . $second, 'category:' . $first ), wp_list_pluck( $terms, 'value' ) );
	}

	/**
	 * Stored terms are contextualized for the post, in the stored order, e.g. to their translations
	 * when the ordering was copied from the original post.
	 *
	 * @return void
	 */
	public function test_get_terms_contextualizes_stored_terms() {
		$post_id           = self::factory()->post->create();
		$first             = self::factory()->category->create();
		$second            = self::factory()->category->create();
		$second_translated = self::factory()->category->create();
		update_post_meta( $post_id, Global_Transformations::META_ORDER_KEY . '_terms', array( 'category:' . $second, 'category:' . $first ) );

		$filter = static function ( $term, $filtered_post_id ) use ( $post_id, $second, $second_translated ) {
			if ( $post_id === $filtered_post_id && $second === $term->term_id ) {
				return get_term( $second_translated, 'category' );
			}

			return $term;
		};
		add_filter( 'cloudinary_contextualized_term', $filter, 10, 2 );

		$terms = array_values( $this->get_global_transformations()->get_terms( $post_id ) );

		$this->assertSame( array( $second_translated, $first ), array_map( 'intval', wp_list_pluck( wp_list_pluck( $terms, 'term' ), 'term_id' ) ) );
		$this->assertSame( array( 'category:' . $second_translated, 'category:' . $first ), wp_list_pluck( $terms, 'value' ) );
	}

	/**
	 * A filter returning something other than a term keeps the stored term.
	 *
	 * @return void
	 */
	public function test_get_terms_ignores_invalid_contextualized_term() {
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create();
		update_post_meta( $post_id, Global_Transformations::META_ORDER_KEY . '_terms', array( 'category:' . $term_id ) );

		add_filter( 'cloudinary_contextualized_term', '__return_null' );

		$terms = array_values( $this->get_global_transformations()->get_terms( $post_id ) );

		$this->assertSame( $term_id, $terms[0]['term']->term_id );
	}
}
