/**
 * Live writing checklist: content rules update as you type; the AI-pattern,
 * voice and style-guide checks run on the server (no AI cost) a moment
 * after you stop typing. Flagged sentences can be highlighted in the text
 * and rewritten one by one (targeted rewrites, previewed first).
 */
import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { dispatch, select, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { create, insert, toHTMLString } from '@wordpress/rich-text';
import { data, writer } from '../../admin/api';
import { checklist, contentFacts } from '../checks';
import { highlight } from '../highlights';
import Preview from '../Preview';
import { flatBlocks, postContext, richHtml, useRequest } from '../utils';
import { useMetaValues } from './SeoPanel';

const ICONS = { good: '✓', warn: '!', info: '·' };

/**
 * Replace one sentence inside a block's rich text, unless it carries a link.
 *
 * @param {string} clientId Block.
 * @param {string} sentence Original sentence.
 * @param {string} rewrite  New sentence.
 * @return {string} '' on success, else why it was skipped.
 */
const replaceSentence = ( clientId, sentence, rewrite ) => {
	const block = select( 'core/block-editor' ).getBlock( clientId );
	if ( ! block || block.attributes.content === undefined ) {
		return __( 'The block changed.', 'chatbot' );
	}
	const value = create( { html: richHtml( block.attributes ) } );
	const start = value.text.indexOf( sentence );
	if ( -1 === start ) {
		return __(
			'The sentence was edited since the check.',
			'chatbot'
		);
	}
	const end = start + sentence.length;
	for ( let i = start; i < end; i++ ) {
		if (
			( value.formats[ i ] || [] ).some(
				( format ) => 'core/link' === format.type
			)
		) {
			return __(
				'It contains a link, so edit it by hand.',
				'chatbot'
			);
		}
	}
	dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
		content: toHTMLString( {
			value: insert( value, rewrite, start, end ),
		} ),
	} );
	return '';
};

const ChecklistPanel = () => {
	const blocks = useSelect(
		( s ) => s( 'core/block-editor' ).getBlocks(),
		[]
	);
	const title = useSelect(
		( s ) => s( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
		[]
	);
	const excerpt = useSelect(
		( s ) => s( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) || '',
		[]
	);
	const featured = useSelect(
		( s ) =>
			s( 'core/editor' ).getEditedPostAttribute( 'featured_media' ) || 0,
		[]
	);
	const pendingRaw = useMetaValues();
	const [ seo, setSeo ] = useState( { title: '', description: '' } );
	const [ focus, setFocus ] = useState( '' );
	const [ remote, setRemote ] = useState( null );
	const [ marking, setMarking ] = useState( true );
	const [ fixing, setFixing ] = useState( null );
	const [ chosen, setChosen ] = useState( {} );
	const [ skipped, setSkipped ] = useState( [] );
	const request = useRequest();
	const timer = useRef();

	useEffect( () => {
		if ( data.postId ) {
			writer( `editor/seo?post_id=${ data.postId }` )
				.then( setSeo )
				.catch( () => {} );
		}
	}, [] );

	const all = useMemo( () => flatBlocks( blocks ), [ blocks ] );
	const facts = useMemo( () => contentFacts( all, data.homeUrl ), [ all ] );
	const textKey = facts.text
		.map( ( item ) => item.clientId + item.text )
		.join( '|' );

	// Server checks a moment after typing stops.
	useEffect( () => {
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			const payload = {};
			facts.text.forEach(
				( item ) => ( payload[ item.clientId ] = item.text )
			);
			writer( 'editor/check', {
				method: 'POST',
				data: { post_id: data.postId, blocks: payload },
			} )
				.then( setRemote )
				.catch( () => {} );
		}, 1500 );
		return () => clearTimeout( timer.current );
	}, [ textKey ] );

	const flags = remote
		? [ ...remote.patterns, ...remote.voice ].filter(
				( flag ) => flag.sentence
			)
		: [];
	const flagKey = flags
		.map( ( flag ) => flag.id + flag.sentence )
		.join( '|' );
	useEffect( () => {
		highlight( marking ? flags : [] );
		return () => highlight( [] );
	}, [ flagKey, marking ] );

	let pending = null;
	try {
		pending = pendingRaw ? JSON.parse( pendingRaw ) : null;
	} catch {}
	const items = checklist(
		facts,
		{
			title,
			excerpt,
			featured,
			metaTitle: pending ? pending.title : seo.title,
			metaDescription: pending ? pending.description : seo.description,
			focus,
		},
		remote
	);

	const fix = ( again = false ) => {
		const list = flags.slice( 0, 15 );
		setFixing( { list, rewrites: [] } );
		setSkipped( [] );
		request
			.run( 'rewrite', 'editor/rewrite', {
				...postContext(),
				items: list.map( ( flag ) => ( {
					sentence: flag.sentence,
					reason: flag.reason,
				} ) ),
				again,
			} )
			.then( ( body ) => {
				if ( ! body ) {
					return;
				}
				setFixing( { list, rewrites: body.rewrites } );
				setSkipped( body.skipped );
				const ticked = {};
				body.rewrites.forEach(
					( rewrite, index ) => ( ticked[ index ] = true )
				);
				setChosen( ticked );
			} );
	};

	const apply = () => {
		const notes = [];
		fixing.rewrites.forEach( ( rewrite, index ) => {
			if ( ! chosen[ index ] ) {
				return;
			}
			const flag = fixing.list.find(
				( item ) => item.sentence === rewrite.original
			);
			const why = flag
				? replaceSentence( flag.id, rewrite.original, rewrite.rewrite )
				: __( 'Not found.', 'chatbot' );
			if ( why ) {
				notes.push( { original: rewrite.original, reason: why } );
			}
		} );
		setFixing( null );
		setSkipped( notes );
	};

	return (
		<div className="qcwr-checklist">
			<TextControl
				label={ __(
					'Topic or search phrase (optional)',
					'chatbot'
				) }
				value={ focus }
				onChange={ setFocus }
				__nextHasNoMarginBottom
			/>
			<ul className="qcwr-checklist__items">
				{ items.map( ( item ) => (
					<li key={ item.id } className={ `is-${ item.status }` }>
						<span
							className="qcwr-checklist__icon"
							aria-hidden="true"
						>
							{ ICONS[ item.status ] }
						</span>
						<span>
							{ item.label }
							{ item.hint && <small>{ item.hint }</small> }
						</span>
					</li>
				) ) }
			</ul>
			{ flags.length > 0 && (
				<div className="qcwr-flags">
					<ToggleControl
						label={ __(
							'Highlight them in the text',
							'chatbot'
						) }
						checked={ marking }
						onChange={ setMarking }
						__nextHasNoMarginBottom
					/>
					<ul>
						{ flags.slice( 0, 20 ).map( ( flag, index ) => (
							<li key={ index }>
								<Button
									variant="link"
									onClick={ () =>
										flag.id &&
										dispatch(
											'core/block-editor'
										).selectBlock( flag.id )
									}
								>
									“
									{ flag.sentence.length > 90
										? `${ flag.sentence.slice( 0, 90 ) }…`
										: flag.sentence }
									”
								</Button>
								<small>{ flag.reason }</small>
							</li>
						) ) }
					</ul>
					<Button variant="secondary" onClick={ () => fix() }>
						{ sprintf(
							/* translators: %d: sentences. */
							_n(
								'Fix %d sentence with AI',
								'Fix %d sentences with AI',
								Math.min( 15, flags.length ),
								'chatbot'
							),
							Math.min( 15, flags.length )
						) }
					</Button>
				</div>
			) }
			{ skipped.length > 0 && (
				<Notice status="warning" onRemove={ () => setSkipped( [] ) }>
					<ul>
						{ skipped.map( ( item, index ) => (
							<li key={ index }>
								“{ item.original }” — { item.reason }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
			{ fixing && (
				<Preview
					title={ __( 'Targeted rewrites', 'chatbot' ) }
					busy={ !! request.busy }
					error={ request.error }
					onTryAgain={ () => fix( true ) }
					onDiscard={ () => setFixing( null ) }
					actions={
						fixing.rewrites.length
							? [
									{
										label: __(
											'Apply the ticked ones',
											'chatbot'
										),
										onClick: apply,
										always: true,
									},
								]
							: []
					}
				>
					<ul className="qcwr-rewrites">
						{ fixing.rewrites.map( ( rewrite, index ) => (
							<li key={ index }>
								<CheckboxControl
									checked={ !! chosen[ index ] }
									onChange={ ( checked ) =>
										setChosen( {
											...chosen,
											[ index ]: checked,
										} )
									}
									label={ rewrite.rewrite }
									__nextHasNoMarginBottom
								/>
								<del>{ rewrite.original }</del>
							</li>
						) ) }
					</ul>
				</Preview>
			) }
		</div>
	);
};

export default ChecklistPanel;
