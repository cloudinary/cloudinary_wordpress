import { __ } from '@wordpress/i18n';

const AssetPreview = {
	preview: null,
	wrap: null,
	apply: null,
	url: null,
	defaultWidth: null,
	defaultHeight: null,
	maxSize: null,
	init() {
		return this;
	},
	createPreview( width = 400, height = 300 ) {
		this.maxSize = width > height ? width : height;
		this.defaultWidth = width;
		this.defaultHeight = height;
		this.wrap = document.createElement( 'div' );
		this.apply = document.createElement( 'button' );
		this.preview = document.createElement( 'img' );
		this.apply.type = 'button';
		this.apply.classList.add( 'button-primary' );
		this.apply.innerText = __( 'Preview', 'cloudinary' );
		this.preview.style.transition = 'opacity 1s';
		this.preview.style.opacity = 1;
		this.preview.style.maxWidth = '100%';
		this.preview.style.maxHeight = '100%';
		this.reset();
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

		this.preview.addEventListener( 'load', ( e ) => {
			this.preview.style.opacity = 1;
			this.wrap.style.width = '';
			this.wrap.style.height = '';
			this.defaultHeight = this.preview.height;
			this.defaultWidth = this.preview.width;

			if ( this.defaultHeight > this.defaultWidth ) {
				this.wrap.style.height = this.maxSize + 'px';
			} else {
				this.wrap.style.width = this.maxSize + 'px';
			}
		} );
		this.preview.addEventListener( 'error', ( e ) => {
			this.preview.src = this.getNoURL( '⚠' );
		} );
		this.apply.addEventListener( 'click', () => {
			this.apply.style.display = 'none';
			this.reset();
			this.preview.style.opacity = 0.6;
			this.preview.src = this.url;
		} );

		return this.wrap;
	},
	reset() {
		this.preview.src = this.getNoURL();
	},
	setSrc( src, load = false ) {
		this.preview.style.opacity = 0.6;
		if ( load ) {
			this.apply.style.display = 'none';
			this.preview.src = src;
		} else {
			this.apply.style.display = 'block';
			this.url = src;
		}
	},
	getNoURL( icon = '︎' ) {
		const x = this.defaultWidth / 2 - 23;
		const y = this.defaultHeight / 2 + 25;
		return `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="${ this.defaultWidth }" height="${ this.defaultHeight }"><style>.error { font: normal 50px sans-serif; fill:rgb(255,0,0); }</style><rect width="100%" height="100%" style="fill:rgba(0,0,0,0.2);"></rect><text x="${ x }" y="${ y }" class="error">${ icon }</text></svg>`;
	},
};

export default AssetPreview;
