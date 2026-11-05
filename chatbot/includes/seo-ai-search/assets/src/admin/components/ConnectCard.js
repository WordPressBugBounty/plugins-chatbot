/**
 * Inline card at the top of a screen when Search Console is not connected
 * (or stopped working). The screen's own content always renders below it:
 * Search Console is optional and adds real data on top (S14).
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import { navigate } from '../nav';

const ConnectCard = ( { status, strong = false } ) => {
	if ( ! status || 'connected' === status.google.status ) {
		return null;
	}
	const failing = 'failing' === status.google.status;
	// Opportunities (R3): a bold note until Search Console is connected; not dismissible.
	const lead = strong
		? __(
				'Connect Google Search Console for significantly better results.',
				'chatbot'
			)
		: __( 'Search Console is optional.', 'chatbot' );
	const body = strong
		? __(
				'These opportunities come from your site’s content alone. Search Console adds the searches you appear for, your positions and your clicks, so the list can rank what will bring the most visitors. It is free.',
				'chatbot'
			)
		: __(
				'Connecting it adds your own clicks, positions and searches to these numbers (read-only, through your own Google app).',
				'chatbot'
			);
	return (
		<div
			className={ `qcseo-connect-inline ${ failing ? 'is-failing' : '' } ${
				strong && ! failing ? 'is-strong' : ''
			}` }
			role={ failing ? 'alert' : 'note' }
		>
			<p>
				<strong>
					{ failing
						? __(
								'Google Search Console stopped working.',
								'chatbot'
							)
						: lead }
				</strong>{ ' ' }
				{ failing ? status.google.message : body }
			</p>
			{ data.can.keys ? (
				<Button
					variant={ failing ? 'primary' : 'secondary' }
					size="compact"
					onClick={ () =>
						navigate( 'settings', { tab: 'connections' } )
					}
				>
					{ failing
						? __( 'Fix the connection', 'chatbot' )
						: __( 'Connect Search Console', 'chatbot' ) }
				</Button>
			) : (
				<span className="qcseo-muted">
					{ strong
						? __(
								'Ask your site administrator to connect it.',
								'chatbot'
							)
						: __(
								'An administrator can connect it.',
								'chatbot'
							) }
				</span>
			) }
		</div>
	);
};

export default ConnectCard;
