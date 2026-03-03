import tippy from 'tippy.js';
import { useEffect, useState, useCallback } from '@wordpress/element';

/**
 * Custom hook to initialize a tippy.js tooltip on a React element.
 * It returns the callback ref function required for the target element, which should then be assigned as a `ref`.
 *
 * @param {string} content The content of the tooltip.
 * @return {Function} The callback ref function to be assigned to the element's 'ref' prop.
 */
export const useTippyTooltip = ( content ) => {
	const [ tooltipElement, setTooltipElement ] = useState( null );

	// Callback ref to set the tooltip element.
	// We use useCallback instead of useRef to ensure this works for conditionally rendered components too.
	const tooltipRef = useCallback( ( node ) => setTooltipElement( node ), [] );

	useEffect( () => {
		if ( ! tooltipElement ) {
			return;
		}

		// Initialize tippy.js on the tooltip element.
		const tippyInstance = tippy( tooltipElement, { content } );

		return () => {
			// Cleanup tippy.js instance on unmount.
			tippyInstance?.destroy();
		};
	}, [ tooltipElement, content ] );

	return tooltipRef;
};
