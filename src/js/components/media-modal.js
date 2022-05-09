if ( wp.media ) {
	wp.media.events.on( 'editor:image-edit', function ( props ) {
		props.metadata.cldoverwrite = null;
		const classes = props.image.className.split( ' ' );
		if ( classes.indexOf( 'cld-overwrite' ) >= 0 ) {
			props.metadata.cldoverwrite = 'true';
		}
	} );
	wp.media.events.on( 'editor:image-update', function ( props ) {
		const classes = props.image.className.split( ' ' );
		if (
			props.metadata.cldoverwrite &&
			classes.indexOf( 'cld-overwrite' ) === -1
		) {
			classes.push( 'cld-overwrite' );
		} else if (
			! props.metadata.cldoverwrite &&
			classes.indexOf( 'cld-overwrite' ) >= 0
		) {
			delete classes[ classes.indexOf( 'cld-overwrite' ) ];
		}

		props.image.className = classes.join( ' ' );
	} );

	// Intercept props and inject cld-overwrite class.
	let currentOverwrite = null;
	const imageProps = wp.media.string.props;
	wp.media.string.props = function ( props, asset ) {
		if ( props.cldoverwrite ) {
			props.classes = [ 'cld-overwrite' ];
			currentOverwrite = true;
		}
		const newProps = imageProps( props, asset );
		return newProps;
	};
	// Intercept ajax post, and send the cld-overwrite flag, and transformations.
	wp.media.post = function ( action, data ) {
		if ( 'send-attachment-to-editor' === action ) {
			const state = wp.media.editor.get().state();
			const attach = state.get( 'selection' ).get( data.attachment );
			if ( attach.attributes.transformations ) {
				data.attachment.transformations =
					attach.attributes.transformations;
			}
			if (
				data.html.indexOf( 'cld-overwrite' ) > -1 ||
				true === currentOverwrite
			) {
				data.attachment.cldoverwrite = true;
				currentOverwrite = null;
			}
		}
		// Return the original.
		return wp.ajax.post( action, data );
	};

	const MediaFrame = wp.media.view.MediaFrame.Select;
	const MediaFramePost = wp.media.view.MediaFrame.Post;
	const MediaFrameImageDetails = wp.media.view.MediaFrame.ImageDetails;
	const MediaFrameVideoDetails = wp.media.view.MediaFrame.VideoDetails;
	const AttachmentDisplay = wp.media.view.Settings.AttachmentDisplay;
	const Cloudinary = wp.media.View.extend( {
		tagName: 'div',
		className: 'cloudinary-widget',
		template: wp.template( 'cloudinary-dam' ),
		active: false,
		toolbar: null,
		frame: null,
		ready() {
			const controller = this.controller;
			const selection = this.model.get( 'selection' );
			const library = this.model.get( 'library' );
			const attachment = wp.media.model.Attachment;
			// Set widget to same as model.
			CLDN.mloptions.multiple = controller.options.multiple;
			if ( this.cid !== this.active ) {
				CLDN.mloptions.inline_container =
					'#cloudinary-dam-' + controller.cid;
				if ( 1 === selection.length ) {
					const att = attachment.get( selection.models[ 0 ].id );
					if ( typeof att.attributes.public_id !== 'undefined' ) {
						CLDN.mloptions.asset = {
							resource_id: att.attributes.public_id,
						};
					}
				} else {
					CLDN.mloptions.asset = null;
				}

				try {
					if ( ! CLDN.mloptions.folder ) {
						CLDN.mloptions.folder = { path: '' };
					}
					const type = selection.props.attributes.type;
					CLDN.mloptions.folder.resource_type = Array.isArray( type )
						? type[ 0 ]
						: type;
				} catch ( err ) {}

				window.ml = cloudinary.openMediaLibrary(
					CLDN.mloptions,
					{
						insertHandler( data ) {
							for ( let i = 0; i < data.assets.length; i++ ) {
								const temp = data.assets[ i ];
								wp.media
									.post( 'cloudinary-down-sync', {
										nonce: CLDN.nonce,
										asset: temp,
									} )
									.done( function ( asset ) {
										const updateAsset = function (
											newAsset,
											attach
										) {
											newAsset.uploading = false;
											attach.set( newAsset );
											wp.Uploader.queue.remove( attach );
											if (
												wp.Uploader.queue.length === 0
											) {
												wp.Uploader.queue.reset();
											}
										};
										if (
											typeof asset.resync !== 'undefined'
										) {
											asset.resync.forEach( function (
												newUpdateAsset
											) {
												const updateAttach = attachment.get(
													newUpdateAsset.id
												);
												updateAttach.set(
													newUpdateAsset
												);
											} );
										}
										if (
											typeof asset.fetch !== 'undefined'
										) {
											const attach = attachment.get(
												asset.attachment_id
											);
											attach.set( asset );
											library.add( attach );
											wp.Uploader.queue.add( attach );
											wp.ajax
												.send( {
													url: asset.fetch,
													beforeSend( request ) {
														request.setRequestHeader(
															'X-WP-Nonce',
															CLDN.nonce
														);
													},
													data: {
														src: asset.url,
														filename:
															asset.filename,
														attachment_id:
															asset.attachment_id,
														transformations:
															asset.transformations,
													},
												} )
												.done( function ( newAsset ) {
													const att = attachment.get(
														newAsset.id
													);
													updateAsset(
														newAsset,
														att
													);
												} )
												.fail( function ( err ) {
													updateAsset(
														asset,
														attach
													);
													library.remove( attach );
													selection.remove( attach );

													if (
														typeof err === 'string'
													) {
														alert( err );
													} else if (
														err.status === 500
													) {
														alert( 'HTTP error.' );
													}
												} );
										} else {
											const attach = attachment.get(
												asset.id
											);
											attach.set( asset );
											selection.add( attach );
										}
										if ( wp.Uploader.queue.length === 0 ) {
											wp.Uploader.queue.reset();
										}
										controller.content.mode( 'browse' );
									} );
							}
						},
					},
					document.querySelectorAll( '.dam-cloudinary' )[ 0 ]
				);
			}
			this.active = this.cid;
			return this;
		},
	} );
	const extendType = function ( type ) {
		const obj = {
			/**
			 * Bind region mode event callbacks.
			 *
			 * @see media.controller.Region.render
			 */
			bindHandlers() {
				type.prototype.bindHandlers.apply( this, arguments );
				this.on(
					'content:render:cloudinary',
					this.cloudinaryContent,
					this
				);
			},
			/**
			 * Render callback for the router region in the `browse` mode.
			 *
			 * @param {wp.media.view.Router} routerView
			 */
			browseRouter( routerView ) {
				type.prototype.browseRouter.apply( this, arguments );
				routerView.set( {
					cloudinary: {
						text: 'Cloudinary',
						priority: 60,
					},
				} );
			},

			/**
			 * Render callback for the content region in the `upload` mode.
			 */
			cloudinaryContent() {
				this.$el.addClass( 'hide-toolbar' );
				const state = this.state();
				const view = new Cloudinary( {
					controller: this,
					model: state,
				} ).render();
				this.content.set( view );
			},
		};

		return obj;
	};

	/**
	 * Extend the AttachmentDetails to add the transformations overwrite toggle.
	 *
	 * @param {wp.media.view} type The original object.
	 * @return {Object} The object to extend.
	 */
	const extendDetails = function ( type ) {
		return {
			initialize() {
				type.prototype.initialize.apply( this, arguments );
				this.listenTo(
					this.model,
					'change:cldoverwrite',
					this.handleOverwrite
				);
			},
			handleOverwrite( data ) {
				const sizes = this.options.attachment.attributes.sizes;
				for ( const size in sizes ) {
					const url = new URL( sizes[ size ].url );
					if ( data.attributes.cldoverwrite ) {
						url.searchParams.set( 'cld_overwrite', true );
					} else {
						url.searchParams.delete( 'cld_overwrite' );
					}
					this.options.attachment.attributes.sizes[ size ].url =
						url.href;
				}
			},
		};
	};
	// Extending the current media library frames to add a new tab to each area.
	wp.media.view.MediaFrame.Select = MediaFrame.extend(
		extendType( MediaFrame )
	);
	wp.media.view.MediaFrame.Post = MediaFramePost.extend(
		extendType( MediaFramePost )
	);
	wp.media.view.MediaFrame.ImageDetails = MediaFrameImageDetails.extend(
		extendType( MediaFrameImageDetails )
	);
	wp.media.view.MediaFrame.VideoDetails = MediaFrameVideoDetails.extend(
		extendType( MediaFrameVideoDetails )
	);
	wp.media.view.Settings.AttachmentDisplay = AttachmentDisplay.extend(
		extendDetails( AttachmentDisplay )
	);
}
