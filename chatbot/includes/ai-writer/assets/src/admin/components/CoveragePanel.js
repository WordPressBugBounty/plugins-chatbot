/**
 * Coverage view inside Topic Finder (brief 7.10, M13): the shared topic map
 * as a simple list. Each topic shows its coverage, pages, missing
 * subtopics and a "Write next" button. Extensions add actions above the
 * list (slot qcld_wpbot_writer.coverage.actions) and details per topic
 * (slot qcld_wpbot_writer.coverage.topic).
 */
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import { SourceBadge } from './write/SearchData';
import { ProSpots } from './pro/ProPreview';

const TRENDS = () => ( {
	rising: __( 'Rising', 'chatbot' ),
	steady: __( 'Steady', 'chatbot' ),
	falling: __( 'Falling', 'chatbot' ),
} );

const LEVELS = () => ( {
	low: __( 'Low', 'chatbot' ),
	medium: __( 'Medium', 'chatbot' ),
	high: __( 'High', 'chatbot' ),
} );

export const COVERAGE = () => ( {
	covered: __( 'Covered', 'chatbot' ),
	thin: __( 'Thin', 'chatbot' ),
	planned: __( 'Planned', 'chatbot' ),
	missing: __( 'Missing', 'chatbot' ),
	'': __( 'Not checked', 'chatbot' ),
} );

const PAGE_STATUS = () => ( {
	publish: __( 'Live', 'chatbot' ),
	draft: __( 'Draft', 'chatbot' ),
	future: __( 'Scheduled', 'chatbot' ),
	pending: __( 'Pending review', 'chatbot' ),
	planned: __( 'Planned', 'chatbot' ),
} );

const Badge = ( { coverage } ) => (
	<span className={ `qcwr-coverage qcwr-coverage--${ coverage || 'none' }` }>
		{ COVERAGE()[ coverage ] || coverage }
	</span>
);

const TopicRow = ( { topic, onError, reload } ) => {
	const [ open, setOpen ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const writeNext = () => {
		setBusy( true );
		writer( `topics/coverage/${ topic.next.topic_id }/write`, {
			method: 'POST',
		} )
			.then( ( result ) => {
				window.location.href = result.url;
			} )
			.catch( ( err ) => {
				onError( errorMessage( err ) );
				setBusy( false );
			} );
	};
	return (
		<li className="qcwr-coverage-topic" data-topic={ topic.id }>
			<div className="qcwr-coverage-topic__head">
				<button
					type="button"
					className="qcwr-coverage-topic__name"
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
				>
					{ topic.name }
				</button>
				<Badge coverage={ topic.coverage } />
				<span className="qcwr-muted">
					{ sprintf(
						/* translators: 1: live pages, 2: planned pages. */
						__( '%1$d live · %2$d planned', 'chatbot' ),
						topic.live,
						topic.planned
					) }
					{ null !== topic.volume && undefined !== topic.volume
						? ' · ' +
							sprintf(
								/* translators: %s: monthly searches. */
								__( '%s searches a month', 'chatbot' ),
								Number( topic.volume ).toLocaleString()
							)
						: '' }
				</span>
				{ topic.numbers && (
					<span className="qcwr-coverage__numbers">
						<SourceBadge source={ topic.numbers.source } />
						{ topic.numbers.trend_direction && (
							<span
								className={ `qcwr-badge qcwr-badge--${ topic.numbers.trend_direction }` }
							>
								{ TRENDS()[ topic.numbers.trend_direction ] }
							</span>
						) }
						{ topic.numbers.competition && (
							<span className="qcwr-badge">
								{ sprintf(
									/* translators: %s: low, medium or high. */
									__( 'Competition: %s', 'chatbot' ),
									LEVELS()[ topic.numbers.competition ]
								) }
							</span>
						) }
					</span>
				) }
				{ topic.next && data.can.use && (
					<Button
						variant="secondary"
						size="small"
						isBusy={ busy }
						disabled={ busy }
						onClick={ writeNext }
					>
						{ sprintf(
							/* translators: %s: topic to write about. */
							__( 'Write next: %s', 'chatbot' ),
							topic.next.name
						) }
					</Button>
				) }
			</div>
			{ topic.missing.length > 0 && (
				<p className="qcwr-coverage-topic__missing">
					{ _n(
						'Missing subtopic:',
						'Missing subtopics:',
						topic.missing.length,
						'chatbot'
					) }{ ' ' }
					{ topic.missing.join( ', ' ) }
				</p>
			) }
			{ applyFilters( 'qcld_wpbot_writer.coverage.topic', null, {
				topic,
				reload,
				onError,
			} ) }
			{ open && (
				<div className="qcwr-coverage-topic__body">
					{ topic.pages.length ? (
						<ul className="qcwr-coverage-pages">
							{ topic.pages.map( ( page ) => (
								<li key={ `${ page.post_id }-${ page.query }` }>
									{ page.url ? (
										<a href={ page.url }>{ page.title }</a>
									) : (
										page.title
									) }{ ' ' }
									<span className="qcwr-muted">
										{ PAGE_STATUS()[ page.status ] ||
											page.status }
										{ page.pillar
											? ' · ' +
												__(
													'Main guide',
													'chatbot'
												)
											: '' }
									</span>
								</li>
							) ) }
						</ul>
					) : (
						<p className="qcwr-muted">
							{ __(
								'No pages for this topic yet.',
								'chatbot'
							) }
						</p>
					) }
					{ topic.subtopics.length > 0 && (
						<ul className="qcwr-coverage-subtopics">
							{ topic.subtopics.map( ( sub ) => (
								<li key={ sub.id }>
									{ sub.name }{ ' ' }
									<Badge coverage={ sub.coverage } />
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</li>
	);
};

const CoveragePanel = () => {
	const [ view, setView ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ filter, setFilter ] = useState( '' );

	const load = useCallback(
		() =>
			writer( 'topics/coverage' )
				.then( setView )
				.catch( ( err ) => setError( errorMessage( err ) ) ),
		[]
	);
	useEffect( () => {
		load();
	}, [ load ] );

	if ( ! view ) {
		return (
			<section className="qcwr-card qcwr-coverage-panel">
				<h2>{ __( 'Coverage', 'chatbot' ) }</h2>
				{ error ? (
					<p className="qcwr-error">{ error }</p>
				) : (
					<Spinner />
				) }
			</section>
		);
	}
	if ( ! view.available ) {
		return null;
	}
	const topics = view.topics.filter(
		( topic ) => ! filter || topic.coverage === filter
	);
	return (
		<section className="qcwr-card qcwr-coverage-panel">
			<div className="qcwr-topics__head">
				<h2>{ __( 'Coverage', 'chatbot' ) }</h2>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Show', 'chatbot' ) }
					hideLabelFromVision
					value={ filter }
					onChange={ setFilter }
					options={ [
						{
							value: '',
							label: __( 'All topics', 'chatbot' ),
						},
						...[ 'missing', 'thin', 'planned', 'covered' ].map(
							( key ) => ( {
								value: key,
								label: COVERAGE()[ key ],
							} )
						),
					] }
				/>
			</div>
			<p className="qcwr-muted">
				{ __(
					'Which topics your site covers well, which are thin and what to write next.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<ProSpots spots={ [ 'coverage.group_ai', 'coverage.clusters' ] } />
			{ applyFilters( 'qcld_wpbot_writer.coverage.actions', null, {
				view,
				reload: load,
				onError: setError,
			} ) }
			{ topics.length ? (
				<ul className="qcwr-coverage-list">
					{ topics.map( ( topic ) => (
						<TopicRow
							key={ topic.id }
							topic={ topic }
							onError={ setError }
							reload={ load }
						/>
					) ) }
				</ul>
			) : (
				<p className="qcwr-muted">
					{ view.topics.length
						? __(
								'No topics with this coverage.',
								'chatbot'
							)
						: __(
								'No topics yet. Finished posts and saved ideas add their topics here.',
								'chatbot'
							) }
				</p>
			) }
		</section>
	);
};

export default CoveragePanel;
