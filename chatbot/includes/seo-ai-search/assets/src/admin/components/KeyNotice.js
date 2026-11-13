/**
 * Bold notice for a failing AI key: names the cause and the fix.
 */
import { __, sprintf } from '@wordpress/i18n';
import { data } from '../api';

const KeyNotice = ( { notices } ) => {
	if ( ! notices || ! notices.length ) {
		return null;
	}
	return (
		<div className="qcseo-key-notice" role="alert">
			{ notices.map( ( notice ) => (
				<div key={ notice.provider } className="qcseo-key-notice__item">
					<strong className="qcseo-key-notice__cause">
						{ notice.cause }
					</strong>
					<span className="qcseo-key-notice__fix">
						{ sprintf(
							/* translators: %s: what to do. */
							__( 'How to fix it: %s', 'chatbot' ),
							notice.fix
						) }
					</span>
					{ data.can.keys && (
						<a
							className="qcseo-key-notice__link"
							href={ `${ data.pageUrl }&tab=settings` }
						>
							{ __( 'Open AI settings', 'chatbot' ) }
						</a>
					) }
				</div>
			) ) }
		</div>
	);
};

export default KeyNotice;
