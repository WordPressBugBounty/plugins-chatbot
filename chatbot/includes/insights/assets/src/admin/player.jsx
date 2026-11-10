/* global wp */
import rrwebPlayer from 'rrweb-player';
import { api, go } from './api';
import { Icon } from './icons';
import { duration, dateTime, path, flag, bytes, relative } from './format';
import { SIGNALS } from './overview';
import { applyExt, extList, hasRender, ExtPart } from './extend';

const { createElement, useState, useEffect, useRef } = wp.element;
const { __, sprintf } = wp.i18n;

// Custom-event tags the recorder adds for frustration signals, with their timeline colour.
const MARKER_TAGS = {
	'rage click': { type: 'rage', color: '#e25563' },
	'dead click': { type: 'dead', color: '#c27803' },
	'js error': { type: 'error', color: '#7c3aed' },
};

function clock( ms ) {
	const s = Math.max( 0, Math.round( ms / 1000 ) );
	return Math.floor( s / 60 ) + ':' + String( s % 60 ).padStart( 2, '0' );
}

function markersOf( events ) {
	if ( ! events || ! events.length ) {
		return [];
	}
	const start = events[ 0 ].timestamp;
	return events
		.filter( ( e ) => e.type === 5 && e.data && MARKER_TAGS[ e.data.tag ] )
		.map( ( e ) => {
			const payload = e.data.payload || {};
			const detail = payload.detail || {};
			return {
				type: MARKER_TAGS[ e.data.tag ].type,
				offset: e.timestamp - start,
				text: MARKER_TAGS[ e.data.tag ].type === 'error' ? detail.m || '' : payload.selector || '',
			};
		} );
}

function Replay( { events, onPlayer } ) {
	const ref = useRef( null );

	useEffect( () => {
		const el = ref.current;
		if ( ! el || ! events || events.length < 2 ) {
			return undefined;
		}
		el.innerHTML = '';
		const width = Math.max( 320, el.clientWidth );
		const meta = events.find( ( e ) => e.type === 4 && e.data );
		const ratio = meta && meta.data.width ? meta.data.height / meta.data.width : 0.5625;
		const height = Math.min( Math.round( width * ratio ), Math.round( window.innerHeight * 0.68 ) );
		let player;
		try {
			player = new rrwebPlayer( {
				target: el,
				props: {
					events,
					width,
					height,
					autoPlay: true,
					skipInactive: true,
					speedOption: [ 1, 2, 4, 8 ],
					showController: true,
					mouseTail: { strokeStyle: 'rgba(91, 78, 150, 0.55)', lineWidth: 2 },
					UNSAFE_replayCanvas: false,
					tags: Object.keys( MARKER_TAGS ).reduce( ( acc, tag ) => {
						acc[ tag ] = MARKER_TAGS[ tag ].color;
						return acc;
					}, {} ),
				},
			} );
			if ( onPlayer ) {
				onPlayer( player );
			}
		} catch ( e ) {
			el.innerHTML = '<div class="qcin-notice qcin-notice--error">' + __( 'This recording could not be replayed.', 'qcin-textdomain' ) + '</div>';
		}
		return () => {
			if ( onPlayer ) {
				onPlayer( null );
			}
			if ( player && player.$destroy ) {
				try {
					player.$destroy();
				} catch ( e ) {}
			}
		};
	}, [ events ] );

	return <div className="qcin-replay" ref={ ref } />;
}

const FRUSTRATION_LABEL = {
	none: () => __( 'No frustration', 'qcin-textdomain' ),
	low: () => __( 'Some frustration', 'qcin-textdomain' ),
	high: () => __( 'High frustration', 'qcin-textdomain' ),
};

function AiSummary( { id } ) {
	const [ data, setData ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		setData( null );
		setError( '' );
		api.get( '/sessions/' + id + '/summary' )
			.then( setData )
			.catch( () => setData( { summary: null, state: { ready: false } } ) );
	}, [ id ] );

	const run = ( force ) => {
		setBusy( true );
		setError( '' );
		api.post( '/sessions/' + id + '/summary', { force: !! force } )
			.then( setData )
			.catch( ( e ) => setError( ( e && e.message ) || __( 'The summary could not be created.', 'qcin-textdomain' ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! data ) {
		return <div className="qcin-ai-summary qcin-skeleton" aria-busy="true" />;
	}

	const state = data.state || {};
	const sum = data.summary;
	const r = ( sum && sum.result ) || {};

	let body;
	if ( sum ) {
		body = (
			<>
				<p className="qcin-ai-summary__text">{ r.summary }</p>
				{ ( r.goal || r.outcome ) && (
					<dl className="qcin-ai-summary__facts">
						{ r.goal && (
							<>
								<dt>{ __( 'Goal', 'qcin-textdomain' ) }</dt>
								<dd>{ r.goal }</dd>
							</>
						) }
						{ r.outcome && (
							<>
								<dt>{ __( 'Outcome', 'qcin-textdomain' ) }</dt>
								<dd>{ r.outcome }</dd>
							</>
						) }
					</dl>
				) }
				{ r.struggles && r.struggles.length > 0 && (
					<ul className="qcin-ai-summary__struggles">
						{ r.struggles.map( ( t, i ) => (
							<li key={ i }>{ t }</li>
						) ) }
					</ul>
				) }
				{ r.frustration && FRUSTRATION_LABEL[ r.frustration ] && (
					<span className={ 'qcin-ai-level qcin-ai-level--' + r.frustration }>{ FRUSTRATION_LABEL[ r.frustration ]() }</span>
				) }
				{ sum.stale && <p className="qcin-help">{ __( 'The visit continued after this summary was written.', 'qcin-textdomain' ) }</p> }
				<div className="qcin-ai-summary__meta">
					<span className="qcin-muted">
						{ sprintf(
							/* translators: 1: provider and model, 2: number of tokens, 3: relative time */
							__( '%1$s · %2$s tokens · %3$s', 'qcin-textdomain' ),
							sum.model,
							Number( sum.tokens || 0 ).toLocaleString(),
							relative( sum.createdAt )
						) }
					</span>
					{ state.feature && (
						<button type="button" className="qcin-link-btn" onClick={ () => run( true ) } disabled={ busy || !! state.paused }>
							{ __( 'Write again', 'qcin-textdomain' ) }
						</button>
					) }
				</div>
			</>
		);
	} else if ( ! state.ready ) {
		body = (
			<>
				<p className="qcin-muted">{ __( 'Get a short summary of what this visitor wanted, where they struggled and how the visit ended.', 'qcin-textdomain' ) }</p>
				<a className="qcin-btn qcin-btn--ghost qcin-btn--small" href="#/settings?section=ai">
					{ __( 'Set up AI in Settings', 'qcin-textdomain' ) }
				</a>
			</>
		);
	} else if ( ! state.feature ) {
		body = (
			<>
				<p className="qcin-muted">{ __( 'AI session summaries are turned off.', 'qcin-textdomain' ) }</p>
				<a className="qcin-btn qcin-btn--ghost qcin-btn--small" href="#/settings?section=ai">
					{ __( 'Turn on in Settings', 'qcin-textdomain' ) }
				</a>
			</>
		);
	} else {
		body = (
			<>
				<p className="qcin-muted">{ __( 'Get a short summary of what this visitor wanted, where they struggled and how the visit ended.', 'qcin-textdomain' ) }</p>
				<button type="button" className="qcin-btn qcin-btn--primary qcin-btn--small" onClick={ () => run( false ) } disabled={ busy || !! state.paused }>
					<Icon.sparkle size={ 15 } /> { busy ? __( 'Summarizing…', 'qcin-textdomain' ) : __( 'Summarize visit', 'qcin-textdomain' ) }
				</button>
				{ state.label && (
					<p className="qcin-help">
						{ sprintf(
							/* translators: %s: provider and model */
							__( 'Uses %s.', 'qcin-textdomain' ),
							state.label
						) }
					</p>
				) }
			</>
		);
	}

	return (
		<section className="qcin-ai-summary" aria-busy={ busy }>
			<h3>
				<Icon.sparkle size={ 16 } /> { __( 'AI summary', 'qcin-textdomain' ) }
			</h3>
			{ busy && sum && <p className="qcin-muted">{ __( 'Writing a new summary…', 'qcin-textdomain' ) }</p> }
			{ body }
			{ state.paused && ! busy && <div className="qcin-notice qcin-notice--error">{ state.paused }</div> }
			{ error && <div className="qcin-notice qcin-notice--error" role="alert">{ error }</div> }
		</section>
	);
}

export function Player( { id, notify } ) {
	const [ session, setSession ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ current, setCurrent ] = useState( null );
	const [ events, setEvents ] = useState( null );
	const [ loadingEvents, setLoadingEvents ] = useState( false );
	const playerRef = useRef( null );

	useEffect( () => {
		setSession( null );
		setError( '' );
		api.get( '/sessions/' + id )
			.then( ( s ) => {
				setSession( s );
				if ( s.pageviews && s.pageviews.length ) {
					setCurrent( s.pageviews[ 0 ].id );
				}
			} )
			.catch( ( e ) => setError( ( e && e.message ) || __( 'Recording not found.', 'qcin-textdomain' ) ) );
	}, [ id ] );

	useEffect( () => {
		if ( ! current ) {
			return;
		}
		setEvents( null );
		setLoadingEvents( true );
		api.get( '/sessions/' + id + '/pageviews/' + current + '/events' )
			.then( ( r ) => setEvents( r.events || [] ) )
			.catch( () => setEvents( [] ) )
			.finally( () => setLoadingEvents( false ) );
	}, [ id, current ] );

	const toggleFavourite = () => {
		const value = ! session.favourite;
		api.post( '/sessions/' + id + '/favourite', { value } ).then( () => {
			setSession( { ...session, favourite: value } );
			notify( value ? __( 'Saved. This recording is kept past the retention period.', 'qcin-textdomain' ) : __( 'Removed from saved recordings.', 'qcin-textdomain' ), 'success' );
		} );
	};

	const remove = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this recording? This cannot be undone.', 'qcin-textdomain' ) ) ) {
			return;
		}
		api.del( '/sessions/' + id ).then( () => {
			notify( __( 'Recording deleted.', 'qcin-textdomain' ), 'success' );
			go( 'recordings' );
		} );
	};

	if ( error ) {
		return (
			<div className="qcin-card">
				<a className="qcin-back" href="#/recordings">
					<Icon.back size={ 16 } /> { __( 'All recordings', 'qcin-textdomain' ) }
				</a>
				<div className="qcin-notice qcin-notice--error">{ error }</div>
			</div>
		);
	}
	if ( ! session ) {
		return <div className="qcin-card qcin-skeleton qcin-skeleton--tall" aria-busy="true" />;
	}

	const pv = ( session.pageviews || [] ).find( ( p ) => p.id === current );
	const idx = ( session.pageviews || [] ).findIndex( ( p ) => p.id === current );
	const markers = applyExt( 'qcin.admin.playerMarkers', markersOf( events ), { events, session, pageview: pv } ).filter(
		( m ) => m && typeof m.offset === 'number' && ( m.label || SIGNALS[ m.type ] )
	);
	const ui = window.qcInsightsUI;
	const panels = extList( 'qcin.admin.playerPanels', null, hasRender );
	const actions = extList( 'qcin.admin.playerActions', null, hasRender );
	const pageSignals = ( session.signals || [] ).filter( ( sig ) => sig.pageview === current );
	const quickBack = pageSignals.find( ( sig ) => sig.type === 'quickback' );
	const countsFor = ( id ) => {
		const out = {};
		( session.signals || [] ).forEach( ( sig ) => {
			if ( sig.pageview === id && SIGNALS[ sig.type ] ) {
				out[ sig.type ] = ( out[ sig.type ] || 0 ) + 1;
			}
		} );
		return out;
	};
	const jump = ( offset ) => {
		if ( playerRef.current && playerRef.current.goto ) {
			playerRef.current.goto( Math.max( 0, offset - 1500 ), true );
		}
	};

	return (
		<div className="qcin-player-layout">
			<div className="qcin-card qcin-player">
				<div className="qcin-card__head">
					<div>
						<a className="qcin-back" href="#/recordings">
							<Icon.back size={ 16 } /> { __( 'All recordings', 'qcin-textdomain' ) }
						</a>
						<h2 className="qcin-card__title">{ pv ? pv.title || path( pv.url ) : __( 'Recording', 'qcin-textdomain' ) }</h2>
						{ pv && <p className="qcin-card__sub">{ path( pv.url ) }</p> }
					</div>
					<div className="qcin-actions">
						{ actions.map( ( a ) => (
							<ExtPart key={ a.id } part={ a.render } props={ { session, notify, ui } } />
						) ) }
						<button type="button" className={ 'qcin-btn qcin-btn--ghost qcin-btn--small' + ( session.favourite ? ' is-on' : '' ) } onClick={ toggleFavourite } aria-pressed={ session.favourite }>
							<Icon.star size={ 15 } filled={ session.favourite } /> { session.favourite ? __( 'Saved', 'qcin-textdomain' ) : __( 'Save', 'qcin-textdomain' ) }
						</button>
						<button type="button" className="qcin-btn qcin-btn--danger qcin-btn--small" onClick={ remove }>
							<Icon.trash size={ 15 } /> { __( 'Delete', 'qcin-textdomain' ) }
						</button>
					</div>
				</div>
				{ loadingEvents && <div className="qcin-skeleton qcin-skeleton--tall" aria-busy="true" /> }
				{ ! loadingEvents && events && events.length < 2 && (
					<div className="qcin-empty">
						<p>{ __( 'This page has no replayable data yet. If the visit is still live, check back in a minute.', 'qcin-textdomain' ) }</p>
					</div>
				) }
				{ ! loadingEvents && events && events.length >= 2 && <Replay events={ events } onPlayer={ ( p ) => ( playerRef.current = p ) } /> }
				{ quickBack && (
					<div className="qcin-notice qcin-notice--warn">
						<strong>{ SIGNALS.quickback.short }:</strong>{ ' ' }
						{ __( 'the visitor left this page within 5 seconds and went back to the previous page.', 'qcin-textdomain' ) }
					</div>
				) }
				{ markers.length > 0 && (
					<div className="qcin-markers">
						<h3>{ markers.every( ( m ) => SIGNALS[ m.type ] ) ? __( 'Frustration on this page', 'qcin-textdomain' ) : __( 'Moments on this page', 'qcin-textdomain' ) }</h3>
						<ul>
							{ markers.map( ( m, i ) => (
								<li key={ i }>
									<button type="button" className="qcin-marker" onClick={ () => jump( m.offset ) }>
										<span className={ 'qcin-sig qcin-sig--' + m.type } aria-hidden="true" />
										<span className="qcin-marker__time">{ clock( m.offset ) }</span>
										<strong>{ m.label || SIGNALS[ m.type ].short }</strong>
										{ m.text && <code>{ m.text.length > 70 ? m.text.slice( 0, 70 ) + '…' : m.text }</code> }
									</button>
								</li>
							) ) }
						</ul>
					</div>
				) }
				{ session.pageviews.length > 1 && (
					<div className="qcin-pv-nav">
						<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" disabled={ idx <= 0 } onClick={ () => setCurrent( session.pageviews[ idx - 1 ].id ) }>
							{ __( 'Previous page', 'qcin-textdomain' ) }
						</button>
						<span>
							{ sprintf(
								/* translators: 1: page number, 2: total pages */
								__( 'Page %1$d of %2$d in this visit', 'qcin-textdomain' ),
								idx + 1,
								session.pageviews.length
							) }
						</span>
						<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" disabled={ idx >= session.pageviews.length - 1 } onClick={ () => setCurrent( session.pageviews[ idx + 1 ].id ) }>
							{ __( 'Next page', 'qcin-textdomain' ) }
						</button>
					</div>
				) }
			</div>

			<aside className="qcin-card qcin-session-info">
				<AiSummary id={ id } />
				{ panels.map( ( p ) => (
					<ExtPart key={ p.id } part={ p.render } props={ { session, pageview: pv, events, jump, notify, ui } } />
				) ) }
				<h3>{ __( 'Visit details', 'qcin-textdomain' ) }</h3>
				<dl>
					<dt>{ __( 'Started', 'qcin-textdomain' ) }</dt>
					<dd>{ dateTime( session.started_at ) }</dd>
					<dt>{ __( 'Length', 'qcin-textdomain' ) }</dt>
					<dd>{ duration( session.duration_s ) }</dd>
					<dt>{ __( 'Device', 'qcin-textdomain' ) }</dt>
					<dd className="qcin-cap">{ session.device }</dd>
					<dt>{ __( 'Browser', 'qcin-textdomain' ) }</dt>
					<dd>
						{ session.browser } · { session.os }
					</dd>
					{ session.country && (
						<>
							<dt>{ __( 'Country', 'qcin-textdomain' ) }</dt>
							<dd>
								{ flag( session.country ) } { session.country }
							</dd>
						</>
					) }
					<dt>{ __( 'Came from', 'qcin-textdomain' ) }</dt>
					<dd>{ session.referrer || session.utm_source || __( 'Direct', 'qcin-textdomain' ) }</dd>
					<dt>{ __( 'Visitor', 'qcin-textdomain' ) }</dt>
					<dd>{ session.returning ? __( 'Returning', 'qcin-textdomain' ) : __( 'New', 'qcin-textdomain' ) }</dd>
					<dt>{ __( 'Size', 'qcin-textdomain' ) }</dt>
					<dd>{ bytes( session.bytes ) }</dd>
				</dl>
				<h3>{ __( 'Pages visited', 'qcin-textdomain' ) }</h3>
				<ol className="qcin-pages">
					{ session.pageviews.map( ( p ) => (
						<li key={ p.id }>
							<button type="button" className={ 'qcin-page' + ( p.id === current ? ' is-active' : '' ) } onClick={ () => setCurrent( p.id ) }>
								<span className="qcin-page__title">{ p.title || path( p.url ) }</span>
								<span className="qcin-muted">
									{ duration( p.duration_ms / 1000 ) } · { p.scroll }% { __( 'scrolled', 'qcin-textdomain' ) }
								</span>
								{ Object.keys( countsFor( p.id ) ).length > 0 && (
									<span className="qcin-page__flags">
										{ Object.entries( countsFor( p.id ) ).map( ( [ type, n ] ) => (
											<span key={ type } className={ 'qcin-badge qcin-badge--' + type }>
												{ SIGNALS[ type ].short }
												{ n > 1 ? ' ×' + n : '' }
											</span>
										) ) }
									</span>
								) }
							</button>
						</li>
					) ) }
				</ol>
			</aside>
		</div>
	);
}
