/**
 * Topic Finder tab: the Sources strip (Connected, Not connected, Not
 * available, Failing, each with a fix), then the ideas: one list per source,
 * or one ranked list when an extension provides it. Every idea shows its
 * sources, trend, relevance, whether the site covers it and why, with
 * Write, Add to calendar and Dismiss. The Coverage view (shared topic map)
 * sits under the Sources strip.
 */
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import CoveragePanel from '../components/CoveragePanel';
import JobProgress from '../components/JobProgress';
import { SourceBadge, monthly } from '../components/write/SearchData';
import { ProSpots } from '../components/pro/ProPreview';

const STATUS = () => ( {
	connected: __( 'Connected', 'chatbot' ),
	not_connected: __( 'Not connected', 'chatbot' ),
	not_available: __( 'Not available', 'chatbot' ),
	failing: __( 'Not working', 'chatbot' ),
} );

const TREND = () => ( {
	rising: __( 'Rising', 'chatbot' ),
	steady: __( 'Steady', 'chatbot' ),
	seasonal: __( 'Seasonal', 'chatbot' ),
	falling: __( 'Falling', 'chatbot' ),
} );

const LEVEL = () => ( {
	low: __( 'Low', 'chatbot' ),
	medium: __( 'Medium', 'chatbot' ),
	high: __( 'High', 'chatbot' ),
} );

// Source names for SEO & AI Search sources when they are not registered here.
const SEO_SOURCES = () => ( {
	seo: __( 'Keyword Vault', 'chatbot' ),
	seo_paid_searches: __( 'Paid searches with no page', 'chatbot' ),
	seo_topic_gaps: __( 'Topic Map gaps', 'chatbot' ),
} );

/**
 * Monthly searches of an idea, or null when unknown (never 0 for unknown).
 *
 * @param {Object} idea Idea.
 * @return {number|null} Searches.
 */
const volumeOf = ( idea ) => {
	const demand = idea.demand || {};
	if ( null !== demand.volume && undefined !== demand.volume ) {
		return Number( demand.volume );
	}
	if ( Array.isArray( demand.volume_range ) ) {
		return Number( demand.volume_range[ 0 ] );
	}
	return null !== idea.volume && undefined !== idea.volume
		? Number( idea.volume )
		: null;
};

/**
 * Sort by monthly searches: known volumes first (high to low); ideas
 * without numbers keep their usual order after them.
 *
 * @param {Array} ideas Ideas.
 * @return {Array} Sorted copy.
 */
const byVolume = ( ideas ) =>
	ideas
		.map( ( idea, index ) => ( { idea, index, volume: volumeOf( idea ) } ) )
		.sort( ( a, b ) => {
			if ( null === a.volume && null === b.volume ) {
				return a.index - b.index;
			}
			if ( null === a.volume ) {
				return 1;
			}
			if ( null === b.volume ) {
				return -1;
			}
			return b.volume - a.volume || a.index - b.index;
		} )
		.map( ( item ) => item.idea );

/**
 * Search numbers of an idea, each with its source (CR-6).
 *
 * @param {Object} props      Props.
 * @param {Object} props.idea Idea.
 * @return {Element|null} Badges.
 */
const Demand = ( { idea } ) => {
	const demand = idea.demand || {};
	const volume = monthly( demand ) || monthly( { volume: idea.volume } );
	return (
		<>
			{ volume && (
				<span className="qcwr-badge qcwr-badge--volume">
					{ volume }
				</span>
			) }
			{ volume && <SourceBadge source={ demand.source } /> }
			{ ! volume && demand.estimate && (
				<span className="qcwr-badge">
					{ sprintf(
						/* translators: %s: high, medium or low. */
						__( 'Demand: %s (AI estimate)', 'chatbot' ),
						LEVEL()[ demand.estimate ] || demand.estimate
					) }
				</span>
			) }
			{ demand.trend_direction &&
				demand.trend_direction !== idea.trend && (
					<span
						className={ `qcwr-badge qcwr-badge--${ demand.trend_direction }` }
					>
						{ TREND()[ demand.trend_direction ] }
					</span>
				) }
			{ demand.competition && (
				<span className="qcwr-badge">
					{ sprintf(
						/* translators: %s: low, medium or high. */
						__( 'Competition: %s', 'chatbot' ),
						LEVEL()[ demand.competition ] || demand.competition
					) }
				</span>
			) }
		</>
	);
};

const fixHref = ( fix ) =>
	fix.url ||
	`${ data.adminUrl }admin.php?page=wpbot-ai-writer&tab=${
		fix.tab || 'settings'
	}${ 'settings' === fix.tab ? '#qcwr-connections' : '' }`;

/**
 * One source in the strip.
 *
 * @param {Object} props        Props.
 * @param {Object} props.source Source state.
 * @return {Element} Chip.
 */
const SourceChip = ( { source } ) => (
	<li className={ `qcwr-source qcwr-source--${ source.status }` }>
		<strong>{ source.label }</strong>
		<span className="qcwr-source__status">
			{ STATUS()[ source.status ] }
		</span>
		{ source.message &&
			'connected' !== source.status &&
			// The Search Console card below says it in full.
			! (
				'search_console' === source.id &&
				'not_connected' === source.status
			) && (
				<span className="qcwr-source__message">{ source.message }</span>
			) }
		{ source.fix && data.can.settings && (
			<Button variant="link" href={ fixHref( source.fix ) }>
				{ source.fix.label }
			</Button>
		) }
	</li>
);

/**
 * One idea with its actions.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.idea    Idea.
 * @param {Object}   props.labels  Source id => label.
 * @param {Function} props.onGone  Called when the idea leaves the list.
 * @param {Function} props.onError Error callback.
 * @return {Element} Row.
 */
const IdeaRow = ( { idea, labels, onGone, onError } ) => {
	const [ date, setDate ] = useState( '' );
	const [ picking, setPicking ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ done, setDone ] = useState( '' );

	const act = ( action, body = {} ) => {
		setBusy( action );
		return writer( `topics/${ idea.id }/${ action }`, {
			method: 'POST',
			// A merged topic (combined ranking) acts on every idea in it.
			data: { ...body, also: idea.merged_ids || [] },
		} )
			.catch( ( err ) => {
				onError( errorMessage( err ) );
				throw err;
			} )
			.finally( () => setBusy( '' ) );
	};

	const sources = ( idea.sources || [ idea.source ] )
		.map( ( id ) => labels[ id ] || SEO_SOURCES()[ id ] || id )
		.join( ' + ' );

	return (
		<li className="qcwr-idea" data-idea={ idea.id }>
			<div className="qcwr-idea__main">
				<strong className="qcwr-idea__title">{ idea.title }</strong>
				<div className="qcwr-idea__meta">
					<span className="qcwr-badge">{ sources }</span>
					<Demand idea={ idea } />
					{ idea.trend && (
						<span
							className={ `qcwr-badge qcwr-badge--${ idea.trend }` }
						>
							{ TREND()[ idea.trend ] || idea.trend }
						</span>
					) }
					{ idea.relevance > 0 && (
						<span className="qcwr-badge">
							{ sprintf(
								/* translators: %d: relevance 0-100. */
								__( 'Relevance %d%%', 'chatbot' ),
								idea.relevance
							) }
						</span>
					) }
					{ idea.session_count > 0 && (
						<span className="qcwr-badge">
							{ sprintf(
								/* translators: %d: number of chats. */
								__( '%d chats', 'chatbot' ),
								idea.session_count
							) }
						</span>
					) }
					{ idea.format && (
						<span
							className="qcwr-badge qcwr-badge--format"
							title={ idea.format.reason }
						>
							{ sprintf(
								/* translators: %s: article format. */
								__( 'Format: %s', 'chatbot' ),
								(
									( data.formats || [] ).find(
										( format ) =>
											format.value === idea.format.id
									) || { label: idea.format.id }
								).label
							) }
						</span>
					) }
					{ idea.topic && (
						<span className="qcwr-badge qcwr-badge--topic">
							{ idea.topic }
						</span>
					) }
					{ idea.planned_by && (
						<span className="qcwr-idea__covered">
							{ __( 'Planned:', 'chatbot' ) }{ ' ' }
							{ idea.planned_by }
						</span>
					) }
					{ idea.covered ? (
						<span className="qcwr-idea__covered">
							{ __( 'Covered by:', 'chatbot' ) }{ ' ' }
							<a href={ idea.covered.url }>
								{ idea.covered.title }
							</a>
						</span>
					) : (
						<span className="qcwr-idea__gap">
							{ __( 'Not covered yet', 'chatbot' ) }
						</span>
					) }
				</div>
				{ idea.reason && (
					<p className="qcwr-idea__reason">{ idea.reason }</p>
				) }
				{ idea.questions && idea.questions.length > 0 && (
					<ul className="qcwr-idea__questions">
						{ idea.questions.map( ( question ) => (
							<li key={ question }>{ question }</li>
						) ) }
					</ul>
				) }
				{ idea.link && (
					<a
						className="qcwr-idea__link"
						href={ idea.link }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open the source', 'chatbot' ) }
					</a>
				) }
				{ applyFilters(
					'qcld_wpbot_writer.topics.columns',
					null,
					idea
				) }
			</div>
			{ data.can.use && (
				<div className="qcwr-idea__actions">
					{ done && <span className="qcwr-muted">{ done }</span> }
					<Button
						variant="primary"
						isBusy={ 'write' === busy }
						disabled={ !! busy }
						onClick={ () =>
							act( 'write' )
								.then( ( result ) => {
									window.location.href = result.url;
								} )
								.catch( () => {} )
						}
					>
						{ __( 'Write', 'chatbot' ) }
					</Button>
					{ picking ? (
						<span className="qcwr-idea__date">
							<input
								type="date"
								aria-label={ __(
									'Day for this idea',
									'chatbot'
								) }
								min={ data.calendar && data.calendar.today }
								value={ date }
								onChange={ ( event ) =>
									setDate( event.target.value )
								}
							/>
							<Button
								variant="secondary"
								disabled={ ! date || !! busy }
								isBusy={ 'calendar' === busy }
								onClick={ () =>
									act( 'calendar', { date } )
										.then( () => {
											setDone(
												__(
													'Added to the calendar.',
													'chatbot'
												)
											);
											onGone( idea.id );
										} )
										.catch( () => {} )
								}
							>
								{ __( 'Add', 'chatbot' ) }
							</Button>
						</span>
					) : (
						<Button
							variant="secondary"
							disabled={ !! busy }
							onClick={ () => setPicking( true ) }
						>
							{ __( 'Add to calendar', 'chatbot' ) }
						</Button>
					) }
					<Button
						variant="tertiary"
						isDestructive
						isBusy={ 'dismiss' === busy }
						disabled={ !! busy }
						onClick={ () =>
							act( 'dismiss' )
								.then( () => onGone( idea.id ) )
								.catch( () => {} )
						}
					>
						{ __( 'Dismiss', 'chatbot' ) }
					</Button>
				</div>
			) }
		</li>
	);
};

const TopicsScreen = () => {
	const [ view, setView ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ sort, setSort ] = useState( 'best' );

	const load = useCallback(
		() =>
			writer( 'topics' )
				.then( ( next ) => {
					setView( next );
					setJobId( next.job_id || 0 );
				} )
				.catch( ( err ) => setError( errorMessage( err ) ) ),
		[]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	const refresh = () => {
		setBusy( true );
		setError( '' );
		writer( 'topics/refresh', { method: 'POST', data: {} } )
			.then( ( result ) => setJobId( result.job_id ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	const onJob = useCallback(
		( job ) => {
			if ( job && ! [ 'queued', 'running' ].includes( job.status ) ) {
				setJobId( 0 );
				load();
			}
		},
		[ load ]
	);

	const remove = ( id ) =>
		setView( ( current ) => ( {
			...current,
			combined: current.combined
				? current.combined.filter( ( idea ) => idea.id !== id )
				: null,
			lists: Object.fromEntries(
				Object.entries( current.lists ).map( ( [ key, list ] ) => [
					key,
					list.filter( ( idea ) => idea.id !== id ),
				] )
			),
		} ) );

	if ( ! view ) {
		return error ? <p className="qcwr-error">{ error }</p> : <Spinner />;
	}

	const labels = Object.fromEntries(
		view.sources.map( ( source ) => [ source.id, source.label ] )
	);
	const failing = view.sources.filter(
		( source ) => 'failing' === source.status
	);
	const gsc = view.sources.find(
		( source ) => 'search_console' === source.id
	);
	const sorted = ( ideas ) =>
		'volume' === sort ? byVolume( ideas || [] ) : ideas || [];
	const anyVolume = [
		...( view.combined || [] ),
		...Object.values( view.lists ).flat(),
	].some( ( idea ) => null !== volumeOf( idea ) );
	const row = ( idea ) => (
		<IdeaRow
			key={ idea.id }
			idea={ idea }
			labels={ labels }
			onGone={ remove }
			onError={ setError }
		/>
	);

	return (
		<div className="qcwr-topics">
			<div className="qcwr-card">
				<div className="qcwr-topics__head">
					<h2>{ __( 'Topic Finder', 'chatbot' ) }</h2>
					{ data.can.use && (
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || !! jobId }
							onClick={ refresh }
						>
							{ __( 'Find new topics', 'chatbot' ) }
						</Button>
					) }
				</div>
				<p className="qcwr-muted">
					{ view.refreshed
						? sprintf(
								/* translators: %s: date and time. */
								__( 'Last checked: %s', 'chatbot' ),
								new Date( view.refreshed ).toLocaleString()
							)
						: __(
								'Not checked yet. Click "Find new topics".',
								'chatbot'
							) }
				</p>
				<ul
					className="qcwr-sources"
					aria-label={ __( 'Sources', 'chatbot' ) }
				>
					{ view.sources.map( ( source ) => (
						<SourceChip key={ source.id } source={ source } />
					) ) }
				</ul>
				<ProSpots
					spots={ [
						'topics.chats',
						'topics.youtube',
						'topics.wikipedia',
						'topics.web',
						'topics.competitors',
					] }
					title={ __(
						'More sources with WPBot Pro:',
						'chatbot'
					) }
				/>
				{ failing.length > 0 && (
					<div className="qcwr-key-notice" role="alert">
						{ failing.map( ( source ) => (
							<div
								key={ source.id }
								className="qcwr-key-notice__item"
							>
								<strong className="qcwr-key-notice__cause">
									{ sprintf(
										/* translators: %s: source name. */
										__(
											'%s is not working.',
											'chatbot'
										),
										source.label
									) }
								</strong>
								<span className="qcwr-key-notice__fix">
									{ source.message }
								</span>
								{ source.fix && data.can.settings && (
									<a
										className="qcwr-key-notice__link"
										href={ fixHref( source.fix ) }
									>
										{ source.fix.label }
									</a>
								) }
							</div>
						) ) }
					</div>
				) }
				{ gsc && 'not_connected' === gsc.status && (
					<div className="qcwr-connect-card">
						<p>{ gsc.message }</p>
						{ data.can.keys && gsc.fix && (
							<Button
								variant="secondary"
								href={ fixHref( gsc.fix ) }
							>
								{ gsc.fix.label }
							</Button>
						) }
					</div>
				) }
				{ error && (
					<Notice status="error" onRemove={ () => setError( '' ) }>
						{ error }
					</Notice>
				) }
				{ jobId > 0 && (
					<JobProgress jobId={ jobId } onChange={ onJob } />
				) }
			</div>

			<CoveragePanel />

			{ applyFilters( 'qcld_wpbot_writer.topics.panels', null, {
				view,
				reload: load,
			} ) }

			{ anyVolume && (
				<div className="qcwr-topics__sort">
					<SelectControl
						label={ __( 'Sort ideas', 'chatbot' ) }
						value={ sort }
						options={ [
							{
								value: 'best',
								label: __( 'Best first', 'chatbot' ),
							},
							{
								value: 'volume',
								label: __(
									'Most searched first',
									'chatbot'
								),
							},
						] }
						onChange={ setSort }
						__nextHasNoMarginBottom
					/>
				</div>
			) }
			{ view.combined ? (
				<section className="qcwr-card">
					<h2>{ __( 'Ranked topics', 'chatbot' ) }</h2>
					{ view.combined.length ? (
						<ul className="qcwr-ideas">
							{ sorted( view.combined ).map( row ) }
						</ul>
					) : (
						<p className="qcwr-muted">
							{ __( 'No ideas yet.', 'chatbot' ) }
						</p>
					) }
				</section>
			) : (
				view.sources
					.filter(
						( source ) =>
							'not_available' !== source.status ||
							( view.lists[ source.id ] || [] ).length
					)
					.map( ( source ) => (
						<section
							key={ source.id }
							className="qcwr-card qcwr-topic-list"
							data-source={ source.id }
						>
							<h2>{ source.label }</h2>
							<p className="qcwr-muted">{ source.description }</p>
							{ ( view.lists[ source.id ] || [] ).length ? (
								<ul className="qcwr-ideas">
									{ sorted( view.lists[ source.id ] ).map(
										row
									) }
								</ul>
							) : (
								<p className="qcwr-muted">
									{ source.last_run && source.last_run.error
										? source.last_run.error
										: __(
												'No ideas from this source yet.',
												'chatbot'
											) }
								</p>
							) }
						</section>
					) )
			) }
		</div>
	);
};

export default TopicsScreen;
