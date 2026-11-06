/**
 * "AI Writer" toolbar menu on text blocks: Simplify, Shorten, Expand, Fix
 * grammar, Translate, Make more natural, Change tone. Works on the selected
 * text, or on the whole block when nothing is selected. Every result is a
 * preview first; Replace and Insert below are normal, undoable edits.
 */
import { BlockControls } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	SelectControl,
	TextControl,
	Modal,
	ToolbarDropdownMenu,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { dispatch, select } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { create, insert, slice, toHTMLString } from '@wordpress/rich-text';
import { data } from '../admin/api';
import { aiDisabledReason } from '../admin/components/AiReadiness';
import CompareTechniques from '../admin/components/persuasion/CompareTechniques';
import {
	optionsLabel,
	useVersions,
} from '../admin/components/regenerate/Regenerate';
import Preview from './Preview';
import { sparkles } from './icons';
import { postContext, richHtml, useRequest } from './utils';

export const TEXT_BLOCKS = [
	'core/paragraph',
	'core/heading',
	'core/list-item',
];

const ACTIONS = () => [
	{ id: 'simplify', label: __( 'Simplify', 'chatbot' ) },
	{ id: 'shorten', label: __( 'Shorten', 'chatbot' ) },
	{ id: 'expand', label: __( 'Expand', 'chatbot' ) },
	{ id: 'grammar', label: __( 'Fix grammar', 'chatbot' ) },
	{ id: 'natural', label: __( 'Make more natural', 'chatbot' ) },
	{ id: 'tone', label: __( 'Change tone…', 'chatbot' ), ask: true },
	{
		id: 'translate',
		label: __( 'Translate…', 'chatbot' ),
		ask: true,
	},
];

const TONES = () => [
	{ value: 'friendly', label: __( 'Friendly', 'chatbot' ) },
	{ value: 'professional', label: __( 'Professional', 'chatbot' ) },
	{ value: 'confident', label: __( 'Confident', 'chatbot' ) },
	{ value: 'playful', label: __( 'Playful', 'chatbot' ) },
	{ value: 'empathetic', label: __( 'Empathetic', 'chatbot' ) },
	{ value: 'direct', label: __( 'Direct', 'chatbot' ) },
];

/**
 * The text to work on: the selection inside this block, else the block.
 *
 * @param {string} clientId   Block.
 * @param {Object} attributes Attributes.
 * @return {Object} { value, start, end, html, partial }.
 */
const target = ( clientId, attributes ) => {
	const value = create( { html: richHtml( attributes ) } );
	const editor = select( 'core/block-editor' );
	const start = editor.getSelectionStart();
	const end = editor.getSelectionEnd();
	if (
		start.clientId === clientId &&
		end.clientId === clientId &&
		'content' === start.attributeKey &&
		start.offset !== end.offset
	) {
		const from = Math.min( start.offset, end.offset );
		const to = Math.max( start.offset, end.offset );
		return {
			value,
			start: from,
			end: to,
			html: toHTMLString( { value: slice( value, from, to ) } ),
			partial: true,
		};
	}
	return {
		value,
		start: 0,
		end: value.text.length,
		html: toHTMLString( { value } ),
		partial: false,
	};
};

/**
 * "Rewrite with technique…" (round 3 P3): Compare techniques on the
 * selected text or block, then replace it with the chosen version.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.clientId Block.
 * @param {Object}   props.target   Text to work on.
 * @param {Function} props.onClose  Close.
 * @return {Element} Modal.
 */
const CompareModal = ( { clientId, target: what, onClose } ) => {
	const editor = select( 'core/block-editor' );
	const first =
		0 === editor.getBlockIndex( clientId ) &&
		! editor.getBlockRootClientId( clientId );
	const [ kind, setKind ] = useState( first ? 'intro' : 'section' );
	const source = create( { html: what.html } ).text;
	const use = ( answer ) => {
		const paragraphs = String( answer.text )
			.split( /\n{2,}/ )
			.map( ( text ) => text.trim() )
			.filter( Boolean );
		if ( what.partial || paragraphs.length < 2 ) {
			const next = what.partial
				? toHTMLString( {
						value: insert(
							what.value,
							create( { text: paragraphs.join( ' ' ) } ),
							what.start,
							what.end
						),
					} )
				: toHTMLString( {
						value: create( { text: paragraphs.join( ' ' ) } ),
					} );
			dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
				content: next,
			} );
		} else {
			dispatch( 'core/block-editor' ).replaceBlocks(
				clientId,
				paragraphs.map( ( text ) =>
					createBlock( 'core/paragraph', {
						content: toHTMLString( { value: create( { text } ) } ),
					} )
				)
			);
		}
		onClose();
	};
	return (
		<Modal
			title={ __( 'Rewrite with technique', 'chatbot' ) }
			onRequestClose={ onClose }
			className="qcwr-compare-modal"
			shouldCloseOnClickOutside={ false }
			size="large"
		>
			<SelectControl
				label={ __( 'This text is', 'chatbot' ) }
				value={ kind }
				options={ [
					{
						value: 'section',
						label: __(
							'A paragraph or section',
							'chatbot'
						),
					},
					{
						value: 'intro',
						label: __( 'The introduction', 'chatbot' ),
					},
					{
						value: 'cta',
						label: __( 'A call to action', 'chatbot' ),
					},
				] }
				onChange={ setKind }
				__nextHasNoMarginBottom
			/>
			<CompareTechniques
				key={ kind }
				kind={ kind }
				getInput={ () => ( { ...postContext(), source } ) }
				onUse={ use }
			/>
		</Modal>
	);
};

// The open "Rewrite with technique" modal lives outside the toolbar menu,
// which unmounts when the block loses selection (clicks in the modal do that).
let openCompare = null;
const compareListeners = new Set();
const setOpenCompare = ( next ) => {
	openCompare = next;
	compareListeners.forEach( ( listener ) => listener( next ) );
};

/**
 * Renders the modal for its block while it is open.
 *
 * @param {Object} props          Props.
 * @param {string} props.clientId Block.
 * @return {Element|null} Modal.
 */
const CompareHost = ( { clientId } ) => {
	const [ state, setState ] = useState( openCompare );
	useEffect( () => {
		compareListeners.add( setState );
		return () => compareListeners.delete( setState );
	}, [] );
	return state && state.clientId === clientId ? (
		<CompareModal
			clientId={ clientId }
			target={ state.target }
			onClose={ () => setOpenCompare( null ) }
		/>
	) : null;
};

const AiMenu = ( { clientId, name, attributes } ) => {
	const [ open, setOpen ] = useState( null );
	const [ option, setOption ] = useState( '' );
	const versions = useVersions();
	const result = versions.current ? versions.current.html : '';
	const { run, busy, error, setError } = useRequest();

	// CR-14: regenerate with the same options, or changed ones (versions kept on screen).
	const go = ( action, chosen, regenerate = null ) => {
		const what =
			open && open.target ? open.target : target( clientId, attributes );
		if ( ! open || open.action.id !== action.id ) {
			versions.reset();
		}
		setOpen( { action, target: what } );
		if ( action.ask && ! chosen ) {
			return;
		}
		run( 'transform', 'editor/transform', {
			...postContext(),
			html: what.html,
			action: action.id,
			option: chosen || '',
			again: !! regenerate,
			regenerate: regenerate || {},
		} ).then(
			( body ) =>
				body && versions.add( body, optionsLabel( regenerate || {} ) )
		);
	};

	const close = () => {
		setOpen( null );
		versions.reset();
		setOption( '' );
		setError( '' );
	};

	const replace = () => {
		const { value, start, end, partial } = open.target;
		const next = partial
			? toHTMLString( {
					value: insert(
						value,
						create( { html: result } ),
						start,
						end
					),
				} )
			: result;
		dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
			content: next,
		} );
		close();
	};

	const insertBelow = () => {
		const editor = select( 'core/block-editor' );
		const root = editor.getBlockRootClientId( clientId );
		const index = editor.getBlockIndex( clientId );
		const type =
			'core/list-item' === name ? 'core/list-item' : 'core/paragraph';
		dispatch( 'core/block-editor' ).insertBlocks(
			createBlock( type, { content: result } ),
			index + 1,
			root || undefined
		);
		close();
	};

	const needsOption = open && open.action.ask && ! result && ! busy;

	return (
		<>
			<ToolbarDropdownMenu
				icon={ sparkles }
				label={
					aiDisabledReason( data.readiness ) ||
					__( 'AI Writer', 'chatbot' )
				}
				toggleProps={ {
					disabled: !! aiDisabledReason( data.readiness ),
				} }
				controls={ [
					...ACTIONS().map( ( action ) => ( {
						title: action.label,
						onClick: () => go( action ),
					} ) ),
					...( data.persuasion && data.persuasion.compare
						? [
								{
									title: __(
										'Rewrite with technique…',
										'chatbot'
									),
									onClick: () =>
										setOpenCompare( {
											clientId,
											target: target(
												clientId,
												attributes
											),
										} ),
								},
							]
						: [] ),
				] }
			/>

			{ open && (
				<Preview
					title={ open.action.label.replace( '…', '' ) }
					html={ result }
					busy={ !! busy }
					error={ error }
					onDiscard={ close }
					onTryAgain={
						result
							? ( options ) => go( open.action, option, options )
							: null
					}
					regenerateKind="transform"
					versions={ versions.versions }
					versionIndex={ versions.index }
					onVersion={ versions.show }
					actions={
						needsOption
							? [
									{
										label: __(
											'Rewrite',
											'chatbot'
										),
										always: true,
										onClick: () =>
											go(
												open.action,
												option ||
													( 'tone' === open.action.id
														? 'friendly'
														: '' )
											),
									},
								]
							: [
									{
										label: __(
											'Replace',
											'chatbot'
										),
										onClick: replace,
									},
									{
										label: __(
											'Insert below',
											'chatbot'
										),
										onClick: insertBelow,
									},
								]
					}
				>
					{ needsOption && 'tone' === open.action.id && (
						<SelectControl
							label={ __( 'Tone', 'chatbot' ) }
							value={ option || 'friendly' }
							options={ TONES() }
							onChange={ setOption }
							__nextHasNoMarginBottom
						/>
					) }
					{ needsOption && 'translate' === open.action.id && (
						<TextControl
							label={ __(
								'Into which language?',
								'chatbot'
							) }
							placeholder={ __(
								'For example: Spanish',
								'chatbot'
							) }
							value={ option }
							onChange={ setOption }
							__nextHasNoMarginBottom
						/>
					) }
				</Preview>
			) }
		</>
	);
};

const withAiMenu = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( ! TEXT_BLOCKS.includes( props.name ) ) {
			return <BlockEdit { ...props } />;
		}
		if ( ! props.isSelected ) {
			return (
				<>
					<BlockEdit { ...props } />
					<CompareHost clientId={ props.clientId } />
				</>
			);
		}
		return (
			<>
				<BlockEdit { ...props } />
				<CompareHost clientId={ props.clientId } />
				<BlockControls group="other">
					<AiMenu
						clientId={ props.clientId }
						name={ props.name }
						attributes={ props.attributes }
					/>
				</BlockControls>
			</>
		);
	},
	'withQcwrAiMenu'
);

export const registerBlockActions = () =>
	addFilter( 'editor.BlockEdit', 'qcwr/ai-menu', withAiMenu );
