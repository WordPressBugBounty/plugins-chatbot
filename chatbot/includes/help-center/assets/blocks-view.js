/**
 * Help Center content blocks: front end (no jQuery). Tabs, screenshot zoom,
 * and a copy button on code blocks in articles. Without JavaScript, tabs read
 * as sections and screenshots stay at their normal size.
 */
( function () {
	'use strict';

	var i18n = window.qcldWpbotHelpCenterBlocks || {};
	var uid = 0;

	function tabs( root ) {
		var sections = Array.prototype.filter.call( root.children, function ( n ) {
			return n.classList && n.classList.contains( 'wpbot-help-tab' );
		} );
		if ( sections.length < 2 || root.classList.contains( 'is-ready' ) ) {
			return;
		}
		var list = document.createElement( 'div' );
		list.className = 'wpbot-help-tabs__list';
		list.setAttribute( 'role', 'tablist' );
		var buttons = sections.map( function ( section, i ) {
			var id = 'wpbot-help-tab-' + ++uid;
			var label = section.querySelector( '.wpbot-help-tab__label' );
			var panel = section.querySelector( '.wpbot-help-tab__panel' ) || section;
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'wpbot-help-tabs__tab';
			button.id = id;
			button.setAttribute( 'role', 'tab' );
			button.textContent = label ? label.textContent : String( i + 1 );
			panel.setAttribute( 'role', 'tabpanel' );
			panel.setAttribute( 'aria-labelledby', id );
			panel.id = id + '-panel';
			button.setAttribute( 'aria-controls', panel.id );
			list.appendChild( button );
			return button;
		} );
		function select( index, focus ) {
			buttons.forEach( function ( b, i ) {
				var on = i === index;
				b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				b.tabIndex = on ? 0 : -1;
				sections[ i ].hidden = ! on;
				if ( on && focus ) {
					b.focus();
				}
			} );
		}
		buttons.forEach( function ( b, i ) {
			b.addEventListener( 'click', function () {
				select( i, false );
			} );
			b.addEventListener( 'keydown', function ( e ) {
				var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: buttons.length - 1 }[ e.key ];
				if ( undefined !== next ) {
					e.preventDefault();
					select( ( next + buttons.length ) % buttons.length, true );
				}
			} );
		} );
		root.insertBefore( list, root.firstChild );
		root.classList.add( 'is-ready' );
		select( 0, false );
	}

	function zoom( figure ) {
		var frame = figure.querySelector( '.wpbot-help-screenshot__frame' );
		if ( ! frame || frame.hasAttribute( 'tabindex' ) ) {
			return;
		}
		frame.tabIndex = 0;
		frame.setAttribute( 'role', 'button' );
		frame.setAttribute( 'aria-label', i18n.zoom || 'Zoom' );
		function toggle() {
			var on = ! figure.classList.contains( 'is-zoomed' );
			figure.classList.toggle( 'is-zoomed', on );
			frame.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		}
		frame.addEventListener( 'click', toggle );
		frame.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				toggle();
			} else if ( 'Escape' === e.key && figure.classList.contains( 'is-zoomed' ) ) {
				toggle();
			}
		} );
	}

	function copy( pre ) {
		if ( pre.querySelector( '.wpbot-help-copy' ) || ! navigator.clipboard ) {
			return;
		}
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'wpbot-help-copy';
		button.textContent = i18n.copy || 'Copy';
		button.addEventListener( 'click', function () {
			var code = pre.querySelector( 'code' ) || pre;
			navigator.clipboard.writeText( code.innerText.replace( /\n$/, '' ) ).then( function () {
				button.textContent = i18n.copied || 'Copied';
				setTimeout( function () {
					button.textContent = i18n.copy || 'Copy';
				}, 1500 );
			} );
		} );
		pre.classList.add( 'has-copy' );
		pre.appendChild( button );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-help-tabs' ), tabs );
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-help-screenshot' ), zoom );
		Array.prototype.forEach.call( document.querySelectorAll( '.wpbot-help-article pre, pre.wp-block-code' ), copy );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
