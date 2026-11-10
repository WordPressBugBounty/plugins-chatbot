/* global wp */
/**
 * Extension points for feature scripts (WPBot Pro or any other plugin).
 *
 * Feature scripts load after the app (PHP filter qcin/admin_scripts) and
 * register parts with wp.hooks filters. The app reads the filters every
 * time it draws, and draws again when a filter is added later.
 *
 *   wp.hooks.addFilter( 'qcin.admin.screens', 'my/plugin', ( screens ) => ( { ...screens, funnels: FunnelsScreen } ) );
 *
 * Filters (value → what to return):
 * - qcin.admin.screens            object id → Component (or { render, icon }). Props: settings, save, notify, query, ui.
 *                                 Add the menu entry itself with the PHP filter qcin/admin_screens.
 * - qcin.admin.settingsSections   array of { id, render: Component }. Props: settings, save (0.5.1: save( patch )
 *                                 saves core settings and updates the screen), notify, ui.
 * - qcin.admin.overviewCards      array of { id, render: Component }. Props: range, data, settings, ui.
 * - qcin.admin.recordingsFilters  array of { key, label, options: [ { value, label } ] }. The value is sent
 *                                 to GET /admin/sessions as ?key=value (read it in PHP qcin/session_query).
 * - qcin.admin.screenFilters      array of { key, label, options, default?, screens?: [ 'overview', 'heatmaps',
 *                                 'recordings' ] }. A select on those screens (all three by default). The value
 *                                 is sent with their requests as ?key=value (read it in PHP qcin/segment) and
 *                                 stays chosen when moving between screens. Used for saved segments.
 * - qcin.admin.recordingsTools    array of { id, render: Component } drawn under the Recordings filters (0.5.1).
 *                                 Props: filters (current values), query, ui. To show other visits, link to
 *                                 #/recordings?key=value (the screen reads its filters from the address).
 * - qcin.admin.recordingBadges    ( badges, session ) → array of { key, label, className?, title? }.
 * - qcin.admin.playerActions      array of { id, render: Component }. Props: session, notify, ui.
 * - qcin.admin.playerPanels       array of { id, render: Component }. Props: session, pageview, events, jump, notify, ui.
 * - qcin.admin.playerMarkers      ( markers, { events, session, pageview } ) → markers. A marker:
 *                                 { type, offset (ms from page start), label, text? }.
 * - qcin.admin.heatmapModes       array of { id, label, draw( doc, ctx ), render?: Component }. ctx: data, scale,
 *                                 device, range, urlId, filters, ui. render gets the same ctx and draws under the
 *                                 toolbar. Give elements you add to the page the class qcin-hm-ext: the core
 *                                 removes them before every draw. ui.heat.drawClicks / highlight draw like the core.
 *                                 Overview cards also get filters (0.5.0).
 */

export const hooks = wp.hooks;

/**
 * Apply an extension filter.
 *
 * @param {string} name  Filter name.
 * @param {*}      value Default value.
 * @param {*}      ctx   Extra argument.
 * @return {*} Filtered value.
 */
export function applyExt( name, value, ctx ) {
	if ( ! hooks || typeof hooks.applyFilters !== 'function' ) {
		return value;
	}
	try {
		const out = hooks.applyFilters( name, value, ctx );
		return out === undefined || out === null ? value : out;
	} catch ( e ) {
		// A broken extension must not break the core screens.
		if ( window.console ) {
			window.console.error( '[Site Insight] extension filter failed:', name, e );
		}
		return value;
	}
}

/**
 * Array filter with only valid items (objects with a render function or the needed keys).
 *
 * @param {string}   name  Filter name.
 * @param {*}        ctx   Extra argument.
 * @param {Function} valid Item check.
 * @return {Array} Items.
 */
export function extList( name, ctx, valid ) {
	const list = applyExt( name, [], ctx );
	return Array.isArray( list ) ? list.filter( ( it ) => it && typeof it === 'object' && ( ! valid || valid( it ) ) ) : [];
}

export const hasRender = ( it ) => typeof it.render === 'function' && !! it.id;

const RESERVED = [ 'range', 'device', 'url', 'url_id', 'frustration', 'page', 'per_page', 'search', 'f', 'r' ];
const SHARED_KEY = 'qcin_screen_filters';
let shared = null;

function sharedValues() {
	if ( shared === null ) {
		shared = {};
		try {
			const raw = window.sessionStorage.getItem( SHARED_KEY );
			shared = raw ? JSON.parse( raw ) || {} : {};
		} catch ( e ) {}
	}
	return shared;
}

/**
 * Screen filters (e.g. segments) that apply to one screen.
 *
 * @param {string} screen overview, heatmaps or recordings.
 * @return {Array} Filters.
 */
export function screenFilterList( screen ) {
	return extList(
		'qcin.admin.screenFilters',
		screen,
		( f ) =>
			typeof f.key === 'string' &&
			/^[a-z][a-z0-9_]*$/.test( f.key ) &&
			RESERVED.indexOf( f.key ) === -1 &&
			Array.isArray( f.options ) &&
			( ! Array.isArray( f.screens ) || f.screens.indexOf( screen ) !== -1 )
	);
}

/**
 * Starting values: from the address (#/screen?key=value), else the value
 * chosen on another screen, else the filter's default.
 *
 * @param {Array}  list  Filters.
 * @param {Object} query Route query.
 * @return {Object} key => value.
 */
export function screenFilterValues( list, query ) {
	const out = {};
	const prev = sharedValues();
	list.forEach( ( f ) => {
		const ok = ( v ) => v !== undefined && f.options.some( ( o ) => String( o.value ) === String( v ) );
		const q = query && query[ f.key ];
		if ( ok( q ) ) {
			// A value from a link also applies to the other screens.
			rememberScreenFilter( f.key, String( q ) );
		}
		out[ f.key ] = ok( q ) ? String( q ) : ok( prev[ f.key ] ) ? String( prev[ f.key ] ) : String( f.default || '' );
	} );
	return out;
}

/**
 * Remember a chosen value for the other screens.
 *
 * @param {string} key   Filter key.
 * @param {string} value Value.
 */
export function rememberScreenFilter( key, value ) {
	const s = sharedValues();
	s[ key ] = value;
	try {
		window.sessionStorage.setItem( SHARED_KEY, JSON.stringify( s ) );
	} catch ( e ) {}
}

/**
 * Selects for screen filters.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.list     Filters.
 * @param {Object}   props.values   key => value.
 * @param {Function} props.onChange ( key, value ).
 * @param {string}   props.prefix   Element id prefix.
 * @return {*} Elements.
 */
export function ScreenFilterSelects( { list, values, onChange, prefix } ) {
	return list.map( ( f ) => (
		<wp.element.Fragment key={ f.key }>
			<label className="screen-reader-text" htmlFor={ prefix + f.key }>{ f.label }</label>
			<select
				id={ prefix + f.key }
				className="qcin-screen-filter"
				value={ values[ f.key ] === undefined ? '' : values[ f.key ] }
				onChange={ ( e ) => {
					rememberScreenFilter( f.key, e.target.value );
					onChange( f.key, e.target.value );
				} }
			>
				{ f.options.map( ( o ) => (
					<option key={ String( o.value ) } value={ String( o.value ) }>
						{ o.label }
					</option>
				) ) }
			</select>
		</wp.element.Fragment>
	) );
}

/**
 * Re-render when a qcin.admin.* filter is added after the first draw.
 *
 * @param {Function} bump Called with no arguments.
 * @return {Function} Unsubscribe.
 */
export function onExtChange( bump ) {
	if ( ! hooks || typeof hooks.addAction !== 'function' ) {
		return () => {};
	}
	const ns = 'qcin/core/watch-' + Math.random().toString( 36 ).slice( 2 );
	const cb = ( hookName ) => {
		if ( typeof hookName === 'string' && hookName.indexOf( 'qcin.admin.' ) === 0 ) {
			bump();
		}
	};
	hooks.addAction( 'hookAdded', ns, cb );
	hooks.addAction( 'hookRemoved', ns, cb );
	return () => {
		hooks.removeAction( 'hookAdded', ns );
		hooks.removeAction( 'hookRemoved', ns );
	};
}

/**
 * Small error boundary-free guard: render an extension part, or nothing if it throws.
 *
 * @param {Function} Part  Component.
 * @param {Object}   props Props.
 * @return {*} Element or null.
 */
export function ExtPart( { part, props } ) {
	const Part = part;
	return <ExtBoundary><Part { ...props } /></ExtBoundary>;
}

class ExtBoundary extends wp.element.Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}
	static getDerivedStateFromError() {
		return { failed: true };
	}
	componentDidCatch( error ) {
		if ( window.console ) {
			window.console.error( '[Site Insight] extension part failed:', error );
		}
	}
	render() {
		return this.state.failed ? null : this.props.children;
	}
}
