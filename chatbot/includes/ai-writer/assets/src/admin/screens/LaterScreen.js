/**
 * Placeholder for a tab that arrives in a later test build.
 */
import { __ } from '@wordpress/i18n';

const LaterScreen = ( { title, text } ) => (
	<div className="qcwr-card qcwr-later">
		<h2>{ title }</h2>
		<p>{ text }</p>
		<p className="qcwr-muted">
			{ __(
				'This part arrives in a later test build of AI Writer.',
				'chatbot'
			) }
		</p>
	</div>
);

export default LaterScreen;
