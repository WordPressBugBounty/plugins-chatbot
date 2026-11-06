/**
 * The /ai block: type "/ai" anywhere to write, continue or rewrite at that
 * spot. It previews what it wrote and is then replaced by native blocks.
 * It is never part of the saved post (and renders nothing if it were).
 */
import { useBlockProps } from '@wordpress/block-editor';
import { parse, registerBlockType, getBlockContent } from '@wordpress/blocks';
import {
	Button,
	ButtonGroup,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { RawHTML, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data } from '../admin/api';
import AiReadiness, { aiDisabledReason } from '../admin/components/AiReadiness';
import RegenerateControl, {
	optionsLabel,
	useVersions,
	VersionPager,
} from '../admin/components/regenerate/Regenerate';
import { sparkles } from './icons';
import { postContext, toText, useRequest } from './utils';

/**
 * Plain text of the blocks around a block.
 *
 * @param {string} clientId Block.
 * @return {Object} before, after, previous (client id of the block above).
 */
const around = ( clientId ) => {
	const editor = select( 'core/block-editor' );
	const root = editor.getBlockRootClientId( clientId );
	const order = editor.getBlockOrder( root || undefined );
	const index = order.indexOf( clientId );
	const text = ( ids ) =>
		ids
			.map( ( id ) => toText( getBlockContent( editor.getBlock( id ) ) ) )
			.filter( Boolean )
			.join( '\n\n' );
	return {
		before: text( order.slice( Math.max( 0, index - 4 ), index ) ),
		after: text( order.slice( index + 1, index + 3 ) ),
		previous: index > 0 ? order[ index - 1 ] : '',
	};
};

const MODES = () => [
	{ id: 'write', label: __( 'Write', 'chatbot' ) },
	{ id: 'continue', label: __( 'Continue', 'chatbot' ) },
	{
		id: 'rewrite',
		label: __( 'Rewrite the block above', 'chatbot' ),
	},
];

const Edit = ( { clientId, attributes, setAttributes } ) => {
	const [ mode, setMode ] = useState( 'write' );
	const versions = useVersions();
	const result = versions.current;
	const { run, busy, error } = useRequest();
	const blockProps = useBlockProps( { className: 'qcwr-ai-block' } );

	// CR-14: write, then regenerate with the same or changed options.
	const generate = ( regenerate = null ) => {
		const spot = around( clientId );
		const previous =
			'rewrite' === mode && spot.previous
				? toText(
						getBlockContent(
							select( 'core/block-editor' ).getBlock(
								spot.previous
							)
						)
					)
				: '';
		run( 'write', 'editor/write-here', {
			...postContext(),
			prompt: attributes.prompt,
			mode,
			before: 'rewrite' === mode ? '' : spot.before,
			after: spot.after,
			selected: previous,
			format: 'blocks',
			again: !! regenerate,
			regenerate: regenerate || {},
		} ).then(
			( body ) =>
				body && versions.add( body, optionsLabel( regenerate || {} ) )
		);
	};

	const insertResult = () => {
		const blocks = parse( result.content );
		const editor = dispatch( 'core/block-editor' );
		const spot = around( clientId );
		if ( 'rewrite' === mode && spot.previous ) {
			editor.removeBlock( spot.previous, false );
		}
		editor.replaceBlocks( clientId, blocks );
	};

	return (
		<div { ...blockProps }>
			<div className="qcwr-ai-block__head">
				{ sparkles }
				<strong>{ __( 'AI Writer', 'chatbot' ) }</strong>
				<ButtonGroup>
					{ MODES().map( ( item ) => (
						<Button
							key={ item.id }
							size="small"
							variant={
								mode === item.id ? 'primary' : 'secondary'
							}
							onClick={ () => setMode( item.id ) }
						>
							{ item.label }
						</Button>
					) ) }
				</ButtonGroup>
			</div>
			<AiReadiness readiness={ data.readiness } newTab />
			<TextareaControl
				label={ __( 'What should it write?', 'chatbot' ) }
				placeholder={
					'write' === mode
						? __(
								'For example: a paragraph on our opening hours',
								'chatbot'
							)
						: __(
								'Optional: how should it do it?',
								'chatbot'
							)
				}
				value={ attributes.prompt }
				onChange={ ( prompt ) => setAttributes( { prompt } ) }
				rows={ 2 }
				__nextHasNoMarginBottom
			/>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ busy && <Spinner /> }
			{ result && ! busy && (
				<RawHTML className="qcwr-ai-block__preview">
					{ result.preview }
				</RawHTML>
			) }
			<div className="qcwr-actions">
				{ ! result && (
					<Button
						variant="primary"
						isBusy={ !! busy }
						disabled={
							!! busy ||
							!! aiDisabledReason( data.readiness ) ||
							( 'write' === mode && ! attributes.prompt.trim() )
						}
						onClick={ () => generate() }
					>
						{ __( 'Write it', 'chatbot' ) }
					</Button>
				) }
				{ result && (
					<>
						<Button
							variant="primary"
							disabled={ !! busy }
							onClick={ insertResult }
						>
							{ 'rewrite' === mode
								? __(
										'Replace the block above',
										'chatbot'
									)
								: __( 'Insert', 'chatbot' ) }
						</Button>
						{ ! aiDisabledReason( data.readiness ) && (
							<RegenerateControl
								kind="write-here"
								busy={ !! busy }
								onRegenerate={ generate }
							/>
						) }
						<VersionPager
							versions={ versions.versions }
							index={ versions.index }
							onChange={ versions.show }
						/>
					</>
				) }
				<Button
					variant="tertiary"
					isDestructive
					onClick={ () =>
						dispatch( 'core/block-editor' ).removeBlock( clientId )
					}
				>
					{ __( 'Discard', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export const registerAiBlock = () =>
	registerBlockType( 'qcld-wpbot-writer/ai', {
		apiVersion: 3,
		title: __( 'AI Writer', 'chatbot' ),
		description: __(
			'Write, continue or rewrite at this spot. It is replaced by what it writes.',
			'chatbot'
		),
		icon: sparkles,
		category: 'text',
		keywords: [ 'ai', 'write', 'continue', 'rewrite' ],
		attributes: { prompt: { type: 'string', default: '' } },
		supports: { html: false, reusable: false, customClassName: false },
		edit: Edit,
		save: () => null,
	} );
