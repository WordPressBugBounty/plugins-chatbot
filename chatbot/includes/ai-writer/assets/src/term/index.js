/**
 * "Write description with AI" under the description field of product
 * categories and tags. The text is put into the field; it is saved when
 * the owner adds or updates the term.
 */
import { Button, Notice } from '@wordpress/components';
import { createRoot, render, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../admin/api';

const field = () =>
	document.getElementById( 'description' ) ||
	document.getElementById( 'tag-description' );

const TermButton = () => {
	const [ busy, setBusy ] = useState( false );
	const [ flags, setFlags ] = useState( [] );
	const [ error, setError ] = useState( '' );

	const write = () => {
		setBusy( true );
		setError( '' );
		writer( `woo/terms/${ data.taxonomy }/${ data.termId }/write`, {
			method: 'POST',
		} )
			.then( ( body ) => {
				const target = field();
				if ( target ) {
					target.value = body.text.replace( /<[^>]+>/g, '' );
					target.dispatchEvent(
						new window.Event( 'input', { bubbles: true } )
					);
				}
				setFlags( body.flags || [] );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-term">
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy }
				onClick={ write }
			>
				{ __( 'Write description with AI', 'chatbot' ) }
			</Button>
			<p className="description">
				{ __(
					'Written from the products in it. Check it, then update to save.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ flags.map( ( flag, index ) => (
				<Notice key={ index } status="warning" isDismissible={ false }>
					{ flag.reason }
				</Notice>
			) ) }
		</div>
	);
};

const mount = () => {
	const target = field();
	// The add form (no term yet) has nothing to write from.
	if ( ! target || ! data.termId ) {
		return;
	}
	const root = document.createElement( 'div' );
	target.parentNode.appendChild( root );
	if ( createRoot ) {
		createRoot( root ).render( <TermButton /> );
	} else {
		render( <TermButton />, root );
	}
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
