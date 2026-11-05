/**
 * The Google Ads API spot in Settings & AI Provider > Connections.
 *
 * An extension (WPBot Pro) fills it through the
 * `qcld_wpbot_seo.settings.connections.google` filter. When nothing fills it
 * and the host shows Pro previews (Free hosts, until the owner hides them),
 * a labelled, inactive Pro preview explains what the spot is for. No Pro code
 * runs behind the preview, and the module never checks which edition is
 * installed: it only reacts to what has been registered.
 */
import { applyFilters, hasFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import ProPreview from './ProPreview';

export const ADS_FILTER = 'qcld_wpbot_seo.settings.connections.google';

/**
 * Does the spot have something to show (a real setup card or a preview)?
 *
 * @return {boolean} True when an extension fills it or Pro previews are on.
 */
export const adsSlotFilled = () =>
	hasFilter( ADS_FILTER ) || ( data.previews || [] ).length > 0;

/**
 * Preview text: what the Google Ads API adds (plain text, escaped by React).
 *
 * @return {Object} Preview for ProPreview.
 */
const adsPreview = () => ( {
	label: __( 'Google Ads API and Keyword Planner', 'chatbot' ),
	text: __(
		'Read Google Ads directly, without Google Analytics, and use Google Keyword Planner for search volumes.',
		'chatbot'
	),
	features: [
		__(
			'Paid searches straight from Google Ads, with cost and conversions',
			'chatbot'
		),
		__(
			'Google Keyword Planner volumes and ideas in research, the Keyword Vault and the Topic Map',
			'chatbot'
		),
		__(
			'Monthly refresh of Keyword Vault numbers, with trend, competition and bid range',
			'chatbot'
		),
	],
} );

/**
 * The spot: the extension's card, or the Pro preview, or nothing.
 *
 * @param {Object} props          Props.
 * @param {Object} props.settings Current settings.
 * @return {Element|null} Content.
 */
const GoogleAdsSlot = ( { settings } ) => {
	const card = applyFilters( ADS_FILTER, null, { settings } );
	if ( card ) {
		return card;
	}
	if ( ( data.previews || [] ).length > 0 ) {
		return <ProPreview preview={ adsPreview() } as="h3" />;
	}
	return null;
};

export default GoogleAdsSlot;
