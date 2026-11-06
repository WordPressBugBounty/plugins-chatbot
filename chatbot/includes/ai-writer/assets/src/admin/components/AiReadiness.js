/**
 * The shared "AI is not ready" notice (CR-5), in WPBot Core's style: the
 * cause in bold and one button to the fix on "Settings & AI Provider".
 * In editors the button opens a new tab, so unsaved edits are kept.
 * With an older WPBot Core (no readiness check) the failing-key notice
 * is shown instead, as before.
 */
import { Disabled } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import KeyNotice from './KeyNotice';

/**
 * Short reason to show next to disabled AI buttons ('' when AI is ready).
 *
 * @param {Object|null} readiness Readiness from WPBot Core.
 * @return {string} Reason.
 */
export const aiDisabledReason = ( readiness ) => {
	if ( ! readiness || readiness.ready ) {
		return '';
	}
	return readiness.message || __( 'AI is not ready.', 'chatbot' );
};

/**
 * AI actions, switched off with the reason while AI is not ready (CR-5).
 *
 * @param {Object}  props          Props.
 * @param {Element} props.children Panel content.
 * @return {Element} Content.
 */
export const AiOnly = ( { children } ) => {
	const reason = aiDisabledReason( data.readiness );
	if ( ! reason ) {
		return children;
	}
	return (
		<>
			<p className="qcwr-ai-off">{ reason }</p>
			<Disabled>{ children }</Disabled>
		</>
	);
};

const AiReadiness = ( { readiness, health = [], newTab = false } ) => {
	if ( ! readiness ) {
		return <KeyNotice notices={ health } />;
	}
	if ( readiness.ready ) {
		return readiness.warning ? (
			<div className="qcld-ai-readiness is-warning" role="status">
				<strong>{ readiness.warning }</strong>{ ' ' }
				{ __(
					'Your backup AI provider is answering for now.',
					'chatbot'
				) }
			</div>
		) : null;
	}
	return (
		<div className="qcld-ai-readiness qcwr-ai-readiness" role="alert">
			<strong className="qcld-ai-readiness__message">
				{ readiness.message }
			</strong>
			{ readiness.url && readiness.action_label && (
				<a
					className="qcld-ai-readiness__button"
					href={ readiness.url }
					target={ newTab ? '_blank' : undefined }
					rel={ newTab ? 'noopener noreferrer' : undefined }
				>
					{ readiness.action_label }
					{ newTab && (
						<span className="screen-reader-text">
							{ __( '(opens in a new tab)', 'chatbot' ) }
						</span>
					) }
				</a>
			) }
		</div>
	);
};

export default AiReadiness;
