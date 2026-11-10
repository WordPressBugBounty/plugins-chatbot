/**
 * WPBot Insights loader (~2 KB).
 *
 * Decides in the browser whether this visit is recorded, in this order:
 * Do Not Track / Global Privacy Control, automation, consent (WP Consent API),
 * then sampling. Only then does it fetch the recorder bundle (recorded
 * visits) or the small collector (clicks, scroll and frustration signals).
 * It touches browser storage only after consent.
 */
( function () {
	'use strict';

	var c = window.qcInsightsConfig;
	if ( ! c || window.__qcinLoaded ) {
		return;
	}
	window.__qcinLoaded = true;

	// Pages shown inside the admin heatmap screen are never tracked.
	if ( /[?&]qcin_heatmap=/.test( window.location.search ) ) {
		return;
	}

	var nav = window.navigator || {};
	var SESSION_KEY = 'qcin_s';
	var VISITOR_KEY = 'qcin_v';

	function storage() {
		try {
			var s = window.localStorage;
			s.setItem( '__qcin', '1' );
			s.removeItem( '__qcin' );
			return s;
		} catch ( e ) {
			try {
				return window.sessionStorage;
			} catch ( e2 ) {
				return null;
			}
		}
	}

	function uuid() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return window.crypto.randomUUID();
		}
		var b = new Uint8Array( 16 );
		window.crypto.getRandomValues( b );
		b[ 6 ] = ( b[ 6 ] & 15 ) | 64;
		b[ 8 ] = ( b[ 8 ] & 63 ) | 128;
		var h = '';
		for ( var i = 0; i < 16; i++ ) {
			h += ( b[ i ] + 256 ).toString( 16 ).slice( 1 );
			if ( i === 3 || i === 5 || i === 7 || i === 9 ) {
				h += '-';
			}
		}
		return h;
	}

	// 1. Do Not Track / Global Privacy Control.
	if ( c.dnt && ( nav.doNotTrack === '1' || window.doNotTrack === '1' || nav.globalPrivacyControl === true ) ) {
		return;
	}

	// 2. Automated browsers.
	if ( nav.webdriver && ! c.automation ) {
		return;
	}

	// 3. Consent.
	function consentState() {
		if ( c.consent === 'never' ) {
			return 'allow';
		}
		if ( typeof window.wp_has_consent !== 'function' ) {
			// No consent plugin: "auto" records, "always" never records.
			return c.consent === 'always' ? 'deny' : 'allow';
		}
		// The WP Consent API handles opt-in and opt-out regions itself.
		return window.wp_has_consent( 'statistics' ) ? 'allow' : 'wait';
	}

	var started = false;

	function start() {
		if ( started ) {
			return;
		}
		started = true;

		var store = storage();
		var now = Date.now();
		var timeout = ( c.sessionMin || 30 ) * 60000;
		var session = null;
		try {
			session = JSON.parse( ( store && store.getItem( SESSION_KEY ) ) || 'null' );
		} catch ( e ) {
			session = null;
		}
		if ( ! session || typeof session.id !== 'string' || now - session.t > timeout ) {
			session = { id: uuid(), t: now, on: Math.random() * 100 < c.sample };
		}
		if ( typeof session.h !== 'boolean' ) {
			session.h = Math.random() * 100 < ( typeof c.hsample === 'number' ? c.hsample : 100 );
		}
		session.t = now;
		if ( store ) {
			store.setItem( SESSION_KEY, JSON.stringify( session ) );
		}

		// 4. Sampling, decided once per session: recorded visits load the
		// recorder (which also collects clicks); other visits sampled for
		// heatmaps load the small collector; the rest load nothing.
		if ( ! session.on && ! session.h ) {
			return;
		}

		var vid = store && store.getItem( VISITOR_KEY );
		if ( ! vid ) {
			vid = uuid();
			if ( store ) {
				store.setItem( VISITOR_KEY, vid );
			}
		}

		window.__qcinBoot = {
			config: c,
			vid: vid,
			sid: function () {
				return session.id;
			},
			touch: function () {
				session.t = Date.now();
				if ( store ) {
					store.setItem( SESSION_KEY, JSON.stringify( session ) );
				}
			},
			restart: function () {
				if ( store ) {
					store.removeItem( SESSION_KEY );
				}
			},
			uuid: uuid,
		};

		var s = document.createElement( 'script' );
		s.src = session.on ? c.recorder : c.collector;
		s.async = true;
		// Feature scripts load only after the tracker runs, and only for
		// the kind of visit they asked for.
		var ext = c.ext || [];
		if ( ext.length ) {
			s.onload = function () {
				for ( var i = 0; i < ext.length; i++ ) {
					var w = ext[ i ].when;
					if ( w === 'any' || ( w === 'recorded' ) === !! session.on ) {
						var e = document.createElement( 'script' );
						e.src = ext[ i ].src;
						e.async = true;
						document.head.appendChild( e );
					}
				}
			};
		}
		document.head.appendChild( s );
	}

	var state = consentState();
	if ( state === 'allow' ) {
		start();
	} else if ( state === 'wait' ) {
		document.addEventListener( 'wp_listen_for_consent_change', function ( e ) {
			var changed = ( e && e.detail ) || {};
			if ( changed.statistics === 'allow' ) {
				start();
			}
		} );
	}
} )();
