/**
 * Regenerate (CR-14 R2) in the editor sidebar: each section of the article
 * (a level-2 heading and the blocks under it) can be written again with the
 * same or changed options, and the whole article too: keep the outline and
 * rewrite the sections, or write a new outline (see ArticleRegenerate).
 */
import { parse } from '@wordpress/blocks';
import { Button, Notice } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import HonestyFlags from '../../admin/components/persuasion/HonestyFlags';
import RegenerateControl, {
	optionsLabel,
	useVersions,
} from '../../admin/components/regenerate/Regenerate';
import ArticleRegenerate from '../../admin/components/regenerate/ArticleRegenerate';
import Preview from '../Preview';
import { postContext, useRequest } from '../utils';

/**
 * Client ids of the blocks under the n-th level-2 heading (heading excluded).
 *
 * @param {number} index Section index.
 * @return {string[]} Client ids.
 */
const sectionBlocks = ( index ) => {
	const blocks = select( 'core/block-editor' ).getBlocks();
	let seen = -1;
	const ids = [];
	for ( const block of blocks ) {
		const isH2 =
			'core/heading' === block.name &&
			2 === ( block.attributes.level || 2 );
		if ( isH2 ) {
			seen++;
			if ( seen > index ) {
				break;
			}
			continue;
		}
		if ( seen === index ) {
			ids.push( block.clientId );
		}
	}
	return ids;
};

/**
 * Replace a section's blocks (one undoable change).
 *
 * @param {number} index   Section index.
 * @param {string} content Block markup.
 */
const replaceSection = ( index, content ) => {
	const ids = sectionBlocks( index );
	const editor = dispatch( 'core/block-editor' );
	const blocks = parse( content );
	if ( ids.length ) {
		editor.replaceBlocks( ids, blocks );
		return;
	}
	// An empty section: insert after its heading.
	const all = select( 'core/block-editor' ).getBlocks();
	let seen = -1;
	for ( let i = 0; i < all.length; i++ ) {
		if (
			'core/heading' === all[ i ].name &&
			2 === ( all[ i ].attributes.level || 2 )
		) {
			seen++;
			if ( seen === index ) {
				editor.insertBlocks( blocks, i + 1 );
				return;
			}
		}
	}
};

const Sections = () => {
	const [ sections, setSections ] = useState( null );
	const [ open, setOpen ] = useState( null );
	const versions = useVersions();
	const request = useRequest();
	const result = versions.current;

	const load = () =>
		request
			.run( 'sections', 'regenerate/sections', postContext() )
			.then( ( body ) => body && setSections( body.sections ) );

	const regenerateSection = ( section, options = {} ) => {
		if ( ! open || open.index !== section.index ) {
			versions.reset();
		}
		setOpen( section );
		request
			.run( 'section', 'regenerate/section', {
				...postContext(),
				index: section.index,
				regenerate: options,
			} )
			.then(
				( body ) =>
					body &&
					versions.add( body, optionsLabel( options ), body.versions )
			);
	};

	if ( ! sections ) {
		return (
			<div className="qcwr-regen-sections">
				<Button
					variant="secondary"
					isBusy={ 'sections' === request.busy }
					onClick={ load }
				>
					{ __( 'Show the sections', 'chatbot' ) }
				</Button>
				{ request.error && (
					<Notice status="error" isDismissible={ false }>
						{ request.error }
					</Notice>
				) }
			</div>
		);
	}
	return (
		<div className="qcwr-regen-sections">
			{ ! sections.length && (
				<p className="qcwr-muted">
					{ __(
						'No sections yet: sections start at level-2 headings.',
						'chatbot'
					) }
				</p>
			) }
			<ul className="qcwr-regen-sections__list">
				{ sections.map( ( section ) => (
					<li key={ section.index }>
						<span className="qcwr-regen-sections__heading">
							{ section.heading }
						</span>
						<RegenerateControl
							kind="section"
							busy={ 'section' === request.busy }
							onRegenerate={ ( options ) =>
								regenerateSection( section, options )
							}
						/>
					</li>
				) ) }
			</ul>
			{ open && (
				<Preview
					title={ sprintf(
						/* translators: %s: section heading. */
						__( 'Section: %s', 'chatbot' ),
						open.heading
					) }
					html={ result ? result.html : '' }
					busy={ 'section' === request.busy }
					error={ request.error }
					onTryAgain={ ( options ) =>
						regenerateSection( open, options )
					}
					regenerateKind="section"
					versions={ versions.versions }
					versionIndex={ versions.index }
					onVersion={ versions.show }
					onDiscard={ () => setOpen( null ) }
					actions={ [
						{
							label: __(
								'Replace the section',
								'chatbot'
							),
							onClick: () => {
								replaceSection( open.index, result.content );
								setOpen( null );
							},
						},
					] }
				>
					{ result && result.lost && (
						<Notice status="warning" isDismissible={ false }>
							{ result.lost }
						</Notice>
					) }
					{ result && <HonestyFlags flags={ result.honesty } /> }
				</Preview>
			) }
		</div>
	);
};

const WholeArticle = () => (
	<ArticleRegenerate
		postId={ select( 'core/editor' ).getCurrentPostId() }
		isDraft={ [ 'draft', 'pending', 'auto-draft' ].includes(
			select( 'core/editor' ).getEditedPostAttribute( 'status' )
		) }
		inEditor
	/>
);

const RegeneratePanel = () => (
	<>
		<Sections />
		<WholeArticle />
	</>
);

export default RegeneratePanel;
