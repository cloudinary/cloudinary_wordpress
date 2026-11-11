/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( './fixtures' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );
const { phpJson } = require( './utils/settings' );
const { wpCli, wpEvalFile } = require( './utils/wizard' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

/**
 * Attachment IDs the spec creates. afterAll deletes the ones a test did
 * not delete.
 *
 * @type {number[]}
 */
let attachmentIds = [];

/**
 * Public IDs of the Cloudinary assets the spec creates. afterAll removes
 * the ones a test did not remove.
 *
 * @type {string[]}
 */
let publicIds = [];

/**
 * Upload the fixture image and sync it to Cloudinary.
 *
 * Syncs only this attachment through the push sync, which is what the
 * auto-sync queue runs, so other media in the test site is not touched.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} label        Part of the filename.
 * @return {Promise<{ attachmentId: number, publicId: string }>} The synced attachment.
 */
async function uploadAndSync( requestUtils, label ) {
	const media = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/media',
		headers: {
			'Content-Type': 'image/jpeg',
			'Content-Disposition': `attachment; filename="delete-${ label }-${ Date.now() }.jpg"`,
		},
		data: fs.readFileSync( FIXTURE_PATH ),
	} );
	attachmentIds.push( media.id );

	const synced = JSON.parse(
		wpEvalFile( `
			$id     = ${ Number( media.id ) };
			$plugin = get_plugin_instance();
			$plugin->get_component( 'sync' )->managers['push']->process_assets( $id );
			$media = $plugin->get_component( 'media' );
			echo wp_json_encode(
				array(
					'publicId' => $media->has_public_id( $id ) ? $media->get_public_id( $id ) : '',
					'synced'   => $plugin->get_component( 'sync' )->is_synced( $id, true ),
				)
			);
		` )
	);
	expect( synced.publicId, 'the image should get a public ID' ).toBeTruthy();
	publicIds.push( synced.publicId );
	expect( synced.synced, 'the image should be fully synced' ).toBe( true );

	return { attachmentId: media.id, publicId: synced.publicId };
}

/**
 * Check through the Admin API if an image asset exists on Cloudinary.
 *
 * @param {string} publicId The public ID.
 * @return {boolean} True when the asset exists.
 */
function remoteAssetExists( publicId ) {
	// Only a 404 means the asset is gone. Any other API error must fail
	// the test, or a timeout would look like a successful delete.
	const result = wpEvalFile( `
		$details = get_plugin_instance()
			->get_component( 'connect' )
			->api
			->get_asset_details( ${ phpJson( publicId ) }, 'image' );
		if ( ! is_wp_error( $details ) ) {
			echo 'exists';
		} elseif ( 404 === (int) $details->get_error_code() ) {
			echo 'missing';
		} else {
			echo 'error ' . $details->get_error_code() . ': ' . $details->get_error_message();
		}
	` );
	if ( 'exists' !== result && 'missing' !== result ) {
		throw new Error(
			`Could not check the Cloudinary asset ${ publicId }: ${ result }`
		);
	}
	return 'exists' === result;
}

/**
 * Forget an attachment that a test deleted, so afterAll does not delete it
 * again.
 *
 * @param {number} attachmentId The attachment ID.
 */
function forgetAttachment( attachmentId ) {
	attachmentIds = attachmentIds.filter( ( id ) => id !== attachmentId );
}

// @serial: needs real credentials in `cloudinary_connect` to sync assets,
// while every analytics spec overwrites that option with fake ones.
test.describe( 'Cloudinary delete sync', { tag: '@serial' }, () => {
	test.beforeAll( () => {
		ensureCloudinaryConnected();
	} );

	test.afterAll( () => {
		const ids = attachmentIds;
		const assets = publicIds;
		attachmentIds = [];
		publicIds = [];

		// Remove the assets directly, so a failed test does not leave
		// them in the Cloudinary account.
		if ( assets.length ) {
			try {
				wpEvalFile( `
					$api = get_plugin_instance()->get_component( 'connect' )->api;
					foreach ( ${ phpJson( assets ) } as $public_id ) {
						$api->destroy( 'image', array( 'public_id' => $public_id ) );
					}
				` );
			} catch ( e ) {
				console.warn(
					'Cleanup of the Cloudinary assets failed:',
					e.message
				);
			}
		}

		for ( const id of ids ) {
			try {
				wpCli( [ 'post', 'delete', String( id ), '--force' ] );
			} catch ( e ) {
				console.warn( `Cleanup of post ${ id } failed:`, e.message );
			}
		}
	} );

	test( 'deleting an attachment through REST removes its Cloudinary asset', async ( {
		requestUtils,
	} ) => {
		const { attachmentId, publicId } = await uploadAndSync(
			requestUtils,
			'rest'
		);
		expect(
			remoteAssetExists( publicId ),
			'the asset should exist on Cloudinary before the delete'
		).toBe( true );

		await requestUtils.deleteMedia( attachmentId );
		forgetAttachment( attachmentId );

		expect(
			remoteAssetExists( publicId ),
			'the asset should be removed from Cloudinary'
		).toBe( false );
	} );

	test( '"Delete permanently" in the Media Library removes the Cloudinary asset', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { attachmentId, publicId } = await uploadAndSync(
			requestUtils,
			'library'
		);
		expect( remoteAssetExists( publicId ) ).toBe( true );

		await admin.visitAdminPage( 'upload.php', 'mode=list' );
		const row = page.locator( `#post-${ attachmentId }` );
		await row.hover();

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await Promise.all( [
			page.waitForURL( /upload\.php.*deleted=1/ ),
			row.locator( 'a.submitdelete' ).click(),
		] );
		forgetAttachment( attachmentId );

		await expect( page.locator( `#post-${ attachmentId }` ) ).toHaveCount(
			0
		);
		expect(
			remoteAssetExists( publicId ),
			'the asset should be removed from Cloudinary'
		).toBe( false );
	} );
} );
