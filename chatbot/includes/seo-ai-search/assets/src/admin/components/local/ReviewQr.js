/**
 * QR code for Google's review link: shown in the Reviews card and
 * downloadable as a PNG to print on receipts, the counter or flyers.
 * Generated in the browser with the qrcode-generator library (MIT, by
 * Kazuhiko Arase); nothing is sent anywhere.
 */
import { Button } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import qrcode from 'qrcode-generator';

const MARGIN = 4; // Quiet zone in modules, as the QR standard asks.

/**
 * QR modules for a text (error correction M: still reads when slightly damaged).
 *
 * @param {string} text Text.
 * @return {Array|null} Rows of booleans, or null.
 */
const qrModules = ( text ) => {
	try {
		const qr = qrcode( 0, 'M' );
		qr.addData( text, 'Byte' );
		qr.make();
		const count = qr.getModuleCount();
		const rows = [];
		for ( let r = 0; r < count; r++ ) {
			const row = [];
			for ( let c = 0; c < count; c++ ) {
				row.push( qr.isDark( r, c ) );
			}
			rows.push( row );
		}
		return rows;
	} catch {
		// Too long for a QR code: show nothing.
		return null;
	}
};

/**
 * Save the QR code as a PNG (drawn on a canvas, 12 pixels per module).
 *
 * @param {Array}  rows     Modules.
 * @param {string} filename File name.
 */
const downloadPng = ( rows, filename ) => {
	const scale = 12;
	const size = ( rows.length + MARGIN * 2 ) * scale;
	const canvas = document.createElement( 'canvas' );
	canvas.width = size;
	canvas.height = size;
	const ctx = canvas.getContext( '2d' );
	ctx.fillStyle = '#ffffff';
	ctx.fillRect( 0, 0, size, size );
	ctx.fillStyle = '#000000';
	rows.forEach( ( row, r ) =>
		row.forEach( ( dark, c ) => {
			if ( dark ) {
				ctx.fillRect(
					( c + MARGIN ) * scale,
					( r + MARGIN ) * scale,
					scale,
					scale
				);
			}
		} )
	);
	const link = document.createElement( 'a' );
	link.href = canvas.toDataURL( 'image/png' );
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
};

const ReviewQr = ( { url } ) => {
	const rows = useMemo( () => qrModules( url ), [ url ] );
	if ( ! rows ) {
		return null;
	}
	const size = rows.length + MARGIN * 2;
	const path = [];
	rows.forEach( ( row, r ) =>
		row.forEach( ( dark, c ) => {
			if ( dark ) {
				path.push( `M${ c + MARGIN } ${ r + MARGIN }h1v1h-1z` );
			}
		} )
	);
	return (
		<div className="qcseo-review-qr">
			<svg
				viewBox={ `0 0 ${ size } ${ size }` }
				width="132"
				height="132"
				role="img"
				aria-label={ __(
					'QR code for your Google review link',
					'chatbot'
				) }
				shapeRendering="crispEdges"
			>
				<rect width={ size } height={ size } fill="#ffffff" />
				<path d={ path.join( '' ) } fill="#000000" />
			</svg>
			<div>
				<p className="qcseo-muted">
					{ __(
						'Print it on receipts, at the counter or on flyers: customers scan it and land on your Google review form.',
						'chatbot'
					) }
				</p>
				<Button
					variant="secondary"
					size="compact"
					onClick={ () => downloadPng( rows, 'review-qr-code.png' ) }
				>
					{ __( 'Download QR code (PNG)', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default ReviewQr;
