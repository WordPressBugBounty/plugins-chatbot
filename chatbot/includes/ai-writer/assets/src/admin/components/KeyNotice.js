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
		<div className="qcwr-key-notice" role="alert">
			{ notices.map( ( notice ) => (
				<div key={ notice.provider } className="qcwr-key-notice__item">
					<strong className="qcwr-key-notice__cause">
						{ notice.cause }
					</strong>
					<span className="qcwr-key-notice__fix">
						{ sprintf(
							/* translators: %s: what to do. */
							__( 'How to fix it: %s', 'chatbot' ),
							notice.fix
						) }
					</span>
					{ data.can.keys && (
						<a
							className="qcwr-key-notice__link"
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
