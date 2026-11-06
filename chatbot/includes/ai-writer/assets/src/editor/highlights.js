/**
 * Highlights flagged sentences in the editor without changing the content,
 * using the CSS Custom Highlight API. Browsers without it simply show the
 * list in the sidebar.
 */

const NAME = 'qcwr-flag';

/**
 * Document that holds the blocks (the editor canvas iframe when present).
 *
 * @return {Document} Document.
 */
const canvas = () => {
	const frame = document.querySelector( 'iframe[name="editor-canvas"]' );
	return ( frame && frame.contentDocument ) || document;
};

/**
 * DOM range covering a sentence inside an element, across inline tags.
 *
 * @param {Element} element  Block element.
 * @param {string}  sentence Sentence.
 * @return {Range|null} Range.
 */
const rangeOf = ( element, sentence ) => {
	const doc = element.ownerDocument;
	const walker = doc.createTreeWalker(
		element,
		4 /* NodeFilter.SHOW_TEXT */
	);
	const nodes = [];
	let text = '';
	while ( walker.nextNode() ) {
		nodes.push( { node: walker.currentNode, start: text.length } );
		text += walker.currentNode.nodeValue;
	}
	const index = text.indexOf( sentence );
	if ( -1 === index ) {
		return null;
	}
	const locate = ( offset ) => {
		for ( let i = nodes.length - 1; i >= 0; i-- ) {
			if ( offset >= nodes[ i ].start ) {
				return [ nodes[ i ].node, offset - nodes[ i ].start ];
			}
		}
		return null;
	};
	const start = locate( index );
	const end = locate( index + sentence.length );
	if ( ! start || ! end ) {
		return null;
	}
	const range = doc.createRange();
	range.setStart( start[ 0 ], start[ 1 ] );
	range.setEnd( end[ 0 ], end[ 1 ] );
	return range;
};

/**
 * Show highlights for flags ({id, sentence}); an empty list clears them.
 *
 * @param {Object[]} flags Flags with block client ids.
 * @return {boolean} Whether highlighting is supported.
 */
export const highlight = ( flags ) => {
	const doc = canvas();
	const view = doc.defaultView;
	if ( ! view || ! view.CSS || ! view.CSS.highlights || ! view.Highlight ) {
		return false;
	}
	if ( ! doc.getElementById( 'qcwr-highlight-style' ) ) {
		const style = doc.createElement( 'style' );
		style.id = 'qcwr-highlight-style';
		style.textContent = `::highlight(${ NAME }){background-color:rgba(255,196,0,.4);}`;
		doc.head.appendChild( style );
	}
	const ranges = [];
	flags.forEach( ( flag ) => {
		if ( ! flag.id || ! flag.sentence ) {
			return;
		}
		const element = doc.querySelector( `[data-block="${ flag.id }"]` );
		const range = element && rangeOf( element, flag.sentence );
		if ( range ) {
			ranges.push( range );
		}
	} );
	if ( ranges.length ) {
		view.CSS.highlights.set( NAME, new view.Highlight( ...ranges ) );
	} else {
		view.CSS.highlights.delete( NAME );
	}
	return true;
};
