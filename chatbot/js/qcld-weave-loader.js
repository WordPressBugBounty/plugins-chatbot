/**
 * WPBot "Weave" loader: three small glass spheres circling each other in 3D while the
 * bot waits for the AI. It replaces the default typing GIF in the chat window.
 *
 *   qcldWeave.html( 'Carrie is typing...' )  Markup for a loader (text optional). Insert it anywhere:
 *                                           the spheres start on their own (a MutationObserver finds them).
 *   qcldWeave.enabled()                     False when the site keeps the classic GIF (qcld_wpbot_loader_style).
 *
 * The outer element keeps the class "wp-chatbot-comment-loader", so every existing
 * `.find( '.wp-chatbot-comment-loader' ).remove()` removes the spheres and the text together.
 * The spheres are glass in one colour, like the WPBot logo: logo blue, or qcldWeaveConfig.color
 * (filter qcld_wpbot_loader_color). qcldWeaveConfig.speed sets the pace (filter qcld_wpbot_loader_speed,
 * 1.5 by default). Visitors who ask for reduced motion see a still pose.
 */
( function ( w, d ) {
	'use strict';

	if ( w.qcldWeave ) {
		return;
	}

	var config = w.qcldWeaveConfig || {};
	var DEFAULT_COLOR = '#3d8fd6'; // The blue of the WPBot logo.
	var TIME = 2.55; // Pace of the motion at speed 1.
	var SPEED = parseFloat( config.speed ) > 0 ? parseFloat( config.speed ) : 1.5;
	var BALL = 0.05376; // Sphere radius / box size (about 5.4 px spheres in the 50 px box).
	var SPREAD = 0.7436; // Orbit size: the spheres span 30 px of the 50 px box.
	var D = 3.2; // Perspective distance.
	var ORBITS = [
		{ r: 0.62, tilt: 0.5, node: 0.0, w: 0.95, p: 0.0 },
		{ r: 0.58, tilt: -0.8, node: 1.6, w: -0.8, p: 2.1 },
		{ r: 0.66, tilt: 1.2, node: 3.1, w: 0.7, p: 4.2 }
	];
	var reduce = !! ( w.matchMedia && w.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	var live = [];
	var raf = 0;
	var last = 0;
	var clock = 0;

	/* ---------------------------------------------------------------- */
	/* Colours                                                          */
	/* ---------------------------------------------------------------- */

	function parse( str ) {
		if ( ! str ) {
			return null;
		}
		str = String( str ).trim();
		if ( str.charAt( 0 ) === '#' ) {
			var h = str.slice( 1 );
			if ( h.length === 3 ) {
				h = h.split( '' ).map( function ( c ) {
					return c + c;
				} ).join( '' );
			}
			if ( ! /^[0-9a-f]{6}$/i.test( h ) ) {
				return null;
			}
			return { c: [ parseInt( h.slice( 0, 2 ), 16 ), parseInt( h.slice( 2, 4 ), 16 ), parseInt( h.slice( 4, 6 ), 16 ) ], a: 1 };
		}
		var m = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?/.exec( str );
		return m ? { c: [ +m[ 1 ], +m[ 2 ], +m[ 3 ] ], a: m[ 4 ] === undefined ? 1 : +m[ 4 ] } : null;
	}

	function hsl( c ) {
		var r = c[ 0 ] / 255, g = c[ 1 ] / 255, b = c[ 2 ] / 255;
		var max = Math.max( r, g, b ), min = Math.min( r, g, b ), l = ( max + min ) / 2, dd = max - min, h = 0, s = 0;
		if ( dd ) {
			s = dd / ( 1 - Math.abs( 2 * l - 1 ) );
			h = max === r ? ( ( g - b ) / dd ) % 6 : ( max === g ? ( b - r ) / dd + 2 : ( r - g ) / dd + 4 );
			h *= 60;
		}
		return [ ( h + 360 ) % 360, s, l ];
	}

	function mix( a, b, k ) {
		return [ 0, 1, 2 ].map( function ( i ) {
			return Math.round( a[ i ] + ( b[ i ] - a[ i ] ) * k );
		} );
	}

	function css( c, a ) {
		return a === undefined ? 'rgb(' + c.join( ',' ) + ')' : 'rgba(' + c.join( ',' ) + ',' + a + ')';
	}

	// The spheres' colour: the loader's own data-color, the site's setting, or logo blue.
	function sphereColor( host ) {
		var own = parse( host.getAttribute( 'data-color' ) || config.color );
		return own ? own.c : parse( DEFAULT_COLOR ).c;
	}

	// Text colour that reads on whatever is behind the loader (its bubble is see-through).
	function textColor( host ) {
		for ( var el = host.parentElement; el && el !== d.documentElement; el = el.parentElement ) {
			var cs = w.getComputedStyle( el );
			if ( cs.backgroundImage && cs.backgroundImage !== 'none' ) {
				return '';
			}
			var bg = parse( cs.backgroundColor );
			if ( bg && bg.a > 0.5 ) {
				return hsl( bg.c )[ 2 ] < 0.45 ? '#c9ccd1' : '#5f6368';
			}
		}
		return '';
	}

	/* ---------------------------------------------------------------- */
	/* One loader                                                       */
	/* ---------------------------------------------------------------- */

	function Weave( orb ) {
		this.orb = orb;
		this.host = orb.closest( '.qcld-weave' ) || orb;
		this.size = orb.offsetWidth || 50;
		this.balls = [];
		for ( var j = 0; j < 3; j++ ) {
			var ball = d.createElement( 'span' );
			ball.className = 'qw-ball';
			orb.appendChild( ball );
			this.balls.push( ball );
		}
		this.paint();
		var text = this.host.querySelector( '.qcld-weave-text' );
		var tc = text ? textColor( this.host ) : '';
		if ( tc ) {
			text.style.color = tc;
		}
		this.frame( clock );
	}

	Weave.prototype.paint = function () {
		var t = sphereColor( this.host ), white = [ 255, 255, 255 ], black = [ 0, 0, 0 ];
		var r = Math.max( 1.6, this.size * BALL );
		var glow = Math.max( 2, this.size * 0.03 );
		// Glass: the colour deepens to a dark rim, light gathers at the bottom (as if passing through)
		// and a thin bright edge outlines it. The window reflection is the CSS ::after.
		var body = 'radial-gradient(circle at 50% 84%, ' + css( mix( t, white, 0.6 ), 0.95 ) + ' 0%, ' + css( mix( t, white, 0.35 ), 0 ) + ' 38%), '
			+ 'radial-gradient(circle at 46% 42%, ' + css( mix( t, white, 0.28 ), 0.88 ) + ' 0%, ' + css( mix( t, white, 0.06 ), 0.94 ) + ' 50%, ' + css( mix( t, black, 0.22 ) ) + ' 84%, ' + css( mix( t, black, 0.42 ) ) + ' 100%)';
		var edge = 'inset 0 0 0 ' + Math.max( 0.5, r * 0.08 ).toFixed( 2 ) + 'px ' + css( mix( t, white, 0.7 ), 0.6 ) + ', 0 0 ' + ( glow * 0.7 ).toFixed( 1 ) + 'px ' + css( t, 0.35 );
		for ( var i = 0; i < 3; i++ ) {
			var b = this.balls[ i ];
			b.style.width = b.style.height = ( r * 2 ) + 'px';
			b.style.background = body;
			b.style.boxShadow = edge;
		}
		this.r = r;
	};

	function position( o, t ) {
		var th = t * o.w + o.p;
		var x = o.r * Math.cos( th ), z = o.r * Math.sin( th );
		var yt = -z * Math.sin( o.tilt ), zt = z * Math.cos( o.tilt );
		return [ x * Math.cos( o.node ) + zt * Math.sin( o.node ), yt, -x * Math.sin( o.node ) + zt * Math.cos( o.node ) ];
	}

	Weave.prototype.frame = function ( t ) {
		var s = this.size, R = s / 2, pts = [];
		for ( var i = 0; i < 3; i++ ) {
			var p = position( ORBITS[ i ], t ), k = D / ( D - p[ 2 ] );
			pts.push( { x: R + p[ 0 ] * R * SPREAD * k, y: R + p[ 1 ] * R * SPREAD * k, z: p[ 2 ], k: k } );
		}
		for ( var j = 0; j < 3; j++ ) {
			var q = pts[ j ], st = this.balls[ j ].style;
			st.transform = 'translate(' + ( q.x - this.r ).toFixed( 2 ) + 'px,' + ( q.y - this.r ).toFixed( 2 ) + 'px) scale(' + q.k.toFixed( 3 ) + ')';
			st.zIndex = String( 10 + Math.round( ( q.z + 1 ) * 20 ) );
			st.filter = 'brightness(' + ( 0.78 + 0.15 * ( q.z + 1 ) ).toFixed( 3 ) + ')';
		}
	};

	/* ---------------------------------------------------------------- */
	/* Loop and discovery                                               */
	/* ---------------------------------------------------------------- */

	function tick( now ) {
		raf = 0;
		live = live.filter( function ( l ) {
			return l.orb.isConnected;
		} );
		if ( ! live.length || d.hidden ) {
			last = 0;
			return;
		}
		var dt = last ? Math.min( 0.1, ( now - last ) / 1000 ) : 0;
		last = now;
		clock += dt * TIME * SPEED;
		for ( var i = 0; i < live.length; i++ ) {
			live[ i ].frame( clock );
		}
		raf = w.requestAnimationFrame( tick );
	}

	function start() {
		if ( ! raf && ! reduce && live.length && ! d.hidden ) {
			raf = w.requestAnimationFrame( tick );
		}
	}

	function init( root ) {
		var list = [];
		if ( root.nodeType !== 1 ) {
			return;
		}
		if ( root.matches( '.qcld-weave-orb' ) ) {
			list.push( root );
		}
		var found = root.querySelectorAll( '.qcld-weave-orb' );
		for ( var i = 0; i < found.length; i++ ) {
			list.push( found[ i ] );
		}
		list.forEach( function ( orb ) {
			if ( orb.getAttribute( 'data-ready' ) ) {
				return;
			}
			orb.setAttribute( 'data-ready', '1' );
			try {
				live.push( new Weave( orb ) );
			} catch ( e ) {
				orb.setAttribute( 'data-ready', 'error' );
			}
		} );
		start();
	}

	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	w.qcldWeave = {
		enabled: function () {
			return config.style !== 'classic';
		},
		html: function ( text ) {
			var label = text ? esc( text ) : '…';
			return '<span class="wp-chatbot-comment-loader qcld-weave' + ( reduce ? ' is-still' : '' ) + '" role="status" aria-label="' + label + '">'
				+ '<span class="qcld-weave-orb" aria-hidden="true"></span>'
				+ ( text ? '<span class="qcld-weave-text">' + esc( text ) + '</span>' : '' )
				+ '</span>';
		},
		init: init
	};

	function boot() {
		init( d.body );
		if ( w.MutationObserver ) {
			new MutationObserver( function ( muts ) {
				for ( var i = 0; i < muts.length; i++ ) {
					for ( var j = 0; j < muts[ i ].addedNodes.length; j++ ) {
						var n = muts[ i ].addedNodes[ j ];
						if ( n.nodeType === 1 && ( n.classList.contains( 'qcld-weave-orb' ) || n.querySelector( '.qcld-weave-orb' ) ) ) {
							init( n );
						}
					}
				}
			} ).observe( d.body, { childList: true, subtree: true } );
		}
		d.addEventListener( 'visibilitychange', function () {
			last = 0;
			start();
		} );
	}

	if ( d.body ) {
		boot();
	} else {
		d.addEventListener( 'DOMContentLoaded', boot );
	}
}( window, document ) );
