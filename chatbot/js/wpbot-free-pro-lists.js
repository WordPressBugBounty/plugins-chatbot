/**
 * WPBot Free: fills each element with data-qcld-pro="<feature id>" with that WPBot Pro
 * feature's Benefits and Features lists (rendered and escaped in PHP by
 * QCLD_WPBot_Free_Pro_Catalogue::enqueue_lists). Watches the page, so screens built by
 * React or jQuery get their lists as they render.
 */
( function () {
	'use strict';
	var data = window.qcldWpbotProLists;
	if ( ! data || ! data.html ) {
		return;
	}
	if ( data.css && ! document.getElementById( 'qcld-wpbot-pro-css' ) ) {
		var style = document.createElement( 'style' );
		style.id = 'qcld-wpbot-pro-css';
		style.textContent = data.css;
		document.head.appendChild( style );
	}
	function fill( root ) {
		var nodes = ( root || document ).querySelectorAll( '[data-qcld-pro]:not([data-qcld-pro-done])' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			var html = data.html[ node.getAttribute( 'data-qcld-pro' ) ];
			if ( html ) {
				node.innerHTML = html;
				node.setAttribute( 'data-qcld-pro-done', '1' );
			}
		} );
	}
	// Screens without placeholders (bundles we do not rebuild): next to their "PRO" badges,
	// add one placeholder per container. Rule: scope, containers (selectors in order of preference), id.
	function inject() {
		( data.inject || [] ).forEach( function ( rule ) {
			var scope = document.querySelector( rule.scope );
			if ( ! scope ) {
				return;
			}
			Array.prototype.forEach.call( scope.querySelectorAll( 'span' ), function ( badge ) {
				if ( 'PRO' !== badge.textContent.trim() || badge.closest( '.qcld-wpbot-pro' ) ) {
					return;
				}
				// Containers in order of preference: the first one found around the badge wins.
				var box = null;
				String( rule.containers ).split( ',' ).some( function ( sel ) {
					box = badge.closest( sel.trim() );
					return !! box;
				} );
				if ( ! box ) {
					return;
				}
				var isGrid = /(^|\s)grid(\s|$)/.test( box.className );
				var host = isGrid ? box.parentNode : box;
				if ( host.querySelector( ':scope > [data-qcld-pro]' ) ) {
					return;
				}
				var holder = document.createElement( 'div' );
				holder.setAttribute( 'data-qcld-pro', rule.id );
				holder.className = 'qcld-wpbot-pro-injected';
				host.appendChild( holder );
			} );
		} );
	}
	function start() {
		inject();
		fill( document );
		if ( window.MutationObserver ) {
			new MutationObserver( function () {
				inject();
				fill( document );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
	window.qcldWpbotProFill = fill;
}() );
