/**
 * Small pieces shared by Research and the Keyword Vault (S14).
 *
 * Honest numbers: exact volume, cost per click, difficulty and rank are
 * shown only from a real source, always with its badge; AI values are the
 * labels high / medium / low with "AI estimate".
 */
import { __, sprintf } from '@wordpress/i18n';
import { num } from '../../format';
import Sparkline from '../Sparkline';

export const INTENTS = {
	informational: __( 'Informational', 'chatbot' ),
	commercial: __( 'Commercial', 'chatbot' ),
	transactional: __( 'Transactional', 'chatbot' ),
	navigational: __( 'Navigational', 'chatbot' ),
};

export const LEVELS = {
	high: __( 'High', 'chatbot' ),
	medium: __( 'Medium', 'chatbot' ),
	low: __( 'Low', 'chatbot' ),
};

export const STATUSES = {
	researched: __( 'Researched', 'chatbot' ),
	targeted: __( 'Targeted', 'chatbot' ),
	ranking: __( 'Ranking', 'chatbot' ),
	archived: __( 'Archived', 'chatbot' ),
};

export const PAGE_TYPES = {
	guide: __( 'Guide', 'chatbot' ),
	how_to: __( 'How-to', 'chatbot' ),
	list: __( 'List', 'chatbot' ),
	comparison: __( 'Comparison', 'chatbot' ),
	review: __( 'Review', 'chatbot' ),
	product: __( 'Product page', 'chatbot' ),
	category: __( 'Category page', 'chatbot' ),
	landing: __( 'Landing page', 'chatbot' ),
	faq: __( 'FAQ', 'chatbot' ),
	tool: __( 'Tool', 'chatbot' ),
	news: __( 'News', 'chatbot' ),
	other: __( 'Other', 'chatbot' ),
};

/**
 * Source badge next to a real number.
 *
 * @param {Object} props        Props.
 * @param {string} props.source Source name (DataForSEO, Search Console…).
 * @return {Element|null} Badge.
 */
export const SourceBadge = ( { source } ) =>
	source ? <span className="qcseo-source-badge">{ source }</span> : null;

/**
 * An AI label (high / medium / low) marked "AI estimate", with the reason
 * as a tooltip.
 *
 * @param {Object} props        Props.
 * @param {string} props.level  high, medium or low.
 * @param {string} props.reason One-line reason.
 * @return {Element|string} Label.
 */
export const AiLabel = ( { level, reason } ) =>
	level ? (
		<span className={ `qcseo-level is-${ level }` } title={ reason || '' }>
			{ LEVELS[ level ] || level }{ ' ' }
			<span className="qcseo-ai-estimate">
				{ __( 'AI estimate', 'chatbot' ) }
			</span>
		</span>
	) : (
		'—'
	);

/**
 * A real number with its source, or nothing.
 *
 * @param {Object} props      Props.
 * @param {Object} props.fact { value, source }.
 * @param {string} props.kind volume, cpc, position or trend.
 * @return {Element|null} Fact.
 */
export const RealFact = ( { fact, kind } ) => {
	if ( ! fact || null === fact.value || undefined === fact.value ) {
		return null;
	}
	let value = num( fact.value );
	if ( 'cpc' === kind ) {
		value = Number( fact.value ).toFixed( 2 );
	} else if ( 'position' === kind ) {
		value = sprintf(
			/* translators: %s: average position. */
			__( 'position %s', 'chatbot' ),
			fact.value
		);
	} else if ( 'trend' === kind ) {
		return (
			<span className="qcseo-real">
				<Sparkline
					points={ fact.value }
					label={ __( 'Trend', 'chatbot' ) }
				/>
				<SourceBadge source={ fact.source } />
			</span>
		);
	}
	return (
		<span className="qcseo-real">
			{ value } <SourceBadge source={ fact.source } />
		</span>
	);
};

/**
 * Save a text as a file in the browser.
 *
 * @param {string} text     Content.
 * @param {string} filename File name.
 * @param {string} type     MIME type.
 */
export const download = ( text, filename, type = 'text/csv' ) => {
	const blob = new window.Blob( [ text ], { type } );
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.URL.revokeObjectURL( url );
};
