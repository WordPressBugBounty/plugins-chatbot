/**
 * Brand & Business (CR-15): one page for how the business sounds (Brand
 * Voice) and what it can prove and how its customers talk (Business
 * Profile: Persuasion Kit and Customer words).
 *
 * The tab follows the URL (`section=voice|business|kit|words`; the old
 * `tab=profile` opens the Kit) and is written back to it, so a link or a
 * reload lands on the same tab. `#qcwr-site-voice` keeps opening Brand Voice.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import ProfileScreen from './ProfileScreen';
import VoiceScreen from './VoiceScreen';

/**
 * Write the open tab to the address bar (no reload, no new history entry).
 *
 * @param {string} section voice, kit or words.
 */
const remember = ( section ) => {
	try {
		const url = new window.URL( window.location.href );
		url.searchParams.set( 'tab', 'voice' );
		url.searchParams.set( 'section', section );
		window.history.replaceState( null, '', url.toString() );
	} catch {}
};

/**
 * The tab to open first.
 *
 * @return {string} 'voice' or 'business'.
 */
const firstTab = () => {
	if ( '#qcwr-site-voice' === window.location.hash || ! data.business ) {
		return 'voice';
	}
	return [ 'business', 'kit', 'words' ].includes( data.section )
		? 'business'
		: 'voice';
};

const BrandBusinessScreen = () => {
	const [ tab, setTab ] = useState( firstTab );
	const choose = ( next ) => {
		setTab( next );
		remember( 'voice' === next ? 'voice' : 'kit' );
	};
	return (
		<div className="qcwr-brand">
			{ data.business && (
				<div
					className="qcwr-tabs qcwr-brand__tabs"
					role="tablist"
					aria-label={ __( 'Brand & Business', 'chatbot' ) }
				>
					<Button
						role="tab"
						aria-selected={ 'voice' === tab }
						className={ 'voice' === tab ? 'is-active' : '' }
						onClick={ () => choose( 'voice' ) }
					>
						{ __( 'Brand Voice', 'chatbot' ) }
					</Button>
					<Button
						role="tab"
						aria-selected={ 'business' === tab }
						className={ 'business' === tab ? 'is-active' : '' }
						onClick={ () => choose( 'business' ) }
					>
						{ __( 'Business Profile', 'chatbot' ) }
					</Button>
				</div>
			) }
			{ 'business' === tab ? (
				<ProfileScreen
					initial={ 'words' === data.section ? 'words' : 'kit' }
					onChange={ remember }
				/>
			) : (
				<VoiceScreen />
			) }
		</div>
	);
};

export default BrandBusinessScreen;
