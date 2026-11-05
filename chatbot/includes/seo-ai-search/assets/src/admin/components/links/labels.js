/**
 * Shared labels for the Links area.
 */
import { __ } from '@wordpress/i18n';

export const STATUS = {
	unchecked: __( 'Not checked yet', 'chatbot' ),
	live: __( 'Live', 'chatbot' ),
	changed: __( 'Changed', 'chatbot' ),
	lost: __( 'Lost', 'chatbot' ),
	blocked: __( 'Not checked (robots.txt)', 'chatbot' ),
	error: __( 'Could not check', 'chatbot' ),
};

export const ORIGIN = {
	csv: __( 'Search Console CSV', 'chatbot' ),
	bing: __( 'Bing', 'chatbot' ),
	outreach: __( 'Outreach', 'chatbot' ),
	manual: __( 'Added by hand', 'chatbot' ),
	exchange: __( 'Exchange', 'chatbot' ),
	referral: __( 'Referral', 'chatbot' ),
	backlinks: __( 'Backlink data', 'chatbot' ),
};

export const KIND = {
	mention: __( 'Mentions', 'chatbot' ),
	resource: __( 'Resource pages', 'chatbot' ),
	broken: __( 'Broken links', 'chatbot' ),
	publication: __( 'Publications', 'chatbot' ),
	partner: __( 'Partners', 'chatbot' ),
	other: __( 'Other', 'chatbot' ),
};

export const TYPE = {
	link_request: __( 'Link request', 'chatbot' ),
	guest_post: __( 'Guest post', 'chatbot' ),
	collaboration: __( 'Collaboration', 'chatbot' ),
};

export const STEP = {
	to_contact: __( 'To contact', 'chatbot' ),
	contacted: __( 'Contacted', 'chatbot' ),
	replied: __( 'Replied', 'chatbot' ),
	won: __( 'Won', 'chatbot' ),
	declined: __( 'Declined', 'chatbot' ),
	idea: __( 'Idea', 'chatbot' ),
	pitched: __( 'Pitched', 'chatbot' ),
	accepted: __( 'Accepted', 'chatbot' ),
	submitted: __( 'Submitted', 'chatbot' ),
	published: __( 'Published', 'chatbot' ),
	suggested: __( 'Suggested', 'chatbot' ),
	talking: __( 'Talking', 'chatbot' ),
	agreed: __( 'Agreed', 'chatbot' ),
	live: __( 'Live', 'chatbot' ),
};

export const REL = [
	{ value: '', label: __( 'Not known', 'chatbot' ) },
	{ value: 'follow', label: __( 'Normal link', 'chatbot' ) },
	{ value: 'nofollow', label: 'nofollow' },
	{ value: 'sponsored', label: 'sponsored' },
	{ value: 'ugc', label: 'ugc' },
];

export const EXCHANGE = {
	proposed: __( 'Proposed', 'chatbot' ),
	live: __( 'Live', 'chatbot' ),
	broken: __( 'Broken', 'chatbot' ),
	ended: __( 'Ended', 'chatbot' ),
};

export const options = ( map, all ) => [
	...( all ? [ { value: '', label: all } ] : [] ),
	...Object.keys( map ).map( ( value ) => ( {
		value,
		label: map[ value ],
	} ) ),
];
