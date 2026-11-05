/**
 * Shared "is AI ready?" notice for every WPBot module (follow-up 3.0).
 *
 * Plain JS on wp.element (no build step). Pass the object returned by
 * qcld_wpbot_core_ai_readiness() or GET /wpbot-core/v1/ai-readiness/{module}:
 *
 *   wp.element.createElement( window.qcldWpbotCore.AiReadyNotice, { readiness } )
 *
 * aiDisabledReason( readiness ) returns '' when AI is ready, otherwise the
 * short reason to show next to a disabled AI button.
 *
 * @param {Object} wp WordPress globals.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.element ) {
		return;
	}
	const el = wp.element.createElement;
	const __ =
		wp.i18n && wp.i18n.__
			? wp.i18n.__
			: function ( text ) {
					return text;
				};

	function AiReadyNotice( props ) {
		const state = props && props.readiness;
		if ( ! state ) {
			return null;
		}
		if ( state.ready ) {
			return state.warning
				? el(
						'div',
						{
							className: 'qcld-ai-readiness is-warning',
							role: 'status',
						},
						el( 'strong', null, state.warning ),
						' ',
						__(
							'Your backup AI provider is answering for now.',
							'chatbot'
						)
					)
				: null;
		}
		return el(
			'div',
			{ className: 'qcld-ai-readiness', role: 'alert' },
			el(
				'strong',
				{ className: 'qcld-ai-readiness__message' },
				state.message
			),
			state.url && state.action_label
				? el(
						'a',
						{
							className: 'qcld-ai-readiness__button',
							href: state.url,
						},
						state.action_label
					)
				: null
		);
	}

	function aiDisabledReason( state ) {
		if ( ! state || state.ready ) {
			return '';
		}
		return state.message || __( 'AI is not ready.', 'chatbot' );
	}

	window.qcldWpbotCore = window.qcldWpbotCore || {};
	window.qcldWpbotCore.AiReadyNotice = AiReadyNotice;
	window.qcldWpbotCore.aiDisabledReason = aiDisabledReason;
} )( window.wp );
