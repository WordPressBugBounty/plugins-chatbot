/**
 * "Places to show up": pick up to five searches, see the cost, and the
 * AI (with web search when the model can) plus YouTube list the threads,
 * videos and articles where the owner could help. Best fit first; each
 * place shows its source and its community's rules. Nothing is posted.
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
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { AiReason, aiBlocked, useAiReady } from '../../ai';
import JobProgress from '../JobProgress';
import { costText } from './SiteContext';
import PlaceDraft from './PlaceDraft';

const SOURCE = {
	ai: __( 'Found by AI web search', 'chatbot' ),
	youtube: __( 'YouTube search', 'chatbot' ),
	provider: __(
		'Search results (keyword data provider)',
		'chatbot'
	),
	suggestion: __( 'AI suggestion (not checked)', 'chatbot' ),
	citation: __( 'Cited by AI assistants', 'chatbot' ),
};

const SEARCH_SOURCE = {
	focus: __( 'focus', 'chatbot' ),
	vault: __( 'Vault', 'chatbot' ),
	search_console: __( 'Search Console', 'chatbot' ),
};

/**
 * @param {Object}   props               Props.
 * @param {number}   props.version       Bumped when the focus changed.
 * @param {Function} props.renderActions Actions for a place (drafts add theirs).
 * @param {Function} props.onPosted      Called after "I posted it".
 * @return {Element} Card.
 */
const PlacesToShowUp = ( {
	version = 0,
	renderActions = null,
	onPosted = () => {},
} ) => {
	const ai = useAiReady();
	const [ view, setView ] = useState( null );
	const [ picked, setPicked ] = useState( [] );
	const [ own, setOwn ] = useState( '' );
	const [ estimate, setEstimate ] = useState( null );
	const [ job, setJob ] = useState( 0 );
	const [ platform, setPlatform ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const fail = ( e ) =>
		setMessage( { status: 'error', text: errorMessage( e ) } );
	const load = () =>
		seo( `cited/places?platform=${ platform }` )
			.then( ( next ) => {
				setView( next );
				setPicked( ( current ) =>
					current.length
						? current
						: next.searches
								.slice( 0, Math.min( 3, next.max ) )
								.map( ( s ) => s.search )
				);
			} )
			.catch( fail );
	useEffect( () => {
		load();
	}, [ version, platform ] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}
	const searches = [ ...picked, own.trim() ].filter( Boolean );
	const ask = () => {
		setBusy( true );
		setMessage( null );
		seo( 'cited/places/estimate', { method: 'POST', data: { searches } } )
			.then( setEstimate )
			.catch( fail )
			.finally( () => setBusy( false ) );
	};
	const run = () => {
		setBusy( true );
		seo( 'cited/places/find', { method: 'POST', data: { searches } } )
			.then( ( r ) => {
				setEstimate( null );
				setJob( r.job_id );
			} )
			.catch( fail )
			.finally( () => setBusy( false ) );
	};
	const setting = ( body ) =>
		seo( 'cited/settings', { method: 'POST', data: body } )
			.then( setView )
			.catch( fail );
	const toggle = ( search, on ) =>
		setPicked(
			on
				? [ ...picked, search ].slice( 0, view.max )
				: picked.filter( ( s ) => s !== search )
		);
	const dismiss = ( place ) =>
		seo( 'cited/places/status', {
			method: 'POST',
			data: { id: place.id, status: 'dismissed' },
		} )
			.then( load )
			.catch( fail );

	return (
		<div className="qcseo-card qcseo-cited-places">
			<h3>{ __( 'Places to show up', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ view.web_search
					? __(
							'Your AI searches the web for threads, questions, videos and articles that rank or get quoted for your searches.',
							'chatbot'
						)
					: __(
							'Your AI model cannot search the web, so it suggests communities where people discuss your searches. Choose a model with web search in Settings & AI Provider > AI for real threads.',
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
			<fieldset className="qcseo-cited-searches">
				<legend>
					{ sprintf(
						/* translators: %d: most searches per run. */
						__( 'Searches (up to %d)', 'chatbot' ),
						view.max
					) }
				</legend>
				{ view.searches.map( ( s ) => (
					<CheckboxControl
						key={ s.search }
						__nextHasNoMarginBottom
						label={ `${ s.search } (${
							SEARCH_SOURCE[ s.source ] || s.source
						})` }
						checked={ picked.includes( s.search ) }
						disabled={
							! picked.includes( s.search ) &&
							picked.length >= view.max
						}
						onChange={ ( on ) => toggle( s.search, on ) }
					/>
				) ) }
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Or type a search', 'chatbot' ) }
					value={ own }
					onChange={ setOwn }
				/>
			</fieldset>
			<div className="qcseo-actions">
				{ ! estimate && ! job && (
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={
							busy || ! searches.length || aiBlocked( ai )
						}
						onClick={ ask }
					>
						{ __( 'Find places', 'chatbot' ) }
					</Button>
				) }
				{ estimate && (
					<>
						<span className="qcseo-muted">
							{ sprintf(
								/* translators: 1: searches, 2: cost. */
								__(
									'%1$d AI searches, %2$s in total, from your own AI key.',
									'chatbot'
								),
								estimate.searches,
								costText( estimate.cost )
							) }
							{ estimate.provider &&
								' ' +
									sprintf(
										/* translators: %s: provider. */
										__(
											'Includes Google results from %s.',
											'chatbot'
										),
										estimate.provider
									) }
							{ estimate.youtube &&
								' ' +
									__(
										'YouTube results are free.',
										'chatbot'
									) }
						</span>
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy }
							onClick={ run }
						>
							{ __( 'Find them', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setEstimate( null ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</>
				) }
				{ aiBlocked( ai ) && <AiReason /> }
			</div>
			{ job > 0 && (
				<JobProgress
					jobId={ job }
					onChange={ ( s ) => {
						if ( s && 'done' === s.status ) {
							setJob( 0 );
							load();
						}
					} }
				/>
			) }
			{ view.can_edit && 'unavailable' !== view.youtube && (
				<div className="qcseo-cited-youtube qcseo-muted">
					{ 'off' === view.youtube && (
						<>
							{ __(
								'Add YouTube videos to the places (free, read-only).',
								'chatbot'
							) }{ ' ' }
							<Button
								variant="link"
								onClick={ () => setting( { youtube: true } ) }
							>
								{ __( 'Turn on YouTube', 'chatbot' ) }
							</Button>
						</>
					) }
					{ ( 'reconnect' === view.youtube ||
						'not_connected' === view.youtube ) && (
						<>
							{ __(
								'YouTube is on. Reconnect Google once to allow read-only YouTube, and turn on "YouTube Data API v3" in your Google Cloud project.',
								'chatbot'
							) }{ ' ' }
							<a
								href={ `${ data.pageUrl }&tab=settings&section=connections` }
							>
								{ __( 'Open Connections', 'chatbot' ) }
							</a>
						</>
					) }
					{ 'connected' === view.youtube &&
						__(
							'YouTube videos are included.',
							'chatbot'
						) }
				</div>
			) }

			<div className="qcseo-head">
				<strong>
					{ sprintf(
						/* translators: %d: places. */
						__( '%d places', 'chatbot' ),
						view.places.length
					) }
				</strong>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Platform', 'chatbot' ) }
					hideLabelFromVision
					value={ platform }
					options={ [
						{
							value: '',
							label: __( 'All platforms', 'chatbot' ),
						},
						...view.platforms
							.filter( ( p ) => p.on )
							.map( ( p ) => ( {
								value: p.id,
								label: p.label,
							} ) ),
					] }
					onChange={ setPlatform }
				/>
			</div>
			{ 0 === view.places.length && ! job && (
				<p className="qcseo-muted">
					{ __(
						'No places yet. Pick your searches and click Find places.',
						'chatbot'
					) }
				</p>
			) }
			<ul className="qcseo-places">
				{ view.places.map( ( place ) => (
					<li key={ place.id } className="qcseo-place">
						<div className="qcseo-place__head">
							<span className="qcseo-status">
								{ place.platform_label }
							</span>
							{ place.url ? (
								<a
									href={ place.url }
									target="_blank"
									rel="noreferrer noopener"
								>
									{ place.title }
								</a>
							) : (
								<strong>{ place.title }</strong>
							) }
							<span
								className="qcseo-place__fit"
								title={ __(
									'Fit with your focus',
									'chatbot'
								) }
							>
								{ sprintf(
									/* translators: %d: fit score. */
									__( 'Fit %d', 'chatbot' ),
									place.fit
								) }
							</span>
						</div>
						<p>{ place.why }</p>
						<p className="qcseo-muted">
							{ sprintf(
								/* translators: 1: search, 2: source. */
								__( 'For "%1$s" · %2$s', 'chatbot' ),
								place.search,
								SOURCE[ place.source ] || place.source
							) }
							{ place.detail && place.detail.date
								? ` · ${ place.detail.date }`
								: '' }
						</p>
						{ place.rules && (
							<p className="qcseo-place__rules">
								{ place.rules }
							</p>
						) }
						<PlaceDraft
							place={ place }
							reload={ ( info ) => {
								load();
								if ( info && info.posted ) {
									onPosted();
								}
							} }
						/>
						<div className="qcseo-actions">
							{ renderActions && renderActions( place, load ) }
							{ applyFilters(
								'qcld_wpbot_seo.cited.placeActions',
								null,
								{ place, reload: load }
							) }
							<Button
								variant="tertiary"
								size="compact"
								onClick={ () => dismiss( place ) }
							>
								{ __( 'Not relevant', 'chatbot' ) }
							</Button>
						</div>
					</li>
				) ) }
			</ul>
		</div>
	);
};

export default PlacesToShowUp;
