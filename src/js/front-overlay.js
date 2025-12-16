import { __ } from '@wordpress/i18n';
import tippy from 'tippy.js';

import 'tippy.js/dist/tippy.css';
import '../css/front-overlay.scss';

const Front_Overlay = {
	init() {

		[ ...document.images ].forEach( ( image ) => {
			const parent = image.parentNode;
			if ( parent.tagName === 'PICTURE' ) {
				this.processPicture( image, parent );
				image.addEventListener( 'load', this.load.bind( this, image, parent) );
			} else {
				this.wrapImage( image );
			}
		});

		const tooltips = document.querySelectorAll( '.cld-tag' );

		this.tippy( tooltips );
	},
	load( image, parent ) {
		parent.querySelectorAll( '.overlay-tag' ).forEach( tag => tag.remove() );
		this.processPicture( image, parent );
		this.tippy( parent.querySelectorAll( '.cld-tag' ) );
	},
	processPicture( image, parent ) {
		const siblings = parent.querySelectorAll( 'source' );
		if ( siblings.length > 0 ) {
			siblings.forEach(
				( sibling ) => {
					if ( [ sibling.src, sibling.srcset ].some( src => src.includes( image.currentSrc ) )) {
						[ ...image.attributes ].forEach(
							attr => {
								if ( attr.name.startsWith( 'data-' ) ) {
									image.removeAttribute( attr.name );
								}
							}
						);
						Object.assign( image.dataset, { ...sibling.dataset } );
					}
				}
			);
		}

		this.wrapImage( image );
	},
	wrapImage( image ) {
		if ( image.dataset.publicId ) {
			this.cldTag( image );
		} else {
			this.wpTag( image );
		}
	},
	createTag( image ) {
		const tag = document.createElement( 'span' );
		tag.classList.add( 'overlay-tag' );

		image.parentNode.insertBefore( tag, image );
		return tag;
	},
	cldTag( image ) {
		const tag = this.createTag( image );

		tag.template = this.createTemplate( image );
		tag.innerText = __( 'Cloudinary', 'cloudinary' );
		tag.classList.add( 'cld-tag' );
	},
	wpTag( image ) {
		const tag = this.createTag( image );
		tag.innerText = __( 'WordPress', 'cloudinary' );
		tag.classList.add( 'wp-tag' );
	},
	createTemplate( image ) {
		const box = document.createElement( 'div' );
		box.classList.add( 'cld-tag-info' );
		box.appendChild( this.makeLine( __( 'Local size', 'cloudinary' ),
			image.dataset.filesize
		) );
		box.appendChild( this.makeLine( __( 'Optimized size', 'cloudinary' ),
			image.dataset.optsize
		) );
		box.appendChild( this.makeLine( __( 'Optimized format', 'cloudinary' ),
			image.dataset.optformat
		) );
		if ( image.dataset.percent ) {
			box.appendChild( this.makeLine(
				__( 'Reduction', 'cloudinary' ),
				image.dataset.percent + '%'
			) );
		}

		box.appendChild( this.makeLine( __( 'Transformations', 'cloudinary' ),
			image.dataset.transformations
		) );

		if ( image.dataset.transformationCrop ) {
			box.appendChild( this.makeLine( __( 'Crop transformations', 'cloudinary' ),
				image.dataset.transformationCrop
			) );
		}

		const link = document.createElement( 'a' );
		link.classList.add( 'edit-link' );
		link.href = image.dataset.permalink;
		link.innerText = __( 'Edit Effects', 'cloudinary' );
		box.appendChild( this.makeLine( '', '', link ) );

		return box;
	},
	makeLine( name, value, link ) {
		const line = document.createElement( 'div' );
		const title = document.createElement( 'span' );
		const detail = document.createElement( 'span' );
		title.innerText = name;
		title.classList.add( 'title' );

		detail.innerText = value;
		if ( link ) {
			detail.appendChild( link );
		}
		line.appendChild( title );
		line.appendChild( detail );
		return line;
	},
	tippy( tooltips ) {
		tippy( tooltips, {
			placement: 'bottom-start',
			interactive: true,
			appendTo: () => document.body,
			aria: {
				content: 'auto',
				expanded: 'auto',
			},
			content( reference ) {
				return reference.template.innerHTML;
			},
			allowHTML: true,
		} );
	},
	debounce( callback, wait ) {
		let timeoutId = null;
		return (...args) => {
			window.clearTimeout( timeoutId );
			timeoutId = window.setTimeout(
				() => {
					callback( ...args );
				},
				wait
			);
		};
	}
};

window.addEventListener( 'load', () => Front_Overlay.init() );
