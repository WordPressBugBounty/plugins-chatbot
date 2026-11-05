/* WPBot asset cache guard: falls back to original files and reports failures (see src/Frontend.php). */
( function ( w, d ) {
	'use strict';
	var c = w.wpbotAo || {}, err = c.err || {}, files = c.files || [], loaded = 0;

	function report( f ) {
		var b = w.FormData && navigator.sendBeacon && c.ajax && new FormData();
		if ( b ) {
			b.append( 'action', 'wpbot_ao_report' );
			b.append( 'type', f.t );
			b.append( 'handle', f.h );
			b.append( 'key', f.key || '' );
			navigator.sendBeacon( c.ajax, b );
		}
	}

	function add( tag, attrs, before ) {
		var e = d.createElement( tag );
		Object.keys( attrs ).forEach( function ( k ) {
			e[ k ] = attrs[ k ];
		} );
		( before ? before.parentNode : 'script' === tag ? d.body : d.head ).insertBefore( e, before ? before.nextSibling : null );
		return e;
	}

	function scripts() {
		var ran = w.wpbotAoRan || {}, queue = files.filter( function ( f ) {
			if ( 'script' !== f.t || ran[ f.h ] || 'runtime' === err[ f.src ] ) {
				return false;
			}
			report( f );
			return true;
		} );
		( function next() {
			var f = queue.shift();
			if ( f ) {
				add( 'script', { src: f.orig, async: false, onload: next, onerror: next } );
			}
		}() );
	}

	function sheets() {
		files.forEach( function ( f ) {
			var l = 'style' === f.t && d.getElementById( f.id || f.h + '-css' );
			if ( 'style' === f.t && ( 'load' === err[ f.src ] || ! l || ! l.sheet ) ) {
				report( f );
				add( 'link', { rel: 'stylesheet', href: f.orig }, l || null );
			}
		} );
	}

	function styles() {
		if ( ! loaded++ ) {
			( c.styles || [] ).forEach( function ( s ) {
				if ( ! d.getElementById( s.id ) ) {
					add( 'link', { rel: 'stylesheet', id: s.id, href: s.href } );
				}
			} );
		}
	}

	function arm() {
		var root = ( c.root && d.querySelector( c.root ) ) || d;
		sheets();
		[ 'pointerover', 'pointerdown', 'touchstart' ].forEach( function ( e ) {
			root.addEventListener( e, styles, { once: true, passive: true } );
		} );
		d.addEventListener( 'keydown', styles, { once: true } );
		( w.requestIdleCallback || function ( f ) {
			return setTimeout( f, 1500 );
		} )( styles, { timeout: 3000 } );
	}

	scripts();
	if ( 'complete' === d.readyState ) {
		arm();
	} else {
		w.addEventListener( 'load', arm );
	}
}( window, document ) );
