/**
 * A text field that edits a list (comma separated, or one item per line).
 *
 * The box keeps exactly what the owner types, so spaces, commas and new
 * lines stay while typing; the list is parsed from it on every change.
 * When the list is changed from outside (a prefill), the box follows.
 */
import { TextControl, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Split text into a clean list.
 *
 * @param {string} text      Typed text.
 * @param {string} separator ',' or '\n'.
 * @return {string[]} Items, trimmed, without blanks.
 */
export const parseList = ( text, separator ) =>
	String( text || '' )
		.split( separator )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

const same = ( a, b ) =>
	a.length === b.length && a.every( ( item, index ) => item === b[ index ] );

const ListField = ( { list, onChange, separator = ',', ...props } ) => {
	const items = Array.isArray( list ) ? list : [];
	const joiner = '\n' === separator ? '\n' : ', ';
	const [ text, setText ] = useState( items.join( joiner ) );

	// Follow outside changes only (a prefill), never the owner's own typing.
	useEffect( () => {
		if ( ! same( parseList( text, separator ), items ) ) {
			setText( items.join( joiner ) );
		}
		// The typed text is the source while it matches the list.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ items.join( '\u0000' ) ] );

	const change = ( value ) => {
		setText( value );
		onChange( parseList( value, separator ) );
	};
	const Control = '\n' === separator ? TextareaControl : TextControl;
	return <Control { ...props } value={ text } onChange={ change } />;
};

export default ListField;
