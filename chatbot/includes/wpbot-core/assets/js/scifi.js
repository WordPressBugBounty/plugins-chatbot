/**
 * WPBot admin design: the GRID look's HUD (owner, 21 November 2026).
 *
 * The admin theme's GRID look puts html.qa-scifi on the page; assets/css/scifi.css restyles the
 * design for it. This script adds the decorative parts that need markup, all hidden from screen
 * readers and click-through where they overlay the page:
 * - a "››› SECTOR · <tab> ‹‹‹" plate in the header band;
 * - a breadcrumb above the page title and a readout (node ring, build, link, clock) beside it;
 * - a tick ruler under the section tabs;
 * - a frame around the screen with a status plate (version, clock, errors in the last 7 days).
 * - on the host's dashboard pages, five status tiles with live numbers (Hud_Stats, refreshed every minute).
 * It builds them when the GRID turns on (also live, on the admin theme's qcld_admin_theme_change
 * event) and removes them when it turns off. Values come from Design::hud(): real data only.
 */
( function () {
	'use strict';

	var cfg = ( window.qcldWpbotDesign && window.qcldWpbotDesign.hud ) || null;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n || {};
	var root = document.documentElement;
	var made = [];
	var timer = null;
	var poll = null;
	var tt = t.tiles || {};

	function el( tag, cls, html ) {
		var e = document.createElement( tag );
		e.className = cls;
		e.setAttribute( 'aria-hidden', 'true' );
		e.setAttribute( 'data-qa-skip', '' );
		if ( html ) {
			e.innerHTML = html;
		}
		made.push( e );
		return e;
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function up( s ) {
		return String( s || '' ).toUpperCase();
	}

	// "WPBot Pro 15.8.2" -> "15.8.2"; the menu name -> "WPBOT-PRO".
	function build() {
		var m = /(\d+(?:\.\d+)+)/.exec( cfg.version || '' );
		return m ? m[ 1 ] : up( cfg.version );
	}

	function node() {
		return up( cfg.menu ).replace( /[^A-Z0-9]+/g, '-' ).replace( /^-|-$/g, '' ) || 'WPBOT';
	}

	function status() {
		var n = parseInt( cfg.errors, 10 ) || 0;
		return n > 0 ? up( ( t.errors || '%d errors in 7 days' ).replace( '%d', n ) ) : up( t.nominal || 'All systems nominal' );
	}

	function clock() {
		var now = new Date();
		var two = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		var text = two( now.getHours() ) + ':' + two( now.getMinutes() ) + ':' + two( now.getSeconds() );
		var clocks = document.querySelectorAll( '.wpb-sf-clock' );
		for ( var i = 0; i < clocks.length; i++ ) {
			clocks[ i ].textContent = text;
		}
	}

	function corner() {
		return '<svg viewBox="0 0 36 36" focusable="false"><path d="M0 34V12L12 0h22" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 30V14L14 4h8" fill="none" stroke="currentColor" stroke-width="3" opacity=".9"/><rect x="26" y="-1.5" width="10" height="3" fill="currentColor"/></svg>';
	}

	/* ------------------------------------------------------------------ */
	/* Status tiles (Hud_Stats)                                            */
	/* ------------------------------------------------------------------ */

	var C1 = '#38e1f5';
	var C2 = '#9af5ff';

	function num( n ) {
		return n === null || n === undefined ? '—' : Number( n ).toLocaleString();
	}

	function deltaText( now, prev ) {
		if ( now === null || prev === null || ! prev ) {
			return '';
		}
		var d = Math.round( ( now - prev ) * 100 / prev );
		return ( d > 0 ? '+' : '' ) + d + '%';
	}

	// Columns of dots (a point cloud): one column per value.
	function dotwave( vals ) {
		var w = 120;
		var h = 42;
		var max = Math.max.apply( null, vals.concat( [ 1 ] ) );
		var step = vals.length > 1 ? w / vals.length : w;
		var out = '';
		vals.forEach( function ( v, i ) {
			var top = Math.round( ( v / max ) * ( h - 6 ) );
			for ( var y = 0; y <= top; y += 3 ) {
				var hi = y > top - 3;
				out += '<circle cx="' + ( i * step + step / 2 ).toFixed( 1 ) + '" cy="' + ( h - 2 - y ) + '" r="' + ( hi ? 1.3 : 0.9 ) + '" fill="' + ( hi ? C2 : C1 ) + '" opacity="' + ( 0.3 + 0.7 * y / h ).toFixed( 2 ) + '"/>';
			}
		} );
		return '<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true">' + out + '</svg>';
	}

	// Segmented vertical meters, ten steps each.
	function meters( vals ) {
		var max = Math.max.apply( null, vals.concat( [ 1 ] ) );
		var out = '';
		vals.forEach( function ( v, i ) {
			var on = Math.round( ( v / max ) * 10 );
			for ( var k = 0; k < 10; k++ ) {
				out += '<rect x="' + ( i * 12 ) + '" y="' + ( 38 - k * 4 ) + '" width="8" height="2.6" fill="' + ( k < on ? ( k === on - 1 ? C2 : C1 ) : 'rgba(56,225,245,.14)' ) + '"/>';
			}
		} );
		return '<svg width="' + Math.max( 12, vals.length * 12 ) + '" height="42" viewBox="0 0 ' + Math.max( 12, vals.length * 12 ) + ' 42" aria-hidden="true">' + out + '</svg>';
	}

	// A glowing line with a soft area under it.
	function line( vals ) {
		var w = 120;
		var h = 42;
		if ( vals.length < 2 ) {
			vals = [ 0, 0 ].concat( vals );
		}
		var max = Math.max.apply( null, vals.concat( [ 1 ] ) );
		var d = vals.map( function ( v, i ) {
			return ( i ? 'L' : 'M' ) + ( i * w / ( vals.length - 1 ) ).toFixed( 1 ) + ' ' + ( h - 4 - ( v / max ) * ( h - 10 ) ).toFixed( 1 );
		} ).join( ' ' );
		return '<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><path d="' + d + ' L' + w + ' ' + h + ' L0 ' + h + ' Z" fill="rgba(56,225,245,.12)"/><path d="' + d + '" fill="none" stroke="' + C1 + '" stroke-width="1.6" style="filter:drop-shadow(0 0 3px ' + C1 + ')"/></svg>';
	}

	function ring( pct ) {
		var r = 20;
		var c = 2 * Math.PI * r;
		var on = pct === null ? 0 : c * pct / 100;
		return '<svg width="58" height="58" viewBox="0 0 58 58" aria-hidden="true"><circle cx="29" cy="29" r="26" fill="none" stroke="rgba(56,225,245,.35)" stroke-width="1" stroke-dasharray="2 4"/><circle cx="29" cy="29" r="' + r + '" fill="none" stroke="rgba(56,225,245,.16)" stroke-width="5"/><circle cx="29" cy="29" r="' + r + '" fill="none" stroke="' + C1 + '" stroke-width="5" stroke-dasharray="' + on.toFixed( 1 ) + ' ' + c.toFixed( 1 ) + '" transform="rotate(-90 29 29)" style="filter:drop-shadow(0 0 3px ' + C1 + ')"/><text x="29" y="33" text-anchor="middle" font-family="Orbitron, sans-serif" font-size="11" font-weight="700" fill="#fff">' + ( pct === null ? '—' : pct + '%' ) + '</text></svg>';
	}

	function tile( key, label, body ) {
		return '<div class="wpb-sf-tile" data-tile="' + key + '"><div class="wpb-sf-tile__label">' + esc( up( label ) ) + '</div><span class="wpb-sf-tile__dots"></span>' + body + '</div>';
	}

	function aiLabel( status ) {
		var map = { ready: tt.ready, no_key: tt.no_key, not_enabled: tt.not_enabled, no_model: tt.no_model, failing: tt.failing, budget_reached: tt.budget };
		return up( map[ status ] || status || '—' );
	}

	function renderTiles( box, s ) {
		var c = s.conversations;
		var a = s.answers;
		var l = s.leads;
		var r = s.resolved;
		var st = s.status;
		var off = st.sessions === false;
		var dc = deltaText( c.now, c.prev );
		var da = deltaText( a.now, a.prev );
		var done = st.checks.filter( function ( k ) {
			return k.done;
		} ).length;
		var seg = st.checks.map( function ( k ) {
			return '<i class="' + ( k.done ? 'on' : '' ) + '" title="' + esc( k.label ) + '"></i>';
		} ).join( '' );
		box.innerHTML =
			tile( 'conversations', tt.conversations, '<div class="wpb-sf-tile__value">' + num( c.now ) + '<small>/ ' + esc( up( tt.day ) ) + '</small></div><div class="wpb-sf-tile__row">' + ( dc ? '<span class="wpb-sf-delta">' + dc + '</span>' : '<span></span>' ) + dotwave( c.series.length ? c.series : [ 0 ] ) + '</div>' ) +
			tile( 'answers', tt.answers, '<div class="wpb-sf-tile__value">' + num( a.now ) + '<small>/ ' + esc( up( tt.day ) ) + '</small></div><div class="wpb-sf-tile__row">' + ( da ? '<span class="wpb-sf-delta">' + da + '</span>' : '<span></span>' ) + meters( a.series.length ? a.series : [ 0 ] ) + '</div>' ) +
			tile( 'leads', tt.leads, '<div class="wpb-sf-tile__value">' + num( l.total ) + '<small>' + esc( up( tt.captured ) ) + '</small></div><div class="wpb-sf-tile__row">' + ( l.day !== null ? '<span class="wpb-sf-delta">+' + num( l.day ) + ' ' + esc( tt.today ) + '</span>' : '<span></span>' ) + line( l.series ) + '</div>' ) +
			tile( 'resolved', tt.resolved, '<div class="wpb-sf-tile__row is-ring">' + ring( r.rate ) + '<div class="wpb-sf-mono">' + esc( up( tt.sessions ) ) + ' <b>' + num( r.sessions ) + '</b><br>' + esc( up( tt.handoffs ) ) + ' <b>' + num( r.handoffs ) + '</b><br>' + esc( up( tt.unanswered ) ) + ' <b>' + num( r.unanswered ) + '</b><br><small>' + esc( up( tt.week ) ) + '</small></div></div>' ) +
			tile( 'status', tt.status, '<div class="wpb-sf-tile__value is-status' + ( st.ready ? '' : ' is-warn' ) + '"><span class="wpb-sf-pulse"></span>' + esc( aiLabel( st.ai ) ) + '</div><div class="wpb-sf-seg">' + seg + '</div><div class="wpb-sf-mono wpb-sf-tile__foot"><span>' + esc( up( tt.setup ) ) + ' <b>' + done + '/' + st.checks.length + '</b></span><span>' + esc( up( tt.errors ) ) + ' <b class="' + ( st.errors ? 'is-warn' : '' ) + '">' + num( st.errors ) + '</b></span></div>' );
		box.setAttribute( 'data-loaded', '1' );
		if ( off ) {
			box.setAttribute( 'title', tt.off || '' );
		}
	}

	function loadTiles( box ) {
		var body = new window.FormData();
		body.append( 'action', cfg.action );
		body.append( 'nonce', cfg.nonce );
		return window.fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( json && json.success && box.isConnected ) {
					renderTiles( box, json.data );
				}
			} )
			[ 'catch' ]( function () {} );
	}

	function buildTiles( before ) {
		if ( ! cfg.tiles || ! cfg.ajax || ! window.fetch || ! before ) {
			return;
		}
		var box = el( 'div', 'wpb-sf-tiles', '' );
		box.removeAttribute( 'aria-hidden' );
		box.setAttribute( 'role', 'region' );
		box.setAttribute( 'aria-label', up( tt.status || 'Status' ) );
		box.setAttribute( 'aria-live', 'polite' );
		var keys = [ 'conversations', 'answers', 'leads', 'resolved', 'status' ];
		box.innerHTML = keys.map( function ( k ) {
			return tile( k, tt[ k ] || k, '<div class="wpb-sf-tile__value">—</div>' );
		} ).join( '' );
		before.parentNode.insertBefore( box, before );
		loadTiles( box );
		poll = window.setInterval( function () {
			if ( ! document.hidden ) {
				loadTiles( box );
			}
		}, 60000 );
	}

	function buildHud() {
		if ( made.length ) {
			return;
		}
		var section = cfg.section || cfg.title || '';

		// Band: plate between the brand and the controls.
		var actions = document.querySelector( '.wpb-band__actions' );
		if ( actions && section ) {
			actions.parentNode.insertBefore(
				el( 'div', 'wpb-sf-banddeco', '<span class="wpb-sf-dots"></span><span class="wpb-sf-chev">›››</span><span class="wpb-sf-plate">' + esc( up( t.sector || 'Sector' ) ) + ' · ' + esc( up( section ) ) + '</span><span class="wpb-sf-chev">‹‹‹</span><span class="wpb-sf-dots"></span>' ),
				actions
			);
		}

		// Page head: breadcrumb (CSS shows it from the attribute) and the readout.
		var head = document.querySelector( '.wpb-pagehead' );
		if ( head ) {
			var text = head.querySelector( '.wpb-pagehead__text' );
			if ( text ) {
				var crumb = [ cfg.title, section ];
				var sub = document.querySelector( '.wpb-subnav__item.is-current' );
				if ( sub && sub.textContent.trim() && sub.textContent.trim() !== section ) {
					crumb.push( sub.textContent.trim() );
				}
				text.setAttribute( 'data-wpb-sf-crumb', '// ' + up( crumb.filter( Boolean ).join( '  ›  ' ) ) );
			}
			var idx = parseInt( cfg.index, 10 ) || 0;
			head.appendChild(
				el(
					'div',
					'wpb-sf-readout',
					'<div class="wpb-sf-ring"><small>' + esc( up( t.node || 'Node' ) ) + '</small><span>' + ( idx < 10 ? '0' : '' ) + idx + '</span></div>' +
					'<div class="wpb-sf-lines">' + esc( up( t.node || 'Node' ) ) + ' <b>' + esc( node() ) + '</b><br>' + esc( up( t.build || 'Build' ) ) + ' <b>' + esc( build() ) + '</b><br>' + esc( up( t.link || 'Link' ) ) + ' <b>' + esc( up( t.online || 'Online' ) ) + '</b> · <span class="wpb-sf-clock"></span></div>'
				)
			);
		}

		// Ruler under the section tabs (or under the page head).
		var after = document.querySelector( '.wpb-subnav' ) || head;
		if ( after ) {
			var labels = '';
			for ( var n = 0; n <= 600; n += 50 ) {
				labels += '<i>' + ( n < 100 ? ( n < 10 ? '00' : '0' ) : '' ) + n + '</i>';
			}
			var ruler = el( 'div', 'wpb-sf-ruler', '<span class="wpb-sf-chev">›››</span><div class="wpb-sf-ruler__scale"><div class="wpb-sf-ruler__labels">' + labels + '</div></div><span class="wpb-sf-chev">‹‹‹</span>' );
			after.parentNode.insertBefore( ruler, after.nextSibling );
			// Status tiles right under the ruler (the host's dashboard pages only).
			buildTiles( ruler.nextSibling || null );
		}

		// Frame around the screen with the status plate.
		var frame = el(
			'div',
			'wpb-sf-frame',
			'<span class="wpb-sf-c c-tl">' + corner() + '</span><span class="wpb-sf-c c-tr">' + corner() + '</span><span class="wpb-sf-c c-bl">' + corner() + '</span><span class="wpb-sf-c c-br">' + corner() + '</span>' +
			'<div class="wpb-sf-frame__tab"><span class="wpb-sf-chev">›››</span>' + esc( up( cfg.menu ) ) + ( build() ? ' · ' + esc( build() ) : '' ) + ' · <span class="wpb-sf-clock"></span> · <span class="' + ( parseInt( cfg.errors, 10 ) > 0 ? 'is-warn' : '' ) + '">' + esc( status() ) + '</span><span class="wpb-sf-chev">‹‹‹</span></div>'
		);
		document.body.appendChild( frame );

		clock();
		timer = window.setInterval( clock, 1000 );
		// The section tabs got wider: let the "More" menu measure again.
		try {
			window.dispatchEvent( new Event( 'resize' ) );
		} catch ( e ) {}
	}

	function removeHud() {
		while ( made.length ) {
			var e = made.pop();
			if ( e.parentNode ) {
				e.parentNode.removeChild( e );
			}
		}
		var text = document.querySelector( '[data-wpb-sf-crumb]' );
		if ( text ) {
			text.removeAttribute( 'data-wpb-sf-crumb' );
		}
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
		if ( poll ) {
			window.clearInterval( poll );
			poll = null;
		}
		try {
			window.dispatchEvent( new Event( 'resize' ) );
		} catch ( e ) {}
	}

	function sync() {
		if ( root.classList.contains( 'qa-scifi' ) && document.body && document.body.classList.contains( 'wpbot-design' ) ) {
			buildHud();
		} else if ( made.length ) {
			removeHud();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', sync );
	} else {
		sync();
	}
	document.addEventListener( 'qcld_admin_theme_change', sync );
} )();
