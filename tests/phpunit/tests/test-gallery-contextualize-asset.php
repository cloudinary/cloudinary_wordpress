<?php
/**
 * Tests for Cloudinary\Media\Gallery::contextualize_asset().
 *
 * The gallery block stores the attachment ID and public ID of each asset in its attributes. When
 * a multilingual plugin (e.g. WPML Media Translation) maps an attachment to a different one for
 * the current language, the gallery must deliver that one instead, like core blocks do.
 *
 * The Media dependency is stubbed so no sync or Cloudinary account is needed.
 *
 * @package Cloudinary
 */

/**
 * Covers Gallery::contextualize_asset().
 */
class Test_Gallery_Contextualize_Asset extends WP_UnitTestCase {

	/**
	 * The original attachment ID.
	 *
	 * @var int
	 */
	const ORIGINAL_ID = 10;

	/**
	 * The contextualized (e.g. translated) attachment ID.
	 *
	 * @var int
	 */
	const TRANSLATED_ID = 20;

	/**
	 * The gallery asset, as stored in the block attributes.
	 *
	 * @var array
	 */
	protected $asset = array(
		'attachmentId'   => self::ORIGINAL_ID,
		'publicId'       => 'original',
		'transformation' => array( 'transformation' => array( array( 'effect' => 'grayscale' ) ) ),
	);

	/**
	 * Build a gallery wired to a stubbed media component.
	 *
	 * @param array $public_ids Map of attachment ID => public ID for synced attachments.
	 *
	 * @return Test_Gallery_Contextualize_Asset_Gallery
	 */
	protected function get_gallery( array $public_ids ) {
		$media             = ( new ReflectionClass( Test_Gallery_Contextualize_Asset_Media::class ) )->newInstanceWithoutConstructor();
		$media->public_ids = $public_ids;

		$gallery        = ( new ReflectionClass( Test_Gallery_Contextualize_Asset_Gallery::class ) )->newInstanceWithoutConstructor();
		$gallery->media = $media;

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
		add_filter(
			'cloudinary_contextualized_post_id',
			static function ( $post_id ) use ( $id ) {
				return self::ORIGINAL_ID === (int) $post_id ? $id : $post_id;
			}
		);
	}

	/**
	 * Without any contextualization, the asset is untouched.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_without_contextualization() {
		$gallery = $this->get_gallery( array( self::ORIGINAL_ID => 'original' ) );

		$this->assertSame( $this->asset, $gallery->contextualize_asset( $this->asset ) );
	}

	/**
	 * A synced contextualized attachment replaces the stored one, and the stored transformations
	 * of the original are dropped so they get rebuilt for the new attachment.
	 *
	 * @return void
	 */
	public function test_asset_swapped_for_synced_contextualized_attachment() {
		$this->contextualize_to( self::TRANSLATED_ID );
		$gallery = $this->get_gallery(
			array(
				self::ORIGINAL_ID   => 'original',
				self::TRANSLATED_ID => 'translated',
			)
		);

		$this->assertSame(
			array(
				'attachmentId' => self::TRANSLATED_ID,
				'publicId'     => 'translated',
			),
			$gallery->contextualize_asset( $this->asset )
		);
	}

	/**
	 * A contextualized attachment that isn't synced yet has no public ID to deliver, so the
	 * original asset is kept.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_when_contextualized_attachment_not_synced() {
		$this->contextualize_to( self::TRANSLATED_ID );
		$gallery = $this->get_gallery( array( self::ORIGINAL_ID => 'original' ) );

		$this->assertSame( $this->asset, $gallery->contextualize_asset( $this->asset ) );
	}

	/**
	 * WPML returns null for an attachment without a translation; the original asset is kept.
	 *
	 * @return void
	 */
	public function test_asset_unchanged_when_no_contextualized_attachment() {
		$this->contextualize_to( null );
		$gallery = $this->get_gallery( array( self::ORIGINAL_ID => 'original' ) );

		$this->assertSame( $this->asset, $gallery->contextualize_asset( $this->asset ) );
	}
}

/**
 * Exposes Gallery::contextualize_asset() for testing.
 */
class Test_Gallery_Contextualize_Asset_Gallery extends \Cloudinary\Media\Gallery {

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
}

/**
 * Media stub with in-memory public IDs.
 */
class Test_Gallery_Contextualize_Asset_Media extends \Cloudinary\Media {

	/**
	 * Map of attachment ID => public ID.
	 *
	 * @var array
	 */
	public $public_ids = array();

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
}
