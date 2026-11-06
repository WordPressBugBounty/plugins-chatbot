/**
 * Image panel: replace an image (or choose the featured image) from Stock,
 * Openverse, Unsplash (when the admin turned it on), the Media Library,
 * an upload, AI, or a graphic template.
 *
 * Unsplash images are hotlinked with the credit, as Unsplash's guidelines
 * require, and are never offered for the featured image.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	FormFileUpload,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage } from '../admin/api';
import { draw, upload } from './graphics';
import { useRequest } from './utils';

const escapeHtml = ( text ) =>
	String( text ).replace(
		/[&<>"']/g,
		( char ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ char ]
	);

const safeUrl = ( url ) => ( /^https:\/\//.test( url ) ? url : '' );

/**
 * Caption with links for an Unsplash photo (required credit).
 *
 * @param {Object} photo Candidate.
 * @return {string} Caption HTML.
 */
const unsplashCredit = ( photo ) =>
	`${ __( 'Photo by', 'chatbot' ) } <a href="${ escapeHtml( safeUrl( photo.credit_url ) ) }">${ escapeHtml( photo.credit ) }</a> ${ __( 'on', 'chatbot' ) } <a href="${ escapeHtml( safeUrl( photo.unsplash_url ) ) }">Unsplash</a>`;

const SearchTab = ( { source, query: initial, onChoose, busy, postId } ) => {
	const [ query, setQuery ] = useState( initial || '' );
	const [ results, setResults ] = useState( null );
	const request = useRequest();
	const search = () =>
		request
			.run( 'search', 'images/search', {
				source,
				query,
				post_id: postId || data.postId,
			} )
			.then( ( body ) => body && setResults( body.results ) );
	useEffect( () => {
		if ( initial ) {
			search();
		}
		// Search once on open with the suggested words.
	}, [] );

	return (
		<div className="qcwr-images__tab">
			<div className="qcwr-actions">
				<TextControl
					label={ __( 'Search', 'chatbot' ) }
					hideLabelFromVision
					value={ query }
					onChange={ setQuery }
					onKeyDown={ ( event ) => 'Enter' === event.key && search() }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					onClick={ search }
					isBusy={ !! request.busy }
				>
					{ __( 'Search', 'chatbot' ) }
				</Button>
			</div>
			{ request.error && (
				<Notice status="warning" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ request.busy && <Spinner /> }
			{ results && ! results.length && ! request.busy && (
				<p>
					{ __(
						'Nothing found. Try other words.',
						'chatbot'
					) }
				</p>
			) }
			<div className="qcwr-images__grid">
				{ ( results || [] ).map( ( item, index ) => (
					<button
						type="button"
						key={ `${ item.url }-${ index }` }
						className="qcwr-images__item"
						disabled={ busy }
						onClick={ () => onChoose( item ) }
					>
						<img
							src={ item.thumb || item.url }
							alt={ item.alt || '' }
						/>
						{ item.credit && <span>{ item.credit }</span> }
					</button>
				) ) }
			</div>
		</div>
	);
};

const AiTab = ( { alt, onDone, postId } ) => {
	const [ prompt, setPrompt ] = useState( alt || '' );
	const request = useRequest();
	return (
		<div className="qcwr-images__tab">
			<TextareaControl
				label={ __( 'Describe the image', 'chatbot' ) }
				value={ prompt }
				onChange={ setPrompt }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			<Button
				variant="primary"
				isBusy={ !! request.busy }
				disabled={ !! request.busy || ! prompt.trim() }
				onClick={ () =>
					request
						.run( 'ai', 'images/ai', {
							prompt,
							alt: prompt,
							post_id: postId || data.postId,
						} )
						.then( ( body ) => body && onDone( body ) )
				}
			>
				{ __( 'Create image (uses your AI key)', 'chatbot' ) }
			</Button>
		</div>
	);
};

const UploadTab = ( { onDone } ) => {
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	return (
		<div className="qcwr-images__tab">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<FormFileUpload
				accept="image/*"
				onChange={ ( event ) => {
					const file = event.target.files[ 0 ];
					if ( ! file ) {
						return;
					}
					const form = new window.FormData();
					form.append( 'file', file, file.name );
					setBusy( true );
					apiFetch( {
						path: '/wp/v2/media',
						method: 'POST',
						body: form,
					} )
						.then( ( media ) =>
							onDone( {
								id: media.id,
								url: media.source_url,
								alt: media.alt_text || '',
								caption: '',
							} )
						)
						.catch( ( err ) => setError( errorMessage( err ) ) )
						.finally( () => setBusy( false ) );
				} }
				render={ ( { openFileDialog } ) => (
					<Button
						variant="secondary"
						isBusy={ busy }
						onClick={ openFileDialog }
					>
						{ __( 'Choose a file', 'chatbot' ) }
					</Button>
				) }
			/>
		</div>
	);
};

const GraphicTab = ( { title, onDone } ) => {
	const canvas = useRef();
	const [ kind, setKind ] = useState( 'title' );
	const [ text, setText ] = useState( title || '' );
	const [ items, setItems ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const list = items
		.split( '\n' )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

	useEffect( () => {
		if ( canvas.current ) {
			draw(
				canvas.current,
				kind,
				{ text, items: list, footer: data.brand.name },
				data.brand
			);
		}
	} );

	return (
		<div className="qcwr-images__tab">
			<SelectControl
				label={ __( 'Card', 'chatbot' ) }
				value={ kind }
				options={ [
					{
						value: 'title',
						label: __( 'Title card', 'chatbot' ),
					},
					{
						value: 'quote',
						label: __( 'Quote card', 'chatbot' ),
					},
					{
						value: 'list',
						label: __( 'List card', 'chatbot' ),
					},
				] }
				onChange={ setKind }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={
					'quote' === kind
						? __( 'Quote', 'chatbot' )
						: __( 'Heading', 'chatbot' )
				}
				value={ text }
				onChange={ setText }
				__nextHasNoMarginBottom
			/>
			{ 'list' === kind && (
				<TextareaControl
					label={ __( 'Points (one per line)', 'chatbot' ) }
					value={ items }
					onChange={ setItems }
					rows={ 4 }
					__nextHasNoMarginBottom
				/>
			) }
			<canvas ref={ canvas } className="qcwr-images__canvas" />
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy || ! text.trim() }
				onClick={ () => {
					setBusy( true );
					upload( canvas.current, text, text )
						.then( ( media ) =>
							onDone( {
								id: media.id,
								url: media.source_url,
								alt: text,
								caption: '',
							} )
						)
						.catch( ( err ) => setError( errorMessage( err ) ) )
						.finally( () => setBusy( false ) );
				} }
			>
				{ __( 'Save to Media Library and use', 'chatbot' ) }
			</Button>
		</div>
	);
};

/**
 * @param {Object}   props
 * @param {string}   props.query      Suggested search words.
 * @param {string}   props.alt        Current alt text.
 * @param {boolean}  props.featured   Choosing the featured image.
 * @param {Function} props.onPick     Receives { id, url, alt, caption, hotlink }.
 * @param {Function} props.onRemove   Remove the image (block only).
 * @param {Function} props.onClose    Close.
 * @param {number}   props.postId     Post the image is for (default: the post being edited).
 * @param {string}   props.initialTab Tab to open first (e.g. 'ai', 'media').
 */
const ImagePicker = ( {
	query,
	alt,
	featured,
	onPick,
	onRemove,
	onClose,
	postId = 0,
	initialTab = '',
} ) => {
	const request = useRequest();
	const tabs = [
		...data.images.stock
			.filter( ( source ) => 'openverse' !== source )
			.map( ( source ) => ( {
				name: source,
				title: 'pexels' === source ? 'Pexels' : 'Pixabay',
			} ) ),
		{ name: 'openverse', title: 'Openverse' },
		...( data.images.unsplash && ! featured
			? [ { name: 'unsplash', title: 'Unsplash' } ]
			: [] ),
		{ name: 'media', title: __( 'Media Library', 'chatbot' ) },
		...( data.can.upload
			? [
					{
						name: 'upload',
						title: __( 'Upload', 'chatbot' ),
					},
					{
						name: 'ai',
						title: __( 'Create with AI', 'chatbot' ),
					},
					{
						name: 'graphic',
						title: __( 'Graphic templates', 'chatbot' ),
					},
				]
			: [] ),
	];

	const choose = ( item ) => {
		if ( item.hotlink ) {
			// Unsplash: hotlink, credit with links, and report the use.
			request.run( 'track', 'images/unsplash', {
				download: item.download,
			} );
			onPick( {
				id: 0,
				url: item.url,
				alt: item.alt || alt || query || '',
				caption: unsplashCredit( item ),
				hotlink: true,
			} );
			return;
		}
		request
			.run( 'import', 'images/import', {
				candidate: item,
				alt: alt || query || '',
				post_id: postId || data.postId,
			} )
			.then( ( body ) => body && onPick( body ) );
	};

	return (
		<Modal
			title={
				featured
					? __( 'Featured image', 'chatbot' )
					: __( 'Replace image', 'chatbot' )
			}
			onRequestClose={ onClose }
			className="qcwr-images"
			size="large"
		>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ 'import' === request.busy && (
				<p>
					<Spinner />{ ' ' }
					{ __(
						'Adding the image to your Media Library…',
						'chatbot'
					) }
				</p>
			) }
			<TabPanel
				tabs={ tabs }
				initialTabName={
					tabs.some( ( tab ) => tab.name === initialTab )
						? initialTab
						: undefined
				}
			>
				{ ( tab ) => {
					if ( 'upload' === tab.name ) {
						return <UploadTab onDone={ onPick } />;
					}
					if ( 'ai' === tab.name ) {
						return (
							<AiTab
								alt={ alt || query }
								onDone={ onPick }
								postId={ postId }
							/>
						);
					}
					if ( 'graphic' === tab.name ) {
						return <GraphicTab title={ query } onDone={ onPick } />;
					}
					return (
						<SearchTab
							key={ tab.name }
							source={ tab.name }
							query={ query }
							busy={ !! request.busy }
							onChoose={ choose }
							postId={ postId }
						/>
					);
				} }
			</TabPanel>
			{ data.images.unsplash && ! featured && (
				<p className="qcwr-muted">
					{ __(
						'Unsplash photos stay on Unsplash (they are not copied to your site) and keep their credit.',
						'chatbot'
					) }
				</p>
			) }
			{ onRemove && (
				<Button variant="tertiary" isDestructive onClick={ onRemove }>
					{ __( 'Remove image', 'chatbot' ) }
				</Button>
			) }
		</Modal>
	);
};

export default ImagePicker;
