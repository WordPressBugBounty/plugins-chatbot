/**
 * Settings > Connections: the owner's keyword data provider (optional).
 * Key saved encrypted in WPBot Core and never shown again; monthly budget;
 * balance check.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { core, errorMessage } from '../api';

const UNIT = {
	usd: __( 'USD', 'chatbot' ),
	credits: __( 'credits', 'chatbot' ),
	units: __( 'API units', 'chatbot' ),
};

const HELP = {
	dataforseo: __(
		'Enter your API login and API password as login:password (DataForSEO dashboard > API Access). Pay as you go; about $0.09 per lookup of up to 1,000 keywords.',
		'chatbot'
	),
	keywordseverywhere: __(
		'Your API key from Keywords Everywhere. One credit per keyword.',
		'chatbot'
	),
	semrush: __(
		'Your Semrush API key (Business plan with API units). About 10 units per keyword.',
		'chatbot'
	),
};

export const unitLabel = ( unit ) => UNIT[ unit ] || unit;

const KeywordData = () => {
	const [ view, setView ] = useState( null );
	const [ provider, setProvider ] = useState( '' );
	const [ key, setKey ] = useState( '' );
	const [ budget, setBudget ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const apply = ( v ) => {
		setView( v );
		setProvider( v.provider );
		setBudget( v.budget ? String( v.budget ) : '' );
		setKey( '' );
	};
	useEffect( () => {
		core( 'keyword-data?module=seo' )
			.then( apply )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const run = ( promise, done ) => {
		setBusy( true );
		setMessage( '' );
		promise
			.then( ( v ) => {
				apply( v );
				setMessage( done( v ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const current = view.providers.find( ( p ) => p.id === provider );
	return (
		<div className="qcseo-card qcseo-keyword-data">
			<h3>{ __( 'Keyword data (optional)', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'Search volumes, cost per click and trends from your own provider account. Without one, everything works on Search Console data, and your free Bing Webmaster Tools key (Other services above) adds Bing search numbers and trends, labelled "Bing (free)". Lookups show their cost first and are cached for 30 days.',
					'chatbot'
				) }
			</p>
			{ view.health && view.health.code && (
				<p className="qcseo-key-notice">
					<strong>{ view.health.message }</strong>
				</p>
			) }
			<div className="qcseo-grid">
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Provider', 'chatbot' ) }
					value={ provider }
					options={ [
						{ value: '', label: __( 'None', 'chatbot' ) },
						...view.providers.map( ( p ) => ( {
							value: p.id,
							label: p.label,
						} ) ),
					] }
					onChange={ setProvider }
					__nextHasNoMarginBottom
				/>
				{ provider && (
					<TextControl
						__next40pxDefaultSize
						label={
							current && current.set
								? sprintf(
										/* translators: %s: masked key. */
										__(
											'Key (saved: %s; leave empty to keep it)',
											'chatbot'
										),
										current.hint
									)
								: __( 'Key', 'chatbot' )
						}
						help={ HELP[ provider ] }
						type="password"
						autoComplete="off"
						value={ key }
						onChange={ setKey }
						__nextHasNoMarginBottom
					/>
				) }
				{ provider && (
					<TextControl
						__next40pxDefaultSize
						label={ sprintf(
							/* translators: %s: unit, such as USD or credits. */
							__(
								'Monthly budget (%s; empty = no limit)',
								'chatbot'
							),
							unitLabel( current ? current.unit : '' )
						) }
						type="number"
						min={ 0 }
						value={ budget }
						onChange={ setBudget }
						__nextHasNoMarginBottom
					/>
				) }
			</div>
			{ view.connected && (
				<p className="qcseo-muted">
					{ sprintf(
						/* translators: 1: amount spent, 2: unit. */
						__(
							'Spent this month: %1$s %2$s.',
							'chatbot'
						),
						Number( view.spent ).toLocaleString( undefined, {
							maximumFractionDigits: 2,
						} ),
						unitLabel( view.unit )
					) }
					{ view.balance && undefined !== view.balance.amount && ' ' }
					{ view.balance &&
						undefined !== view.balance.amount &&
						sprintf(
							/* translators: 1: balance, 2: unit. */
							__( 'Balance: %1$s %2$s.', 'chatbot' ),
							Number( view.balance.amount ).toLocaleString(
								undefined,
								{ maximumFractionDigits: 2 }
							),
							unitLabel( view.unit )
						) }
				</p>
			) }
			<div className="qcseo-actions">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () =>
						run(
							core( 'keyword-data', {
								method: 'POST',
								data: {
									module: 'seo',
									provider,
									key,
									budget: Number( budget || 0 ),
								},
							} ),
							() => __( 'Saved.', 'chatbot' )
						)
					}
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>
				{ view.connected && (
					<Button
						variant="secondary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ () =>
							run(
								core( 'keyword-data/balance', {
									method: 'POST',
									data: { module: 'seo' },
								} ),
								( v ) =>
									sprintf(
										/* translators: 1: balance, 2: unit. */
										__(
											'The key works. Balance: %1$s %2$s.',
											'chatbot'
										),
										Number(
											v.checked.amount
										).toLocaleString( undefined, {
											maximumFractionDigits: 2,
										} ),
										unitLabel( v.checked.unit )
									)
							)
						}
					>
						{ __(
							'Check the key and balance',
							'chatbot'
						) }
					</Button>
				) }
				{ current && current.set && (
					<Button
						variant="tertiary"
						isDestructive
						disabled={ busy }
						onClick={ () =>
							run(
								core( 'keyword-data', {
									method: 'POST',
									data: {
										module: 'seo',
										provider,
										remove: true,
									},
								} ),
								() => __( 'Key removed.', 'chatbot' )
							)
						}
					>
						{ __( 'Remove key', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ message && <p className="qcseo-message">{ message }</p> }
		</div>
	);
};

export default KeywordData;
