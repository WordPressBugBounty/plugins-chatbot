/**
 * Shared competitor list (round 2, R2).
 *
 * CompetitorSuggest: the "Suggest competitors" button. It first asks what to
 * compete on (a product, service or topic) and which kind of competitor to
 * look for; the AI's verified suggestions are shown pre-ticked, and ticked
 * ones are added to the field and, for people who can change settings, to
 * the shared list. Once the owner has given a brief, an empty field with
 * `auto` runs it on its own (once per brief). Used next to every competitor
 * field (and by Pro through window.qcldWpbotSeoUI).
 *
 * CompetitorSettings: the shared list in Settings & AI Provider.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../api';
import { AiReason, aiBlocked, useAiReady } from '../ai';

const SOURCES = {
	owner: __( 'Added by you', 'chatbot' ),
	ai: __( 'AI suggestion', 'chatbot' ),
	keyword_provider: __( 'Keyword data provider', 'chatbot' ),
	search_console: __( 'Search Console', 'chatbot' ),
	ai_writer: __( 'AI Writer', 'chatbot' ),
	seo: __( 'Your earlier list', 'chatbot' ),
};

const KINDS = {
	any: __( 'Any kind', 'chatbot' ),
	local: __( 'Local businesses near me', 'chatbot' ),
	online: __( 'Online shops and services', 'chatbot' ),
	content: __( 'Blogs and content sites', 'chatbot' ),
};

/**
 * The owner's last brief (shared by every picker on the page; updated after each run).
 *
 * @return {Object} set, focus, kind, default_focus, has_location.
 */
const prefs = () =>
	data.compPrefs || {
		set: false,
		focus: '',
		kind: 'any',
		default_focus: '',
		has_location: false,
	};

/**
 * "Competitors for: bike servicing · Local businesses near me".
 *
 * @param {Object} result Suggest result (focus, kind).
 * @return {string} Line.
 */
const briefLine = ( result ) => {
	const parts = [];
	if ( result.focus ) {
		parts.push( result.focus );
	}
	if ( result.kind && 'any' !== result.kind ) {
		parts.push( KINDS[ result.kind ] || result.kind );
	}
	return parts.length
		? sprintf(
				/* translators: %s: what the owner competes on, and the kind of competitor. */
				__( 'Competitors for: %s', 'chatbot' ),
				parts.join( ' · ' )
			)
		: __( 'Competitors for the whole site', 'chatbot' );
};

/**
 * Domains in a field's text (one per line or comma separated).
 *
 * @param {string} text Text.
 * @return {string[]} Domains.
 */
export const splitDomains = ( text ) =>
	String( text || '' )
		.split( /[\s,]+/ )
		.map( ( d ) => d.trim() )
		.filter( Boolean );

/**
 * Suggestions with checkboxes (pre-ticked), reasons and what was left out.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.result    Suggest result.
 * @param {Object}   props.picked    domain => ticked.
 * @param {Function} props.setPicked Setter.
 * @return {Element} List.
 */
const SuggestionList = ( { result, picked, setPicked } ) => (
	<div className="qcseo-competitor-suggestions">
		<p className="qcseo-competitor-brief">
			<strong>{ briefLine( result ) }</strong>
		</p>
		{ ! result.web_search && (
			<p className="qcseo-muted">
				{ __(
					'Your AI model cannot search the web, so these come from what it already knows. Each site was opened to check it exists.',
					'chatbot'
				) }
			</p>
		) }
		{ 0 === result.suggestions.length && (
			<p>
				{ __(
					'No new competitors were found that passed the checks.',
					'chatbot'
				) }
			</p>
		) }
		<ul>
			{ result.suggestions.map( ( item ) => (
				<li key={ item.domain }>
					<CheckboxControl
						__nextHasNoMarginBottom
						checked={ !! picked[ item.domain ] }
						onChange={ ( v ) =>
							setPicked( { ...picked, [ item.domain ]: v } )
						}
						label={
							<>
								<strong>{ item.domain }</strong>
								{ item.title && (
									<span className="qcseo-muted">
										{ ' ' }
										{ item.title }
									</span>
								) }
							</>
						}
						help={ `${ item.reason } (${
							SOURCES[ item.source ] || item.source
						})` }
					/>
				</li>
			) ) }
		</ul>
		{ result.dropped && result.dropped.length > 0 && (
			<details>
				<summary>
					{ sprintf(
						/* translators: %d: suggestions left out. */
						_n(
							'%d suggestion was left out',
							'%d suggestions were left out',
							result.dropped.length,
							'chatbot'
						),
						result.dropped.length
					) }
				</summary>
				<ul>
					{ result.dropped.map( ( d ) => (
						<li key={ d.domain }>
							{ d.domain }: { d.why }
						</li>
					) ) }
				</ul>
			</details>
		) }
	</div>
);

/**
 * "Suggest competitors" next to a competitor field.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.value     The field's text.
 * @param {Function} props.onChange  Field setter (text).
 * @param {string}   props.separator Separator when adding (", " or "\n").
 * @param {number}   props.max       Most domains in the field.
 * @param {string}   props.focus     Suggested "compete on" text (for example the research keyword).
 * @param {boolean}  props.auto      Once a brief was given: run on its own while the field is empty (once per brief).
 * @param {Function} props.onSaved   Called after ticked sites were saved to the shared list.
 * @return {Element} Picker.
 */
export const CompetitorSuggest = ( {
	value,
	onChange,
	separator = ', ',
	max = 20,
	focus = '',
	auto = false,
	onSaved = () => {},
} ) => {
	const ai = useAiReady();
	const blocked = aiBlocked( ai );
	const [ busy, setBusy ] = useState( false );
	const [ asking, setAsking ] = useState( false );
	const [ brief, setBrief ] = useState( {
		focus: '',
		kind: 'any',
	} );
	const [ result, setResult ] = useState( null );
	const [ picked, setPicked ] = useState( {} );
	const [ message, setMessage ] = useState( null );
	const tried = useRef( false );

	// The form starts from the given focus, else the last brief, else the site's tagline or name.
	const openForm = () => {
		const last = prefs();
		setBrief( {
			focus: focus || last.focus || last.default_focus || '',
			kind: last.kind || 'any',
		} );
		setResult( null );
		setAsking( true );
	};

	const run = ( isAuto, next ) => {
		setBusy( true );
		setMessage( null );
		setAsking( false );
		seo( 'competitors/suggest', {
			method: 'POST',
			data: { auto: isAuto, focus: next.focus, kind: next.kind },
		} )
			.then( ( r ) => {
				if ( ! isAuto ) {
					data.compPrefs = {
						...prefs(),
						set: true,
						focus: r.focus,
						kind: r.kind,
					};
				}
				if ( r.skipped ) {
					return;
				}
				setResult( r );
				const ticks = {};
				r.suggestions.forEach( ( s ) => {
					ticks[ s.domain ] = true;
				} );
				setPicked( ticks );
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			)
			.finally( () => setBusy( false ) );
	};

	// After the first brief: run on its own with the last choices (the server runs each brief once).
	useEffect( () => {
		if (
			! auto ||
			tried.current ||
			! ai ||
			blocked ||
			splitDomains( value ).length
		) {
			return;
		}
		tried.current = true;
		const last = prefs();
		if ( last.set ) {
			run( true, {
				focus: focus || last.focus,
				kind: last.kind || 'any',
			} );
		} else {
			openForm();
		}
	}, [ auto, ai, blocked, value ] );

	const use = () => {
		const chosen = result.suggestions.filter( ( s ) => picked[ s.domain ] );
		const domains = Array.from(
			new Set( [
				...splitDomains( value ),
				...chosen.map( ( s ) => s.domain ),
			] )
		).slice( 0, max );
		onChange( domains.join( separator ) );
		setResult( null );
		if ( ! data.can.settings || ! chosen.length ) {
			return;
		}
		seo( 'competitors/add', {
			method: 'POST',
			data: { items: chosen },
		} )
			.then( () => {
				setMessage( {
					status: 'success',
					text: __(
						'Added here and to your competitor list.',
						'chatbot'
					),
				} );
				onSaved();
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	};

	return (
		<div className="qcseo-competitor-picker">
			<div className="qcseo-actions">
				<Button
					variant="secondary"
					size="compact"
					isBusy={ busy }
					disabled={ busy || !! blocked }
					onClick={ openForm }
				>
					{ __( 'Suggest competitors', 'chatbot' ) }
				</Button>
				{ blocked && <AiReason /> }
				{ busy && (
					<span className="qcseo-muted">
						<Spinner />
						{ __(
							'Finding competitors and checking each site…',
							'chatbot'
						) }
					</span>
				) }
			</div>
			{ asking && ! busy && ! blocked && (
				<div className="qcseo-competitor-ask">
					<p className="qcseo-muted">
						{ __(
							'Tell the AI what you compete on and which competitors you mean. Your choices are kept for next time.',
							'chatbot'
						) }
					</p>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Compete on', 'chatbot' ) }
						help={ __(
							'A product, service or topic, for example "bike servicing". Leave empty for the whole site.',
							'chatbot'
						) }
						value={ brief.focus }
						onChange={ ( v ) => setBrief( { ...brief, focus: v } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Kind of competitor', 'chatbot' ) }
						value={ brief.kind }
						options={ Object.keys( KINDS ).map( ( k ) => ( {
							value: k,
							label: KINDS[ k ],
						} ) ) }
						onChange={ ( v ) => setBrief( { ...brief, kind: v } ) }
						help={
							'local' === brief.kind && ! prefs().has_location
								? __(
										'Add your business location in Local SEO for better local results.',
										'chatbot'
									)
								: undefined
						}
					/>
					<div className="qcseo-actions">
						<Button
							variant="primary"
							size="compact"
							onClick={ () => run( false, brief ) }
						>
							{ __( 'Find competitors', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							size="compact"
							onClick={ () => setAsking( false ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ result && (
				<>
					<SuggestionList
						result={ result }
						picked={ picked }
						setPicked={ setPicked }
					/>
					{ result.suggestions.length > 0 && (
						<div className="qcseo-actions">
							<Button
								variant="primary"
								size="compact"
								disabled={
									! Object.values( picked ).some( Boolean )
								}
								onClick={ use }
							>
								{ data.can.settings
									? __(
											'Use the ticked sites and save them to my competitors',
											'chatbot'
										)
									: __(
											'Use the ticked sites',
											'chatbot'
										) }
							</Button>
							<Button
								variant="tertiary"
								size="compact"
								onClick={ () => setResult( null ) }
							>
								{ __( 'Close', 'chatbot' ) }
							</Button>
						</div>
					) }
				</>
			) }
		</div>
	);
};

/**
 * Settings: the shared competitor list.
 *
 * @return {Element} Card.
 */
export const CompetitorSettings = () => {
	const [ view, setView ] = useState( null );
	const [ text, setText ] = useState( '' );
	const [ adding, setAdding ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	const load = () =>
		seo( 'competitors' )
			.then( ( r ) => {
				setView( r );
				setText( r.list.map( ( c ) => c.domain ).join( '\n' ) );
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	useEffect( () => {
		load();
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}
	const save = ( items ) =>
		seo( 'competitors/save', { method: 'POST', data: { items } } )
			.then( ( r ) => {
				setView( { ...view, list: r.list } );
				setText( r.list.map( ( c ) => c.domain ).join( '\n' ) );
				setMessage( {
					status: 'success',
					text: __( 'Competitors saved.', 'chatbot' ),
				} );
			} )
			.catch( ( e ) =>
				setMessage( { status: 'error', text: errorMessage( e ) } )
			);
	const keep = ( domain ) => view.list.filter( ( c ) => c.domain !== domain );
	return (
		<div className="qcseo-card qcseo-competitors">
			<h3>{ __( 'Your competitors', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'One list for every competitor field: Competitor gap research, link prospects (competitors are left out), and AI Writer. Up to 20 sites.',
					'chatbot'
				) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ 0 === view.list.length && (
				<p>
					{ __(
						'No competitors yet. Let the AI suggest some, or add them yourself.',
						'chatbot'
					) }
				</p>
			) }
			{ view.list.length > 0 && (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Site', 'chatbot' ) }</th>
							<th>{ __( 'Why', 'chatbot' ) }</th>
							<th>{ __( 'From', 'chatbot' ) }</th>
							<th>
								<span className="screen-reader-text">
									{ __( 'Actions', 'chatbot' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ view.list.map( ( c ) => (
							<tr key={ c.domain }>
								<td>
									<a
										href={ `https://${ c.domain }/` }
										target="_blank"
										rel="noreferrer noopener"
									>
										{ c.domain }
									</a>
								</td>
								<td>{ c.reason || '—' }</td>
								<td>{ SOURCES[ c.source ] || c.source }</td>
								<td>
									{ view.can_save && (
										<Button
											variant="link"
											isDestructive
											onClick={ () =>
												save( keep( c.domain ) )
											}
										>
											{ __(
												'Remove',
												'chatbot'
											) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ view.can_save && (
				<div className="qcseo-actions qcseo-actions--field">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Add a competitor', 'chatbot' ) }
						placeholder="example.com"
						value={ adding }
						onChange={ setAdding }
					/>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						disabled={ ! adding.trim() }
						onClick={ () => {
							save( [ ...view.list, adding.trim() ] );
							setAdding( '' );
						} }
					>
						{ __( 'Add', 'chatbot' ) }
					</Button>
				</div>
			) }
			<CompetitorSuggest
				value={ text }
				onChange={ () => {} }
				onSaved={ load }
				separator={ '\n' }
			/>
			{ applyFilters( 'qcld_wpbot_seo.competitors.after', null, {
				reload: load,
			} ) }
		</div>
	);
};
