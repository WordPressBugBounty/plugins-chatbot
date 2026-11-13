/**
 * Local SEO > Google Business Profile (round 3, I): turn it on, follow the
 * steps (reconnect Google, Google's API access), choose the profile
 * location, and see the health check: where the profile and the website
 * disagree, with how to fix it. Pro adds performance below.
 */
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo, product } from '../../api';

const BusinessProfile = () => {
	const [ view, setView ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const call = ( path, body ) => {
		setBusy( true );
		setMessage( '' );
		return seo( path, body ? { method: 'POST', data: body } : {} )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	useEffect( () => {
		call( 'local/gbp' );
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	if ( 'unavailable' === view.status ) {
		return null;
	}
	const connections = `${ data.pageUrl }&tab=settings&section=connections`;
	const failed = view.health
		? view.health.checks.filter( ( c ) => ! c.ok ).length
		: 0;
	return (
		<div className="qcseo-card qcseo-gbp">
			<h3>{ __( 'Google Business Profile', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'Checks that your Business Profile on Google says the same as your website: name, address, phone, website, opening hours and holiday hours. Read-only: you fix differences on Google.',
					'chatbot'
				) }
			</p>
			{ message && (
				<Notice status="error" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ 'off' === view.status && view.can_edit && (
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => call( 'local/gbp/settings', { on: true } ) }
				>
					{ __( 'Connect Business Profile', 'chatbot' ) }
				</Button>
			) }
			{ ( 'reconnect' === view.status ||
				'not_connected' === view.status ) && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: product name, for example WPBot. */
						__(
							'Reconnect Google once and allow Business Profile. Google asks for "manage your business listings": %s only reads your profile and reviews, and posts a review reply only when you click Post reply.',
							'chatbot'
						),
						product
					) }{ ' ' }
					<a href={ connections }>
						{ __( 'Open Connections', 'chatbot' ) }
					</a>
				</Notice>
			) }
			{ 'pending' === view.status && (
				<Notice status="warning" isDismissible={ false }>
					{ view.error || view.message }{ ' ' }
					<a
						href={ view.access }
						target="_blank"
						rel="noreferrer noopener"
					>
						{ __( 'Request access', 'chatbot' ) }
					</a>
				</Notice>
			) }
			{ 'connected' === view.status && (
				<div className="qcseo-form">
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Your Business Profile location',
							'chatbot'
						) }
						value={ view.link ? view.link.name : '' }
						disabled={ ! view.can_edit || busy }
						options={ [
							{
								value: '',
								label: __( 'Choose…', 'chatbot' ),
							},
							...view.locations.map( ( l ) => ( {
								value: l.name,
								label: l.city
									? `${ l.title } (${ l.city })`
									: l.title,
							} ) ),
						] }
						onChange={ ( name ) =>
							call( 'local/gbp/link', { name } )
						}
					/>
				</div>
			) }
			{ view.error && 'pending' !== view.status && (
				<p className="qcseo-error">{ view.error }</p>
			) }
			{ view.health && (
				<>
					<p>
						<strong>
							{ 0 === failed
								? __(
										'Your profile matches your website.',
										'chatbot'
									)
								: sprintf(
										/* translators: %d: checks to fix. */
										__(
											'%d things to fix on Google',
											'chatbot'
										),
										failed
									) }
						</strong>
					</p>
					<table className="widefat striped qcseo-table qcseo-gbp-checks">
						<thead>
							<tr>
								<th>{ __( 'Check', 'chatbot' ) }</th>
								<th>{ __( 'Website', 'chatbot' ) }</th>
								<th>{ __( 'Google', 'chatbot' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ view.health.checks.map( ( c ) => (
								<tr
									key={ c.id }
									className={ c.ok ? 'is-ok' : 'is-bad' }
								>
									<td>
										<span
											className={ `qcseo-status ${
												c.ok ? 'is-ranking' : 'is-bad'
											}` }
										>
											{ c.ok
												? __( 'OK', 'chatbot' )
												: __(
														'Fix',
														'chatbot'
													) }
										</span>{ ' ' }
										{ c.label }
										{ c.fix && (
											<div className="qcseo-muted">
												{ c.fix }
											</div>
										) }
									</td>
									<td>{ c.website || '—' }</td>
									<td>{ c.google || '—' }</td>
								</tr>
							) ) }
						</tbody>
					</table>
					<p className="qcseo-actions">
						<Button
							variant="secondary"
							href={ view.health.edit_url }
							target="_blank"
							rel="noreferrer noopener"
						>
							{ __(
								'Edit your profile on Google',
								'chatbot'
							) }
						</Button>
					</p>
				</>
			) }
			{ applyFilters( 'qcld_wpbot_seo.local.gbp', null, { view } ) }
			{ 'off' !== view.status && view.can_edit && (
				<p>
					<Button
						variant="link"
						isDestructive
						disabled={ busy }
						onClick={ () =>
							call( 'local/gbp/settings', { on: false } )
						}
					>
						{ __(
							'Stop using Business Profile',
							'chatbot'
						) }
					</Button>
				</p>
			) }
		</div>
	);
};

export default BusinessProfile;
