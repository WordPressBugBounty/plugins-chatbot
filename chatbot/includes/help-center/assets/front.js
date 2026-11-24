/**
 * Help Center pages: live search suggestions, "Ask" buttons that open the
 * chatbot, print and copy link, the current section in the table of contents,
 * the docs tree toggle on phones, helpful votes with a reason, view counting
 * and glossary popovers. No jQuery.
 */
( function () {
	'use strict';

	var cfg = window.qcldWpbotHelpCenter || {};
	var i18n = cfg.i18n || {};

	function chatButton() {
		return document.getElementById( 'wp-chatbot-ball' );
	}

	function format( text, value ) {
		return String( text || '' ).replace( '%s', value );
	}

	// "Ask" buttons and cards appear only when the chatbot is on the page.
	function showChat() {
		if ( ! chatButton() ) {
			return false;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-hc [data-needs-chat]' ), function ( n ) {
			n.hidden = false;
		} );
		return true;
	}

	function ask() {
		var ball = chatButton();
		if ( ball ) {
			ball.click();
		}
	}

	function live( form ) {
		var input = form.querySelector( 'input[type=search]' );
		var box = form.querySelector( '.wpbot-hc__live' );
		var status = form.querySelector( '[role=status]' );
		var timer = null;
		var last = '';
		if ( ! input || ! box || ! cfg.rest || ! window.fetch ) {
			return;
		}
		function close() {
			box.hidden = true;
			box.innerHTML = '';
		}
		function link( href, text ) {
			var a = document.createElement( 'a' );
			a.href = href;
			a.textContent = text;
			return a;
		}
		function render( q, items ) {
			box.innerHTML = '';
			if ( items.length ) {
				items.forEach( function ( item ) {
					var tmp = document.createElement( 'textarea' );
					tmp.innerHTML = item.title && item.title.rendered ? item.title.rendered : '';
					box.appendChild( link( item.link, tmp.value ) );
				} );
				box.appendChild( link( cfg.searchUrl + ( cfg.searchUrl.indexOf( '?' ) < 0 ? '?' : '&' ) + 's=' + encodeURIComponent( q ), format( i18n.all, q ) ) );
			} else {
				var p = document.createElement( 'p' );
				p.textContent = format( i18n.none, q );
				box.appendChild( p );
			}
			if ( chatButton() ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'is-ask';
				b.innerHTML = '<span class="wpbot-hc__dot" aria-hidden="true"></span>';
				b.appendChild( document.createTextNode( format( i18n.ask, q ) ) );
				b.addEventListener( 'click', function () {
					close();
					ask();
				} );
				box.appendChild( b );
			}
			box.hidden = false;
			if ( status ) {
				status.textContent = items.length ? items.length + ' ' + ( items.length === 1 ? 'suggestion' : 'suggestions' ) : format( i18n.none, q );
			}
		}
		input.addEventListener( 'input', function () {
			var q = input.value.trim();
			clearTimeout( timer );
			if ( q.length < 2 ) {
				close();
				return;
			}
			timer = setTimeout( function () {
				last = q;
				fetch( cfg.rest + '?search=' + encodeURIComponent( q ) + '&per_page=5&_fields=title,link', { credentials: 'same-origin' } )
					.then( function ( r ) {
						return r.ok ? r.json() : [];
					} )
					.then( function ( items ) {
						if ( last === q && input.value.trim() === q ) {
							render( q, Array.isArray( items ) ? items : [] );
						}
					} )
					.catch( close );
			}, 200 );
		} );
		form.addEventListener( 'keydown', function ( e ) {
			var options = Array.prototype.slice.call( box.querySelectorAll( 'a,button' ) );
			var at = options.indexOf( document.activeElement );
			if ( 'Escape' === e.key ) {
				close();
				input.focus();
			} else if ( 'ArrowDown' === e.key && ! box.hidden && options.length ) {
				e.preventDefault();
				options[ Math.min( at + 1, options.length - 1 ) ].focus();
			} else if ( 'ArrowUp' === e.key && at >= 0 ) {
				e.preventDefault();
				( at === 0 ? input : options[ at - 1 ] ).focus();
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! form.contains( e.target ) ) {
				close();
			}
		} );
	}

	function toc() {
		var links = Array.prototype.slice.call( document.querySelectorAll( '.wpbot-hc__toc:not(.is-inline) a[href^="#"]' ) );
		if ( ! links.length || ! window.IntersectionObserver ) {
			return;
		}
		var byId = {};
		links.forEach( function ( a ) {
			byId[ decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) ) ] = a;
		} );
		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting && byId[ entry.target.id ] ) {
						links.forEach( function ( a ) {
							a.removeAttribute( 'aria-current' );
						} );
						byId[ entry.target.id ].setAttribute( 'aria-current', 'true' );
					}
				} );
			},
			{ rootMargin: '0px 0px -70% 0px' }
		);
		Object.keys( byId ).forEach( function ( id ) {
			var h = document.getElementById( id );
			if ( h ) {
				observer.observe( h );
			}
		} );
	}

	function tree() {
		var nav = document.querySelector( '.wpbot-hc__tree' );
		var toggle = nav && nav.querySelector( '.wpbot-hc__tree-toggle' );
		var body = nav && nav.querySelector( '.wpbot-hc__tree-body' );
		if ( ! toggle || ! body || ! window.matchMedia || ! window.matchMedia( '(max-width: 640px)' ).matches ) {
			return;
		}
		nav.classList.add( 'is-collapsible' );
		toggle.hidden = false;
		body.hidden = true;
		toggle.addEventListener( 'click', function () {
			body.hidden = ! body.hidden;
			toggle.setAttribute( 'aria-expanded', body.hidden ? 'false' : 'true' );
		} );
	}

	function post( path, body ) {
		return fetch( cfg.api + path, {
			method: 'POST',
			credentials: 'omit',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( String( r.status ) );
			}
			return r.json();
		} );
	}

	function store( key, value ) {
		try {
			if ( undefined === value ) {
				return window.localStorage.getItem( key );
			}
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
		return null;
	}

	// Once per visit and article; the server skips editors and bots.
	function countView() {
		var id = cfg.post;
		if ( ! id || ! cfg.api || ! window.fetch || ! document.querySelector( '.wpbot-hc--single' ) ) {
			return;
		}
		try {
			var key = 'wpbot-hc-viewed-' + id;
			if ( window.sessionStorage.getItem( key ) ) {
				return;
			}
			window.sessionStorage.setItem( key, '1' );
		} catch ( e ) {}
		post( '/view', { post: id } ).catch( function () {} );
	}

	function feedback( box ) {
		var id = parseInt( box.getAttribute( 'data-feedback' ), 10 );
		var buttons = Array.prototype.slice.call( box.querySelectorAll( '[data-vote]' ) );
		var form = box.querySelector( '.wpbot-hc__fb-why' );
		var status = box.querySelector( '.wpbot-hc__fb-status' );
		var next = box.querySelector( '.wpbot-hc__fb-next' );
		var token = '';
		if ( ! id || ! cfg.api || ! window.fetch ) {
			return;
		}
		box.hidden = false;
		function pressed( value ) {
			buttons.forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b.getAttribute( 'data-vote' ) === value ? 'true' : 'false' );
			} );
		}
		function offerHelp() {
			// Only when there is a way to get help (the assistant on the page, a contact link or a Pro action).
			var ways = Array.prototype.filter.call( next.querySelectorAll( 'a,button' ), function ( n ) {
				return ! n.hidden;
			} );
			next.hidden = ! ways.length;
		}
		var earlier = store( 'wpbot-hc-vote-' + id );
		if ( earlier ) {
			pressed( earlier );
		}
		buttons.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var value = b.getAttribute( 'data-vote' );
				pressed( value );
				status.textContent = '';
				post( '/vote', { post: id, value: value } )
					.then( function ( data ) {
						store( 'wpbot-hc-vote-' + id, value );
						token = data && data.token ? data.token : '';
						if ( 'no' === value ) {
							form.hidden = false;
							var first = form.querySelector( 'input' );
							if ( first ) {
								first.focus();
							}
						} else {
							form.hidden = true;
							next.hidden = true;
							status.textContent = i18n.thanks || '';
						}
					} )
					.catch( function () {
						pressed( '' );
						status.textContent = i18n.failed || '';
					} );
			} );
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var choice = form.querySelector( 'input[name=reason]:checked' );
			var comment = form.querySelector( 'textarea' );
			var words = comment ? comment.value.trim() : '';
			if ( ! choice && ! words ) {
				status.textContent = i18n.whyHint || '';
				return;
			}
			post( '/feedback', { post: id, token: token, reason: choice ? choice.value : '', comment: words } )
				.then( function () {
					form.hidden = true;
					status.textContent = i18n.sent || '';
					offerHelp();
				} )
				.catch( function () {
					status.textContent = i18n.failed || '';
				} );
		} );
	}

	// Glossary terms: the definition in a bubble on hover and keyboard focus; Escape closes it.
	// A first tap on a touch screen shows the bubble, a second follows the link.
	function glossary() {
		var terms = Array.prototype.slice.call( document.querySelectorAll( '.wpbot-hc a.wpbot-hc-term' ) );
		if ( ! terms.length ) {
			return;
		}
		var bubble = document.createElement( 'div' );
		bubble.className = 'wpbot-hc-term-pop';
		bubble.setAttribute( 'aria-hidden', 'true' );
		bubble.hidden = true;
		( document.querySelector( '.wpbot-hc' ) || document.body ).appendChild( bubble );
		var current = null;
		var hideTimer = null;
		var touched = null;
		function show( a ) {
			var def = document.getElementById( a.getAttribute( 'aria-describedby' ) );
			if ( ! def ) {
				return;
			}
			clearTimeout( hideTimer );
			current = a;
			bubble.innerHTML = '';
			var strong = document.createElement( 'strong' );
			strong.textContent = a.getAttribute( 'data-term' ) || a.textContent;
			bubble.appendChild( strong );
			bubble.appendChild( document.createTextNode( ' ' + def.textContent ) );
			bubble.hidden = false;
			// The bubble is position: fixed, so viewport coordinates are used as they are.
			var r = a.getBoundingClientRect();
			var left = Math.max( 8, Math.min( r.left, document.documentElement.clientWidth - bubble.offsetWidth - 8 ) );
			var below = r.bottom + bubble.offsetHeight + 8 < window.innerHeight;
			bubble.style.left = left + 'px';
			bubble.style.top = ( below ? r.bottom + 6 : r.top - bubble.offsetHeight - 6 ) + 'px';
		}
		function hide() {
			bubble.hidden = true;
			current = null;
			touched = null;
		}
		function later() {
			clearTimeout( hideTimer );
			hideTimer = setTimeout( hide, 200 );
		}
		terms.forEach( function ( a ) {
			a.addEventListener( 'mouseenter', function () {
				show( a );
			} );
			a.addEventListener( 'mouseleave', later );
			a.addEventListener( 'focus', function () {
				show( a );
			} );
			a.addEventListener( 'blur', later );
			a.addEventListener( 'pointerdown', function ( e ) {
				// Remember whether the bubble was already open before focus opens it.
				touched = 'touch' === e.pointerType ? { link: a, open: current === a && ! bubble.hidden } : null;
			} );
			a.addEventListener( 'click', function ( e ) {
				if ( touched && touched.link === a && ! touched.open ) {
					e.preventDefault();
					show( a );
				}
				touched = null;
			} );
		} );
		bubble.addEventListener( 'mouseenter', function () {
			clearTimeout( hideTimer );
		} );
		bubble.addEventListener( 'mouseleave', later );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! bubble.hidden ) {
				hide();
			}
		} );
		window.addEventListener( 'scroll', function () {
			if ( current ) {
				show( current );
			}
		}, { passive: true } );
	}

	function init() {
		if ( ! showChat() ) {
			// The launcher can arrive a little later.
			var tries = 0;
			var wait = setInterval( function () {
				if ( showChat() || ++tries > 20 ) {
					clearInterval( wait );
				}
			}, 250 );
		}
		document.addEventListener( 'click', function ( e ) {
			var t = e.target.closest ? e.target.closest( '[data-ask],[data-print],[data-copy-link],[data-launcher]' ) : null;
			if ( ! t || ! t.closest( '.wpbot-hc' ) ) {
				return;
			}
			if ( t.hasAttribute( 'data-launcher' ) ) {
				// The help button opens the assistant when it is on the page; otherwise the link goes to the Help Center.
				if ( chatButton() ) {
					e.preventDefault();
					ask();
				}
			} else if ( t.hasAttribute( 'data-ask' ) ) {
				ask();
			} else if ( t.hasAttribute( 'data-print' ) ) {
				window.print();
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( window.location.href.split( '#' )[ 0 ] ).then( function () {
					var label = t.textContent;
					t.textContent = t.getAttribute( 'data-done' ) || label;
					setTimeout( function () {
						t.textContent = label;
					}, 1500 );
				} );
			}
		} );
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-hc__search[data-live]' ), live );
		toc();
		tree();
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-hc [data-feedback]' ), feedback );
		countView();
		glossary();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
