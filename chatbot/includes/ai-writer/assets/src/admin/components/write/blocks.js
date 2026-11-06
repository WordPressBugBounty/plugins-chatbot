/**
 * Turning edited HTML back into block markup, and safe previews of the
 * owner's own edits (shared by the finished article and Improve Content).
 */
import { getBlockType, rawHandler, serialize } from '@wordpress/blocks';

/**
 * HTML turned into block markup (WordPress's own converter).
 *
 * @param {string} html HTML.
 * @return {string} Block markup.
 */
export const toBlocks = ( html ) => {
	const library = window.wp && window.wp.blockLibrary;
	if ( ! getBlockType( 'core/paragraph' ) && library ) {
		library.registerCoreBlocks();
	}
	// Rendered markup carries classes WordPress adds itself; leave them out.
	const clean = String( html || '' ).replace(
		/\sclass="wp-block-(paragraph|heading)"/g,
		''
	);
	return serialize( rawHandler( { HTML: clean } ) );
};

/**
 * The owner's own text shown back as formatted HTML, without scripts or
 * event attributes (the server cleans it again when it is applied).
 *
 * @param {string} html HTML typed by the owner.
 * @return {string} HTML for a preview.
 */
export const safePreview = ( html ) =>
	String( html || '' )
		.replace( /<(script|style|iframe|object|embed)\b[\s\S]*?<\/\1>/gi, '' )
		.replace( /<(script|style|iframe|object|embed)\b[^>]*>/gi, '' )
		.replace( /\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '' )
		.replace( /(href|src)\s*=\s*(["'])\s*javascript:[^"']*\2/gi, '$1="#"' );

/**
 * Block-level HTML (paragraphs, headings, lists) as editable text:
 * paragraphs separated by blank lines, simple tags kept.
 *
 * @param {string} html HTML.
 * @return {string} Text for a text area.
 */
export const toEditable = ( html ) =>
	String( html || '' )
		.replace( /<!--[\s\S]*?-->/g, '' )
		.replace( /\sclass="[^"]*"/g, '' )
		.replace( /<\/p>\s*<p[^>]*>/gi, '\n\n' )
		.replace( /<\/?p[^>]*>/gi, '' )
		.replace( /\n{3,}/g, '\n\n' )
		.trim();

/**
 * Editable text back to block-level HTML: each blank-line chunk becomes a
 * paragraph unless it already starts with a block tag.
 *
 * @param {string} text Text.
 * @return {string} HTML.
 */
export const fromEditable = ( text ) =>
	String( text || '' )
		.split( /\n\s*\n/ )
		.map( ( chunk ) => chunk.trim() )
		.filter( Boolean )
		.map( ( chunk ) =>
			/^<(h[1-6]|ul|ol|blockquote|table|figure)\b/i.test( chunk )
				? chunk
				: `<p>${ chunk.replace( /\n/g, '<br>' ) }</p>`
		)
		.join( '\n' );
