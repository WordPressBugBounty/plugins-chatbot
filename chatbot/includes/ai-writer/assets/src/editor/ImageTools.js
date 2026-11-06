/**
 * Image tools: "Replace" on image blocks, three featured image suggestions,
 * missing alt text, and the pre-publish image check with a fix for each
 * issue. The check never blocks publishing.
 */
import { BlockControls } from '@wordpress/block-editor';
import { Button, Notice, Spinner, ToolbarButton } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { dispatch, select, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data } from '../admin/api';
import ImagePicker from './ImagePicker';
import { sparkles } from './icons';
import { flatBlocks, postContext, toText, useRequest } from './utils';

const setFeatured = ( picked ) =>
	dispatch( 'core/editor' ).editPost( { featured_media: picked.id } );

const imageAttributes = ( picked ) => ( {
	id: picked.id || undefined,
	url: picked.url,
	alt: picked.alt || '',
	caption: picked.caption || '',
} );

const withReplace = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const [ open, setOpen ] = useState( false );
		if ( 'core/image' !== props.name || ! props.isSelected ) {
			return <BlockEdit { ...props } />;
		}
		return (
			<>
				<BlockEdit { ...props } />
				<BlockControls group="other">
					<ToolbarButton
						icon={ sparkles }
						label={ __(
							'Replace with AI Writer',
							'chatbot'
						) }
						onClick={ () => setOpen( true ) }
					/>
				</BlockControls>
				{ open && (
					<ImagePicker
						query={
							props.attributes.alt ||
							select( 'core/editor' ).getEditedPostAttribute(
								'title'
							)
						}
						alt={ props.attributes.alt }
						onPick={ ( picked ) => {
							props.setAttributes( imageAttributes( picked ) );
							setOpen( false );
						} }
						onRemove={ () => {
							dispatch( 'core/block-editor' ).removeBlock(
								props.clientId
							);
							setOpen( false );
						} }
						onClose={ () => setOpen( false ) }
					/>
				) }
			</>
		);
	},
	'withQcwrReplace'
);

export const registerImageTools = () =>
	addFilter( 'editor.BlockEdit', 'qcwr/image-replace', withReplace );

/**
 * Three ready featured image suggestions from the title.
 */
export const FeaturedSuggestions = () => {
	const title = useSelect(
		( s ) => s( 'core/editor' ).getEditedPostAttribute( 'title' ),
		[]
	);
	const [ results, setResults ] = useState( null );
	const [ picker, setPicker ] = useState( false );
	const request = useRequest();
	const suggest = () =>
		request
			.run( 'featured', 'images/featured', {
				query: title,
				post_id: data.postId,
			} )
			.then( ( body ) => body && setResults( body.results ) );
	const use = ( item ) =>
		request
			.run( 'import', 'images/import', {
				candidate: item,
				alt: title,
				post_id: data.postId,
			} )
			.then( ( body ) => body && setFeatured( body ) );

	return (
		<div className="qcwr-featured">
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isBusy={ 'featured' === request.busy }
					disabled={ ! title || !! request.busy }
					onClick={ suggest }
				>
					{ __( 'Suggest featured images', 'chatbot' ) }
				</Button>
				<Button variant="tertiary" onClick={ () => setPicker( true ) }>
					{ __( 'More choices', 'chatbot' ) }
				</Button>
			</div>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ 'import' === request.busy && <Spinner /> }
			{ results && (
				<div className="qcwr-images__grid qcwr-images__grid--three">
					{ results.map( ( item, index ) => (
						<button
							type="button"
							key={ index }
							className="qcwr-images__item"
							onClick={ () => use( item ) }
							disabled={ !! request.busy }
						>
							<img src={ item.thumb || item.url } alt="" />
							<span>{ __( 'Use', 'chatbot' ) }</span>
						</button>
					) ) }
					{ ! results.length && (
						<p>
							{ __( 'No suggestions found.', 'chatbot' ) }
						</p>
					) }
				</div>
			) }
			{ picker && (
				<ImagePicker
					featured
					query={ title }
					alt={ title }
					onPick={ ( picked ) => {
						setFeatured( picked );
						setPicker( false );
					} }
					onClose={ () => setPicker( false ) }
				/>
			) }
		</div>
	);
};

/**
 * Write alt text for Media Library images that have none (preview first).
 *
 * @param {Object[]} images Image facts (clientId, id, alt).
 * @param {Function} run    useRequest().run.
 * @return {Promise<Object>} id => alt.
 */
const writeAlt = ( images, run ) => {
	const blocks = flatBlocks( select( 'core/block-editor' ).getBlocks() );
	const context = ( clientId ) => {
		const index = blocks.findIndex(
			( block ) => block.clientId === clientId
		);
		const near = blocks
			.slice( Math.max( 0, index - 2 ), index )
			.map( ( block ) => toText( block.attributes.content || '' ) );
		return near.join( ' ' ).slice( 0, 300 );
	};
	return run( 'alt', 'editor/alt', {
		...postContext(),
		images: images
			.filter( ( image ) => image.id )
			.map( ( image ) => ( {
				id: image.id,
				context: context( image.clientId ),
			} ) ),
	} );
};

/**
 * The image check: what the editor sees plus server facts (size, reuse,
 * credits). Each issue has a fix.
 *
 * @param {Object}  props
 * @param {boolean} props.featuredFix Offer featured image suggestions in the list.
 */
export const ImageCheck = ( { featuredFix = true } ) => {
	// Select stable store values; derive lists in render (no new objects in useSelect).
	const blocks = useSelect(
		( s ) => s( 'core/block-editor' ).getBlocks(),
		[]
	);
	const featured = useSelect(
		( s ) =>
			s( 'core/editor' ).getEditedPostAttribute( 'featured_media' ) || 0,
		[]
	);
	const all = useMemo( () => flatBlocks( blocks ), [ blocks ] );
	const images = all
		.filter( ( block ) => 'core/image' === block.name )
		.map( ( block ) => ( {
			clientId: block.clientId,
			id: block.attributes.id || 0,
			url: block.attributes.url || '',
			alt: block.attributes.alt || '',
			caption: String( block.attributes.caption || '' ),
		} ) );
	const placeholders = all
		.filter( ( block ) =>
			/qcwr-image-placeholder/.test( block.attributes.className || '' )
		)
		.map( ( block ) => block.clientId );
	const [ facts, setFacts ] = useState( {} );
	const [ alts, setAlts ] = useState( null );
	const request = useRequest();
	const ids = [ ...images.map( ( image ) => image.id ), featured ].filter(
		Boolean
	);
	const key = ids.join( ',' );

	useEffect( () => {
		if ( ! ids.length ) {
			setFacts( {} );
			return;
		}
		request
			.run( 'facts', 'images/check', { post_id: data.postId, ids } )
			.then( ( body ) => {
				const byId = {};
				( body ? body.images : [] ).forEach(
					( fact ) => ( byId[ fact.id ] = fact )
				);
				setFacts( byId );
			} );
		// Re-check when the set of images changes.
	}, [ key ] );

	const selectBlock = ( clientId ) =>
		dispatch( 'core/block-editor' ).selectBlock( clientId );
	const noAlt = images.filter( ( image ) => ! image.alt );
	const issues = [];
	if ( ! featured ) {
		issues.push( {
			id: 'featured',
			text: __( 'No featured image.', 'chatbot' ),
			fix: featuredFix ? <FeaturedSuggestions /> : null,
		} );
	}
	if ( noAlt.length ) {
		issues.push( {
			id: 'alt',
			text: sprintf(
				/* translators: %d: images. */ _n(
					'%d image has no alt text.',
					'%d images have no alt text.',
					noAlt.length,
					'chatbot'
				),
				noAlt.length
			),
			fix: noAlt.some( ( image ) => image.id ) ? (
				<Button
					variant="secondary"
					isBusy={ 'alt' === request.busy }
					onClick={ () =>
						writeAlt( noAlt, request.run ).then(
							( body ) => body && setAlts( body.alts )
						)
					}
				>
					{ __( 'Write alt text', 'chatbot' ) }
				</Button>
			) : (
				<Button
					variant="link"
					onClick={ () => selectBlock( noAlt[ 0 ].clientId ) }
				>
					{ __( 'Select it', 'chatbot' ) }
				</Button>
			),
		} );
	}
	images.forEach( ( image ) => {
		const fact = facts[ image.id ];
		if ( fact && fact.oversized ) {
			issues.push( {
				id: `big-${ image.clientId }`,
				text: sprintf(
					/* translators: %d: kilobytes. */ __(
						'A large image file (%d KB) slows the page.',
						'chatbot'
					),
					Math.round( fact.bytes / 1024 )
				),
				fix: (
					<Button
						variant="link"
						onClick={ () => selectBlock( image.clientId ) }
					>
						{ __( 'Select it to replace', 'chatbot' ) }
					</Button>
				),
			} );
		}
		if ( fact && fact.reused_in.length ) {
			issues.push( {
				id: `reused-${ image.clientId }`,
				text: sprintf(
					/* translators: %s: post title. */ __(
						'Also used in "%s".',
						'chatbot'
					),
					fact.reused_in[ 0 ].title
				),
				fix: (
					<Button
						variant="link"
						onClick={ () => selectBlock( image.clientId ) }
					>
						{ __( 'Select it to replace', 'chatbot' ) }
					</Button>
				),
			} );
		}
		if (
			fact &&
			fact.needs_credit &&
			! toText( image.caption ).includes(
				fact.credit.replace( /^[^:]+:\s*/, '' ).split( ' (' )[ 0 ]
			)
		) {
			issues.push( {
				id: `credit-${ image.clientId }`,
				text: __(
					'The image licence asks for a credit.',
					'chatbot'
				),
				fix: (
					<Button
						variant="secondary"
						onClick={ () =>
							dispatch(
								'core/block-editor'
							).updateBlockAttributes( image.clientId, {
								caption: fact.credit,
							} )
						}
					>
						{ __( 'Add the credit', 'chatbot' ) }
					</Button>
				),
			} );
		}
	} );
	if ( placeholders.length ) {
		issues.push( {
			id: 'placeholders',
			text: sprintf(
				/* translators: %d: placeholders. */ _n(
					'%d image placeholder to replace.',
					'%d image placeholders to replace.',
					placeholders.length,
					'chatbot'
				),
				placeholders.length
			),
			fix: (
				<Button
					variant="link"
					onClick={ () => selectBlock( placeholders[ 0 ] ) }
				>
					{ __( 'Select it', 'chatbot' ) }
				</Button>
			),
		} );
	}
	if (
		images.length &&
		images.every( ( image ) => /images\.unsplash\.com/.test( image.url ) )
	) {
		issues.push( {
			id: 'unsplash-only',
			text: __(
				'Every image is from Unsplash. Add one of your own or from another source, and set a featured image (Unsplash cannot be the featured image).',
				'chatbot'
			),
			fix: null,
		} );
	}

	return (
		<div className="qcwr-image-check">
			{ ! issues.length && (
				<p className="qcwr-good">
					{ __( 'Images look good.', 'chatbot' ) }
				</p>
			) }
			<ul>
				{ issues.map( ( issue ) => (
					<li key={ issue.id }>
						<span>{ issue.text }</span>
						{ issue.fix }
					</li>
				) ) }
			</ul>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ alts && (
				<div className="qcwr-alts">
					<p>
						<strong>
							{ __( 'Suggested alt text', 'chatbot' ) }
						</strong>{ ' ' }
						{ __(
							'(written from the file details and nearby text; check it matches each image)',
							'chatbot'
						) }
					</p>
					<ul>
						{ Object.keys( alts ).map( ( id ) => (
							<li key={ id }>{ alts[ id ] }</li>
						) ) }
					</ul>
					<Button
						variant="primary"
						onClick={ () => {
							images.forEach( ( image ) => {
								if ( ! image.alt && alts[ image.id ] ) {
									dispatch(
										'core/block-editor'
									).updateBlockAttributes( image.clientId, {
										alt: alts[ image.id ],
									} );
								}
							} );
							setAlts( null );
						} }
					>
						{ __( 'Apply', 'chatbot' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => setAlts( null ) }
					>
						{ __( 'Discard', 'chatbot' ) }
					</Button>
				</div>
			) }
		</div>
	);
};
