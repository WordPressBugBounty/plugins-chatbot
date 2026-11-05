/**
 * Google connection (owner's own Google Cloud app): guide for Search
 * Console and Analytics, client fields, Connect, property choices.
 * Uses WPBot Core's shared connection, so AI Writer's Topic Finder and
 * this module use one connection.
 */
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { core, data, errorMessage, seo } from '../api';

const settingsUrl = () => `${ data.pageUrl }&tab=settings`;

const GoogleSettings = ( { settings, onSettings } ) => {
	const [ google, setGoogle ] = useState( null );
	const [ sites, setSites ] = useState( null );
	const [ properties, setProperties ] = useState( null );
	const [ clientId, setClientId ] = useState( '' );
	const [ secret, setSecret ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ note ] = useState( data.googleNote || '' );

	const load = () =>
		core( 'connections/google' )
			.then( ( view ) => {
				setGoogle( view );
				setClientId( view.client_id || '' );
				if ( view.connected ) {
					core( 'connections/google/sites' )
						.then( setSites )
						.catch( () => setSites( null ) );
					if ( view.analytics ) {
						seo( 'analytics/properties' )
							.then( setProperties )
							.catch( ( e ) => setError( errorMessage( e ) ) );
					}
				}
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) );

	useEffect( () => {
		load();
	}, [] );

	const run = ( name, promise ) => {
		setBusy( name );
		setError( '' );
		return promise
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const saveClient = () =>
		run(
			'save',
			core( 'connections/google', {
				method: 'POST',
				data: { client_id: clientId, client_secret: secret },
			} ).then( ( view ) => {
				setGoogle( view );
				setSecret( '' );
			} )
		);

	const connect = () =>
		run(
			'connect',
			core( 'connections/google/connect', {
				method: 'POST',
				data: { return_url: settingsUrl() },
			} ).then( ( result ) => {
				window.location.href = result.url;
			} )
		);

	const disconnect = () =>
		run(
			'disconnect',
			core( 'connections/google/disconnect', { method: 'POST' } ).then(
				load
			)
		);

	const saveProperty = ( property ) =>
		run(
			'property',
			core( 'connections/google', {
				method: 'POST',
				data: { property },
			} ).then( setGoogle )
		);

	const saveGa = ( id ) =>
		run(
			'ga',
			seo( 'analytics/property', { method: 'POST', data: { id } } ).then(
				load
			)
		);

	if ( ! google ) {
		return error ? <p className="qcseo-error">{ error }</p> : <Spinner />;
	}

	const needsReconnect =
		google.connected && google.missing && google.missing.length > 0;

	return (
		<div className="qcseo-card qcseo-form">
			<h3>
				{ __(
					'Google Search Console and Analytics',
					'chatbot'
				) }
			</h3>
			<p className="qcseo-muted">
				{ __(
					'Read-only access through your own Google Cloud app, so your data stays between this site and Google. Search Console and Google Analytics are optional: everything works without them, and they add your own clicks, positions and searches. AI Writer uses the same connection.',
					'chatbot'
				) }
			</p>
			{ note && (
				<Notice status="error" isDismissible={ false }>
					{ note }
				</Notice>
			) }
			{ google.connected && 'failing' === google.status && (
				<Notice status="error" isDismissible={ false }>
					<strong>{ google.message }</strong>
				</Notice>
			) }
			{ needsReconnect && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Click Connect again to add read-only Google Analytics access. Your Search Console access stays.',
						'chatbot'
					) }
				</Notice>
			) }

			<details className="qcseo-guide" open={ ! google.connected }>
				<summary>
					{ __(
						'How to create your Google app (about 5 minutes)',
						'chatbot'
					) }
				</summary>
				<ol>
					<li>
						<ExternalLink href="https://console.cloud.google.com/projectcreate">
							{ __(
								'Create a Google Cloud project',
								'chatbot'
							) }
						</ExternalLink>{ ' ' }
						{ __( '(free; any name).', 'chatbot' ) }
					</li>
					<li>
						{ __(
							'Enable these APIs in the project:',
							'chatbot'
						) }{ ' ' }
						<ExternalLink href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com">
							{ __(
								'Google Search Console API',
								'chatbot'
							) }
						</ExternalLink>
						{ settings.use_analytics && (
							<>
								{ ', ' }
								<ExternalLink href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com">
									{ __(
										'Google Analytics Data API',
										'chatbot'
									) }
								</ExternalLink>
								{ ', ' }
								<ExternalLink href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com">
									{ __(
										'Google Analytics Admin API',
										'chatbot'
									) }
								</ExternalLink>
							</>
						) }
						.
					</li>
					<li>
						{ __(
							'Under APIs & Services > OAuth consent screen, choose External, enter your email, and add yourself as a test user. Then set the publishing status to "In production", or Google ends the access after 7 days. Google shows an "unverified app" warning for your own app; you can continue because it is yours.',
							'chatbot'
						) }
					</li>
					<li>
						{ __(
							'Under Credentials, create an OAuth client ID of type "Web application".',
							'chatbot'
						) }
					</li>
					<li>
						{ __(
							'Add this as an Authorized redirect URI:',
							'chatbot'
						) }{ ' ' }
						<code className="qcseo-copy">
							{ google.redirect_uri }
						</code>
					</li>
					<li>
						{ __(
							'Copy the Client ID and Client secret here, save, then click Connect and allow read-only access.',
							'chatbot'
						) }
					</li>
				</ol>
			</details>

			<CheckboxControl
				label={ __(
					'Also connect Google Analytics (optional, read-only)',
					'chatbot'
				) }
				checked={ !! settings.use_analytics }
				onChange={ ( value ) => onSettings( { use_analytics: value } ) }
				__nextHasNoMarginBottom
			/>

			<div className="qcseo-grid">
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Client ID', 'chatbot' ) }
					value={ clientId }
					onChange={ setClientId }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Client secret', 'chatbot' ) }
					type="password"
					autoComplete="off"
					value={ secret }
					placeholder={ google.has_secret ? google.secret_hint : '' }
					help={
						google.has_secret
							? __(
									'Saved. Type a new secret only to replace it.',
									'chatbot'
								)
							: ''
					}
					onChange={ setSecret }
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="qcseo-actions">
				<Button
					variant="secondary"
					isBusy={ 'save' === busy }
					disabled={ !! busy || ! clientId }
					onClick={ saveClient }
				>
					{ __( 'Save client', 'chatbot' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ 'connect' === busy }
					disabled={ !! busy || ! google.has_secret }
					onClick={ connect }
				>
					{ google.connected
						? __( 'Connect again', 'chatbot' )
						: __( 'Connect', 'chatbot' ) }
				</Button>
				{ google.connected && (
					<Button
						variant="tertiary"
						isDestructive
						isBusy={ 'disconnect' === busy }
						disabled={ !! busy }
						onClick={ disconnect }
					>
						{ __( 'Disconnect', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ error && <p className="qcseo-error">{ error }</p> }

			{ google.connected && sites && sites.sites && (
				<SelectControl
					__next40pxDefaultSize
					label={ __(
						'Search Console property',
						'chatbot'
					) }
					value={ google.property || sites.suggested || '' }
					options={ [
						{
							value: '',
							label: __( 'Choose…', 'chatbot' ),
						},
						...sites.sites.map( ( site ) => ( {
							value: site,
							label: site,
						} ) ),
					] }
					onChange={ saveProperty }
					__nextHasNoMarginBottom
				/>
			) }
			{ google.connected && google.analytics && properties && (
				<SelectControl
					__next40pxDefaultSize
					label={ __(
						'Google Analytics property',
						'chatbot'
					) }
					value={ google.ga_property || '' }
					options={ [
						{
							value: '',
							label: __( 'Not used', 'chatbot' ),
						},
						...properties.map( ( item ) => ( {
							value: item.id,
							label: `${ item.name } (${ item.account })`,
						} ) ),
					] }
					onChange={ saveGa }
					__nextHasNoMarginBottom
				/>
			) }
		</div>
	);
};

export default GoogleSettings;
