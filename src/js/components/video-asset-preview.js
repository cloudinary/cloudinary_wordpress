import { __ } from '@wordpress/i18n';

const VideoAssetPreview = {
	preview: null,
	wrap: null,
	apply: null,
	url: null,
	publicId: null,
	player: null,
	defaultWidth: null,
	defaultHeight: null,
	maxSize: null,

	init() {
		return this;
	},

	createPreview( width = 427, height = 240 ) {
		this.maxSize = width > height ? width : height;
		this.defaultWidth = width;
		this.defaultHeight = height;
		this.wrap = document.createElement( 'div' );
		this.apply = document.createElement( 'button' );
		this.preview = document.createElement( 'video' );

		this.apply.type = 'button';
		this.apply.classList.add( 'button-primary' );
		this.apply.innerText = __( 'Preview', 'cloudinary' );

		this.preview.id = 'cld-asset-video-preview';
		this.preview.style.transition = 'opacity 1s';
		this.preview.style.opacity = 1;
		this.preview.style.maxWidth = '100%';
		this.preview.style.maxHeight = '100%';
		this.preview.controls = true;
		this.preview.setAttribute( 'width', width );
		this.preview.setAttribute( 'height', height );

		this.wrap.style.minHeight = '200px';
		this.wrap.style.width = this.maxSize + 'px';
		this.wrap.style.position = 'relative';
		this.wrap.style.display = 'flex';
		this.wrap.style.alignItems = 'center';
		this.wrap.style.justifyContent = 'center';
		this.apply.style.position = 'absolute';
		this.apply.style.display = 'none';

		this.wrap.appendChild( this.preview );
		this.wrap.appendChild( this.apply );

		this.apply.addEventListener( 'click', () => {
			this.apply.style.display = 'none';
			this.preview.style.opacity = 0.6;
			this.updatePlayer( this.url );
		} );

		return this.wrap;
	},

	setPublicId( publicId ) {
		this.publicId = publicId;
		this.initPlayer();
	},

	initPlayer() {
		if (
			typeof window.cloudinary === 'undefined' ||
			typeof window.cld === 'undefined'
		) {
			console.error( 'Cloudinary video player not loaded' );
			return;
		}

		// Initialize player if not already initialized
		if ( ! this.player ) {
			this.player = window.cld.videoPlayer( this.preview.id, {
				fluid: true,
				controls: true,
			} );
		}
	},

	setSrc( src, load = false ) {
		this.preview.style.opacity = 0.6;
		if ( load ) {
			this.apply.style.display = 'none';

			// Ensure player is initialized before updating
			if ( ! this.player ) {
				this.initPlayer();
			}

			this.updatePlayer( src );
		} else {
			this.apply.style.display = 'block';
			this.url = src;
		}
	},

	updatePlayer( src ) {
		if ( ! this.player ) {
			return;
		}

		const sourceConfig = {
			publicId: this.publicId,
		};

		if ( src && src.trim() !== '' ) {
			sourceConfig.transformation = { raw_transformation: src };
		}

		this.player.source( sourceConfig );
		this.preview.style.opacity = 1;
	},

	reset( src ) {
		this.setSrc( src, false );
	},
};

export default VideoAssetPreview;
