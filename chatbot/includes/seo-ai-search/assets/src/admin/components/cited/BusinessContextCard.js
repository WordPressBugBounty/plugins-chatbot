/**
 * Get cited by AI > Business Context: a short card that shows what WPBot
 * knows about the business and links to WPBot > Business Context, where it
 * is edited for every module. Replaces the full Site Context panel when the
 * host's WPBot Core has the Business Context screen.
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';

const BusinessContextCard = ( { onChange } ) => {
	const [ state, setState ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'cited/context' )
			.then( ( next ) => {
				setState( next );
				onChange( next );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );
	if ( ! state ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const summary = state.summary;
	return (
		<div className="qcseo-card qcseo-business-context-card">
			<h3>{ __( 'Your business', 'chatbot' ) }</h3>
			{ summary ? (
				<>
					<p>{ summary.business }</p>
					{ summary.offers && summary.offers.length > 0 && (
						<p className="qcseo-muted">
							{ summary.offers.slice( 0, 6 ).join( ' · ' ) }
						</p>
					) }
				</>
			) : (
				<p>
					{ __(
						'Fill in Business Context first: Get cited by AI uses it to find the right places and to write drafts that sound like you.',
						'chatbot'
					) }
				</p>
			) }
			<a
				className={ `components-button ${
					summary ? 'is-secondary' : 'is-primary'
				}` }
				href={ data.businessContext }
			>
				{ summary
					? __( 'Edit in Business Context', 'chatbot' )
					: __( 'Fill in Business Context', 'chatbot' ) }
			</a>
		</div>
	);
};

export default BusinessContextCard;
