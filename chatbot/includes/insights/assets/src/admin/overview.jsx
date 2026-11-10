/* global wp */
import { api } from './api';
import { duration, path } from './format';
import { Onboarding } from './onboarding';

import { extList, hasRender, ExtPart, screenFilterList, screenFilterValues, ScreenFilterSelects } from './extend';

const { createElement, Fragment, useState, useEffect, useRef } = wp.element;
const { __, sprintf, _n } = wp.i18n;

export const RANGES = [
	{ value: '1', label: __( 'Last 24 hours', 'qcin-textdomain' ) },
	{ value: '7', label: __( 'Last 7 days', 'qcin-textdomain' ) },
	{ value: '30', label: __( 'Last 30 days', 'qcin-textdomain' ) },
	{ value: '90', label: __( 'Last 90 days', 'qcin-textdomain' ) },
];

export const SIGNALS = {
	rage: { label: __( 'Rage clicks', 'qcin-textdomain' ), short: __( 'Rage click', 'qcin-textdomain' ), help: __( 'Several fast clicks in the same spot.', 'qcin-textdomain' ) },
	dead: { label: __( 'Dead clicks', 'qcin-textdomain' ), short: __( 'Dead click', 'qcin-textdomain' ), help: __( 'A click that changed nothing on the page.', 'qcin-textdomain' ) },
	quickback: { label: __( 'Quick backs', 'qcin-textdomain' ), short: __( 'Quick back', 'qcin-textdomain' ), help: __( 'Opened a page and went back within 5 seconds.', 'qcin-textdomain' ) },
	error: { label: __( 'JavaScript errors', 'qcin-textdomain' ), short: __( 'JS error', 'qcin-textdomain' ), help: __( 'A script error happened on the page.', 'qcin-textdomain' ) },
};
// Extra signal types from features (PHP filter qcin/signal_types).
( function addExtraSignals() {
	const extra = ( window.qcInsightsAdmin && window.qcInsightsAdmin.signalTypes ) || {};
	Object.keys( extra ).forEach( ( key ) => {
		if ( ! SIGNALS[ key ] && extra[ key ] && extra[ key ].label ) {
			SIGNALS[ key ] = { label: extra[ key ].label, short: extra[ key ].short || extra[ key ].label, help: extra[ key ].help || '' };
		}
	} );
} )();

function useWidth( ref ) {
	const [ width, setWidth ] = useState( 0 );
	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return undefined;
		}
		const update = () => setWidth( el.clientWidth );
		update();
		if ( typeof window.ResizeObserver === 'function' ) {
			const ro = new window.ResizeObserver( update );
			ro.observe( el );
			return () => ro.disconnect();
		}
		window.addEventListener( 'resize', update );
		return () => window.removeEventListener( 'resize', update );
	}, [] );
	return width;
}

function niceMax( v ) {
	if ( v <= 4 ) {
		return 4;
	}
	const p = Math.pow( 10, Math.floor( Math.log10( v ) ) );
	const n = v / p;
	return ( n <= 2 ? 2 : n <= 5 ? 5 : 10 ) * p;
}

function dayLabel( day, long ) {
	const d = new Date( day + 'T12:00:00Z' );
	return d.toLocaleDateString( undefined, long ? { weekday: 'short', month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric' } );
}

/**
 * Sessions per day: one series of thin bars with a hover tooltip.
 *
 * @param {Object} props        Props.
 * @param {Array}  props.series Days with sessions and frustrated counts.
 */
function SessionsChart( { series } ) {
	const ref = useRef( null );
	const width = useWidth( ref );
	const [ hover, setHover ] = useState( null );
	const H = 220;
	const pad = { top: 12, right: 8, bottom: 26, left: 40 };
	const max = niceMax( Math.max( 1, ...series.map( ( d ) => d.sessions ) ) );
	const w = Math.max( 0, width - pad.left - pad.right );
	const h = H - pad.top - pad.bottom;
	const band = series.length ? w / series.length : 0;
	const barW = Math.max( 2, Math.min( 28, band - 2 ) );
	const y = ( v ) => pad.top + h - ( v / max ) * h;
	const ticks = [ 0, max / 2, max ];
	const labelEvery = Math.max( 1, Math.ceil( series.length / Math.max( 1, Math.floor( w / 72 ) ) ) );

	return (
		<div className="qcin-chart" ref={ ref }>
			{ width > 0 && (
				<svg width={ width } height={ H } role="img" aria-label={ __( 'Sessions per day', 'qcin-textdomain' ) }>
					{ ticks.map( ( t ) => (
						<g key={ t }>
							<line x1={ pad.left } x2={ width - pad.right } y1={ y( t ) } y2={ y( t ) } className="qcin-chart__grid" />
							<text x={ pad.left - 8 } y={ y( t ) + 4 } textAnchor="end" className="qcin-chart__tick">
								{ Math.round( t ).toLocaleString() }
							</text>
						</g>
					) ) }
					{ series.map( ( d, i ) => {
						const x = pad.left + i * band + ( band - barW ) / 2;
						const top = y( d.sessions );
						const bh = pad.top + h - top;
						const r = Math.min( 4, barW / 2, bh );
						return (
							<g key={ d.day }>
								{ d.sessions > 0 && (
									<path
										className={ 'qcin-chart__bar' + ( hover === i ? ' is-hover' : '' ) }
										d={ `M${ x },${ pad.top + h }V${ top + r }Q${ x },${ top } ${ x + r },${ top }H${ x + barW - r }Q${ x + barW },${ top } ${ x + barW },${ top + r }V${ pad.top + h }Z` }
									/>
								) }
								{ i % labelEvery === 0 && (
									<text x={ pad.left + i * band + band / 2 } y={ H - 8 } textAnchor="middle" className="qcin-chart__tick">
										{ dayLabel( d.day ) }
									</text>
								) }
								<rect
									x={ pad.left + i * band }
									y={ pad.top }
									width={ band }
									height={ h }
									fill="transparent"
									onMouseEnter={ () => setHover( i ) }
									onMouseLeave={ () => setHover( null ) }
								/>
							</g>
						);
					} ) }
					<line x1={ pad.left } x2={ width - pad.right } y1={ pad.top + h } y2={ pad.top + h } className="qcin-chart__axis" />
				</svg>
			) }
			{ hover !== null && series[ hover ] && (
				<div
					className="qcin-chart__tip"
					style={ { left: Math.min( Math.max( pad.left + hover * band + band / 2, 80 ), width - 80 ), top: Math.max( 0, y( series[ hover ].sessions ) - 64 ) } }
				>
					<strong>{ dayLabel( series[ hover ].day, true ) }</strong>
					<span>
						{ sprintf(
							/* translators: %s: number of sessions */
							_n( '%s session', '%s sessions', series[ hover ].sessions, 'qcin-textdomain' ),
							series[ hover ].sessions.toLocaleString()
						) }
					</span>
					<span className="qcin-muted">
						{ sprintf(
							/* translators: %s: number of sessions */
							__( '%s with frustration', 'qcin-textdomain' ),
							series[ hover ].frustrated.toLocaleString()
						) }
					</span>
				</div>
			) }
			<table className="screen-reader-text">
				<caption>{ __( 'Sessions per day', 'qcin-textdomain' ) }</caption>
				<tbody>
					{ series.map( ( d ) => (
						<tr key={ d.day }>
							<th scope="row">{ d.day }</th>
							<td>{ d.sessions }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

export function Overview( { settings, save, query } ) {
	const [ range, setRange ] = useState( '7' );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	// Filters added by features (e.g. saved segments).
	const sfList = screenFilterList( 'overview' );
	const [ sf, setSf ] = useState( () => screenFilterValues( sfList, query ) );
	const sfKey = JSON.stringify( sf );

	useEffect( () => {
		if ( ! settings.enabled ) {
			return undefined;
		}
		let live = true;
		setData( null );
		setError( '' );
		api.get( '/overview', { range, ...sf } )
			.then( ( r ) => live && setData( r ) )
			.catch( ( e ) => live && setError( ( e && e.message ) || __( 'Could not load the overview.', 'qcin-textdomain' ) ) );
		// Ignore an older answer that arrives after a newer request.
		return () => {
			live = false;
		};
	}, [ settings.enabled, range, sfKey ] );

	if ( ! settings.enabled ) {
		return <Onboarding save={ save } />;
	}

	const k = data && data.kpis;
	const tiles = k
		? [
				{ label: __( 'Sessions', 'qcin-textdomain' ), value: k.sessions.toLocaleString() },
				{ label: __( 'Pages per session', 'qcin-textdomain' ), value: k.avg_pages.toLocaleString() },
				{ label: __( 'Average session length', 'qcin-textdomain' ), value: duration( k.avg_duration ) },
				{ label: __( 'Average scroll depth', 'qcin-textdomain' ), value: k.avg_scroll + '%' },
				{ label: __( 'Sessions with frustration', 'qcin-textdomain' ), value: k.frustrated_pct + '%', alert: k.frustrated_pct >= 20 },
		  ]
		: [];

	return (
		<Fragment>
			<div className="qcin-toolbar">
				<div>
					<h2 className="qcin-page-title">{ __( 'Overview', 'qcin-textdomain' ) }</h2>
					<p className="qcin-muted">{ __( 'All tracked visits, recorded or not.', 'qcin-textdomain' ) }</p>
				</div>
				<div className="qcin-filters">
					<label className="screen-reader-text" htmlFor="qcin-ov-range">{ __( 'Date range', 'qcin-textdomain' ) }</label>
					<select id="qcin-ov-range" value={ range } onChange={ ( e ) => setRange( e.target.value ) }>
						{ RANGES.map( ( r ) => (
							<option key={ r.value } value={ r.value }>
								{ r.label }
							</option>
						) ) }
					</select>
					<ScreenFilterSelects list={ sfList } values={ sf } prefix="qcin-ov-" onChange={ ( key, value ) => setSf( { ...sf, [ key ]: value } ) } />
				</div>
			</div>

			{ error && <div className="qcin-notice qcin-notice--error">{ error }</div> }

			<div className="qcin-stats qcin-stats--5">
				{ k
					? tiles.map( ( t ) => (
							<div key={ t.label } className={ 'qcin-stat qcin-card' + ( t.alert ? ' is-alert' : '' ) }>
								<div className="qcin-stat__value">{ t.value }</div>
								<div className="qcin-stat__label">{ t.label }</div>
							</div>
					  ) )
					: [ 0, 1, 2, 3, 4 ].map( ( i ) => <div key={ i } className="qcin-stat qcin-card is-loading" /> ) }
			</div>

			<div className="qcin-grid-main">
				<div className="qcin-card">
					<div className="qcin-card__head">
						<div>
							<h2 className="qcin-card__title">{ __( 'Sessions per day', 'qcin-textdomain' ) }</h2>
							<p className="qcin-card__sub">{ __( 'Days in UTC. Hover a bar for details.', 'qcin-textdomain' ) }</p>
						</div>
					</div>
					{ data ? <SessionsChart series={ data.series } /> : <div className="qcin-skeleton" aria-busy="true" /> }
				</div>

				<div className="qcin-card">
					<div className="qcin-card__head">
						<div>
							<h2 className="qcin-card__title">{ __( 'Frustration', 'qcin-textdomain' ) }</h2>
							<p className="qcin-card__sub">{ __( 'Sessions with each signal', 'qcin-textdomain' ) }</p>
						</div>
					</div>
					<ul className="qcin-signal-list">
						{ Object.keys( SIGNALS ).map( ( key ) => {
							const n = k ? k.flags[ key ] || 0 : null;
							const pct = k && k.sessions ? Math.round( ( 100 * n ) / k.sessions ) : 0;
							return (
								<li key={ key }>
									<span className={ 'qcin-sig qcin-sig--' + key } aria-hidden="true" />
									<span className="qcin-signal-list__text">
										<strong>{ SIGNALS[ key ].label }</strong>
										<span className="qcin-muted">{ SIGNALS[ key ].help }</span>
									</span>
									<span className="qcin-signal-list__value">
										{ null === n ? '–' : n.toLocaleString() }
										{ n > 0 && <span className="qcin-muted"> ({ pct }%)</span> }
									</span>
									{ n > 0 ? (
										<a className="qcin-btn qcin-btn--small qcin-btn--ghost" href={ '#/recordings?f=' + key + '&r=' + range }>
											{ __( 'Watch', 'qcin-textdomain' ) }
										</a>
									) : (
										<span className="qcin-signal-list__spacer" />
									) }
								</li>
							);
						} ) }
					</ul>
				</div>
			</div>

			<div className="qcin-card">
				<div className="qcin-card__head">
					<div>
						<h2 className="qcin-card__title">{ __( 'Top pages', 'qcin-textdomain' ) }</h2>
					</div>
				</div>
				{ ! data && <div className="qcin-skeleton" aria-busy="true" /> }
				{ data && data.pages.length === 0 && (
					<div className="qcin-empty">
						<p>{ __( 'No visits in this period yet. Open your site in a private window to try it.', 'qcin-textdomain' ) }</p>
					</div>
				) }
				{ data && data.pages.length > 0 && (
					<table className="qcin-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Page', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Views', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Average time', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Average scroll', 'qcin-textdomain' ) }</th>
								<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'qcin-textdomain' ) }</span></th>
							</tr>
						</thead>
						<tbody>
							{ data.pages.map( ( p ) => (
								<tr key={ p.url_id }>
									<td className="qcin-ellipsis">
										<div className="qcin-strong">{ p.title || path( p.url ) }</div>
										<div className="qcin-muted">{ path( p.url ) }</div>
									</td>
									<td data-label={ __( 'Views', 'qcin-textdomain' ) }>{ p.views.toLocaleString() }</td>
									<td data-label={ __( 'Average time', 'qcin-textdomain' ) }>{ duration( p.avg_time_s ) }</td>
									<td data-label={ __( 'Average scroll', 'qcin-textdomain' ) }>
										<span className="qcin-meter" aria-hidden="true">
											<span style={ { width: p.avg_scroll + '%' } } />
										</span>
										{ p.avg_scroll }%
									</td>
									<td className="qcin-right">
										<a className="qcin-btn qcin-btn--small" href={ '#/heatmaps?url=' + p.url_id }>
											{ __( 'Heatmap', 'qcin-textdomain' ) }
										</a>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

			{ data && data.errors.length > 0 && (
				<div className="qcin-card">
					<div className="qcin-card__head">
						<div>
							<h2 className="qcin-card__title">{ __( 'Most common JavaScript errors', 'qcin-textdomain' ) }</h2>
							<p className="qcin-card__sub">{ __( 'Errors in visitors’ browsers can break buttons and forms.', 'qcin-textdomain' ) }</p>
						</div>
						<a className="qcin-btn qcin-btn--small qcin-btn--ghost" href={ '#/recordings?f=error&r=' + range }>
							{ __( 'Watch sessions', 'qcin-textdomain' ) }
						</a>
					</div>
					<ul className="qcin-errors">
						{ data.errors.map( ( e, i ) => (
							<li key={ i }>
								<code>{ e.message }</code>
								<span className="qcin-muted">
									{ e.source ? path( e.source ) + ( e.line ? ':' + e.line : '' ) + ' · ' : '' }
									{ sprintf(
										/* translators: %s: number of times */
										_n( '%s time', '%s times', e.count, 'qcin-textdomain' ),
										e.count.toLocaleString()
									) +
										' · ' +
										sprintf(
											/* translators: %s: number of sessions */
											_n( '%s session', '%s sessions', e.sessions, 'qcin-textdomain' ),
											e.sessions.toLocaleString()
										) }
								</span>
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ extList( 'qcin.admin.overviewCards', null, hasRender ).map( ( card ) => (
				<ExtPart key={ card.id + sfKey } part={ card.render } props={ { range, data, settings, filters: sf, ui: window.qcInsightsUI } } />
			) ) }
		</Fragment>
	);
}
