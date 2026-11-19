/**
 * Chat button designer (owner, 18 November 2026): a live preview of the site and picker cards for
 * every option. Printed inline by Admin\Launcher_Settings inside the hosts' settings form; the values
 * travel in hidden fields and save with the form. The preview frame is the site's own front page
 * (Widget\Launcher_Preview): small changes are sent to it and shown at once, changes that rebuild
 * the floating box or swap the icon picture reload it.
 */
( function () {
	'use strict';
	// The hosts print the Icons list after the designer: start once the whole form is there.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', designer );
	} else {
		designer();
	}
	function designer() {
	var root = document.getElementById( 'qcld-wpbot-designer' );
	if ( ! root ) {
		return;
	}
	var cfg;
	try {
		cfg = JSON.parse( root.querySelector( '.qwd-config' ).textContent );
	} catch ( e ) {
		return;
	}
	var T = cfg.text;
	var C = cfg.choices;
	var frame = root.querySelector( '.qwd-frame' );
	var panel = root.querySelector( '.qwd-panel' );
	var tools = root.querySelector( '.qwd-tools' );

	function field( key ) {
		return root.querySelector( 'input[data-key="' + key + '"]' );
	}
	function get( key ) {
		return field( key ).value;
	}
	function set( key, value ) {
		field( key ).value = String( value );
	}
	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}

	// The hosts' Icons list (radio buttons elsewhere in the same form) is where the picture icons live.
	function hostRadios() {
		return Array.prototype.slice.call( document.querySelectorAll( 'input[name="wp_chatbot_icon"]' ) );
	}
	function hostIcon() {
		var r = document.querySelector( 'input[name="wp_chatbot_icon"]:checked' );
		return r ? r.value : '';
	}
	function iconShown() {
		var h = hostIcon();
		if ( -1 !== cfg.uploads.indexOf( h ) ) {
			return 'upload';
		}
		var i = get( 'icon' );
		if ( 'site' !== i ) {
			return i;
		}
		if ( cfg.aliases[ h ] ) {
			return cfg.aliases[ h ];
		}
		return h === cfg.logoIcon ? 'logo' : 'site';
	}

	function classes() {
		var out = [ 'qcld-wpbot-d2', 'qcld-wpbot-f-' + get( 'finish' ), 'qcld-wpbot-box-' + get( 'box.layout' ) ];
		if ( 'solid' !== get( 'style' ) ) {
			out.push( 'qcld-wpbot-st-' + get( 'style' ) );
		}
		if ( '1' === get( 'motion.enabled' ) ) {
			out.push( 'qcld-wpbot-m', 'qcld-wpbot-o-' + get( 'motion.open' ) );
			if ( 'none' !== get( 'motion.wait' ) ) {
				out.push( 'qcld-wpbot-w-' + get( 'motion.wait' ) );
			}
			if ( 'none' !== get( 'motion.enter' ) ) {
				out.push( 'qcld-wpbot-e-' + get( 'motion.enter' ) );
			}
		}
		var icon = iconShown();
		if ( cfg.masks[ icon ] ) {
			out.push( 'qcld-wpbot-vec' );
		} else if ( 'logo' === icon ) {
			out.push( 'qcld-wpbot-logo' );
		}
		return out;
	}
	function vars() {
		var icon = iconShown();
		var v = { '--qcld-wpbot-c': get( 'color' ), '--qcld-wpbot-s': get( 'size' ) + 'px' };
		if ( cfg.masks[ icon ] ) {
			v[ '--qcld-wpbot-mask-icon' ] = cfg.masks[ icon ];
		}
		if ( 'logo' === icon ) {
			v[ '--qcld-wpbot-logo' ] = 'url("' + cfg.logo + '")';
		}
		return v;
	}
	function theme() {
		return {
			design: get( 'design' ),
			finish: get( 'finish' ),
			style: get( 'style' ),
			icon: get( 'icon' ),
			color: get( 'color' ),
			size: parseInt( get( 'size' ), 10 ),
			motion: { enabled: '1' === get( 'motion.enabled' ), wait: get( 'motion.wait' ), enter: get( 'motion.enter' ), open: get( 'motion.open' ) },
			box: { layout: get( 'box.layout' ), typing: '1' === get( 'box.typing' ) }
		};
	}

	// Preview.
	function send( extra ) {
		if ( ! frame.contentWindow ) {
			return;
		}
		// The look only applies to the new design; replay and open or close work for both.
		var msg = 'modern' === get( 'design' ) ? { type: 'qcld-wpbot-preview', classes: classes(), vars: vars() } : { type: 'qcld-wpbot-preview' };
		Object.keys( extra || {} ).forEach( function ( k ) {
			msg[ k ] = extra[ k ];
		} );
		try {
			frame.contentWindow.postMessage( msg, window.location.origin );
		} catch ( e ) {
			// The frame is still loading: the next load applies the values anyway.
		}
	}
	var reloadTimer = null;
	function reload() {
		clearTimeout( reloadTimer );
		reloadTimer = setTimeout( function () {
			var path = document.getElementById( 'wp_chatbot_custom_icon_path' );
			frame.src = cfg.preview + '&qcld_wpbot_theme=' + encodeURIComponent( JSON.stringify( theme() ) ) + '&qcld_wpbot_icon=' + encodeURIComponent( hostIcon() ) +
				( 'custom.png' === hostIcon() && path && path.value ? '&qcld_wpbot_icon_path=' + encodeURIComponent( path.value ) : '' );
		}, 250 );
	}

	// A new-design option picked while Classic is on switches to the new design (owner, 18 November
	// 2026: in Classic the options looked washed out and did nothing).
	var designSeg = null;
	function modern() {
		if ( 'modern' === get( 'design' ) ) {
			return false;
		}
		set( 'design', 'modern' );
		if ( designSeg ) {
			mark( designSeg, 'modern' );
		}
		root.classList.remove( 'qwd-classic' );
		reload();
		return true;
	}
	function apply( extra ) {
		if ( ! modern() ) {
			send( extra );
		}
	}

	// UI pieces.
	function section( title, help ) {
		var s = el( 'div', 'qwd-section' ); // Not a <section>: the hosts' tab CSS hides sections outside the open tab.
		s.appendChild( el( 'h5', 'qwd-title', title ) );
		if ( help ) {
			s.appendChild( el( 'p', 'qwd-help', help ) );
		}
		panel.appendChild( s );
		return s;
	}
	function sub( parent, title ) {
		parent.appendChild( el( 'h6', 'qwd-sub', title ) );
	}
	function cards( parent, key, list, after, cls ) {
		var wrap = el( 'div', 'qwd-cards' + ( cls ? ' ' + cls : '' ) );
		Object.keys( list ).forEach( function ( value ) {
			var b = el( 'button', 'qwd-card' );
			b.type = 'button';
			b.setAttribute( 'data-value', value );
			if ( 'finish' === key ) {
				var sw = el( 'span', 'qwd-swatch qwd-f-' + value );
				// The admin theme flattens backgrounds to its own colors: the swatch shows the real finish.
				sw.setAttribute( 'data-qa-skip', '' );
				sw.style.setProperty( '--c', get( 'color' ) );
				b.appendChild( sw );
			}
			b.appendChild( el( 'b', '', list[ value ][ 0 ] ) );
			b.appendChild( el( 'span', '', list[ value ][ 1 ] ) );
			b.addEventListener( 'click', function () {
				set( key, value );
				mark( wrap, value );
				after( value );
			} );
			wrap.appendChild( b );
		} );
		mark( wrap, get( key ) );
		parent.appendChild( wrap );
		return wrap;
	}
	function mark( wrap, value ) {
		Array.prototype.forEach.call( wrap.querySelectorAll( '[data-value]' ), function ( b ) {
			b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-value' ) === String( value ) ) );
		} );
	}
	function seg( parent, key, list, after ) {
		var wrap = el( 'div', 'qwd-seg' );
		Object.keys( list ).forEach( function ( value ) {
			var b = el( 'button', '', list[ value ] );
			b.type = 'button';
			b.setAttribute( 'data-value', value );
			b.addEventListener( 'click', function () {
				set( key, value );
				mark( wrap, value );
				after( value );
			} );
			wrap.appendChild( b );
		} );
		mark( wrap, get( key ) );
		parent.appendChild( wrap );
		return wrap;
	}
	function toggle( parent, key, label, after ) {
		var l = el( 'label', 'qwd-toggle' );
		var i = document.createElement( 'input' );
		i.type = 'checkbox';
		i.checked = '1' === get( key );
		i.addEventListener( 'change', function () {
			set( key, i.checked ? '1' : '0' );
			after( i.checked );
		} );
		l.appendChild( i );
		l.appendChild( document.createTextNode( ' ' + label ) );
		parent.appendChild( l );
	}

	// Design.
	var sDesign = section( T.design );
	designSeg = seg( sDesign, 'design', { modern: T.modern, classic: T.classic }, function () {
		root.classList.toggle( 'qwd-classic', 'classic' === get( 'design' ) );
		reload();
	} );
	sDesign.appendChild( el( 'p', 'qwd-help qwd-classic-note', T.classicNote ) );
	root.classList.toggle( 'qwd-classic', 'classic' === get( 'design' ) );

	// Icon.
	var sIcon = section( T.icon, T.iconHelp );
	var iconWrap = el( 'div', 'qwd-icons' );
	function markIcons() {
		var shownHost = hostIcon();
		var forced = get( 'icon' );
		Array.prototype.forEach.call( iconWrap.querySelectorAll( '[data-icon]' ), function ( b ) {
			var v = b.getAttribute( 'data-icon' );
			var on = 'site' === forced ? b.getAttribute( 'data-host' ) === '1' && v === shownHost : v === forced && b.getAttribute( 'data-host' ) !== '1';
			b.setAttribute( 'aria-pressed', String( on ) );
		} );
	}
	sub( sIcon, T.yours );
	var hostGrid = el( 'div', 'qwd-icon-grid' );
	hostRadios().forEach( function ( r ) {
		var label = r.closest( 'label' ) || document.querySelector( 'label[for="' + r.id + '"]' );
		var img = label ? label.querySelector( 'img' ) : null;
		var b = el( 'button', 'qwd-icon' );
		b.type = 'button';
		b.setAttribute( 'data-icon', r.value );
		b.setAttribute( 'data-host', '1' );
		b.title = label ? label.textContent.replace( /\s+/g, ' ' ).trim() : r.value;
		b.setAttribute( 'aria-label', b.title );
		if ( img ) {
			var i = document.createElement( 'img' );
			i.src = img.src;
			i.alt = '';
			b.appendChild( i );
		}
		b.addEventListener( 'click', function () {
			r.checked = true;
			r.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			set( 'icon', 'site' );
			markIcons();
			reload();
		} );
		hostGrid.appendChild( b );
	} );
	iconWrap.appendChild( hostGrid );
	// Upload: the host's own uploader (the media library), so the address is saved where it always was.
	var hostUpload = document.querySelector( '.wp_chatbot_custom_icon_button, .qcld_chatbot_custom_icon_button' );
	var customRadio = document.querySelector( 'input[name="wp_chatbot_icon"][value="custom.png"]' );
	if ( hostUpload && customRadio ) {
		var up = el( 'button', 'button qwd-upload', T.upload );
		up.type = 'button';
		up.addEventListener( 'click', function () {
			hostUpload.click();
		} );
		iconWrap.appendChild( up );
		// The host's uploader writes the new address into the hidden path field (without an event):
		// watch it after a click, then show and pick the upload.
		var path = document.getElementById( 'wp_chatbot_custom_icon_path' );
		var watchTimer = null;
		up.addEventListener( 'click', function () {
			var before = path ? path.value : '';
			var tries = 0;
			clearInterval( watchTimer );
			watchTimer = setInterval( function () {
				if ( ! path || ++tries > 1200 ) {
					clearInterval( watchTimer );
					return;
				}
				if ( path.value && path.value !== before ) {
					clearInterval( watchTimer );
					var mirror = hostGrid.querySelector( '[data-icon="custom.png"] img' );
					if ( mirror ) {
						mirror.src = path.value;
					}
					customRadio.checked = true;
					customRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					set( 'icon', 'site' );
					markIcons();
					reload();
				}
			}, 500 );
		} );
	}
	// The Icons list and its upload row are in this picker now: hide them, they stay in the form
	// (owner, 18 November 2026: "i think we have duplicate icon settings").
	function hideHost( n ) {
		if ( n && ! n.contains( root ) ) {
			n.classList.add( 'qwd-host-hidden' );
		}
	}
	var list = hostRadios()[ 0 ] ? hostRadios()[ 0 ].closest( '.cxsc-settings-blocks' ) : null;
	if ( list ) {
		hideHost( list );
		var head = list.previousElementSibling;
		if ( head && /^H[1-6]$/.test( head.tagName ) ) {
			hideHost( head );
		}
	}
	var pathField = document.getElementById( 'wp_chatbot_custom_icon_path' );
	hideHost( pathField ? pathField.closest( '.form-group' ) : null );
	sub( iconWrap, T.drawn );
	var drawnGrid = el( 'div', 'qwd-icon-grid' );
	var inList = {};
	hostRadios().forEach( function ( r ) {
		if ( cfg.aliases[ r.value ] ) {
			inList[ cfg.aliases[ r.value ] ] = true;
		}
	} );
	Object.keys( cfg.icons ).forEach( function ( id ) {
		if ( inList[ id ] ) {
			return; // Shown once, in the list above.
		}
		var b = el( 'button', 'qwd-icon qwd-icon-vec' );
		b.type = 'button';
		b.setAttribute( 'data-icon', id );
		b.title = cfg.icons[ id ];
		b.setAttribute( 'aria-label', cfg.icons[ id ] );
		b.innerHTML = cfg.svgs[ id ].replace( /#000/g, 'currentColor' );
		b.addEventListener( 'click', function () {
			set( 'icon', id );
			markIcons();
			apply();
		} );
		drawnGrid.appendChild( b );
	} );
	iconWrap.appendChild( drawnGrid );
	sIcon.appendChild( iconWrap );
	hostRadios().forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			markIcons();
			reload();
		} );
	} );
	markIcons();

	// Button.
	var sButton = section( T.button );
	sub( sButton, T.finish );
	var finishCards = cards( sButton, 'finish', C.finish, function () {
		apply();
	}, 'qwd-cards-finish' );
	sub( sButton, T.style );
	seg( sButton, 'style', { solid: C.style.solid[ 0 ], white: C.style.white[ 0 ], bare: C.style.bare[ 0 ] }, function () {
		apply();
	} );
	sub( sButton, T.color );
	var colors = el( 'div', 'qwd-colors' );
	colors.setAttribute( 'data-qa-skip', '' );
	[ '#3d8bd8', '#2f6fe4', '#6d4ae0', '#0f8f7e', '#e2553f', '#2b2f3a' ].forEach( function ( c ) {
		var b = el( 'button', 'qwd-color' );
		b.type = 'button';
		b.style.background = c;
		b.setAttribute( 'data-value', c );
		b.setAttribute( 'aria-label', c );
		b.addEventListener( 'click', function () {
			pick( c );
		} );
		colors.appendChild( b );
	} );
	var custom = document.createElement( 'input' );
	custom.type = 'color';
	custom.className = 'qwd-color-input';
	custom.value = get( 'color' ).length === 7 ? get( 'color' ) : '#3d8bd8';
	custom.setAttribute( 'aria-label', T.color );
	custom.addEventListener( 'input', function () {
		pick( custom.value );
	} );
	colors.appendChild( custom );
	sButton.appendChild( colors );
	function pick( c ) {
		set( 'color', c );
		mark( colors, c );
		Array.prototype.forEach.call( finishCards.querySelectorAll( '.qwd-swatch' ), function ( sw ) {
			sw.style.setProperty( '--c', c );
		} );
		apply();
	}
	mark( colors, get( 'color' ) );
	sub( sButton, T.size );
	seg( sButton, 'size', { 52: '52 px', 60: '60 px', 68: '68 px' }, function () {
		apply();
	} );

	// Motion.
	var sMotion = section( T.motion, T.reduced );
	toggle( sMotion, 'motion.enabled', T.animate, function () {
		apply( { replay: true } );
	} );
	sub( sMotion, T.enter );
	cards( sMotion, 'motion.enter', C.enter, function () {
		apply( { replay: true } );
	}, 'qwd-cards-small' );
	sub( sMotion, T.wait );
	cards( sMotion, 'motion.wait', C.wait, function () {
		apply();
	}, 'qwd-cards-small' );
	sub( sMotion, T.open );
	cards( sMotion, 'motion.open', C.open, function () {
		if ( modern() ) {
			return;
		}
		// Show it: open, then close again.
		send( { toggle: true } );
		setTimeout( function () {
			send( { toggle: true } );
		}, 1600 );
	}, 'qwd-cards-small' );

	// Floating box.
	var sBox = section( T.box );
	var hostOff = document.getElementById( 'disable_wp_chatbot_notification' );
	cards( sBox, 'box.layout', C.layout, function ( value ) {
		if ( hostOff ) {
			hostOff.checked = 'off' === value;
		}
		modern();
		reload();
	} );
	toggle( sBox, 'box.typing', T.typing, function () {
		reload();
	} );
	if ( hostOff ) {
		hostOff.addEventListener( 'change', function () {
			if ( hostOff.checked ) {
				set( 'box.layout', 'off' );
			} else if ( 'off' === get( 'box.layout' ) ) {
				set( 'box.layout', 'preview' );
			}
			mark( sBox.querySelector( '.qwd-cards' ), get( 'box.layout' ) );
		} );
	}

	// Preview tools.
	function tool( label, run ) {
		var b = el( 'button', 'button', label );
		b.type = 'button';
		b.addEventListener( 'click', run );
		tools.appendChild( b );
		return b;
	}
	tool( T.replay, function () {
		send( { replay: true } );
	} );
	tool( T.toggle, function () {
		send( { toggle: true } );
	} );
	var phone = tool( T.phone, function () {
		root.classList.toggle( 'qwd-phone' );
		phone.setAttribute( 'aria-pressed', String( root.classList.contains( 'qwd-phone' ) ) );
		fit();
	} );

	// The preview is the site at a real desktop (or phone) size, so the open chat window and the
	// floating box show whole (owner, 18 November 2026: the preview was too small). On desktop it shows
	// the page's bottom-right corner, where the button and the chat live, at nearly real size: a whole
	// page scaled into the column is too small to read.
	var wrap = root.querySelector( '.qwd-frame-wrap' );
	function fit() {
		var isPhone = root.classList.contains( 'qwd-phone' );
		var vw = isPhone ? 390 : 1100;
		var vh = 780;
		var shown = isPhone ? vw : 620;
		var room = wrap.parentNode.clientWidth || shown;
		var tall = Math.max( 360, ( window.innerHeight || 900 ) - 150 );
		var scale = Math.min( 1, room / shown, tall / vh );
		var width = Math.round( Math.min( room, vw * scale ) );
		frame.style.width = vw + 'px';
		frame.style.height = vh + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		frame.style.left = Math.round( width - vw * scale ) + 'px';
		wrap.style.width = width + 'px';
		wrap.style.height = Math.round( vh * scale ) + 'px';
	}
	fit();
	if ( window.ResizeObserver ) {
		new ResizeObserver( fit ).observe( wrap.parentNode );
	}
	window.addEventListener( 'resize', fit );
	phone.setAttribute( 'aria-pressed', 'false' );
	frame.addEventListener( 'load', function () {
		send();
	} );
	reload();
	}
}() );
