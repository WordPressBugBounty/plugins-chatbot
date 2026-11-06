/**
 * Basic graphic templates drawn in the browser in the site's brand colours
 * (title card, quote card, list card), saved to the Media Library.
 */
import apiFetch from '@wordpress/api-fetch';

export const SIZE = { width: 1200, height: 630 };

/**
 * Wrap text into lines that fit a width.
 *
 * @param {CanvasRenderingContext2D} ctx   Context.
 * @param {string}                   text  Text.
 * @param {number}                   width Max width.
 * @return {string[]} Lines.
 */
const wrap = ( ctx, text, width ) => {
	const lines = [];
	let line = '';
	String( text )
		.split( /\s+/ )
		.forEach( ( word ) => {
			const next = line ? `${ line } ${ word }` : word;
			if ( ctx.measureText( next ).width > width && line ) {
				lines.push( line );
				line = word;
			} else {
				line = next;
			}
		} );
	if ( line ) {
		lines.push( line );
	}
	return lines;
};

/**
 * Draw a card on a canvas.
 *
 * @param {HTMLCanvasElement} canvas Canvas.
 * @param {string}            kind   title, quote or list.
 * @param {Object}            input  text, items[], footer.
 * @param {Object}            brand  primary, soft, text colours.
 * @return {void}
 */
export const draw = ( canvas, kind, input, brand ) => {
	const ctx = canvas.getContext( '2d' );
	const { width, height } = SIZE;
	canvas.width = width;
	canvas.height = height;
	const dark = 'title' === kind;
	ctx.fillStyle = dark ? brand.primary : brand.soft;
	ctx.fillRect( 0, 0, width, height );
	ctx.fillStyle = dark ? '#ffffff' : brand.primary;
	ctx.fillRect( 80, 80, 12, height - 160 );

	ctx.fillStyle = dark ? '#ffffff' : brand.text;
	ctx.textBaseline = 'top';
	const family =
		'-apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
	let y = 110;
	if ( 'list' === kind ) {
		ctx.font = `bold 52px ${ family }`;
		wrap( ctx, input.text, 980 )
			.slice( 0, 2 )
			.forEach( ( line ) => {
				ctx.fillText( line, 130, y );
				y += 64;
			} );
		y += 20;
		ctx.font = `36px ${ family }`;
		( input.items || [] ).slice( 0, 5 ).forEach( ( item ) => {
			ctx.fillText( `•  ${ wrap( ctx, item, 900 )[ 0 ] || '' }`, 140, y );
			y += 54;
		} );
	} else {
		const quote = 'quote' === kind;
		ctx.font = `${ quote ? 'italic ' : 'bold ' }${ quote ? 50 : 64 }px ${ family }`;
		const lines = wrap(
			ctx,
			quote ? `“${ input.text }”` : input.text,
			980
		).slice( 0, 6 );
		const lineHeight = quote ? 66 : 80;
		y = Math.max( 110, ( height - lines.length * lineHeight ) / 2 - 20 );
		lines.forEach( ( line ) => {
			ctx.fillText( line, 130, y );
			y += lineHeight;
		} );
	}
	if ( input.footer ) {
		ctx.font = `28px ${ family }`;
		ctx.fillStyle = dark ? 'rgba(255,255,255,.85)' : brand.primary;
		ctx.fillText( input.footer, 130, height - 120 );
	}
};

/**
 * Upload a canvas as a PNG to the Media Library.
 *
 * @param {HTMLCanvasElement} canvas Canvas.
 * @param {string}            name   File name base.
 * @param {string}            alt    Alt text.
 * @return {Promise<Object>} Attachment from the REST API.
 */
export const upload = ( canvas, name, alt ) =>
	new Promise( ( resolve, reject ) =>
		canvas.toBlob(
			( blob ) =>
				blob ? resolve( blob ) : reject( new Error( 'canvas' ) ),
			'image/png'
		)
	).then( ( blob ) => {
		const form = new window.FormData();
		const slug = String( name || 'graphic' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-|-$/g, '' )
			.slice( 0, 40 );
		form.append( 'file', blob, `${ slug || 'graphic' }.png` );
		form.append( 'alt_text', alt || '' );
		return apiFetch( { path: '/wp/v2/media', method: 'POST', body: form } );
	} );
