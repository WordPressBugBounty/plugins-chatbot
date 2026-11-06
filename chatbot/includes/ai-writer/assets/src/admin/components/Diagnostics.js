/**
 * Diagnostics: run a small test job to check the queue on this host.
 */
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage, writer } from '../api';
import JobProgress from './JobProgress';

const Diagnostics = () => {
	const [ jobId, setJobId ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const start = ( withAi ) => {
		setBusy( true );
		setError( '' );
		writer( 'diagnostics/test-job', {
			method: 'POST',
			data: { with_ai: withAi },
		} )
			.then( ( result ) => setJobId( result.job_id ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-card">
			<h2>
				{ __( 'Check that background jobs work', 'chatbot' ) }
			</h2>
			<p className="qcwr-muted">
				{ __(
					'Long writing jobs run in small steps. This test runs a few quick steps; you can reload the page or stop and resume it.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => start( false ) }
				>
					{ __( 'Run a test job', 'chatbot' ) }
				</Button>
				<Button
					variant="tertiary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => start( true ) }
				>
					{ __(
						'Run a test job with one AI call',
						'chatbot'
					) }
				</Button>
			</div>
			{ jobId > 0 && <JobProgress key={ jobId } jobId={ jobId } /> }
		</div>
	);
};

export default Diagnostics;
