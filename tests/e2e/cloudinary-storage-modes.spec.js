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
const { expectCloudinaryUrl } = require( './utils/delivery' );
const { saveSettings } = require( './utils/settings' );
const { wpCli, wpEvalFile } = require( './utils/wizard' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );
const FIXTURE_BYTES = fs.statSync( FIXTURE_PATH ).size;

/**
 * Settings pinned for the spec, restored in afterAll. Without lazy load
 * the server writes the delivery URL into `src`. Each test then sets the
 * storage mode (`offload`) it needs.
 *
 * @type {Record<string, string>}
 */
const SETTINGS = {
	use_lazy_load: 'off',
	auto_sync: 'on',
	offload: 'dual_full',
};

let cloudName;

/** @type {Record<string, string>|null} */
let previousSettings = null;

/**
 * IDs of every attachment and post the spec creates, removed in afterAll.
 *
 * @type {number[]}
 */
let createdIds = [];

/**
 * IDs of the attachments in `createdIds`, whose Cloudinary assets afterAll
 * removes.
 *
 * @type {number[]}
 */
let attachmentIds = [];

/**
 * The image the "Cloudinary only" tests move between storage modes.
 *
 * @type {{ attachmentId: number, postLink: string }|null}
 */
let offloaded = null;

/**
 * Upload the fixture image and publish a post with it in an image block.
 *
 * The timestamp in the filename gives a fresh public ID, so no asset from
 * an earlier run applies.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} label        Part of the filename and post title.
 * @return {Promise<{ attachmentId: number, postLink: string }>} The created items.
 */
async function createImagePost( requestUtils, label ) {
	const stamp = Date.now();
	const media = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/media',
		headers: {
			'Content-Type': 'image/jpeg',
			'Content-Disposition': `attachment; filename="storage-${ label }-${ stamp }.jpg"`,
		},
		data: fs.readFileSync( FIXTURE_PATH ),
	} );
	createdIds.push( media.id );
	attachmentIds.push( media.id );

	const post = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/posts',
		data: {
			status: 'publish',
			title: `Cloudinary storage ${ label } e2e ${ stamp }`,
			content:
				`<!-- wp:image {"id":${ media.id }} -->\n` +
				`<figure class="wp-block-image"><img src="${ media.source_url }" alt="" class="wp-image-${ media.id }"/></figure>\n` +
				`<!-- /wp:image -->`,
		},
	} );
	createdIds.push( post.id );

	return { attachmentId: media.id, postLink: post.link };
}

/**
 * Read the local storage state of an attachment.
 *
 * @param {number} attachmentId The attachment ID.
 * @return {{ file: string, exists: boolean, bytes: number, sizes: Record<string, boolean>, storage: string, delay: string }} The state.
 */
function storageState( attachmentId ) {
	return JSON.parse(
		wpEvalFile( `
			$id    = ${ Number( attachmentId ) };
			$media = get_plugin_instance()->get_component( 'media' );
			$file  = get_attached_file( $id );
			$meta  = wp_get_attachment_metadata( $id );
			$sizes = array();
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $name => $size ) {
					$sizes[ $name ] = file_exists( path_join( dirname( $file ), $size['file'] ) );
				}
			}
			clearstatcache();
			echo wp_json_encode(
				array(
					'file'    => $file,
					'exists'  => file_exists( $file ),
					'bytes'   => file_exists( $file ) ? filesize( $file ) : 0,
					'sizes'   => $sizes,
					'storage' => (string) $media->get_post_meta( $id, Sync::META_KEYS['storage'], true ),
					'delay'   => (string) get_post_meta( $id, Sync::META_KEYS['delay'], true ),
				)
			);
		` )
	);
}

/**
 * Move the start of the "Cloudinary only" delay back, so the next sync
 * removes the local files without a one-minute wait.
 *
 * @param {number} attachmentId The attachment ID.
 */
function endOffloadDelay( attachmentId ) {
	wpEvalFile( `
		update_post_meta(
			${ Number( attachmentId ) },
			Sync::META_KEYS['delay'],
			time() - \\Cloudinary\\Sync\\Storage::DELETE_DELAY - 1
		);
	` );
}

/**
 * Remove the Cloudinary assets of attachments.
 *
 * Deleting an attachment removes its asset only when the plugin thinks the
 * attachment is synced, and a changed storage setting makes it out of
 * sync. So remove the assets directly, or they stay in the account.
 *
 * @param {number[]} ids Attachment IDs.
 */
function destroyRemoteAssets( ids ) {
	wpEvalFile( `
		$plugin = get_plugin_instance();
		$media  = $plugin->get_component( 'media' );
		$api    = $plugin->get_component( 'connect' )->api;
		foreach ( array( ${ ids.map( Number ).join( ', ' ) } ) as $id ) {
			if ( $media->has_public_id( $id ) ) {
				$api->destroy(
					$media->get_resource_type( $id ),
					array(
						'public_id'  => $media->get_public_id( $id ),
						'invalidate' => true,
					)
				);
			}
		}
	` );
}

/**
 * Sync one attachment synchronously instead of waiting for the auto-sync
 * queue.
 *
 * Not `wp cloudinary sync`: that syncs every attachment in the library,
 * and a storage change replaces or removes the local files of each one.
 *
 * @param {number} attachmentId The attachment ID.
 */
function sync( attachmentId ) {
	wpEvalFile( `
		get_plugin_instance()
			->get_component( 'sync' )
			->managers['push']
			->process_assets( ${ Number( attachmentId ) } );
	` );
}

/**
 * Open the post and check that its image loads from Cloudinary.
 *
 * @param {import('@playwright/test').Page}            page
 * @param {{ attachmentId: number, postLink: string }} item The post to open.
 */
async function expectImageFromCloudinary( page, { attachmentId, postLink } ) {
	await page.goto( postLink );

	const img = page
		.locator( `.wp-block-image img.wp-image-${ attachmentId }` )
		.first();
	await expect( img ).toBeVisible();
	await expect
		.poll( () => img.evaluate( ( el ) => el.complete && el.naturalWidth ), {
			message: 'the image should load',
		} )
		.toBeGreaterThan( 0 );

	expectCloudinaryUrl( await img.getAttribute( 'src' ), cloudName );
}

// @serial: needs real credentials in `cloudinary_connect` to sync assets,
// while every analytics spec overwrites that option with fake ones. It also
// changes the storage setting, which every other delivery spec depends on.
test.describe( 'Cloudinary storage modes', { tag: '@serial' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );
		previousSettings = saveSettings( SETTINGS );
	} );

	test.afterAll( () => {
		try {
			if ( previousSettings ) {
				saveSettings( previousSettings );
				previousSettings = null;
			}
		} finally {
			offloaded = null;
			const ids = createdIds;
			const assets = attachmentIds;
			createdIds = [];
			attachmentIds = [];

			if ( assets.length ) {
				try {
					destroyRemoteAssets( assets );
				} catch ( e ) {
					console.warn(
						'Cleanup of the Cloudinary assets failed:',
						e.message
					);
				}
			}

			// WP-CLI rather than REST for the same reason as the image
			// delivery spec: `force=true` on a `?rest_route=` URL is fragile.
			for ( const id of ids ) {
				try {
					wpCli( [ 'post', 'delete', String( id ), '--force' ] );
				} catch ( e ) {
					console.warn(
						`Cleanup of post ${ id } failed:`,
						e.message
					);
				}
			}
		}
	} );

	test( '"Cloudinary only" removes the local files after the delay', async ( {
		page,
		requestUtils,
	} ) => {
		saveSettings( { offload: 'cld' } );
		offloaded = await createImagePost( requestUtils, 'cld' );
		const { attachmentId } = offloaded;

		sync( attachmentId );

		// The plugin waits Storage::DELETE_DELAY before it removes the
		// local files, so a failed upload cannot lose the only copy.
		let state = storageState( attachmentId );
		expect(
			state.exists,
			'the local file should stay during the delay'
		).toBe( true );
		expect( state.delay, 'the delay should start' ).not.toBe( '' );
		expect( state.storage, 'storage should not be set yet' ).not.toBe(
			'cld'
		);

		endOffloadDelay( attachmentId );
		sync( attachmentId );

		state = storageState( attachmentId );
		expect( state.storage ).toBe( 'cld' );
		expect(
			state.exists,
			`the local file ${ state.file } should be removed`
		).toBe( false );
		expect(
			Object.keys( state.sizes ).length,
			'the attachment should keep its size metadata'
		).toBeGreaterThan( 0 );
		for ( const [ name, exists ] of Object.entries( state.sizes ) ) {
			expect(
				exists,
				`the local "${ name }" size file should be removed`
			).toBe( false );
		}

		// With no local copy left, Cloudinary is the only source.
		await expectImageFromCloudinary( page, offloaded );
	} );

	test( '"Cloudinary and WordPress" downloads the local files again', async ( {
		page,
	} ) => {
		expect(
			offloaded,
			'the previous test should offload an image'
		).not.toBeNull();
		const { attachmentId } = offloaded;

		saveSettings( { offload: 'dual_full' } );
		sync( attachmentId );

		const state = storageState( attachmentId );
		expect( state.storage ).toBe( 'dual_full' );
		expect(
			state.exists,
			`the local file ${ state.file } should be downloaded again`
		).toBe( true );
		expect(
			state.bytes,
			'the local file should be the full-quality original'
		).toBe( FIXTURE_BYTES );
		expect( Object.keys( state.sizes ).length ).toBeGreaterThan( 0 );
		for ( const [ name, exists ] of Object.entries( state.sizes ) ) {
			expect(
				exists,
				`the local "${ name }" size file should be generated again`
			).toBe( true );
		}

		await expectImageFromCloudinary( page, offloaded );
	} );

	test( '"Cloudinary and WordPress (low resolution)" keeps a smaller local copy', async ( {
		page,
		requestUtils,
	} ) => {
		saveSettings( { offload: 'dual_low' } );
		const item = await createImagePost( requestUtils, 'dual-low' );

		sync( item.attachmentId );

		const state = storageState( item.attachmentId );
		expect( state.storage ).toBe( 'dual_low' );
		expect( state.exists, 'a local file should stay' ).toBe( true );
		expect(
			state.bytes,
			'the local file should be the low-quality version'
		).toBeLessThan( FIXTURE_BYTES );

		// Delivery still uses the full-quality original on Cloudinary.
		await expectImageFromCloudinary( page, item );
	} );
} );
