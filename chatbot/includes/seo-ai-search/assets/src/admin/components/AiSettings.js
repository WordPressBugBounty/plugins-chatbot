/**
 * AI keys and models for SEO & AI Search (its own copy of the keys).
 *
 * Keys are typed here and sent once; the server only ever returns the last
 * four characters. At most two providers are on (WPBot Core): a primary and
 * an optional secondary used only when the primary fails. Other providers
 * keep their keys and models but stay off.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { core, data, errorMessage, product } from '../api';
import { refreshReadiness, setReadiness } from '../ai';

const OTHER = '__other__';

const ModelPicker = ( { provider, value, onChange } ) => {
	const ids = Object.keys( provider.models || {} ).filter(
		( id ) => provider.models[ id ].text
	);
	const known = ids.includes( value );
	const [ custom, setCustom ] = useState( ! known && value !== '' );
	const options = [
		{ label: __( 'Default', 'chatbot' ), value: '' },
		...ids.map( ( id ) => ( {
			label: provider.models[ id ].label,
			value: id,
		} ) ),
		{ label: __( 'Other model ID…', 'chatbot' ), value: OTHER },
	];
	return (
		<div className="qcseo-model-picker">
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Model', 'chatbot' ) }
				value={ custom ? OTHER : value }
				options={ options }
				onChange={ ( next ) => {
					if ( next === OTHER ) {
						setCustom( true );
						return;
					}
					setCustom( false );
					onChange( next );
				} }
				__nextHasNoMarginBottom
			/>
			{ custom && (
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Model ID', 'chatbot' ) }
					value={ value }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			) }
		</div>
	);
};

const AiSettings = ( { onHealth } ) => {
	const [ view, setView ] = useState( null );
	const [ edits, setEdits ] = useState( {} );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( '' );

	const load = ( next ) => {
		setView( next );
		setEdits( {} );
		if ( next.readiness ) {
			setReadiness( next.readiness );
		}
		if ( onHealth && ! next.readiness ) {
			onHealth( next.health || [] );
		}
	};

	useEffect( () => {
		core( `keys/${ data.module }` )
			.then( load )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			);
		// Load once on mount; `load` only sets state.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( view && window.location.hash.startsWith( '#provider-' ) ) {
		const target = document.getElementById(
			window.location.hash.slice( 1 )
		);
		if ( target && ! target.dataset.scrolled ) {
			target.dataset.scrolled = '1';
			target.scrollIntoView( { block: 'center' } );
		}
	}

	if ( ! view ) {
		return message ? (
			<Notice status={ message.status } isDismissible={ false }>
				{ message.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const edit = ( id, field, value ) =>
		setEdits( {
			...edits,
			[ id ]: { ...( edits[ id ] || {} ), [ field ]: value },
		} );
	const current = ( id, field ) =>
		edits[ id ] && field in edits[ id ]
			? edits[ id ][ field ]
			: view.providers[ id ][ field ];

	const save = () => {
		setBusy( 'save' );
		const providers = {};
		Object.keys( edits ).forEach( ( id ) => {
			providers[ id ] = edits[ id ];
		} );
		core( `keys/${ data.module }`, {
			method: 'POST',
			data: {
				active: view.active || '',
				secondary: view.active ? view.secondary || '' : '',
				budget: view.budget,
				providers,
			},
		} )
			.then( ( next ) => {
				load( next );
				setMessage( {
					status: 'success',
					text: __( 'AI settings saved.', 'chatbot' ),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	// "Turn on" for a provider whose key is saved but which is off: it
	// becomes the primary, or the backup when a primary is already on.
	const turnOn = ( id ) => {
		setBusy( `on-${ id }` );
		const slot = view.active ? { secondary: id } : { active: id };
		core( `keys/${ data.module }`, { method: 'POST', data: slot } )
			.then( ( next ) => {
				load( next );
				setMessage( {
					status: 'success',
					text: sprintf(
						/* translators: %s: AI provider name. */
						__( '%s is turned on.', 'chatbot' ),
						next.providers[ id ].label
					),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const test = ( id ) => {
		setBusy( `test-${ id }` );
		core( `keys/${ data.module }/test`, {
			method: 'POST',
			data: { provider: id },
		} )
			.then( ( result ) => {
				setMessage( {
					status: result.ok ? 'success' : 'error',
					text: result.message,
				} );
				if ( onHealth && ! view.readiness ) {
					onHealth( result.health || [] );
				}
				refreshReadiness().catch( () => {} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	// Round R3: follow AI Settings (default) or keep this feature's own keys.
	const switchSource = ( source ) => {
		setBusy( 'source' );
		core( `keys/${ data.module }`, {
			method: 'POST',
			data: { source },
		} )
			.then( ( next ) => {
				load( next );
				setMessage( {
					status: 'success',
					text:
						'own' === source
							? __(
									'This feature now has its own keys, copied from AI Settings. Change them below.',
									'chatbot'
								)
							: __(
									'This feature now uses AI Settings.',
									'chatbot'
								),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	if ( 'own' !== view.source ) {
		const label = ( id ) =>
			id && view.providers[ id ] ? view.providers[ id ].label : '';
		return (
			<div className="qcseo-card qcseo-ai-settings">
				<h2>{ __( 'AI providers and keys', 'chatbot' ) }</h2>
				{ message && (
					<Notice
						status={ message.status }
						onRemove={ () => setMessage( null ) }
					>
						{ message.text }
					</Notice>
				) }
				<div className="qcseo-ai-settings__follow">
					<p>
						<strong>
							{ sprintf(
								/* translators: %s: plugin name. */
								__(
									'Using %s > AI Settings',
									'chatbot'
								),
								view.source_name || product
							) }
						</strong>
					</p>
					<p className="qcseo-muted">
						{ view.active
							? sprintf(
									/* translators: 1: feature name, 2: AI provider name. */
									__(
										'%1$s uses the keys saved in AI Settings. Main AI: %2$s.',
										'chatbot'
									),
									'SEO & AI Search',
									label( view.active )
								)
							: sprintf(
									/* translators: %s: feature name. */
									__(
										'%s uses the keys saved in AI Settings. No key is saved there yet.',
										'chatbot'
									),
									'SEO & AI Search'
								) }
						{ view.secondary && (
							<>
								{ ' ' }
								{ sprintf(
									/* translators: %s: AI provider name. */
									__( 'Backup: %s.', 'chatbot' ),
									label( view.secondary )
								) }
							</>
						) }
					</p>
					<div className="qcseo-actions">
						{ view.source_url && (
							<Button
								variant="secondary"
								href={ view.source_url }
							>
								{ __( 'Open AI Settings', 'chatbot' ) }
							</Button>
						) }
						<Button
							variant="tertiary"
							isBusy={ busy === 'source' }
							disabled={ !! busy }
							onClick={ () => switchSource( 'own' ) }
						>
							{ sprintf(
								/* translators: %s: feature name. */
								__( 'Use own keys for %s', 'chatbot' ),
								'SEO & AI Search'
							) }
						</Button>
					</div>
				</div>
				<div className="qcseo-ai-settings__general">
					<TextControl
						type="number"
						min={ 0 }
						step={ 1 }
						label={ __(
							'Monthly budget in US dollars (0 = no budget)',
							'chatbot'
						) }
						value={ view.budget }
						onChange={ ( value ) =>
							setView( { ...view, budget: Number( value ) || 0 } )
						}
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="qcseo-actions">
					<Button
						variant="primary"
						isBusy={ busy === 'save' }
						disabled={ !! busy }
						onClick={ () => {
							setBusy( 'save' );
							core( `keys/${ data.module }`, {
								method: 'POST',
								data: { budget: view.budget },
							} )
								.then( ( next ) => {
									load( next );
									setMessage( {
										status: 'success',
										text: __(
											'AI settings saved.',
											'chatbot'
										),
									} );
								} )
								.catch( ( err ) =>
									setMessage( {
										status: 'error',
										text: errorMessage( err ),
									} )
								)
								.finally( () => setBusy( '' ) );
						} }
					>
						{ __( 'Save AI settings', 'chatbot' ) }
					</Button>
				</div>
			</div>
		);
	}

	const providerIds = Object.keys( view.providers );
	const secondary = view.secondary || '';
	const role = ( id ) => {
		if ( id === view.active ) {
			return 'primary';
		}
		return id === secondary ? 'secondary' : 'off';
	};
	const statusLabel = ( id ) => {
		const provider = view.providers[ id ];
		switch ( provider.status ) {
			case 'ready':
				return __( 'Ready', 'chatbot' );
			case 'off':
				return __( 'Key saved, turned off', 'chatbot' );
			case 'failing':
				return sprintf(
					/* translators: %s: reason. */
					__( 'Not working: %s', 'chatbot' ),
					provider.status_reason ||
						__(
							'the saved key can no longer be read',
							'chatbot'
						)
				);
			case 'no_key':
				return __( 'No key', 'chatbot' );
			default:
				return '';
		}
	};
	const roleLabels = {
		primary: __( 'Primary', 'chatbot' ),
		secondary: __( 'Secondary (backup)', 'chatbot' ),
		off: __( 'Off', 'chatbot' ),
	};
	const providerOptions = ( ids ) =>
		ids.map( ( id ) => ( {
			label: view.providers[ id ].label,
			value: id,
		} ) );
	const choosePrimary = ( value ) =>
		setView( {
			...view,
			active: value,
			secondary: value === secondary ? '' : secondary,
		} );
	const imported = view.imported && view.imported.at;

	return (
		<div className="qcseo-card qcseo-ai-settings">
			<h2>{ __( 'AI providers and keys', 'chatbot' ) }</h2>
			<p className="qcseo-muted">
				{ __(
					'SEO & AI Search keeps its own copy of your keys. Keys are stored encrypted and are never shown again after saving.',
					'chatbot'
				) }
				{ view.imported_summary && (
					<>
						{ ' ' }
						<strong>{ view.imported_summary }</strong>
					</>
				) }
				{ ! view.imported_summary &&
					imported &&
					view.imported.providers &&
					view.imported.providers.length > 0 && (
						<>
							{ ' ' }
							{ sprintf(
								/* translators: %s: plugin name. */
								__(
									'Keys were copied once from %s.',
									'chatbot'
								),
								view.imported.from
							) }
						</>
					) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }

			<div className="qcseo-providers">
				{ providerIds.map( ( id ) => {
					const provider = view.providers[ id ];
					return (
						<fieldset
							key={ id }
							id={ `provider-${ id }` }
							className={ `qcseo-provider ${ 'off' !== role( id ) ? 'is-enabled' : '' }` }
						>
							<legend>{ provider.label }</legend>
							<span
								className={ `qcseo-provider__role is-${ role(
									id
								) }` }
							>
								{ roleLabels[ role( id ) ] }
							</span>
							{ provider.status && (
								<p
									className={ `qcseo-provider__status is-${ provider.status }` }
								>
									{ statusLabel( id ) }
									{ 'off' === provider.status &&
										data.can.keys &&
										! ( view.active && view.secondary ) && (
											<>
												{ ' ' }
												<Button
													variant="primary"
													size="compact"
													isBusy={
														busy === `on-${ id }`
													}
													disabled={ !! busy }
													onClick={ () =>
														turnOn( id )
													}
												>
													{ __(
														'Turn on',
														'chatbot'
													) }
												</Button>
											</>
										) }
								</p>
							) }
							{ provider.needs_url && (
								<TextControl
									__next40pxDefaultSize
									label={ __(
										'Endpoint URL (OpenAI-compatible, ending in /v1)',
										'chatbot'
									) }
									type="url"
									value={ current( id, 'url' ) || '' }
									onChange={ ( value ) =>
										edit( id, 'url', value )
									}
									__nextHasNoMarginBottom
								/>
							) }
							<TextControl
								__next40pxDefaultSize
								label={
									provider.needs_key
										? __( 'API key', 'chatbot' )
										: __(
												'API key (optional)',
												'chatbot'
											)
								}
								type="password"
								autoComplete="off"
								placeholder={
									provider.has_key
										? provider.key_hint
										: __(
												'Paste a key',
												'chatbot'
											)
								}
								value={
									( edits[ id ] && edits[ id ].key ) || ''
								}
								onChange={ ( value ) =>
									edit( id, 'key', value )
								}
								help={
									provider.unreadable
										? __(
												'The saved key can no longer be read. Paste it again.',
												'chatbot'
											)
										: ''
								}
								__nextHasNoMarginBottom
							/>
							<ModelPicker
								provider={ provider }
								value={ current( id, 'model' ) || '' }
								onChange={ ( value ) =>
									edit( id, 'model', value )
								}
							/>
							<div className="qcseo-provider__actions">
								<Button
									variant="secondary"
									isBusy={ busy === `test-${ id }` }
									disabled={
										!! busy ||
										( ! provider.has_key &&
											provider.needs_key )
									}
									onClick={ () => test( id ) }
								>
									{ __( 'Test', 'chatbot' ) }
								</Button>
								{ provider.has_key && (
									<CheckboxControl
										label={ __(
											'Remove saved key',
											'chatbot'
										) }
										checked={
											!! (
												edits[ id ] &&
												edits[ id ].clear_key
											)
										}
										onChange={ ( value ) =>
											edit( id, 'clear_key', value )
										}
										__nextHasNoMarginBottom
									/>
								) }
							</div>
						</fieldset>
					);
				} ) }
			</div>

			<div className="qcseo-ai-settings__general">
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Primary provider', 'chatbot' ) }
					value={ view.active || '' }
					options={ [
						{
							label: __( 'None (AI is off)', 'chatbot' ),
							value: '',
						},
						...providerOptions( providerIds ),
					] }
					onChange={ choosePrimary }
					help={ __(
						'All AI work in SEO & AI Search uses this provider. Add its key above.',
						'chatbot'
					) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					__next40pxDefaultSize
					label={ __(
						'Secondary provider (backup)',
						'chatbot'
					) }
					value={ secondary }
					disabled={ ! view.active }
					options={ [
						{ label: __( 'None', 'chatbot' ), value: '' },
						...providerOptions(
							providerIds.filter( ( id ) => id !== view.active )
						),
					] }
					onChange={ ( value ) =>
						setView( { ...view, secondary: value } )
					}
					help={ __(
						'Used only when the primary fails (bad key, no credit, rate limit or outage). Only these two providers are ever on; the others keep their keys but stay off.',
						'chatbot'
					) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					type="number"
					min={ 0 }
					step={ 1 }
					label={ __(
						'Monthly budget in US dollars (0 = no budget)',
						'chatbot'
					) }
					value={ view.budget }
					onChange={ ( value ) =>
						setView( { ...view, budget: Number( value ) || 0 } )
					}
					__nextHasNoMarginBottom
				/>
			</div>

			<div className="qcseo-actions">
				<Button
					variant="primary"
					isBusy={ busy === 'save' }
					disabled={ !! busy }
					onClick={ save }
				>
					{ __( 'Save AI settings', 'chatbot' ) }
				</Button>
				<Button
					variant="tertiary"
					isBusy={ busy === 'source' }
					disabled={ !! busy }
					onClick={ () => switchSource( 'settings' ) }
				>
					{ __( 'Use AI Settings instead', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default AiSettings;
