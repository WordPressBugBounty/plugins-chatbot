/**
 * The finished article on the Write page (CR-10): read it formatted, edit
 * it here with WordPress's own editor ("Edit here", saved back as blocks),
 * and fill its images: the featured image and every image place, each with
 * "Create with AI", "Find a stock photo" and "Choose from Media Library".
 * "Open the draft" (in the parent) stays for the full editor.
 */
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { RawHTML, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import ImagePicker from '../../../editor/ImagePicker';
import { toBlocks } from './blocks';

const EDITOR_ID = 'qcwr-article-editor';

const ImageCard = ( { label, image, onChoose } ) => (
	<li className="qcwr-article__image">
		{ image && image.url ? (
			<img src={ image.url } alt="" />
		) : (
			<span className="qcwr-article__empty">
				{ __( 'No image yet', 'chatbot' ) }
			</span>
		) }
		<span className="qcwr-article__label">{ label }</span>
		<span className="qcwr-article__buttons">
			{ data.images && data.images.ai && (
				<Button variant="link" onClick={ () => onChoose( 'ai' ) }>
					{ __( 'Create with AI', 'chatbot' ) }
				</Button>
			) }
			<Button variant="link" onClick={ () => onChoose( '' ) }>
				{ __( 'Find a stock photo', 'chatbot' ) }
			</Button>
			<Button variant="link" onClick={ () => onChoose( 'library' ) }>
				{ __( 'Choose from Media Library', 'chatbot' ) }
			</Button>
		</span>
	</li>
);

const ArticleView = ( { postId } ) => {
	const [ view, setView ] = useState( null );
	const [ editing, setEditing ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ picker, setPicker ] = useState( null );
	// WordPress's editor changes the DOM around its textarea, so it lives in
	// a container React leaves alone.
	const holder = useRef( null );

	useEffect( () => {
		writer( `write/draft/${ postId }` )
			.then( setView )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	}, [ postId ] );

	// WordPress's own editor, started on the article's HTML.
	useEffect( () => {
		const box = holder.current;
		if ( ! editing || ! box ) {
			return undefined;
		}
		const area = document.createElement( 'textarea' );
		area.id = EDITOR_ID;
		area.rows = 20;
		area.value = view.html;
		area.setAttribute( 'aria-label', __( 'Article', 'chatbot' ) );
		box.appendChild( area );
		const old = window.wp && window.wp.oldEditor;
		if ( old ) {
			old.initialize( EDITOR_ID, {
				tinymce: {
					wpautop: true,
					toolbar1:
						'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
				},
				quicktags: true,
				mediaButtons: false,
			} );
		}
		return () => {
			if ( old ) {
				old.remove( EDITOR_ID );
			}
			box.innerHTML = '';
		};
		// Started once per edit; the HTML is read when editing starts.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ editing ] );

	const setImage = ( spot, attachmentId ) => {
		setBusy( true );
		setError( '' );
		return writer( `write/draft/${ postId }/image`, {
			method: 'POST',
			data: { spot: String( spot ), attachment_id: attachmentId },
		} )
			.then( setView )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	const choose = ( spot, tab, label ) => {
		const media = window.wp && window.wp.media;
		if ( 'library' === tab && media ) {
			const frame = media( {
				title: __( 'Choose an image', 'chatbot' ),
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', () => {
				const picked = frame.state().get( 'selection' ).first();
				if ( picked ) {
					setImage( spot, picked.get( 'id' ) );
				}
			} );
			frame.open();
			return;
		}
		setPicker( {
			spot,
			label,
			tab: 'library' === tab ? 'media' : tab,
		} );
	};

	const startEdit = () => {
		setTitle( view.title );
		setEditing( true );
	};

	const save = () => {
		const old = window.wp && window.wp.oldEditor;
		const html = old
			? old.getContent( EDITOR_ID )
			: document.getElementById( EDITOR_ID ).value;
		setBusy( true );
		setError( '' );
		writer( `write/draft/${ postId }`, {
			method: 'POST',
			data: { title, content: toBlocks( html ) },
		} )
			.then( ( next ) => {
				setEditing( false );
				setView( next );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! view ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	return (
		<div className="qcwr-article">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<h3>{ __( 'Images', 'chatbot' ) }</h3>
			{ view.images_note && (
				<Notice status="warning" isDismissible={ false }>
					{ view.images_note }{ ' ' }
					{ data.can && data.can.settings && (
						<a
							href={ `${ data.pageUrl }&tab=settings#qcwr-images` }
						>
							{ __( 'Image settings', 'chatbot' ) }
						</a>
					) }
				</Notice>
			) }
			<ul className="qcwr-article__images">
				<ImageCard
					label={ __( 'Featured image', 'chatbot' ) }
					image={ view.featured }
					onChoose={ ( tab ) =>
						choose( 'featured', tab, view.title )
					}
				/>
				{ view.spots.map( ( spot ) => (
					<ImageCard
						key={ spot.index }
						label={
							spot.label ||
							sprintf(
								/* translators: %d: image number. */
								__( 'Image %d', 'chatbot' ),
								spot.index + 1
							)
						}
						image={ spot.url ? spot : null }
						onChoose={ ( tab ) =>
							choose( spot.index, tab, spot.label )
						}
					/>
				) ) }
			</ul>
			{ busy && <Spinner /> }

			<div className="qcwr-article__head">
				<h3>{ __( 'Your article', 'chatbot' ) }</h3>
				{ ! editing && (
					<Button variant="secondary" onClick={ startEdit }>
						{ __( 'Edit here', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ editing ? (
				<div className="qcwr-article__edit">
					<TextControl
						label={ __( 'Title', 'chatbot' ) }
						value={ title }
						onChange={ setTitle }
						__nextHasNoMarginBottom
					/>
					<div ref={ holder } />
					<div className="qcwr-actions">
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy }
							onClick={ save }
						>
							{ __( 'Save', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ () => setEditing( false ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) : (
				<div className="qcwr-article__body">
					<h2>{ view.title }</h2>
					<RawHTML>{ view.html }</RawHTML>
				</div>
			) }

			{ picker && (
				<ImagePicker
					query={ picker.label }
					alt={ picker.label }
					featured={ 'featured' === picker.spot }
					postId={ postId }
					initialTab={ picker.tab }
					onPick={ ( image ) => {
						const spot = picker.spot;
						setPicker( null );
						if ( ! image || ! image.id ) {
							setError(
								__(
									'This photo cannot be saved to your site. Choose another one.',
									'chatbot'
								)
							);
							return;
						}
						setImage( spot, image.id );
					} }
					onClose={ () => setPicker( null ) }
				/>
			) }
		</div>
	);
};

export default ArticleView;
