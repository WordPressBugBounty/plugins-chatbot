/**
 * "Get search volumes": shows the estimate first, runs only after a
 * second click, then follows the job. Cached keywords cost nothing.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../api';
import JobProgress from './JobProgress';
import { unitLabel } from './KeywordData';

/**
 * @param {Object}   props        Props.
 * @param {string}   props.scope  opportunities | top.
 * @param {number}   props.limit  Most searches.
 * @param {string}   props.label  Button label.
 * @param {Function} props.onDone Called when volumes are in.
 * @return {Element} Lookup.
 */
const VolumeLookup = ( {
	scope = 'opportunities',
	limit = 200,
	label,
	onDone,
} ) => {
	const [ estimate, setEstimate ] = useState( null );
	const [ job, setJob ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const ask = () => {
		setBusy( true );
		setMessage( '' );
		seo( 'keywords/estimate', { method: 'POST', data: { scope, limit } } )
			.then( setEstimate )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const go = () => {
		setBusy( true );
		seo( 'keywords/volumes', { method: 'POST', data: { scope, limit } } )
			.then( ( r ) => {
				setEstimate( null );
				if ( r.job_id ) {
					setJob( r.job_id );
				} else if ( onDone ) {
					onDone();
				}
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<div className="qcseo-volume-lookup">
			{ ! estimate && ! job && (
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ ask }
				>
					{ label || __( 'Get search volumes', 'chatbot' ) }
				</Button>
			) }
			{ estimate && (
				<p>
					{ 0 === estimate.keywords
						? __(
								'All these searches already have volumes from the last 30 days. Nothing to pay.',
								'chatbot'
							)
						: sprintf(
								/* translators: 1: searches to look up, 2: cached, 3: cost, 4: unit, 5: provider. */
								__(
									'%1$d searches to look up (%2$d already cached). Estimated cost: %3$s %4$s with %5$s.',
									'chatbot'
								),
								estimate.keywords,
								estimate.cached,
								Number( estimate.cost ).toLocaleString(
									undefined,
									{ maximumFractionDigits: 4 }
								),
								unitLabel( estimate.unit ),
								estimate.provider
							) }
					{ null !== estimate.left &&
						undefined !== estimate.left &&
						' ' +
							sprintf(
								/* translators: 1: budget left, 2: unit. */
								__(
									'Budget left this month: %1$s %2$s.',
									'chatbot'
								),
								Number( estimate.left ).toLocaleString(
									undefined,
									{ maximumFractionDigits: 2 }
								),
								unitLabel( estimate.unit )
							) }{ ' ' }
					{ estimate.keywords > 0 && (
						<Button
							variant="primary"
							size="compact"
							isBusy={ busy }
							disabled={ busy }
							onClick={ go }
						>
							{ __( 'Look up now', 'chatbot' ) }
						</Button>
					) }{ ' ' }
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => setEstimate( null ) }
					>
						{ __( 'Cancel', 'chatbot' ) }
					</Button>
				</p>
			) }
			{ job > 0 && (
				<JobProgress
					jobId={ job }
					onChange={ ( state ) => {
						if ( state && 'done' === state.status ) {
							setJob( 0 );
							if ( onDone ) {
								onDone();
							}
						}
					} }
				/>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
		</div>
	);
};

export default VolumeLookup;
