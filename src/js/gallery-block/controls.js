import Dot from 'dot-object';
import { __ } from '@wordpress/i18n';
import cloneDeep from 'lodash/cloneDeep';
import Tippy from '@tippyjs/react';

import '@wordpress/components/build-style/style.css';

import {
	Button,
	ButtonGroup,
	PanelBody,
	RangeControl,
	SelectControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

import { ColorPalette } from '@wordpress/block-editor';

import {
	ASPECT_RATIOS,
	CAROUSEL_LOCATION,
	CAROUSEL_STYLE,
	FADE_TRANSITIONS,
	INDICATOR_SHAPE,
	LAYOUT_OPTIONS,
	MEDIA_ICON_SHAPE,
	NAVIGATION,
	NAVIGATION_BUTTON_SHAPE,
	SELECTED_BORDER_POSITION,
	SELECTED_STYLE,
	ZOOM_TRIGGER,
	ZOOM_TYPE,
	ZOOM_VIEWER_POSITION,
	RESIZE_CROP,
	PAD_STYLES,
} from './options';

import Radio from './radio';
import { convertColors } from './utils';

const ColorPaletteLabel = ( { children, value } ) => (
	<div className="colorpalette-color-label">
		<span>{ children }</span>
		<span
			className="component-color-indicator"
			aria-label={ `Color: ${ value }` }
			style={ { background: value } }
		></span>
	</div>
);

const dot = new Dot( '_' );

const Controls = ( { attributes, setAttributes, colors } ) => {
	const attributesClone = cloneDeep( attributes );
	const nestedAttrs = dot.object( attributesClone );

	if ( ! attributes.transformation_crop ) {
		attributes.transformation_crop = 'pad';
		attributes.transformation_background = 'rgb:FFFFFF';
	}

	if ( 'fill' === attributes.transformation_crop ) {
		delete attributes.transformation_background;
	}

	const onChangeColor = ( value, key ) => {
		const newAttrs = { [ key ]: convertColors( value ) };

		setAttributes( newAttrs );
	};

	return (
		<>
			<PanelBody title={ __( 'Layout', 'cloudinary' ) }>
				{ LAYOUT_OPTIONS.map( ( item ) => (
					<Radio
						key={ `${ item.value.type }-${ item.value.columns }-layout` }
						value={ item.value }
						onChange={ ( value ) => {
							setAttributes( {
								displayProps_mode: value.type,
								displayProps_columns: value.columns || 1,
							} );
						} }
						icon={ item.icon }
						current={ {
							type: attributes.displayProps_mode,
							columns: attributes.displayProps_columns || 1,
						} }
					>
						{ item.label }
					</Radio>
				) ) }
			</PanelBody>
			<PanelBody
				title={ __( 'Color Palette', 'cloudinary' ) }
				initialOpen={ false }
			>
				<ColorPaletteLabel value={ attributes.themeProps_primary }>
					{ __( 'Primary', 'cloudinary' ) }
				</ColorPaletteLabel>
				<ColorPalette
					value={ attributes.themeProps_primary }
					colors={ colors }
					disableCustomColors={ false }
					onChange={ ( value ) =>
						onChangeColor( value, 'themeProps_primary' )
					}
				/>
				<ColorPaletteLabel value={ attributes.themeProps_onPrimary }>
					{ __( 'On Primary', 'cloudinary' ) }
				</ColorPaletteLabel>
				<ColorPalette
					value={ attributes.themeProps_onPrimary }
					colors={ colors }
					disableCustomColors={ false }
					onChange={ ( value ) =>
						onChangeColor( value, 'themeProps_onPrimary' )
					}
				/>
				<ColorPaletteLabel value={ attributes.themeProps_active }>
					{ __( 'Active', 'cloudinary' ) }
				</ColorPaletteLabel>
				<ColorPalette
					value={ attributes.themeProps_active }
					colors={ colors }
					disableCustomColors={ false }
					onChange={ ( value ) =>
						onChangeColor( value, 'themeProps_active' )
					}
				/>
			</PanelBody>
			{ attributes.displayProps_mode === 'classic' && (
				<PanelBody
					title={ __( 'Fade Transition', 'cloudinary' ) }
					initialOpen={ false }
				>
					<SelectControl
						value={ attributes.transition }
						options={ FADE_TRANSITIONS }
						onChange={ ( value ) =>
							setAttributes( { transition: value } )
						}
					/>
				</PanelBody>
			) }
			<PanelBody
				title={ __( 'Main Viewer Parameters', 'cloudinary' ) }
				initialOpen={ false }
			>
				<SelectControl
					label={ __( 'Aspect Ratio', 'cloudinary' ) }
					value={ attributes.aspectRatio }
					options={ ASPECT_RATIOS }
					onChange={ ( value ) =>
						setAttributes( { aspectRatio: value } )
					}
				/>
				<p>
					<Tippy
						content={
							<span>
								{ __(
									'How to resize or crop images to fit the gallery. Pad adds padding around the image using the specified padding style. Fill crops the image from the center so it fills as much of the available space as possible.',
									'cloudinary'
								) }
							</span>
						}
						theme={ 'cloudinary' }
						arrow={ false }
						placement={ 'bottom-start' }
					>
						<div className="cld-ui-title">
							{ __( 'Resize/Crop Mode', 'cloudinary' ) }
							<span className="dashicons dashicons-info cld-tooltip"></span>
						</div>
					</Tippy>
					<ButtonGroup>
						{ RESIZE_CROP.map( ( type ) => (
							<Button
								key={ type.value + '-look-and-feel' }
								isDefault
								isPressed={
									type.value ===
									attributes.transformation_crop
								}
								onClick={ () =>
									setAttributes( {
										transformation_crop: type.value,
										transformation_background: null,
									} )
								}
							>
								{ type.label }
							</Button>
						) ) }
					</ButtonGroup>
				</p>
				{ 'pad' === attributes.transformation_crop && (
					<SelectControl
						label={ __( 'Pad style', 'cloudinary' ) }
						value={ attributes.transformation_background }
						options={ PAD_STYLES }
						onChange={ ( value ) => {
							setAttributes( {
								transformation_background: value,
							} );
						} }
					/>
				) }
				<p>{ __( 'Navigation', 'cloudinary' ) }</p>
				<p>
					<ButtonGroup>
						{ NAVIGATION.map( ( navType ) => (
							<Button
								key={ navType.value + '-navigation' }
								isDefault
								isPressed={
									navType.value === attributes.navigation
								}
								onClick={ () =>
									setAttributes( {
										navigation: navType.value,
									} )
								}
							>
								{ navType.label }
							</Button>
						) ) }
					</ButtonGroup>
				</p>
				<div style={ { marginTop: '30px' } }>
					<ToggleControl
						label={ __( 'Show Zoom', 'cloudinary' ) }
						checked={ attributes.zoom }
						onChange={ () =>
							setAttributes( { zoom: ! attributes.zoom } )
						}
					/>
					{ attributes.zoom && (
						<>
							<p>{ __( 'Zoom Type', 'cloudinary' ) }</p>
							<p>
								<ButtonGroup>
									{ ZOOM_TYPE.map( ( item ) => (
										<Button
											key={ item.value + '-zoom-type' }
											isDefault
											isPressed={
												item.value ===
												attributes.zoomProps_type
											}
											onClick={ () =>
												setAttributes( {
													zoomProps_type: item.value,
												} )
											}
										>
											{ item.label }
										</Button>
									) ) }
								</ButtonGroup>
							</p>
							{ attributes.zoomProps_type === 'flyout' && (
								<SelectControl
									label={ __(
										'Zoom Viewer Position',
										'cloudinary'
									) }
									value={
										attributes.zoomProps_viewerPosition
									}
									options={ ZOOM_VIEWER_POSITION }
									onChange={ ( value ) =>
										setAttributes( {
											zoomProps_viewerPosition: value,
										} )
									}
								/>
							) }
							{ attributes.zoomProps_type !== 'popup' && (
								<>
									<p>
										{ __( 'Zoom Trigger', 'cloudinary' ) }
									</p>
									<p>
										<ButtonGroup>
											{ ZOOM_TRIGGER.map( ( item ) => (
												<Button
													key={
														item.value +
														'-zoom-trigger'
													}
													isDefault
													isPressed={
														item.value ===
														attributes.zoomProps_trigger
													}
													onClick={ () =>
														setAttributes( {
															zoomProps_trigger:
																item.value,
														} )
													}
												>
													{ item.label }
												</Button>
											) ) }
										</ButtonGroup>
									</p>
								</>
							) }
						</>
					) }
				</div>
			</PanelBody>
			<PanelBody
				title={ __( 'Carousel Parameters', 'cloudinary' ) }
				initialOpen={ false }
			>
				<p>{ __( 'Carousel Location', 'cloudinary' ) }</p>
				<p>
					<ButtonGroup>
						{ CAROUSEL_LOCATION.map( ( item ) => (
							<Button
								key={ item.value + '-carousel-location' }
								isDefault
								isPressed={
									item.value === attributes.carouselLocation
								}
								onClick={ () =>
									setAttributes( {
										carouselLocation: item.value,
									} )
								}
							>
								{ item.label }
							</Button>
						) ) }
					</ButtonGroup>
				</p>
				<RangeControl
					label={ __( 'Carousel Offset', 'cloudinary' ) }
					value={ attributes.carouselOffset }
					onChange={ ( offset ) =>
						setAttributes( { carouselOffset: offset } )
					}
					min={ 0 }
					max={ 100 }
				/>
				<p>{ __( 'Carousel Style', 'cloudinary' ) }</p>
				<p>
					<ButtonGroup>
						{ CAROUSEL_STYLE.map( ( item ) => (
							<Button
								key={ item.value + '-carousel-style' }
								isDefault
								isPressed={
									item.value === attributes.carouselStyle
								}
								onClick={ () =>
									setAttributes( {
										carouselStyle: item.value,
									} )
								}
							>
								{ item.label }
							</Button>
						) ) }
					</ButtonGroup>
				</p>
				{ attributes.carouselStyle === 'thumbnails' && (
					<>
						<RangeControl
							label={ __( 'Width', 'cloudinary' ) }
							value={ attributes.thumbnailProps_width }
							onChange={ ( newWidth ) =>
								setAttributes( {
									thumbnailProps_width: newWidth,
								} )
							}
							min={ 5 }
							max={ 300 }
						/>
						<RangeControl
							label={ __( 'Height', 'cloudinary' ) }
							value={ attributes.thumbnailProps_height }
							onChange={ ( newHeight ) =>
								setAttributes( {
									thumbnailProps_height: newHeight,
								} )
							}
							min={ 5 }
							max={ 300 }
						/>
						<p>{ __( 'Navigation Button Shape', 'cloudinary' ) }</p>
						{ NAVIGATION_BUTTON_SHAPE.map( ( item ) => (
							<Radio
								key={ item.value + '-navigation-button-shape' }
								value={ item.value }
								onChange={ ( value ) =>
									setAttributes( {
										thumbnailProps_navigationShape: value,
									} )
								}
								icon={ item.icon }
								current={
									attributes.thumbnailProps_navigationShape
								}
							>
								{ item.label }
							</Radio>
						) ) }
						<p>{ __( 'Selected Style', 'cloudinary' ) }</p>
						<p>
							<ButtonGroup>
								{ SELECTED_STYLE.map( ( item ) => (
									<Button
										key={ item.value + '-selected-style' }
										isDefault
										isPressed={
											item.value ===
											attributes.thumbnailProps_selectedStyle
										}
										onClick={ () =>
											setAttributes( {
												thumbnailProps_selectedStyle:
													item.value,
											} )
										}
									>
										{ item.label }
									</Button>
								) ) }
							</ButtonGroup>
						</p>
						<SelectControl
							label={ __(
								'Selected Border Position',
								'cloudinary'
							) }
							value={
								attributes.thumbnailProps_selectedBorderPosition
							}
							options={ SELECTED_BORDER_POSITION }
							onChange={ ( value ) =>
								setAttributes( {
									thumbnailProps_selectedBorderPosition: value,
								} )
							}
						/>
						<RangeControl
							label={ __(
								'Selected Border Width',
								'cloudinary'
							) }
							value={
								attributes.thumbnailProps_selectedBorderWidth
							}
							onChange={ ( newBw ) =>
								setAttributes( {
									thumbnailProps_selectedBorderWidth: newBw,
								} )
							}
							min={ 0 }
							max={ 10 }
						/>
						<p>{ __( 'Media Shape Icon', 'cloudinary' ) }</p>
						{ MEDIA_ICON_SHAPE.map( ( item ) => (
							<Radio
								key={ item.value + '-media' }
								value={ item.value }
								onChange={ ( value ) =>
									setAttributes( {
										thumbnailProps_mediaSymbolShape: value,
									} )
								}
								icon={ item.icon }
								current={
									attributes.thumbnailProps_mediaSymbolShape
								}
							>
								{ item.label }
							</Radio>
						) ) }
					</>
				) }
				{ attributes.carouselStyle === 'indicators' && (
					<>
						<p>{ __( 'Indicators Shape', 'cloudinary' ) }</p>
						{ INDICATOR_SHAPE.map( ( item ) => (
							<Radio
								key={ item.value + '-indicator' }
								value={ item.value }
								onChange={ ( value ) =>
									setAttributes( {
										indicatorProps_shape: value,
									} )
								}
								icon={ item.icon }
								current={ attributes.indicatorProps_shape }
							>
								{ item.label }
							</Radio>
						) ) }
					</>
				) }
			</PanelBody>
			<PanelBody
				title={ __( 'Additional Settings', 'cloudinary' ) }
				initialOpen={ false }
			>
				<TextareaControl
					label={ __( 'Custom Settings', 'cloudinary' ) }
					help={ __(
						'Provide a JSON string of the settings you want to add and/or override.',
						'cloudinary'
					) }
					value={ nestedAttrs.customSettings }
					onChange={ ( value ) => {
						setAttributes( {
							customSettings: value,
						} );
					} }
				/>
			</PanelBody>
		</>
	);
};

export default Controls;
