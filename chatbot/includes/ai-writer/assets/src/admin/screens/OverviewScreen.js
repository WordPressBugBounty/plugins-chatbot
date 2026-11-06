/**
 * Overview: the three ways to start, running jobs and recent drafts. The
 * buttons open the Write, Improve Content and WooCommerce menu items.
 */
import { Button, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { core, data, errorMessage, writer } from '../api';
import JobProgress from '../components/JobProgress';
import { emptyBrief } from '../components/write/WriteFlow';
import { navigate } from '../nav';

const examples = () => [
	__(
		'Answers to the five questions customers ask us most',
		'chatbot'
	),
	__( 'How to choose the right option for your needs', 'chatbot' ),
	__( 'What happens when you work with us, step by step', 'chatbot' ),
];

const OverviewScreen = () => {
	const [ jobs, setJobs ] = useState( null );
	const [ recent, setRecent ] = useState( [] );
	const [ error, setError ] = useState( '' );

	const load = () => {
		if ( ! data.can.use ) {
			setJobs( [] );
			return;
		}
		core( `jobs?module=${ data.module }` )
			.then( setJobs )
			.catch( ( err ) => setError( errorMessage( err ) ) );
		writer( 'write/recent' )
			.then( setRecent )
			.catch( () => {} );
	};

	useEffect( () => {
		load();
		// Load once.
	}, [] );

	const active = ( jobs || [] ).filter( ( job ) =>
		[ 'queued', 'running', 'paused', 'failed' ].includes( job.status )
	);

	return (
		<div className="qcwr-write">
			<div className="qcwr-starts">
				<div className="qcwr-card qcwr-start">
					<h2>{ __( 'Write something new', 'chatbot' ) }</h2>
					<p>
						{ __(
							'A post, help article, FAQ, page or product description from one sentence.',
							'chatbot'
						) }
					</p>
					<Button
						variant="primary"
						disabled={ ! data.can.use }
						onClick={ () => navigate( 'write' ) }
					>
						{ __( 'Write', 'chatbot' ) }
					</Button>
				</div>
				<div className="qcwr-card qcwr-start">
					<h2>
						{ __( 'Improve existing content', 'chatbot' ) }
					</h2>
					<p>
						{ __(
							'Make a post or page clearer, shorter or up to date, section by section.',
							'chatbot'
						) }
					</p>
					<Button
						variant="secondary"
						disabled={ ! data.can.use }
						onClick={ () => navigate( 'improve' ) }
					>
						{ __( 'Improve', 'chatbot' ) }
					</Button>
				</div>
				<div className="qcwr-card qcwr-start">
					<h2>
						{ __( 'Answer visitor questions', 'chatbot' ) }
					</h2>
					<p>
						{ __(
							'Turn a question your visitors ask into an FAQ, help article or page section.',
							'chatbot'
						) }
					</p>
					<Button
						variant="secondary"
						disabled={ ! data.can.use }
						onClick={ () => navigate( 'write', { answer: true } ) }
					>
						{ __( 'Answer a question', 'chatbot' ) }
					</Button>
				</div>
			</div>

			{ jobs && ! recent.length && ! active.length && data.can.use && (
				<section className="qcwr-card">
					<h2>
						{ __( 'Not sure where to start?', 'chatbot' ) }
					</h2>
					<p className="qcwr-muted">
						{ __(
							'Pick an idea to begin with. You can change everything.',
							'chatbot'
						) }
					</p>
					<div className="qcwr-examples">
						{ examples().map( ( topic ) => (
							<Button
								key={ topic }
								variant="secondary"
								onClick={ () =>
									navigate( 'write', {
										brief: { ...emptyBrief(), topic },
									} )
								}
							>
								{ topic }
							</Button>
						) ) }
					</div>
				</section>
			) }

			<section className="qcwr-card">
				<h2>{ __( 'Running jobs', 'chatbot' ) }</h2>
				{ error && <p className="qcwr-error">{ error }</p> }
				{ null === jobs && ! error && <Spinner /> }
				{ jobs && ! active.length && (
					<p className="qcwr-muted">
						{ __( 'Nothing is running.', 'chatbot' ) }
					</p>
				) }
				{ active.map( ( job ) =>
					[ 'article', 'improve', 'products' ].includes(
						job.type
					) ? (
						<div
							key={ job.id }
							className="qcwr-job qcwr-job--compact"
						>
							<strong>{ job.title }</strong>
							<Button
								variant="secondary"
								onClick={ () =>
									'improve' === job.type
										? navigate( 'improve', {
												jobId: Number( job.id ),
											} )
										: navigate(
												'products' === job.type
													? 'woo'
													: 'write',
												'products' === job.type
													? {
															productsJob: Number(
																job.id
															),
														}
													: {
															jobId: Number(
																job.id
															),
														}
											)
								}
							>
								{ __( 'Continue', 'chatbot' ) }
							</Button>
						</div>
					) : (
						<JobProgress key={ job.id } jobId={ job.id } />
					)
				) }
			</section>

			{ recent.length > 0 && (
				<section className="qcwr-card">
					<h2>{ __( 'Recent drafts', 'chatbot' ) }</h2>
					<ul className="qcwr-recent">
						{ recent.map( ( item ) => (
							<li key={ item.id }>
								<a href={ item.edit_url }>
									{ item.title ||
										__( '(no title)', 'chatbot' ) }
								</a>
								<span className="qcwr-muted">
									{ ' ' }
									· { item.status }
								</span>
							</li>
						) ) }
					</ul>
				</section>
			) }
		</div>
	);
};

export default OverviewScreen;
