/**
 * Rounds a number to the nearest 0.5 increment.
 * This reduces URL variations for DPR values.
 *
 * @param {number} value - The value to round.
 * @return {number} The rounded value (e.g., 1.666 -> 1.5, 2.3 -> 2.5).
 */
export const roundToHalf = ( value ) => {
	return Math.round( value * 2 ) / 2;
};
