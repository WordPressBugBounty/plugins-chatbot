/**
 * AI Writer in the Classic Editor: title suggestions, excerpt, and a Write
 * with AI box that inserts clean HTML at the cursor. Nothing is saved until
 * the owner saves the post; TinyMCE's undo works as usual.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	createRoot,
	render,
	RawHTML,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../admin/api';
import AiReadiness, { AiOnly } from '../admin/components/AiReadiness';
import CompareTechniques from '../admin/components/persuasion/CompareTechniques';
import RegenerateControl from '../admin/components/regenerate/Regenerate';
import ImagePicker from '../editor/ImagePicker';
import ProductTools from './ProductTools';
import { ProSpots } from '../admin/components/pro/ProPreview';
import './style.scss';

const tiny = () => {
	const editor =
		window.tinymce && window.tinymce.get && window.tinymce.get( 'content' );
	return editor && ! editor.isHidden() ? editor : null;
};

const field = ( id ) => document.getElementById( id );

const context = () => ( {
	post_id: data.postId,
	title: field( 'title' ) ? field( 'title' ).value : '',
	content: tiny()
		? tiny().getContent()
		: ( field( 'content' ) || {} ).value || '',
} );

const setField = ( id, value ) => {
	const input = field( id );
	if ( ! input ) {
		return;
	}
	input.value = value;
	input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	const prompt = field( `${ id }-prompt-text` );
	if ( prompt ) {
		prompt.classList.add( 'screen-reader-text' );
	}
};

const insertHtml = ( html ) => {
	const editor = tiny();
	if ( editor ) {
		editor.execCommand( 'mceInsertContent', false, html );
		return;
	}
	const area = field( 'content' );
	if ( area ) {
		const start = area.selectionStart || area.value.length;
		const end = area.selectionEnd || start;
		area.value =
			area.value.slice( 0, start ) + html + area.value.slice( end );
	}
};

/**
 * Images in the Classic Editor (CR-10): find or create the featured image,
 * or insert an image where the cursor is.
 */
const ClassicImages = () => {
	const [ picker, setPicker ] = useState( '' );
	const [ note, setNote ] = useState( '' );
	const title = () => {
		const input = document.getElementById( 'title' );
		return input ? input.value : '';
	};
	const pick = ( image ) => {
		const mode = picker;
		setPicker( '' );
		if ( ! image || ! image.id ) {
			return;
		}
		if ( 'insert' === mode ) {
			insertHtml(
				`<img src="${ image.url }" alt="${ ( image.alt || '' ).replace(
					/"/g,
					'&quot;'
				) }" class="wp-image-${ image.id }" />`
			);
			return;
		}
		writer( `write/draft/${ data.postId }/image`, {
			method: 'POST',
			data: { spot: 'featured', attachment_id: image.id },
		} )
			.then( () => {
				// Keep the classic form's own field in step, so saving keeps the image.
				const thumb = document.getElementById( '_thumbnail_id' );
				if ( thumb ) {
					thumb.value = String( image.id );
				}
				setNote(
					__(
						'Featured image set. It shows in the Featured image box after you save or reload.',
						'chatbot'
					)
				);
			} )
			.catch( ( err ) => setNote( errorMessage( err ) ) );
	};
	return (
		<div className="qcwr-classic__images">
			<strong>{ __( 'Images', 'chatbot' ) }</strong>
			<div className="qcwr-classic__row">
				<Button
					variant="secondary"
					onClick={ () => setPicker( 'featured' ) }
				>
					{ __(
						'Find or create the featured image',
						'chatbot'
					) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => setPicker( 'insert' ) }
				>
					{ __( 'Insert an image', 'chatbot' ) }
				</Button>
			</div>
			{ note && <p className="qcwr-muted">{ note }</p> }
			{ picker && (
				<ImagePicker
					featured={ 'featured' === picker }
					query={ title() }
					alt={ title() }
					onPick={ pick }
					onClose={ () => setPicker( '' ) }
				/>
			) }
		</div>
	);
};

const ClassicBox = () => {
	const [ titles, setTitles ] = useState( [] );
	const [ seo, setSeo ] = useState( null );
	const [ lab, setLab ] = useState( false );

	// The chosen search title and description travel with the post form.
	useEffect( () => {
		const pending = field( 'qcwr-seo-pending' );
		if ( pending ) {
			pending.value = seo ? JSON.stringify( seo ) : '';
		}
	}, [ seo ] );
	const [ prompt, setPrompt ] = useState( '' );
	const [ mode, setMode ] = useState( 'write' );
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const call = ( key, path, payload ) => {
		setBusy( key );
		setError( '' );
		return writer( path, {
			method: 'POST',
			data: { ...context(), ...payload },
		} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				return null;
			} )
			.finally( () => setBusy( '' ) );
	};

	// CR-14: write, then regenerate with the same or changed options.
	const write = ( regenerate = null ) =>
		call( 'write', 'editor/write-here', {
			prompt,
			mode,
			format: 'html',
			again: !! regenerate,
			regenerate: regenerate || {},
		} ).then( ( body ) => body && setPreview( body ) );
	const suggestTitles = ( regenerate = null ) =>
		call( 'titles', 'editor/titles', {
			again: !! regenerate,
			regenerate: regenerate || {},
			slot: 'titles',
		} ).then( ( body ) => body && setTitles( body.titles ) );

	return (
		<div className="qcwr-classic">
			<AiReadiness
				readiness={ data.readiness }
				health={ data.health }
				newTab
			/>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<AiOnly>
				<div className="qcwr-classic__row">
					{ titles.length ? (
						<RegenerateControl
							kind="titles"
							busy={ 'titles' === busy }
							label={ __( 'More titles', 'chatbot' ) }
							hide={ [ 'length' ] }
							onRegenerate={ suggestTitles }
						/>
					) : (
						<Button
							variant="secondary"
							isBusy={ 'titles' === busy }
							disabled={ !! busy }
							onClick={ () => suggestTitles() }
						>
							{ __( 'Suggest titles', 'chatbot' ) }
						</Button>
					) }
					<Button
						variant="secondary"
						isBusy={ 'excerpt' === busy }
						disabled={ !! busy }
						onClick={ () =>
							call( 'excerpt', 'editor/excerpt', {} ).then(
								( body ) =>
									body && setField( 'excerpt', body.excerpt )
							)
						}
					>
						{ __( 'Write excerpt', 'chatbot' ) }
					</Button>
					{ data.persuasion && data.persuasion.compare && (
						<Button
							variant="tertiary"
							isPressed={ lab }
							onClick={ () => setLab( ! lab ) }
						>
							{ __( 'Headline lab', 'chatbot' ) }
						</Button>
					) }
				</div>
				{ lab && (
					<CompareTechniques
						kind="headline"
						lab
						getInput={ () => {
							const now = context();
							return { ...now, source: now.title };
						} }
						onUse={ ( answer ) => setField( 'title', answer.text ) }
						onClose={ () => setLab( false ) }
					/>
				) }
				{ titles.length > 0 && (
					<ul className="qcwr-classic__titles">
						{ titles.map( ( title ) => (
							<li key={ title }>
								<Button
									variant="link"
									onClick={ () => setField( 'title', title ) }
								>
									{ title }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				{ data.update &&
					data.update.of > 0 &&
					data.update.canPublish && (
						<div className="qcwr-classic__row">
							<Button
								variant="primary"
								isBusy={ 'publish' === busy }
								disabled={ !! busy }
								onClick={ () => {
									setBusy( 'publish' );
									writer(
										`improve/updates/${ data.postId }/publish`,
										{ method: 'POST' }
									)
										.then( ( result ) => {
											window.location.href =
												result.edit_url;
										} )
										.catch( ( err ) => {
											setError( errorMessage( err ) );
											setBusy( '' );
										} );
								} }
							>
								{ __(
									'Publish the update',
									'chatbot'
								) }
							</Button>
							<span className="description">
								{ __(
									'Save the draft first if you edited it.',
									'chatbot'
								) }
							</span>
						</div>
					) }
				<div className="qcwr-classic__row">
					<Button
						variant="secondary"
						isBusy={ 'meta' === busy }
						disabled={ !! busy }
						onClick={ () =>
							call( 'meta', 'editor/meta', {} ).then(
								( body ) =>
									body &&
									setSeo( {
										title: body.meta_title,
										description: body.meta_description,
									} )
							)
						}
					>
						{ __(
							'Search title and description',
							'chatbot'
						) }
					</Button>
				</div>
				{ seo && (
					<div className="qcwr-classic__preview">
						<TextControl
							label={ `${ __( 'Search title', 'chatbot' ) } (${ seo.title.length }/60)` }
							value={ seo.title }
							onChange={ ( title ) =>
								setSeo( { ...seo, title } )
							}
							__nextHasNoMarginBottom
						/>
						<TextareaControl
							label={ `${ __( 'Search description', 'chatbot' ) } (${ seo.description.length }/155)` }
							value={ seo.description }
							onChange={ ( description ) =>
								setSeo( { ...seo, description } )
							}
							rows={ 3 }
							__nextHasNoMarginBottom
						/>
						<p className="description">
							{ sprintf(
								/* translators: %s: SEO plugin name. */
								__(
									'Saved to %s when you save or update.',
									'chatbot'
								),
								data.seo.label
							) }
						</p>
					</div>
				) }
				{ data.woo && data.woo.isProduct && (
					<>
						<hr />
						<ProductTools />
					</>
				) }
				<hr />
				<ClassicImages />
				<hr />
				<strong>{ __( 'Write with AI', 'chatbot' ) }</strong>
				<SelectControl
					label={ __( 'What to do', 'chatbot' ) }
					hideLabelFromVision
					value={ mode }
					options={ [
						{
							value: 'write',
							label: __( 'Write something', 'chatbot' ),
						},
						{
							value: 'continue',
							label: __( 'Continue the post', 'chatbot' ),
						},
					] }
					onChange={ setMode }
					__nextHasNoMarginBottom
				/>
				<TextareaControl
					label={ __( 'What should it write?', 'chatbot' ) }
					hideLabelFromVision
					placeholder={ __(
						'For example: a paragraph about our prices',
						'chatbot'
					) }
					value={ prompt }
					onChange={ setPrompt }
					rows={ 3 }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					isBusy={ 'write' === busy }
					disabled={
						!! busy || ( 'write' === mode && ! prompt.trim() )
					}
					onClick={ () => write() }
				>
					{ __( 'Write it', 'chatbot' ) }
				</Button>
				{ 'write' === busy && <Spinner /> }
				{ preview && (
					<div className="qcwr-classic__preview">
						<RawHTML>{ preview.preview }</RawHTML>
						<div className="qcwr-classic__row">
							<Button
								variant="primary"
								onClick={ () => {
									insertHtml( preview.content );
									setPreview( null );
								} }
							>
								{ __(
									'Insert at the cursor',
									'chatbot'
								) }
							</Button>
							<RegenerateControl
								kind="write-here"
								busy={ 'write' === busy }
								onRegenerate={ write }
							/>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () => setPreview( null ) }
							>
								{ __( 'Discard', 'chatbot' ) }
							</Button>
						</div>
					</div>
				) }
			</AiOnly>
			<ProSpots
				spots={ [
					'editor.internal_links',
					'editor.fact_check',
					'editor.translate',
					'editor.channels',
					'editor.voice',
					'editor.review',
				] }
			/>
		</div>
	);
};

const mount = () => {
	const root = document.getElementById( 'qcwr-classic-root' );
	if ( ! root ) {
		return;
	}
	if ( createRoot ) {
		createRoot( root ).render( <ClassicBox /> );
	} else {
		render( <ClassicBox />, root );
	}
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
