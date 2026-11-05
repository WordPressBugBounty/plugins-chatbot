/**
 * Local SEO: one business location (Free), opening hours, LocalBusiness
 * schema, and checks across the site. The Pro module adds more locations.
 */
import {
	Button,
	CheckboxControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import BusinessProfile from '../components/local/BusinessProfile';
import LocalReviews from '../components/local/LocalReviews';
import LocalAiSlot from '../components/local/LocalAiSlot';
import AreaPlanner from '../components/local/AreaPlanner';
import { data, errorMessage, seo } from '../api';

const DAYS = [
	'Monday',
	'Tuesday',
	'Wednesday',
	'Thursday',
	'Friday',
	'Saturday',
	'Sunday',
];

const LocalScreen = () => {
	const [ view, setView ] = useState( null );
	const [ loc, setLoc ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'local' ).then( ( result ) => {
			setView( result );
			setLoc( {
				...result.location,
				service_area: ( result.location.service_area || [] ).join(
					'\n'
				),
			} );
		} );
	useEffect( () => {
		load().catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );

	if ( ! view || ! loc ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const set = ( key ) => ( value ) => setLoc( { ...loc, [ key ]: value } );
	const hours = loc.hours || {};
	const setDay = ( day, change ) =>
		setLoc( {
			...loc,
			hours: {
				...hours,
				[ day ]: { ...( hours[ day ] || {} ), ...change },
			},
		} );
	const save = () => {
		setBusy( true );
		setMessage( '' );
		seo( 'local/save', { method: 'POST', data: { location: loc } } )
			.then( ( result ) => {
				setView( result );
				setMessage( __( 'Saved.', 'chatbot' ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const editable = data.can.settings;

	return (
		<div className="qcseo-local">
			<h2 className="qcseo-screen-title">
				{ __( 'Local SEO', 'chatbot' ) }
			</h2>
			{ view.checks && (
				<div className="qcseo-card">
					<h3>{ __( 'Checks', 'chatbot' ) }</h3>
					<ul className="qcseo-readiness">
						{ view.checks.items.map( ( item ) => (
							<li
								key={ item.id }
								className={ item.pass ? 'is-pass' : 'is-fail' }
							>
								<span aria-hidden="true">
									{ item.pass ? '✓' : '•' }
								</span>{ ' ' }
								<strong>{ item.label }</strong>
								{ ! item.pass && (
									<span className="qcseo-muted">
										{ ' ' }
										— { item.message }
									</span>
								) }
							</li>
						) ) }
					</ul>
					{ view.checks.phones.length > 0 && (
						<ul>
							{ view.checks.phones.map( ( phone ) => (
								<li key={ phone.number }>
									{ phone.number }:{ ' ' }
									{ phone.pages.join( ', ' ) }
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
			<div className="qcseo-card">
				<h3>{ __( 'Your business', 'chatbot' ) }</h3>
				<div className="qcseo-grid">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Business name', 'chatbot' ) }
						value={ loc.name }
						onChange={ set( 'name' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Type of business', 'chatbot' ) }
						value={ loc.type }
						options={ Object.entries( view.types ).map(
							( [ value, label ] ) => ( { value, label } )
						) }
						onChange={ set( 'type' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Street address', 'chatbot' ) }
						value={ loc.street }
						onChange={ set( 'street' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Town or city', 'chatbot' ) }
						value={ loc.city }
						onChange={ set( 'city' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Region', 'chatbot' ) }
						value={ loc.region }
						onChange={ set( 'region' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Postcode', 'chatbot' ) }
						value={ loc.postcode }
						onChange={ set( 'postcode' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Country code (e.g. BD, GB, US)',
							'chatbot'
						) }
						value={ loc.country }
						onChange={ set( 'country' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Phone', 'chatbot' ) }
						value={ loc.phone }
						onChange={ set( 'phone' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Email (optional)', 'chatbot' ) }
						value={ loc.email }
						onChange={ set( 'email' ) }
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Contact or location page',
							'chatbot'
						) }
						value={ String( loc.page_id || 0 ) }
						options={ [
							{
								value: '0',
								label: __( 'Choose…', 'chatbot' ),
							},
							...view.pages.map( ( p ) => ( {
								value: String( p.id ),
								label: p.title,
							} ) ),
						] }
						onChange={ ( value ) =>
							set( 'page_id' )( Number( value ) )
						}
						disabled={ ! editable }
						__nextHasNoMarginBottom
					/>
				</div>
				<TextareaControl
					label={ __(
						'Areas you serve (one per line)',
						'chatbot'
					) }
					value={ loc.service_area }
					onChange={ set( 'service_area' ) }
					disabled={ ! editable }
					__nextHasNoMarginBottom
				/>
				<h4>{ __( 'Opening hours', 'chatbot' ) }</h4>
				<table className="qcseo-hours">
					<tbody>
						{ DAYS.map( ( day ) => {
							const slot = hours[ day ] || {};
							return (
								<tr key={ day }>
									<th scope="row">{ day }</th>
									<td>
										<CheckboxControl
											label={ __(
												'Closed',
												'chatbot'
											) }
											checked={ !! slot.closed }
											onChange={ ( value ) =>
												setDay( day, { closed: value } )
											}
											disabled={ ! editable }
											__nextHasNoMarginBottom
										/>
									</td>
									<td>
										<input
											type="time"
											aria-label={ `${ day } opens` }
											value={ slot.open || '' }
											disabled={
												! editable || slot.closed
											}
											onChange={ ( e ) =>
												setDay( day, {
													open: e.target.value,
												} )
											}
										/>
										{ ' – ' }
										<input
											type="time"
											aria-label={ `${ day } closes` }
											value={ slot.close || '' }
											disabled={
												! editable || slot.closed
											}
											onChange={ ( e ) =>
												setDay( day, {
													close: e.target.value,
												} )
											}
										/>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
				{ editable && (
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ save }
					>
						{ __( 'Save business details', 'chatbot' ) }
					</Button>
				) }
				{ message && (
					<span className="qcseo-message"> { message }</span>
				) }
				{ view.seoPlugin && (
					<p className="qcseo-muted">
						{ __(
							"Your SEO plugin prints the site's schema, so WPBot does not add LocalBusiness schema; add your business in the SEO plugin's local settings too.",
							'chatbot'
						) }
					</p>
				) }
			</div>
			<BusinessProfile />
			<LocalReviews />
			<LocalAiSlot view={ view } />
			<AreaPlanner />
			{ applyFilters( 'qcld_wpbot_seo.local.after', null, view ) }
		</div>
	);
};

export default LocalScreen;
