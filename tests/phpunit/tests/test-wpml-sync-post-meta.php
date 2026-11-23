<?php
/**
 * Tests for Cloudinary\Integrations\WPML::sync_post_meta().
 *
 * WPML isn't installed in the test environment, so its wpml_original_element_id filter and
 * wpml_sync_custom_field action are stubbed.
 *
 * @package Cloudinary
 */

use Cloudinary\Integrations\WPML;
use Cloudinary\Media\Global_Transformations;

/**
 * Covers WPML::sync_post_meta().
 */
class Test_WPML_Sync_Post_Meta extends WP_UnitTestCase {

	/**
	 * The original post ID.
	 *
	 * @var int
	 */
	protected $original_id;

	/**
	 * The translated post ID.
	 *
	 * @var int
	 */
	protected $translation_id;

	/**
	 * The integration under test.
	 *
	 * @var WPML
	 */
	protected $wpml;

	/**
	 * The wpml_sync_custom_field calls, as [ post ID, meta key ].
	 *
	 * @var array
	 */
	protected $synced = array();

	/**
	 * Set up the posts and the WPML stubs.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->original_id    = self::factory()->post->create();
		$this->translation_id = self::factory()->post->create();
		// WPML isn't active, so the constructor doesn't register any hook.
		$this->wpml   = new WPML( \Cloudinary\get_plugin_instance() );
		$this->synced = array();

		$original_id = $this->original_id;
		add_filter(
			'wpml_original_element_id',
			static function () use ( $original_id ) {
				return $original_id;
			}
		);
		add_action(
			'wpml_sync_custom_field',
			function ( $post_id, $meta_key ) {
				$this->synced[] = array( $post_id, $meta_key );
			},
			10,
			2
		);
	}

	/**
	 * A change of the flag on an original post is synced to its translations.
	 *
	 * @return void
	 */
	public function test_flag_change_on_original_is_synced() {
		$this->wpml->sync_post_meta( 1, $this->original_id, Global_Transformations::META_FEATURED_IMAGE_KEY );

		$this->assertSame( array( array( $this->original_id, Global_Transformations::META_FEATURED_IMAGE_KEY ) ), $this->synced );
	}

	/**
	 * A deletion passes an array of meta IDs, and is synced too.
	 *
	 * @return void
	 */
	public function test_flag_deletion_on_original_is_synced() {
		$this->wpml->sync_post_meta( array( 1, 2 ), $this->original_id, Global_Transformations::META_FEATURED_IMAGE_KEY );

		$this->assertCount( 1, $this->synced );
	}

	/**
	 * A change on a translation isn't synced: translations get the value of their original.
	 *
	 * @return void
	 */
	public function test_flag_change_on_translation_is_not_synced() {
		$this->wpml->sync_post_meta( 1, $this->translation_id, Global_Transformations::META_FEATURED_IMAGE_KEY );

		$this->assertSame( array(), $this->synced );
	}

	/**
	 * Other meta keys are left to WPML.
	 *
	 * @return void
	 */
	public function test_other_meta_is_not_synced() {
		$this->wpml->sync_post_meta( 1, $this->original_id, 'some_other_meta' );

		$this->assertSame( array(), $this->synced );
	}

	/**
	 * A meta write on something that isn't a post is ignored.
	 *
	 * @return void
	 */
	public function test_unknown_post_is_not_synced() {
		$this->wpml->sync_post_meta( 1, 0, Global_Transformations::META_FEATURED_IMAGE_KEY );

		$this->assertSame( array(), $this->synced );
	}

	/**
	 * Meta writes made while syncing don't start another sync.
	 *
	 * @return void
	 */
	public function test_writes_during_sync_do_not_sync_again() {
		$wpml        = $this->wpml;
		$original_id = $this->original_id;
		add_action(
			'wpml_sync_custom_field',
			static function ( $post_id, $meta_key ) use ( $wpml, $original_id ) {
				$wpml->sync_post_meta( 2, $original_id, $meta_key );
			},
			20,
			2
		);

		$this->wpml->sync_post_meta( 1, $this->original_id, Global_Transformations::META_FEATURED_IMAGE_KEY );

		$this->assertCount( 1, $this->synced );
	}
}
