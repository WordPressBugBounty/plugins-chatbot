/**
 * QuantumCloud admin theme: maps plugin colors to the theme's tokens, and runs the switch.
 *
 * Mapping: every element in the page content is read once (its real background, text and
 * border colors) and gets .qa-* classes that point those colors at the theme's tokens:
 * white and pale tints become surfaces, the brand purple becomes the accent, pale
 * red/green/amber/blue become their soft tokens, dark text becomes the text tokens, and so on.
 * All reads happen before any write, so the page is laid out once.
 */
( function () {
	'use strict';

	var config = window.qcldAdminThemeConfig;
	if ( ! config ) {
		return;
	}

	var root = document.documentElement;
	var SYNC_KEY = 'qcld-admin-theme-sync';
	var MAP_RE = /(^|\s)qa-(bg|fg|bd|bdl|head|noshadow|guard)[\w-]*/g;
	// Never recolored: WordPress chrome (styled by the stylesheet), media, color pickers, editors,
	// the module's own UI, and what the host plugins list (config.skip, e.g. a live widget preview).
	var SKIP_TREE = [ '#wpadminbar', '#adminmenumain', 'img', 'svg', 'video', 'canvas', 'iframe', 'picture', 'object', 'embed', '.qa-switch', '.wp-picker-container', '.iris-picker', '.CodeMirror', '.mce-container', '.swal2-icon', '[data-qa-skip]' ].concat( config.skip || [] ).join( ',' );
	var state = { mode: config.mode, scope: config.scope, all: config.all || '' };
	var lastLook = config.last || 'light'; // Last look other than Classic.
	var mq = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;
	var observer = null;

	/* ------------------------------------------------------------------ */
	/* Colors                                                             */
	/* ------------------------------------------------------------------ */

	function parse( str ) {
		var m = /^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\s*\)/.exec( str || '' );
		if ( ! m ) {
			var fn = /^(?:oklch|oklab|lab|lch|hsla?|hwb|color)\([^)]*\)/.exec( str || '' );
			return fn ? toRgb( fn[ 0 ] ) : null;
		}
		var a = m[ 4 ] === undefined ? 1 : ( m[ 4 ].indexOf( '%' ) > -1 ? parseFloat( m[ 4 ] ) / 100 : parseFloat( m[ 4 ] ) );
		return { r: +m[ 1 ], g: +m[ 2 ], b: +m[ 3 ], a: a };
	}

	// Newer color syntaxes (Tailwind 4 writes oklch(), browsers report it as written): painted on one
	// pixel and read back as sRGB. Without this the Automator's cards and texts were never mapped, so
	// Dark showed light text on its white cards (QA, 18 November 2026).
	var COLOR_FN_RE = /(?:rgba?|oklch|oklab|lab|lch|hsla?|hwb|color)\([^)]+\)/g;
	var rgbCache = new Map();
	var pixel = null;

	function toRgb( str ) {
		if ( rgbCache.has( str ) ) {
			return rgbCache.get( str );
		}
		var result = null;
		try {
			if ( ! pixel ) {
				var canvas = document.createElement( 'canvas' );
				canvas.width = 1;
				canvas.height = 1;
				pixel = canvas.getContext( '2d', { willReadFrequently: true } );
			}
			pixel.clearRect( 0, 0, 1, 1 );
			pixel.fillStyle = '#000';
			pixel.fillStyle = str;
			pixel.fillRect( 0, 0, 1, 1 );
			var d = pixel.getImageData( 0, 0, 1, 1 ).data;
			result = { r: d[ 0 ], g: d[ 1 ], b: d[ 2 ], a: d[ 3 ] / 255 };
		} catch ( e ) {
			result = null;
		}
		rgbCache.set( str, result );
		return result;
	}

	// Composite over white, then HSL.
	function info( c ) {
		var r = ( c.r * c.a + 255 * ( 1 - c.a ) ) / 255;
		var g = ( c.g * c.a + 255 * ( 1 - c.a ) ) / 255;
		var b = ( c.b * c.a + 255 * ( 1 - c.a ) ) / 255;
		var max = Math.max( r, g, b );
		var min = Math.min( r, g, b );
		var l = ( max + min ) / 2;
		var d = max - min;
		var s = d === 0 ? 0 : d / ( 1 - Math.abs( 2 * l - 1 ) );
		var h = 0;
		if ( d ) {
			if ( max === r ) {
				h = 60 * ( ( ( g - b ) / d ) % 6 );
			} else if ( max === g ) {
				h = 60 * ( ( b - r ) / d + 2 );
			} else {
				h = 60 * ( ( r - g ) / d + 4 );
			}
		}
		if ( h < 0 ) {
			h += 360;
		}
		var family;
		if ( d < 0.07 || s < 0.12 ) {
			family = 'neutral';
		} else if ( h >= 235 && h < 292 ) {
			family = 'purple';
		} else if ( h >= 8 && h < 28 && s > 0.4 ) {
			family = 'clay'; // The theme's own accent: left as it is.
		} else if ( h >= 292 || h < 15 ) {
			family = 'red';
		} else if ( h < 62 ) {
			family = 'amber';
		} else if ( h < 175 ) {
			family = 'green';
		} else {
			family = 'blue';
		}
		return { l: l, s: s, h: h, chroma: d, family: family };
	}

	function bgClass( c, area, knob ) {
		if ( ! c || c.a < 0.08 ) {
			return '';
		}
		var i = info( c );
		if ( i.l >= 0.985 ) {
			// White: switch knobs and dots (small, round, about square) stay white.
			return knob ? '' : 'qa-bg-surface';
		}
		if ( i.l >= 0.86 ) {
			if ( i.family === 'neutral' || ( ( i.family === 'purple' || i.family === 'blue' ) && i.s < 0.55 ) ) {
				return 'qa-bg-sub';
			}
			if ( i.family === 'purple' ) {
				return 'qa-bg-accent-soft';
			}
			return { red: 'qa-bg-danger-soft', green: 'qa-bg-success-soft', amber: 'qa-bg-warn-soft', blue: 'qa-bg-info-soft' }[ i.family ] || '';
		}
		if ( i.family === 'purple' ) {
			return i.l >= 0.72 ? 'qa-bg-sub' : ( i.l >= 0.18 ? 'qa-bg-accent' : '' );
		}
		return '';
	}

	// Font icons (Dashicons, Font Awesome, ...): large and colored like a heading, but they must keep
	// their icon font, else the glyph shows as an empty square (QA, 17 November 2026).
	var ICON_RE = /(^|\s)(dashicons|fa|fas|far|fab|fal|fad|fa-[\w-]+|glyphicon|bi|material-icons|material-symbols-[\w-]+|eicon-[\w-]+|icon-[\w-]+)(\s|$)/;

	function isIcon( el ) {
		return el.tagName === 'I' || ( typeof el.className === 'string' && ICON_RE.test( el.className ) );
	}

	function fgClass( c, el, size, weight, borderPurple ) {
		if ( ! c || c.a < 0.3 ) {
			return '';
		}
		var i = info( c );
		if ( i.l > 0.9 ) {
			return '';
		}
		var tag = el.tagName;
		var heading = ! isIcon( el ) && ( /^H[1-3]$/.test( tag ) || size >= 22 || ( size >= 18 && weight >= 600 ) );
		if ( i.family === 'purple' && i.s > 0.2 && i.l >= 0.3 && i.l < 0.75 ) {
			if ( borderPurple ) {
				return 'qa-fg-1';
			}
			return heading ? 'qa-head' : 'qa-fg-accent';
		}
		if ( i.family === 'neutral' || i.s < 0.35 || ( i.family === 'purple' ) ) {
			if ( heading && i.l < 0.42 ) {
				return 'qa-head';
			}
			return i.l < 0.22 ? 'qa-fg-1' : ( i.l < 0.42 ? 'qa-fg-2' : 'qa-fg-3' );
		}
		return { blue: 'qa-fg-accent', red: 'qa-fg-danger', green: 'qa-fg-success', amber: 'qa-fg-warn' }[ i.family ] || '';
	}

	// A border the plugin made invisible on purpose: white or nearly white, or the element's own
	// background. Recolored, it drew extra boxes around settings (QA, 17 November 2026).
	function hidden( color, cs ) {
		var c = parse( color );
		if ( ! c || c.a < 0.08 ) {
			return true;
		}
		if ( info( c ).l >= 0.97 ) {
			return true;
		}
		var bg = parse( cs.backgroundColor );
		return !! ( bg && bg.a >= 0.99 && Math.abs( bg.r - c.r ) + Math.abs( bg.g - c.g ) + Math.abs( bg.b - c.b ) < 6 );
	}

	function borderKind( c ) {
		if ( ! c || c.a < 0.08 ) {
			return '';
		}
		var i = info( c );
		if ( i.family === 'red' && i.s > 0.4 && i.l < 0.75 ) {
			return 'danger';
		}
		if ( i.l >= 0.8 && ( i.family === 'neutral' || i.family === 'purple' || i.family === 'blue' ) ) {
			return 'base';
		}
		if ( i.family === 'neutral' && i.l >= 0.4 ) {
			return 'strong';
		}
		if ( i.family === 'purple' && i.s > 0.15 ) {
			return 'strong';
		}
		// WordPress's admin color on outlined buttons (blue in most color schemes): its text is mapped to the
		// accent, so a blue frame stayed around clay text (QA, 18 November 2026).
		if ( i.family === 'blue' && i.s > 0.45 && i.l >= 0.25 && i.l < 0.72 ) {
			return 'accent';
		}
		return '';
	}

	// A border in the element's own background color (drawn to look borderless).
	function sameAsBackground( color, cs ) {
		var c = parse( color );
		var bg = parse( cs.backgroundColor );
		return !! ( c && bg && c.a >= 0.08 && bg.a >= 0.99 && Math.abs( bg.r - c.r ) + Math.abs( bg.g - c.g ) + Math.abs( bg.b - c.b ) < 6 );
	}

	function gradientClass( image ) {
		if ( ! image || image.indexOf( 'gradient' ) === -1 || image.indexOf( 'url(' ) > -1 ) {
			return '';
		}
		var colors = image.match( COLOR_FN_RE ) || [];
		var light = true;
		var purple = false;
		var see = false;
		for ( var k = 0; k < colors.length; k++ ) {
			var c = parse( colors[ k ] );
			if ( ! c || c.a < 0.08 ) {
				continue;
			}
			var i = info( c );
			if ( i.l < 0.86 ) {
				light = false;
			}
			if ( i.family === 'purple' && i.l < 0.72 ) {
				purple = true;
			}
			if ( c.a < 0.9 ) {
				see = true;
			}
		}
		if ( light ) {
			return 'qa-bg-flat qa-bg-sub';
		}
		// A see-through fade over content (a "show more" curtain) stays a fade, in the accent; made solid it
		// hid the content and looked like a tall empty bar (QA, 17 November 2026).
		if ( purple && see ) {
			return 'qa-bg-fade';
		}
		return purple ? 'qa-bg-flat qa-bg-accent' : '';
	}

	/* ------------------------------------------------------------------ */
	/* Mapping                                                            */
	/* ------------------------------------------------------------------ */

	function skip( el ) {
		return el.nodeType !== 1 || ( el.closest && el.closest( SKIP_TREE ) );
	}

	function strip( el ) {
		if ( typeof el.className === 'string' && el.className.indexOf( 'qa-' ) > -1 ) {
			el.className = el.className.replace( MAP_RE, '' ).replace( /\s+/g, ' ' ).trim();
		}
	}

	// Whether the element's background really comes from its style attribute. An inline
	// "background: transparent" under a stylesheet color (React tabs in AI Marketing) is not a color
	// chosen on purpose: kept, it left white and purple tabs in Dark (QA, 18 November 2026).
	function inlineBackground( el, cs ) {
		if ( ! el.style ) {
			return false;
		}
		if ( el.style.backgroundImage && el.style.backgroundImage !== 'none' && el.style.backgroundImage !== 'initial' ) {
			return true;
		}
		var value = el.style.backgroundColor;
		if ( ! value ) {
			return false;
		}
		var own = /^rgba?\(/.test( value ) ? parse( value ) : toRgb( value );
		if ( ! own || own.a < 0.08 ) {
			return false;
		}
		var real = parse( cs.backgroundColor );
		return !! real && Math.abs( real.r - own.r ) + Math.abs( real.g - own.g ) + Math.abs( real.b - own.b ) < 6;
	}

	// Read phase: returns the classes for one element (no writes).
	function plan( el, parentColor ) {
		var cs = window.getComputedStyle( el );
		var classes = [];
		var area = el.offsetWidth * el.offsetHeight;
		var inlineBg = inlineBackground( el, cs );

		var bg = '';
		var h = el.offsetHeight;
		var w = el.offsetWidth;
		// A switch track: small, wider than tall, fully rounded, pale. Kept visible against cards.
		var track = h && h <= 40 && w <= 90 && w > h && parseFloat( cs.borderTopLeftRadius ) >= h / 2 - 1;
		// A color set inline is usually chosen on purpose (a swatch, a brand button) and is kept, except a
		// large white or pale panel (a card or notice written with style="background:#fff").
		var inlinePanel = false;
		if ( inlineBg && area >= 6000 ) {
			var plain = bgClass( parse( cs.backgroundColor ), area, false );
			inlinePanel = plain === 'qa-bg-surface' || plain === 'qa-bg-sub' || plain === 'qa-bg-page';
		}
		if ( ! inlineBg || inlinePanel ) {
			var knob = area && area < 1100 && w && h && w / h < 1.6 && h / w < 1.6 && parseFloat( cs.borderTopLeftRadius ) >= Math.min( w, h ) / 2 - 1;
			bg = gradientClass( cs.backgroundImage ) || bgClass( parse( cs.backgroundColor ), area, knob );
			if ( track && ( bg === 'qa-bg-sub' || bg === 'qa-bg-surface' ) ) {
				bg = 'qa-bg-track';
			}
			// Stripes and hover tints painted with a huge inset shadow (Bootstrap and DataTables tables):
			// the shadow covers the background, so it decides the color and is dropped.
			if ( cs.boxShadow && cs.boxShadow.indexOf( 'inset' ) > -1 && /\b\d{4,}px/.test( cs.boxShadow ) ) {
				var tint = bgClass( parse( cs.boxShadow ), 0 );
				if ( tint && ( ! bg || bg === 'qa-bg-surface' ) ) {
					bg = tint;
				}
				classes.push( 'qa-noshadow' );
			}
			if ( bg ) {
				classes.push( bg );
			}
		}

		// Borders: top/right/bottom together, left on its own (accent stripes).
		var side = parseFloat( cs.borderTopWidth ) ? cs.borderTopColor : ( parseFloat( cs.borderBottomWidth ) ? cs.borderBottomColor : ( parseFloat( cs.borderRightWidth ) ? cs.borderRightColor : '' ) );
		if ( side && hidden( side, cs ) ) {
			side = '';
		}
		var kind = side ? borderKind( parse( side ) ) : '';
		var sideInfo = side ? parse( side ) : null;
		var borderPurple = !! ( sideInfo && info( sideInfo ).family === 'purple' && info( sideInfo ).s > 0.15 );
		if ( kind === 'base' ) {
			classes.push( 'qa-bd' );
		} else if ( kind === 'strong' ) {
			classes.push( 'qa-bd-strong' );
		} else if ( kind === 'accent' ) {
			classes.push( 'qa-bd-accent' );
		}
		// Same color as the background, which is being remapped: made transparent so the new background
		// shows through it (a purple frame stayed around the clay "Allow" button, QA 18 November 2026).
		if ( bg ) {
			var anySide = parseFloat( cs.borderTopWidth ) ? cs.borderTopColor : ( parseFloat( cs.borderBottomWidth ) ? cs.borderBottomColor : ( parseFloat( cs.borderLeftWidth ) ? cs.borderLeftColor : ( parseFloat( cs.borderRightWidth ) ? cs.borderRightColor : '' ) ) );
			if ( anySide && sameAsBackground( anySide, cs ) ) {
				classes.push( 'qa-bd-clear' );
			}
		}
		if ( parseFloat( cs.borderLeftWidth ) && ! hidden( cs.borderLeftColor, cs ) ) {
			var left = borderKind( parse( cs.borderLeftColor ) );
			if ( left === 'base' ) {
				classes.push( 'qa-bdl' );
			} else if ( left === 'strong' ) {
				classes.push( 'qa-bdl-strong' );
			} else if ( left === 'danger' ) {
				classes.push( 'qa-bdl-danger' );
			}
		}

		// Text: on every element, since plugins often set the same color on a cell as on its row.
		if ( parentColor !== null ) {
			var fg = fgClass( parse( cs.color ), el, parseFloat( cs.fontSize ), parseInt( cs.fontWeight, 10 ) || 400, borderPurple );
			if ( fg ) {
				classes.push( fg );
			}
		}

		if ( bg && cs.boxShadow && cs.boxShadow !== 'none' && cs.boxShadow.indexOf( 'inset' ) === -1 ) {
			classes.push( 'qa-noshadow' );
		}
		return { color: cs.color, classes: classes };
	}

	function mapTree( start ) {
		if ( ! start || skip( start ) ) {
			return;
		}
		// No transitions while mapping: a color read mid-transition would be the old (mapped) one.
		root.classList.add( 'qa-mapping' );
		var list = [ start ];
		var all = start.getElementsByTagName( '*' );
		for ( var k = 0; k < all.length; k++ ) {
			list.push( all[ k ] );
		}
		// Strip old classes so the reads see the plugin's own colors.
		for ( k = 0; k < list.length; k++ ) {
			strip( list[ k ] );
		}
		var colors = new Map();
		var plans = [];
		for ( k = 0; k < list.length; k++ ) {
			var el = list[ k ];
			if ( skip( el ) ) {
				continue;
			}
			var parent = el.parentElement;
			var parentColor = parent ? ( colors.has( parent ) ? colors.get( parent ) : window.getComputedStyle( parent ).color ) : '';
			var p = plan( el, parentColor );
			colors.set( el, p.color );
			if ( p.classes.length ) {
				plans.push( [ el, p.classes ] );
			}
		}
		for ( k = 0; k < plans.length; k++ ) {
			var node = plans[ k ][ 0 ];
			for ( var j = 0; j < plans[ k ][ 1 ].length; j++ ) {
				node.classList.add.apply( node.classList, plans[ k ][ 1 ][ j ].split( ' ' ) );
			}
		}
		guard( list );
		void window.getComputedStyle( start ).backgroundColor; // Settle styles before transitions come back.
		root.classList.remove( 'qa-mapping' );
	}

	/* ------------------------------------------------------------------ */
	/* Contrast guard                                                     */
	/* ------------------------------------------------------------------ */

	// After mapping, text that still cannot be read on what is really behind it (a color the mapping
	// does not know, a background it keeps, a hover tint) gets the token that reads best there: the
	// main text color, the pill text or the text on the accent (QA, 18 November 2026).
	var GUARD_MIN = 3;
	var GUARD = [ [ 'qa-guard-1', '--qa-text-1' ], [ 'qa-guard-pill', '--qa-on-pill' ], [ 'qa-guard-on-accent', '--qa-on-accent' ] ];

	function luminance( c ) {
		function ch( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * ch( c.r ) + 0.7152 * ch( c.g ) + 0.0722 * ch( c.b );
	}

	function contrast( a, b ) {
		var x = luminance( a );
		var y = luminance( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	}

	function over( top, bottom ) {
		var a = top.a + bottom.a * ( 1 - top.a );
		if ( ! a ) {
			return { r: 0, g: 0, b: 0, a: 0 };
		}
		return {
			r: ( top.r * top.a + bottom.r * bottom.a * ( 1 - top.a ) ) / a,
			g: ( top.g * top.a + bottom.g * bottom.a * ( 1 - top.a ) ) / a,
			b: ( top.b * top.a + bottom.b * bottom.a * ( 1 - top.a ) ) / a,
			a: a,
		};
	}

	// What the text sits on: the element's and its ancestors' backgrounds, composited. Null when a
	// picture is behind it (the real color is unknown).
	function backdrop( el, cache ) {
		var layers = [];
		var e = el;
		var base = null;
		while ( e && e.nodeType === 1 ) {
			if ( cache.has( e ) ) {
				base = cache.get( e );
				break;
			}
			var cs = window.getComputedStyle( e );
			if ( cs.backgroundImage && cs.backgroundImage !== 'none' ) {
				if ( cs.backgroundImage.indexOf( 'url(' ) > -1 ) {
					return null;
				}
				var stops = cs.backgroundImage.match( COLOR_FN_RE ) || [];
				if ( stops.length ) {
					var first = parse( stops[ 0 ] );
					if ( first ) {
						layers.push( { el: e, c: first } );
						if ( first.a >= 0.99 ) {
							base = { r: 0, g: 0, b: 0, a: 0 };
							break;
						}
					}
				}
			}
			var c = parse( cs.backgroundColor );
			if ( c && c.a > 0 ) {
				layers.push( { el: e, c: c } );
				if ( c.a >= 0.99 ) {
					base = { r: 0, g: 0, b: 0, a: 0 };
					break;
				}
			}
			e = e.parentElement;
		}
		if ( base === null ) {
			base = parse( window.getComputedStyle( document.body ).backgroundColor ) || { r: 255, g: 255, b: 255, a: 1 };
			if ( base.a < 0.99 ) {
				base = over( base, { r: 255, g: 255, b: 255, a: 1 } );
			}
		}
		if ( ! base ) {
			return null;
		}
		var result = base;
		for ( var k = layers.length - 1; k >= 0; k-- ) {
			result = result.a ? over( layers[ k ].c, result ) : layers[ k ].c;
		}
		cache.set( el, result );
		return result;
	}

	function ownText( el ) {
		if ( el.tagName === 'INPUT' ) {
			return /^(submit|button|reset)$/.test( el.type ) ? el.value : '';
		}
		var text = '';
		for ( var n = el.firstChild; n; n = n.nextSibling ) {
			if ( n.nodeType === 3 ) {
				text += n.nodeValue;
			}
		}
		return text.trim();
	}

	function guard( list ) {
		// The token colors as rgb(): a detached element normalizes any CSS color it is given.
		var tokens = window.getComputedStyle( root );
		var probe = document.createElement( 'span' );
		var rgb = [];
		for ( var t = 0; t < GUARD.length; t++ ) {
			probe.style.color = '';
			probe.style.color = tokens.getPropertyValue( GUARD[ t ][ 1 ] ).trim();
			rgb.push( parse( probe.style.color ) );
		}

		var cache = new Map();
		var fixes = [];
		for ( var k = 0; k < list.length; k++ ) {
			var el = list[ k ];
			if ( skip( el ) || ! el.isConnected || ownText( el ).length < 2 ) {
				continue;
			}
			var cs = window.getComputedStyle( el );
			// Not visibility: the first mapping runs while the content is still hidden (qa-preload).
			if ( cs.display === 'none' || parseFloat( cs.opacity ) < 0.2 || ! el.getClientRects().length ) {
				continue;
			}
			var fg = parse( cs.color );
			var bg = backdrop( el, cache );
			if ( ! fg || ! bg ) {
				continue;
			}
			if ( contrast( over( fg, bg ), bg ) >= GUARD_MIN ) {
				continue;
			}
			var best = -1;
			var bestRatio = 0;
			for ( t = 0; t < rgb.length; t++ ) {
				if ( rgb[ t ] ) {
					var r = contrast( rgb[ t ], bg );
					if ( r > bestRatio ) {
						bestRatio = r;
						best = t;
					}
				}
			}
			if ( best > -1 ) {
				fixes.push( [ el, GUARD[ best ][ 0 ] ] );
			}
		}
		for ( k = 0; k < fixes.length; k++ ) {
			fixes[ k ][ 0 ].classList.add( fixes[ k ][ 1 ] );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Hover and focus                                                    */
	/* ------------------------------------------------------------------ */

	// Plugins recolor controls on hover and focus (a purple or pale tint behind the label). The mapping
	// reads each element at rest, so those states kept the plugin's colors next to mapped text and the
	// label could not be read (QA, 18 November 2026). While a control is hovered or focused, its part
	// of the page is mapped again with the state's colors, and once more when the state ends.
	var CONTROL = 'a, button, label, summary, li, tr, [role="tab"], [role="button"], [role="menuitem"], [role="option"], .nav-tab, input[type="submit"], input[type="button"]';
	var CONTROL_MAX = 250; // Descendants: a whole list or table is not remapped on every hover.
	var active = [];

	function controls( target ) {
		var found = [];
		var e = target && target.nodeType === 1 ? target : ( target && target.parentElement );
		var content = document.getElementById( 'wpbody-content' );
		for ( var depth = 0; e && e !== document.body && found.length < 3 && depth < 8; depth++, e = e.parentElement ) {
			if ( e === content || e.id === 'wpwrap' ) {
				break;
			}
			if ( ( e.matches( CONTROL ) || window.getComputedStyle( e ).cursor === 'pointer' ) && e.getElementsByTagName( '*' ).length <= CONTROL_MAX && ! skip( e ) ) {
				found.push( e );
			}
		}
		return found;
	}

	function settle( next ) {
		var k;
		var changed = [];
		for ( k = 0; k < active.length; k++ ) {
			if ( next.indexOf( active[ k ] ) === -1 && active[ k ].isConnected ) {
				changed.push( active[ k ] ); // State ended: map at rest.
			}
		}
		for ( k = 0; k < next.length; k++ ) {
			if ( active.indexOf( next[ k ] ) === -1 ) {
				changed.push( next[ k ] ); // State began: map with its colors.
			}
		}
		active = next;
		if ( ! changed.length ) {
			return;
		}
		if ( observer ) {
			observer.disconnect();
		}
		// Outer controls first, so an inner one is read with its outer one already mapped.
		changed.sort( function ( a, b ) {
			return a.contains( b ) ? -1 : ( b.contains( a ) ? 1 : 0 );
		} );
		for ( k = 0; k < changed.length; k++ ) {
			mapTree( changed[ k ] );
		}
		observe();
	}

	function stateTargets() {
		var list = [];
		var hovered = document.querySelectorAll( ':hover' );
		var deepest = hovered.length ? hovered[ hovered.length - 1 ] : null;
		var focused = document.activeElement && document.activeElement !== document.body ? document.activeElement : null;
		[ deepest, focused ].forEach( function ( node ) {
			controls( node ).forEach( function ( c ) {
				if ( list.indexOf( c ) === -1 ) {
					list.push( c );
				}
			} );
		} );
		return list;
	}

	var stateQueued = false;

	function onState() {
		if ( ! mapped || stateQueued ) {
			return;
		}
		stateQueued = true;
		window.requestAnimationFrame( function () {
			stateQueued = false;
			if ( mapped ) {
				settle( stateTargets() );
			}
		} );
	}

	document.addEventListener( 'mouseover', onState, true );
	document.addEventListener( 'mouseout', onState, true );
	document.addEventListener( 'focusin', onState, true );
	document.addEventListener( 'focusout', onState, true );

	function unmapAll() {
		var els = document.querySelectorAll( '[class*="qa-"]' );
		for ( var k = 0; k < els.length; k++ ) {
			strip( els[ k ] );
		}
	}

	var pending = [];
	var flushQueued = false;

	function queue( el ) {
		if ( pending.indexOf( el ) === -1 ) {
			pending.push( el );
		}
		if ( ! flushQueued ) {
			flushQueued = true;
			window.requestAnimationFrame( flush );
		}
	}

	function flush() {
		flushQueued = false;
		var items = pending;
		pending = [];
		if ( observer ) {
			observer.disconnect();
		}
		for ( var k = 0; k < items.length; k++ ) {
			if ( items[ k ].isConnected ) {
				mapTree( items[ k ] );
			}
		}
		observe();
	}

	function observe() {
		if ( ! window.MutationObserver || ! document.body ) {
			return;
		}
		if ( ! observer ) {
			observer = new MutationObserver( function ( mutations ) {
				for ( var k = 0; k < mutations.length; k++ ) {
					var m = mutations[ k ];
					if ( m.type === 'attributes' ) {
						var before = ( m.oldValue || '' ).replace( MAP_RE, '' ).replace( /\s+/g, ' ' ).trim();
						var now = m.attributeName === 'class' ? ( m.target.className || '' ).toString().replace( MAP_RE, '' ).replace( /\s+/g, ' ' ).trim() : m.target.getAttribute( m.attributeName );
						if ( before !== now && ! skip( m.target ) ) {
							queue( m.target );
						}
						continue;
					}
					for ( var j = 0; j < m.addedNodes.length; j++ ) {
						var n = m.addedNodes[ j ];
						if ( n.nodeType === 1 && ! skip( n ) ) {
							queue( n );
						}
					}
				}
			} );
		}
		observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeOldValue: true, attributeFilter: [ 'class', 'style' ] } );
	}

	function stopObserving() {
		if ( observer ) {
			observer.disconnect();
		}
		pending = [];
	}

	function mapPage() {
		var parts = [ document.getElementById( 'wpbody-content' ), document.getElementById( 'wpfooter' ) ];
		for ( var k = 0; k < parts.length; k++ ) {
			mapTree( parts[ k ] );
		}
		// Dialogs and dropdowns that plugins append to <body>.
		var kids = document.body.children;
		for ( k = 0; k < kids.length; k++ ) {
			if ( kids[ k ].id !== 'wpwrap' && ! /^(SCRIPT|STYLE|LINK|TEMPLATE)$/.test( kids[ k ].tagName ) ) {
				mapTree( kids[ k ] );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Applying a preference                                              */
	/* ------------------------------------------------------------------ */

	function look( mode ) {
		if ( mode === 'auto' ) {
			return mq && mq.matches ? 'dark' : 'light';
		}
		return mode;
	}

	function applies( s ) {
		return s.mode !== 'classic' && ( s.scope === 'all' || !! config.isPluginPage );
	}

	// The look the user chose (Classic counts as light). The toggle shows this on every page,
	// also where the theme does not apply (another plugin's page with "WPBot dashboard only").
	function chosenLook() {
		return state.mode === 'classic' ? 'light' : look( state.mode );
	}

	var mapped = false;
	var mappedLook = '';

	function apply() {
		var on = applies( state );
		if ( on ) {
			root.setAttribute( 'data-qa-theme', look( state.mode ) );
			root.classList.add( 'qa-theme' );
			// WordPress's frame (toolbar, admin menu) changes only with "Whole admin panel".
			root.classList.toggle( 'qa-scope-all', state.scope === 'all' );
			if ( ! mapped ) {
				mapPage();
				mapped = true;
				mappedLook = look( state.mode );
				observe();
			} else if ( mappedLook !== look( state.mode ) ) {
				// Light and Dark have different token colors: the contrast guard decides again.
				stopObserving();
				mapPage();
				mappedLook = look( state.mode );
				observe();
			}
		} else {
			root.classList.remove( 'qa-theme', 'qa-scope-all' );
			root.removeAttribute( 'data-qa-theme' );
			if ( mapped ) {
				stopObserving();
				unmapAll();
				mapped = false;
			}
		}
		if ( document.body ) {
			document.body.classList.toggle( 'qcld-admin-theme', on );
		}

		var k;
		var btns = document.querySelectorAll( '.qa-switch__btn' );
		for ( k = 0; k < btns.length; k++ ) {
			btns[ k ].setAttribute( 'aria-pressed', btns[ k ].getAttribute( 'data-qa-mode' ) === state.mode ? 'true' : 'false' );
		}
		var node = document.getElementById( 'wp-admin-bar-qcld-admin-theme' );
		if ( node ) {
			var dark = chosenLook() === 'dark';
			var top = node.querySelector( ':scope > .ab-item' );
			node.classList.toggle( 'qa-is-dark', dark );
			if ( top ) {
				top.setAttribute( 'role', 'switch' );
				top.setAttribute( 'aria-checked', dark ? 'true' : 'false' );
				top.setAttribute( 'title', dark ? config.i18n.toLight : config.i18n.toDark );
			}
		}
		if ( state.mode !== 'classic' ) {
			lastLook = state.mode;
		}
		var where = state.mode === 'classic' ? 'classic' : state.scope;
		var items = document.querySelectorAll( '#wp-admin-bar-qcld-admin-theme .qa-ab-mode, #wp-admin-bar-qcld-admin-theme .qa-ab-where' );
		for ( k = 0; k < items.length; k++ ) {
			var id = items[ k ].id || '';
			items[ k ].classList.toggle( 'qa-selected', id === 'wp-admin-bar-qcld-admin-theme-mode-' + state.mode || id === 'wp-admin-bar-qcld-admin-theme-where-' + where );
		}
		syncTrialBar();
		try {
			document.dispatchEvent( new CustomEvent( 'qcld_admin_theme_change', { detail: { mode: state.mode, scope: state.scope, all: state.all, look: on ? look( state.mode ) : 'classic' } } ) );
		} catch ( e ) {}
	}

	/* ------------------------------------------------------------------ */
	/* Server                                                             */
	/* ------------------------------------------------------------------ */

	function request( fields ) {
		var body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );
		return window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var err = new Error( 'save failed' );
					err.code = json && json.data && json.data.code;
					throw err;
				}
				return json.data;
			} );
	}

	function adopt( data ) {
		state = { mode: data.mode, scope: data.scope, all: data.all || '' };
		apply();
		try {
			window.localStorage.setItem( SYNC_KEY, JSON.stringify( { mode: state.mode, scope: state.scope, all: state.all, t: Date.now() } ) );
		} catch ( e ) {}
	}

	function fail( previous ) {
		state = previous;
		apply();
		toast( config.i18n.saveError );
	}

	/**
	 * Save a look and scope. Turning on "Entire dashboard" asks for confirmation first (step 1 of
	 * the opt-in), then offers Undo; the server refuses it without that confirmation.
	 */
	function save( next ) {
		var turningOnAll = next.scope === 'all' && state.scope !== 'all';
		if ( turningOnAll ) {
			confirmAll( function ( ok ) {
				if ( ok ) {
					commit( next, true );
				}
			} );
			return;
		}
		commit( next, false );
	}

	function commit( next, confirmedAll ) {
		var previous = state;
		hideToast(); // A note about the previous change no longer applies.
		state = { mode: next.mode, scope: next.scope, all: confirmedAll ? 'trial' : ( next.scope === 'all' ? state.all : '' ) };
		apply();
		var fields = { op: 'save', mode: next.mode, scope: next.scope };
		if ( confirmedAll ) {
			fields.confirm_all = '1';
		}
		request( fields )
			.then( function ( data ) {
				adopt( data );
				if ( confirmedAll ) {
					toast( config.i18n.appliedAll, config.i18n.undo, revert );
				}
			} )
			[ 'catch' ]( function () {
				fail( previous );
			} );
	}

	// Step 2 of the opt-in.
	function keep() {
		var previous = state;
		state = { mode: state.mode, scope: state.scope, all: 'kept' };
		apply();
		request( { op: 'keep' } )
			.then( function ( data ) {
				adopt( data );
				toast( config.i18n.kept );
			} )
			[ 'catch' ]( function () {
				fail( previous );
			} );
	}

	// Switch back / Undo: the server restores the preference from before "Entire dashboard".
	function revert() {
		var previous = state;
		request( { op: 'revert' } )
			.then( function ( data ) {
				adopt( data );
				toast( config.i18n.reverted );
			} )
			[ 'catch' ]( function () {
				fail( previous );
			} );
	}

	// Toolbar toggle: Light <-> Dark only. It never changes where the theme applies: that is
	// chosen in the menu. On a page the theme does not cover, a note says so and offers
	// "Use everywhere" (which asks first, like the menu's Whole admin panel).
	function toggle() {
		var next = { mode: chosenLook() === 'dark' ? 'light' : 'dark', scope: state.scope };
		save( next );
		if ( ! applies( next ) ) {
			toast( config.i18n.notHere, config.i18n.useAll, function () {
				save( { mode: next.mode, scope: 'all' } );
			} );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Confirmation dialog, toast, trial bar                              */
	/* ------------------------------------------------------------------ */

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	var dialog = null;

	function confirmAll( done ) {
		var i18n = config.i18n;
		if ( ! window.HTMLDialogElement ) {
			// No <dialog> support: a plain confirmation.
			done( window.confirm( i18n.confirmTitle + '\n\n' + i18n.confirmBody ) );
			return;
		}
		if ( ! dialog ) {
			dialog = el( 'dialog', { class: 'qa-dialog', 'aria-labelledby': 'qa-dialog-title', 'aria-describedby': 'qa-dialog-body', 'data-qa-skip': '' } );
			var form = el( 'form', { method: 'dialog', class: 'qa-dialog__form' } );
			form.appendChild( el( 'h2', { id: 'qa-dialog-title', class: 'qa-dialog__title' }, i18n.confirmTitle ) );
			var bodyBox = el( 'div', { id: 'qa-dialog-body', class: 'qa-dialog__body' } );
			bodyBox.appendChild( el( 'p', {}, i18n.confirmBody ) );
			bodyBox.appendChild( el( 'p', {}, i18n.confirmTrial ) );
			form.appendChild( bodyBox );
			var actions = el( 'div', { class: 'qa-dialog__actions' } );
			actions.appendChild( el( 'button', { type: 'submit', value: 'cancel', class: 'button qa-dialog__cancel' }, i18n.cancel ) );
			actions.appendChild( el( 'button', { type: 'submit', value: 'confirm', class: 'button button-primary qa-dialog__confirm' }, i18n.confirmYes ) );
			form.appendChild( actions );
			dialog.appendChild( form );
			document.body.appendChild( dialog );
		}
		var onClose = function () {
			dialog.removeEventListener( 'close', onClose );
			done( dialog.returnValue === 'confirm' );
		};
		dialog.returnValue = '';
		dialog.addEventListener( 'close', onClose );
		dialog.showModal();
		// Safer default: focus Cancel, so Enter does not confirm by accident.
		dialog.querySelector( '.qa-dialog__cancel' ).focus();
	}

	var toastTimer = null;

	function toast( message, actionLabel, action ) {
		var box = document.getElementById( 'qa-toast' );
		if ( ! box ) {
			box = el( 'div', { id: 'qa-toast', class: 'qa-toast', role: 'status', 'aria-live': 'polite', 'data-qa-skip': '' } );
			document.body.appendChild( box );
			box.addEventListener( 'mouseenter', function () {
				window.clearTimeout( toastTimer );
			} );
			box.addEventListener( 'focusin', function () {
				window.clearTimeout( toastTimer );
			} );
		}
		box.textContent = '';
		box.appendChild( el( 'span', { class: 'qa-toast__text' }, message ) );
		if ( actionLabel && action ) {
			var act = el( 'button', { type: 'button', class: 'qa-toast__action' }, actionLabel );
			act.addEventListener( 'click', function () {
				hideToast();
				action();
			} );
			box.appendChild( act );
		}
		var close = el( 'button', { type: 'button', class: 'qa-toast__close', 'aria-label': config.i18n.dismiss }, '×' );
		close.addEventListener( 'click', hideToast );
		box.appendChild( close );
		box.classList.add( 'is-visible' );
		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( hideToast, actionLabel ? 20000 : 6000 );
	}

	function hideToast() {
		var box = document.getElementById( 'qa-toast' );
		if ( box ) {
			box.classList.remove( 'is-visible' );
		}
		window.clearTimeout( toastTimer );
	}

	// The trial bar (step 2) follows the state: shown while "Entire dashboard" is a trial.
	function syncTrialBar() {
		var bar = document.querySelector( '.qa-trial' );
		var want = state.scope === 'all' && state.all === 'trial' && state.mode !== 'classic';
		if ( ! want ) {
			if ( bar ) {
				bar.parentNode.removeChild( bar );
			}
			return;
		}
		if ( bar ) {
			return;
		}
		var host = document.querySelector( '#wpbody-content .wrap' ) || document.getElementById( 'wpbody-content' );
		if ( ! host ) {
			return;
		}
		var i18n = config.i18n;
		bar = el( 'div', { class: 'notice qa-trial inline', 'data-qa-skip': '', role: 'region', 'aria-label': i18n.scopeAll } );
		bar.appendChild( el( 'p', { class: 'qa-trial__text' }, i18n.trialText ) );
		var actions = el( 'p', { class: 'qa-trial__actions' } );
		actions.appendChild( el( 'a', { class: 'button button-primary qa-trial__keep', 'data-qa-op': 'keep', href: '#' }, i18n.keep ) );
		actions.appendChild( el( 'a', { class: 'button qa-trial__back', 'data-qa-op': 'revert', href: '#' }, i18n.switchBack ) );
		bar.appendChild( actions );
		host.insertBefore( bar, host.firstChild );
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                             */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'click', function ( e ) {
		var t = e.target && e.target.closest ? e.target : null;
		if ( ! t ) {
			return;
		}
		var btn = t.closest( '.qa-switch__btn' );
		if ( btn ) {
			e.preventDefault();
			save( { mode: btn.getAttribute( 'data-qa-mode' ), scope: state.scope } );
			return;
		}
		var op = t.closest( '.qa-trial [data-qa-op]' );
		if ( op ) {
			e.preventDefault();
			if ( op.getAttribute( 'data-qa-op' ) === 'keep' ) {
				keep();
			} else {
				revert();
			}
			return;
		}
		var item = t.closest( '#wp-admin-bar-qcld-admin-theme .qa-ab-mode, #wp-admin-bar-qcld-admin-theme .qa-ab-where' );
		if ( item ) {
			e.preventDefault();
			var id = item.id || '';
			var look = state.mode === 'classic' ? lastLook : state.mode;
			if ( id.indexOf( '-mode-' ) > -1 ) {
				save( { mode: id.split( '-mode-' )[ 1 ], scope: state.scope } );
			} else if ( id.slice( -8 ) === '-classic' ) {
				save( { mode: 'classic', scope: state.scope } );
			} else {
				// WPBot dashboard only / Whole admin panel (the latter asks first).
				save( { mode: look, scope: id.split( '-where-' )[ 1 ] } );
			}
			return;
		}
		if ( t.closest( '#wp-admin-bar-qcld-admin-theme > .ab-item' ) ) {
			e.preventDefault();
			toggle();
		}
	} );

	// Profile: picking "Entire dashboard" asks for the same confirmation, then ticks the box.
	document.addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( ! t || ! t.matches || ! t.matches( 'input[data-qa-scope-all]' ) || ! t.checked ) {
			return;
		}
		var box = document.getElementById( 'qcld_admin_theme_confirm_all' );
		if ( ! box || box.checked ) {
			return;
		}
		confirmAll( function ( ok ) {
			if ( ok ) {
				box.checked = true;
			} else {
				var plugin = document.querySelector( 'input[name="qcld_admin_theme_scope"][value="plugin"]' );
				if ( plugin ) {
					plugin.checked = true;
				}
			}
		} );
	} );

	if ( mq ) {
		var onChange = function () {
			if ( state.mode === 'auto' && root.classList.contains( 'qa-theme' ) ) {
				root.setAttribute( 'data-qa-theme', look( 'auto' ) );
			}
		};
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', onChange );
		} else if ( mq.addListener ) {
			mq.addListener( onChange );
		}
	}

	// Other open admin tabs follow.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key !== SYNC_KEY || ! e.newValue ) {
			return;
		}
		try {
			var d = JSON.parse( e.newValue );
			state = { mode: d.mode, scope: d.scope, all: d.all || '' };
			apply();
		} catch ( err ) {}
	} );

	/* Public API for the host plugins' own scripts. */
	window.qcldAdminTheme = {
		get: function () {
			return { mode: state.mode, scope: state.scope, all: state.all, look: applies( state ) ? look( state.mode ) : 'classic' };
		},
		set: function ( mode, scope ) {
			save( { mode: mode || state.mode, scope: scope || state.scope } );
		},
		keep: keep,
		revert: revert,
		refresh: function ( node ) {
			if ( mapped ) {
				queue( node || document.getElementById( 'wpbody-content' ) );
			}
		}
	};

	function start() {
		apply();
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				root.classList.remove( 'qa-preload' );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
