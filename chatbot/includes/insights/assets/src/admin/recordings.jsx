/* global wp */
import { api, boot, go } from './api';
import { Icon } from './icons';
import { duration, relative, dateTime, bytes, path, flag } from './format';
import { Onboarding } from './onboarding';
import { SIGNALS } from './overview';
import { extList, hasRender, ExtPart, screenFilterList, screenFilterValues, ScreenFilterSelects } from './extend';

const { createElement, Fragment, useState, useEffect } = wp.element;
const { __, sprintf, _n } = wp.i18n;

function Stats( { status } ) {
	if ( ! status ) {
		return (
			<div className="qcin-stats">
				{ [ 0, 1, 2, 3 ].map( ( i ) => (
					<div key={ i } className="qcin-stat qcin-card is-loading" />
				) ) }
			</div>
		);
	}
	const items = [
		{ label: __( 'Recordings, last 24 hours', 'qcin-textdomain' ), value: status.last24h.sessions.toLocaleString() },
		{ label: __( 'Recordings, last 7 days', 'qcin-textdomain' ), value: status.last7d.sessions.toLocaleString() },
		{ label: __( 'Average recording length', 'qcin-textdomain' ), value: duration( status.last7d.avg_duration ) },
		{
			label: __( 'Storage used', 'qcin-textdomain' ),
			value: bytes( status.bytes ),
			/* translators: %s: storage cap */
			sub: sprintf( __( 'of %s', 'qcin-textdomain' ), bytes( status.capBytes ) ),
		},
	];
	return (
		<div className="qcin-stats">
			{ items.map( ( it ) => (
				<div key={ it.label } className="qcin-stat qcin-card">
					<div className="qcin-stat__value">
						{ it.value }
						{ it.sub && <span className="qcin-stat__sub"> { it.sub }</span> }
					</div>
					<div className="qcin-stat__label">{ it.label }</div>
				</div>
			) ) }
		</div>
	);
}

function DeviceIcon( { device } ) {
	const Fn = Icon[ device ] || Icon.desktop;
	return (
		<span className="qcin-device" title={ device }>
			<Fn size={ 16 } label={ device } />
		</span>
	);
}

export function Recordings( { settings, save, query } ) {
	const [ status, setStatus ] = useState( null );
	const initialFlag = query && SIGNALS[ query.f ] ? query.f : '';
	const initialRange = query && [ '1', '7', '30', '90', 'all' ].indexOf( query.r ) !== -1 ? query.r : '7';
	// Filters added by features; their values come from the URL (#/recordings?key=value) too.
	const extFilters = extList( 'qcin.admin.recordingsFilters', null, ( f ) => typeof f.key === 'string' && /^[a-z][a-z0-9_]*$/.test( f.key ) && Array.isArray( f.options ) && [ 'range', 'device', 'url', 'frustration', 'page', 'per_page' ].indexOf( f.key ) === -1 );
	const initialExt = {};
	extFilters.forEach( ( f ) => {
		const q = query && query[ f.key ];
		initialExt[ f.key ] = f.options.some( ( o ) => String( o.value ) === q ) ? q : String( f.default || '' );
	} );
	// Screen filters (e.g. saved segments) shared with the Overview and Heatmaps.
	const sfList = screenFilterList( 'recordings' ).filter( ( f ) => ! extFilters.some( ( e ) => e.key === f.key ) );
	const initialSf = screenFilterValues( sfList, query );
	const [ filters, setFilters ] = useState( { range: initialRange, device: '', url: '', frustration: initialFlag, ...initialExt, ...initialSf } );
	const [ urlInput, setUrlInput ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ list, setList ] = useState( null );
	const [ error, setError ] = useState( '' );
	const perPage = 20;

	useEffect( () => {
		if ( ! settings.enabled ) {
			return;
		}
		api.get( '/status' ).then( setStatus ).catch( () => {} );
	}, [ settings.enabled ] );

	useEffect( () => {
		if ( ! settings.enabled ) {
			return undefined;
		}
		let live = true;
		setList( null );
		setError( '' );
		api.get( '/sessions', { ...filters, page, per_page: perPage } )
			.then( ( r ) => live && setList( r ) )
			.catch( ( e ) => live && setError( ( e && e.message ) || __( 'Could not load recordings.', 'qcin-textdomain' ) ) );
		// Ignore an older answer that arrives after a newer request.
		return () => {
			live = false;
		};
	}, [ settings.enabled, filters, page ] );

	useEffect( () => {
		const t = setTimeout( () => {
			if ( urlInput !== filters.url ) {
				setPage( 1 );
				setFilters( { ...filters, url: urlInput } );
			}
		}, 400 );
		return () => clearTimeout( t );
	}, [ urlInput ] );

	if ( ! settings.enabled ) {
		return <Onboarding save={ save } />;
	}

	const setFilter = ( key, value ) => {
		setPage( 1 );
		setFilters( { ...filters, [ key ]: value } );
	};
	const pages = list ? Math.max( 1, Math.ceil( list.total / perPage ) ) : 1;

	return (
		<Fragment>
			<Stats status={ status } />
			<div className="qcin-card">
				<div className="qcin-card__head">
					<div>
						<h2 className="qcin-card__title">{ __( 'Recordings', 'qcin-textdomain' ) }</h2>
						<p className="qcin-card__sub">
							{ list
								? sprintf(
									/* translators: %s: number of recordings */
									_n( '%s recording', '%s recordings', list.total, 'qcin-textdomain' ),
									list.total.toLocaleString()
								)
								: ' ' }
						</p>
					</div>
					<div className="qcin-filters">
						<label className="screen-reader-text" htmlFor="qcin-f-range">{ __( 'Date range', 'qcin-textdomain' ) }</label>
						<select id="qcin-f-range" value={ filters.range } onChange={ ( e ) => setFilter( 'range', e.target.value ) }>
							<option value="1">{ __( 'Last 24 hours', 'qcin-textdomain' ) }</option>
							<option value="7">{ __( 'Last 7 days', 'qcin-textdomain' ) }</option>
							<option value="30">{ __( 'Last 30 days', 'qcin-textdomain' ) }</option>
							<option value="90">{ __( 'Last 90 days', 'qcin-textdomain' ) }</option>
							<option value="all">{ __( 'All time', 'qcin-textdomain' ) }</option>
						</select>
						<label className="screen-reader-text" htmlFor="qcin-f-device">{ __( 'Device', 'qcin-textdomain' ) }</label>
						<select id="qcin-f-device" value={ filters.device } onChange={ ( e ) => setFilter( 'device', e.target.value ) }>
							<option value="">{ __( 'All devices', 'qcin-textdomain' ) }</option>
							<option value="desktop">{ __( 'Desktop', 'qcin-textdomain' ) }</option>
							<option value="tablet">{ __( 'Tablet', 'qcin-textdomain' ) }</option>
							<option value="mobile">{ __( 'Mobile', 'qcin-textdomain' ) }</option>
						</select>
						<label className="screen-reader-text" htmlFor="qcin-f-frustration">{ __( 'Frustration', 'qcin-textdomain' ) }</label>
						<select id="qcin-f-frustration" value={ filters.frustration } onChange={ ( e ) => setFilter( 'frustration', e.target.value ) }>
							<option value="">{ __( 'Any behaviour', 'qcin-textdomain' ) }</option>
							<option value="any">{ __( 'With any frustration', 'qcin-textdomain' ) }</option>
							{ Object.keys( SIGNALS ).map( ( key ) => (
								<option key={ key } value={ key }>
									{ SIGNALS[ key ].label }
								</option>
							) ) }
						</select>
						{ extFilters.map( ( f ) => (
							<Fragment key={ f.key }>
								<label className="screen-reader-text" htmlFor={ 'qcin-f-' + f.key }>{ f.label }</label>
								<select id={ 'qcin-f-' + f.key } value={ filters[ f.key ] === undefined ? '' : filters[ f.key ] } onChange={ ( e ) => setFilter( f.key, e.target.value ) }>
									{ f.options.map( ( o ) => (
										<option key={ String( o.value ) } value={ String( o.value ) }>
											{ o.label }
										</option>
									) ) }
								</select>
							</Fragment>
						) ) }
						<ScreenFilterSelects list={ sfList } values={ filters } prefix="qcin-f-" onChange={ ( key, value ) => setFilter( key, value ) } />
						<label className="screen-reader-text" htmlFor="qcin-f-url">{ __( 'Visited page contains', 'qcin-textdomain' ) }</label>
						<input id="qcin-f-url" type="search" placeholder={ __( 'Visited page contains…', 'qcin-textdomain' ) } value={ urlInput } onChange={ ( e ) => setUrlInput( e.target.value ) } />
					</div>
				</div>

				{ extList( 'qcin.admin.recordingsTools', null, hasRender ).map( ( tool ) => (
					<ExtPart key={ tool.id } part={ tool.render } props={ { filters, query, ui: window.qcInsightsUI } } />
				) ) }

				{ error && <div className="qcin-notice qcin-notice--error">{ error }</div> }

				{ ! list && ! error && <div className="qcin-skeleton" aria-busy="true" /> }

				{ list && list.items.length === 0 && (
					<div className="qcin-empty">
						<Icon.recordings size={ 28 } />
						<p>
							<strong>{ __( 'No recordings yet', 'qcin-textdomain' ) }</strong>
						</p>
						<p>
							{ filters.frustration
								? __( 'No recorded visits match these filters. Frustration counts on the Overview include visits that were not recorded, which cannot be replayed.', 'qcin-textdomain' )
								: __( 'New visits appear here within a minute. Logged-in administrators and editors are not recorded by default, so open your site in a private window to try it.', 'qcin-textdomain' ) }
						</p>
					</div>
				) }

				{ list && list.items.length > 0 && (
					<table className="qcin-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Started', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Entry page', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Length', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Pages', 'qcin-textdomain' ) }</th>
								<th scope="col">{ __( 'Visitor', 'qcin-textdomain' ) }</th>
								<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'qcin-textdomain' ) }</span></th>
							</tr>
						</thead>
						<tbody>
							{ list.items.map( ( s ) => (
								<tr key={ s.id } className="qcin-row" onClick={ () => go( 'recordings/' + s.id ) }>
									<td>
										<div className="qcin-strong" title={ dateTime( s.started_at ) }>{ relative( s.started_at ) }</div>
										{ s.active && <span className="qcin-badge qcin-badge--live">{ __( 'Live', 'qcin-textdomain' ) }</span> }
										{ s.favourite && <span className="qcin-badge">{ __( 'Saved', 'qcin-textdomain' ) }</span> }
										{ extList( 'qcin.admin.recordingBadges', s, ( b ) => b.key && b.label ).map( ( b ) => (
											<span key={ 'x-' + b.key } className={ 'qcin-badge' + ( b.className ? ' ' + b.className : '' ) } title={ b.title || '' }>
												{ b.label }
											</span>
										) ) }
										{ ( s.flags || [] ).map( ( f ) => (
											<span key={ f } className={ 'qcin-badge qcin-badge--' + f } title={ SIGNALS[ f ] ? SIGNALS[ f ].help : '' }>
												{ SIGNALS[ f ] ? SIGNALS[ f ].short : f }
											</span>
										) ) }
									</td>
									<td className="qcin-ellipsis" data-label={ __( 'Entry page', 'qcin-textdomain' ) }>
										<div className="qcin-strong">{ s.entry_title || path( s.entry_url ) }</div>
										<div className="qcin-muted">{ path( s.entry_url ) }</div>
									</td>
									<td data-label={ __( 'Length', 'qcin-textdomain' ) }>{ duration( s.duration_s ) }</td>
									<td data-label={ __( 'Pages', 'qcin-textdomain' ) }>{ s.pageviews }</td>
									<td>
										<div className="qcin-visitor">
											<DeviceIcon device={ s.device } />
											<span>
												{ s.browser } · { s.os }
												{ s.country && <span title={ s.country }> { flag( s.country ) }</span> }
											</span>
										</div>
										<div className="qcin-muted">
											{ s.returning ? __( 'Returning', 'qcin-textdomain' ) : __( 'New visitor', 'qcin-textdomain' ) }
											{ s.referrer ? ' · ' + s.referrer : '' }
										</div>
									</td>
									<td className="qcin-right">
										<a className="qcin-btn qcin-btn--small" href={ '#/recordings/' + s.id } onClick={ ( e ) => e.stopPropagation() }>
											{ __( 'Play', 'qcin-textdomain' ) }
										</a>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }

				{ list && pages > 1 && (
					<div className="qcin-pager">
						<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
							{ __( 'Previous', 'qcin-textdomain' ) }
						</button>
						<span>
							{ sprintf(
								/* translators: 1: page, 2: pages */
								__( 'Page %1$d of %2$d', 'qcin-textdomain' ),
								page,
								pages
							) }
						</span>
						<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" disabled={ page >= pages } onClick={ () => setPage( page + 1 ) }>
							{ __( 'Next', 'qcin-textdomain' ) }
						</button>
					</div>
				) }
			</div>
		</Fragment>
	);
}
