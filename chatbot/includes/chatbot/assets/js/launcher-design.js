/**
 * New chat button design: floating box behaviour. Plain JavaScript, no dependencies; printed
 * inline by Widget\Launcher_Design. Config: the JSON in <script id="qcld-wpbot-design-config">.
 *
 * - Places the floating box from the button's real position (--qcld-wpbot-bx / --qcld-wpbot-by).
 * - Gathers the close button, greeting and message into one card (the hosts' own elements are
 *   moved, not copied, so their click handlers keep working).
 * - Ask bar layout: adds a "Type your question" bar that opens the chat and puts the cursor in the
 *   message field. Side menu layout: the quick menu stacks up beside the button. Card layout: the
 *   quick menu goes inside the card. Pills and chat preview: the quick menu floats under the
 *   message (the CSS turns the greeting and the message into bubbles for the chat preview).
 * - Typing dots before the message, the first time the box shows.
 * - The box slides in when it really shows (qcld-wpbot-in), already in its place next to the button:
 *   with the lazy launcher it is hidden while the widget loads, and its entrance used to play unseen,
 *   so it popped in and then jumped (owner, 17 November 2026: "choppy").
 */
( function () {
	'use strict';
	var node = document.getElementById( 'qcld-wpbot-design-config' );
	var cfg = {};
	try {
		cfg = node ? JSON.parse( node.textContent ) : {};
	} catch ( e ) {
		cfg = {};
	}
	var root = document.documentElement;
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var shown = false;
	var typed = false;

	function ball() {
		return document.getElementById( 'wp-chatbot-ball' );
	}
	function box() {
		return document.getElementById( 'wp-chatbot-notification-container' );
	}
	function isOpen() {
		var b = ball();
		return !! ( ( b && b.classList.contains( 'wpbot_chatopen_iconanimation' ) ) || document.querySelector( '.active-chat-board' ) );
	}
	function visible( el ) {
		if ( ! el || ! el.isConnected ) {
			return false;
		}
		var cs = window.getComputedStyle( el );
		return 'none' !== cs.display && 'hidden' !== cs.visibility && el.getClientRects().length > 0;
	}

	// A finite animation that moves the button (rolling in, the lazy launcher's glide, a hover)
	// is running: the button is not in its place yet.
	function moving() {
		var b = ball();
		var els = b ? [ b, b.querySelector( '.wp-chatbot-ball' ) ] : [];
		for ( var i = 0; i < els.length; i++ ) {
			var list = els[ i ] && els[ i ].getAnimations ? els[ i ].getAnimations() : [];
			for ( var j = 0; j < list.length; j++ ) {
				var a = list[ j ];
				try {
					if ( 'running' === a.playState && Infinity !== a.effect.getComputedTiming().endTime && a.effect.getKeyframes().some( function ( k ) {
						return 'transform' in k;
					} ) ) {
						return a;
					}
				} catch ( e ) {
					// An animation this browser cannot describe: ignore it.
				}
			}
		}
		return null;
	}

	// The button's resting place: the wrapper's box plus the button's layout offset, so the motions
	// that move the button itself (float, bounce, heartbeat, liquid) do not move the floating box with
	// them in steps (owner, 18 November 2026: "animations are jerky").
	function rest( b, face ) {
		var op = face.offsetParent;
		if ( face === b || ! op ) {
			return face.getBoundingClientRect();
		}
		// The layout parent is not moved by the button's own motion; offsetLeft/Top ignore transforms.
		var p = op.getBoundingClientRect();
		var left = p.left + op.clientLeft + face.offsetLeft;
		var top = p.top + op.clientTop + face.offsetTop;
		return { left: left, top: top, right: left + face.offsetWidth, bottom: top + face.offsetHeight, width: face.offsetWidth, height: face.offsetHeight };
	}

	// Where the button is: the box and the side menu are placed from it.
	function place() {
		var b = ball();
		var face = b ? b.querySelector( '.wp-chatbot-ball' ) || b : null;
		if ( ! face || moving() ) {
			return false;
		}
		var r = rest( b, face );
		if ( ! r.width ) {
			return false;
		}
		var left = r.left + r.width / 2 < window.innerWidth / 2;
		root.classList.toggle( 'qcld-wpbot-ball-left', left );
		root.style.setProperty( '--qcld-wpbot-bx', Math.max( 8, Math.round( left ? r.left : window.innerWidth - r.right ) ) + 'px' );
		root.style.setProperty( '--qcld-wpbot-by', Math.max( 8, Math.round( window.innerHeight - r.bottom ) ) + 'px' );
		return true;
	}

	function openChat() {
		if ( isOpen() ) {
			return;
		}
		var face = document.querySelector( '#wp-chatbot-ball .wp-chatbot-ball' ) || document.querySelector( '.wp-chatbot-ball' );
		if ( face ) {
			face.click();
		}
	}

	// After the chat opens (now, or once the widget has loaded), put the cursor in the message field.
	function focusEditorSoon() {
		var until = Date.now() + 12000;
		( function tick() {
			var editor = document.getElementById( 'wp-chatbot-editor' );
			if ( editor && isOpen() && visible( editor ) ) {
				try {
					editor.focus( { preventScroll: true } );
				} catch ( e ) {
					editor.focus();
				}
				return;
			}
			if ( Date.now() < until ) {
				setTimeout( tick, 150 );
			}
		}() );
	}

	// The motions' small helpers (orbit dot, sweep ring, "Chat with us" label, typing bubble, shine,
	// waving hand, "Hi!") and the ring and aurora finishes' bezel: added once, shown only by the CSS
	// of the chosen motion or finish.
	function fx() {
		var b = ball();
		var face = b ? b.querySelector( '.wp-chatbot-ball' ) : null;
		if ( ! b || ! face || b.querySelector( '.qcld-wpbot-fx-ring' ) ) {
			return;
		}
		var add = function ( parent, cls, text, dots ) {
			var el = document.createElement( 'span' );
			el.className = 'qcld-wpbot-fx-' + cls;
			el.setAttribute( 'aria-hidden', 'true' );
			if ( text ) {
				el.textContent = text;
			}
			for ( var i = 0; i < ( dots || 0 ); i++ ) {
				el.appendChild( document.createElement( 'i' ) );
			}
			parent.appendChild( el );
		};
		add( b, 'bezel' );
		add( b, 'ring' );
		add( b, 'orb' );
		add( b, 'label', cfg.label || 'Chat with us' );
		add( b, 'typing', '', 3 );
		add( face, 'shine' );
		add( face, 'hand' );
		add( face, 'hi', cfg.hi || 'Hi!' );
	}

	// Card, ask bar and menu, built once from the host's markup.
	function build() {
		var c = box();
		if ( ! c || c.getAttribute( 'data-qcld-wpbot' ) ) {
			return;
		}
		c.setAttribute( 'data-qcld-wpbot', '1' );
		var card = document.createElement( 'div' );
		card.className = 'qcld-wpbot-card';
		[ '.wp-chatbot-notification-controller', '.wp-chatbot-notification-agent-profile', '.wp-chatbot-notification-message' ].forEach( function ( sel ) {
			var el = c.querySelector( sel );
			if ( el ) {
				card.appendChild( el );
			}
		} );
		c.insertBefore( card, c.firstChild );
		// A saved agent picture that no longer exists: hide it rather than show a broken image.
		var avatar = card.querySelector( '.wp-chatbot-notification-widget-avatar img' );
		if ( avatar ) {
			var broken = function () {
				avatar.parentNode.classList.add( 'qcld-wpbot-noimg' );
			};
			if ( avatar.complete && ! avatar.naturalWidth ) {
				broken();
			} else {
				avatar.addEventListener( 'error', broken );
			}
		}
		card.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.wp-chatbot-notification-close' ) ) {
				return;
			}
			// The host opens the chat for clicks on the message and avatar: only open when it did not.
			setTimeout( openChat, 60 );
		} );

		// The quick menu: the hosts print it two ways (items inside one .wp-chatbot-notification-navigation
		// that has the click handler, or items that carry that class themselves). Gather them in one
		// menu, moving whichever element has the handler.
		var nav = null;
		var items = c.querySelectorAll( '.qcwp_notification_nav' );
		if ( items.length ) {
			nav = document.createElement( 'div' );
			nav.className = 'qcld-wpbot-menu';
			Array.prototype.forEach.call( items, function ( item ) {
				var holder = item.parentElement && item.parentElement.matches( '.wp-chatbot-notification-navigation' ) && ! item.matches( '.wp-chatbot-notification-navigation' ) ? item.parentElement : item;
				if ( holder.parentNode !== nav ) {
					nav.appendChild( holder );
				}
			} );
			Array.prototype.forEach.call( c.querySelectorAll( '.wp-chatbot-notification-navigation_wraper' ), function ( w ) {
				if ( ! w.querySelector( '.qcwp_notification_nav' ) ) {
					w.parentNode.removeChild( w );
				}
			} );
			// Card with quick replies: the chips sit inside the card, under the message.
			( 'card' === cfg.layout ? card : c ).appendChild( nav );
		}
		root.classList.toggle( 'qcld-wpbot-has-nav', !! nav );

		if ( 'ask' === cfg.layout ) {
			var ask = document.createElement( 'button' );
			ask.type = 'button';
			ask.className = 'qcld-wpbot-ask';
			ask.innerHTML = '<span></span><i aria-hidden="true"></i>';
			ask.firstChild.textContent = cfg.ask || 'Type your question…';
			ask.setAttribute( 'aria-label', cfg.askLabel || cfg.ask || 'Ask a question' );
			// Before the widget loads, the launcher takes the click and opens the chat itself.
			ask.addEventListener( 'pointerdown', focusEditorSoon );
			ask.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					focusEditorSoon();
				}
			} );
			ask.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				openChat();
				focusEditorSoon();
			} );
			card.parentNode.insertBefore( ask, card.nextSibling );
			if ( nav ) {
				ask.parentNode.insertBefore( nav, ask.nextSibling );
			}
		}
	}

	function measureNav() {
		var nav = document.querySelector( '#wp-chatbot-notification-container .qcld-wpbot-menu' );
		if ( nav && 'side' === cfg.layout ) {
			var h = nav.getBoundingClientRect().height;
			if ( h ) {
				root.style.setProperty( '--qcld-wpbot-nav-h', Math.round( h ) + 'px' );
			}
		}
	}

	// The host shows and hides the box; follow it.
	function watch() {
		var c = box();
		if ( ! c ) {
			return;
		}
		build();
		fx();
		var now = visible( c );
		if ( ! now ) {
			c.classList.remove( 'qcld-wpbot-in' );
		}
		if ( now ) {
			// The button may have moved (page-load roll, template scripts): keep the box with it.
			place();
		}
		if ( now && ! shown ) {
			// Show the box once the button has landed, so it appears in its place and stays there.
			var busy = moving();
			if ( busy ) {
				busy.finished.then( soon, soon );
				return;
			}
			if ( ! place() ) {
				setTimeout( soon, 100 ); // Not measurable yet: try again shortly; the box stays hidden.
				return;
			}
			c.classList.add( 'qcld-wpbot-in' );
			measureNav();
			if ( ! typed && cfg.typing && ! reduce ) {
				typed = true;
				c.classList.add( 'qcld-wpbot-typing' );
				setTimeout( function () {
					c.classList.remove( 'qcld-wpbot-typing' );
					measureNav();
				}, 1300 );
			}
		}
		shown = now;
	}

	// Follow the host at once (not only on the next tick): class and style changes on the page,
	// the chat window and the box, one check per frame.
	var queued = false;
	function soon() {
		if ( queued ) {
			return;
		}
		queued = true;
		( window.requestAnimationFrame || setTimeout )( function () {
			queued = false;
			watch();
		} );
	}

	var started = false;
	function start() {
		if ( started ) {
			return;
		}
		started = true;
		fx();
		place();
		watch();
		setInterval( watch, 400 );
		if ( window.MutationObserver ) {
			var mo = new MutationObserver( soon );
			mo.observe( root, { attributes: true, attributeFilter: [ 'class' ] } );
			[ box(), document.getElementById( 'wp-chatbot-chat-container' ) ].forEach( function ( el ) {
				if ( el ) {
					mo.observe( el, { attributes: true, attributeFilter: [ 'class', 'style' ] } );
				}
			} );
		}
		window.addEventListener( 'resize', place, { passive: true } );
		// Measured during the page-load roll the button's box is rotated and shifted: measure again after it.
		document.addEventListener( 'animationend', function ( e ) {
			if ( e.target && e.target.id === 'wp-chatbot-ball' ) {
				place();
			}
		} );
	}
	// In the designer's preview frame: apply the edited look at once (same origin only).
	if ( cfg.preview && window.addEventListener ) {
		window.addEventListener( 'message', function ( e ) {
			var d = e.data;
			if ( e.origin !== window.location.origin || ! d || 'qcld-wpbot-preview' !== d.type ) {
				return;
			}
			if ( d.classes ) {
				var keep = /^qcld-wpbot-(ball-left|has-nav|lazy|loading)$/;
				root.className.split( /\s+/ ).forEach( function ( c ) {
					if ( 0 === c.indexOf( 'qcld-wpbot-' ) && ! keep.test( c ) ) {
						root.classList.remove( c );
					}
				} );
				d.classes.forEach( function ( c ) {
					root.classList.add( c );
				} );
			}
			if ( d.vars ) {
				Object.keys( d.vars ).forEach( function ( k ) {
					root.style.setProperty( k, d.vars[ k ] );
				} );
			}
			setTimeout( place, 50 );
		} );
	}

	// The hosts print the button and the box before this script: start now when they are there.
	if ( 'loading' !== document.readyState || ( ball() && box() ) ) {
		start();
	} else {
		document.addEventListener( 'DOMContentLoaded', start );
	}
}() );
