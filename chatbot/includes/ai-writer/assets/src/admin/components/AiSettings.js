/**
 * AI keys and models for AI Writer (its own copy of the keys).
 *
 * Keys are typed here and sent once; the server only ever returns the last
 * four characters. At most two providers are on: a primary and an optional
 * secondary used only when the primary fails. Other providers keep their
 * keys but stay off.
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
import { core, data, errorMessage } from '../api';

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
		<div className="qcwr-model-picker">
			<SelectControl
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
		if ( onHealth ) {
			onHealth( next.health || [] );
		}
	};

	// The readiness notice links to #provider-{id}: show that card.
	useEffect( () => {
		const hash = window.location.hash || '';
		if ( ! view || 0 !== hash.indexOf( '#provider-' ) ) {
			return;
		}
		const card = document.getElementById( hash.slice( 1 ) );
		if ( card ) {
			card.scrollIntoView( { block: 'center' } );
			card.classList.add( 'is-highlighted' );
		}
	}, [ view ] );

	useEffect( () => {
		core( `keys/${ data.module }` )
			.then( load )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			);
		// Load once on mount; `load` only sets state.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

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
				active: view.active,
				secondary: view.secondary || '',
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
				if ( onHealth ) {
					onHealth( result.health || [] );
				}
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const reimport = () => {
		// eslint-disable-next-line no-alert -- A native confirm is enough for this rare action.
		const confirmed = window.confirm(
			__(
				'Copy the keys and models from the main WPBot AI settings again? Keys you added here for those providers will be replaced.',
				'chatbot'
			)
		);
		if ( ! confirmed ) {
			return;
		}
		setBusy( 'import' );
		core( `keys/${ data.module }/import`, { method: 'POST' } )
			.then( ( next ) => {
				load( next );
				setMessage( {
					status: 'success',
					text: __(
						'Keys copied from the main AI settings.',
						'chatbot'
					),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const providerIds = Object.keys( view.providers );
	const imported = view.imported && view.imported.at;
	const secondary = view.secondary || '';
	const role = ( id ) => {
		if ( id === view.active ) {
			return 'primary';
		}
		return id === secondary ? 'secondary' : 'off';
	};
	const roleLabels = {
		primary: __( 'Primary', 'chatbot' ),
		secondary: __( 'Secondary (backup)', 'chatbot' ),
		off: __( 'Off', 'chatbot' ),
	};
	const providerOption = ( id ) => {
		const provider = view.providers[ id ];
		const keyless =
			provider.needs_key &&
			! provider.has_key &&
			! ( edits[ id ] && edits[ id ].key );
		return {
			label: keyless
				? sprintf(
						/* translators: %s: AI provider name. */
						__( '%s (no key yet)', 'chatbot' ),
						provider.label
					)
				: provider.label,
			value: id,
		};
	};
	// "Turn on": the first free place (primary, then backup).
	const turnOn = ( id ) => {
		if ( ! view.active ) {
			setView( { ...view, active: id } );
		} else if ( ! secondary && id !== view.active ) {
			setView( { ...view, secondary: id } );
		}
	};
	const statusLine = ( id, provider ) => {
		const status = provider.status || '';
		if ( 'ready' === status ) {
			return {
				status,
				text: __( 'Ready', 'chatbot' ),
			};
		}
		if ( 'failing' === status ) {
			return {
				status,
				text: sprintf(
					/* translators: %s: why the provider fails. */
					__( 'Not working: %s', 'chatbot' ),
					provider.status_reason ||
						__( 'the last request failed', 'chatbot' )
				),
			};
		}
		if ( 'off' === status ) {
			return {
				status,
				text: __( 'Key saved, turned off', 'chatbot' ),
			};
		}
		if ( 'no_key' === status ) {
			return { status, text: __( 'No key', 'chatbot' ) };
		}
		return null;
	};
	const choosePrimary = ( value ) =>
		setView( {
			...view,
			active: value,
			secondary: value === secondary ? '' : secondary,
		} );

	return (
		<div className="qcwr-card qcwr-ai-settings">
			<h2>{ __( 'AI providers and keys', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'AI Writer keeps its own copy of your keys. Keys are stored encrypted and are never shown again after saving.',
					'chatbot'
				) }
				{ imported &&
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

			<div className="qcwr-ai-settings__general">
				<SelectControl
					label={ __( 'Primary provider', 'chatbot' ) }
					value={ view.active }
					options={ [
						{
							label: __( 'None (AI is off)', 'chatbot' ),
							value: '',
						},
						...providerIds.map( providerOption ),
					] }
					onChange={ choosePrimary }
					help={ __(
						'Every AI request goes to this provider.',
						'chatbot'
					) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __(
						'Secondary provider (backup)',
						'chatbot'
					) }
					value={ secondary }
					disabled={ ! view.active }
					options={ [
						{
							label: __( 'None', 'chatbot' ),
							value: '',
						},
						...providerIds
							.filter( ( id ) => id !== view.active )
							.map( providerOption ),
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

			<div className="qcwr-providers">
				{ providerIds.map( ( id ) => {
					const provider = view.providers[ id ];
					const line = statusLine( id, provider );
					return (
						<fieldset
							key={ id }
							id={ `provider-${ id }` }
							className={ `qcwr-provider ${ 'off' !== role( id ) ? 'is-enabled' : '' }` }
						>
							<legend>
								{ provider.label }{ ' ' }
								<span
									className={ `qcwr-provider__role is-${ role(
										id
									) }` }
								>
									{ roleLabels[ role( id ) ] }
								</span>
							</legend>
							{ line && (
								<p
									className={ `qcwr-provider__status is-${ line.status }` }
								>
									{ line.text }
									{ 'off' === line.status &&
										'off' === role( id ) &&
										( ! view.active || ! secondary ) && (
											<>
												{ ' ' }
												<Button
													variant="link"
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
									{ 'off' === line.status &&
										'off' !== role( id ) && (
											<>
												{ ' · ' }
												{ __(
													'Save to turn it on.',
													'chatbot'
												) }
											</>
										) }
								</p>
							) }
							{ provider.needs_url && (
								<TextControl
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
										: __( 'Paste a key', 'chatbot' )
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
							<div className="qcwr-provider__actions">
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

			<div className="qcwr-actions">
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
					isBusy={ busy === 'import' }
					disabled={ !! busy }
					onClick={ reimport }
				>
					{ __(
						'Copy from main WPBot AI settings again',
						'chatbot'
					) }
				</Button>
			</div>
		</div>
	);
};

export default AiSettings;
