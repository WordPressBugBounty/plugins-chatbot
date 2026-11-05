/**
 * Local SEO > Service and area planner (round 3, L): services against the
 * areas served, with searches per month when known, the page that covers
 * each one, and what to do. Own pages only where the business has real
 * local details; everything else goes on the service page (no doorway pages).
 */
import { Button, Spinner, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';
import VolumeLookup from '../VolumeLookup';

const VERDICTS = {
	covered: {
		label: __( 'Covered', 'chatbot' ),
		className: 'is-ranking',
	},
	own_page: {
		label: __( 'Deserves its own page', 'chatbot' ),
		className: 'is-targeted',
	},
	service_page: {
		label: __( 'Mention on the service page', 'chatbot' ),
		className: '',
	},
	low: {
		label: __( 'Few searches', 'chatbot' ),
		className: 'is-archived',
	},
};

const Cell = ( { cell, service, area } ) => {
	const verdict = VERDICTS[ cell.verdict ] || VERDICTS.service_page;
	return (
		<td className={ `qcseo-area-cell is-${ cell.verdict }` }>
			<span className={ `qcseo-status ${ verdict.className }` }>
				{ verdict.label }
			</span>
			<div className="qcseo-muted">
				{ null === cell.volume
					? __( 'searches unknown', 'chatbot' )
					: sprintf(
							/* translators: %s: searches per month. */
							__( '%s searches a month', 'chatbot' ),
							Number( cell.volume ).toLocaleString()
						) }
			</div>
			{ cell.page && (
				<div>
					{ cell.page.url ? (
						<a href={ cell.page.url }>{ cell.page.title }</a>
					) : (
						sprintf(
							/* translators: %s: planned page title. */
							__( 'Planned: %s', 'chatbot' ),
							cell.page.title
						)
					) }
				</div>
			) }
			{ applyFilters( 'qcld_wpbot_seo.local.areaCell', null, {
				cell,
				service,
				area,
			} ) }
		</td>
	);
};

const AreaPlanner = () => {
	const [ view, setView ] = useState( null );
	const [ editing, setEditing ] = useState( false );
	const [ services, setServices ] = useState( '' );
	const [ areas, setAreas ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'local/areas' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const edit = () => {
		setServices( view.services.join( '\n' ) );
		setAreas( view.areas.map( ( a ) => a.name ).join( '\n' ) );
		setEditing( true );
	};
	const save = ( lists ) => {
		setBusy( true );
		setMessage( '' );
		seo( 'local/areas/save', { method: 'POST', data: lists } )
			.then( ( v ) => {
				setView( v );
				setEditing( false );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	const empty = 0 === view.services.length || 0 === view.areas.length;
	return (
		<div className="qcseo-card qcseo-area-planner">
			<div className="qcseo-head">
				<h3>
					{ __( 'Service and area planner', 'chatbot' ) }
				</h3>
				{ view.can_edit && ! editing && (
					<Button variant="tertiary" size="compact" onClick={ edit }>
						{ __( 'Edit services and areas', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcseo-muted">
				{ __(
					'Which of your services people look for in each area you serve, and where each one belongs. A separate page for an area is worth it only when you have real local details there (an address, a phone number, your own notes about the place). Pages that only swap the town name are doorway pages: Google’s spam policies forbid them, so the planner suggests adding those areas to the service page instead.',
					'chatbot'
				) }
			</p>
			{ editing && (
				<div className="qcseo-form">
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'Services (one per line; empty = suggestions from Site Context and product categories)',
							'chatbot'
						) }
						rows={ 5 }
						value={ services }
						onChange={ setServices }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'Areas (one per line; empty = your locations and the areas you serve)',
							'chatbot'
						) }
						rows={ 5 }
						value={ areas }
						onChange={ setAreas }
					/>
					<div className="qcseo-actions">
						<Button
							variant="primary"
							size="compact"
							isBusy={ busy }
							disabled={ busy }
							onClick={ () => save( { services, areas } ) }
						>
							{ __( 'Save', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							size="compact"
							onClick={ () => setEditing( false ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</div>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
			{ empty ? (
				<p className="qcseo-muted">
					{ __(
						'Add your town and the areas you serve above, and build Site Context (Links > Get cited by AI) or add your services here, to see the grid.',
						'chatbot'
					) }
				</p>
			) : (
				<>
					<div className="qcseo-table-scroll">
						<table className="widefat qcseo-area-grid">
							<thead>
								<tr>
									<th>
										{ __( 'Service', 'chatbot' ) }
									</th>
									{ view.areas.map( ( a ) => (
										<th key={ a.name }>
											{ a.name }
											{ a.details && (
												<div className="qcseo-muted">
													{ __(
														'your location',
														'chatbot'
													) }
												</div>
											) }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ view.services.map( ( service, si ) => (
									<tr key={ service }>
										<th scope="row">{ service }</th>
										{ view.areas.map( ( area, ai ) => (
											<Cell
												key={ area.name }
												cell={ view.cells[ si ][ ai ] }
												service={ service }
												area={ area }
											/>
										) ) }
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
					{ view.volumes && (
						<VolumeLookup
							scope="areas"
							limit={ 144 }
							label={ __(
								'Get searches per month for the grid',
								'chatbot'
							) }
							onDone={ load }
						/>
					) }
				</>
			) }
		</div>
	);
};

export default AreaPlanner;
