/**
 * Search appearance: meta title and description for the active SEO plugin
 * (or AI Writer's own meta when none is active). The values are held with
 * the post's unsaved changes and written when the owner saves.
 */
import {
	Button,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { dispatch, select, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, writer } from '../../admin/api';
import CompareTechniques from '../../admin/components/persuasion/CompareTechniques';
import RegenerateControl, {
	optionsLabel,
	useVersions,
	VersionPager,
} from '../../admin/components/regenerate/Regenerate';
import { PENDING_META, postContext, useRequest } from '../utils';

/**
 * Also tell the SEO plugin's own editor screen, so its fields show the new
 * values and its own save does not put the old ones back.
 *
 * @param {string} title       Title.
 * @param {string} description Description.
 * @return {void}
 */
const syncSeoPlugin = ( title, description ) => {
	try {
		if ( 'yoast' === data.seo.plugin ) {
			const yoast = dispatch( 'yoast-seo/editor' );
			if ( yoast && yoast.updateData ) {
				yoast.updateData( { title, description } );
			}
			[
				[ 'yoast_wpseo_title', title ],
				[ 'yoast_wpseo_metadesc', description ],
			].forEach( ( [ id, value ] ) => {
				const field = document.getElementById( id );
				if ( field && value ) {
					field.value = value;
				}
			} );
		}
		if ( 'rankmath' === data.seo.plugin ) {
			const rankMath = dispatch( 'rank-math' );
			if ( rankMath && rankMath.updateTitle && title ) {
				rankMath.updateTitle( title );
			}
			if ( rankMath && rankMath.updateDescription && description ) {
				rankMath.updateDescription( description );
			}
		}
	} catch {
		// The SEO plugin's screen is optional; the server write still happens on save.
	}
};

export const useMetaValues = () =>
	useSelect( ( s ) => {
		const meta = s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		return meta[ PENDING_META ] || '';
	}, [] );

const SeoPanel = () => {
	const pendingRaw = useMetaValues();
	const [ fields, setFields ] = useState( { title: '', description: '' } );
	const [ saved, setSaved ] = useState( null );
	const [ options, setOptions ] = useState( [] );
	const [ comparing, setComparing ] = useState( false );
	const versions = useVersions();
	const request = useRequest();

	useEffect( () => {
		if ( ! data.postId ) {
			return;
		}
		writer( `editor/seo?post_id=${ data.postId }` )
			.then( ( current ) => {
				setSaved( current );
				let pending = null;
				try {
					pending = pendingRaw ? JSON.parse( pendingRaw ) : null;
				} catch {}
				setFields(
					pending || {
						title: current.title,
						description: current.description,
					}
				);
			} )
			.catch( () => {} );
		// Load once.
	}, [] );

	const applyFields = ( next ) => {
		setFields( next );
		dispatch( 'core/editor' ).editPost( {
			meta: { [ PENDING_META ]: JSON.stringify( next ) },
		} );
		syncSeoPlugin( next.title, next.description );
	};

	const fromMeta = ( body ) => ( {
		title: body.meta_title,
		description: body.meta_description,
	} );
	// CR-14: write them, then regenerate with the same or changed options.
	const suggest = ( regenerate = null ) =>
		request
			.run( 'meta', 'editor/meta', {
				...postContext(),
				again: !! regenerate,
				regenerate: regenerate || {},
				slot: 'meta',
			} )
			.then( ( body ) => {
				if ( body ) {
					versions.add(
						body,
						optionsLabel( regenerate || {} ),
						body.versions
					);
					applyFields( fromMeta( body ) );
				}
			} );
	const showVersion = ( index ) => {
		versions.show( index );
		const row = versions.versions[ index ];
		if ( row && row.data ) {
			applyFields( fromMeta( row.data ) );
		}
	};
	const more = () =>
		request
			.run( 'variations', 'editor/variations', {
				...postContext(),
				kind: 'meta',
			} )
			.then( ( body ) => body && setOptions( body.options ) );

	const changed =
		saved &&
		( fields.title !== saved.title ||
			fields.description !== saved.description );

	return (
		<div className="qcwr-seo">
			<p className="qcwr-muted">
				{ sprintf(
					/* translators: %s: SEO plugin name. */
					__( 'Saved to: %s', 'chatbot' ),
					data.seo.label
				) }
			</p>
			<TextControl
				label={ sprintf(
					/* translators: %d: characters. */ __(
						'Search title (%d/60)',
						'chatbot'
					),
					fields.title.length
				) }
				value={ fields.title }
				onChange={ ( title ) => applyFields( { ...fields, title } ) }
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ sprintf(
					/* translators: %d: characters. */ __(
						'Search description (%d/155)',
						'chatbot'
					),
					fields.description.length
				) }
				value={ fields.description }
				onChange={ ( description ) =>
					applyFields( { ...fields, description } )
				}
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isBusy={ 'meta' === request.busy }
					disabled={ !! request.busy }
					onClick={ () => suggest() }
				>
					{ __( 'Write them', 'chatbot' ) }
				</Button>
				{ versions.versions.length > 0 && (
					<RegenerateControl
						kind="meta"
						busy={ 'meta' === request.busy }
						hide={ [ 'length' ] }
						onRegenerate={ suggest }
					/>
				) }
				<Button
					variant="tertiary"
					isBusy={ 'variations' === request.busy }
					disabled={ !! request.busy }
					onClick={ more }
				>
					{ __( '5 options', 'chatbot' ) }
				</Button>
				{ data.persuasion && data.persuasion.compare && (
					<Button
						variant="tertiary"
						onClick={ () => setComparing( ! comparing ) }
					>
						{ __( 'Compare techniques', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ comparing && (
				<CompareTechniques
					kind="meta_title"
					getInput={ () => ( {
						...postContext(),
						source: fields.title,
					} ) }
					onUse={ ( answer ) =>
						applyFields( { ...fields, title: answer.text } )
					}
					onClose={ () => setComparing( false ) }
				/>
			) }
			<VersionPager
				versions={ versions.versions }
				index={ versions.index }
				onChange={ showVersion }
			/>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ changed && select( 'core/editor' ).isEditedPostDirty() && (
				<p className="qcwr-muted">
					{ __(
						'Written to the SEO plugin when you save the post.',
						'chatbot'
					) }
				</p>
			) }
			<ul className="qcwr-choices">
				{ options.map( ( option, index ) => (
					<li key={ index }>
						<Button
							variant="link"
							onClick={ () =>
								applyFields( {
									title: option.title,
									description: option.description,
								} )
							}
						>
							<strong>{ option.title }</strong>
							<span>{ option.description }</span>
						</Button>
					</li>
				) ) }
			</ul>
		</div>
	);
};

export default SeoPanel;
