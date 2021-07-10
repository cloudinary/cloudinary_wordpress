/* global videoFreeForm cldVideos cld */

( function () {
	if ( typeof cldVideos === 'undefined' ) {
		return;
	}

	cldVideos = JSON.parse( cldVideos );

	for ( const videoInstance in cldVideos ) {
		const cldConfig = cldVideos[ videoInstance ];
		const cldId = 'cloudinary-video-' + videoInstance;
		cld.videoPlayer( cldId, cldConfig );
	}

	window.addEventListener( 'load', function () {
		for ( const videoInstance in cldVideos ) {
			const cldId = 'cloudinary-video-' + videoInstance;
			const videoContainer = document.getElementById( cldId );
			let videoElement = videoContainer.getElementsByTagName( 'video' );

			if ( videoElement.length === 1 ) {
				videoElement = videoElement[ 0 ];
				videoElement.style.width = '100%';
				if (
					videoFreeForm &&
					videoElement.src.indexOf( videoFreeForm ) === -1 &&
					! cldVideos[ videoInstance ].overwrite_transformations
				) {
					videoElement.src = videoElement.src.replace(
						'upload/',
						'upload/' + videoFreeForm + '/'
					);
				}
			}
		}
	} );
} )();
