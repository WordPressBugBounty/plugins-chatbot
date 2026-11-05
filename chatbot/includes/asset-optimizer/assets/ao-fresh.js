/*! WPBot asset cache: keeps cached pages fresh (live show/hide rules, security tokens, visitor details). */
( function ( w, d ) {
	'use strict';
	var c = w.wpbotAo || {};
	var f = c.fresh || null, fetched = 0, swaps = [];

	function show( yes ) {
		var hold = d.getElementById( 'wpbot-ao-rules' );
		if ( yes && hold ) {
			hold.parentNode.removeChild( hold );
		}
	}

	function pick( live ) {
		var i = ( live.open ? 4 : 0 ) + ( live.mobile ? 2 : 0 ) + ( live.logged_in ? 1 : 0 );
		return '1' === f.table.charAt( i );
	}

	// Kept so the Pro engine loader can apply them again once its scripts have defined their objects.
	c.applyFresh = function () {
		if ( c.freshObjects ) {
			update( c.freshObjects );
		}
	};

	function update( objects ) {
		c.freshObjects = objects;
		Object.keys( objects || {} ).forEach( function ( name ) {
			var o = w[ name ], v = objects[ name ];
			if ( ! o || 'object' !== typeof o ) {
				return;
			}
			Object.keys( v ).forEach( function ( k ) {
				if ( 'string' === typeof o[ k ] && 'string' === typeof v[ k ] && o[ k ] && o[ k ] !== v[ k ] && /^[A-Za-z0-9_-]{6,}$/.test( o[ k ] ) ) {
					swaps.push( [ o[ k ], v[ k ] ] );
				}
				o[ k ] = v[ k ];
			} );
		} );
	}

	// Requests built from values copied before the refresh still carry the old tokens: swap them on the way out.
	function rewrite( text ) {
		swaps.forEach( function ( s ) {
			text = text.split( '=' + s[ 0 ] ).join( '=' + s[ 1 ] );
		} );
		return text;
	}

	function refresh() {
		if ( fetched || ! f || ! c.ajax || ! w.fetch ) {
			return;
		}
		fetched = 1;
		w.fetch( c.ajax + ( c.ajax.indexOf( '?' ) < 0 ? '?' : '&' ) + 'action=wpbot_ao_fresh', { credentials: 'same-origin', cache: 'no-store' } ).then( function ( r ) {
			return r.json();
		} ).then( function ( r ) {
			if ( ! r || ! r.success ) {
				throw new Error( 'wpbot_ao_fresh' );
			}
			update( r.data.objects );
			if ( f.table ) {
				show( pick( r.data.live ) );
			}
		} ).catch( function () {
			if ( f.table ) {
				show( '1' === f.table.charAt( f.now ) );
			}
		} );
	}

	function freshen() {
		var age, $ = w.jQuery;
		if ( ! f ) {
			return;
		}
		age = Date.now() / 1000 - f.t;
		if ( $ && $.ajaxPrefilter ) {
			$.ajaxPrefilter( function ( o ) {
				if ( swaps.length && 'string' === typeof o.data ) {
					o.data = rewrite( o.data );
				}
				if ( swaps.length && o.url ) {
					o.url = rewrite( o.url );
				}
			} );
		}
		// A page printed just now already knows the live answer.
		if ( f.table && age < 60 ) {
			show( '1' === f.table.charAt( f.now ) );
		}
		if ( f.u || ( f.table && age >= 60 ) || age > 21600 ) {
			refresh();
		} else if ( age >= 60 ) {
			[ 'pointerover', 'pointerdown', 'touchstart', 'focusin' ].forEach( function ( e ) {
				( ( c.root && d.querySelector( c.root ) ) || d ).addEventListener( e, refresh, { once: true, passive: true } );
			} );
		}
	}

	freshen();
}( window, document ) );
