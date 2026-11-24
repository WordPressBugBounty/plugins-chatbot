/**
 * Search settings and pages (includes/admin/class-qcld-wpbot-settings-search.php).
 *
 * Opens from the band's search button and Ctrl+K / Cmd+K on WPBot pages, in place of WordPress's
 * command palette. Matches on every key: every word of the search must start a word of the title,
 * keywords or place (one typo allowed in longer words); titles weigh most. Also lists the settings
 * on the open page and scrolls to the one chosen.
 */
( function () {
	'use strict';

	var cfg = window.qcldWpbotSearch || {};
	var t = cfg.text || {};
	var MAX = 12;
	var dialog = null;
	var input = null;
	var list = null;
	var results = [];
	var active = 0;
	var lastFocus = null;
	var pagePool = [];

	function norm( s ) {
		s = String( s || '' ).toLowerCase().replace( /&amp;|&/g, ' and ' );
		if ( s.normalize ) {
			s = s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		return s.replace( /[^a-z0-9À-￿]+/g, ' ' ).trim();
	}

	function words( s ) {
		var n = norm( s );
		return n ? n.split( ' ' ) : [];
	}

	// One letter wrong, missing, extra or swapped.
	function near( a, b ) {
		if ( Math.abs( a.length - b.length ) > 1 ) {
			return false;
		}
		var i = 0;
		var j = 0;
		var edits = 0;
		while ( i < a.length && j < b.length ) {
			if ( a[ i ] === b[ j ] ) {
				i++;
				j++;
				continue;
			}
			if ( ++edits > 1 ) {
				return false;
			}
			if ( a[ i + 1 ] === b[ j ] && a[ i ] === b[ j + 1 ] ) {
				i += 2;
				j += 2;
			} else if ( a.length > b.length ) {
				i++;
			} else if ( b.length > a.length ) {
				j++;
			} else {
				i++;
				j++;
			}
		}
		return edits + ( a.length - i ) + ( b.length - j ) <= 1;
	}

	function prep( e ) {
		e._t = words( e.t );
		e._k = words( e.k );
		e._c = words( e.c );
		e._full = norm( e.t );
		return e;
	}

	var index = ( cfg.index || [] ).map( prep );

	function wordScore( q, list, prefixPts, partPts ) {
		var best = 0;
		for ( var i = 0; i < list.length; i++ ) {
			var w = list[ i ];
			if ( w.indexOf( q ) === 0 ) {
				best = Math.max( best, w === q ? prefixPts + 2 : prefixPts );
			} else if ( q.length > 2 && w.indexOf( q ) > 0 ) {
				best = Math.max( best, partPts );
			} else if ( q.length > 3 && near( q, w.slice( 0, q.length + 1 ) ) ) {
				best = Math.max( best, Math.floor( partPts / 2 ) + 1 );
			}
		}
		return best;
	}

	function score( e, qs, qfull ) {
		var total = 0;
		for ( var i = 0; i < qs.length; i++ ) {
			var s = Math.max( wordScore( qs[ i ], e._t, 10, 6 ), wordScore( qs[ i ], e._k, 6, 3 ), wordScore( qs[ i ], e._c, 5, 2 ) );
			if ( ! s ) {
				return 0;
			}
			total += s;
		}
		if ( e._full.indexOf( qfull ) === 0 ) {
			total += 15;
		} else if ( e._full.indexOf( qfull ) > 0 ) {
			total += 6;
		}
		return total;
	}

	// The settings on the open page: section titles and field labels people can see.
	function pageEntries() {
		var root = document.getElementById( 'wpbody-content' ) || document.body;
		var nodes = root.querySelectorAll( 'h2, h3, h4, .qc-opt-title, .qcwp-label, label, th, legend' );
		var seen = {};
		var out = [];
		for ( var i = 0; i < nodes.length && out.length < 400; i++ ) {
			var n = nodes[ i ];
			if ( ! n.offsetParent || n.closest( '.qcld-wpbot-ss' ) ) {
				continue;
			}
			var text = ( n.textContent || '' ).replace( /\s+/g, ' ' ).trim();
			if ( text.length < 3 || text.length > 90 || seen[ text ] ) {
				continue;
			}
			seen[ text ] = true;
			out.push( prep( { t: text, c: t.onPage || 'On this page', k: '', u: '', el: n } ) );
		}
		return out;
	}

	function search( q ) {
		var qs = words( q );
		var qfull = norm( q );
		var pool = index.concat( pagePool );
		if ( ! qs.length ) {
			return index.slice( 0, 8 );
		}
		var scored = [];
		var seen = {};
		pool.forEach( function ( e, n ) {
			var s = score( e, qs, qfull );
			var key = e._full + '|' + ( e.u || 'page' );
			if ( s && ! seen[ key ] ) {
				seen[ key ] = true;
				scored.push( { e: e, s: e.el ? s - 1 : s, n: n } );
			}
		} );
		scored.sort( function ( a, b ) {
			return b.s - a.s || a.n - b.n;
		} );
		return scored.slice( 0, MAX ).map( function ( r ) {
			return r.e;
		} );
	}

	function render() {
		list.innerHTML = '';
		if ( ! results.length ) {
			var none = document.createElement( 'li' );
			none.className = 'qcld-wpbot-ss__none';
			none.textContent = t.none || 'Nothing found.';
			list.appendChild( none );
			input.removeAttribute( 'aria-activedescendant' );
			return;
		}
		results.forEach( function ( e, i ) {
			var li = document.createElement( 'li' );
			li.id = 'qcld-wpbot-ss-' + i;
			li.className = 'qcld-wpbot-ss__item' + ( i === active ? ' is-active' : '' );
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
			var title = document.createElement( 'span' );
			title.className = 'qcld-wpbot-ss__title';
			title.textContent = e.t;
			var where = document.createElement( 'span' );
			where.className = 'qcld-wpbot-ss__where';
			where.textContent = e.c;
			li.appendChild( title );
			li.appendChild( where );
			li.addEventListener( 'mousemove', function () {
				if ( active !== i ) {
					active = i;
					mark();
				}
			} );
			li.addEventListener( 'click', function () {
				go( e );
			} );
			list.appendChild( li );
		} );
		input.setAttribute( 'aria-activedescendant', 'qcld-wpbot-ss-' + active );
	}

	function mark() {
		var items = list.querySelectorAll( '.qcld-wpbot-ss__item' );
		for ( var i = 0; i < items.length; i++ ) {
			items[ i ].classList.toggle( 'is-active', i === active );
			items[ i ].setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
		}
		if ( items[ active ] ) {
			items[ active ].scrollIntoView( { block: 'nearest' } );
			input.setAttribute( 'aria-activedescendant', items[ active ].id );
		}
	}

	function go( e ) {
		if ( ! e ) {
			return;
		}
		if ( e.el ) {
			close();
			e.el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			e.el.classList.add( 'qcld-wpbot-ss-flash' );
			setTimeout( function () {
				e.el.classList.remove( 'qcld-wpbot-ss-flash' );
			}, 2200 );
			return;
		}
		window.location.href = e.u;
	}

	function update() {
		results = search( input.value );
		active = 0;
		render();
	}

	function build() {
		dialog = document.createElement( 'div' );
		dialog.className = 'qcld-wpbot-ss';
		dialog.hidden = true;
		dialog.innerHTML = '<div class="qcld-wpbot-ss__scrim"></div><div class="qcld-wpbot-ss__box" role="dialog" aria-modal="true"><div class="qcld-wpbot-ss__bar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="text" class="qcld-wpbot-ss__input" role="combobox" aria-expanded="true" aria-controls="qcld-wpbot-ss-list" autocomplete="off" spellcheck="false"><button type="button" class="qcld-wpbot-ss__close">Esc</button></div><ul class="qcld-wpbot-ss__list" id="qcld-wpbot-ss-list" role="listbox"></ul><p class="qcld-wpbot-ss__hint"></p></div>';
		document.body.appendChild( dialog );
		input = dialog.querySelector( 'input' );
		list = dialog.querySelector( 'ul' );
		var box = dialog.querySelector( '.qcld-wpbot-ss__box' );
		box.setAttribute( 'aria-label', t.label || 'Search' );
		input.setAttribute( 'placeholder', t.placeholder || 'Search settings and pages' );
		input.setAttribute( 'aria-label', t.label || 'Search' );
		list.setAttribute( 'aria-label', t.label || 'Search' );
		dialog.querySelector( '.qcld-wpbot-ss__hint' ).textContent = t.hint || '';
		dialog.querySelector( '.qcld-wpbot-ss__close' ).setAttribute( 'aria-label', t.close || 'Close' );
		dialog.querySelector( '.qcld-wpbot-ss__close' ).addEventListener( 'click', close );
		dialog.querySelector( '.qcld-wpbot-ss__scrim' ).addEventListener( 'click', close );
		input.addEventListener( 'input', update );
		input.addEventListener( 'keydown', function ( ev ) {
			if ( 'ArrowDown' === ev.key || 'ArrowUp' === ev.key ) {
				ev.preventDefault();
				if ( results.length ) {
					active = ( active + ( 'ArrowDown' === ev.key ? 1 : -1 ) + results.length ) % results.length;
					mark();
				}
			} else if ( 'Enter' === ev.key ) {
				ev.preventDefault();
				go( results[ active ] );
			} else if ( 'Escape' === ev.key ) {
				ev.preventDefault();
				close();
			} else if ( 'Tab' === ev.key ) {
				ev.preventDefault(); // Focus stays in the search while it is open.
			}
		} );
	}

	function open() {
		if ( ! dialog ) {
			build();
		}
		lastFocus = document.activeElement;
		dialog.hidden = false;
		document.documentElement.classList.add( 'qcld-wpbot-ss-open' );
		input.value = '';
		pagePool = pageEntries(); // Read once per opening: typing stays fast.
		update();
		input.focus();
	}

	function close() {
		if ( ! dialog || dialog.hidden ) {
			return;
		}
		dialog.hidden = true;
		document.documentElement.classList.remove( 'qcld-wpbot-ss-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	// The band's button: ours, before WordPress's palette sees the click.
	document.addEventListener(
		'click',
		function ( ev ) {
			var btn = ev.target && ev.target.closest ? ev.target.closest( '.wpb-band__search' ) : null;
			if ( btn ) {
				ev.preventDefault();
				ev.stopPropagation();
				open();
			}
		},
		true
	);
	window.addEventListener(
		'keydown',
		function ( ev ) {
			if ( ( ev.ctrlKey || ev.metaKey ) && ! ev.altKey && ! ev.shiftKey && 'k' === String( ev.key ).toLowerCase() ) {
				ev.preventDefault();
				ev.stopImmediatePropagation();
				if ( dialog && ! dialog.hidden ) {
					close();
				} else {
					open();
				}
			}
		},
		true
	);

	function showButton() {
		var btn = document.querySelector( '.wpb-band__search' );
		if ( btn ) {
			btn.hidden = false; // WPBot Core shows it only when WordPress's palette is there.
		}
	}
	showButton();
	window.addEventListener( 'load', showButton );

	window.qcldWpbotSettingsSearch = { open: open, close: close, search: search };
}() );
