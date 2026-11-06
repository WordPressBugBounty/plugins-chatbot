/**
 * Add to the post: FAQ (at the end), a summary (at the top) and variations
 * for headlines, opening hooks and calls to action. Every addition is
 * previewed first and inserted as normal, undoable blocks.
 */
import { createBlock, parse } from '@wordpress/blocks';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RegenerateControl, {
	optionsLabel,
	useVersions,
	VersionPager,
} from '../../admin/components/regenerate/Regenerate';
import Preview from '../Preview';
import { postContext, useRequest } from '../utils';

const ADDS = () => ( {
	faq: {
		label: __( 'Add FAQ', 'chatbot' ),
		title: __( 'FAQ', 'chatbot' ),
		where: 'end',
	},
	summary: {
		label: __( 'Add summary', 'chatbot' ),
		title: __( 'Summary', 'chatbot' ),
		where: 'top',
	},
} );

const insertAt = ( blocks, where ) =>
	dispatch( 'core/block-editor' ).insertBlocks(
		blocks,
		'top' === where ? 0 : select( 'core/block-editor' ).getBlockCount()
	);

export const AddBlocks = () => {
	const [ open, setOpen ] = useState( null );
	const versions = useVersions();
	const result = versions.current;
	const request = useRequest();
	// CR-14: a first write, or a regeneration with the same or changed options.
	const go = ( id, options = null ) => {
		if ( id !== open ) {
			versions.reset();
		}
		setOpen( id );
		request
			.run( id, `editor/${ id }`, {
				...postContext(),
				again: !! options,
				regenerate: options || {},
				slot: id,
			} )
			.then(
				( body ) =>
					body &&
					versions.add(
						body,
						optionsLabel( options || {} ),
						body.versions
					)
			);
	};
	const adds = ADDS();
	return (
		<>
			<div className="qcwr-actions">
				{ Object.keys( adds ).map( ( id ) => (
					<Button
						key={ id }
						variant="secondary"
						onClick={ () => go( id ) }
					>
						{ adds[ id ].label }
					</Button>
				) ) }
			</div>
			{ open && (
				<Preview
					title={ adds[ open ].title }
					html={ result ? result.html : '' }
					busy={ !! request.busy }
					error={ request.error }
					onTryAgain={ ( options ) => go( open, options ) }
					regenerateKind={ open }
					versions={ versions.versions }
					versionIndex={ versions.index }
					onVersion={ versions.show }
					onDiscard={ () => setOpen( null ) }
					actions={ [
						{
							label:
								'top' === adds[ open ].where
									? __(
											'Insert at the top',
											'chatbot'
										)
									: __(
											'Insert at the end',
											'chatbot'
										),
							onClick: () => {
								insertAt(
									parse( result.content ),
									adds[ open ].where
								);
								setOpen( null );
							},
						},
					] }
				/>
			) }
		</>
	);
};

const KINDS = () => [
	{ value: 'headline', label: __( 'Headlines', 'chatbot' ) },
	{ value: 'hook', label: __( 'Opening hooks', 'chatbot' ) },
	{ value: 'cta', label: __( 'Calls to action', 'chatbot' ) },
];

export const Variations = () => {
	const [ kind, setKind ] = useState( 'headline' );
	const versions = useVersions();
	const options = ( versions.current && versions.current.options ) || [];
	const request = useRequest();
	const get = ( regenerate = null ) =>
		request
			.run( 'variations', 'editor/variations', {
				...postContext(),
				kind,
				again: !! regenerate,
				regenerate: regenerate || {},
				slot: `variations_${ kind }`,
			} )
			.then(
				( body ) =>
					body &&
					versions.add(
						body,
						optionsLabel( regenerate || {} ),
						body.versions
					)
			);
	const use = ( text ) => {
		if ( 'headline' === kind ) {
			dispatch( 'core/editor' ).editPost( { title: text } );
		} else {
			const html = text.replace(
				/[<>&]/g,
				( c ) => ( { '<': '&lt;', '>': '&gt;', '&': '&amp;' } )[ c ]
			);
			insertAt(
				[ createBlock( 'core/paragraph', { content: html } ) ],
				'hook' === kind ? 'top' : 'end'
			);
		}
	};
	return (
		<div className="qcwr-variations">
			<div className="qcwr-actions">
				<SelectControl
					label={ __( 'Variations', 'chatbot' ) }
					hideLabelFromVision
					value={ kind }
					options={ KINDS() }
					onChange={ ( next ) => {
						setKind( next );
						versions.reset();
					} }
					__nextHasNoMarginBottom
				/>
				{ options.length > 0 ? (
					<RegenerateControl
						kind="variations"
						busy={ !! request.busy }
						label={ __( '5 more', 'chatbot' ) }
						onRegenerate={ get }
					/>
				) : (
					<Button
						variant="secondary"
						isBusy={ !! request.busy }
						disabled={ !! request.busy }
						onClick={ () => get() }
					>
						{ __( 'Get 5', 'chatbot' ) }
					</Button>
				) }
			</div>
			<VersionPager
				versions={ versions.versions }
				index={ versions.index }
				onChange={ versions.show }
			/>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			<ul className="qcwr-choices">
				{ options.map( ( option ) => (
					<li key={ option }>
						<Button variant="link" onClick={ () => use( option ) }>
							{ option }
						</Button>
					</li>
				) ) }
			</ul>
			{ options.length > 0 && (
				<p className="qcwr-muted">
					{ 'headline' === kind
						? __(
								'Click one to use it as the title.',
								'chatbot'
							)
						: __( 'Click one to insert it.', 'chatbot' ) }
				</p>
			) }
		</div>
	);
};
