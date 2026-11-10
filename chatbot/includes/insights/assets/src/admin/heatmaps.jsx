/* global wp */
import rrwebPlayer from 'rrweb-player';
import { api } from './api';
import { Icon } from './icons';
import { path } from './format';
import { Onboarding } from './onboarding';
import { RANGES } from './overview';
import { prepare, clear, drawClicks, drawScroll, highlight, reach } from './heatmap-render';
import { extList, ExtPart, screenFilterList, screenFilterValues, ScreenFilterSelects } from './extend';

const { createElement, Fragment, useState, useEffect, useRef, useCallback } = wp.element;
const { __, _n, sprintf } = wp.i18n;

const DEVICES = [
	{ id: 'desktop', label: __( 'Desktop', 'qcin-textdomain' ), w: 1366, h: 768, radius: 26 },
	{ id: 'tablet', label: __( 'Tablet', 'qcin-textdomain' ), w: 768, h: 1024, radius: 22 },
	{ id: 'mobile', label: __( 'Mobile', 'qcin-textdomain' ), w: 390, h: 844, radius: 18 },
];

function frameUrl( url ) {
	return url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'qcin_heatmap=1';
}

function shortSelector( selector ) {
	const parts = selector.split( '>' );
	return parts.length > 2 ? '…>' + parts.slice( -2 ).join( '>' ) : selector;
}

function sleep( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

/**
 * Rebuild a recorded page (a few seconds in) with a hidden player and
 * return its HTML, for pages the live frame cannot show (login, cart).
 *
 * @param {Object} rec Recording reference (session, pageview, width, height).
 * @return {Promise<string>} HTML.
 */
async function snapshotHtml( rec ) {
	const res = await api.get( '/sessions/' + rec.session + '/pageviews/' + rec.pageview + '/events' );
	const events = res.events || [];
	if ( events.length < 2 ) {
		throw new Error( 'empty' );
	}
	const host = document.createElement( 'div' );
	host.style.cssText = 'position:fixed;left:-20000px;top:0;width:' + ( rec.width || 1280 ) + 'px;height:' + ( rec.height || 800 ) + 'px;overflow:hidden;';
	document.body.appendChild( host );
	let player;
	try {
		player = new rrwebPlayer( {
			target: host,
			props: { events, width: rec.width || 1280, height: rec.height || 800, autoPlay: false, showController: false, skipInactive: false },
		} );
		const total = events[ events.length - 1 ].timestamp - events[ 0 ].timestamp;
		player.goto( Math.min( 3000, Math.max( 0, total ) ), false );
		await sleep( 500 );
		const doc = player.getReplayer().iframe.contentDocument;
		return '<!DOCTYPE html>' + doc.documentElement.outerHTML;
	} finally {
		try {
			if ( player && player.$destroy ) {
				player.$destroy();
			}
		} catch ( e ) {}
		host.remove();
	}
}

export function Heatmaps( { settings, save, query } ) {
	const [ range, setRange ] = useState( '30' );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ mode, setMode ] = useState( 'clicks' );
	const [ source, setSource ] = useState( 'live' );
	const [ search, setSearch ] = useState( '' );
	const [ pages, setPages ] = useState( null );
	const [ urlId, setUrlId ] = useState( query && query.url ? parseInt( query.url, 10 ) : 0 );
	// Filters added by features (e.g. saved segments).
	const sfList = screenFilterList( 'heatmaps' );
	const [ sf, setSf ] = useState( () => screenFilterValues( sfList, query ) );
	const sfKey = JSON.stringify( sf );
	const [ data, setData ] = useState( null );
	const [ frame, setFrame ] = useState( { state: 'idle' } );
	const [ drawn, setDrawn ] = useState( { found: [], missingClicks: 0 } );
	const [ hover, setHover ] = useState( '' );
	const [ snapshot, setSnapshot ] = useState( '' );
	const boxRef = useRef( null );
	const iframeRef = useRef( null );
	const [ boxWidth, setBoxWidth ] = useState( 0 );

	const dev = DEVICES.find( ( d ) => d.id === device ) || DEVICES[ 0 ];
	const scale = boxWidth ? Math.min( 1, boxWidth / dev.w ) : 0.5;
	// Heatmap types added by features (e.g. area or attention maps).
	const extModes = extList( 'qcin.admin.heatmapModes', null, ( m ) => m.id && m.label && typeof m.draw === 'function' && m.id !== 'clicks' && m.id !== 'scroll' );
	const extMode = extModes.find( ( m ) => m.id === mode ) || null;
	const extCtx = { data, scale, device, range, urlId, filters: sf, ui: window.qcInsightsUI };
	const extRef = useRef( null );
	extRef.current = { mode: extMode, ctx: extCtx };

	useEffect( () => {
		const el = boxRef.current;
		if ( ! el ) {
			return undefined;
		}
		const update = () => setBoxWidth( el.clientWidth );
		update();
		const ro = typeof window.ResizeObserver === 'function' ? new window.ResizeObserver( update ) : null;
		if ( ro ) {
			ro.observe( el );
		}
		return () => ro && ro.disconnect();
	}, [ settings.enabled ] );

	// Pages with data.
	useEffect( () => {
		if ( ! settings.enabled ) {
			return undefined;
		}
		let live = true;
		const t = setTimeout( () => {
			api.get( '/heatmaps/pages', { range, search, ...sf } )
				.then( ( r ) => {
					if ( ! live ) {
						return;
					}
					setPages( r.items );
					if ( r.items.length && ( ! urlId || ( search && ! r.items.some( ( p ) => p.url_id === urlId ) ) ) ) {
						setUrlId( r.items[ 0 ].url_id );
					}
				} )
				.catch( () => live && setPages( [] ) );
		}, search ? 300 : 0 );
		return () => {
			live = false;
			clearTimeout( t );
		};
	}, [ settings.enabled, range, search, sfKey ] );

	// Heatmap data for the selected page and device.
	useEffect( () => {
		if ( ! urlId ) {
			return undefined;
		}
		let live = true;
		setData( null );
		api.get( '/heatmaps', { url_id: urlId, device, range, ...sf } )
			.then( ( r ) => live && setData( r ) )
			.catch( () => live && setData( { error: true } ) );
		return () => {
			live = false;
		};
	}, [ urlId, device, range, sfKey ] );

	// Recorded snapshot when that source is chosen.
	useEffect( () => {
		if ( source !== 'recording' || ! data || ! data.recording ) {
			setSnapshot( '' );
			return;
		}
		let cancelled = false;
		setFrame( { state: 'loading' } );
		snapshotHtml( data.recording )
			.then( ( html ) => ! cancelled && setSnapshot( html ) )
			.catch( () => ! cancelled && setFrame( { state: 'error', reason: 'snapshot' } ) );
		return () => {
			cancelled = true;
		};
	}, [ source, data && data.recording && data.recording.pageview ] );

	const doc = () => {
		try {
			const f = iframeRef.current;
			return f && f.contentDocument && f.contentDocument.documentElement ? f.contentDocument : null;
		} catch ( e ) {
			return null;
		}
	};

	const render = useCallback( () => {
		const d = doc();
		if ( ! d || ! data || data.error ) {
			return;
		}
		prepare( d );
		// Layers drawn by feature modes carry the class qcin-hm-ext; remove them before every draw.
		Array.prototype.slice.call( d.querySelectorAll( '.qcin-hm-ext' ) ).forEach( ( n ) => n.remove() );
		const ext = extRef.current;
		if ( ext && ext.mode ) {
			// Start from the plain page: no core heat layer under a feature mode.
			clear( d );
			try {
				ext.mode.draw( d, ext.ctx );
			} catch ( e ) {
				if ( window.console ) {
					window.console.error( '[Site Insight] heatmap mode failed:', e );
				}
			}
			setDrawn( { found: [], missingClicks: 0 } );
		} else if ( mode === 'scroll' ) {
			drawScroll( d, data.scroll, scale, {
				/* translators: %d: percent of visitors */
				reached: ( pct ) => sprintf( __( '%d%% of visitors reached this point', 'qcin-textdomain' ), pct ),
				fold: __( 'Average fold', 'qcin-textdomain' ),
			} );
			setDrawn( { found: [], missingClicks: 0 } );
		} else {
			setDrawn( drawClicks( d, data.clicks.elements, Math.min( dev.radius * 1.8, Math.max( dev.radius, dev.radius / scale ) ) ) );
		}
	}, [ data, mode, dev.radius, scale ] );

	const onLoad = () => {
		const d = doc();
		const f = iframeRef.current;
		const blank = ! f || ( source === 'live' && ! f.getAttribute( 'src' ) ) || ( source === 'recording' && ! snapshot );
		if ( blank ) {
			return;
		}
		if ( ! d || ( source === 'live' && d.location && d.location.href === 'about:blank' ) ) {
			setFrame( { state: 'error', reason: 'blocked' } );
			return;
		}
		setFrame( { state: 'ready', at: Date.now() } );
	};

	// Draw after the frame is ready (and again once late content settles).
	useEffect( () => {
		if ( frame.state !== 'ready' ) {
			return undefined;
		}
		const t1 = setTimeout( render, 300 );
		const t2 = setTimeout( render, 1500 );
		return () => {
			clearTimeout( t1 );
			clearTimeout( t2 );
		};
	}, [ frame, render ] );

	// Reset the frame when the page, device or source changes.
	useEffect( () => {
		setFrame( { state: urlId ? 'loading' : 'idle' } );
		setHover( '' );
		const d = doc();
		if ( d ) {
			clear( d );
		}
	}, [ urlId, device, source ] );

	useEffect( () => {
		highlight( doc(), hover );
	}, [ hover ] );

	if ( ! settings.enabled ) {
		return <Onboarding save={ save } />;
	}

	const page = data && data.url;
	const liveSrc = source === 'live' && data && data.url ? frameUrl( data.url.url ) : '';
	const elements = data && data.clicks ? data.clicks.elements : [];
	const total = data && data.clicks ? data.clicks.total : 0;
	const reachMap = data && data.scroll ? reach( data.scroll ) : null;

	return (
		<Fragment>
			<div className="qcin-toolbar">
				<div>
					<h2 className="qcin-page-title">{ __( 'Heatmaps', 'qcin-textdomain' ) }</h2>
					<p className="qcin-muted">{ __( 'Where visitors click and how far they scroll, from all tracked visits.', 'qcin-textdomain' ) }</p>
				</div>
				<div className="qcin-filters">
					<label className="screen-reader-text" htmlFor="qcin-hm-range">{ __( 'Date range', 'qcin-textdomain' ) }</label>
					<select id="qcin-hm-range" value={ range } onChange={ ( e ) => setRange( e.target.value ) }>
						{ RANGES.map( ( r ) => (
							<option key={ r.value } value={ r.value }>
								{ r.label }
							</option>
						) ) }
					</select>
					<ScreenFilterSelects list={ sfList } values={ sf } prefix="qcin-hm-" onChange={ ( key, value ) => setSf( { ...sf, [ key ]: value } ) } />
				</div>
			</div>

			<div className="qcin-hm-main">
					<div className="qcin-card">
						<div className="qcin-hm-picker">
							<label htmlFor="qcin-hm-page">{ __( 'Page', 'qcin-textdomain' ) }</label>
							<select id="qcin-hm-page" value={ urlId || '' } onChange={ ( e ) => setUrlId( parseInt( e.target.value, 10 ) || 0 ) } disabled={ ! pages || ! pages.length }>
								{ ! pages && <option value="">{ __( 'Loading pages…', 'qcin-textdomain' ) }</option> }
								{ pages && ! pages.length && <option value="">{ __( 'No pages with visits in this period', 'qcin-textdomain' ) }</option> }
								{ ( pages || [] ).map( ( p ) => (
									<option key={ p.url_id } value={ p.url_id }>
										{ ( p.title || path( p.url ) ) + ' — ' + path( p.url ) + ' (' + sprintf(
											/* translators: %s: number of views */
											__( '%s views', 'qcin-textdomain' ),
											p.views.toLocaleString()
										) + ')' }
									</option>
								) ) }
								{ pages && urlId > 0 && page && ! pages.some( ( p ) => p.url_id === urlId ) && <option value={ urlId }>{ page.title || path( page.url ) }</option> }
							</select>
							<label className="screen-reader-text" htmlFor="qcin-hm-search">{ __( 'Find a page', 'qcin-textdomain' ) }</label>
							<input id="qcin-hm-search" type="search" placeholder={ __( 'Find a page…', 'qcin-textdomain' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } />
						</div>
						<div className="qcin-card__head">
							<div>
								<h2 className="qcin-card__title">{ page ? page.title || path( page.url ) : __( 'Choose a page', 'qcin-textdomain' ) }</h2>
								{ page && <p className="qcin-card__sub">{ path( page.url ) }</p> }
							</div>
							<div className="qcin-actions">
								<div className="qcin-segmented" role="group" aria-label={ __( 'Heatmap type', 'qcin-textdomain' ) }>
									<button type="button" aria-pressed={ mode === 'clicks' } className={ mode === 'clicks' ? 'is-on' : '' } onClick={ () => setMode( 'clicks' ) }>
										{ __( 'Clicks', 'qcin-textdomain' ) }
									</button>
									<button type="button" aria-pressed={ mode === 'scroll' } className={ mode === 'scroll' ? 'is-on' : '' } onClick={ () => setMode( 'scroll' ) }>
										{ __( 'Scroll', 'qcin-textdomain' ) }
									</button>
									{ extModes.map( ( m ) => (
										<button key={ m.id } type="button" aria-pressed={ mode === m.id } className={ mode === m.id ? 'is-on' : '' } onClick={ () => setMode( m.id ) }>
											{ m.label }
										</button>
									) ) }
								</div>
								<div className="qcin-segmented" role="group" aria-label={ __( 'Device', 'qcin-textdomain' ) }>
									{ DEVICES.map( ( d ) => {
										const IconFn = Icon[ d.id ];
										return (
											<button key={ d.id } type="button" aria-pressed={ device === d.id } className={ device === d.id ? 'is-on' : '' } onClick={ () => setDevice( d.id ) } title={ d.label }>
												<IconFn size={ 16 } />
												<span className="qcin-segmented__label">{ d.label }</span>
											</button>
										);
									} ) }
								</div>
							</div>
						</div>

						{ data && ! data.error && extMode && typeof extMode.render === 'function' && (
							<ExtPart key={ extMode.id } part={ extMode.render } props={ extCtx } />
						) }
						{ data && ! data.error && ! extMode && (
							<div className="qcin-hm-summary">
								{ mode === 'clicks' ? (
									<Fragment>
										<span>
											<strong>{ total.toLocaleString() }</strong> { _n( 'click', 'clicks', total, 'qcin-textdomain' ) }
										</span>
										<span>
											<strong>{ data.scroll.views.toLocaleString() }</strong> { _n( 'page view', 'page views', data.scroll.views, 'qcin-textdomain' ) }
										</span>
										{ drawn.missingClicks > 0 && frame.state === 'ready' && (
											<span className="qcin-muted">
												{ sprintf(
													/* translators: %s: number of clicks */
													__( '%s clicks on elements no longer on the page', 'qcin-textdomain' ),
													drawn.missingClicks.toLocaleString()
												) }
											</span>
										) }
									</Fragment>
								) : (
									<Fragment>
										<span>
											<strong>{ data.scroll.views.toLocaleString() }</strong> { _n( 'page view', 'page views', data.scroll.views, 'qcin-textdomain' ) }
										</span>
										{ [ 25, 50, 75, 100 ].map( ( d ) => (
											<span key={ d }>
												{ sprintf(
													/* translators: 1: page depth percent, 2: percent of visitors */
													__( '%1$d%% down the page: %2$d%% of visitors', 'qcin-textdomain' ),
													d,
													Math.round( reachMap[ d ] * 100 )
												) }
											</span>
										) ) }
										{ data.scroll.fold > 0 && (
											<span className="qcin-muted">
												{ sprintf(
													/* translators: %d: percent of the page */
													__( 'Average fold at %d%%', 'qcin-textdomain' ),
													data.scroll.fold
												) }
											</span>
										) }
									</Fragment>
								) }
								<span className="qcin-hm-source">
									<label htmlFor="qcin-hm-source" className="qcin-muted">{ __( 'Show on', 'qcin-textdomain' ) }</label>
									<select id="qcin-hm-source" value={ source } onChange={ ( e ) => setSource( e.target.value ) }>
										<option value="live">{ __( 'Live page', 'qcin-textdomain' ) }</option>
										<option value="recording" disabled={ ! data.recording }>
											{ data.recording ? __( 'Recorded snapshot', 'qcin-textdomain' ) : __( 'Recorded snapshot (none on this device)', 'qcin-textdomain' ) }
										</option>
									</select>
								</span>
							</div>
						) }

						{ data && data.error && <div className="qcin-notice qcin-notice--error">{ __( 'Could not load heatmap data.', 'qcin-textdomain' ) }</div> }
						{ data && data.segment && data.segment.clicksFrom && mode === 'clicks' && (
							<div className="qcin-notice qcin-hm-segment-note">
								{ sprintf(
									/* translators: %s: date */
									__( 'Clicks for this segment are counted from %s (detailed clicks are kept for a limited time). Scroll depth covers the whole range.', 'qcin-textdomain' ),
									data.segment.clicksFrom
								) }
							</div>
						) }
						{ frame.state === 'error' && (
							<div className="qcin-notice qcin-notice--error">
								{ frame.reason === 'blocked'
									? __( 'This page could not be shown in a frame (it may block framing). Try a recorded snapshot instead.', 'qcin-textdomain' )
									: __( 'The recorded snapshot could not be rebuilt.', 'qcin-textdomain' ) }
							</div>
						) }
						{ data && ! data.error && mode === 'clicks' && total === 0 && (
							<div className="qcin-notice">
								{ sprintf(
									/* translators: %s: device name */
									__( 'No clicks recorded on %s for this page in this period yet.', 'qcin-textdomain' ),
									dev.label.toLowerCase()
								) }
							</div>
						) }

						<div className="qcin-hm-stage" ref={ boxRef }>
							{ urlId > 0 && (
								<div className="qcin-hm-device" style={ { width: dev.w * scale, height: dev.h * scale } }>
									{ frame.state === 'loading' && <div className="qcin-hm-loading">{ __( 'Loading page…', 'qcin-textdomain' ) }</div> }
									{ source === 'live' ? (
										<iframe
											key={ 'live-' + urlId + '-' + device }
											ref={ iframeRef }
											title={ __( 'Page preview with heatmap', 'qcin-textdomain' ) }
											src={ liveSrc || undefined }
											width={ dev.w }
											height={ dev.h }
											style={ { transform: 'scale(' + scale + ')' } }
											onLoad={ onLoad }
										/>
									) : (
										<iframe
											key={ 'rec-' + urlId + '-' + device + '-' + snapshot.length }
											ref={ iframeRef }
											title={ __( 'Recorded page with heatmap', 'qcin-textdomain' ) }
											sandbox="allow-same-origin"
											srcDoc={ snapshot || undefined }
											width={ dev.w }
											height={ dev.h }
											style={ { transform: 'scale(' + scale + ')' } }
											onLoad={ onLoad }
										/>
									) }
								</div>
							) }
						</div>
						<div className="qcin-hm-foot">
							<span className="qcin-muted">
								{ extMode
									? extMode.help || ''
									: mode === 'clicks'
										? __( 'Colour shows how often each spot was clicked, from light yellow (few) to dark red (most).', 'qcin-textdomain' )
										: __( 'Darker areas were seen by fewer visitors. Scroll inside the preview to see the whole page.', 'qcin-textdomain' ) }
							</span>
							<button type="button" className="qcin-btn qcin-btn--small qcin-btn--ghost" onClick={ render } disabled={ frame.state !== 'ready' }>
								<Icon.refresh size={ 14 } /> { __( 'Redraw', 'qcin-textdomain' ) }
							</button>
						</div>
					</div>

					{ mode === 'clicks' && elements.length > 0 && (
						<div className="qcin-card">
							<div className="qcin-card__head">
								<div>
									<h2 className="qcin-card__title">{ __( 'Most clicked elements', 'qcin-textdomain' ) }</h2>
									<p className="qcin-card__sub">{ __( 'Hover a row to find the element on the page.', 'qcin-textdomain' ) }</p>
								</div>
							</div>
							<table className="qcin-table qcin-table--compact">
								<thead>
									<tr>
										<th scope="col">{ __( 'Element', 'qcin-textdomain' ) }</th>
										<th scope="col">{ __( 'Clicks', 'qcin-textdomain' ) }</th>
										<th scope="col">{ __( 'Share', 'qcin-textdomain' ) }</th>
										<th scope="col">{ __( 'Frustration', 'qcin-textdomain' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ elements.slice( 0, 15 ).map( ( el ) => {
										const missing = frame.state === 'ready' && mode === 'clicks' && drawn.found.indexOf( el.selector ) === -1;
										return (
											<tr key={ el.id } onMouseEnter={ () => setHover( missing ? '' : el.selector ) } onMouseLeave={ () => setHover( '' ) } className={ hover === el.selector ? 'is-hover' : '' }>
												<td className="qcin-ellipsis" title={ el.selector }>
													<code>{ shortSelector( el.selector ) }</code>
													{ missing && <span className="qcin-badge">{ __( 'Not on the page now', 'qcin-textdomain' ) }</span> }
												</td>
												<td data-label={ __( 'Clicks', 'qcin-textdomain' ) }>{ el.clicks.toLocaleString() }</td>
												<td data-label={ __( 'Share', 'qcin-textdomain' ) }>{ total ? Math.round( ( 100 * el.clicks ) / total ) : 0 }%</td>
												<td>
													{ el.rage > 0 && (
														<span className="qcin-badge qcin-badge--rage">
															{ sprintf(
																/* translators: %d: number of rage clicks */
																__( '%d rage', 'qcin-textdomain' ),
																el.rage
															) }
														</span>
													) }
													{ el.dead > 0 && (
														<span className="qcin-badge qcin-badge--dead">
															{ sprintf(
																/* translators: %d: number of dead clicks */
																__( '%d dead', 'qcin-textdomain' ),
																el.dead
															) }
														</span>
													) }
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
						</div>
					) }
			</div>
		</Fragment>
	);
}
