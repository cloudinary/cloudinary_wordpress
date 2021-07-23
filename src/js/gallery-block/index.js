/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';
import attributes from './attributes.json';

registerBlockType( 'cloudinary/gallery', {
	title: __( 'Cloudinary Gallery', 'cloudinary' ),
	description: __(
		'Add a gallery powered by the Cloudinary Gallery Widget to your post.',
		'cloudinary'
	),
	category: 'widgets',
	icon: 'format-gallery',
	attributes,
	edit,
	save,
} );
