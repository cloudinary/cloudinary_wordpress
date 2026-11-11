/**
 * Helpers for specs that render the Cloudinary Gallery block.
 */

const { expect } = require( '../fixtures' );

/**
 * Class of the block container the Gallery Widget renders into.
 *
 * @type {string}
 */
const GALLERY_CONTAINER = 'cld-gallery-e2e';

/**
 * The Cloudinary Gallery Widget is loaded from product-gallery.cloudinary.com
 * and renders on `window.load`, so allow for a slow third-party fetch.
 *
 * @type {number}
 */
const WIDGET_TIMEOUT = 30_000;

/**
 * Build the serialized `cloudinary/gallery` block. The inner markup must
 * match the block's `save()` output exactly, or the editor flags the block
 * as invalid when a test opens the post.
 *
 * @param {Object[]} selectedImages          Response of the plugin's `image_data` endpoint.
 * @param {Object}   layout                  Layout attributes.
 * @param {string}   layout.mode             `expanded` or `classic`.
 * @param {number}   layout.columns          Column count (expanded mode only).
 * @param {Object}   [layout.customSettings] Custom Settings, stored as JSON text like the block does.
 * @param {string}   [layout.cloudName]      Cloud name. The editor saves it on the block, and its
 *                                           preview needs it; the front end adds it on render.
 * @return {string} Block markup.
 */
function galleryBlock(
	selectedImages,
	{ mode, columns, customSettings, cloudName }
) {
	const attributes = {
		container: GALLERY_CONTAINER,
		selectedImages,
		displayProps_mode: mode,
		displayProps_columns: columns,
	};

	if ( cloudName ) {
		attributes.cloudName = cloudName;
	}

	if ( customSettings ) {
		attributes.customSettings = JSON.stringify( customSettings );
	}

	return (
		`<!-- wp:cloudinary/gallery ${ JSON.stringify( attributes ) } -->\n` +
		`<div class="${ GALLERY_CONTAINER }"></div>\n` +
		`<!-- /wp:cloudinary/gallery -->`
	);
}

/**
 * Open a published post and wait for the Gallery Widget to finish
 * rendering every image in the main viewer.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          postLink   Post permalink.
 * @param {number}                          imageCount Number of images in the gallery.
 * @return {Promise<import('@playwright/test').Locator>} The widget root.
 */
async function openGallery( page, postLink, imageCount ) {
	await page.goto( postLink );

	const gallery = page.locator(
		`.${ GALLERY_CONTAINER } [data-test="gallery-wrap"]`
	);
	await expect(
		gallery,
		'the Gallery Widget should render into the block container'
	).toBeVisible( { timeout: WIDGET_TIMEOUT } );

	await expect(
		gallery.locator(
			'[data-test="gallery-viewer-wrap"] [data-test="image-wrap"][data-ready="true"]'
		)
	).toHaveCount( imageCount, { timeout: WIDGET_TIMEOUT } );

	return gallery;
}

/**
 * Read the `src` and `srcset` attributes of the given images.
 *
 * @param {import('@playwright/test').Locator} images
 * @return {Promise<Array<{ src: string|null, srcset: string|null }>>} Attributes per image.
 */
async function collectImageUrls( images ) {
	return images.evaluateAll( ( nodes ) =>
		nodes.map( ( img ) => ( {
			src: img.getAttribute( 'src' ),
			srcset: img.getAttribute( 'srcset' ),
		} ) )
	);
}

module.exports = {
	GALLERY_CONTAINER,
	WIDGET_TIMEOUT,
	collectImageUrls,
	galleryBlock,
	openGallery,
};
