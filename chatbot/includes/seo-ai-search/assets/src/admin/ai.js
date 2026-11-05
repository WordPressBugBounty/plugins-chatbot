/**
 * AI readiness for the whole app (follow-up 3.0).
 *
 * WPBot Core answers "is AI ready for SEO & AI Search?" with the cause and
 * the fix. The answer arrives with the page and is refreshed after AI
 * settings change; every AI action reads it, so a button is disabled with
 * the reason next to it instead of failing silently.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { core, data } from './api';

let current = data.readiness || null;
const listeners = new Set();

/**
 * Replace the readiness (after a save or a fresh check) and re-render
 * everything that shows it.
 *
 * @param {Object|null} next Readiness from WPBot Core.
 */
export const setReadiness = ( next ) => {
	current = next || null;
	listeners.forEach( ( listener ) => listener( current ) );
};

/**
 * Ask WPBot Core again (for example after a test call or a failed AI call).
 *
 * @return {Promise<Object>} Readiness.
 */
export const refreshReadiness = () =>
	core( `ai-readiness/${ data.module }` ).then( ( next ) => {
		setReadiness( next );
		return next;
	} );

/**
 * The current readiness, kept up to date.
 *
 * @return {Object|null} Readiness, or null on an older WPBot Core.
 */
export const useAiReady = () => {
	const [ state, setState ] = useState( current );
	useEffect( () => {
		listeners.add( setState );
		return () => listeners.delete( setState );
	}, [] );
	return state;
};

/**
 * Why AI actions are disabled right now ('' when AI is ready, or when an
 * older WPBot Core gives no answer).
 *
 * @param {Object|null} state Readiness.
 * @return {string} Reason.
 */
export const aiBlocked = ( state ) =>
	state && ! state.ready
		? state.message || __( 'AI is not ready.', 'chatbot' )
		: '';

/**
 * Bold notice at the top of screens with AI actions.
 *
 * @return {Element|null} Notice.
 */
export const AiReady = () => {
	const state = useAiReady();
	const Shared = window.qcldWpbotCore && window.qcldWpbotCore.AiReadyNotice;
	if ( ! state ) {
		return null;
	}
	if ( Shared ) {
		return <Shared readiness={ state } />;
	}
	if ( state.ready ) {
		return null;
	}
	return (
		<div className="qcld-ai-readiness" role="alert">
			<strong className="qcld-ai-readiness__message">
				{ state.message }
			</strong>
			{ state.url && state.action_label && (
				<a className="qcld-ai-readiness__button" href={ state.url }>
					{ state.action_label }
				</a>
			) }
		</div>
	);
};

/**
 * Short reason and link next to a disabled AI button.
 *
 * @return {Element|null} Reason.
 */
export const AiReason = () => {
	const state = useAiReady();
	const reason = aiBlocked( state );
	if ( ! reason ) {
		return null;
	}
	return (
		<span className="qcseo-ai-reason">
			{ reason }{ ' ' }
			{ state.url && state.action_label && (
				<a href={ state.url }>{ state.action_label }</a>
			) }
		</span>
	);
};
