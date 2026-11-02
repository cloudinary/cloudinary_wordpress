<?php
/**
 * Tests for Cloudinary\Delivery::create_delivery().
 *
 * An asset imported from the DAM with crops/effects stages those transformations in post
 * meta until it gets a delivery relation. create_size_relation() migrates them into the new row and
 * deletes the post meta, but create_delivery() then "restored" what it had captured before the
 * row existed (nothing), through a relationship save deferred to shutdown, overwriting the
 * migrated value with null. The transformations were lost for good.
 *
 * Recreating the relation of an asset that already has one must also keep its transformations and
 * overlays, each in its own column, rather than baking the overlays into the transformations.
 *
 * The shutdown save is run by hand, as it would be at the end of the request.
 *
 * @package Cloudinary
 */

use Cloudinary\Relate\Relationship;
use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Covers Delivery::create_delivery().
 */
class Test_Delivery_Create_Delivery extends WP_UnitTestCase {

	/**
	 * The attachment under test.
	 *
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * Create the relationship table and an attachment with a public ID.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( str_replace( 'CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', Utils::get_table_sql() ) ); // phpcs:ignore WordPress.DB

		$this->attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->get_media()->update_post_meta( $this->attachment_id, Sync::META_KEYS['public_id'], 'canola' );
	}

	/**
	 * Get the Media component.
	 *
	 * @return \Cloudinary\Media
	 */
	protected function get_media() {
		return \Cloudinary\get_plugin_instance()->get_component( 'media' );
	}

	/**
	 * Build a Delivery instance wired to the plugin's Media component and a stubbed Sync.
	 *
	 * @return Test_Delivery_Create_Delivery_Delivery
	 */
	protected function get_delivery() {
		$plugin   = \Cloudinary\get_plugin_instance();
		$delivery = new Test_Delivery_Create_Delivery_Delivery( $plugin );
		$delivery->set_components( $this->get_media(), new Test_Delivery_Create_Delivery_Sync() );

		return $delivery;
	}

	/**
	 * Run the relationship save that is deferred to shutdown.
	 *
	 * @return void
	 */
	protected function run_shutdown_save() {
		Relationship::get_relationship( $this->attachment_id )->do_save();
	}

	/**
	 * Read the attachment's relation row straight from the table.
	 *
	 * @return array|null
	 */
	protected function get_row() {
		global $wpdb;
		$table = Utils::get_relationship_table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $this->attachment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Transformations staged in post meta by a DAM import end up in the new relation.
	 *
	 * @return void
	 */
	public function test_keeps_staged_transformations_of_a_new_relation() {
		$this->get_media()->update_post_meta( $this->attachment_id, Sync::META_KEYS['transformation'], array( array( 'effect' => 'sepia' ) ) );

		$this->get_delivery()->create_delivery( $this->attachment_id );
		$this->run_shutdown_save();

		$row = $this->get_row();
		$this->assertSame( 'e_sepia', $row['transformations'] );
		$this->assertEmpty( $this->get_media()->get_post_meta( $this->attachment_id, Sync::META_KEYS['transformation'], true ) );
	}

	/**
	 * The relation is recreated several times in one request when the attachment is used more than
	 * once on the page; the staged transformations, consumed by the first call, must survive the rest.
	 *
	 * @return void
	 */
	public function test_keeps_staged_transformations_when_recreated_in_the_same_request() {
		$this->get_media()->update_post_meta( $this->attachment_id, Sync::META_KEYS['transformation'], array( array( 'effect' => 'sepia' ) ) );

		$delivery = $this->get_delivery();
		$delivery->create_delivery( $this->attachment_id );
		$delivery->create_delivery( $this->attachment_id );
		$delivery->create_delivery( $this->attachment_id );
		$this->run_shutdown_save();

		$this->assertSame( 'e_sepia', $this->get_row()['transformations'] );
	}

	/**
	 * Recreating an existing relation keeps its transformations and overlays in their own columns.
	 *
	 * @return void
	 */
	public function test_keeps_transformations_and_overlays_of_an_existing_relation() {
		$text_overlay  = wp_json_encode( array( 'transformation' => 'l_text:Arial_20:Hello/fl_layer_apply' ) );
		$image_overlay = wp_json_encode( array( 'transformation' => 'l_logo/fl_layer_apply' ) );

		$delivery = $this->get_delivery();
		$delivery->create_delivery( $this->attachment_id );
		$relationship                  = Relationship::get_relationship( $this->attachment_id );
		$relationship->transformations = 'e_sepia';
		$relationship->text_overlay    = $text_overlay;
		$relationship->image_overlay   = $image_overlay;
		$this->run_shutdown_save();

		$delivery->create_delivery( $this->attachment_id );
		$this->run_shutdown_save();

		$row = $this->get_row();
		$this->assertSame( 'e_sepia', $row['transformations'] );
		$this->assertSame( $text_overlay, $row['text_overlay'] );
		$this->assertSame( $image_overlay, $row['image_overlay'] );
	}

	/**
	 * Transformations newly staged in post meta replace those of the existing relation.
	 *
	 * @return void
	 */
	public function test_staged_transformations_replace_those_of_an_existing_relation() {
		$delivery = $this->get_delivery();
		$delivery->create_delivery( $this->attachment_id );
		Relationship::get_relationship( $this->attachment_id )->transformations = 'e_sepia';
		$this->run_shutdown_save();

		$this->get_media()->update_post_meta( $this->attachment_id, Sync::META_KEYS['transformation'], array( array( 'effect' => 'grayscale' ) ) );
		$delivery->create_delivery( $this->attachment_id );
		$this->run_shutdown_save();

		$this->assertSame( 'e_grayscale', $this->get_row()['transformations'] );
	}
}

/**
 * Delivery with its components settable, as setup() only runs on a connected plugin.
 */
class Test_Delivery_Create_Delivery_Delivery extends \Cloudinary\Delivery {

	/**
	 * Set the Media and Sync components.
	 *
	 * @param \Cloudinary\Media $media The media component.
	 * @param object            $sync  The sync component.
	 *
	 * @return void
	 */
	public function set_components( $media, $sync ) {
		$this->media = $media;
		$this->sync  = $sync;
	}
}

/**
 * Sync stub, as signatures need the sync types a connected plugin registers.
 */
class Test_Delivery_Create_Delivery_Sync {

	/**
	 * Stubbed to a no-op.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $type          The sync type.
	 *
	 * @return void
	 */
	public function set_signature_item( $attachment_id, $type ) {}
}
