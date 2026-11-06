/**
 * The "Write something new" flow: brief → questions → outline → writing → done.
 *
 * The job lives on the server, so the flow can be left and resumed: opening
 * it with a jobId picks up wherever the job is.
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import JobProgress from '../JobProgress';
import BriefStep from './BriefStep';
import QuestionsStep from './QuestionsStep';
import OutlineStep from './OutlineStep';
import DoneStep from './DoneStep';
import DuplicateCheck from './DuplicateCheck';
import DataStep from './DataStep';

export const emptyBrief = () => ( {
	type: data.settings.default_type,
	topic: '',
	length: data.settings.default_length,
	words: 0,
	audience: '',
	keywords: [],
	tone: '',
	post_type: 'post',
	sources: { post_ids: [], document_ids: [], chatbot: undefined, notes: '' },
	answers: [],
	// Shared keyword and topic map (brief 7.10).
	main_query: '',
	map_topic: '',
	idea_id: 0,
	// From SEO & AI Search (round 2), or typed: intent, questions, links, numbers.
	intent: '',
	questions: [],
	link_to: [],
	link_from: [],
	keyword_numbers: {},
	numbers: {},
	vault_id: 0,
	// CR-16: article format ('' = none) and whether the owner chose it.
	format: '',
	format_picked: false,
	// CR-16 F2: facts ticked in the Real data step, and the ones still missing.
	facts: [],
	missing: [],
	// CR-16 F3: the research analysis the owner approved (kept on the server).
	research_id: '',
} );

const WriteFlow = ( {
	initialJobId = 0,
	initialBrief = null,
	linkPosts = null,
	onExit,
	onResult,
	exitLabel,
} ) => {
	const [ step, setStep ] = useState( initialJobId ? 'job' : 'brief' );
	const [ brief, setBrief ] = useState( initialBrief || emptyBrief() );
	const [ jobId, setJobId ] = useState( initialJobId );
	const [ view, setView ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	// Pages that already target this search (asked once per brief).
	const [ duplicates, setDuplicates ] = useState( null );

	const refresh = ( id ) =>
		writer( `write/jobs/${ id }` )
			.then( ( next ) => {
				setView( next );
				if ( next.result && next.result.into_draft && onResult ) {
					// Edit screen: the editor inserts the result into the open draft.
					onResult( next.result, id );
				} else if ( next.result ) {
					setStep( 'done' );
				} else if ( 'outline' === next.awaiting ) {
					setStep( 'outline' );
				} else {
					setStep( 'job' );
				}
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	useEffect( () => {
		if ( initialJobId ) {
			refresh( initialJobId );
		}
		// Load the job once when opened with one.
	}, [ initialJobId ] );

	// A step that just changed the brief passes it in (state updates later).
	const start = ( override = null ) => {
		setBusy( true );
		setError( '' );
		writer( 'write/start', {
			method: 'POST',
			data: { brief: override || brief },
		} )
			.then( ( next ) => {
				setJobId( next.job.id );
				setView( next );
				setStep( 'job' );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	// After the brief: questions (if asked), then the outline.
	const questionsOrStart = ( override = null ) =>
		data.settings.ask_questions
			? setStep( 'questions' )
			: start( override );
	// CR-16 F2: a piece in a format gathers its real data first.
	const afterBrief = () =>
		brief.format ? setStep( 'data' ) : questionsOrStart();

	const onJobChange = ( job ) => {
		if ( 'done' === job.status || 'paused' === job.status ) {
			refresh( job.id );
		}
	};

	return (
		<div className="qcwr-flow">
			<div className="qcwr-flow__head">
				<Button variant="link" onClick={ onExit }>
					{ exitLabel || __( '← Back to start', 'chatbot' ) }
				</Button>
				{ view && view.topic && (
					<strong className="qcwr-flow__topic">{ view.topic }</strong>
				) }
			</div>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{ 'brief' === step && (
				<BriefStep
					brief={ brief }
					setBrief={ setBrief }
					linkPosts={ linkPosts }
					busy={ busy }
					onNext={ () => {
						const next = afterBrief;
						if ( duplicates !== null || brief.target_post ) {
							next();
							return;
						}
						setBusy( true );
						writer( 'write/duplicates', {
							method: 'POST',
							data: { brief },
						} )
							.then( ( result ) => {
								setDuplicates( result.matches );
								if ( ! result.matches.length ) {
									next();
								} else {
									setStep( 'duplicates' );
								}
							} )
							// The check never blocks writing.
							.catch( () => next() )
							.finally( () => setBusy( false ) );
					} }
				/>
			) }
			{ 'duplicates' === step && (
				<DuplicateCheck
					matches={ duplicates || [] }
					onBack={ () => setStep( 'brief' ) }
					onAngle={ ( match ) => {
						setBrief( {
							...brief,
							sources: {
								...brief.sources,
								notes: `${ brief.sources.notes || '' }\n${
									// translators: %s: title of the existing page.
									__(
										'Take a different angle from the existing page "%s" and do not repeat what it covers.',
										'chatbot'
									).replace( '%s', match.title )
								}`.trim(),
							},
						} );
						if ( brief.format ) {
							setStep( 'data' );
						} else if ( data.settings.ask_questions ) {
							setStep( 'questions' );
						} else {
							setStep( 'brief' );
						}
					} }
					onContinue={ afterBrief }
				/>
			) }
			{ 'data' === step && (
				<DataStep
					brief={ brief }
					setBrief={ setBrief }
					busy={ busy }
					onBack={ () => setStep( 'brief' ) }
					onNext={ questionsOrStart }
				/>
			) }
			{ 'questions' === step && (
				<QuestionsStep
					brief={ brief }
					setBrief={ setBrief }
					busy={ busy }
					onBack={ () => setStep( 'brief' ) }
					onNext={ () => start() }
				/>
			) }
			{ 'job' === step && jobId > 0 && (
				<div className="qcwr-card">
					<h2>{ __( 'Writing', 'chatbot' ) }</h2>
					<p className="qcwr-muted">
						{ __(
							'You can leave this page: the work continues, and you can come back to it from the Write tab.',
							'chatbot'
						) }
					</p>
					<JobProgress
						key={ `job-${ jobId }-${ view && view.outline ? 'o' : 'n' }` }
						jobId={ jobId }
						onChange={ onJobChange }
					/>
				</div>
			) }
			{ 'outline' === step && view && view.outline && (
				<OutlineStep
					jobId={ jobId }
					initial={ view.outline }
					onApproved={ ( next ) => {
						setView( next );
						setStep( 'job' );
					} }
				/>
			) }
			{ 'done' === step && view && view.result && (
				<DoneStep
					result={ view.result }
					answer={ 'answer' === brief.mode || !! view.result.answer }
					onNew={ () => {
						setBrief( emptyBrief() );
						setJobId( 0 );
						setView( null );
						setStep( 'brief' );
					} }
				/>
			) }
			{ ! view &&
				! [ 'brief', 'questions', 'data' ].includes( step ) &&
				! error && <Spinner /> }
		</div>
	);
};

export default WriteFlow;
