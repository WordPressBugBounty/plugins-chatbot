/**
 * WPBot admin pages: browsers never fill in or offer to save keys and secrets (owner, 23 November
 * 2026: the Teams App ID and Client secret looked filled in, with the browser's saved user name and
 * password, and Chrome offered to save a password after saving ChatBot settings).
 *
 * A password field makes the browser treat a form as a sign-in form: it fills the saved user name
 * into the text field before it and the saved password into it, and offers to save what was typed.
 * Here, key and secret fields are text fields drawn as dots (where the browser can draw them so), so
 * no form on these pages looks like a sign-in form. Elsewhere they stay password fields marked
 * "new-password", which browsers do not fill in.
 */
( function () {
	'use strict';

	var dots = !! ( window.CSS && window.CSS.supports && ( window.CSS.supports( '-webkit-text-security', 'disc' ) || window.CSS.supports( 'text-security', 'disc' ) ) );

	function quiet( input ) {
		input.setAttribute( 'autocomplete', 'password' === input.type ? 'new-password' : 'off' );
		input.setAttribute( 'data-lpignore', 'true' );
		input.setAttribute( 'data-1p-ignore', 'true' );
		input.setAttribute( 'data-bwignore', 'true' );
	}

	var observing = false;

	function mask( input ) {
		input.wpbotOwnChange = observing;
		input.type = 'text';
		input.classList.add( 'wpbot-secret' );
	}

	function fix( input ) {
		if ( input.getAttribute( 'data-wpbot-secret' ) ) {
			return;
		}
		input.setAttribute( 'data-wpbot-secret', '1' );
		if ( dots ) {
			mask( input );
			input.setAttribute( 'spellcheck', 'false' );
		}
		quiet( input );
		// The field before it (an App ID, a user name) is where the browser would put a saved user name.
		var form = input.form;
		if ( form ) {
			Array.prototype.forEach.call( form.querySelectorAll( 'input[type="text"]:not([data-wpbot-secret]), input[type="email"], input:not([type])' ), quiet );
		}
	}

	function scan( root ) {
		if ( root && root.querySelectorAll ) {
			Array.prototype.forEach.call( root.querySelectorAll( 'input[type="password"]' ), fix );
		}
	}

	function start() {
		scan( document );
		if ( window.MutationObserver ) {
			new MutationObserver( function ( muts ) {
				muts.forEach( function ( m ) {
					if ( 'attributes' === m.type ) {
						var input = m.target;
						if ( input.wpbotOwnChange ) {
							input.wpbotOwnChange = false;
						} else if ( 'password' === input.type ) {
							// A field added as a password field, or a "Hide" button: dots, as a text field.
							if ( dots ) {
								input.setAttribute( 'data-wpbot-secret', '1' );
								mask( input );
							}
							quiet( input );
						} else if ( 'text' === input.type && input.getAttribute( 'data-wpbot-secret' ) ) {
							// A "Show" button: show the text.
							input.classList.remove( 'wpbot-secret' );
						}
						return;
					}
					Array.prototype.forEach.call( m.addedNodes, function ( n ) {
						if ( 1 === n.nodeType ) {
							if ( 'INPUT' === n.tagName && 'password' === n.type ) {
								fix( n );
							} else {
								scan( n );
							}
						}
					} );
				} );
			} ).observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'type' ] } );
			observing = true;
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
