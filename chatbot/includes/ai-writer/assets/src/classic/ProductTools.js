/**
 * Product tools in the AI Writer box (classic product screen): long and
 * short description, product FAQ, variation descriptions and gallery alt
 * text, all from the product's data. Claims the data does not support are
 * listed for the owner; nothing is hidden.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { RawHTML, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../admin/api';
import CompareTechniques from '../admin/components/persuasion/CompareTechniques';
import RegenerateControl from '../admin/components/regenerate/Regenerate';
import HonestyFlags from '../admin/components/persuasion/HonestyFlags';
import PersuasionPicker from '../admin/components/persuasion/PersuasionPicker';

/**
 * Put HTML into a classic editor field (TinyMCE or its textarea).
 *
 * @param {string}  id      Editor id (content or excerpt).
 * @param {string}  html    HTML.
 * @param {boolean} replace Replace everything (else add at the end).
 */
export const setEditorHtml = ( id, html, replace ) => {
	const editor =
		window.tinymce && window.tinymce.get && window.tinymce.get( id );
	if ( editor && ! editor.isHidden() ) {
		if ( replace ) {
			editor.setContent( html );
		} else {
			editor.setContent( editor.getContent() + '\n' + html );
		}
		editor.fire( 'change' );
		return;
	}
	const area = document.getElementById( id );
	if ( area ) {
		area.value = replace ? html : `${ area.value }\n\n${ html }`;
	}
};

const Flags = ( { flags = [], unsupported = [] } ) =>
	flags.length || unsupported.length ? (
		<Notice status="warning" isDismissible={ false }>
			<strong>{ __( 'Check before using:', 'chatbot' ) }</strong>
			<ul>
				{ flags.map( ( flag, index ) => (
					<li key={ `f${ index }` }>
						“{ flag.sentence }” — { flag.reason }
					</li>
				) ) }
				{ unsupported.map( ( item, index ) => (
					<li key={ `u${ index }` }>
						{ __(
							'Left out (not in the product data):',
							'chatbot'
						) }{ ' ' }
						{ item }
					</li>
				) ) }
			</ul>
		</Notice>
	) : null;

const ProductTools = () => {
	const [ notes, setNotes ] = useState( '' );
	// Round 3: this product's persuasion choices (kept with the product).
	const [ persuasion, setPersuasion ] = useState( data.choices || {} );
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ chosen, setChosen ] = useState( {} );
	const [ done, setDone ] = useState( '' );
	const [ comparing, setComparing ] = useState( false );
	const base = `woo/products/${ data.postId }`;

	const call = ( part, path, payload ) => {
		setBusy( part );
		setError( '' );
		setDone( '' );
		return writer( path, { method: 'POST', data: payload } )
			.then( ( body ) => {
				setResult( { part, ...body } );
				const all = {};
				( body.variations || body.alts || [] ).forEach(
					( item ) => ( all[ item.id ] = true )
				);
				setChosen( all );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( '' ) );
	};
	// CR-14: write, then regenerate with the same or changed options.
	const write = ( part, regenerate = null ) =>
		call( part, `${ base }/write`, {
			part,
			notes,
			again: !! regenerate,
			regenerate: regenerate || {},
			persuasion,
		} );

	const saveChosen = ( path, key, values ) => {
		const payload = {};
		values.forEach( ( item ) => {
			if ( chosen[ item.id ] ) {
				payload[ item.id ] = item[ key ];
			}
		} );
		setBusy( 'save' );
		writer( path, {
			method: 'POST',
			data:
				'alt' === key ? { apply: payload } : { descriptions: payload },
		} )
			.then( () => {
				setResult( null );
				setDone( __( 'Saved.', 'chatbot' ) );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( '' ) );
	};

	const use = ( id, replace ) => {
		setEditorHtml( id, result.html, replace );
		setResult( null );
		setDone(
			__( 'Added. Update the product to save it.', 'chatbot' )
		);
	};

	return (
		<div className="qcwr-classic__product">
			<strong>{ __( 'Product copy', 'chatbot' ) }</strong>
			<TextareaControl
				label={ __(
					'Anything to add? (materials, care, who it is for)',
					'chatbot'
				) }
				value={ notes }
				onChange={ setNotes }
				rows={ 2 }
				__nextHasNoMarginBottom
			/>
			<PersuasionPicker
				value={ persuasion }
				onChange={ setPersuasion }
				type="product"
			/>
			<div className="qcwr-classic__row">
				<Button
					variant="secondary"
					isBusy={ 'long' === busy }
					disabled={ !! busy }
					onClick={ () => write( 'long' ) }
				>
					{ __( 'Description', 'chatbot' ) }
				</Button>
				<Button
					variant="secondary"
					isBusy={ 'short' === busy }
					disabled={ !! busy }
					onClick={ () => write( 'short' ) }
				>
					{ __( 'Short description', 'chatbot' ) }
				</Button>
				<Button
					variant="secondary"
					isBusy={ 'faq' === busy }
					disabled={ !! busy }
					onClick={ () => write( 'faq' ) }
				>
					{ __( 'Product FAQ', 'chatbot' ) }
				</Button>
				{ data.woo.variable && (
					<Button
						variant="secondary"
						isBusy={ 'variations' === busy }
						disabled={ !! busy }
						onClick={ () => write( 'variations' ) }
					>
						{ __( 'Variation descriptions', 'chatbot' ) }
					</Button>
				) }
				<Button
					variant="secondary"
					isBusy={ 'alt' === busy }
					disabled={ !! busy }
					onClick={ () => call( 'alt', `${ base }/alt`, {} ) }
				>
					{ __( 'Image alt text', 'chatbot' ) }
				</Button>
				{ data.persuasion && data.persuasion.compare && (
					<Button
						variant="tertiary"
						isPressed={ comparing }
						onClick={ () => setComparing( ! comparing ) }
					>
						{ __( 'Compare techniques', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ comparing && (
				<CompareTechniques
					kind="product_description"
					getInput={ () => {
						const editor =
							window.tinymce &&
							window.tinymce.get &&
							window.tinymce.get( 'content' );
						const area = document.getElementById( 'content' );
						return {
							product_id: data.postId,
							post_id: data.postId,
							title: ( document.getElementById( 'title' ) || {} )
								.value,
							source:
								editor && ! editor.isHidden()
									? editor.getContent( { format: 'text' } )
									: ( area && area.value ) || '',
							stage: persuasion.stage || '',
						};
					} }
					onUse={ ( answer ) => {
						setEditorHtml( 'content', answer.html, true );
						setComparing( false );
						setDone(
							__(
								'Description replaced. Update the product to save it.',
								'chatbot'
							)
						);
					} }
					onClose={ () => setComparing( false ) }
				/>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ done && (
				<Notice status="success" onRemove={ () => setDone( '' ) }>
					{ done }
				</Notice>
			) }
			{ busy && <Spinner /> }
			{ result && result.html && (
				<div className="qcwr-classic__preview">
					<Flags
						flags={ result.flags }
						unsupported={ result.unsupported }
					/>
					<HonestyFlags flags={ result.honesty } />
					<RawHTML>{ result.html }</RawHTML>
					<div className="qcwr-classic__row">
						{ 'long' === result.part && (
							<>
								<Button
									variant="primary"
									onClick={ () => use( 'content', true ) }
								>
									{ __(
										'Replace the description',
										'chatbot'
									) }
								</Button>
								<Button
									variant="secondary"
									onClick={ () => use( 'content', false ) }
								>
									{ __( 'Add below it', 'chatbot' ) }
								</Button>
							</>
						) }
						{ 'short' === result.part && (
							<Button
								variant="primary"
								onClick={ () => use( 'excerpt', true ) }
							>
								{ __(
									'Use as short description',
									'chatbot'
								) }
							</Button>
						) }
						{ 'faq' === result.part && (
							<Button
								variant="primary"
								onClick={ () => use( 'content', false ) }
							>
								{ __(
									'Add to the description',
									'chatbot'
								) }
							</Button>
						) }
						<RegenerateControl
							kind="product"
							busy={ !! busy }
							onRegenerate={ ( options ) =>
								write( result.part, options )
							}
						/>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setResult( null ) }
						>
							{ __( 'Discard', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ result && result.variations && (
				<div className="qcwr-classic__preview">
					{ result.variations.map( ( item ) => (
						<div key={ item.id }>
							<CheckboxControl
								label={ `${ item.label }: ${ item.text }` }
								checked={ !! chosen[ item.id ] }
								onChange={ ( value ) =>
									setChosen( {
										...chosen,
										[ item.id ]: value,
									} )
								}
								__nextHasNoMarginBottom
							/>
							<Flags flags={ item.flags } />
						</div>
					) ) }
					<div className="qcwr-classic__row">
						<Button
							variant="primary"
							isBusy={ 'save' === busy }
							onClick={ () =>
								saveChosen(
									`${ base }/variations`,
									'text',
									result.variations
								)
							}
						>
							{ __(
								'Save to the ticked variations',
								'chatbot'
							) }
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setResult( null ) }
						>
							{ __( 'Discard', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ result && result.alts && (
				<div className="qcwr-classic__preview">
					{ ! result.alts.length && <p>{ result.message }</p> }
					{ result.alts.map( ( item ) => (
						<div key={ item.id } className="qcwr-classic__alt">
							{ item.thumb && (
								<img
									src={ item.thumb }
									alt=""
									width="48"
									height="48"
								/>
							) }
							<CheckboxControl
								label={ item.alt }
								checked={ !! chosen[ item.id ] }
								onChange={ ( value ) =>
									setChosen( {
										...chosen,
										[ item.id ]: value,
									} )
								}
								__nextHasNoMarginBottom
							/>
						</div>
					) ) }
					{ result.alts.length > 0 && (
						<>
							<p className="description">
								{ __(
									'Written from the file details and product name; check each one matches its image.',
									'chatbot'
								) }
							</p>
							<Button
								variant="primary"
								isBusy={ 'save' === busy }
								onClick={ () =>
									saveChosen(
										`${ base }/alt`,
										'alt',
										result.alts
									)
								}
							>
								{ __(
									'Save the ticked alt texts',
									'chatbot'
								) }
							</Button>
						</>
					) }
				</div>
			) }
		</div>
	);
};

export default ProductTools;
