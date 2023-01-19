import { __ } from '@wordpress/i18n';
import tippy from 'tippy.js';

import 'tippy.js/dist/tippy.css';
import '../css/front-overlay.scss';

const Front_Overlay = {
	init() {

		[ ...document.images ].forEach( ( image ) => {
			this.wrapImage( image );
		} );

		const tooltips = document.querySelectorAll( '.cld-tag' );

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
		const link = document.createElement( 'a' );
		link.classList.add( 'edit-link' );
		link.href = image.dataset.permalink;
		link.innerText = __( 'Edit asset', 'cloudinary' );
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
	}
};

window.addEventListener( 'load', () => Front_Overlay.init() );
