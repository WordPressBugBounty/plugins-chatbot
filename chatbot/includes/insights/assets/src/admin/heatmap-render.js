/**
 * Draws click and scroll heatmaps on a canvas placed inside the page shown
 * in the heatmap frame (live page or recorded snapshot, both same-origin),
 * so the heat scrolls with the page.
 *
 * Click heat: each element's 10 x 10 bins are mapped onto the element's
 * current position; intensity is relative to the busiest bin and coloured
 * with one fixed light-yellow-to-dark-red gradient on every site.
 * Scroll map: parts of the page fewer visitors reached get darker.
 */

const CANVAS_ID = 'qcin-hm-canvas';
const MAX_HEIGHT = 16000;

let palette = null;

function heatPalette() {
	if ( palette ) {
		return palette;
	}
	const c = document.createElement( 'canvas' );
	c.width = 256;
	c.height = 1;
	const ctx = c.getContext( '2d' );
	const g = ctx.createLinearGradient( 0, 0, 256, 0 );
	g.addColorStop( 0, '#fff3b0' );
	g.addColorStop( 0.35, '#ffc44d' );
	g.addColorStop( 0.6, '#ff7a1a' );
	g.addColorStop( 0.82, '#e3261f' );
	g.addColorStop( 1, '#8c0a12' );
	ctx.fillStyle = g;
	ctx.fillRect( 0, 0, 256, 1 );
	palette = ctx.getImageData( 0, 0, 256, 1 ).data;
	return palette;
}

function pageSize( doc ) {
	const de = doc.documentElement;
	const b = doc.body || de;
	return { w: Math.max( de.scrollWidth, b.scrollWidth ), h: Math.max( de.scrollHeight, b.scrollHeight ) };
}

/**
 * Stop links and forms in the frame from navigating, and add the
 * highlight style.
 *
 * @param {Document} doc Frame document.
 */
export function prepare( doc ) {
	if ( ! doc || doc.__qcinReady ) {
		return;
	}
	doc.__qcinReady = true;
	const block = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
	};
	[ 'click', 'auxclick', 'submit' ].forEach( ( t ) => doc.addEventListener( t, block, true ) );
	const style = doc.createElement( 'style' );
	style.textContent = '.qcin-hm-hl{outline:3px solid #5b4e96 !important;outline-offset:3px !important;}';
	( doc.head || doc.documentElement ).appendChild( style );
}

function canvasFor( doc ) {
	let c = doc.getElementById( CANVAS_ID );
	if ( ! c ) {
		c = doc.createElement( 'canvas' );
		c.id = CANVAS_ID;
		c.setAttribute( 'aria-hidden', 'true' );
		c.style.cssText = 'position:absolute;left:0;top:0;pointer-events:none;z-index:2147483646;margin:0;padding:0;border:0;';
		doc.documentElement.appendChild( c );
	}
	// Collapse before measuring so the canvas never stretches the page.
	c.width = 0;
	c.height = 0;
	c.style.width = '0px';
	c.style.height = '0px';
	const size = pageSize( doc );
	c.width = size.w;
	c.height = Math.min( size.h, MAX_HEIGHT );
	c.style.width = size.w + 'px';
	c.style.height = c.height + 'px';
	return { canvas: c, pageHeight: size.h };
}

export function clear( doc ) {
	if ( ! doc ) {
		return;
	}
	const c = doc.getElementById( CANVAS_ID );
	if ( c ) {
		c.remove();
	}
	Array.prototype.slice.call( doc.querySelectorAll( '.qcin-hm-ext' ) ).forEach( ( n ) => n.remove() );
	highlight( doc, '' );
}

function find( doc, selector ) {
	try {
		return doc.querySelector( selector );
	} catch ( e ) {
		return null;
	}
}

/**
 * Draw the click heatmap.
 *
 * @param {Document} doc      Frame document.
 * @param {Array}    elements Elements with selector, clicks and bins [[x, y, n]].
 * @param {number}   radius   Blob radius in px.
 * @return {{found: string[], missingClicks: number}} Which selectors were found.
 */
export function drawClicks( doc, elements, radius ) {
	const { canvas } = canvasFor( doc );
	const ctx = canvas.getContext( '2d' );
	const win = doc.defaultView;
	const found = [];
	let missingClicks = 0;
	const points = [];
	let max = 0;

	elements.forEach( ( el ) => {
		const node = find( doc, el.selector );
		const r = node ? node.getBoundingClientRect() : null;
		if ( ! r || ( ! r.width && ! r.height ) ) {
			missingClicks += el.clicks;
			return;
		}
		found.push( el.selector );
		el.bins.forEach( ( [ bx, by, n ] ) => {
			points.push( {
				x: r.left + win.scrollX + ( ( bx + 0.5 ) / 10 ) * r.width,
				y: r.top + win.scrollY + ( ( by + 0.5 ) / 10 ) * r.height,
				n,
			} );
			max = Math.max( max, n );
		} );
	} );

	ctx.clearRect( 0, 0, canvas.width, canvas.height );
	ctx.fillStyle = 'rgba(24, 20, 40, 0.38)';
	ctx.fillRect( 0, 0, canvas.width, canvas.height );
	if ( ! points.length ) {
		return { found, missingClicks };
	}

	// Draw intensity as alpha on a canvas covering only the points, then colour it.
	let x0 = Infinity;
	let y0 = Infinity;
	let x1 = -Infinity;
	let y1 = -Infinity;
	points.forEach( ( p ) => {
		x0 = Math.min( x0, p.x );
		y0 = Math.min( y0, p.y );
		x1 = Math.max( x1, p.x );
		y1 = Math.max( y1, p.y );
	} );
	x0 = Math.max( 0, Math.floor( x0 - radius ) );
	y0 = Math.max( 0, Math.floor( y0 - radius ) );
	x1 = Math.min( canvas.width, Math.ceil( x1 + radius ) );
	y1 = Math.min( canvas.height, Math.ceil( y1 + radius ) );
	const w = x1 - x0;
	const h = y1 - y0;
	if ( w <= 0 || h <= 0 ) {
		return { found, missingClicks };
	}
	const tmp = document.createElement( 'canvas' );
	tmp.width = w;
	tmp.height = h;
	const t = tmp.getContext( '2d' );
	points.forEach( ( p ) => {
		const a = Math.max( 0.15, p.n / max );
		const px = p.x - x0;
		const py = p.y - y0;
		const g = t.createRadialGradient( px, py, 0, px, py, radius );
		g.addColorStop( 0, 'rgba(0,0,0,' + a + ')' );
		g.addColorStop( 1, 'rgba(0,0,0,0)' );
		t.fillStyle = g;
		t.fillRect( px - radius, py - radius, radius * 2, radius * 2 );
	} );
	const img = t.getImageData( 0, 0, w, h );
	const d = img.data;
	const pal = heatPalette();
	for ( let i = 0; i < d.length; i += 4 ) {
		const a = d[ i + 3 ];
		if ( a > 3 ) {
			const o = a * 4;
			d[ i ] = pal[ o ];
			d[ i + 1 ] = pal[ o + 1 ];
			d[ i + 2 ] = pal[ o + 2 ];
			d[ i + 3 ] = Math.min( 235, a * 1.4 + 30 );
		} else {
			d[ i + 3 ] = 0;
		}
	}
	t.putImageData( img, 0, 0 );
	ctx.drawImage( tmp, x0, y0 );
	return { found, missingClicks };
}

/**
 * Share of views that reached at least each 5% depth.
 *
 * @param {Object} scroll Buckets (0-100 by 5) and total views.
 * @return {Object} depth => share 0-1.
 */
export function reach( scroll ) {
	const out = {};
	const total = scroll.views || 0;
	let acc = 0;
	for ( let d = 100; d >= 0; d -= 5 ) {
		acc += scroll.buckets[ d ] || 0;
		out[ d ] = total ? acc / total : 0;
	}
	return out;
}

function label( ctx, text, x, y, k ) {
	const size = Math.round( 13 * k );
	ctx.font = '600 ' + size + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	const w = ctx.measureText( text ).width + 16 * k;
	const h = 24 * k;
	ctx.fillStyle = 'rgba(45, 42, 58, 0.88)';
	ctx.beginPath();
	if ( ctx.roundRect ) {
		ctx.roundRect( x, y - h / 2, w, h, h / 2 );
	} else {
		ctx.rect( x, y - h / 2, w, h );
	}
	ctx.fill();
	ctx.fillStyle = '#ffffff';
	ctx.fillText( text, x + 8 * k, y + size * 0.36 );
}

/**
 * Draw the scroll map.
 *
 * @param {Document} doc     Frame document.
 * @param {Object}   scroll  Scroll data (buckets, views, fold).
 * @param {number}   scale   Preview scale, so labels stay readable.
 * @param {Object}   strings Translated strings: reached(pct), fold.
 */
export function drawScroll( doc, scroll, scale, strings ) {
	const k = Math.min( 2.5, 1 / Math.max( 0.3, scale || 1 ) );
	const { canvas, pageHeight } = canvasFor( doc );
	const ctx = canvas.getContext( '2d' );
	const H = pageHeight;
	ctx.clearRect( 0, 0, canvas.width, canvas.height );
	const r = reach( scroll );
	for ( let d = 0; d < 100; d += 5 ) {
		const seen = r[ Math.min( 100, d + 5 ) ];
		ctx.fillStyle = 'rgba(24, 20, 40,' + ( 0.72 * ( 1 - seen ) ).toFixed( 3 ) + ')';
		ctx.fillRect( 0, ( d / 100 ) * H, canvas.width, ( 5 / 100 ) * H + 1 );
	}
	for ( let d = 10; d < 100; d += 10 ) {
		const y = Math.round( ( d / 100 ) * H );
		if ( y > canvas.height ) {
			break;
		}
		ctx.strokeStyle = 'rgba(45, 42, 58, 0.45)';
		ctx.setLineDash( [ 4 * k, 6 * k ] );
		ctx.lineWidth = k;
		ctx.beginPath();
		ctx.moveTo( 0, y + 0.5 );
		ctx.lineTo( canvas.width, y + 0.5 );
		ctx.stroke();
		ctx.setLineDash( [] );
		label( ctx, strings.reached( Math.round( r[ d ] * 100 ) ), 12 * k, y, k );
	}
	if ( scroll.fold > 0 && scroll.fold < 100 ) {
		const y = Math.round( ( scroll.fold / 100 ) * H );
		ctx.strokeStyle = '#5b4e96';
		ctx.lineWidth = 2 * k;
		ctx.beginPath();
		ctx.moveTo( 0, y );
		ctx.lineTo( canvas.width, y );
		ctx.stroke();
		label( ctx, strings.fold, Math.max( 12 * k, canvas.width - 150 * k ), y, k );
	}
}

/**
 * Outline one element and scroll it into view; '' removes the outline.
 *
 * @param {Document} doc      Frame document.
 * @param {string}   selector Selector.
 */
export function highlight( doc, selector ) {
	if ( ! doc ) {
		return;
	}
	doc.querySelectorAll( '.qcin-hm-hl' ).forEach( ( n ) => n.classList.remove( 'qcin-hm-hl' ) );
	if ( ! selector ) {
		return;
	}
	const node = find( doc, selector );
	if ( node ) {
		node.classList.add( 'qcin-hm-hl' );
		const r = node.getBoundingClientRect();
		const win = doc.defaultView;
		if ( r.top < 0 || r.bottom > win.innerHeight ) {
			win.scrollTo( { top: Math.max( 0, r.top + win.scrollY - win.innerHeight / 3 ), behavior: 'smooth' } );
		}
	}
}
