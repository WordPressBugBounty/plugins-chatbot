/**
 * Images: how drafts get their images, size, and stock image keys.
 */
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { core, data, errorMessage, writer } from '../api';

// Image filling choices (CR-10). Sites keep the choice they saved.
const FILL = () => [
	{
		label: __(
			'Media Library, then stock photos, then AI if nothing is found',
			'chatbot'
		),
		value: 'library_stock_ai',
	},
	{
		label: __(
			'Media Library first, then free stock photos (no AI)',
			'chatbot'
		),
		value: 'auto',
	},
	{
		label: __(
			'AI first, then Media Library, then stock photos',
			'chatbot'
		),
		value: 'ai',
	},
	{
		label: __( 'Placeholders I replace myself', 'chatbot' ),
		value: 'placeholder',
	},
	{ label: __( 'No images', 'chatbot' ), value: 'none' },
];

const ImageSettings = () => {
	const [ settings, setSettings ] = useState( data.settings );
	const [ stock, setStock ] = useState( null );
	const [ keys, setKeys ] = useState( {} );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		if ( data.can.keys ) {
			core( 'connections/stock' )
				.then( setStock )
				.catch( () => {} );
		}
	}, [] );

	const set = ( key, value ) =>
		setSettings( { ...settings, [ key ]: value } );

	const save = () => {
		setBusy( true );
		const requests = [
			writer( 'settings', {
				method: 'POST',
				data: {
					settings: {
						image_fill: settings.image_fill,
						image_fill_featured: settings.image_fill_featured,
						image_provider: settings.image_provider,
						image_max_width: settings.image_max_width,
						image_webp: settings.image_webp,
						upload_limit_mb: settings.upload_limit_mb,
						chatbot_source: settings.chatbot_source,
						cliches: settings.cliches,
					},
				},
			} ).then( ( next ) => {
				data.settings = next;
				setSettings( next );
			} ),
		];
		if ( data.can.keys && Object.keys( keys ).length ) {
			requests.push(
				core( 'connections/stock', {
					method: 'POST',
					data: { services: keys },
				} ).then( ( next ) => {
					setStock( next );
					setKeys( {} );
				} )
			);
		}
		Promise.all( requests )
			.then( () =>
				setMessage( {
					status: 'success',
					text: __( 'Saved.', 'chatbot' ),
				} )
			)
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-card" id="qcwr-images">
			<h2>{ __( 'Images and sources', 'chatbot' ) }</h2>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<div className="qcwr-grid">
				<SelectControl
					label={ __( 'Images in the article', 'chatbot' ) }
					value={ settings.image_fill }
					options={ FILL() }
					onChange={ ( value ) => set( 'image_fill', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Header (featured) image', 'chatbot' ) }
					value={ settings.image_fill_featured || 'same' }
					options={ [
						{
							label: __(
								'Same as the article images',
								'chatbot'
							),
							value: 'same',
						},
						...FILL(),
					] }
					onChange={ ( value ) =>
						set( 'image_fill_featured', value )
					}
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'AI images are made with', 'chatbot' ) }
					help={ __(
						'Any saved OpenAI or Gemini key works, even when it is not one of your two text providers.',
						'chatbot'
					) }
					value={ settings.image_provider || '' }
					options={ [
						{
							label: __(
								'OpenAI or Gemini (the first saved key)',
								'chatbot'
							),
							value: '',
						},
						{ label: 'OpenAI', value: 'openai' },
						{ label: 'Google Gemini', value: 'gemini' },
					] }
					onChange={ ( value ) => set( 'image_provider', value ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="number"
					label={ __(
						'Largest image width (pixels)',
						'chatbot'
					) }
					value={ settings.image_max_width }
					onChange={ ( value ) =>
						set( 'image_max_width', Number( value ) || 0 )
					}
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="number"
					label={ __(
						'Largest document upload (MB)',
						'chatbot'
					) }
					value={ settings.upload_limit_mb }
					onChange={ ( value ) =>
						set( 'upload_limit_mb', Number( value ) || 0 )
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="qcwr-toggles">
				<ToggleControl
					label={ __(
						'Save images as WebP when the server supports it',
						'chatbot'
					) }
					checked={ !! settings.image_webp }
					onChange={ ( value ) => set( 'image_webp', value ) }
					__nextHasNoMarginBottom
				/>
				{ data.hasChatbot !== false && (
					<ToggleControl
						label={ __(
							'Use ChatBot knowledge as a source by default',
							'chatbot'
						) }
						checked={ !! settings.chatbot_source }
						onChange={ ( value ) => set( 'chatbot_source', value ) }
						__nextHasNoMarginBottom
					/>
				) }
			</div>
			{ data.can.keys && stock && (
				<>
					<h3>
						{ __( 'Stock photo keys (free)', 'chatbot' ) }
					</h3>
					<p className="qcwr-muted">
						{ __(
							'Openverse works without a key. Pexels and Pixabay give more choice with a free key.',
							'chatbot'
						) }
					</p>
					<div className="qcwr-grid">
						{ [
							[ 'pexels', 'Pexels' ],
							[ 'pixabay', 'Pixabay' ],
							[ 'unsplash', 'Unsplash' ],
						].map( ( [ id, label ] ) => (
							<TextControl
								key={ id }
								type="password"
								autoComplete="off"
								label={ `${ label } API key` }
								placeholder={
									stock[ id ].has_key
										? stock[ id ].key_hint
										: __( 'Paste a key', 'chatbot' )
								}
								value={
									keys[ id ] && keys[ id ].key
										? keys[ id ].key
										: ''
								}
								onChange={ ( value ) =>
									setKeys( {
										...keys,
										[ id ]: {
											...( keys[ id ] || {} ),
											key: value,
										},
									} )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					<ToggleControl
						label={ __(
							'Use Unsplash in the image panel',
							'chatbot'
						) }
						help={ __(
							'Off by default. Unsplash photos are shown from Unsplash (not copied to your site), always keep the photographer credit, are not resized, and cannot be the featured image. Needs your own free Unsplash key above.',
							'chatbot'
						) }
						checked={
							keys.unsplash && undefined !== keys.unsplash.enabled
								? keys.unsplash.enabled
								: !! stock.unsplash.enabled
						}
						onChange={ ( enabled ) =>
							setKeys( {
								...keys,
								unsplash: {
									...( keys.unsplash || {} ),
									enabled,
								},
							} )
						}
						__nextHasNoMarginBottom
					/>
				</>
			) }
			<h3>{ __( 'Phrases that sound like AI', 'chatbot' ) }</h3>
			<p className="qcwr-muted">
				{ __(
					'The AI-pattern check flags these. One per line.',
					'chatbot'
				) }
			</p>
			<textarea
				className="qcwr-textarea"
				rows={ 6 }
				aria-label={ __(
					'Phrases that sound like AI',
					'chatbot'
				) }
				value={ ( settings.cliches || [] ).join( '\n' ) }
				onChange={ ( event ) =>
					set( 'cliches', event.target.value.split( '\n' ) )
				}
			/>
			<div className="qcwr-actions">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ save }
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default ImageSettings;
