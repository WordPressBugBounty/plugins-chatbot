/**
 * Shows a job's progress and drives it from the browser.
 *
 * While the job is queued or running, the screen asks the server to run the
 * next step every few seconds (one short request per step). Closing the tab
 * loses nothing: the job continues on WP-Cron and resumes here on return.
 */
import { Button, Spinner } from '@wordpress/components';
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
		return error ? <p className="qcseo-error">{ error }</p> : <Spinner />;
	}

	const percent = job.total
		? Math.round( ( job.done / job.total ) * 100 )
		: 0;

	return (
		<div className={ `qcseo-job qcseo-job--${ job.status }` }>
			<div className="qcseo-job__head">
				<strong>{ job.title }</strong>
				<span className="qcseo-job__status">
					{ statusLabel( job.status ) }
				</span>
			</div>
			<div
				className="qcseo-job__bar"
				role="progressbar"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow={ percent }
			>
				<span style={ { width: `${ percent }%` } } />
			</div>
			<p className="qcseo-job__text">
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
			{ job.error && <p className="qcseo-error">{ job.error }</p> }
			{ error && <p className="qcseo-error">{ error }</p> }
			<div className="qcseo-job__actions">
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
			<ol className="qcseo-job__steps">
				{ job.steps.map( ( step ) => (
					<li
						key={ step.seq }
						className={ `qcseo-step qcseo-step--${ step.status }` }
					>
						<span>{ step.label }</span>
						<em>{ step.status }</em>
						{ step.error && (
							<small className="qcseo-error">
								{ step.error }
							</small>
						) }
					</li>
				) ) }
			</ol>
		</div>
	);
};

export default JobProgress;
