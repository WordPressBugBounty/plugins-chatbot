/**
 * WPBot Shortcuts: the "Visit this page often? Add shortcut to the Admin Bar" button under the
 * page header, and the "WPBot Shortcuts" admin bar menu it fills (Admin\Shortcuts).
 */
( function () {
	'use strict';
	var cfg = window.qcldWpbotShortcuts;
	if ( ! cfg || ! document.getElementById( 'wpbody-content' ) ) {
		return;
	}
	var t = cfg.i18n;
	var HEADERS = [
		'.qcld-wpbot-unified-header-container',
		'.qcld-wp-chatbot-wrap-header',
		'.wpbot_dashboard_header',
		'.qcwr-host-header',
		'.qcld-wpbot-free-host-header',
		'.qcwpbot-shell-header',
		'.qcwp-page-header',
	];

	/** This page's shortcut address: admin path with its own arguments (the server cleans it). */
	function here() {
		var loc = window.location;
		return loc.pathname.split( '/' ).pop() + loc.search;
	}

	/** Same cleaning as the server, enough to know whether this page is in the list. */
	function key( url ) {
		var a = document.createElement( 'a' );
		a.href = url.indexOf( '://' ) > -1 || url.charAt( 0 ) === '/' ? url : cfg.admin + url;
		var file = a.pathname.split( '/' ).pop();
		var keep = [];
		var page = '';
		a.search.replace( /^\?/, '' ).split( '&' ).forEach( function ( pair ) {
			if ( ! pair ) {
				return;
			}
			var name = decodeURIComponent( pair.split( '=' )[ 0 ] ).toLowerCase();
			if ( /^(_wpnonce|nonce|_wp_http_referer|action|action2|message|msg|updated|settings-updated|saved|deleted|error|notice|paged|qcld-saved)$/.test( name ) ) {
				return;
			}
			if ( 'page' === name ) {
				page = pair;
			} else {
				keep.push( pair );
			}
		} );
		if ( page ) {
			keep.unshift( page );
		}
		return file + ( keep.length ? '?' + keep.join( '&' ) : '' );
	}

	/** Name for the menu: the page title, plus the open tab or section when the page has one. */
	function names() {
		var title = cfg.title || document.title.split( /\s[‹|–-]\s/ )[ 0 ];
		var body = document.getElementById( 'wpbody-content' );
		var current = body.querySelector( '.wpb-subnav__item.is-current, [aria-current="page"], .nav-tab-active, .qcwp-side-item.is-active, .qcld-wpbot-subnav-link.is-active' );
		var sub = '';
		if ( current ) {
			// The label only: no counts, badges or screen reader text.
			var copy = current.cloneNode( true );
			copy.querySelectorAll( '.screen-reader-text, .count, .awaiting-mod, .update-plugins, [class*="badge"], [class*="count"], [class*="pill"], .dashicons, svg' ).forEach( function ( el ) {
				el.parentNode.removeChild( el );
			} );
			sub = copy.textContent.replace( /\s+/g, ' ' ).replace( /\s*\d+\s*$/, '' ).trim();
		}
		if ( sub && sub.length < 60 && sub.toLowerCase() !== title.toLowerCase() ) {
			return { title: sub, group: title };
		}
		return { title: title, group: '' };
	}

	var urls = ( cfg.urls || [] ).map( key );
	var mine = key( here() );
	var wrap = document.createElement( 'div' );
	wrap.className = 'qcld-wpbot-shortcut-wrap';
	var btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'qcld-wpbot-shortcut-btn';
	var status = document.createElement( 'span' );
	status.className = 'screen-reader-text';
	status.setAttribute( 'role', 'status' );
	wrap.appendChild( btn );
	wrap.appendChild( status );

	// The WPBot design's header band has a slot for the button: a star only, the words as its tooltip.
	var slot = document.querySelector( '.wpb-band__slot[data-wpb-slot="shortcut"]' );

	function draw() {
		var added = urls.indexOf( mine ) > -1;
		btn.classList.toggle( 'is-added', added );
		btn.setAttribute( 'aria-pressed', added ? 'true' : 'false' );
		btn.innerHTML = '';
		if ( slot ) {
			btn.className = 'qcld-wpbot-shortcut-btn wpb-band__shortcut' + ( added ? ' is-added' : '' );
			btn.innerHTML = added
				? '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>'
				: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>';
			var words = added ? t.added + ' ' + t.menu + ' (' + t.remove + ')' : t.ask + ' ' + t.add;
			btn.setAttribute( 'title', words );
			var sr = document.createElement( 'span' );
			sr.className = 'screen-reader-text';
			sr.textContent = words;
			btn.appendChild( sr );
			// A short visible label on wide screens (owner, 20 November 2026: the star alone was easy to miss).
			var label = document.createElement( 'span' );
			label.className = 'wpb-band__shortcut-label';
			label.setAttribute( 'aria-hidden', 'true' );
			label.textContent = added ? ( t.shorted || t.added ) : ( t.short || t.add );
			btn.appendChild( label );
			return;
		}
		var icon = document.createElement( 'span' );
		icon.className = 'dashicons ' + ( added ? 'dashicons-star-filled' : 'dashicons-star-empty' );
		icon.setAttribute( 'aria-hidden', 'true' );
		btn.appendChild( icon );
		btn.appendChild( document.createTextNode( ( added ? t.added : t.ask ) + ' ' ) );
		var strong = document.createElement( 'strong' );
		strong.textContent = added ? t.menu : t.add;
		btn.appendChild( strong );
		if ( added ) {
			var undo = document.createElement( 'span' );
			undo.className = 'qcld-wpbot-shortcut-undo';
			undo.textContent = t.remove;
			btn.appendChild( undo );
		}
	}

	/** Redraw the admin bar menu from the saved list (shown once there is a shortcut). */
	function bar( items ) {
		var root = document.getElementById( 'wp-admin-bar-root-default' );
		if ( ! root ) {
			return;
		}
		var node = document.getElementById( 'wp-admin-bar-wpbot-shortcuts' );
		if ( ! items.length ) {
			if ( node ) {
				node.parentNode.removeChild( node );
			}
			return;
		}
		if ( ! node ) {
			node = document.createElement( 'li' );
			node.id = 'wp-admin-bar-wpbot-shortcuts';
			node.className = 'menupop qcld-wpbot-shortcuts';
			var top = document.createElement( 'a' );
			top.className = 'ab-item';
			top.setAttribute( 'aria-haspopup', 'true' );
			top.innerHTML = '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label"></span>';
			top.querySelector( '.ab-label' ).textContent = t.menu;
			node.appendChild( top );
			var sub = document.createElement( 'div' );
			sub.className = 'ab-sub-wrapper';
			sub.innerHTML = '<ul class="ab-submenu" id="wp-admin-bar-wpbot-shortcuts-default"></ul>';
			node.appendChild( sub );
			// WordPress opens menus on hover with this class; keyboard users get focus-within.
			node.addEventListener( 'mouseenter', function () {
				node.classList.add( 'hover' );
			} );
			node.addEventListener( 'mouseleave', function () {
				node.classList.remove( 'hover' );
			} );
			var after = document.getElementById( 'wp-admin-bar-new-content' );
			if ( after && after.parentNode === root ) {
				root.insertBefore( node, after.nextSibling );
			} else {
				root.appendChild( node );
			}
		}
		node.querySelector( '.ab-item' ).href = items[ 0 ].href;
		var list = node.querySelector( '.ab-submenu' );
		list.innerHTML = '';
		items.forEach( function ( item, i ) {
			var li = document.createElement( 'li' );
			li.id = 'wp-admin-bar-wpbot-shortcuts-' + i;
			var a = document.createElement( 'a' );
			a.className = 'ab-item';
			a.href = item.href;
			var name = document.createElement( 'span' );
			name.className = 'qcld-wpbot-shortcut-title';
			name.textContent = item.title;
			a.appendChild( name );
			if ( item.group && item.group !== item.title ) {
				var group = document.createElement( 'span' );
				group.className = 'qcld-wpbot-shortcut-group';
				group.textContent = item.group;
				a.appendChild( group );
			}
			li.appendChild( a );
			list.appendChild( li );
		} );
		var manage = document.createElement( 'li' );
		manage.id = 'wp-admin-bar-wpbot-shortcuts-manage';
		manage.className = 'qcld-wpbot-shortcuts-manage';
		manage.innerHTML = '<a class="ab-item"></a>';
		manage.firstChild.href = cfg.manage;
		manage.firstChild.textContent = t.manage;
		list.appendChild( manage );
	}

	function send( method, body ) {
		return window.fetch( cfg.rest, {
			method: method,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				if ( ! res.ok ) {
					throw data;
				}
				return data;
			} );
		} );
	}

	btn.addEventListener( 'click', function () {
		var added = urls.indexOf( mine ) > -1;
		var n = names();
		btn.disabled = true;
		status.textContent = t.working;
		var req = added ? send( 'DELETE', { url: here() } ) : send( 'POST', { url: here(), title: n.title, group: n.group } );
		req.then( function ( data ) {
			urls = ( data.items || [] ).map( function ( item ) {
				return key( item.url );
			} );
			bar( data.items || [] );
			draw();
			status.textContent = urls.indexOf( mine ) > -1 ? t.added + ' ' + t.menu : t.ask;
		} ).catch( function ( err ) {
			status.textContent = err && err.code === 'qcld_wpbot_core_shortcut_full' ? t.full : t.failed;
			window.alert( status.textContent );
		} ).then( function () {
			btn.disabled = false;
		} );
	} );

	function place() {
		if ( slot ) {
			slot.appendChild( btn );
			slot.appendChild( status );
			return;
		}
		var body = document.getElementById( 'wpbody-content' );
		var header = null;
		for ( var i = 0; i < HEADERS.length && ! header; i++ ) {
			var found = body.querySelectorAll( HEADERS[ i ] );
			for ( var j = 0; j < found.length; j++ ) {
				if ( found[ j ].offsetParent !== null ) {
					header = found[ j ];
					break;
				}
			}
		}
		if ( header ) {
			header.parentNode.insertBefore( wrap, header.nextSibling );
			return;
		}
		var host = body.querySelector( '.wrap' ) || body;
		host.insertBefore( wrap, host.firstChild );
	}

	draw();
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', place );
	} else {
		place();
	}
}() );
