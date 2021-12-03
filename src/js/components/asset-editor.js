import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const AssetEditor = {
	id: null,
	post_id: null,
	transformations: null,
	beforeCallbacks: [],
	completeCallbacks: [],
	init( callback ) {
		if ( typeof cldData.editor === 'undefined' ) {
			return;
		}
		apiFetch.use( apiFetch.createNonceMiddleware( cldData.editor.nonce ) );
		this.callback = callback;
		return this;
	},
	save( data ) {
		this.doBefore( data );
		apiFetch( {
			path: cldData.editor.save_url,
			data,
			method: 'POST',
		} ).then( ( result ) => {
			this.doComplete( result, this );
		} );
	},
	doBefore( data ) {
		this.beforeCallbacks.forEach( ( callback ) => callback( data, this ) );
	},
	doComplete( result ) {
		this.completeCallbacks.forEach( ( callback ) =>
			callback( result, this )
		);
	},
	onBefore( callback ) {
		this.beforeCallbacks.push( callback );
	},
	onComplete( callback ) {
		this.completeCallbacks.push( callback );
	},
};

export default AssetEditor;
