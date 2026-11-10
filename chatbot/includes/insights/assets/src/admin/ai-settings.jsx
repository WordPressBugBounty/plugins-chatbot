/* global wp */
import { api } from './api';
import { dateTime } from './format';
import { Icon } from './icons';
import { Toggle, NumberField, Section } from './fields';

const { createElement, useState } = wp.element;
const { __, sprintf } = wp.i18n;

const FEATURE_LABELS = {
	summary: () => __( 'Session summaries', 'qcin-textdomain' ),
	test: () => __( 'Connection tests', 'qcin-textdomain' ),
};

const num = ( n ) => Number( n || 0 ).toLocaleString();

/**
 * Editable copy of the AI settings. Keys are never sent to the browser:
 * `key` holds a newly typed key only, `clear_key` removes the saved one.
 *
 * @param {Object} payload Response of GET /ai.
 * @return {Object} Form state.
 */
export function aiFormFrom( payload ) {
	const s = ( payload && payload.settings ) || {};
	const providers = {};
	Object.keys( s.providers || {} ).forEach( ( id ) => {
		providers[ id ] = { model: s.providers[ id ].model || '', key: '', clear_key: false, url: s.providers[ id ].url || '' };
	} );
	return {
		enabled: !! s.enabled,
		provider: s.provider || 'openai',
		providers,
		features: { ...( s.features || {} ) },
		limits: { ...( s.limits || {} ) },
	};
}

/**
 * What to POST to /ai (only keys that were typed or cleared).
 *
 * @param {Object} form AI form state.
 * @return {Object} Patch.
 */
export function aiPatch( form ) {
	const providers = {};
	Object.keys( form.providers ).forEach( ( id ) => {
		const p = form.providers[ id ];
		providers[ id ] = { model: p.model };
		if ( p.url !== undefined && p.url !== '' ) {
			providers[ id ].url = p.url.trim();
		}
		if ( p.clear_key ) {
			providers[ id ].clear_key = true;
		} else if ( p.key.trim() ) {
			providers[ id ].key = p.key.trim();
		}
	} );
	const limits = {};
	Object.keys( form.limits ).forEach( ( k ) => ( limits[ k ] = parseInt( form.limits[ k ], 10 ) || 0 ) );
	return { enabled: form.enabled, provider: form.provider, providers, features: form.features, limits };
}

function UsagePanel( { usage, limits, featureList } ) {
	const labelOf = ( f ) => {
		const def = ( featureList || [] ).find( ( x ) => x.id === f );
		if ( FEATURE_LABELS[ f ] ) {
			return FEATURE_LABELS[ f ]();
		}
		return def ? def.label : f;
	};
	if ( ! usage ) {
		return null;
	}
	const dayLimit = parseInt( limits.daily_tokens, 10 ) || 0;
	const monthLimit = parseInt( limits.monthly_tokens, 10 ) || 0;
	const pct = ( used, limit ) => ( limit ? Math.min( 100, Math.round( ( used / limit ) * 100 ) ) : 0 );
	const features = Object.keys( usage.features || {} );
	return (
		<div className="qcin-ai-usage">
			<h3>{ __( 'Usage', 'qcin-textdomain' ) }</h3>
			{ usage.paused && <div className="qcin-notice qcin-notice--error">{ usage.paused }</div> }
			<div className="qcin-grid-2">
				<div>
					<div className="qcin-ai-usage__label">
						{ __( 'Today', 'qcin-textdomain' ) }
						<span className="qcin-muted">
							{ sprintf(
								/* translators: 1: tokens used, 2: number of requests */
								__( '%1$s tokens · %2$s requests', 'qcin-textdomain' ),
								num( usage.day.tokens ),
								num( usage.day.requests )
							) }
						</span>
					</div>
					{ dayLimit > 0 && (
						<div className="qcin-usage__bar" title={ pct( usage.day.tokens, dayLimit ) + '%' }>
							<span style={ { width: pct( usage.day.tokens, dayLimit ) + '%' } } />
						</div>
					) }
				</div>
				<div>
					<div className="qcin-ai-usage__label">
						{ usage.month.label }
						<span className="qcin-muted">
							{ sprintf(
								/* translators: 1: tokens used, 2: number of requests */
								__( '%1$s tokens · %2$s requests', 'qcin-textdomain' ),
								num( usage.month.tokens ),
								num( usage.month.requests )
							) }
						</span>
					</div>
					{ monthLimit > 0 && (
						<div className="qcin-usage__bar" title={ pct( usage.month.tokens, monthLimit ) + '%' }>
							<span style={ { width: pct( usage.month.tokens, monthLimit ) + '%' } } />
						</div>
					) }
				</div>
			</div>
			{ features.length > 0 && (
				<table className="qcin-table qcin-table--compact">
					<thead>
						<tr>
							<th scope="col">{ sprintf(
								/* translators: %s: month and year, e.g. "October 2026" */
								__( 'By feature, %s', 'qcin-textdomain' ),
								usage.month.label
							) }</th>
							<th scope="col" className="qcin-num">{ __( 'Requests', 'qcin-textdomain' ) }</th>
							<th scope="col" className="qcin-num">{ __( 'Tokens in', 'qcin-textdomain' ) }</th>
							<th scope="col" className="qcin-num">{ __( 'Tokens out', 'qcin-textdomain' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ features.map( ( f ) => (
							<tr key={ f }>
								<td>{ labelOf( f ) }</td>
								<td className="qcin-num">{ num( usage.features[ f ].requests ) }</td>
								<td className="qcin-num">{ num( usage.features[ f ].input ) }</td>
								<td className="qcin-num">{ num( usage.features[ f ].output ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}

export function AiSection( { data, form, setForm, onData, notify } ) {
	const [ testing, setTesting ] = useState( false );
	const [ test, setTest ] = useState( null );
	const [ copying, setCopying ] = useState( false );

	if ( ! data || ! form ) {
		return (
			<Section id="qcin-ai" title={ __( 'AI', 'qcin-textdomain' ) }>
				<div className="qcin-skeleton" aria-busy="true" />
			</Section>
		);
	}

	const s = data.settings;
	const id = form.provider;
	const saved = s.providers[ id ] || {};
	const p = form.providers[ id ] || { model: '', key: '', clear_key: false, url: '' };
	const host = data.hostName || __( 'the plugin', 'qcin-textdomain' );
	const hasKey = saved.hasKey && ! p.clear_key;
	const needsKey = saved.needsKey !== false;
	const needsUrl = !! saved.needsUrl;
	const canTest = needsUrl ? !! ( p.url || '' ).trim() : hasKey || !! p.key.trim();

	const setProvider = ( patch ) => {
		setTest( null );
		setForm( { ...form, providers: { ...form.providers, [ id ]: { ...p, ...patch } } } );
	};

	const runTest = () => {
		setTesting( true );
		setTest( null );
		api.post( '/ai/test', { provider: id, key: p.clear_key ? '' : p.key.trim(), model: p.model.trim(), url: ( p.url || '' ).trim() } )
			.then( setTest )
			.catch( ( e ) => setTest( { ok: false, message: ( e && e.message ) || __( 'The test failed.', 'qcin-textdomain' ) } ) )
			.finally( () => setTesting( false ) );
	};

	const copyAgain = () => {
		const ok = window.confirm( // eslint-disable-line no-alert
			sprintf(
				/* translators: %s: host plugin name, e.g. "WPBot" */
				__( 'Copy the API keys and models from %s AI Settings again? This replaces the keys and models saved here for those providers, and selects the provider %s uses. Your other AI settings stay as they are.', 'qcin-textdomain' ),
				host,
				host
			)
		);
		if ( ! ok ) {
			return;
		}
		setCopying( true );
		api.post( '/ai/import' )
			.then( ( r ) => {
				onData( r, true );
				setTest( null );
				notify(
					sprintf(
						/* translators: %s: host plugin name, e.g. "WPBot" */
						__( 'Copied from %s AI Settings.', 'qcin-textdomain' ),
						host
					),
					'success'
				);
			} )
			.catch( ( e ) => notify( ( e && e.message ) || __( 'Could not copy the AI settings.', 'qcin-textdomain' ), 'error' ) )
			.finally( () => setCopying( false ) );
	};

	const imported = s.imported;
	const importedNames = imported && imported.providers ? imported.providers.map( ( pid ) => ( s.providers[ pid ] ? s.providers[ pid ].label : pid ) ).join( ', ' ) : '';
	const listId = 'qcin-ai-models-' + id;

	return (
		<Section
			id="qcin-ai"
			title={ __( 'AI', 'qcin-textdomain' ) }
			desc={ __( 'AI features use your own API key. Each feature below says when it runs. Keys stay on this server; only their last 4 characters are shown here.', 'qcin-textdomain' ) }
		>
			<Toggle
				id="qcin-ai-enabled"
				checked={ form.enabled }
				onChange={ ( v ) => setForm( { ...form, enabled: v } ) }
				label={ __( 'Enable AI features', 'qcin-textdomain' ) }
				help={ __( 'When off, nothing is ever sent to an AI provider.', 'qcin-textdomain' ) }
			/>

			{ ( imported || data.canImport ) && (
				<div className="qcin-ai-import">
					<Icon.copy size={ 18 } />
					<div>
						{ imported ? (
							<p>
								{ sprintf(
									/* translators: 1: host plugin name, 2: date, 3: provider names */
									__( 'Copied from %1$s AI Settings on %2$s (%3$s).', 'qcin-textdomain' ),
									imported.from || host,
									dateTime( imported.at ),
									importedNames
								) }{ ' ' }
								{ sprintf(
									/* translators: %s: host plugin name */
									__( 'Changes made in %s later do not change these settings.', 'qcin-textdomain' ),
									imported.from || host
								) }
							</p>
						) : (
							<p>
								{ sprintf(
									/* translators: %s: host plugin name */
									__( '%s AI Settings have API keys you can use here.', 'qcin-textdomain' ),
									host
								) }
							</p>
						) }
						{ data.canImport ? (
							<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" onClick={ copyAgain } disabled={ copying }>
								{ copying
									? __( 'Copying…', 'qcin-textdomain' )
									: sprintf(
										/* translators: %s: host plugin name */
										imported ? __( 'Copy again from %s AI Settings', 'qcin-textdomain' ) : __( 'Copy from %s AI Settings', 'qcin-textdomain' ),
										host
									) }
							</button>
						) : (
							<p className="qcin-help">
								{ sprintf(
									/* translators: %s: host plugin name */
									__( '%s AI Settings have no API keys right now.', 'qcin-textdomain' ),
									host
								) }
							</p>
						) }
					</div>
				</div>
			) }

			<div className="qcin-grid-2">
				<div className="qcin-field">
					<label htmlFor="qcin-ai-provider">{ __( 'Provider', 'qcin-textdomain' ) }</label>
					<select
						id="qcin-ai-provider"
						value={ id }
						onChange={ ( e ) => {
							setTest( null );
							setForm( { ...form, provider: e.target.value } );
						} }
					>
						{ Object.keys( s.providers ).map( ( pid ) => (
							<option key={ pid } value={ pid }>
								{ s.providers[ pid ].label }
								{ s.providers[ pid ].hasKey ? ' · ' + __( 'key saved', 'qcin-textdomain' ) : '' }
							</option>
						) ) }
					</select>
				</div>
				<div className="qcin-field">
					<label htmlFor="qcin-ai-model">{ __( 'Model', 'qcin-textdomain' ) }</label>
					<input id="qcin-ai-model" type="text" list={ listId } value={ p.model } spellCheck="false" autoComplete="off" onChange={ ( e ) => setProvider( { model: e.target.value } ) } />
					<datalist id={ listId }>
						{ ( saved.models || [] ).map( ( m ) => (
							<option key={ m } value={ m } />
						) ) }
					</datalist>
					<p className="qcin-help">{ __( 'Pick a suggestion or type any model your account can use. Smaller models cost less and are enough for summaries.', 'qcin-textdomain' ) }</p>
				</div>
			</div>

			{ needsUrl && (
				<div className="qcin-field">
					<label htmlFor="qcin-ai-url">
						{ sprintf(
							/* translators: %s: provider name, e.g. "Ollama" */
							__( '%s server address', 'qcin-textdomain' ),
							saved.label || id
						) }
					</label>
					<input id="qcin-ai-url" type="url" value={ p.url || '' } spellCheck="false" autoComplete="off" placeholder="http://localhost:11434" onChange={ ( e ) => setProvider( { url: e.target.value } ) } />
					<p className="qcin-help">
						{ __( 'The address this web server uses to reach your Ollama server. Requests go from your server, not from the browser, and no API key is needed.', 'qcin-textdomain' ) }{ ' ' }
						{ saved.keyUrl && (
							<a href={ saved.keyUrl } target="_blank" rel="noopener noreferrer">
								{ __( 'Get Ollama', 'qcin-textdomain' ) }
							</a>
						) }
					</p>
				</div>
			) }

			{ needsKey && (
			<div className="qcin-field">
				<label htmlFor="qcin-ai-key">
					{ sprintf(
						/* translators: %s: provider name, e.g. "OpenAI" */
						__( '%s API key', 'qcin-textdomain' ),
						saved.label || id
					) }
				</label>
				<div className="qcin-ai-key">
					<input
						id="qcin-ai-key"
						type="password"
						value={ p.key }
						autoComplete="new-password"
						spellCheck="false"
						placeholder={
							hasKey
								? sprintf(
									/* translators: %s: last 4 characters of the saved key */
									__( 'Saved key ending in %s', 'qcin-textdomain' ),
									saved.keyHint
								)
								: __( 'Paste your API key', 'qcin-textdomain' )
						}
						onChange={ ( e ) => setProvider( { key: e.target.value, clear_key: false } ) }
					/>
					{ hasKey && ! p.key && (
						<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" onClick={ () => setProvider( { clear_key: true, key: '' } ) }>
							{ __( 'Remove key', 'qcin-textdomain' ) }
						</button>
					) }
				</div>
				<p className="qcin-help">
					{ p.clear_key && saved.hasKey ? __( 'The saved key will be removed when you save.', 'qcin-textdomain' ) + ' ' : '' }
					{ hasKey && ! p.key ? __( 'To replace it, paste a new key.', 'qcin-textdomain' ) + ' ' : '' }
					{ saved.keyUrl && (
						<a href={ saved.keyUrl } target="_blank" rel="noopener noreferrer">
							{ sprintf(
								/* translators: %s: provider name */
								__( 'Get a %s API key', 'qcin-textdomain' ),
								saved.label
							) }
						</a>
					) }
				</p>
			</div>
			) }

			<div className="qcin-ai-test">
				<button type="button" className="qcin-btn qcin-btn--ghost qcin-btn--small" onClick={ runTest } disabled={ testing || ! canTest }>
					{ testing ? __( 'Testing…', 'qcin-textdomain' ) : __( 'Test connection', 'qcin-textdomain' ) }
				</button>
				{ test && (
					<span className={ test.ok ? 'qcin-ai-ok' : 'qcin-ai-bad' } role="status">
						{ test.message }
					</span>
				) }
				{ ! test && s.ready && (
					<span className="qcin-muted">
						{ sprintf(
							/* translators: %s: provider and model, e.g. "OpenAI · gpt-4o-mini" */
							__( 'In use: %s', 'qcin-textdomain' ),
							s.label
						) }
					</span>
				) }
			</div>

			<fieldset className="qcin-field">
				<legend>{ __( 'Features', 'qcin-textdomain' ) }</legend>
				{ ( s.featureList || [] ).map( ( f ) => (
					<Toggle
						key={ f.id }
						id={ 'qcin-ai-' + f.id }
						checked={ !! form.features[ f.id ] }
						onChange={ ( v ) => setForm( { ...form, features: { ...form.features, [ f.id ]: v } } ) }
						label={ f.label }
						help={ f.help }
					/>
				) ) }
			</fieldset>

			<fieldset className="qcin-field">
				<legend>{ __( 'Cost limits', 'qcin-textdomain' ) }</legend>
				<div className="qcin-grid-3">
					<NumberField id="qcin-ai-daytok" label={ __( 'Tokens per day', 'qcin-textdomain' ) } value={ form.limits.daily_tokens } min="0" onChange={ ( v ) => setForm( { ...form, limits: { ...form.limits, daily_tokens: v } } ) } />
					<NumberField id="qcin-ai-monthtok" label={ __( 'Tokens per month', 'qcin-textdomain' ) } value={ form.limits.monthly_tokens } min="0" onChange={ ( v ) => setForm( { ...form, limits: { ...form.limits, monthly_tokens: v } } ) } />
					<NumberField id="qcin-ai-dayreq" label={ __( 'Requests per day', 'qcin-textdomain' ) } value={ form.limits.daily_requests } min="0" onChange={ ( v ) => setForm( { ...form, limits: { ...form.limits, daily_requests: v } } ) } />
				</div>
				<p className="qcin-help">{ __( 'When a limit is reached, AI pauses until the next day or month. 0 means no limit. A session summary usually uses 1,000 to 3,000 tokens.', 'qcin-textdomain' ) }</p>
			</fieldset>

			<UsagePanel usage={ data.usage } limits={ form.limits } featureList={ s.featureList } />

			{ data.hostAiUrl && (
				<p className="qcin-help">
					{ sprintf(
						/* translators: %s: host plugin name */
						__( 'These settings are separate from %s AI Settings, which the chatbot uses.', 'qcin-textdomain' ),
						host
					) }{ ' ' }
					<a href={ data.hostAiUrl }>
						{ sprintf(
							/* translators: %s: host plugin name */
							__( 'Open %s AI Settings', 'qcin-textdomain' ),
							host
						) }
					</a>
				</p>
			) }
		</Section>
	);
}
