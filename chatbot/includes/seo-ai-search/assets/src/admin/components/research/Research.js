/**
 * Keywords & Topics > Research (S14): Keywords, Niche and Competitor gap.
 * Works with no connections; a keyword data provider adds real numbers.
 * "Run research" is always there with the estimated cost under it (updated
 * as the form changes); a run above the owner's limit is confirmed first.
 * Runs are reused for 30 days unless refreshed, and stay in the history.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo } from '../../api';
import { AiReason, aiBlocked, useAiReady } from '../../ai';
import JobProgress from '../JobProgress';
import { CompetitorSuggest } from '../CompetitorPicker';
import { AiLabel, INTENTS, PAGE_TYPES, RealFact, STATUSES } from './shared';

const KINDS = {
	keywords: __( 'Keywords', 'chatbot' ),
	niche: __( 'Niche', 'chatbot' ),
	gap: __( 'Competitor gap', 'chatbot' ),
};

const money = ( value ) => {
	const n = Number( value || 0 );
	return n > 0 && n < 0.01
		? __( 'less than $0.01', 'chatbot' )
		: `$${ n.toFixed( 2 ) }`;
};

/**
 * Keyword table with selection, facts and "your page".
 *
 * @param {Object}                       props        Props.
 * @param {Object[]}                     props.items  Items.
 * @param {Object}                       props.picked keyword => true.
 * @param {(...args: unknown[]) => void} props.onPick ( keyword, on ).
 * @param {boolean}                      props.gap    Gap columns.
 * @return {Element} Table.
 */
const KeywordTable = ( { items, picked, onPick, gap } ) => {
	const hasReal = items.some(
		( item ) => item.real && Object.keys( item.real ).length > 0
	);
	if ( ! items.length ) {
		return (
			<p className="qcseo-muted">
				{ __( 'Nothing in this list.', 'chatbot' ) }
			</p>
		);
	}
	return (
		<table className="widefat striped qcseo-table qcseo-research-table">
			<thead>
				<tr>
					<th className="check-column">
						<input
							type="checkbox"
							aria-label={ __(
								'Select all',
								'chatbot'
							) }
							checked={ items.every(
								( item ) => picked[ item.keyword ]
							) }
							onChange={ ( event ) => {
								const on = event.target.checked;
								items.forEach( ( item ) =>
									onPick( item.keyword, on )
								);
							} }
						/>
					</th>
					<th>{ __( 'Keyword', 'chatbot' ) }</th>
					<th>{ __( 'Intent', 'chatbot' ) }</th>
					<th>{ __( 'Demand', 'chatbot' ) }</th>
					{ ! gap && (
						<th>{ __( 'Difficulty', 'chatbot' ) }</th>
					) }
					{ gap && (
						<th>{ __( 'Page to build', 'chatbot' ) }</th>
					) }
					{ hasReal && (
						<th>{ __( 'Real data', 'chatbot' ) }</th>
					) }
					<th>{ __( 'Your page', 'chatbot' ) }</th>
					<th>{ __( 'Why', 'chatbot' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ items.map( ( item ) => (
					<tr key={ item.keyword }>
						<td>
							<input
								type="checkbox"
								aria-label={ item.keyword }
								checked={ !! picked[ item.keyword ] }
								onChange={ ( event ) => {
									const on = event.target.checked;
									onPick( item.keyword, on );
								} }
							/>
						</td>
						<td>
							<strong>{ item.keyword }</strong>
							{ item.vault && (
								<span
									className={ `qcseo-status is-${ item.vault.status }` }
								>
									{ STATUSES[ item.vault.status ] }
								</span>
							) }
							{ gap &&
								item.domains &&
								item.domains.length > 0 && (
									<small className="qcseo-muted">
										{ ' ' }
										{ item.domains.join( ', ' ) }
									</small>
								) }
						</td>
						<td>{ INTENTS[ item.intent ] || '—' }</td>
						<td>
							<AiLabel
								level={ item.demand }
								reason={ item.reason || item.why }
							/>
						</td>
						{ ! gap && (
							<td>
								<AiLabel level={ item.difficulty } />
							</td>
						) }
						{ gap && (
							<td>{ PAGE_TYPES[ item.page_type ] || '—' }</td>
						) }
						{ hasReal && (
							<td className="qcseo-real-cell">
								<RealFact
									fact={ item.real && item.real.volume }
									kind="volume"
								/>
								<RealFact
									fact={ item.real && item.real.cpc }
									kind="cpc"
								/>
								<RealFact
									fact={ item.real && item.real.position }
									kind="position"
								/>
								<RealFact
									fact={ item.real && item.real.trend }
									kind="trend"
								/>
							</td>
						) }
						<td>
							{ item.page ? (
								<a
									href={ item.page.url }
									target="_blank"
									rel="noreferrer"
								>
									{ item.page.title }
								</a>
							) : (
								'—'
							) }
						</td>
						<td className="qcseo-muted">
							{ item.reason || item.why || '' }
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
};

/**
 * Save to Vault, Add to Topic Finder, Write a page for selected keywords.
 *
 * @param {Object}                       props         Props.
 * @param {Object[]}                     props.items   Selected items.
 * @param {Object}                       props.run     Run.
 * @param {Object}                       props.status  Research status.
 * @param {(...args: unknown[]) => void} props.onSaved After saving.
 * @return {Element} Actions.
 */
const Actions = ( { items, run, status, onSaved } ) => {
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const source = { keywords: 'research', niche: 'niche', gap: 'gap' }[
		run.kind
	];
	const save = () => {
		setBusy( 'save' );
		seo( 'research/save', {
			method: 'POST',
			data: {
				items: items.map( ( item ) => ( {
					...item,
					competitor_notes:
						'gap' === run.kind
							? [ item.why, ( item.domains || [] ).join( ', ' ) ]
									.filter( Boolean )
									.join( ' — ' )
							: '',
				} ) ),
				source,
				language: run.language,
				country: run.country,
			},
		} )
			.then( ( r ) => {
				setMessage( {
					status: 'success',
					text: sprintf(
						/* translators: %d: keywords saved. */
						__(
							'%d keywords saved to the Vault.',
							'chatbot'
						),
						r.saved.length
					),
				} );
				if ( onSaved ) {
					onSaved();
				}
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( '' ) );
	};
	const ideas = () => {
		setBusy( 'ideas' );
		seo( 'research/ideas', {
			method: 'POST',
			data: { keywords: items.map( ( item ) => item.keyword ) },
		} )
			.then( ( r ) =>
				setMessage( {
					status: 'success',
					text: sprintf(
						/* translators: %d: ideas added. */
						__(
							'%d ideas added to AI Writer’s Topic Finder.',
							'chatbot'
						),
						r.added
					),
				} )
			)
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( '' ) );
	};
	const write = () => {
		const [ first, ...rest ] = items;
		setBusy( 'write' );
		seo( 'research/brief', {
			method: 'POST',
			data: {
				keyword: first.keyword,
				intent: first.intent,
				supporting: rest.map( ( item ) => item.keyword ).slice( 0, 10 ),
				questions: rest
					.filter( ( item ) =>
						/^(who|what|when|where|why|how|can|does|is|are|should|which)\b/i.test(
							item.keyword
						)
					)
					.map( ( item ) => item.keyword ),
				competitor_notes: first.why || '',
				reason: first.reason || '',
			},
		} )
			.then( ( r ) => {
				window.location.href = r.url;
			} )
			.catch( ( e ) => {
				setMessage( { status: 'error', text: errorMessage( e ) } );
				setBusy( '' );
			} );
	};
	return (
		<div className="qcseo-research-actions">
			<span className="qcseo-muted">
				{ sprintf(
					/* translators: %d: selected keywords. */
					__( '%d selected', 'chatbot' ),
					items.length
				) }
			</span>
			<Button
				variant="primary"
				disabled={ ! items.length || !! busy || ! status.vault }
				isBusy={ 'save' === busy }
				onClick={ save }
			>
				{ __( 'Save to Vault', 'chatbot' ) }
			</Button>
			{ status.ideas && (
				<Button
					variant="secondary"
					disabled={ ! items.length || !! busy }
					isBusy={ 'ideas' === busy }
					onClick={ ideas }
				>
					{ __( 'Add to Topic Finder', 'chatbot' ) }
				</Button>
			) }
			{ status.writer && (
				<Button
					variant="secondary"
					disabled={ ! items.length || !! busy }
					isBusy={ 'write' === busy }
					onClick={ write }
					title={ __(
						'Opens AI Writer with the first selected keyword as the main search and the others as supporting searches.',
						'chatbot'
					) }
				>
					{ __( 'Write a page', 'chatbot' ) }
				</Button>
			) }
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
		</div>
	);
};

const Citations = ( { list } ) =>
	list && list.length > 0 ? (
		<div className="qcseo-citations">
			<h4>{ __( 'Sources the AI read', 'chatbot' ) }</h4>
			<ul>
				{ list.map( ( c ) => (
					<li key={ c.url }>
						<a href={ c.url } target="_blank" rel="noreferrer">
							{ c.title || c.url }
						</a>
					</li>
				) ) }
			</ul>
		</div>
	) : null;

/**
 * A finished run.
 *
 * @param {Object}                       props        Props.
 * @param {Object}                       props.run    Run.
 * @param {Object}                       props.status Research status.
 * @param {(...args: unknown[]) => void} props.reload Reload the run.
 * @return {Element} Result.
 */
const Result = ( { run, status, reload } ) => {
	const [ list, setList ] = useState(
		'gap' === run.kind ? 'gaps' : 'related'
	);
	const [ picked, setPicked ] = useState( {} );
	const r = run.result || {};
	const onPick = ( keyword, on ) =>
		setPicked( ( current ) => ( { ...current, [ keyword ]: on } ) );

	let items = [];
	if ( 'keywords' === run.kind ) {
		if ( 'clusters' === list ) {
			const all = [
				...( r.related || [] ),
				...( r.long_tail || [] ),
				...( r.questions || [] ),
			];
			items = all;
		} else {
			items = r[ list ] || [];
		}
	} else if ( 'gap' === run.kind ) {
		items = r.gaps || [];
	} else {
		items = ( r.first_cluster || [] ).map( ( row ) => ( {
			...row,
			reason: row.title,
		} ) );
	}
	const selected = items.filter( ( item ) => picked[ item.keyword ] );
	const clusterOf = {};
	( r.clusters || [] ).forEach( ( c ) =>
		( c.keywords || [] ).forEach( ( k ) => {
			clusterOf[ k ] = c.name;
		} )
	);
	const withCluster = selected.map( ( item ) => ( {
		...item,
		cluster: clusterOf[ item.keyword ] || '',
	} ) );

	return (
		<div className="qcseo-card qcseo-research-result">
			<div className="qcseo-head">
				<h3>
					{ sprintf(
						/* translators: 1: research kind, 2: seed. */
						__( '%1$s: %2$s', 'chatbot' ),
						KINDS[ run.kind ],
						run.seed
					) }
				</h3>
				<span className="qcseo-muted">
					{ run.from_cache
						? sprintf(
								/* translators: %s: date. */
								__(
									'Saved result from %s (no cost). Refresh to run again.',
									'chatbot'
								),
								run.created_at
							)
						: sprintf(
								/* translators: 1: date, 2: cost. */
								__(
									'Run on %1$s, cost %2$s',
									'chatbot'
								),
								run.created_at,
								money( run.cost )
							) }
				</span>
			</div>
			{ ! ( run.sources || [] ).some( ( s ) =>
				s.startsWith( 'provider' )
			) && (
				<p className="qcseo-muted qcseo-honest">
					{ __(
						'Demand and difficulty are AI estimates (high, medium, low), not measured numbers. Connect a keyword data provider for real volumes and costs per click.',
						'chatbot'
					) }
				</p>
			) }

			{ 'keywords' === run.kind && (
				<div className="qcseo-subtabs" role="tablist">
					{ [
						[ 'related', __( 'Related', 'chatbot' ) ],
						[ 'long_tail', __( 'Long-tail', 'chatbot' ) ],
						[ 'questions', __( 'Questions', 'chatbot' ) ],
						[ 'clusters', __( 'Clusters', 'chatbot' ) ],
					].map( ( [ id, label ] ) => (
						<Button
							key={ id }
							role="tab"
							aria-selected={ list === id }
							variant={ list === id ? 'primary' : 'tertiary' }
							onClick={ () => setList( id ) }
						>
							{ label } ({ ( run.counts || {} )[ id ] || 0 })
						</Button>
					) ) }
				</div>
			) }

			{ 'niche' === run.kind && (
				<div className="qcseo-niche">
					{ ! r.web_search && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Results are better with a model that can search the web.',
								'chatbot'
							) }
						</Notice>
					) }
					<p>{ r.overview }</p>
					<div className="qcseo-columns">
						<div>
							<h4>
								{ __( 'Main subtopics', 'chatbot' ) }
							</h4>
							<ul>
								{ ( r.subtopics || [] ).map( ( s ) => (
									<li key={ s.name }>
										<strong>{ s.name }</strong>
										{ s.why ? ` — ${ s.why }` : '' }
									</li>
								) ) }
							</ul>
						</div>
						<div>
							<h4>
								{ __( 'Content gaps', 'chatbot' ) }
							</h4>
							<ul>
								{ ( r.gaps || [] ).map( ( g ) => (
									<li key={ g.topic }>
										<strong>{ g.topic }</strong>
										{ g.why ? ` — ${ g.why }` : '' }
									</li>
								) ) }
							</ul>
							<h4>
								{ __(
									'How sites in this niche earn money',
									'chatbot'
								) }
							</h4>
							<ul>
								{ ( r.monetisation || [] ).map( ( m ) => (
									<li key={ m }>{ m }</li>
								) ) }
							</ul>
						</div>
					</div>
					<h4>
						{ __(
							'First cluster of pages to build',
							'chatbot'
						) }
					</h4>
				</div>
			) }

			{ 'clusters' === list && 'keywords' === run.kind ? (
				<div className="qcseo-clusters">
					{ ( r.clusters || [] ).map( ( c ) => (
						<div key={ c.name } className="qcseo-cluster">
							<strong>{ c.name }</strong>
							<div className="qcseo-chips">
								{ ( c.keywords || [] ).map( ( k ) => (
									<CheckboxControl
										key={ k }
										label={ k }
										checked={ !! picked[ k ] }
										onChange={ ( on ) => onPick( k, on ) }
										__nextHasNoMarginBottom
									/>
								) ) }
							</div>
						</div>
					) ) }
				</div>
			) : (
				<KeywordTable
					items={ items }
					picked={ picked }
					onPick={ onPick }
					gap={ 'gap' === run.kind }
				/>
			) }

			<Actions
				items={ withCluster }
				run={ run }
				status={ status }
				onSaved={ reload }
			/>

			{ 'gap' === run.kind && (
				<div className="qcseo-columns">
					<div>
						<h4>
							{ __( 'Keywords you share', 'chatbot' ) }
						</h4>
						<p className="qcseo-chips">
							{ ( r.shared || [] ).map( ( s ) => (
								<span key={ s.keyword } className="qcseo-chip">
									{ s.keyword }
								</span>
							) ) }
						</p>
						<h4>{ __( 'Your advantages', 'chatbot' ) }</h4>
						<ul>
							{ ( r.advantages || [] ).map( ( a ) => (
								<li key={ a }>{ a }</li>
							) ) }
						</ul>
					</div>
					<div>
						<h4>{ __( 'Action plan', 'chatbot' ) }</h4>
						<ol>
							{ ( r.plan || [] ).map( ( p ) => (
								<li key={ p }>{ p }</li>
							) ) }
						</ol>
						{ r.pages_read && (
							<p className="qcseo-muted">
								{ __(
									'Read from competitor sitemaps (titles and headings only, robots.txt respected; nothing is kept).',
									'chatbot'
								) }
							</p>
						) }
					</div>
				</div>
			) }
			<Citations list={ r.citations } />
		</div>
	);
};

const Research = () => {
	const ready = useAiReady();
	const aiOff = aiBlocked( ready );
	const [ status, setStatus ] = useState( null );
	const [ kind, setKind ] = useState( 'keywords' );
	const [ form, setForm ] = useState( {
		seed: '',
		post_id: '',
		language: '',
		country: '',
		competitors: '',
	} );
	const [ refresh, setRefresh ] = useState( false );
	const [ estimate, setEstimate ] = useState( null );
	const [ estimating, setEstimating ] = useState( false );
	const [ ask, setAsk ] = useState( null );
	const [ run, setRun ] = useState( null );
	const [ history, setHistory ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ pages, setPages ] = useState( [] );

	const loadHistory = () =>
		seo( 'research/history' )
			.then( setHistory )
			.catch( () => {} );

	useEffect( () => {
		seo( 'research/status' )
			.then( ( s ) => {
				setStatus( s );
				setForm( ( f ) => ( {
					...f,
					language: s.place.language,
					country: s.place.country,
					competitors: ( s.competitors || [] ).join( ', ' ),
				} ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
		seo( 'vault/pages' )
			.then( setPages )
			.catch( () => {} );
		loadHistory();
	}, [] );

	// Live estimate: refreshed shortly after the form stops changing.
	useEffect( () => {
		if ( ! status ) {
			return undefined;
		}
		let live = true;
		setAsk( null );
		// Ask only when the form has what a run needs (as the server checks):
		// competitor domains for a gap, else a page or a seed of 2+ characters.
		const complete =
			'gap' === kind
				? '' !== String( form.competitors || '' ).trim()
				: !! form.post_id ||
					String( form.seed || '' ).trim().length >= 2;
		if ( ! complete ) {
			setEstimate( null );
			setEstimating( false );
			return undefined;
		}
		const timer = setTimeout( () => {
			setEstimating( true );
			seo( 'research/estimate', {
				method: 'POST',
				data: {
					kind,
					seed: form.seed,
					post_id: form.post_id ? Number( form.post_id ) : 0,
					language: form.language,
					country: form.country,
					competitors: form.competitors,
					refresh,
				},
			} )
				.then( ( r ) => live && setEstimate( r ) )
				// Not ready yet (for example no keyword typed): no estimate.
				.catch( () => live && setEstimate( null ) )
				.finally( () => live && setEstimating( false ) );
		}, 600 );
		return () => {
			live = false;
			clearTimeout( timer );
		};
	}, [ kind, form, refresh, status ] );

	if ( ! status ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}

	const set = ( key ) => ( value ) => {
		setEstimate( null );
		setForm( { ...form, [ key ]: value } );
	};
	const input = () => ( {
		kind,
		seed: form.seed,
		post_id: form.post_id ? Number( form.post_id ) : 0,
		language: form.language,
		country: form.country,
		competitors: form.competitors,
		refresh,
	} );
	const open = ( id ) =>
		seo( `research/${ id }` )
			.then( ( r ) => {
				setRun( r );
				setKind( r.kind );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	const go = ( confirmed = false ) => {
		if ( ! confirmed && estimate && estimate.confirm ) {
			setAsk( estimate );
			return;
		}
		setBusy( true );
		setMessage( '' );
		setAsk( null );
		setRun( null );
		seo( 'research/run', {
			method: 'POST',
			data: { ...input(), confirmed },
		} )
			.then( ( r ) => {
				setRun( r );
				loadHistory();
			} )
			.catch( ( e ) => {
				if (
					e &&
					'qcld_wpbot_seo_research_confirm' === e.code &&
					e.data &&
					e.data.estimate
				) {
					setEstimate( e.data.estimate );
					setAsk( e.data.estimate );
					return;
				}
				setMessage( errorMessage( e ) );
			} )
			.finally( () => setBusy( false ) );
	};
	const costLine = ( est ) =>
		[
			est.cached
				? __(
						'A saved result from the last 30 days opens at no cost.',
						'chatbot'
					)
				: sprintf(
						/* translators: %s: AI cost. */
						__(
							'Estimated cost: about %s with your AI provider.',
							'chatbot'
						),
						money( est.ai_cost )
					),
			! est.cached && est.provider_cost > 0
				? sprintf(
						/* translators: 1: cost, 2: unit, 3: provider. */
						__(
							'Plus about %1$s %2$s from %3$s.',
							'chatbot'
						),
						est.provider_cost,
						est.provider_unit,
						est.provider
					)
				: '',
		]
			.filter( Boolean )
			.join( ' ' );

	return (
		<div className="qcseo-research">
			<div className="qcseo-card">
				<div className="qcseo-subtabs" role="tablist">
					{ Object.keys( KINDS ).map( ( id ) => (
						<Button
							key={ id }
							role="tab"
							aria-selected={ kind === id }
							variant={ kind === id ? 'primary' : 'tertiary' }
							onClick={ () => {
								setKind( id );
								setEstimate( null );
							} }
						>
							{ KINDS[ id ] }
						</Button>
					) ) }
				</div>
				<p className="qcseo-muted">
					{ 'keywords' === kind &&
						__(
							'Related keywords, long-tail searches, questions and topic clusters for a seed word or one of your pages.',
							'chatbot'
						) }
					{ 'niche' === kind &&
						__(
							'An overview of a niche or industry: subtopics, content gaps, a first cluster of pages to build and how sites earn money.',
							'chatbot'
						) }
					{ 'gap' === kind &&
						__(
							'Keywords and topics your competitors cover and you don’t, the ones you share, your advantages and an action plan.',
							'chatbot'
						) }
				</p>
				<div className="qcseo-grid">
					{ 'keywords' === kind && (
						<>
							<TextControl
								__next40pxDefaultSize
								label={ __(
									'Seed keyword',
									'chatbot'
								) }
								value={ form.seed }
								onChange={ set( 'seed' ) }
								disabled={ !! form.post_id }
								__nextHasNoMarginBottom
							/>
							<SelectControl
								__next40pxDefaultSize
								label={ __(
									'…or one of your pages',
									'chatbot'
								) }
								value={ form.post_id }
								options={ [
									{
										value: '',
										label: __( '—', 'chatbot' ),
									},
									...pages.map( ( p ) => ( {
										value: String( p.id ),
										label: p.title,
									} ) ),
								] }
								onChange={ set( 'post_id' ) }
								__nextHasNoMarginBottom
							/>
						</>
					) }
					{ 'niche' === kind && (
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Niche or industry',
								'chatbot'
							) }
							value={ form.seed }
							onChange={ set( 'seed' ) }
							__nextHasNoMarginBottom
						/>
					) }
					{ 'gap' === kind && (
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Competitor domains (up to five, comma-separated)',
								'chatbot'
							) }
							help={ sprintf(
								/* translators: %s: this site's domain. */
								__( 'Compared with %s.', 'chatbot' ),
								status.own_domain
							) }
							value={ form.competitors }
							onChange={ set( 'competitors' ) }
							__nextHasNoMarginBottom
						/>
					) }
					{ 'gap' === kind && (
						<CompetitorSuggest
							value={ form.competitors }
							onChange={ set( 'competitors' ) }
							focus={ form.seed }
							max={ 5 }
							auto
						/>
					) }
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Language', 'chatbot' ) }
						value={ form.language }
						onChange={ set( 'language' ) }
						placeholder="en"
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Country', 'chatbot' ) }
						value={ form.country }
						onChange={ set( 'country' ) }
						placeholder="us"
						__nextHasNoMarginBottom
					/>
				</div>
				<p className="qcseo-muted">
					{ status.provider
						? sprintf(
								/* translators: %s: provider name. */
								__(
									'Keyword lists and rankings come from %s (real numbers); AI adds intent, clusters and reasons.',
									'chatbot'
								),
								status.provider.label
							)
						: __(
								'No keyword data provider connected: AI suggests keywords with high / medium / low estimates. Connect a provider in Settings & AI Provider for real volumes.',
								'chatbot'
							) }
				</p>
				<div className="qcseo-actions">
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || !! aiOff }
						onClick={ () => go( false ) }
					>
						{ estimate && estimate.cached
							? __(
									'Open saved result (free)',
									'chatbot'
								)
							: __( 'Run research', 'chatbot' ) }
					</Button>
					<CheckboxControl
						label={ __(
							'Refresh (ignore a saved result)',
							'chatbot'
						) }
						checked={ refresh }
						onChange={ setRefresh }
						__nextHasNoMarginBottom
					/>
					{ aiOff && <AiReason /> }
				</div>
				<div className="qcseo-estimate" aria-live="polite">
					{ estimating && ! estimate && (
						<p className="qcseo-muted">
							{ __( 'Estimating the cost…', 'chatbot' ) }
						</p>
					) }
					{ estimate && (
						<p className="qcseo-muted">{ costLine( estimate ) }</p>
					) }
					{ estimate && estimate.note && (
						<p className="qcseo-muted">{ estimate.note }</p>
					) }
				</div>
				{ ask && (
					<Notice status="warning" isDismissible={ false }>
						<p>
							{ sprintf(
								/* translators: %s: the owner's cost limit. */
								__(
									'This run costs more than your limit of %s.',
									'chatbot'
								),
								money( ask.limit )
							) }{ ' ' }
							{ costLine( ask ) }
						</p>
						<div className="qcseo-actions">
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={ busy }
								onClick={ () => go( true ) }
							>
								{ __( 'Run it', 'chatbot' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => setAsk( null ) }
							>
								{ __( 'Cancel', 'chatbot' ) }
							</Button>
						</div>
					</Notice>
				) }
				{ message && <p className="qcseo-error">{ message }</p> }
			</div>

			{ run && 'running' === run.status && run.job_id > 0 && (
				<div className="qcseo-card">
					<JobProgress
						jobId={ run.job_id }
						onChange={ ( job ) => {
							if ( job && 'done' === job.status ) {
								open( run.id );
							}
						} }
					/>
				</div>
			) }
			{ run && 'failed' === run.status && (
				<p className="qcseo-error">
					{ ( run.result && run.result.error ) ||
						__( 'The research failed.', 'chatbot' ) }
				</p>
			) }
			{ run && 'done' === run.status && (
				<Result
					key={ run.id }
					run={ run }
					status={ status }
					reload={ () => open( run.id ) }
				/>
			) }
			{ applyFilters( 'qcld_wpbot_seo.research.after', null, {
				status,
				open,
			} ) }

			{ history.length > 0 && (
				<div className="qcseo-card">
					<h3>{ __( 'Research history', 'chatbot' ) }</h3>
					<table className="widefat striped qcseo-table">
						<tbody>
							{ history.map( ( h ) => (
								<tr key={ h.id }>
									<td>{ KINDS[ h.kind ] }</td>
									<td>{ h.seed }</td>
									<td>
										{ [ h.language, h.country ]
											.filter( Boolean )
											.join( ' / ' ) }
									</td>
									<td className="qcseo-muted">
										{ h.created_at }
									</td>
									<td>
										<Button
											variant="link"
											onClick={ () => open( h.id ) }
										>
											{ __( 'Open', 'chatbot' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
			{ ! data.can.use && (
				<p className="qcseo-muted">
					{ __(
						'Running research needs the "Use" permission.',
						'chatbot'
					) }
				</p>
			) }
		</div>
	);
};

export default Research;
