/**
 * WPBot launcher: shows only the chat icon and loads the full widget
 * (styles, then scripts in order) on the first interaction, on idle when a
 * setting needs it, or at once for inline chats. Plain JavaScript, no deps.
 * Config: the JSON in <script id="qcld-wpbot-launcher-config">.
 */
( function () {
	'use strict';
	var node = document.getElementById( 'qcld-wpbot-launcher-config' );
	if ( ! node ) {
		return;
	}
	var cfg;
	try {
		cfg = JSON.parse( node.textContent );
	} catch ( e ) {
		return;
	}
	var root = document.documentElement;
	var state = 0; // 0 idle, 1 loading, 2 loaded.
	var replay = null;
	var waiting = [];

	function inline( code ) {
		if ( ! code ) {
			return;
		}
		var s = document.createElement( 'script' );
		s.text = code;
		document.body.appendChild( s );
	}

	function css( item ) {
		return new Promise( function ( done ) {
			var l = document.createElement( 'link' );
			l.rel = 'stylesheet';
			l.id = item.id + '-css';
			l.href = item.src;
			l.media = item.media || 'all';
			l.onload = function () {
				done();
			};
			l.onerror = function () {
				if ( item.orig && l.href !== item.orig ) {
					l.href = item.orig; // Cached copy missing: use the original file.
					return;
				}
				done();
			};
			document.head.appendChild( l );
			if ( item.after ) {
				var st = document.createElement( 'style' );
				st.id = item.id + '-inline-css';
				st.textContent = item.after;
				document.head.appendChild( st );
			}
		} );
	}

	// Handlers for "load" / "DOMContentLoaded" added after the page loaded would never run: run them now.
	function patchLateEvents() {
		var undo = [];
		var nativeAdd = window.addEventListener;
		window.addEventListener = function ( type, fn ) {
			if ( ( 'load' === type || 'DOMContentLoaded' === type ) && 'complete' === document.readyState && 'function' === typeof fn ) {
				setTimeout( function () {
					fn.call( window, new Event( type ) );
				}, 0 );
				return;
			}
			return nativeAdd.apply( this, arguments );
		};
		undo.push( function () {
			window.addEventListener = nativeAdd;
		} );
		var patchedJq = null;
		function patchJq() {
			var $ = window.jQuery;
			if ( ! $ || ! $.fn || patchedJq === $ ) {
				return;
			}
			patchedJq = $;
			var on = $.fn.on;
			$.fn.on = function ( type, fn ) {
				if ( this[ 0 ] === window && 'load' === type && 'function' === typeof fn && 'complete' === document.readyState ) {
					setTimeout( function () {
						fn.call( window, $.Event( 'load' ) );
					}, 0 );
					return this;
				}
				return on.apply( this, arguments );
			};
			undo.push( function () {
				$.fn.on = on;
			} );
		}
		return {
			jq: patchJq,
			undo: function () {
				undo.forEach( function ( f ) {
					f();
				} );
			},
		};
	}

	function js( item, patch ) {
		return new Promise( function ( done ) {
			patch.jq();
			inline( item.before );
			if ( ! item.src ) {
				inline( item.after );
				done();
				return;
			}
			var finish = function () {
				patch.jq();
				inline( item.after );
				done();
			};
			var add = function ( src, fallback ) {
				var s = document.createElement( 'script' );
				s.id = item.id + '-js';
				s.src = src;
				s.async = false;
				s.onload = finish;
				s.onerror = function () {
					s.remove();
					if ( fallback ) {
						add( fallback, '' ); // Cached copy missing: use the original file.
					} else {
						finish();
					}
				};
				document.body.appendChild( s );
			};
			add( item.src, item.orig );
		} );
	}

	function load() {
		if ( state ) {
			return;
		}
		state = 1;
		root.classList.add( 'qcld-wpbot-loading' );
		// Download every script at once; they still run one by one, in order.
		( cfg.scripts || [] ).forEach( function ( item ) {
			if ( item.src ) {
				var l = document.createElement( 'link' );
				l.rel = 'preload';
				l.as = 'script';
				l.href = item.src;
				document.head.appendChild( l );
			}
		} );
		Promise.all( ( cfg.styles || [] ).map( css ) ).then( function () {
			var patch = patchLateEvents();
			var chain = Promise.resolve();
			( cfg.scripts || [] ).forEach( function ( item ) {
				chain = chain.then( function () {
					return js( item, patch );
				} );
			} );
			return chain.then( function () {
				setTimeout( function () {
					patch.undo();
				}, 50 );
			} );
		} ).then( function () {
			state = 2;
			root.classList.remove( 'qcld-wpbot-lazy', 'qcld-wpbot-loading' );
			document.dispatchEvent( new CustomEvent( 'qcld_wpbot_chat_loaded' ) );
			if ( replay ) {
				setTimeout( function () {
					var el = document.querySelector( replay );
					if ( el ) {
						el.click();
					}
				}, 60 );
			}
			waiting.forEach( function ( f ) {
				f();
			} );
		} );
	}

	window.qcldWpbotChatLoad = function ( then ) {
		if ( 'function' === typeof then ) {
			if ( 2 === state ) {
				then();
			} else {
				waiting.push( then );
			}
		}
		load();
	};

	// First interaction with the icon: load, then open.
	document.addEventListener(
		'click',
		function ( e ) {
			if ( 2 === state ) {
				return;
			}
			var hit = e.target && e.target.closest ? e.target.closest( cfg.trigger ) : null;
			if ( hit ) {
				e.preventDefault();
				e.stopPropagation();
				replay = cfg.open;
				load();
			}
		},
		true
	);
	// Keyboard: Enter or Space on a launcher that is not a native button (role="button") acts as a click.
	document.addEventListener(
		'keydown',
		function ( e ) {
			if ( 2 === state || ( 'Enter' !== e.key && ' ' !== e.key && 'Spacebar' !== e.key ) ) {
				return;
			}
			var el = e.target && e.target.closest ? e.target.closest( cfg.open ) : null;
			if ( el ) {
				window.qcldWpbotKeyOpen = Date.now(); // Opened with the keyboard: the widget then moves focus into the chat.
			}
			if ( el && 'BUTTON' !== el.tagName ) {
				e.preventDefault();
				el.click();
			}
		},
		true
	);
	[ 'mouseover', 'touchstart', 'focusin' ].forEach( function ( type ) {
		document.addEventListener(
			type,
			function ( e ) {
				if ( ! state && e.target && e.target.closest && e.target.closest( cfg.trigger ) ) {
					load();
				}
			},
			{ capture: true, passive: true }
		);
	} );

	// Fetch the widget's files into the cache when the page is idle, without running them,
	// so the first open is quick. Does not touch the page's first paint.
	function prefetch() {
		if ( state || ! cfg.prefetch ) {
			return;
		}
		var conn = navigator.connection;
		if ( conn && ( conn.saveData || /2g/.test( conn.effectiveType || '' ) ) ) {
			return;
		}
		( cfg.styles || [] ).concat( cfg.scripts || [] ).forEach( function ( item ) {
			if ( ! item.src ) {
				return;
			}
			var l = document.createElement( 'link' );
			l.rel = 'prefetch';
			l.href = item.src;
			document.head.appendChild( l );
		} );
	}

	if ( 'now' === cfg.mode ) {
		load();
	} else if ( 'interaction' === cfg.mode && cfg.prefetch ) {
		var later = function () {
			setTimeout( function () {
				if ( window.requestIdleCallback ) {
					window.requestIdleCallback( prefetch, { timeout: 4000 } );
				} else {
					prefetch();
				}
			}, cfg.prefetch );
		};
		if ( 'complete' === document.readyState ) {
			later();
		} else {
			window.addEventListener( 'load', later );
		}
	} else if ( 'idle' === cfg.mode ) {
		var go = function () {
			if ( window.requestIdleCallback ) {
				window.requestIdleCallback( load, { timeout: 2500 } );
			} else {
				setTimeout( load, 1200 );
			}
		};
		if ( 'complete' === document.readyState ) {
			go();
		} else {
			window.addEventListener( 'load', go );
		}
	}
} )();
