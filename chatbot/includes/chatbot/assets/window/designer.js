/**
 * Chat window designer (owner, 20 November 2026). Plain JavaScript; printed inline by
 * Admin\Window_Settings. Keeps the whole window theme in one hidden field (saved with the host's
 * settings form) and shows it in a frame of the site: colours change at once, everything else
 * reloads the frame with the edited theme in its (nonce-checked) address.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'qcw-designer' );
	if ( ! root || root.getAttribute( 'data-ready' ) ) {
		return;
	}
	root.setAttribute( 'data-ready', '1' );

	var C = JSON.parse( root.querySelector( '.qcwd-config' ).textContent );
	var T = C.text;
	var L = C.labels;
	var field = root.querySelector( '.qcwd-field' );
	var frame = root.querySelector( '.qcwd-frame' );
	var state = JSON.parse( JSON.stringify( C.theme ) );
	var tab = 'design';
	var previewMode = '';
	var phone = false;

	/* ---------- helpers ---------- */
	function get( path ) {
		return path.split( '.' ).reduce( function ( o, k ) {
			return o && 'object' === typeof o ? o[ k ] : undefined;
		}, state );
	}
	function put( path, value ) {
		var keys = path.split( '.' );
		var o = state;
		keys.slice( 0, -1 ).forEach( function ( k ) {
			if ( ! o[ k ] || 'object' !== typeof o[ k ] ) {
				o[ k ] = {};
			}
			o = o[ k ];
		} );
		o[ keys[ keys.length - 1 ] ] = value;
	}
	function esc( t ) {
		var d = document.createElement( 'div' );
		d.textContent = null === t || undefined === t ? '' : String( t );
		return d.innerHTML;
	}
	function needsPro( key, value ) {
		if ( C.pro || ! C.proOnly[ key ] ) {
			return false;
		}
		var rule = C.proOnly[ key ];
		if ( Array.isArray( rule ) ) {
			return -1 !== rule.indexOf( value );
		}
		return value !== rule.free;
	}
	function badge() {
		return ' <span class="qcwd-pro" title="' + esc( T.proNote ) + '">' + esc( T.pro ) + '</span>';
	}

	/* ---------- colours (the same rules as Window_Colors) ---------- */
	function rgb( h ) {
		h = /^#[0-9a-f]{6}$/i.test( h ) ? h : '#000000';
		return [ 1, 3, 5 ].map( function ( i ) {
			return parseInt( h.substr( i, 2 ), 16 );
		} );
	}
	function mix( a, b, t ) {
		var x = rgb( a );
		var y = rgb( b );
		return '#' + x.map( function ( v, i ) {
			var n = Math.round( Math.max( 0, Math.min( 255, v + ( y[ i ] - v ) * t ) ) );
			return ( n < 16 ? '0' : '' ) + n.toString( 16 );
		} ).join( '' );
	}
	function lum( h ) {
		var w = [ 0.2126, 0.7152, 0.0722 ];
		return rgb( h ).reduce( function ( s, v, i ) {
			v /= 255;
			v = v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
			return s + v * w[ i ];
		}, 0 );
	}
	function contrast( a, b ) {
		var x = lum( a );
		var y = lum( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	}
	function bestText( bg ) {
		var dark = mix( '#111318', bg, 0.08 );
		return contrast( '#ffffff', bg ) >= contrast( dark, bg ) ? '#ffffff' : dark;
	}
	function ensure( fg, bg, min ) {
		var target = contrast( '#ffffff', bg ) >= contrast( '#000000', bg ) ? '#ffffff' : '#000000';
		for ( var i = 0; i < 20 && contrast( fg, bg ) < min; i++ ) {
			fg = mix( fg, target, 0.12 );
		}
		return contrast( fg, bg ) < min ? target : fg;
	}
	var TEXT_FOR = { accent: 'accentFg', head: 'headFg', bg: 'fg', bot: 'botFg', me: 'meFg' };
	function classic( seed, dark ) {
		var c;
		if ( ! dark ) {
			c = { accent: seed, head: seed, bg: mix( '#f4f5f7', seed, 0.035 ), fg: mix( '#121419', seed, 0.1 ), bot: '#ffffff', me: seed, composer: '#ffffff', border: mix( '#e4e6ea', seed, 0.08 ) };
		} else {
			var base = '#0f1014';
			var accent = seed;
			for ( var i = 0; i < 12 && contrast( accent, base ) < 4.2; i++ ) {
				accent = mix( accent, '#ffffff', 0.1 );
			}
			c = { accent: accent, head: mix( base, seed, 0.42 ), bg: mix( '#121317', seed, 0.05 ), fg: mix( '#f3f4f7', seed, 0.05 ), bot: mix( '#1f2127', seed, 0.07 ), me: accent, composer: mix( '#1a1c21', seed, 0.05 ), border: mix( base, seed, 0.24 ) };
		}
		c.accentFg = bestText( c.accent );
		c.headFg = bestText( c.head );
		c.botFg = c.fg;
		c.meFg = bestText( c.me );
		c.chip = ensure( c.accent, c.composer, 4.5 );
		return c;
	}
	function brand( palette, seed, tokens, dark ) {
		var p = Object.assign( {}, palette );
		tokens.forEach( function ( t ) {
			var v = seed;
			for ( var i = 0; dark && p.bg && i < 12 && contrast( v, p.bg ) < 4.2; i++ ) {
				v = mix( v, '#ffffff', 0.1 );
			}
			p[ t ] = v;
			if ( TEXT_FOR[ t ] ) {
				p[ TEXT_FOR[ t ] ] = bestText( v );
			}
		} );
		return p;
	}
	function applyBrand( seed ) {
		var meta = C.designs[ state.design ] || {};
		if ( meta.own || ! /^#[0-9a-f]{6}$/i.test( seed ) ) {
			return;
		}
		[ 'light', 'dark' ].forEach( function ( m ) {
			var dark = 'dark' === m;
			if ( 'all' === meta.brand ) {
				state.colors[ m ] = classic( seed, dark );
			} else if ( Array.isArray( meta.brand ) && meta.brand.length ) {
				state.colors[ m ] = brand( state.colors[ m ], seed, meta.brand, dark );
			}
		} );
	}

	/* ---------- preview ---------- */
	var reloadTimer = null;
	function sync() {
		field.value = JSON.stringify( state );
	}
	function frameUrl() {
		var url = C.preview + '&' + encodeURIComponent( C.arg ) + '=' + encodeURIComponent( JSON.stringify( state ) );
		return url;
	}
	function reload() {
		clearTimeout( reloadTimer );
		reloadTimer = setTimeout( function () {
			frame.src = frameUrl();
		}, 450 );
	}
	function tokens() {
		var mode = previewMode || state.mode;
		var palette = state.colors[ 'dark' === mode ? 'dark' : 'light' ];
		var vars = {};
		Object.keys( palette ).forEach( function ( k ) {
			vars[ '--qcw-' + k.replace( /([A-Z])/g, '-$1' ).toLowerCase() ] = palette[ k ];
		} );
		vars[ '--qcw-w' ] = state.width + 'px';
		vars[ '--qcw-h' ] = state.height + 'px';
		vars[ '--qcw-r' ] = state.radius + 'px';
		vars[ '--qcw-blur' ] = state.material.blur + 'px';
		vars[ '--qcw-alpha' ] = state.material.opacity + '%';
		return vars;
	}
	function instant() {
		try {
			frame.contentWindow.postMessage( { type: 'qcld-wpbot-window', vars: tokens(), mode: previewMode || state.mode }, window.location.origin );
		} catch ( e ) {}
	}
	function changed( kind ) {
		sync();
		if ( 'instant' === kind ) {
			instant();
		} else {
			reload();
		}
	}

	/* ---------- controls ---------- */
	function seg( key, title, values ) {
		var current = get( key );
		values = values || C.choices[ key ];
		var html = '<div class="qcwd-row"><div class="qcwd-label">' + esc( title ) + '</div><div class="qcwd-seg" role="group" aria-label="' + esc( title ) + '">';
		values.forEach( function ( v ) {
			var pro = needsPro( key, v );
			var name = ( L[ key ] && L[ key ][ v ] ) || v;
			html += '<button type="button" data-key="' + esc( key ) + '" data-v="' + esc( v ) + '" aria-pressed="' + ( v === current ? 'true' : 'false' ) + '"' + ( pro ? ' disabled aria-disabled="true"' : '' ) + '>' + esc( name ) + ( pro ? badge() : '' ) + '</button>';
		} );
		return html + '</div></div>';
	}
	function range( key, title, unit ) {
		var r = C.ranges[ key ];
		var v = get( key );
		return '<div class="qcwd-row"><label class="qcwd-label" for="qcwd-' + esc( key ) + '">' + esc( title ) + ' <output>' + esc( v ) + esc( unit ) + '</output></label>' +
			'<input type="range" id="qcwd-' + esc( key ) + '" data-range="' + esc( key ) + '" data-unit="' + esc( unit ) + '" min="' + r[ 0 ] + '" max="' + r[ 1 ] + '" value="' + esc( v ) + '"></div>';
	}
	function flag( key ) {
		var pro = needsPro( key, true );
		var on = !! get( key ) && ! pro;
		return '<label class="qcwd-check"><input type="checkbox" data-flag="' + esc( key ) + '"' + ( on ? ' checked' : '' ) + ( pro ? ' disabled' : '' ) + '> <span>' + esc( L.flags[ key ] || key ) + ( pro ? badge() : '' ) + '</span></label>';
	}
	function group( title, inner ) {
		return '<fieldset class="qcwd-group"><legend>' + esc( title ) + '</legend>' + inner + '</fieldset>';
	}

	var TABS = {
		design: function () {
			var html = '<p class="qcwd-help">' + esc( T.designHelp ) + '</p><div class="qcwd-designs">';
			Object.keys( C.designs ).forEach( function ( id ) {
				var d = C.designs[ id ];
				var locked = ! d.available;
				html += '<button type="button" class="qcwd-design qcwd-th-' + esc( id ) + '" data-design="' + esc( id ) + '" aria-pressed="' + ( id === state.design ? 'true' : 'false' ) + '"' + ( locked ? ' disabled aria-disabled="true"' : '' ) + '>' +
					'<span class="qcwd-thumb" aria-hidden="true"></span><span class="qcwd-dname">' + esc( d.name ) + ( locked ? badge() : '' ) + '</span><span class="qcwd-ddesc">' + esc( d.description ) + '</span></button>';
			} );
			html += '</div>';
			if ( 'holo' === state.design ) {
				html += group( T.holo, seg( 'holo.header', T.holoHeader ) + seg( 'holo.replies', T.holoReplies ) + seg( 'holo.send', T.holoSend ) );
			}
			return html;
		},
		layout: function () {
			var html = seg( 'layout', T.layout, [ 'floating', 'compact', 'side' ] ) + seg( 'side', T.side );
			html += range( 'width', T.width, 'px' ) + range( 'height', T.height, 'px' );
			if ( 'side' === state.layout ) {
				html += range( 'sideWidth', T.sideWidth, 'px' ) + flag( 'push' );
			}
			return html + range( 'radius', T.radius, 'px' );
		},
		colours: function () {
			var html = seg( 'mode', T.mode );
			var meta = C.designs[ state.design ] || {};
			if ( ! meta.own ) {
				html += '<div class="qcwd-row"><label class="qcwd-label" for="qcwd-brand">' + esc( T.brand ) + '</label><div class="qcwd-inline"><input type="color" id="qcwd-brand" value="' + esc( state.colors.light.accent ) + '"> <button type="button" class="button qcwd-brand-apply">' + esc( T.apply ) + '</button></div><p class="qcwd-help">' + esc( T.brandHelp ) + '</p></div>';
			}
			[ 'light', 'dark' ].forEach( function ( m ) {
				var inner = '<div class="qcwd-tokens">';
				Object.keys( L.tokens ).forEach( function ( k ) {
					inner += '<label class="qcwd-token"><input type="color" data-color="' + m + '.' + k + '" value="' + esc( state.colors[ m ][ k ] ) + '"> <span>' + esc( L.tokens[ k ] ) + '</span></label>';
				} );
				html += group( 'light' === m ? T.light : T.dark, inner + '</div>' );
			} );
			return html + '<button type="button" class="button qcwd-reset-colors">' + esc( T.resetColors ) + '</button>';
		},
		material: function () {
			return seg( 'material.type', T.material ) + range( 'material.blur', T.blur, 'px' ) + range( 'material.opacity', T.opacity, '%' ) + seg( 'material.header', T.header ) + seg( 'material.inside', T.inside ) +
				group( T.fonts, flag( 'customFonts' ) );
		},
		motion: function () {
			return seg( 'motion.open', T.open ) + seg( 'motion.feel', T.feel ) + seg( 'motion.messages', T.messages ) + seg( 'motion.typing', T.typing ) + seg( 'motion.send', T.send ) +
				group( T.extras, flag( 'motion.stagger' ) + flag( 'motion.breathe' ) + flag( 'motion.scan' ) );
		},
		guidance: function () {
			return group( T.guidance, [ 'guide.page', 'guide.tryask', 'guide.composer', 'guide.ghost', 'guide.follow', 'guide.nudge', 'guide.bottom', 'guide.tip' ].map( flag ).join( '' ) ) +
				seg( 'suggestionsStyle', T.sgStyle ) + seg( 'startMenuStyle', T.smStyle ) +
				group( T.menu, [ 'menu.new', 'menu.email', 'menu.download', 'menu.sound', 'menu.text', 'menu.lang', 'menu.person' ].map( flag ).join( '' ) );
		},
	};
	var TAB_NAMES = { design: T.tabDesign, layout: T.tabLayout, colours: T.tabColours, material: T.tabMaterial, motion: T.tabMotion, guidance: T.tabGuidance };

	var tabsEl = root.querySelector( '.qcwd-tabs' );
	var bodyEl = root.querySelector( '.qcwd-body' );
	function render() {
		tabsEl.innerHTML = Object.keys( TABS ).map( function ( k ) {
			return '<button type="button" role="tab" id="qcwd-tab-' + k + '" aria-controls="qcwd-body" aria-selected="' + ( k === tab ? 'true' : 'false' ) + '" tabindex="' + ( k === tab ? '0' : '-1' ) + '" data-tab="' + k + '">' + esc( TAB_NAMES[ k ] ) + '</button>';
		} ).join( '' );
		bodyEl.id = 'qcwd-body';
		bodyEl.setAttribute( 'role', 'tabpanel' );
		bodyEl.setAttribute( 'aria-labelledby', 'qcwd-tab-' + tab );
		bodyEl.innerHTML = TABS[ tab ]();
	}

	tabsEl.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-tab]' );
		if ( b ) {
			tab = b.getAttribute( 'data-tab' );
			render();
			tabsEl.querySelector( '[aria-selected="true"]' ).focus();
		}
	} );
	tabsEl.addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowRight' !== e.key && 'ArrowLeft' !== e.key ) {
			return;
		}
		var keys = Object.keys( TABS );
		var i = keys.indexOf( tab ) + ( 'ArrowRight' === e.key ? 1 : -1 );
		tab = keys[ ( i + keys.length ) % keys.length ];
		render();
		tabsEl.querySelector( '[aria-selected="true"]' ).focus();
	} );

	bodyEl.addEventListener( 'click', function ( e ) {
		var d = e.target.closest( '[data-design]' );
		if ( d && ! d.disabled ) {
			var id = d.getAttribute( 'data-design' );
			var keep = { mode: state.mode, menu: state.menu, guide: state.guide, customFonts: state.customFonts };
			var brandColor = state.colors.light.accent;
			var was = C.designs[ state.design ] || {};
			state = JSON.parse( JSON.stringify( C.defaults[ id ] ) );
			Object.assign( state, keep );
			if ( ! was.own && 'classic' !== id ) {
				applyBrand( brandColor );
			}
			render();
			changed();
			return;
		}
		var s = e.target.closest( '[data-key]' );
		if ( s && ! s.disabled ) {
			put( s.getAttribute( 'data-key' ), s.getAttribute( 'data-v' ) );
			render();
			changed( 'mode' === s.getAttribute( 'data-key' ) ? 'instant' : '' );
			return;
		}
		if ( e.target.closest( '.qcwd-brand-apply' ) ) {
			applyBrand( root.querySelector( '#qcwd-brand' ).value );
			render();
			changed( 'instant' );
			return;
		}
		if ( e.target.closest( '.qcwd-reset-colors' ) ) {
			state.colors = JSON.parse( JSON.stringify( C.defaults[ state.design ].colors ) );
			render();
			changed( 'instant' );
		}
	} );
	bodyEl.addEventListener( 'input', function ( e ) {
		var t = e.target;
		if ( t.hasAttribute( 'data-range' ) ) {
			var key = t.getAttribute( 'data-range' );
			put( key, parseInt( t.value, 10 ) );
			t.previousElementSibling.querySelector( 'output' ).textContent = t.value + t.getAttribute( 'data-unit' );
			changed( 'sideWidth' === key ? '' : 'instant' );
		} else if ( t.hasAttribute( 'data-color' ) && /^#[0-9a-f]{6}$/i.test( t.value ) ) {
			put( 'colors.' + t.getAttribute( 'data-color' ), t.value.toLowerCase() );
			changed( 'instant' );
		}
	} );
	bodyEl.addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( t.hasAttribute( 'data-flag' ) ) {
			put( t.getAttribute( 'data-flag' ), t.checked );
			if ( 'layout' === tab ) {
				render();
			}
			changed();
		}
	} );

	/* ---------- preview tools ---------- */
	var tools = root.querySelector( '.qcwd-tools' );
	tools.innerHTML = '<label class="qcwd-check"><input type="checkbox" class="qcwd-phone"> <span>' + esc( T.phone ) + '</span></label>' +
		'<label class="qcwd-check"><input type="checkbox" class="qcwd-dark"> <span>' + esc( T.previewDark ) + '</span></label>' +
		'<button type="button" class="button qcwd-reload">' + esc( T.reload ) + '</button>';
	tools.addEventListener( 'change', function ( e ) {
		if ( e.target.classList.contains( 'qcwd-phone' ) ) {
			phone = e.target.checked;
			root.classList.toggle( 'qcwd-is-phone', phone );
		}
		if ( e.target.classList.contains( 'qcwd-dark' ) ) {
			previewMode = e.target.checked ? 'dark' : '';
			instant();
		}
	} );
	tools.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.qcwd-reload' ) ) {
			frame.src = frameUrl();
		}
	} );
	frame.addEventListener( 'load', function () {
		if ( previewMode ) {
			setTimeout( instant, 600 );
		}
	} );

	// Show the site at desktop width (1280 px) scaled into the column, or at phone width.
	var wrap = root.querySelector( '.qcwd-frame-wrap' );
	function fit() {
		if ( phone ) {
			frame.style.width = '390px';
			frame.style.height = '780px';
			frame.style.transform = 'none';
			wrap.style.height = '780px';
			return;
		}
		var scale = Math.min( 1, ( wrap.clientWidth || 1280 ) / 1280 );
		frame.style.width = '1280px';
		frame.style.height = '860px';
		frame.style.transform = 'scale(' + scale + ')';
		wrap.style.height = Math.round( 860 * scale ) + 'px';
	}
	if ( window.ResizeObserver ) {
		new ResizeObserver( fit ).observe( wrap );
	}
	tools.addEventListener( 'change', fit );

	render();
	sync();
	fit();
	frame.src = frameUrl();
}() );
