/**
 * WPBot Insights recorder. Loaded only for sampled, consented visits.
 *
 * Records the page with rrweb (inputs always masked) and collects clicks,
 * scroll and frustration signals with the shared collector. Batches are
 * gzipped in the browser and sent to the site's own REST endpoint.
 * Frustration signals are also added to the recording as custom events,
 * so the player shows them as markers on the timeline.
 * Also exposes a small public API on window.qcInsights.
 */
import { record } from '@rrweb/record';
import { gzipSync, strToU8 } from 'fflate';
import { createCollector, publicApi, endpointUrl, meta } from './collector-core';

const MARKERS = { rage: 'rage click', dead: 'dead click', error: 'js error' };

( function () {
	const boot = window.__qcinBoot;
	if ( ! boot || window.qcInsights ) {
		return;
	}
	const c = boot.config;
	const url = endpointUrl( c );

	let pv = boot.uuid();
	let seq = 0;
	let buf = [];
	let pvBytes = 0;
	let stopped = false;
	let recording = true;
	let stopRecording = null;
	let flushTimer = null;

	const col = createCollector( {
		rules: c.rules,
		onSignal( sig ) {
			if ( recording && MARKERS[ sig.k ] ) {
				try {
					record.addCustomEvent( MARKERS[ sig.k ], { selector: sig.s, detail: sig.d } );
				} catch ( e ) {}
			}
		},
	} );

	const SENSITIVE_TYPES = [ 'password', 'email', 'tel', 'hidden' ];

	function isSensitive( el ) {
		if ( ! el || ! el.getAttribute ) {
			return true;
		}
		const type = ( el.getAttribute( 'type' ) || '' ).toLowerCase();
		const auto = ( el.getAttribute( 'autocomplete' ) || '' ).toLowerCase();
		return SENSITIVE_TYPES.indexOf( type ) !== -1 || auto.indexOf( 'cc-' ) === 0 || auto.indexOf( 'password' ) !== -1;
	}

	function unmasked( el ) {
		return el && el.closest && el.closest( '.qcin-unmask' );
	}

	function stars( text ) {
		return '*'.repeat( ( text || '' ).length );
	}

	function handleReply( reply ) {
		if ( ! reply ) {
			return;
		}
		if ( reply.s === 'stop' || reply.s === 'reject' ) {
			stop();
		} else if ( reply.s === 'norec' ) {
			endRecording();
		} else if ( reply.s === 'restart' ) {
			boot.restart();
			stop();
		}
	}

	function post( body, final, attempt ) {
		if ( final && navigator.sendBeacon ) {
			if ( navigator.sendBeacon( url, new Blob( [ body ], { type: 'application/octet-stream' } ) ) ) {
				return;
			}
		}
		fetch( url, {
			method: 'POST',
			body,
			keepalive: body.length < 60000,
			credentials: 'omit',
			headers: { 'Content-Type': 'application/octet-stream' },
		} )
			.then( ( r ) => r.json() )
			.then( handleReply )
			.catch( () => {
				if ( ! attempt && ! final ) {
					setTimeout( () => post( body, false, 1 ), 3000 );
				}
			} );
	}

	function send( final ) {
		if ( stopped ) {
			return;
		}
		if ( final ) {
			col.flushWaiting();
		}
		if ( ! buf.length && ! ( final ? col.dirty() : col.pending() ) ) {
			return;
		}
		const d = col.drain();
		const payload = {
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
			events: recording ? buf : [],
		};
		buf = [];
		const body = gzipSync( strToU8( JSON.stringify( payload ) ) );
		if ( payload.events.length ) {
			pvBytes += body.length;
		}
		boot.touch();
		post( body, final, 0 );
		if ( pvBytes > 2000000 ) {
			endRecording();
		}
	}

	function onHide() {
		if ( document.visibilityState === 'hidden' ) {
			send( true );
		}
	}

	/** Stop the recording but keep sending clicks and signals. */
	function endRecording() {
		if ( ! recording ) {
			return;
		}
		recording = false;
		buf = [];
		if ( stopRecording ) {
			stopRecording();
			stopRecording = null;
		}
	}

	function stop() {
		if ( stopped ) {
			return;
		}
		endRecording();
		stopped = true;
		clearInterval( flushTimer );
		col.stop();
	}

	function maskSelector() {
		if ( c.maskText ) {
			return '*';
		}
		return c.maskSel || null;
	}

	function startRecording() {
		stopRecording = record( {
			emit( event ) {
				if ( stopped || ! recording ) {
					return;
				}
				buf.push( event );
				if ( buf.length >= 400 ) {
					send( false );
				}
			},
			maskAllInputs: true,
			maskInputFn: ( text, el ) => ( unmasked( el ) && ! isSensitive( el ) ? text : stars( text ) ),
			maskTextClass: 'qcin-mask',
			maskTextSelector: maskSelector(),
			maskTextFn: ( text, el ) => ( unmasked( el ) ? text : stars( text ) ),
			blockClass: 'qcin-block',
			blockSelector: c.blockSel || null,
			ignoreClass: 'qcin-ignore',
			inlineStylesheet: true,
			recordCanvas: false,
			collectFonts: false,
			slimDOMOptions: { script: true, comment: true, headFavicon: true, headWhitespace: true, headMetaSocial: true, headMetaRobots: true, headMetaHttpEquiv: true, headMetaVerification: true },
			sampling: { mousemove: 50, scroll: 150, input: 'last' },
		} );
	}

	function newPageview() {
		send( true );
		pv = boot.uuid();
		seq = 0;
		pvBytes = 0;
		col.newPageview();
		if ( recording ) {
			record.takeFullSnapshot( true );
		}
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

	document.addEventListener( 'visibilitychange', onHide );
	window.addEventListener( 'pagehide', () => send( true ) );
	flushTimer = setInterval( () => send( false ), c.flushMs || 10000 );
	setTimeout( endRecording, ( c.maxMinutes || 30 ) * 60000 );

	startRecording();

	/**
	 * Public API for host plugins (e.g. the WPBot chat widget).
	 */
	window.qcInsights = publicApi( col, {
		event( name, payload ) {
			if ( stopped || typeof name !== 'string' ) {
				return;
			}
			col.custom( name );
			if ( recording ) {
				record.addCustomEvent( name.slice( 0, 64 ), payload || {} );
			}
		},
		tag( key, value ) {
			if ( recording && typeof key === 'string' ) {
				record.addCustomEvent( 'qcin:tag', { key: key.slice( 0, 64 ), value: String( value ).slice( 0, 200 ) } );
			}
		},
		getContext() {
			return { session: boot.sid(), pageview: pv, url: window.location.href, scroll: col.scroll(), recording };
		},
		stop,
	} );
	col.emit( 'ready', {} );
} )();
