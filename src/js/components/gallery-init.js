/* global CLD_GALLERY_CONFIG */

( () => {
	/**
	 * A way to catch the galleryWidget function which comes from the `cloudinary` object.
	 * The `cloudinary` object is later overwritten by the video player library by Cloudinary,
	 * which effectively removes the gallery widget library from the page.
	 */
	const { galleryWidget } = cloudinary;

	window.addEventListener( 'load', function () {
		if (
			document.querySelector( '.woocommerce-page' ) &&
			CLD_GALLERY_CONFIG &&
			CLD_GALLERY_CONFIG?.mediaAssets?.length
		) {
			galleryWidget( CLD_GALLERY_CONFIG ).render();
		}
	} );
} )();
