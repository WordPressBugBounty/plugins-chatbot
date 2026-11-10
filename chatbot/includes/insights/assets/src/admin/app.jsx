/* global wp */
import './style.css';
import { boot, api, go } from './api';
import { Icon } from './icons';
import { Toggle, NumberField, TextArea, Section } from './fields';
import * as format from './format';
import { SIGNALS, RANGES } from './overview';
import { applyExt, onExtChange, ExtPart } from './extend';
import { Recordings } from './recordings';
import { Player } from './player';
import { SettingsScreen } from './settings';
import { Overview } from './overview';
import { Heatmaps } from './heatmaps';
import { drawClicks, highlight } from './heatmap-render';

const { createElement, useState, useEffect, render } = wp.element;
const { __, sprintf } = wp.i18n;

/**
 * Shared UI for feature scripts, so they reuse the core look instead of
 * shipping their own copies. See extend.js for the wp.hooks filters.
 */
export const ui = {
	version: boot.version,
	api,
	boot,
	go,
	Icon,
	Section,
	Toggle,
	NumberField,
	TextArea,
	format,
	SIGNALS,
	RANGES,
	ExtPart,
	// Heatmap drawing helpers for feature heatmap modes (0.5.0).
	heat: { drawClicks, highlight },
};
window.qcInsightsUI = ui;

/**
 * Feature screens: id => { render, icon }.
 *
 * @return {Object} Screens.
 */
function extScreens() {
	const raw = applyExt( 'qcin.admin.screens', {} );
	const out = {};
	Object.keys( raw && typeof raw === 'object' ? raw : {} ).forEach( ( id ) => {
		const v = raw[ id ];
		if ( typeof v === 'function' ) {
			out[ id ] = { render: v };
		} else if ( v && typeof v.render === 'function' ) {
			out[ id ] = v;
		}
	} );
	return out;
}

function parseHash() {
	const raw = ( window.location.hash || '' ).replace( /^#\/?/, '' );
	const [ h, qs ] = raw.split( '?' );
	const query = {};
	( qs || '' ).split( '&' ).forEach( ( pair ) => {
		const [ k, v ] = pair.split( '=' );
		if ( k ) {
			query[ decodeURIComponent( k ) ] = decodeURIComponent( v || '' );
		}
	} );
	const parts = h.split( '/' ).filter( Boolean );
	if ( parts[ 0 ] === 'recordings' && parts[ 1 ] ) {
		return { screen: 'player', id: parts[ 1 ], query };
	}
	return { screen: parts[ 0 ] || 'overview', query };
}

function Toast( { toast } ) {
	if ( ! toast ) {
		return null;
	}
	return (
		<div className={ 'qcin-toast qcin-toast--' + ( toast.type || 'info' ) } role="status">
			{ toast.text }
		</div>
	);
}

function Sidebar( { route, settings, ext } ) {
	const screens = boot.screens || [];
	const active = route.screen === 'player' ? 'recordings' : route.screen;
	return (
		<aside className="qcin-sidebar qcin-card">
			<div className="qcin-sidebar__title">{ boot.title || __( 'Insights', 'qcin-textdomain' ) }</div>
			<nav aria-label={ sprintf(
				/* translators: %s: menu title, e.g. "Site Insight". */
				__( '%s sections', 'qcin-textdomain' ),
				boot.title || __( 'Insights', 'qcin-textdomain' )
			) }>
				{ screens.map( ( s ) => {
					const custom = ext[ s.id ] && typeof ext[ s.id ].icon === 'function' ? ext[ s.id ].icon : null;
					const IconFn = custom || Icon[ s.icon ] || Icon[ s.id ] || Icon.page;
					return (
						<a key={ s.id } href={ '#/' + s.id } className={ 'qcin-nav' + ( active === s.id ? ' is-active' : '' ) } aria-current={ active === s.id ? 'page' : undefined }>
							<IconFn size={ 18 } />
							<span>{ s.label }</span>
						</a>
					);
				} ) }
			</nav>
			<div className={ 'qcin-recording-state' + ( settings.enabled ? ' is-on' : '' ) }>
				<span className="qcin-dot" aria-hidden="true" />
				{ settings.enabled ? __( 'Recording is on', 'qcin-textdomain' ) : __( 'Recording is off', 'qcin-textdomain' ) }
			</div>
		</aside>
	);
}

function App() {
	const [ route, setRoute ] = useState( parseHash() );
	const [ settings, setSettings ] = useState( boot.settings || {} );
	const [ toast, setToast ] = useState( null );
	const [ , setExtVersion ] = useState( 0 );

	// Draw again when a feature script registers a part after the first draw.
	useEffect( () => onExtChange( () => setExtVersion( ( n ) => n + 1 ) ), [] );

	useEffect( () => {
		const onHash = () => setRoute( parseHash() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	useEffect( () => {
		if ( ! toast ) {
			return undefined;
		}
		const t = setTimeout( () => setToast( null ), 3500 );
		return () => clearTimeout( t );
	}, [ toast ] );

	const notify = ( text, type ) => setToast( { text, type } );

	const saveSettings = ( patch ) =>
		api
			.post( '/settings', patch )
			.then( ( saved ) => {
				setSettings( saved );
				notify( __( 'Settings saved.', 'qcin-textdomain' ), 'success' );
				return saved;
			} )
			.catch( ( e ) => {
				notify( ( e && e.message ) || __( 'Could not save settings.', 'qcin-textdomain' ), 'error' );
				throw e;
			} );

	const ext = extScreens();
	const allowed = ( boot.screens || [] ).some( ( s ) => s.id === route.screen ) || route.screen === 'player';

	let screen;
	if ( ! allowed ) {
		screen = null;
	} else if ( route.screen === 'player' ) {
		screen = <Player id={ route.id } notify={ notify } />;
	} else if ( route.screen === 'settings' ) {
		screen = <SettingsScreen key="settings" settings={ settings } save={ saveSettings } notify={ notify } query={ route.query } />;
	} else if ( route.screen === 'recordings' ) {
		screen = <Recordings key={ JSON.stringify( route.query ) } settings={ settings } save={ saveSettings } query={ route.query } />;
	} else if ( route.screen === 'overview' ) {
		screen = <Overview settings={ settings } save={ saveSettings } query={ route.query } />;
	} else if ( route.screen === 'heatmaps' ) {
		screen = <Heatmaps key={ JSON.stringify( route.query ) } settings={ settings } save={ saveSettings } query={ route.query } />;
	} else if ( ext[ route.screen ] ) {
		screen = <ExtPart key={ route.screen + JSON.stringify( route.query ) } part={ ext[ route.screen ].render } props={ { settings, save: saveSettings, notify, query: route.query, ui } } />;
	}
	if ( ! screen ) {
		screen = (
			<div className="qcin-card qcin-empty">
				<p>{ __( 'This section is not available.', 'qcin-textdomain' ) }</p>
			</div>
		);
	}

	return (
		<div className="qcin-layout">
			<Sidebar route={ route } settings={ settings } ext={ ext } />
			<main className="qcin-main">{ screen }</main>
			<Toast toast={ toast } />
		</div>
	);
}

function mount() {
	const el = document.getElementById( 'qcin-app' );
	if ( ! el ) {
		return;
	}
	if ( wp.element.createRoot ) {
		wp.element.createRoot( el ).render( <App /> );
	} else {
		render( <App />, el );
	}
}

// Feature scripts are printed after this one; mount once they have run.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	setTimeout( mount, 0 );
}
