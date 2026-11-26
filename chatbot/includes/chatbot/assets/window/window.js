/**
 * Chat window behaviour (owner, 20 November 2026): three-dot menu, message box, first-time tip,
 * start menu bar arrows, and the bridge to the conversation script, which still runs the chat.
 *
 * Plain JavaScript; jQuery is only touched to stop the conversation script's custom scrollbar
 * (slimScroll) inside the window, which uses native scrolling.
 */
( function () {
	'use strict';

	var STORE = 'qcw-prefs';

	/**
	 * Visitor preferences (sound, text size, tip seen), kept in this browser only.
	 *
	 * @return {Object}
	 */
	function prefs() {
		try {
			return JSON.parse( window.localStorage.getItem( STORE ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function savePrefs( p ) {
		try {
			window.localStorage.setItem( STORE, JSON.stringify( p ) );
		} catch ( e ) {}
	}

	/**
	 * Translatable strings (printed by the module as qcwStrings when available).
	 */
	var T = Object.assign(
		{
			newChat: 'Start a new conversation',
			newConfirm: 'Start a new conversation? This chat will be cleared.',
			cancel: 'Cancel',
			startOver: 'Start over',
			email: 'Email me this chat',
			download: 'Download the chat',
			sound: 'Sound',
			on: 'On',
			off: 'Off',
			textSize: 'Text size',
			smaller: 'Smaller text',
			larger: 'Larger text',
			language: 'Language',
			person: 'Talk to a person',
			personText: 'I would like to talk to a person',
			started: 'New conversation started',
			saved: 'Chat saved as a text file',
			you: 'You',
		},
		window.qcwStrings || {}
	);

	var ICON = {
		new: '<svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.3-5.7M4 4v4h4"/></svg>',
		email: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
		download: '<svg viewBox="0 0 24 24"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>',
		sound: '<svg viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9zM17 9a4 4 0 0 1 0 6"/></svg>',
		text: '<svg viewBox="0 0 24 24"><path d="M4 19 9 5l5 14M6 14h6M15 19l3-8 3 8M16 16.5h4"/></svg>',
		lang: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg>',
		person: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
	};

	function el( tag, attrs, html ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			node.setAttribute( k, attrs[ k ] );
		} );
		if ( html ) {
			node.innerHTML = html;
		}
		return node;
	}

	/* ---------- motion (core: Morph and Fade, Rise, Dots, ripple; WPBot Pro adds the rest) ---------- */
	var REDUCE = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	/**
	 * Registry for more motion (WPBot Pro). Each entry is looked up when it is needed, so a script
	 * that loads later can still add to it.
	 *
	 *   qcwMotion.open[ name ]   = function ( ctx ) { return keyframes; }
	 *                            or { frames: function ( ctx ) {}, scale: 1.4, easing: 'linear' }
	 *                            or { run: function ( ctx, closing, done, ms ) {} } (draws it itself)
	 *   qcwMotion.typing[ name ] = function ( li, loader, root ) {}         replaces the loader in a "typing" row
	 *   qcwMotion.send[ name ]   = function ( button, root ) {}
	 *
	 * ctx: { root, board, launcher, closing, radius, origin: { x, y } }.
	 */
	var Motion = window.qcwMotion = window.qcwMotion || {};
	Motion.open = Motion.open || {};
	Motion.typing = Motion.typing || {};
	Motion.send = Motion.send || {};

	function rootClass( root, prefix, fallback ) {
		var found = fallback;
		root.className.split( /\s+/ ).forEach( function ( c ) {
			if ( 0 === c.indexOf( prefix ) ) {
				found = c.slice( prefix.length );
			}
		} );
		return found;
	}

	/** Base duration of the open animation for the window's feel (0: no motion). */
	function feelMs( root ) {
		var feel = rootClass( root, 'qcw-feel-', 'subtle' );
		return 'off' === feel ? 0 : 'expressive' === feel ? 680 : 360;
	}

	Motion.open.morph = function ( ctx ) {
		var o = ctx.origin.x + 'px ' + ctx.origin.y + 'px';
		return [
			{ transform: 'scale(.06)', borderRadius: '50%', opacity: 0, transformOrigin: o },
			{ offset: 0.25, opacity: 1 },
			{ transform: 'scale(1)', borderRadius: ctx.radius, opacity: 1, transformOrigin: o },
		];
	};
	Motion.open.fade = function () {
		return [ { opacity: 0, transform: 'scale(.97)' }, { opacity: 1, transform: 'scale(1)' } ];
	};

	function init( root ) {
		if ( ! root || root.getAttribute( 'data-qcw-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-qcw-ready', '1' );
		var board = root.querySelector( '#wp-chatbot-board-container' );
		var editor = root.querySelector( '#wp-chatbot-editor' );
		var menu = root.querySelector( '.qcw-menu' );
		var more = root.querySelector( '.qcw-more' );
		var toastEl = root.querySelector( '.qcw-toast' );
		var tip = root.querySelector( '.qcw-tip' );
		var p = prefs();
		var toastTimer = null;

		if ( p.size ) {
			root.style.setProperty( '--qcw-tz', p.size );
		}

		function toast( text ) {
			if ( ! toastEl ) {
				return;
			}
			toastEl.textContent = text;
			toastEl.hidden = false;
			clearTimeout( toastTimer );
			toastTimer = setTimeout( function () {
				toastEl.hidden = true;
			}, 2600 );
		}

		/* ---------- message box ---------- */
		function size() {
			if ( ! editor ) {
				return;
			}
			// Hidden (window closed) or empty: back to the stylesheet's one-line height.
			if ( '' === editor.value || null === editor.offsetParent ) {
				editor.style.height = '';
				editor.style.overflowY = 'hidden';
				return;
			}
			editor.style.height = 'auto';
			var h = Math.max( editor.scrollHeight, 0 );
			editor.style.height = Math.min( 110, h ) + 'px';
			editor.style.overflowY = h > 110 ? 'auto' : 'hidden';
		}
		if ( editor ) {
			// Enter sends (the conversation script listens for it); Shift+Enter is a new line.
			editor.addEventListener(
				'keypress',
				function ( e ) {
					if ( 13 !== e.which && 'Enter' !== e.key ) {
						return;
					}
					if ( e.shiftKey ) {
						e.stopPropagation();
						return;
					}
					e.preventDefault();
					setTimeout( size, 0 );
				},
				true
			);
			editor.addEventListener( 'input', function () {
				size();
				hideTip();
			} );
			size();
		}

		/* ---------- first-time tip ---------- */
		function hideTip() {
			if ( tip && ! tip.hidden ) {
				tip.hidden = true;
			}
		}
		function showTip() {
			var q = prefs();
			if ( ! tip || q.tip || '1' !== root.getAttribute( 'data-qcw-tip' ) ) {
				return;
			}
			q.tip = 1;
			savePrefs( q );
			tip.hidden = false;
			setTimeout( hideTip, 6000 );
		}
		if ( tip ) {
			tip.querySelector( '.qcw-tip-ok' ).addEventListener( 'click', hideTip );
		}

		/* ---------- open and close ---------- */
		function isOpen() {
			return board && board.classList.contains( 'active-chat-board' );
		}
		var floating = root.classList.contains( 'qcw-l-floating' ) || root.classList.contains( 'qcw-l-compact' ) || root.classList.contains( 'qcw-l-side' );
		var running = null;
		// When the window has closed with the focus inside it, give the focus back to the chat button.
		function restoreFocus() {
			var a = document.activeElement;
			if ( isOpen() || ( a && a !== document.body && ! board.contains( a ) ) ) {
				return;
			}
			var launcher = document.querySelector( '.wp-chatbot-ball' );
			if ( launcher && launcher.offsetParent ) {
				launcher.focus();
			}
		}
		function animateBoard( closing ) {
			if ( running ) {
				running.cancel();
				running = null;
			}
			board.classList.remove( 'qcw-closing' );
			var base = feelMs( root );
			if ( ! floating || ! base || ! board.animate ) {
				if ( closing && floating ) {
					setTimeout( restoreFocus, 50 );
				}
				return;
			}
			var kind = REDUCE ? 'fade' : root.getAttribute( 'data-qcw-open' ) || 'morph';
			var entry = Motion.open[ kind ] || Motion.open.morph;
			if ( closing ) {
				// Keep it on screen while it closes (the conversation script already took the class away).
				board.classList.add( 'qcw-closing' );
			}
			var rect = board.getBoundingClientRect();
			var launcher = document.querySelector( '.wp-chatbot-ball' );
			var lr = launcher && launcher.offsetParent ? launcher.getBoundingClientRect() : null;
			var ctx = {
				root: root,
				board: board,
				launcher: launcher,
				closing: closing,
				radius: getComputedStyle( board ).borderTopLeftRadius,
				origin: lr ? { x: lr.left + lr.width / 2 - rect.left, y: lr.top + lr.height / 2 - rect.top } : { x: rect.width / 2, y: rect.height },
			};
			var done = function () {
				running = null;
				board.classList.remove( 'qcw-closing' );
				if ( closing ) {
					restoreFocus();
				}
			};
			var ms = REDUCE ? 180 : base * ( entry.scale || 1 );
			if ( entry && 'function' === typeof entry.run ) {
				entry.run( ctx, closing, done, ms );
				return;
			}
			var frames = 'function' === typeof entry ? entry : entry.frames;
			try {
				running = board.animate( frames( ctx ), {
					duration: closing ? ms * 0.75 : ms,
					easing: entry.easing || 'cubic-bezier(.2,.8,.2,1)',
					direction: closing ? 'reverse' : 'normal',
				} );
				running.onfinish = done;
			} catch ( e ) {
				done();
			}
		}
		if ( board && window.MutationObserver ) {
			var wasOpen = isOpen();
			new MutationObserver( function () {
				var open = isOpen();
				if ( open === wasOpen ) {
					return;
				}
				wasOpen = open;
				animateBoard( ! open );
				root.classList.toggle( 'qcw-open', open );
				document.dispatchEvent( new CustomEvent( open ? 'qcw:open' : 'qcw:close', { detail: { root: root } } ) );
				if ( open ) {
					setTimeout( showTip, 400 );
				} else {
					closeMenu();
					hideTip();
				}
			} ).observe( board, { attributes: true, attributeFilter: [ 'class' ] } );
			root.classList.toggle( 'qcw-open', wasOpen );
		}

		/* ---------- product details (WPBot Pro's store) ----------
		 * The store add-on prints .wp-chatbot-product-container next to the board and opens it with
		 * .active-chatbot-product-details. It takes the open window's look, whatever the design, the
		 * site's colours or the mode: the board's surface, the header for a header band (the product
		 * title and the close button move into it), the message box for the fields and the send
		 * button for Add to Cart. window.css reads these as --qcw-pp-* (owner, 23 November 2026). */
		var panel = root.querySelector( '.wp-chatbot-product-container' );
		if ( panel && board && window.MutationObserver ) {
			var dressPanel = function () {
				if ( ! panel.classList.contains( 'active-chatbot-product-details' ) ) {
					return;
				}
				var details = panel.querySelector( '.wp-chatbot-product-details' );
				if ( details && ! details.querySelector( '.qcw-pp-head' ) ) {
					var head = document.createElement( 'div' );
					head.className = 'qcw-pp-head';
					var title = details.querySelector( '.wp-chatbot-product-title' );
					var close = details.querySelector( '.wp-chatbot-product-close' );
					if ( title ) {
						head.appendChild( title );
					}
					if ( close ) {
						head.appendChild( close );
					}
					details.insertBefore( head, details.firstChild );
				}
				var cs = function ( sel, from ) {
					var e = 'string' === typeof sel ? ( from || root ).querySelector( sel ) : sel;
					return e ? window.getComputedStyle( e ) : null;
				};
				var clear = function ( c ) {
					return ! c || 'transparent' === c || /rgba\([^)]*,\s*0\)$/.test( c ) || /\/\s*0\)$/.test( c );
				};
				var set = function ( name, value ) {
					if ( value && 'none' !== value ) {
						panel.style.setProperty( name, value );
					} else {
						panel.style.removeProperty( name );
					}
				};
				var sides = function ( prefix, st ) {
					[ 'Top', 'Right', 'Bottom', 'Left' ].forEach( function ( side ) {
						var w = st[ 'border' + side + 'Width' ];
						set( prefix + side.toLowerCase(), '0px' === w ? '0' : w + ' ' + st[ 'border' + side + 'Style' ] + ' ' + st[ 'border' + side + 'Color' ] );
					} );
				};
				var b = cs( board );
				set( '--qcw-pp-bg', clear( b.backgroundColor ) ? '' : b.backgroundColor );
				set( '--qcw-pp-bgimg', b.backgroundImage );
				sides( '--qcw-pp-b-', b );
				set( '--qcw-pp-radius', b.borderRadius );
				set( '--qcw-pp-shadow', b.boxShadow );
				set( '--qcw-pp-fg', b.color );
				set( '--qcw-pp-font', b.fontFamily );
				set( '--qcw-pp-blur', b.backdropFilter || b.webkitBackdropFilter );
				var h = cs( '.qcw-head', board );
				if ( h ) {
					// A see-through window header (Holo, Desk...) gives a see-through band, not the token colour.
					set( '--qcw-pp-head-bg', clear( h.backgroundColor ) ? 'transparent' : h.backgroundColor );
					set( '--qcw-pp-head-bgimg', h.backgroundImage );
					set( '--qcw-pp-head-fg', h.color );
					set( '--qcw-pp-head-bb', '0px' === h.borderBottomWidth ? '0' : h.borderBottomWidth + ' ' + h.borderBottomStyle + ' ' + h.borderBottomColor );
				}
				var n = cs( '.qcw-name', board );
				if ( n ) {
					set( '--qcw-pp-title-font', n.fontFamily );
					set( '--qcw-pp-title-weight', n.fontWeight );
					set( '--qcw-pp-title-style', n.fontStyle );
					set( '--qcw-pp-title-case', n.textTransform );
					set( '--qcw-pp-title-ls', 'normal' === n.letterSpacing ? '' : n.letterSpacing );
				}
				var c = cs( '#wp-chatbot-editor-container', board );
				if ( c ) {
					sides( '--qcw-pp-f-', c );
					set( '--qcw-pp-f-radius', c.borderRadius );
					set( '--qcw-pp-f-bg', clear( c.backgroundColor ) ? '' : c.backgroundColor );
				}
				var ed = cs( '#wp-chatbot-editor', board );
				if ( ed ) {
					set( '--qcw-pp-f-font', ed.fontFamily );
				}
				var sb = cs( '#wp-chatbot-send-message', board );
				if ( sb ) {
					// An outlined send button (no fill) gives an outlined Add to Cart in its colour.
					set( '--qcw-pp-btn-bg', clear( sb.backgroundColor ) ? 'transparent' : sb.backgroundColor );
					set( '--qcw-pp-btn-bgimg', sb.backgroundImage );
					set( '--qcw-pp-btn-fg', sb.color );
					sides( '--qcw-pp-btn-b-', sb );
					set( '--qcw-pp-btn-shadow', sb.boxShadow );
					// The colour that marks the chosen option and the focus ring: the button's fill, or
					// its text colour when the button is outlined.
					var grad = ( sb.backgroundImage || '' ).match( /rgba?\([^)]*\)/ );
					set( '--qcw-pp-accent', ! clear( sb.backgroundColor ) ? sb.backgroundColor : ( grad && ! clear( grad[ 0 ] ) ? grad[ 0 ] : sb.color ) );
				}
				var chip = cs( '.qcw-chipbar .qcld-bottom-menu-item > *', board ) || cs( '#wp-chatbot-messages-container li.wp-chatbot-msg-buttons span[class*="qcld-chat"]', board );
				if ( chip ) {
					set( '--qcw-pp-btn-font', chip.fontFamily );
					set( '--qcw-pp-btn-case', chip.textTransform );
					set( '--qcw-pp-btn-ls', 'normal' === chip.letterSpacing ? '' : chip.letterSpacing );
					set( '--qcw-pp-btn-radius', chip.borderRadius );
				}
			};
			/* Where the panel goes (owner, 25 November 2026): beside the window, 80% of its height and
			 * centred on it, its edge 18 px under the window (window.css slides it out from there); over
			 * the window, in its place, when there is no room beside it (.qcw-pp-over). Phones and the
			 * embedded and full-page windows are placed by window.css alone. */
			var TUCK = 18;
			var placePanel = function () {
				if ( root.classList.contains( 'qcw-l-embedded' ) || root.classList.contains( 'qcw-l-full' ) || window.matchMedia( '(max-width: 768px)' ).matches ) {
					[ '--qcw-pp-x', '--qcw-pp-top', '--qcw-pp-w', '--qcw-pp-h' ].forEach( function ( name ) {
						panel.style.removeProperty( name );
					} );
					// toggle( …, false ), not remove(): remove() rewrites the class attribute even when
					// nothing changes, which wakes the observer below again and again.
					panel.classList.toggle( 'qcw-pp-over', false );
					return;
				}
				var r = board.getBoundingClientRect();
				if ( ! r.width || ! r.height ) {
					return;
				}
				var vw = document.documentElement.clientWidth;
				var left = root.classList.contains( 'qcw-side-left' );
				var room = ( left ? vw - r.right : r.left ) + TUCK - 16;
				var over = room < 520;
				var h = over ? r.height : Math.round( r.height * 0.8 );
				panel.classList.toggle( 'qcw-pp-over', over );
				panel.style.setProperty( '--qcw-pp-w', Math.round( over ? r.width : Math.min( 720, room ) ) + 'px' );
				panel.style.setProperty( '--qcw-pp-h', h + 'px' );
				panel.style.setProperty( '--qcw-pp-top', Math.round( r.top + ( r.height - h ) / 2 ) + 'px' );
				panel.style.setProperty( '--qcw-pp-x', Math.round( over ? ( left ? r.left : vw - r.right ) : ( left ? r.right - TUCK : vw - r.left - TUCK ) ) + 'px' );
			};
			var update = function () {
				placePanel();
				dressPanel();
			};
			new MutationObserver( update ).observe( panel, { attributes: true, attributeFilter: [ 'class' ] } );
			// The store's script asks before it opens the panel, so it opens in the right place.
			document.addEventListener( 'qcw:product-open', update );
			window.addEventListener( 'resize', function () {
				if ( panel.classList.contains( 'active-chatbot-product-details' ) ) {
					placePanel();
				}
			} );
			update();
		}

		/* ---------- three-dot menu ---------- */
		function items() {
			return menu ? ( menu.getAttribute( 'data-items' ) || '' ).split( ',' ).filter( Boolean ) : [];
		}
		function langSource() {
			return root.querySelector( '.qcw-lang-source' );
		}
		function mi( act, label, val ) {
			return '<button type="button" class="qcw-mi" role="menuitem" data-act="' + act + '">' + ICON[ act ] + '<span>' + label + '</span>' + ( val ? '<span class="qcw-val">' + val + '</span>' : '' ) + '</button>';
		}
		function mainMenu() {
			var list = items();
			var html = '';
			var group1 = [ 'new', 'email', 'download' ].filter( function ( k ) {
				return list.indexOf( k ) > -1;
			} );
			var group2 = [ 'sound', 'text', 'lang' ].filter( function ( k ) {
				return list.indexOf( k ) > -1 && ( 'lang' !== k || langSource() );
			} );
			group1.forEach( function ( k ) {
				html += mi( k, { new: T.newChat, email: T.email, download: T.download }[ k ] );
			} );
			if ( group1.length && group2.length ) {
				html += '<div class="qcw-sep"></div>';
			}
			group2.forEach( function ( k ) {
				if ( 'sound' === k ) {
					html += mi( 'sound', T.sound, prefs().mute ? T.off : T.on );
				} else if ( 'text' === k ) {
					html += '<div class="qcw-mtext">' + ICON.text + '<span>' + T.textSize + '</span><button type="button" data-act="smaller" aria-label="' + T.smaller + '">A&minus;</button><button type="button" data-act="larger" aria-label="' + T.larger + '">A+</button></div>';
				} else {
					html += mi( 'lang', T.language );
				}
			} );
			if ( list.indexOf( 'person' ) > -1 ) {
				html += ( html ? '<div class="qcw-sep"></div>' : '' ) + mi( 'person', T.person );
			}
			menu.innerHTML = html;
		}
		function placeMenu() {
			var head = root.querySelector( '.qcw-head' );
			menu.style.top = head.offsetTop + head.offsetHeight + 4 + 'px';
		}
		function openMenu() {
			mainMenu();
			placeMenu();
			menu.hidden = false;
			more.setAttribute( 'aria-expanded', 'true' );
			var first = menu.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
		}
		function closeMenu() {
			if ( ! menu || menu.hidden ) {
				return;
			}
			menu.hidden = true;
			more.setAttribute( 'aria-expanded', 'false' );
		}
		function transcriptText() {
			var lines = [];
			root.querySelectorAll( '#wp-chatbot-messages-container > li' ).forEach( function ( li ) {
				var para = li.querySelectorAll( '.wp-chatbot-paragraph' );
				if ( ! para.length ) {
					return;
				}
				var who = li.classList.contains( 'wp-chat-user-msg' ) ? T.you : ( li.querySelector( '.wp-chatbot-agent' ) || {} ).textContent || '';
				var text = Array.prototype.map.call( para, function ( n ) {
					return n.innerText.trim();
				} ).join( '\n' );
				if ( text ) {
					lines.push( who.trim() + ': ' + text );
				}
			} );
			return lines.join( '\n\n' );
		}
		function download() {
			var blob = new Blob( [ transcriptText() + '\n' ], { type: 'text/plain' } );
			var a = el( 'a', { href: URL.createObjectURL( blob ), download: 'chat-' + new Date().toISOString().slice( 0, 10 ) + '.txt' } );
			document.body.appendChild( a );
			a.click();
			setTimeout( function () {
				URL.revokeObjectURL( a.href );
				a.remove();
			}, 500 );
		}
		function askPerson() {
			var item = Array.prototype.find.call( root.querySelectorAll( '[data-option="live-chat"], .qcld-chatbot-default-btn, .qcld-chatbot-custom-intent' ), function ( n ) {
				return /live|person|human|agent/i.test( n.textContent ) || 'live-chat' === n.getAttribute( 'data-option' );
			} );
			if ( item ) {
				item.click();
				return;
			}
			if ( editor ) {
				editor.value = T.personText;
				var send = root.querySelector( '#wp-chatbot-send-message' );
				if ( send ) {
					send.click();
				}
			}
		}
		if ( menu && more ) {
			more.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				if ( menu.hidden ) {
					openMenu();
				} else {
					closeMenu();
				}
			} );
			document.addEventListener( 'pointerdown', function ( e ) {
				if ( ! menu.hidden && ! menu.contains( e.target ) && ! more.contains( e.target ) ) {
					closeMenu();
				}
			} );
			menu.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					e.stopPropagation();
					closeMenu();
					more.focus();
				}
			} );
			menu.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '[data-act]' );
				if ( ! b ) {
					return;
				}
				var a = b.getAttribute( 'data-act' );
				var q = prefs();
				if ( 'new' === a ) {
					menu.innerHTML = '<p>' + T.newConfirm + '</p><div class="qcw-row"><button type="button" data-act="back">' + T.cancel + '</button><button type="button" class="qcw-pri" data-act="newok">' + T.startOver + '</button></div>';
					menu.querySelector( '.qcw-pri' ).focus();
				} else if ( 'newok' === a ) {
					closeMenu();
					var reload = root.querySelector( '#wp-chatbot-desktop-reload' );
					if ( reload ) {
						reload.click();
					}
					toast( T.started );
				} else if ( 'back' === a ) {
					mainMenu();
				} else if ( 'email' === a ) {
					closeMenu();
					var mail = root.querySelector( '#wp-chatbot-email-transcript' );
					if ( mail ) {
						mail.click();
					}
				} else if ( 'download' === a ) {
					closeMenu();
					download();
					toast( T.saved );
				} else if ( 'sound' === a ) {
					q.mute = ! q.mute;
					savePrefs( q );
					mainMenu();
					menu.querySelector( '[data-act="sound"]' ).focus();
				} else if ( 'smaller' === a || 'larger' === a ) {
					var z = parseFloat( q.size || 1 ) + ( 'larger' === a ? 0.1 : -0.1 );
					q.size = Math.max( 0.9, Math.min( 1.3, Math.round( z * 10 ) / 10 ) );
					savePrefs( q );
					root.style.setProperty( '--qcw-tz', q.size );
				} else if ( 'lang' === a ) {
					var src = langSource();
					if ( src ) {
						menu.innerHTML = '';
						var box = el( 'div', { class: 'qcw-lang' } );
						box.appendChild( src.firstElementChild ? src.firstElementChild.cloneNode( true ) : document.createTextNode( '' ) );
						menu.appendChild( box );
						// Changes on the copy are passed to the add-on's own control.
						box.addEventListener( 'change', function ( ev ) {
							var orig = src.querySelector( 'select' );
							if ( orig && 'SELECT' === ev.target.tagName ) {
								orig.value = ev.target.value;
								orig.dispatchEvent( new Event( 'change', { bubbles: true } ) );
								if ( window.jQuery ) {
									window.jQuery( orig ).trigger( 'change' );
								}
								closeMenu();
							}
						} );
						box.addEventListener( 'click', function ( ev ) {
							var link = ev.target.closest( 'a,[data-lan],[data-language]' );
							if ( link ) {
								var attr = link.getAttribute( 'data-lan' ) || link.getAttribute( 'data-language' );
								var orig = attr ? src.querySelector( '[data-lan="' + attr + '"],[data-language="' + attr + '"]' ) : null;
								if ( orig ) {
									ev.preventDefault();
									orig.click();
								}
								closeMenu();
							}
						} );
					}
				} else if ( 'person' === a ) {
					closeMenu();
					askPerson();
				}
			} );
		}

		/* ---------- start menu bar ---------- */
		var scroller = root.querySelector( '.qcw-chipbar .qcld-bottom-menu-wrapper' );
		if ( scroller ) {
			var left = root.querySelector( '.qcw-cb-l' );
			var right = root.querySelector( '.qcw-cb-r' );
			var update = function () {
				var max = scroller.scrollWidth - scroller.clientWidth - 2;
				left.hidden = scroller.scrollLeft <= 2;
				right.hidden = scroller.scrollLeft >= max;
				scroller.classList.toggle( 'qcw-fade-l', scroller.scrollLeft > 2 );
				scroller.classList.toggle( 'qcw-fade-r', scroller.scrollLeft < max );
			};
			scroller.addEventListener( 'scroll', update, { passive: true } );
			left.addEventListener( 'click', function () {
				scroller.scrollBy( { left: -170, behavior: 'smooth' } );
			} );
			right.addEventListener( 'click', function () {
				scroller.scrollBy( { left: 170, behavior: 'smooth' } );
			} );
			if ( window.ResizeObserver ) {
				new ResizeObserver( update ).observe( scroller );
			}
			document.addEventListener( 'qcw:open', function () {
				setTimeout( update, 50 );
			} );
			update();
		}

		/* ---------- keep the list scrolled to the newest message ---------- */
		var list = root.querySelector( '#wp-chatbot-messages-container' );
		var body = root.querySelector( '.qcw-body' );
		var typingKind = rootClass( root, 'qcw-t-', 'dots' );
		function typingRow( li ) {
			var loader = li.querySelector( '.wp-chatbot-comment-loader' );
			if ( ! loader || li.classList.contains( 'qcw-typing' ) ) {
				return;
			}
			li.classList.add( 'qcw-typing' );
			var custom = Motion.typing[ typingKind ];
			if ( 'dots' !== typingKind && 'function' === typeof custom ) {
				custom( li, loader, root );
				return;
			}
			li.classList.add( 'qcw-typing-dots' );
			loader.insertAdjacentHTML( 'beforebegin', '<span class="qcw-think" aria-hidden="true"><i></i><i></i><i></i></span>' );
		}
		// The conversation script often turns the typing row into the answer (the loader is replaced).
		function answered( li ) {
			if ( ! li.classList.contains( 'qcw-typing' ) || li.querySelector( '.wp-chatbot-comment-loader' ) ) {
				return;
			}
			li.classList.remove( 'qcw-typing', 'qcw-typing-dots', 'qcw-typing-custom', 'qcw-typing-' + typingKind );
			Array.prototype.forEach.call( li.querySelectorAll( '.qcw-think, .qcw-think-wave, .qcw-think-shimmer, .qcw-think-orb, .qcw-orbit' ), function ( n ) {
				n.remove();
			} );
			arrived( li );
		}
		function arrived( li ) {
			if ( 1 !== li.nodeType || 'LI' !== li.tagName ) {
				return;
			}
			typingRow( li );
			if ( li.classList.contains( 'qcw-typing' ) ) {
				return;
			}
			li.classList.add( 'qcw-new' );
			if ( root.classList.contains( 'qcw-stagger' ) ) {
				Array.prototype.forEach.call( li.querySelectorAll( '[class*="qcld-chatbot-"]' ), function ( b, i ) {
					b.style.setProperty( '--qcw-i', Math.min( i, 12 ) );
				} );
			}
			setTimeout( function () {
				li.classList.remove( 'qcw-new' );
			}, 1400 );
		}
		if ( list && body && window.MutationObserver ) {
			Array.prototype.forEach.call( list.children, typingRow );
			new MutationObserver( function ( muts ) {
				muts.forEach( function ( m ) {
					if ( m.target === list ) {
						Array.prototype.forEach.call( m.addedNodes, arrived );
					} else {
						var li = m.target.closest ? m.target.closest( '#wp-chatbot-messages-container > li' ) : null;
						if ( li ) {
							typingRow( li );
							answered( li );
						}
					}
				} );
				body.scrollTop = body.scrollHeight;
			} ).observe( list, { childList: true, subtree: true } );
		}

		/* ---------- guidance: Popular on this page, Try asking, suggestions while typing, finish-my-question, nudge ---------- */
		var guide = ( root.getAttribute( 'data-qcw-guide' ) || '' ).split( ',' ).filter( Boolean );
		var gOn = function ( k ) {
			return -1 !== guide.indexOf( k );
		};
		var suggest = root.querySelector( '.qcw-suggest' );
		var pop = root.querySelector( '.qcw-pop' );
		var tryBtn = root.querySelector( '.qcw-try' );
		var ghost = root.querySelector( '.qcw-ghost' );
		var allItems = root.querySelector( '.qcw-allitems' );
		var D = window.qcwData || {};
		var phrases = [];
		try {
			phrases = JSON.parse( root.getAttribute( 'data-qcw-phrases' ) || '[]' ) || [];
		} catch ( e ) {}
		function esc( t ) {
			var d = document.createElement( 'div' );
			d.textContent = t;
			return d.innerHTML;
		}
		function labelOf( node ) {
			return ( node.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		}
		// Count a start menu click for this page (ranks "Popular on this page"; no personal data).
		function report( label, place ) {
			if ( ! D.click || ! label ) {
				return;
			}
			var payload = JSON.stringify( { label: label.slice( 0, 200 ), page: D.page || 0, place: place } );
			try {
				if ( navigator.sendBeacon && navigator.sendBeacon( D.click, new Blob( [ payload ], { type: 'application/json' } ) ) ) {
					return;
				}
			} catch ( e ) {}
			if ( window.fetch ) {
				window.fetch( D.click, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true, credentials: 'same-origin' } ).catch( function () {} );
			}
		}
		root.addEventListener( 'click', function ( e ) {
			var b = e.target.closest ? e.target.closest( '[class*="qcld-chatbot-"]' ) : null;
			if ( ! b || ! root.contains( b ) || b.closest( '.qcw-suggest, .qcw-allitems' ) ) {
				return;
			}
			var place = b.closest( '.qcw-page-card' ) ? 'page' : b.closest( '.qcw-nudge' ) ? 'nudge' : b.closest( '.qcw-chipbar' ) ? 'bar' : 'chat';
			report( labelOf( b ), place );
		} );
		// Press the conversation script's own button with this label (its click handlers run it).
		function press( label, place ) {
			var pool = [];
			[ suggest, allItems ].forEach( function ( c ) {
				if ( c ) {
					pool = pool.concat( Array.prototype.slice.call( c.children ) );
				}
			} );
			var hit = pool.find( function ( n ) {
				return labelOf( n ) === label;
			} );
			report( label, place );
			if ( hit ) {
				hit.click();
				return true;
			}
			return false;
		}
		function copies( cls ) {
			var li = document.createElement( 'li' );
			li.className = 'wp-chatbot-msg wp-chatbot-msg-flat wp-chatbot-msg-buttons ' + cls;
			Array.prototype.forEach.call( suggest.children, function ( n ) {
				li.appendChild( n.cloneNode( true ) );
			} );
			return li;
		}

		// Popular on this page: once per conversation, after the visitor's first question and its answer
		// (owner, 25 November 2026: "must not show during the greeting sequence - asking for name email
		// etc. it should start showing after the 1st question and response"). It used to go before the
		// first row of buttons, which during the greeting is the email step's privacy note. The greeting
		// ends with the start menu (its buttons carry data-qcld-menu, in WPBot Pro and WPBot Free); the
		// visitor's first message after it, typed or tapped, is the first question. Once the bot has
		// answered, the messages are quiet and no collection step is open (a question such as "Leave your
		// number" is followed by the phone question), the card goes just before the latest answer's
		// buttons (Back to Start, the menu again), or after it.
		if ( gOn( 'page' ) && suggest && list && window.MutationObserver ) {
			var pageTimer = null;
			var pageMo = null;
			var placePage = function () {
				if ( list.querySelector( '.qcw-page-card' ) ) {
					pageMo.disconnect();
					return;
				}
				var items = Array.prototype.slice.call( list.children );
				var menuAt = -1;
				items.some( function ( li, i ) {
					if ( li.querySelector( '[data-qcld-menu]' ) ) {
						menuAt = i;
						return true;
					}
					return false;
				} );
				if ( menuAt < 0 ) {
					return; // Still greeting (name, email, phone): no start menu yet.
				}
				// The latest visitor message after the start menu; nothing before the first one.
				var askAt = -1;
				for ( var i = items.length - 1; i > menuAt; i-- ) {
					if ( items[ i ].classList.contains( 'wp-chat-user-msg' ) ) {
						askAt = i;
						break;
					}
				}
				if ( askAt < 0 ) {
					return; // No question yet.
				}
				// Not while the bot is collecting something (phone number, email, search keyword): the hosts
				// report their conversation step (wildCard 0 is the open conversation).
				var state = 'function' === typeof window.qcldWpbotChatState ? window.qcldWpbotChatState() : null;
				if ( state && parseInt( state.wildCard, 10 ) ) {
					return;
				}
				// Nor while an AI Action asks its questions (owner, 25 November 2026): hosts that report it
				// say so with `aiAction`; the card can come after the action ends.
				if ( state && state.aiAction ) {
					return;
				}
				var answered = false;
				var before = null;
				for ( var j = askAt + 1; j < items.length; j++ ) {
					var li = items[ j ];
					if ( li.classList.contains( 'qcw-typing' ) || li.querySelector( '.wp-chatbot-comment-loader' ) ) {
						return; // Still answering.
					}
					var buttons = li.classList.contains( 'wp-chatbot-msg-flat' ) || li.classList.contains( 'wp-chatbot-msg-buttons' );
					if ( buttons && answered ) {
						before = li;
						break;
					}
					if ( li.classList.contains( 'wp-chatbot-msg' ) ) {
						answered = true;
					}
				}
				if ( ! answered ) {
					return;
				}
				var card = copies( 'qcw-page-card' );
				card.insertAdjacentHTML( 'afterbegin', '<span class="qcw-card-h">' + esc( suggest.getAttribute( 'data-page-title' ) || '' ) + '</span>' );
				pageMo.disconnect();
				if ( before ) {
					list.insertBefore( card, before );
				} else {
					list.appendChild( card );
				}
			};
			pageMo = new MutationObserver( function () {
				clearTimeout( pageTimer );
				pageTimer = setTimeout( placePage, 900 ); // After the answer's messages have arrived.
			} );
			pageMo.observe( list, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class' ] } ); // Rows get their classes after they are added.
		}

		// The pop-up above the message box (Try asking, suggestions while typing). It closes by
		// itself after 6 seconds (owner, 25 November 2026), but not while the pointer is on it or
		// the visitor moves through it with the arrow keys, and it has a close button.
		var popItems = [];
		var popActive = -1;
		var popTimer = 0;
		var POP_LIFE = 6000;
		function armPop() {
			clearTimeout( popTimer );
			popTimer = setTimeout( function () {
				if ( ! pop || pop.hidden ) {
					return;
				}
				if ( pop.matches( ':hover' ) || pop.contains( document.activeElement ) || popActive >= 0 ) {
					armPop(); // In use: wait another 6 seconds.
					return;
				}
				closePop();
			}, POP_LIFE );
		}
		function closePop() {
			clearTimeout( popTimer );
			if ( pop && ! pop.hidden ) {
				pop.hidden = true;
				popItems = [];
				popActive = -1;
				if ( tryBtn ) {
					tryBtn.setAttribute( 'aria-expanded', 'false' );
				}
			}
		}
		function openPop( title, list2, place ) {
			if ( ! pop || ! list2.length ) {
				closePop();
				return;
			}
			pop.innerHTML = '<button type="button" class="qcw-pop-x" aria-label="' + esc( pop.getAttribute( 'data-close' ) || 'Close' ) + '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
				( title ? '<div class="qcw-pop-h">' + esc( title ) + '</div>' : '' ) + list2.map( function ( it, i ) {
					return '<button type="button" role="option" class="qcw-pi" data-i="' + i + '">' + esc( it.t ) + '</button>';
				} ).join( '' );
			popItems = list2;
			popActive = -1;
			pop.hidden = false;
			pop.setAttribute( 'data-place', place );
			armPop();
			hideTip();
		}
		function choose( i ) {
			var it = popItems[ i ];
			var place = pop.getAttribute( 'data-place' ) || 'try';
			closePop();
			if ( ! it ) {
				return;
			}
			if ( it.m && press( it.t, place ) ) {
				editor.value = '';
				size();
				return;
			}
			editor.value = it.t;
			if ( sendBtn ) {
				sendBtn.click();
			}
		}
		if ( pop ) {
			pop.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault(); // Keep the focus in the message box.
			} );
			pop.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.qcw-pop-x' ) ) {
					closePop();
					if ( editor ) {
						editor.focus();
					}
					return;
				}
				var b = e.target.closest( '.qcw-pi' );
				if ( b ) {
					choose( parseInt( b.getAttribute( 'data-i' ), 10 ) );
				}
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( ! pop.hidden && ! pop.contains( e.target ) && e.target !== tryBtn && ! ( tryBtn && tryBtn.contains( e.target ) ) && e.target !== editor ) {
					closePop();
				}
			} );
		}
		// Try asking: only on tap, only this page's strict suggestions.
		if ( tryBtn && suggest ) {
			tryBtn.addEventListener( 'click', function () {
				if ( pop && ! pop.hidden && 'try' === pop.getAttribute( 'data-place' ) ) {
					closePop();
					return;
				}
				openPop( suggest.getAttribute( 'data-try-title' ) || '', Array.prototype.map.call( suggest.children, function ( n ) {
					return { t: labelOf( n ), m: 1 };
				} ), 'try' );
				tryBtn.setAttribute( 'aria-expanded', 'true' );
			} );
		}
		// Suggestions while typing: phrases that contain every word typed (three letters or more).
		function matches( q ) {
			var words = q.toLowerCase().split( /\s+/ ).filter( function ( w ) {
				return w.length >= 2;
			} );
			if ( q.length < 3 || ! words.length ) {
				return [];
			}
			return phrases.filter( function ( p2 ) {
				var t = p2.t.toLowerCase();
				return t !== q.toLowerCase() && words.every( function ( w ) {
					return -1 !== t.indexOf( w );
				} );
			} ).slice( 0, 3 );
		}
		// Finish my question (WPBot Pro): the rest of the first phrase that starts with the text.
		var ghostText = '';
		function placeGhost() {
			ghost.style.left = editor.offsetLeft + 'px';
			ghost.style.top = editor.offsetTop + 'px';
			ghost.style.width = editor.offsetWidth + 'px';
			ghost.style.height = editor.offsetHeight + 'px';
			ghost.style.font = getComputedStyle( editor ).font;
		}
		function updateGhost() {
			if ( ! ghost ) {
				return;
			}
			var v = editor.value;
			var lower = v.toLowerCase();
			var hit = v.length >= 3 ? phrases.find( function ( p2 ) {
				return p2.t.length > v.length && 0 === p2.t.toLowerCase().indexOf( lower );
			} ) : null;
			ghostText = hit ? hit.t : '';
			ghost.innerHTML = hit ? '<span class="qcw-gt">' + esc( v ) + '</span><span class="qcw-gr">' + esc( hit.t.slice( v.length ) ) + '</span>' : '';
			if ( hit ) {
				placeGhost();
			}
		}
		// While an AI Action asks its questions the visitor types answers, not questions: no suggestions
		// and no finish-my-question text (owner on wpbot.pro, 25 November 2026: "Lead capture" was sent
		// in place of an answer).
		function inAction() {
			var st = 'function' === typeof window.qcldWpbotChatState ? window.qcldWpbotChatState() : null;
			return !! ( st && st.aiAction );
		}
		if ( editor && ( gOn( 'composer' ) || gOn( 'ghost' ) ) ) {
			editor.addEventListener( 'input', function () {
				if ( inAction() ) {
					if ( pop && 'typing' === pop.getAttribute( 'data-place' ) ) {
						closePop();
					}
					ghostText = '';
					if ( ghost ) {
						ghost.innerHTML = '';
					}
					return;
				}
				if ( gOn( 'composer' ) ) {
					var found = matches( editor.value.trim() );
					if ( found.length ) {
						openPop( '', found, 'typing' );
					} else if ( pop && 'typing' === pop.getAttribute( 'data-place' ) ) {
						closePop();
					}
				}
				updateGhost();
			} );
		}
		if ( editor ) {
			editor.addEventListener(
				'keydown',
				function ( e ) {
					if ( ghostText && ( 'Tab' === e.key || ( 'ArrowRight' === e.key && editor.selectionStart === editor.value.length ) ) ) {
						e.preventDefault();
						editor.value = ghostText;
						ghostText = '';
						if ( ghost ) {
							ghost.innerHTML = '';
						}
						size();
						return;
					}
					if ( ! pop || pop.hidden || ! popItems.length ) {
						return;
					}
					if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
						e.preventDefault();
						popActive = ( popActive + ( 'ArrowDown' === e.key ? 1 : popItems.length - 1 ) ) % popItems.length;
						armPop();
						Array.prototype.forEach.call( pop.querySelectorAll( '.qcw-pi' ), function ( b, i ) {
							b.setAttribute( 'aria-selected', i === popActive ? 'true' : 'false' );
						} );
					} else if ( 'Enter' === e.key && ! e.shiftKey && popActive >= 0 ) {
						// Stops the conversation script's Enter (keypress) too.
						e.preventDefault();
						e.stopImmediatePropagation();
						choose( popActive );
					} else if ( 'Escape' === e.key ) {
						e.stopPropagation();
						closePop();
					}
				},
				true
			);
			editor.addEventListener( 'blur', function () {
				if ( ghost ) {
					ghost.innerHTML = '';
					ghostText = '';
				}
			} );
		}

		// Nudge (WPBot Pro): once, after 25 seconds open with no message from the visitor.
		if ( gOn( 'nudge' ) && suggest && list ) {
			var nudgeTimer = null;
			var nudged = false;
			var nudgeKey = 'qcw-nudged:' + location.pathname;
			try {
				nudged = !! window.sessionStorage.getItem( nudgeKey );
			} catch ( e ) {}
			var stopNudge = function () {
				clearTimeout( nudgeTimer );
				nudgeTimer = null;
			};
			document.addEventListener( 'qcw:open', function ( e ) {
				if ( nudged || ! e.detail || e.detail.root !== root ) {
					return;
				}
				stopNudge();
				var users = list.querySelectorAll( 'li.wp-chat-user-msg' ).length;
				nudgeTimer = setTimeout( function () {
					if ( nudged || ! isOpen() || list.querySelectorAll( 'li.wp-chat-user-msg' ).length !== users || ( editor && '' !== editor.value ) ) {
						return;
					}
					nudged = true;
					try {
						window.sessionStorage.setItem( nudgeKey, '1' );
					} catch ( err ) {}
					var line = document.createElement( 'li' );
					line.className = 'wp-chatbot-msg qcw-nudge';
					line.innerHTML = '<div class="wp-chatbot-paragraph">' + esc( suggest.getAttribute( 'data-nudge' ) || '' ) + '</div>';
					list.appendChild( line );
					list.appendChild( copies( 'qcw-nudge' ) );
				}, 25000 );
			} );
			document.addEventListener( 'qcw:close', stopNudge );
			if ( editor ) {
				editor.addEventListener( 'input', stopNudge );
			}
			root.addEventListener( 'click', function ( e ) {
				if ( e.target.closest && e.target.closest( '[class*="qcld-chatbot-"]' ) ) {
					stopNudge();
				}
			} );
		}

		/* ---------- send effect ---------- */
		var sendBtn = root.querySelector( '#wp-chatbot-send-message' );
		function sendEffect() {
			if ( ! sendBtn || REDUCE || ! feelMs( root ) ) {
				return;
			}
			var kind = root.getAttribute( 'data-qcw-send' ) || 'ripple';
			if ( 'ripple' !== kind && 'function' === typeof Motion.send[ kind ] ) {
				Motion.send[ kind ]( sendBtn, root );
				return;
			}
			var r = document.createElement( 'span' );
			r.className = 'qcw-ripple';
			r.setAttribute( 'aria-hidden', 'true' );
			sendBtn.appendChild( r );
			setTimeout( function () {
				r.remove();
			}, 650 );
		}
		if ( sendBtn ) {
			sendBtn.addEventListener( 'click', sendEffect );
		}
		if ( editor ) {
			editor.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key && ! e.shiftKey && '' !== editor.value.trim() ) {
					sendEffect();
				}
			} );
		}
	}

	/* ---------- visitor's mute (the menu's Sound item) ---------- */
	var play = window.HTMLMediaElement && window.HTMLMediaElement.prototype.play;
	if ( play ) {
		window.HTMLMediaElement.prototype.play = function () {
			var src = ( this.currentSrc || this.src || '' ) + ' ' + ( this.id || '' );
			if ( prefs().mute && /chatbot|wpbot/i.test( src ) ) {
				return Promise.resolve();
			}
			return play.apply( this, arguments );
		};
	}

	/* ---------- no custom scrollbar inside the window ---------- */
	function patchSlimScroll() {
		var $ = window.jQuery;
		if ( ! $ || ! $.fn || ! $.fn.slimScroll || $.fn.slimScroll.qcw ) {
			return;
		}
		var original = $.fn.slimScroll;
		$.fn.slimScroll = function () {
			var outside = this.filter( function () {
				return ! this.closest( '.qcw' );
			} );
			if ( outside.length ) {
				original.apply( outside, arguments );
			}
			return this;
		};
		$.fn.slimScroll.qcw = true;
	}

	function boot() {
		patchSlimScroll();
		document.querySelectorAll( '.qcw' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
	window.addEventListener( 'load', patchSlimScroll );
	document.addEventListener( 'qcld_wpbot_chat_loaded', boot );
}() );
