/**
 * Connections (administrators): Google Search Console through the owner's
 * own Google Cloud OAuth client, and the owner's YouTube Data API key.
 * Secrets are write-only: the screen only ever sees masked hints.
 */
import {
	Button,
	ExternalLink,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { core, data, errorMessage } from '../api';

const settingsUrl = () => {
	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', 'settings' );
	url.searchParams.delete( 'qcld_google' );
	url.hash = 'qcwr-connections';
	return url.toString();
};

const ConnectionsSettings = () => {
	const [ google, setGoogle ] = useState( null );
	const [ youtube, setYoutube ] = useState( null );
	const [ clientId, setClientId ] = useState( '' );
	const [ secret, setSecret ] = useState( '' );
	const [ ytKey, setYtKey ] = useState( '' );
	const [ sites, setSites ] = useState( null );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( '' );

	const fail = ( err ) =>
		setMessage( { status: 'error', text: errorMessage( err ) } );

	useEffect( () => {
		core( 'connections/google' )
			.then( ( view ) => {
				setGoogle( view );
				setClientId( view.client_id );
				const back = new URLSearchParams( window.location.search ).get(
					'qcld_google'
				);
				if ( view.notice ) {
					setMessage( { status: 'error', text: view.notice } );
				} else if ( 'connected' === back ) {
					setMessage( {
						status: 'success',
						text: __(
							'Google Search Console is connected.',
							'chatbot'
						),
					} );
				}
				if ( view.connected ) {
					core( 'connections/google/sites' )
						.then( setSites )
						.catch( () => {} );
				}
			} )
			.catch( fail );
		// Only shown when a registered feature uses YouTube.
		if ( ( data.connections || [] ).includes( 'youtube' ) ) {
			core( 'connections/youtube' )
				.then( setYoutube )
				.catch( () => {} );
		}
	}, [] );

	const saveClient = () => {
		setBusy( 'client' );
		setMessage( null );
		core( 'connections/google', {
			method: 'POST',
			data: { client_id: clientId, client_secret: secret },
		} )
			.then( ( view ) => {
				setGoogle( view );
				setSecret( '' );
				setMessage( {
					status: 'success',
					text: __( 'Saved. Now click Connect.', 'chatbot' ),
				} );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const connect = () => {
		setBusy( 'connect' );
		core( 'connections/google/connect', {
			method: 'POST',
			data: { return_url: settingsUrl() },
		} )
			.then( ( result ) => {
				window.location.href = result.url;
			} )
			.catch( ( err ) => {
				fail( err );
				setBusy( '' );
			} );
	};

	const disconnect = () => {
		setBusy( 'disconnect' );
		core( 'connections/google/disconnect', { method: 'POST' } )
			.then( ( view ) => {
				setGoogle( view );
				setSites( null );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const setProperty = ( property ) => {
		core( 'connections/google', {
			method: 'POST',
			data: { property },
		} )
			.then( setGoogle )
			.catch( fail );
	};

	const saveYoutube = ( clear = false ) => {
		setBusy( 'youtube' );
		core( 'connections/youtube', {
			method: 'POST',
			data: clear ? { clear_key: true } : { key: ytKey },
		} )
			.then( ( view ) => {
				setYoutube( view );
				setYtKey( '' );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	if ( ! google ) {
		return null;
	}

	return (
		<div className="qcwr-card" id="qcwr-connections">
			<h2>{ __( 'Connections', 'chatbot' ) }</h2>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ 'failing' === google.status && (
				<div className="qcwr-key-notice" role="alert">
					<div className="qcwr-key-notice__item">
						<strong className="qcwr-key-notice__cause">
							{ __(
								'Google Search Console is not working.',
								'chatbot'
							) }
						</strong>
						<span className="qcwr-key-notice__fix">
							{ google.message }
						</span>
					</div>
				</div>
			) }

			<h3>{ __( 'Google Search Console', 'chatbot' ) }</h3>
			<p className="qcwr-muted">
				{ __(
					'Read-only access to what people search on Google before they find you. You use your own Google Cloud client, so your data stays between this site and Google.',
					'chatbot'
				) }
			</p>
			{ ! google.connected && (
				<details className="qcwr-guide">
					<summary>
						{ __(
							'How to create your Google client (5 minutes)',
							'chatbot'
						) }
					</summary>
					<ol>
						<li>
							<ExternalLink href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com">
								{ __(
									'Open Google Cloud and enable the Google Search Console API',
									'chatbot'
								) }
							</ExternalLink>{ ' ' }
							{ __(
								'(create a project if asked).',
								'chatbot'
							) }
						</li>
						<li>
							{ __(
								'Under APIs & Services > OAuth consent screen, choose External, add your email, and add yourself as a test user.',
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
							<code className="qcwr-copy">
								{ google.redirect_uri }
							</code>
						</li>
						<li>
							{ __(
								'Copy the Client ID and Client secret here, save, then click Connect.',
								'chatbot'
							) }
						</li>
					</ol>
				</details>
			) }
			<div className="qcwr-grid">
				<TextControl
					label={ __( 'Client ID', 'chatbot' ) }
					value={ clientId }
					onChange={ setClientId }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Client secret', 'chatbot' ) }
					type="password"
					autoComplete="off"
					value={ secret }
					placeholder={
						google.has_secret
							? sprintf(
									/* translators: %s: last characters of the secret. */
									__( 'Saved (%s)', 'chatbot' ),
									google.secret_hint
								)
							: ''
					}
					onChange={ setSecret }
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isBusy={ 'client' === busy }
					disabled={ ! clientId || !! busy }
					onClick={ saveClient }
				>
					{ __( 'Save client', 'chatbot' ) }
				</Button>
				{ google.connected ? (
					<Button
						variant="secondary"
						isDestructive
						isBusy={ 'disconnect' === busy }
						disabled={ !! busy }
						onClick={ disconnect }
					>
						{ __( 'Disconnect', 'chatbot' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						isBusy={ 'connect' === busy }
						disabled={
							! google.client_id || ! google.has_secret || !! busy
						}
						onClick={ connect }
					>
						{ __( 'Connect', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ google.connected && (
				<p className="qcwr-status qcwr-status--connected">
					{ __( 'Connected', 'chatbot' ) }
				</p>
			) }
			{ google.connected && sites && (
				<SelectControl
					label={ __( 'Search Console property', 'chatbot' ) }
					value={ google.property || sites.suggested }
					options={ [
						{
							label: __(
								'Match this site automatically',
								'chatbot'
							),
							value: '',
						},
						...sites.sites.map( ( site ) => ( {
							label: site,
							value: site,
						} ) ),
					] }
					onChange={ setProperty }
					__nextHasNoMarginBottom
				/>
			) }

			{ youtube && (
				<>
					<h3>{ __( 'YouTube', 'chatbot' ) }</h3>
					<p className="qcwr-muted">
						{ __(
							'Add a free YouTube API key to see popular recent videos on your topics in Topic Finder. The free quota allows about 100 searches a day.',
							'chatbot'
						) }{ ' ' }
						<ExternalLink href="https://developers.google.com/youtube/v3/getting-started">
							{ __( 'How to get a key', 'chatbot' ) }
						</ExternalLink>
					</p>
					{ 'failing' === youtube.status && (
						<p className="qcwr-error">{ youtube.message }</p>
					) }
					<div className="qcwr-row">
						<TextControl
							label={ __(
								'YouTube Data API key',
								'chatbot'
							) }
							type="password"
							autoComplete="off"
							value={ ytKey }
							placeholder={
								youtube.has_key
									? sprintf(
											/* translators: %s: last characters of the key. */
											__(
												'Saved (%s)',
												'chatbot'
											),
											youtube.key_hint
										)
									: ''
							}
							onChange={ setYtKey }
							__nextHasNoMarginBottom
						/>
						<Button
							variant="secondary"
							isBusy={ 'youtube' === busy }
							disabled={ ! ytKey || !! busy }
							onClick={ () => saveYoutube() }
						>
							{ __( 'Save key', 'chatbot' ) }
						</Button>
						{ youtube.has_key && (
							<Button
								variant="link"
								isDestructive
								onClick={ () => saveYoutube( true ) }
							>
								{ __( 'Remove', 'chatbot' ) }
							</Button>
						) }
					</div>
				</>
			) }
		</div>
	);
};

export default ConnectionsSettings;
