<?php
/**
 * Tests for the contextualization of Cloudinary Gallery block assets.
 *
 * The gallery block stores the attachment ID and public ID of each asset in its attributes. When
 * a multilingual plugin (e.g. WPML Media Translation) maps an attachment to a different one for
 * the current language, the gallery must deliver that one instead, like core blocks do.
 *
 * The Media dependency is stubbed so no Cloudinary account is needed.
 *
 * @package Cloudinary
 */

/**
 * Covers Gallery::contextualize_asset() and its use in Gallery::prepare_block_render().
 */
class Test_Gallery_Contextualize_Asset extends WP_UnitTestCase {

	/**
	 * The original attachment ID.
	 *
	 * @var int
	 */
	protected $original_id;

	/**
	 * The contextualized (e.g. translated) attachment ID.
	 *
	 * @var int
	 */
	protected $translated_id;

	/**
	 * Create the attachments.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->original_id   = self::factory()->attachment->create_object( 'original.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		$this->translated_id = self::factory()->attachment->create_object( 'translated.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
	}

	/**
	 * The gallery asset, as stored in the block attributes.
	 *
	 * @return array
	 */
	protected function get_asset() {
		return array(
			'attachmentId'   => $this->original_id,
			'publicId'       => 'original',
			'transformation' => array( 'transformation' => array( array( 'effect' => 'grayscale' ) ) ),
		);
	}

	/**
	 * Build a gallery wired to a stubbed media component.
	 *
	 * @param array $deliverable     Map of attachment ID => public ID for attachments that can be delivered from Cloudinary.
	 * @param array $transformations Map of attachment ID => transformations.
	 *
	 * @return Test_Gallery_Contextualize_Asset_Gallery
	 */
	protected function get_gallery( array $deliverable, array $transformations = array() ) {
		$media                  = ( new ReflectionClass( Test_Gallery_Contextualize_Asset_Media::class ) )->newInstanceWithoutConstructor();
		$media->public_ids      = $deliverable;
		$media->transformations = $transformations;

		$gallery        = ( new ReflectionClass( Test_Gallery_Contextualize_Asset_Gallery::class ) )->newInstanceWithoutConstructor();
		$gallery->media = $media;
		$gallery->set_plugin( \Cloudinary\get_plugin_instance() );

		return $gallery;
	}

	/**
	 * Map the original attachment to the given ID through the contextualization filter.
	 *
	 * @param int|null $id The ID the filter returns for the original attachment.
	 *
	 * @return void
	 */
	protected function contextualize_to( $id ) {
		$original_id = $this->original_id;
		add_filter(
			'cloudinary_contextualized_post_id',
			static function ( $post_id ) use ( $original_id, $id ) {
				return $original_id === (int) $post_id ? $id : $post_id;
			}
		);
	}

	/**
	 * Render a gallery block with the stored asset, and return the assets passed to the widget.
	 *
	 * @param Test_Gallery_Contextualize_Asset_Gallery $gallery The gallery.
	 *
	 * @return array
	 */
	protected function render_media_assets( $gallery ) {
		$block = array(
			'blockName' => 'cloudinary/gallery',
			'attrs'     => array( 'selectedImages' => array( $this->get_asset() ) ),
		);

		$output = $gallery->prepare_block_render( '', $block );
		$this->assertSame( 1, preg_match( '/var attributes = (.*);/', $output, $matches ) );

		$attributes = json_decode( $matches[1], true );

		return $attributes['mediaAssets'];
	}

	/**
	 * Without any contextualization, the asset is untouched.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_without_contextualization() {
		$gallery = $this->get_gallery( array( $this->original_id => 'original' ) );

		$this->assertSame( $this->get_asset(), $gallery->contextualize_asset( $this->get_asset() ) );
	}

	/**
	 * A synced contextualized attachment replaces the stored one, and the stored transformations
	 * of the original are dropped so they get rebuilt for the new attachment.
	 *
	 * @return void
	 */
	public function test_asset_swapped_for_synced_contextualized_attachment() {
		$this->contextualize_to( $this->translated_id );
		$gallery = $this->get_gallery(
			array(
				$this->original_id   => 'original',
				$this->translated_id => 'translated',
			)
		);

		$this->assertSame(
			array(
				'attachmentId' => $this->translated_id,
				'publicId'     => 'translated',
			),
			$gallery->contextualize_asset( $this->get_asset() )
		);
	}

	/**
	 * A contextualized attachment that can't be delivered from Cloudinary yet (e.g. its file isn't
	 * synced) keeps the original asset, like the rest of the delivery.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_when_contextualized_attachment_not_deliverable() {
		$this->contextualize_to( $this->translated_id );
		$gallery = $this->get_gallery( array( $this->original_id => 'original' ) );

		$this->assertSame( $this->get_asset(), $gallery->contextualize_asset( $this->get_asset() ) );
	}

	/**
	 * WPML returns null for an attachment without a translation; the original asset is kept.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_when_no_contextualized_attachment() {
		$this->contextualize_to( null );
		$gallery = $this->get_gallery( array( $this->original_id => 'original' ) );

		$this->assertSame( $this->get_asset(), $gallery->contextualize_asset( $this->get_asset() ) );
	}

	/**
	 * The rendered gallery delivers the contextualized attachment with its own transformations.
	 *
	 * @return void
	 */
	public function test_render_delivers_contextualized_attachment_with_its_transformations() {
		$this->contextualize_to( $this->translated_id );
		$sepia   = array( array( 'effect' => 'sepia' ) );
		$gallery = $this->get_gallery(
			array(
				$this->original_id   => 'original',
				$this->translated_id => 'translated',
			),
			array( $this->translated_id => $sepia )
		);

		$this->assertSame(
			array(
				array(
					'attachmentId'   => $this->translated_id,
					'publicId'       => 'translated',
					'transformation' => array( 'transformation' => $sepia ),
				),
			),
			$this->render_media_assets( $gallery )
		);
	}

	/**
	 * The stored transformations of the original attachment don't leak to the contextualized one.
	 *
	 * @return void
	 */
	public function test_render_drops_original_transformations_for_contextualized_attachment() {
		$this->contextualize_to( $this->translated_id );
		$gallery = $this->get_gallery(
			array(
				$this->original_id   => 'original',
				$this->translated_id => 'translated',
			)
		);

		$this->assertSame(
			array(
				array(
					'attachmentId' => $this->translated_id,
					'publicId'     => 'translated',
				),
			),
			$this->render_media_assets( $gallery )
		);
	}

	/**
	 * Without contextualization, the rendered gallery delivers the stored asset.
	 *
	 * @return void
	 */
	public function test_render_delivers_stored_asset_without_contextualization() {
		$gallery = $this->get_gallery( array( $this->original_id => 'original' ) );

		$this->assertSame( array( $this->get_asset() ), $this->render_media_assets( $gallery ) );
	}
}

/**
 * Exposes Gallery::contextualize_asset() for testing, and skips enqueuing the gallery library.
 */
class Test_Gallery_Contextualize_Asset_Gallery extends \Cloudinary\Media\Gallery {

	/**
	 * Set the plugin instance.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin instance.
	 *
	 * @return void
	 */
	public function set_plugin( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Expose the protected method.
	 *
	 * @param array $attachment The gallery asset.
	 *
	 * @return array
	 */
	public function contextualize_asset( $attachment ) {
		return parent::contextualize_asset( $attachment );
	}

	/**
	 * Don't enqueue the gallery library.
	 *
	 * @return void
	 */
	public function enqueue_gallery_library() {
	}
}

/**
 * Media stub with in-memory public IDs and transformations.
 */
class Test_Gallery_Contextualize_Asset_Media extends \Cloudinary\Media {

	/**
	 * Map of attachment ID => public ID.
	 *
	 * @var array
	 */
	public $public_ids = array();

	/**
	 * Map of attachment ID => transformations.
	 *
	 * @var array
	 */
	public $transformations = array();

	/**
	 * Check if the attachment has a public ID.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function has_public_id( $attachment_id ) {
		return isset( $this->public_ids[ $attachment_id ] );
	}

	/**
	 * Get the attachment public ID.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $suffixed      Unused.
	 *
	 * @return string|null
	 */
	public function get_public_id( $attachment_id, $suffixed = false ) {
		return isset( $this->public_ids[ $attachment_id ] ) ? $this->public_ids[ $attachment_id ] : null;
	}

	/**
	 * Get the Cloudinary ID, if the attachment can be delivered from Cloudinary.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string|false
	 */
	public function cloudinary_id( $attachment_id ) {
		return isset( $this->public_ids[ $attachment_id ] ) ? $this->public_ids[ $attachment_id ] . '.jpg' : false;
	}

	/**
	 * Get the attachment transformations.
	 *
	 * @param int   $attachment_id             The attachment ID.
	 * @param array $transformations           Unused.
	 * @param bool  $overwrite_transformations Unused.
	 *
	 * @return array
	 */
	public function get_transformations( $attachment_id, $transformations = array(), $overwrite_transformations = false ) {
		return isset( $this->transformations[ $attachment_id ] ) ? $this->transformations[ $attachment_id ] : array();
	}
}

