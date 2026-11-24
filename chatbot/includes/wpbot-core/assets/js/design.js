/**
 * WPBot admin design (Admin\Design): the Notices bell, "More" menus for tabs that do not fit,
 * and the band's search button (WordPress's command palette, with WPBot's pages as commands).
 *
 * Notices: WPBot Core prints the page's admin notices in a hidden holder. This script runs
 * before WordPress's own script (which moves notices under the page header on document ready),
 * moves each notice into the bell (or, for feedback on the user's own action, into the strip
 * under the band) and marks it "inline" so WordPress leaves it there.
 */
( function () {
	'use strict';
	var cfg = window.qcldWpbotDesign || { tabs: [], i18n: {} };
	var t = cfg.i18n || {};
	var band = document.querySelector( '.wpb-band' );

	/* ------------------------------------------------------------ */
	/* Notices                                                      */
	/* ------------------------------------------------------------ */

	var list = document.querySelector( '.wpb-notices__list' );
	var strip = document.querySelector( '.wpb-strip-slot' );
	var bell = document.querySelector( '.wpb-notices__bell' );
	var panel = document.getElementById( 'wpb-notices-panel' );
	var count = document.querySelector( '.wpb-notices__count' );

	function isStrip( el ) {
		var c = el.classList;
		return c.contains( 'wpb-strip' ) || c.contains( 'notice-success' ) || ( c.contains( 'updated' ) && ! c.contains( 'update-nag' ) );
	}

	function isEmpty( el ) {
		if ( el.nodeType !== 1 || /^(SCRIPT|STYLE|LINK|TEMPLATE|NOSCRIPT)$/.test( el.tagName ) ) {
			return true;
		}
		return ! el.textContent.trim() && ! el.querySelector( 'img, iframe, button, a, input' );
	}

	function isHidden( el ) {
		return el.hidden || el.classList.contains( 'hidden' ) || /display\s*:\s*none/i.test( el.getAttribute( 'style' ) || '' );
	}

	function markInline( el ) {
		el.classList.add( 'inline' );
		var inner = el.querySelectorAll( 'div.updated, div.error, div.notice' );
		for ( var i = 0; i < inner.length; i++ ) {
			inner[ i ].classList.add( 'inline' );
		}
	}

	function updateCount() {
		if ( ! list || ! count ) {
			return;
		}
		var n = 0;
		for ( var i = 0; i < list.children.length; i++ ) {
			if ( ! isHidden( list.children[ i ] ) ) {
				n++;
			}
		}
		count.textContent = String( n );
		count.hidden = n === 0;
		if ( bell ) {
			bell.setAttribute( 'title', n ? ( t.count || '%d notices' ).replace( '%d', n ) : ( t.notices || 'Notices' ) );
		}
	}

	function collect() {
		var holders = document.querySelectorAll( '.wpb-notice-holder' );
		var stripUsed = !! ( strip && strip.children.length );
		for ( var h = 0; h < holders.length; h++ ) {
			var holder = holders[ h ];
			var kids = Array.prototype.slice.call( holder.children );
			for ( var k = 0; k < kids.length; k++ ) {
				var el = kids[ k ];
				if ( /^(SCRIPT|STYLE|LINK|TEMPLATE|NOSCRIPT)$/.test( el.tagName ) ) {
					continue; // Already run; leave in place.
				}
				if ( isEmpty( el ) ) {
					continue;
				}
				markInline( el );
				if ( strip && ! stripUsed && isStrip( el ) ) {
					strip.appendChild( el );
					stripUsed = true;
				} else if ( list ) {
					list.appendChild( el );
				}
			}
		}
		updateCount();
	}

	if ( list ) {
		collect();
		if ( window.MutationObserver ) {
			new window.MutationObserver( updateCount ).observe( list, { childList: true, subtree: false, attributes: true, attributeFilter: [ 'class', 'style', 'hidden' ] } );
		}
	}

	function setPanel( open ) {
		if ( ! panel || ! bell ) {
			return;
		}
		panel.hidden = ! open;
		bell.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}

	if ( bell && panel ) {
		bell.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			setPanel( panel.hidden );
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! panel.hidden && ! panel.contains( e.target ) && e.target !== bell ) {
				setPanel( false );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! panel.hidden ) {
				setPanel( false );
				bell.focus();
			}
		} );
	}

	/* ------------------------------------------------------------ */
	/* "More" menus for tabs that do not fit                        */
	/* ------------------------------------------------------------ */

	var menus = [];

	function setupOverflow( row ) {
		if ( row.getAttribute( 'data-wpb-overflow-ready' ) ) {
			return;
		}
		row.setAttribute( 'data-wpb-overflow-ready', '1' );
		var more = document.createElement( row.tagName === 'UL' || row.tagName === 'OL' ? 'li' : 'div' );
		more.className = 'wpb-more';
		more.hidden = true;
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.innerHTML = '<span></span> <span aria-hidden="true">&#9662;</span>';
		btn.firstChild.textContent = t.more || 'More';
		var menu = document.createElement( row.tagName === 'UL' || row.tagName === 'OL' ? 'ul' : 'div' );
		menu.className = 'wpb-more__menu';
		menu.hidden = true;
		more.appendChild( btn );
		more.appendChild( menu );
		row.appendChild( more );
		btn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			menu.hidden = ! menu.hidden;
			btn.setAttribute( 'aria-expanded', menu.hidden ? 'false' : 'true' );
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! more.contains( e.target ) ) {
				menu.hidden = true;
				btn.setAttribute( 'aria-expanded', 'false' );
			}
		} );
		var items = Array.prototype.filter.call( row.children, function ( el ) {
			return el !== more;
		} );
		menus.push( { row: row, more: more, menu: menu, btn: btn, items: items } );
	}

	function isCurrent( el ) {
		return el.matches( '.is-current, .is-active, [aria-current="page"]' ) || !! el.querySelector( '.is-current, .is-active, [aria-current="page"]' );
	}

	function fits( m ) {
		return m.row.scrollWidth <= m.row.clientWidth + 1;
	}

	function layout( m ) {
		// Everything back in the row, in order, before the More button.
		m.items.forEach( function ( el ) {
			m.row.insertBefore( el, m.more );
		} );
		m.more.hidden = true;
		if ( window.innerWidth <= 782 || fits( m ) ) {
			return;
		}
		m.more.hidden = false;
		var moved = [];
		for ( var i = m.items.length - 1; i >= 0 && ! fits( m ); i-- ) {
			if ( isCurrent( m.items[ i ] ) ) {
				continue;
			}
			moved.unshift( m.items[ i ] );
			m.menu.insertBefore( m.items[ i ], m.menu.firstChild );
		}
		if ( ! moved.length ) {
			m.more.hidden = true;
		}
	}

	function layoutAll() {
		menus.forEach( layout );
	}

	var rows = document.querySelectorAll( '[data-wpb-overflow], body.wpbot-design .qcwpbot-shell__menu' );
	for ( var r = 0; r < rows.length; r++ ) {
		setupOverflow( rows[ r ] );
	}
	if ( menus.length ) {
		layoutAll();
		var timer;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( layoutAll, 120 );
		} );
		// Fonts and late styles can change widths.
		window.addEventListener( 'load', layoutAll );
	}

	/* ------------------------------------------------------------ */
	/* Section tabs: the light trace                                 */
	/* ------------------------------------------------------------ */

	/*
	 * A thin line that runs from the open tab to the tab under the pointer or keyboard focus:
	 * its front edge moves first and the back edge follows (owner, 22 November 2026). Light and
	 * Dark show it only while it moves away from the open tab; the GRID always shows it
	 * (scifi.css). Added after the overflow menus so it is never counted as a tab.
	 */
	var traces = [];
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var EASE = 'cubic-bezier(.3,.7,.2,1)';

	function traceItems( tr ) {
		return Array.prototype.filter.call( tr.row.children, function ( el ) {
			return el !== tr.line && ! el.classList.contains( 'wpb-more' ) && ! el.hidden;
		} );
	}

	function traceCurrent( tr ) {
		var items = traceItems( tr );
		for ( var i = 0; i < items.length; i++ ) {
			if ( isCurrent( items[ i ] ) ) {
				return items[ i ];
			}
		}
		return null;
	}

	function traceTo( tr, el, instant ) {
		if ( ! el || ! el.offsetWidth ) {
			return;
		}
		var x = el.offsetLeft;
		var right = tr.row.clientWidth - ( x + el.offsetWidth );
		var goingRight = tr.x === null || x > tr.x;
		if ( instant || still ) {
			tr.line.style.transition = 'none';
		} else {
			tr.line.style.transition = 'left .22s ' + EASE + ' ' + ( goingRight ? '.11s' : '0s' ) + ', right .22s ' + EASE + ' ' + ( goingRight ? '0s' : '.11s' ) + ', opacity .2s';
		}
		tr.line.style.left = x + 'px';
		tr.line.style.right = right + 'px';
		if ( tr.x !== null && x !== tr.x ) {
			tr.line.classList.toggle( 'is-left', ! goingRight );
		}
		if ( instant || still ) {
			void tr.line.offsetWidth;
			tr.line.style.transition = '';
		}
		tr.x = x;
	}

	function traceEnter( tr, el ) {
		var cur = traceCurrent( tr );
		window.clearTimeout( tr.off );
		if ( ! tr.line.classList.contains( 'is-on' ) ) {
			if ( el === cur ) {
				return;
			}
			traceTo( tr, cur, true );
			tr.line.classList.add( 'is-on' );
			void tr.line.offsetWidth;
		}
		traceTo( tr, el );
		if ( el === cur ) {
			traceOff( tr );
		}
	}

	function traceOff( tr ) {
		window.clearTimeout( tr.off );
		tr.off = window.setTimeout( function () {
			tr.line.classList.remove( 'is-on' );
		}, still ? 0 : 340 );
	}

	function traceRest( tr ) {
		traceTo( tr, traceCurrent( tr ) );
		traceOff( tr );
	}

	function setupTrace( row ) {
		if ( row.querySelector( ':scope > .wpb-subnav__trace' ) ) {
			return;
		}
		var line = document.createElement( row.tagName === 'UL' || row.tagName === 'OL' ? 'li' : 'span' );
		line.className = 'wpb-subnav__trace';
		line.setAttribute( 'aria-hidden', 'true' );
		row.appendChild( line );
		var tr = { row: row, line: line, x: null, off: 0 };
		traces.push( tr );
		function itemOf( target ) {
			var items = traceItems( tr );
			for ( var i = 0; i < items.length; i++ ) {
				if ( items[ i ] === target || items[ i ].contains( target ) ) {
					return items[ i ];
				}
			}
			return null;
		}
		row.addEventListener( 'mouseover', function ( e ) {
			var el = itemOf( e.target );
			if ( el ) {
				traceEnter( tr, el );
			}
		} );
		row.addEventListener( 'mouseleave', function () {
			traceRest( tr );
		} );
		row.addEventListener( 'focusin', function ( e ) {
			var el = itemOf( e.target );
			if ( el ) {
				traceEnter( tr, el );
			}
		} );
		row.addEventListener( 'focusout', function ( e ) {
			if ( ! row.contains( e.relatedTarget ) ) {
				traceRest( tr );
			}
		} );
		traceTo( tr, traceCurrent( tr ), true );
	}

	function placeTraces() {
		traces.forEach( function ( tr ) {
			tr.x = null;
			traceTo( tr, traceCurrent( tr ), true );
		} );
	}

	var traceRows = document.querySelectorAll( '.wpb-subnav__list, body.wpbot-design .qcwpbot-shell__menu' );
	for ( var tq = 0; tq < traceRows.length; tq++ ) {
		setupTrace( traceRows[ tq ] );
	}
	if ( traces.length ) {
		var traceTimer;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( traceTimer );
			traceTimer = window.setTimeout( placeTraces, 160 );
		} );
		window.addEventListener( 'load', placeTraces );
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( placeTraces );
		}
	}

	/* ------------------------------------------------------------ */
	/* Search: WordPress's command palette                          */
	/* ------------------------------------------------------------ */

	function palette() {
		var wp = window.wp;
		return wp && wp.data && wp.data.select && wp.data.dispatch && wp.data.select( 'core/commands' ) ? wp.data.dispatch( 'core/commands' ) : null;
	}

	function setupSearch() {
		var commands = palette();
		var search = band ? band.querySelector( '.wpb-band__search' ) : null;
		if ( ! commands || ! search ) {
			return false;
		}
		if ( /Mac|iPhone|iPad/.test( window.navigator.platform || '' ) ) {
			var kbd = search.querySelector( 'kbd' );
			if ( kbd ) {
				kbd.textContent = '⌘K';
			}
		}
		search.hidden = false;
		search.addEventListener( 'click', function () {
			commands.open();
		} );
		( cfg.tabs || [] ).forEach( function ( tab, i ) {
			try {
				commands.registerCommand( {
					name: 'wpbot/page-' + i,
					label: ( t.go || 'Go to' ) + ' ' + tab.label,
					searchLabel: 'WPBot ' + tab.label,
					callback: function ( args ) {
						if ( args && args.close ) {
							args.close();
						}
						window.location.href = tab.url;
					},
				} );
			} catch ( e ) {}
		} );
		return true;
	}

	if ( ! setupSearch() ) {
		window.addEventListener( 'load', setupSearch );
	}
}() );
