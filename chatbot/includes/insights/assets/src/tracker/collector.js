/**
 * WPBot Insights light collector, for consented visits that are not
 * recorded: clicks, scroll depth and frustration signals for heatmaps and
 * the overview. Sends small JSON batches to the site's own REST endpoint.
 */
import { createCollector, publicApi, endpointUrl, meta } from './collector-core';

( function () {
	const boot = window.__qcinBoot;
	if ( ! boot || window.qcInsights ) {
		return;
	}
	const c = boot.config;
	const url = endpointUrl( c );
	let pv = boot.uuid();
	let seq = 0;
	let stopped = false;
	let timer = null;

	const col = createCollector( { rules: c.rules } );

	function handleReply( reply ) {
		if ( ! reply ) {
			return;
		}
		if ( reply.s === 'stop' || reply.s === 'reject' ) {
			stop();
		} else if ( reply.s === 'restart' ) {
			boot.restart();
			stop();
		}
	}

	function send( final ) {
		if ( stopped ) {
			return;
		}
		if ( final ) {
			col.flushWaiting();
		}
		if ( ! ( final ? col.dirty() : col.pending() ) ) {
			return;
		}
		const d = col.drain();
		const body = JSON.stringify( {
			v: 1,
			sid: boot.sid(),
			vid: boot.vid,
			pv,
			seq: seq++,
			meta: meta(),
			t: col.elapsed(),
			scroll: d.scroll,
			docH: d.docH,
			clicks: d.clicks,
			signals: d.signals,
			ext: d.ext,
		} );
		boot.touch();
		if ( final && navigator.sendBeacon && navigator.sendBeacon( url, new Blob( [ body ], { type: 'text/plain' } ) ) ) {
			return;
		}
		fetch( url, { method: 'POST', body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } } )
			.then( ( r ) => r.json() )
			.then( handleReply )
			.catch( () => {} );
	}

	function stop() {
		if ( stopped ) {
			return;
		}
		stopped = true;
		clearInterval( timer );
		col.stop();
	}

	function newPageview() {
		send( true );
		pv = boot.uuid();
		seq = 0;
		col.newPageview();
		col.emit( 'pageview', { id: pv } );
	}

	// Single-page apps: a history change starts a new pageview.
	const push = window.history.pushState;
	window.history.pushState = function () {
		const before = window.location.href;
		const out = push.apply( this, arguments );
		if ( window.location.href !== before ) {
			newPageview();
		}
		return out;
	};
	window.addEventListener( 'popstate', newPageview );
	// Back/forward cache: the page is shown again without reloading.
	window.addEventListener( 'pageshow', ( e ) => {
		if ( e.persisted ) {
			newPageview();
		}
	} );

	document.addEventListener( 'visibilitychange', () => {
		if ( document.visibilityState === 'hidden' ) {
			send( true );
		}
	} );
	window.addEventListener( 'pagehide', () => send( true ) );
	timer = setInterval( () => send( false ), c.collectMs || 15000 );
	setTimeout( stop, ( c.maxMinutes || 30 ) * 60000 );

	window.qcInsights = publicApi( col, {
		event( name ) {
			if ( ! stopped && typeof name === 'string' ) {
				col.custom( name );
			}
		},
		tag() {},
		getContext() {
			return { session: boot.sid(), pageview: pv, url: window.location.href, scroll: col.scroll(), recording: false };
		},
		stop,
	} );
	col.emit( 'ready', {} );
} )();
