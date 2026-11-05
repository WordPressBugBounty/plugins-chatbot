/**
 * Number formatting for screens.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Whole number with thousands separators.
 *
 * @param {number} value Value.
 * @return {string} Text.
 */
export const num = ( value ) =>
	Number( value || 0 ).toLocaleString( undefined, {
		maximumFractionDigits: 0,
	} );

/**
 * Percentage from a ratio (0.034 => 3.4%).
 *
 * @param {number} value Ratio.
 * @return {string} Text.
 */
export const pct = ( value ) =>
	`${ ( Number( value || 0 ) * 100 ).toLocaleString( undefined, {
		maximumFractionDigits: 1,
	} ) }%`;

/**
 * Average position (lower is better).
 *
 * @param {number} value Position.
 * @return {string} Text.
 */
export const pos = ( value ) =>
	Number( value ) > 0
		? Number( value ).toLocaleString( undefined, {
				maximumFractionDigits: 1,
			} )
		: '–';

/**
 * Trend text for a relative change (0.12 => "+12%").
 *
 * @param {number|null} change Change.
 * @return {string} Text.
 */
export const trend = ( change ) => {
	if ( null === change || undefined === change ) {
		return __( 'new', 'chatbot' );
	}
	const value = Math.round( Number( change ) * 100 );
	return value >= 0
		? sprintf(
				/* translators: %d: percent increase. */
				__( '+%d%%', 'chatbot' ),
				value
			)
		: sprintf(
				/* translators: %d: percent change (negative). */
				__( '%d%%', 'chatbot' ),
				value
			);
};
