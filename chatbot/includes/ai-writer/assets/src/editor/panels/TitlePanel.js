/**
 * Title suggestions (click to use) and the excerpt (separate from the SEO
 * description). Both are normal, undoable edits of the open post.
 */
import { Button, Notice } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data } from '../../admin/api';
import CompareTechniques from '../../admin/components/persuasion/CompareTechniques';
import RegenerateControl, {
	optionsLabel,
	useVersions,
	VersionPager,
} from '../../admin/components/regenerate/Regenerate';
import Preview from '../Preview';
import { postContext, useRequest } from '../utils';

export const TitleSuggestions = () => {
	const versions = useVersions();
	const titles = ( versions.current && versions.current.titles ) || [];
	const request = useRequest();
	// CR-14: first suggestions, then "More titles" with the same or changed options.
	const suggest = ( regenerate = null ) =>
		request
			.run( 'titles', 'editor/titles', {
				...postContext(),
				again: !! regenerate,
				regenerate: regenerate || {},
				slot: 'titles',
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
	return (
		<div className="qcwr-titles">
			{ titles.length ? (
				<RegenerateControl
					kind="titles"
					busy={ !! request.busy }
					label={ __( 'More titles', 'chatbot' ) }
					hide={ [ 'length' ] }
					onRegenerate={ suggest }
				/>
			) : (
				<Button
					variant="secondary"
					isBusy={ !! request.busy }
					disabled={ !! request.busy }
					onClick={ () => suggest() }
				>
					{ __( 'Suggest titles', 'chatbot' ) }
				</Button>
			) }
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
				{ titles.map( ( title ) => (
					<li key={ title }>
						<Button
							variant="link"
							onClick={ () =>
								dispatch( 'core/editor' ).editPost( { title } )
							}
						>
							{ title }
						</Button>
					</li>
				) ) }
			</ul>
		</div>
	);
};

export const ExcerptWriter = () => {
	const [ open, setOpen ] = useState( false );
	const versions = useVersions();
	const excerpt = versions.current ? versions.current.excerpt : '';
	const request = useRequest();
	const write = ( regenerate = null ) => {
		setOpen( true );
		request
			.run( 'excerpt', 'editor/excerpt', {
				...postContext(),
				again: !! regenerate,
				regenerate: regenerate || {},
				slot: 'excerpt',
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
	};
	return (
		<>
			<Button variant="secondary" onClick={ () => write() }>
				{ __( 'Write excerpt', 'chatbot' ) }
			</Button>
			{ open && (
				<Preview
					title={ __( 'Excerpt', 'chatbot' ) }
					text={ excerpt }
					busy={ !! request.busy }
					error={ request.error }
					onTryAgain={ ( options ) => write( options ) }
					regenerateKind="excerpt"
					versions={ versions.versions }
					versionIndex={ versions.index }
					onVersion={ versions.show }
					onDiscard={ () => setOpen( false ) }
					actions={ [
						{
							label: __( 'Use it', 'chatbot' ),
							onClick: () => {
								dispatch( 'core/editor' ).editPost( {
									excerpt,
								} );
								setOpen( false );
							},
						},
					] }
				/>
			) }
		</>
	);
};

/**
 * Headline lab and Compare techniques for the title (round 3 P3).
 *
 * @return {Element|null} Element.
 */
export const HeadlineLab = () => {
	const [ mode, setMode ] = useState( '' );
	if ( ! data.persuasion || ! data.persuasion.compare ) {
		return null;
	}
	return (
		<>
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isPressed={ 'lab' === mode }
					onClick={ () => setMode( 'lab' === mode ? '' : 'lab' ) }
				>
					{ __( 'Headline lab', 'chatbot' ) }
				</Button>
				<Button
					variant="tertiary"
					isPressed={ 'compare' === mode }
					onClick={ () =>
						setMode( 'compare' === mode ? '' : 'compare' )
					}
				>
					{ __( 'Compare techniques', 'chatbot' ) }
				</Button>
			</div>
			{ mode && (
				<CompareTechniques
					key={ mode }
					kind="headline"
					lab={ 'lab' === mode }
					getInput={ () => {
						const context = postContext();
						return { ...context, source: context.title };
					} }
					onUse={ ( answer ) =>
						dispatch( 'core/editor' ).editPost( {
							title: answer.text,
						} )
					}
					onClose={ () => setMode( '' ) }
				/>
			) }
		</>
	);
};

const TitlePanel = () => (
	<>
		<TitleSuggestions />
		<HeadlineLab />
		<div className="qcwr-actions">
			<ExcerptWriter />
		</div>
	</>
);

export default TitlePanel;
