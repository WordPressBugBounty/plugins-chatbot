/**
 * AI spending: the (i) details panel and the one-click raise (AI Settings, AI Usage).
 * Data comes from WPBot Core's REST routes; everything is written with textContent.
 */
( function () {
	'use strict';
	var cfg = window.qcldWpbotSpend;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;

	function el( tag, text, cls ) {
		var node = document.createElement( tag );
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		if ( cls ) {
			node.className = cls;
		}
		return node;
	}

	function money( value, digits ) {
		return '$' + Number( value || 0 ).toFixed( digits || 4 );
	}

	function request( path, method, body ) {
		return fetch( cfg.root + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( String( res.status ) );
			}
			return res.json();
		} );
	}

	function table( headers, rows ) {
		var wrap = el( 'div', null, 'qcwp-table-wrap' );
		var tbl = el( 'table', null, 'qcwp-table' );
		var head = el( 'tr' );
		headers.forEach( function ( h ) {
			head.appendChild( el( 'th', h ) );
		} );
		var thead = el( 'thead' );
		thead.appendChild( head );
		tbl.appendChild( thead );
		var tbody = el( 'tbody' );
		rows.forEach( function ( cells ) {
			var tr = el( 'tr' );
			cells.forEach( function ( c ) {
				tr.appendChild( el( 'td', c ) );
			} );
			tbody.appendChild( tr );
		} );
		tbl.appendChild( tbody );
		wrap.appendChild( tbl );
		return wrap;
	}

	function trend( days ) {
		var max = 0;
		days.forEach( function ( d ) {
			max = Math.max( max, Number( d.cost ) );
		} );
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 ' + days.length * 14 + ' 60' );
		svg.setAttribute( 'class', 'qcwp-spend-chart is-small' );
		svg.setAttribute( 'role', 'img' );
		svg.setAttribute( 'aria-label', t.trend );
		days.forEach( function ( d, i ) {
			var h = max > 0 ? Math.max( 1, Math.round( Number( d.cost ) / max * 55 ) ) : 1;
			var r = document.createElementNS( ns, 'rect' );
			r.setAttribute( 'x', i * 14 + 2 );
			r.setAttribute( 'y', 60 - h );
			r.setAttribute( 'width', 10 );
			r.setAttribute( 'height', h );
			var title = document.createElementNS( ns, 'title' );
			title.textContent = d.day + ': ' + money( d.cost ) + ', ' + d.calls;
			r.appendChild( title );
			svg.appendChild( r );
		} );
		return svg;
	}

	function render( body, data ) {
		body.textContent = '';
		if ( ! data.tasks.length ) {
			body.appendChild( el( 'p', t.none, 'qcwp-empty' ) );
		} else {
			body.appendChild( el( 'h3', t.tasks ) );
			body.appendChild( table( [ t.task, t.calls, t.tokens, t.cost, t.model, t.lastRun ], data.tasks.map( function ( r ) {
				return [ r.label, r.calls, Number( r.tokens_in ) + Number( r.tokens_out ), r.unknown_price > 0 && Number( r.cost ) === 0 ? t.unknown : money( r.cost ), r.model, r.last_run ];
			} ) ) );
			body.appendChild( el( 'h3', t.topRuns ) );
			body.appendChild( table( [ t.when, t.task, t.model, t.tokens, t.cost ], data.top_runs.map( function ( r ) {
				return [ r.created_at, r.label, r.model, Number( r.tokens_in ) + Number( r.tokens_out ), r.price_known ? money( r.cost ) : t.unknown ];
			} ) ) );
		}
		body.appendChild( el( 'h3', t.trend ) );
		body.appendChild( trend( data.trend || [] ) );
		[ [ 'channels', t.channels ], [ 'providers', t.provider ] ].forEach( function ( pair ) {
			if ( data[ pair[ 0 ] ] && data[ pair[ 0 ] ].length ) {
				body.appendChild( el( 'h3', pair[ 1 ] ) );
				body.appendChild( table( [ pair[ 1 ], t.calls, t.cost ], data[ pair[ 0 ] ].map( function ( r ) {
					return [ r.name, r.calls, money( r.cost ) ];
				} ) ) );
			}
		} );
	}

	var dialog = document.getElementById( 'qcwp-spend-dialog' );
	var opener = null;

	document.addEventListener( 'click', function ( event ) {
		var info = event.target.closest ? event.target.closest( '[data-spend-group]' ) : null;
		var raise = event.target.closest ? event.target.closest( '[data-spend-raise]' ) : null;
		var close = event.target.closest ? event.target.closest( '[data-spend-close]' ) : null;
		if ( info && dialog ) {
			opener = info;
			var group = info.getAttribute( 'data-spend-group' );
			var card = document.getElementById( 'spend-' + group );
			dialog.querySelector( 'h2' ).textContent = card ? card.querySelector( '.qcwp-spend-name' ).textContent : group;
			var body = dialog.querySelector( '.qcwp-spend-dialog-body' );
			body.textContent = t.loading;
			if ( dialog.showModal ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', 'open' );
			}
			request( 'spend/group/' + encodeURIComponent( group ) ).then( function ( data ) {
				render( body, data );
			} ).catch( function () {
				body.textContent = t.failed;
			} );
		}
		if ( raise ) {
			raise.disabled = true;
			request( 'spend/raise', 'POST', { scope: raise.getAttribute( 'data-spend-raise' ) } ).then( function () {
				raise.replaceWith( el( 'span', t.raised, 'qcwp-spend-state' ) );
				window.setTimeout( function () {
					window.location.reload();
				}, 800 );
			} ).catch( function () {
				raise.disabled = false;
			} );
		}
		if ( close && dialog ) {
			dialog.close ? dialog.close() : dialog.removeAttribute( 'open' );
		}
	} );

	if ( dialog ) {
		dialog.addEventListener( 'close', function () {
			if ( opener ) {
				opener.focus();
			}
		} );
	}

	// Period: show the reset-day field that applies.
	var period = document.getElementById( 'qcwp-spend-period' );
	function syncPeriod() {
		var weekly = period && 'weekly' === period.value;
		document.querySelectorAll( '.qcwp-spend-monthly' ).forEach( function ( n ) {
			n.hidden = weekly;
		} );
		document.querySelectorAll( '.qcwp-spend-weekly' ).forEach( function ( n ) {
			n.hidden = ! weekly;
		} );
	}
	if ( period ) {
		period.addEventListener( 'change', syncPeriod );
		syncPeriod();
	}
}() );
