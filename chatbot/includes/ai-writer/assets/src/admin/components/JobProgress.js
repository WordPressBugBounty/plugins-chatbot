/**
 * Shows a job's progress and drives it from the browser.
 *
 * While the job is queued or running, the screen asks the server to run the
 * next step every few seconds (one short request per step). Closing the tab
 * loses nothing: the job continues on WP-Cron and resumes here on return.
 */
import { Button, Spinner, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { core, data, errorMessage } from '../api';

const ACTIVE = [ 'queued', 'running' ];

const statusLabel = ( status ) =>
	( {
		queued: __( 'Waiting to start', 'chatbot' ),
		running: __( 'Working', 'chatbot' ),
		paused: __( 'Stopped', 'chatbot' ),
		done: __( 'Finished', 'chatbot' ),
		failed: __( 'Needs attention', 'chatbot' ),
		cancelled: __( 'Cancelled', 'chatbot' ),
	} )[ status ] || status;

const JobProgress = ( { jobId, onChange } ) => {
	const [ job, setJob ] = useState( null );
	const [ budget, setBudget ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const busy = useRef( false );
	// The latest onChange, so a parent passing a new function each render
	// does not restart loading (which would also stop the runner).
	const changed = useRef( onChange );
	changed.current = onChange;

	const update = useCallback( ( next ) => {
		setJob( next );
		if ( changed.current ) {
			changed.current( next );
		}
	}, [] );

	useEffect( () => {
		let stopped = false;
		core( `jobs/${ jobId }` )
			.then( ( next ) => ! stopped && update( next ) )
			.catch( ( err ) => setError( errorMessage( err ) ) );
		return () => {
			stopped = true;
		};
	}, [ jobId, update ] );

	useEffect( () => {
		if ( ! job || ! ACTIVE.includes( job.status ) ) {
			return undefined;
		}
		const interval =
			Math.max( 2, Number( data.settings.runner_interval ) || 3 ) * 1000;
		const timer = setTimeout( () => {
			if ( busy.current ) {
				return;
			}
			busy.current = true;
			core( `jobs/${ jobId }/tick`, { method: 'POST' } )
				.then( update )
				.catch( ( err ) => setError( errorMessage( err ) ) )
				.finally( () => {
					busy.current = false;
				} );
		}, interval );
		return () => clearTimeout( timer );
	}, [ job, jobId, update ] );

	const act = ( action ) =>
		core( `jobs/${ jobId }/${ action }`, { method: 'POST' } )
			.then( update )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	if ( ! job ) {
		return error ? <p className="qcwr-error">{ error }</p> : <Spinner />;
	}

	const percent = job.total
		? Math.round( ( job.done / job.total ) * 100 )
		: 0;

	return (
		<div className={ `qcwr-job qcwr-job--${ job.status }` }>
			<div className="qcwr-job__head">
				<strong>{ job.title }</strong>
				<span className="qcwr-job__status">
					{ statusLabel( job.status ) }
				</span>
			</div>
			<div
				className="qcwr-job__bar"
				role="progressbar"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow={ percent }
			>
				<span style={ { width: `${ percent }%` } } />
			</div>
			<p className="qcwr-job__text">
				{ ACTIVE.includes( job.status ) && job.current
					? sprintf(
							/* translators: 1: step number, 2: total steps, 3: step label. */
							__( 'Step %1$d of %2$d: %3$s', 'chatbot' ),
							Math.min( job.done + 1, job.total ),
							job.total,
							job.current
						)
					: sprintf(
							/* translators: 1: steps done, 2: total steps. */
							__( '%1$d of %2$d steps done', 'chatbot' ),
							job.done,
							job.total
						) }
			</p>
			{ job.error && <p className="qcwr-error">{ job.error }</p> }
			{ error && <p className="qcwr-error">{ error }</p> }
			{ 'budget' === job.awaiting && (
				<div className="qcwr-job__budget">
					<p>
						{ sprintf(
							/* translators: 1: money spent, 2: budget, both in US dollars. */
							__(
								'This job reached its budget ($%1$s of $%2$s). Raise it to continue.',
								'chatbot'
							),
							Number( job.spent ).toFixed( 2 ),
							Number( job.budget ).toFixed( 2 )
						) }
					</p>
					<TextControl
						type="number"
						min="0"
						step="0.5"
						label={ __( 'New budget (US$)', 'chatbot' ) }
						value={ budget }
						onChange={ setBudget }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="primary"
						disabled={ ! ( parseFloat( budget ) > job.spent ) }
						onClick={ () =>
							core( `jobs/${ jobId }/budget`, {
								method: 'POST',
								data: { budget: parseFloat( budget ) },
							} )
								.then( () => core( `jobs/${ jobId }` ) )
								.then( update )
								.catch( ( err ) =>
									setError( errorMessage( err ) )
								)
						}
					>
						{ __( 'Raise budget and continue', 'chatbot' ) }
					</Button>
				</div>
			) }
			<div className="qcwr-job__actions">
				{ ACTIVE.includes( job.status ) && (
					<Button variant="secondary" onClick={ () => act( 'stop' ) }>
						{ __( 'Stop', 'chatbot' ) }
					</Button>
				) }
				{ job.status === 'paused' && ! job.awaiting && (
					<Button variant="primary" onClick={ () => act( 'resume' ) }>
						{ __( 'Resume', 'chatbot' ) }
					</Button>
				) }
				{ job.status === 'failed' && (
					<Button variant="primary" onClick={ () => act( 'resume' ) }>
						{ __( 'Retry the failed step', 'chatbot' ) }
					</Button>
				) }
			</div>
			<ol className="qcwr-job__steps">
				{ job.steps.map( ( step ) => (
					<li
						key={ step.seq }
						className={ `qcwr-step qcwr-step--${ step.status }` }
					>
						<span>{ step.label }</span>
						<em>{ step.status }</em>
						{ step.error && (
							<small className="qcwr-error">{ step.error }</small>
						) }
					</li>
				) ) }
			</ol>
		</div>
	);
};

export default JobProgress;
