/**
 * Prospects: who to contact, by kind, added by hand (AI-found ones arrive
 * with Pro), plus the local citations checklist and linkable content.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { KIND, TYPE, options } from './labels';

const BLANK = {
	kind: 'resource',
	name: '',
	website: '',
	page_url: '',
	contact_url: '',
	location: '',
	reason: '',
};

const FLAG = {
	sells_links: __(
		'Sells links or posts: left out by default',
		'chatbot'
	),
	link_heavy: __( 'Pages full of outbound links', 'chatbot' ),
	unrelated: __( 'Unrelated topics', 'chatbot' ),
};

const Citations = () => {
	const [ view, setView ] = useState( null );
	const [ open, setOpen ] = useState( '' );
	const [ entry, setEntry ] = useState( {} );
	useEffect( () => {
		seo( 'citations' ).then( setView );
	}, [] );
	if ( ! view ) {
		return <Spinner />;
	}
	const save = () =>
		seo( `citations/${ open }`, { method: 'POST', data: entry } ).then(
			( v ) => {
				setView( v );
				setOpen( '' );
			}
		);
	return (
		<div className="qcseo-card qcseo-citations">
			<h3>{ __( 'Local citations', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ view.location.name
					? sprintf(
							/* translators: 1: business name, 2: address, 3: phone. */
							__(
								'Your listings should show the same name, address and phone as Local SEO: %1$s, %2$s, %3$s.',
								'chatbot'
							),
							view.location.name,
							view.location.address,
							view.location.phone
						)
					: __(
							'Add your business details in Local SEO first, so listings can be compared with them.',
							'chatbot'
						) }
			</p>
			<p>
				{ sprintf(
					/* translators: 1: listed, 2: total, 3: with differences. */
					__(
						'Listed on %1$d of %2$d key directories; %3$d with different details.',
						'chatbot'
					),
					view.summary.listed,
					view.summary.total,
					view.summary.mismatched
				) }
			</p>
			<table className="widefat striped qcseo-table">
				<tbody>
					{ view.items.map( ( item ) => (
						<tr key={ item.id }>
							<td>
								<a
									href={ item.url }
									target="_blank"
									rel="noreferrer noopener"
								>
									{ item.name }
								</a>
							</td>
							<td>
								{ 'listed' === item.status &&
									( item.issues.length ? (
										<span className="qcseo-error">
											{ sprintf(
												/* translators: %s: name, address or phone. */
												__(
													'Different: %s',
													'chatbot'
												),
												item.issues.join( ', ' )
											) }
										</span>
									) : (
										__(
											'Listed, details match',
											'chatbot'
										)
									) ) }
								{ 'missing' === item.status &&
									__( 'Not listed yet', 'chatbot' ) }
								{ 'unknown' === item.status && (
									<span className="qcseo-muted">
										{ __(
											'Not checked',
											'chatbot'
										) }
									</span>
								) }
							</td>
							<td>
								{ data.can.use && (
									<Button
										variant="link"
										onClick={ () => {
											setOpen( item.id );
											setEntry( {
												status:
													'unknown' === item.status
														? 'listed'
														: item.status,
												listing: item.listing,
												name: item.name_as,
												address: item.address,
												phone: item.phone,
											} );
										} }
									>
										{ __( 'Update', 'chatbot' ) }
									</Button>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			{ open && (
				<div className="qcseo-grid">
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Listing', 'chatbot' ) }
						value={ entry.status }
						options={ [
							{
								value: 'listed',
								label: __( 'Listed', 'chatbot' ),
							},
							{
								value: 'missing',
								label: __( 'Not listed', 'chatbot' ),
							},
							{
								value: 'unknown',
								label: __( 'Not checked', 'chatbot' ),
							},
						] }
						onChange={ ( status ) =>
							setEntry( { ...entry, status } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Listing address (URL)',
							'chatbot'
						) }
						value={ entry.listing || '' }
						onChange={ ( listing ) =>
							setEntry( { ...entry, listing } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Name as shown', 'chatbot' ) }
						value={ entry.name || '' }
						onChange={ ( name ) => setEntry( { ...entry, name } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Address as shown', 'chatbot' ) }
						value={ entry.address || '' }
						onChange={ ( address ) =>
							setEntry( { ...entry, address } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Phone as shown', 'chatbot' ) }
						value={ entry.phone || '' }
						onChange={ ( phone ) =>
							setEntry( { ...entry, phone } )
						}
						__nextHasNoMarginBottom
					/>
					<Button variant="primary" onClick={ save }>
						{ __( 'Save', 'chatbot' ) }
					</Button>
				</div>
			) }
		</div>
	);
};

const Linkable = () => {
	const [ view, setView ] = useState( null );
	const [ topic, setTopic ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'prospects/linkable' ).then( setView );
	}, [] );
	if ( ! view ) {
		return <Spinner />;
	}
	return (
		<div className="qcseo-card">
			<h3>{ __( 'Linkable content', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'Your pages other sites are most likely to link to: guides, data, checklists and tools. Point outreach at these.',
					'chatbot'
				) }
			</p>
			{ 0 === view.pages.length ? (
				<p className="qcseo-muted">
					{ __(
						'No strong candidates yet. Plan one below.',
						'chatbot'
					) }
				</p>
			) : (
				<ul className="qcseo-movers">
					{ view.pages.map( ( page ) => (
						<li key={ page.post_id }>
							<span>
								<a
									href={ page.url }
									target="_blank"
									rel="noreferrer noopener"
								>
									{ page.title }
								</a>
								<br />
								<small className="qcseo-muted">
									{ page.reason }
								</small>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ view.writer && data.can.use && (
				<div className="qcseo-grid">
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Idea for a new linkable page',
							'chatbot'
						) }
						value={ topic }
						onChange={ setTopic }
						placeholder={ __(
							'e.g. Bike maintenance checklist',
							'chatbot'
						) }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						disabled={ ! topic }
						onClick={ () =>
							seo( 'prospects/linkable/write', {
								method: 'POST',
								data: { topic },
							} )
								.then( ( r ) => {
									window.location.href = r.url;
								} )
								.catch( ( e ) =>
									setMessage( errorMessage( e ) )
								)
						}
					>
						{ __( 'Write it in AI Writer', 'chatbot' ) }
					</Button>
				</div>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
		</div>
	);
};

const Prospects = ( { onStarted } ) => {
	const [ kind, setKind ] = useState( '' );
	const [ items, setItems ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const load = useCallback( () => {
		seo( `prospects?kind=${ kind }` )
			.then( ( r ) => setItems( r.items ) )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [ kind ] );
	useEffect( load, [ load ] );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const previews = ( data.previews || [] ).filter(
		( p ) => 'preview-prospects' === p.id
	);
	return (
		<div className="qcseo-prospects">
			{ applyFilters( 'qcld_wpbot_seo.prospects.finders', null, {
				reload: load,
			} ) }
			{ previews.map( ( p ) => (
				<div key={ p.id } className="qcseo-card qcseo-preview-card">
					<span className="qcseo-pro-pill">
						{ __( 'Pro', 'chatbot' ) }
					</span>
					<h3>{ p.label }</h3>
					<p className="qcseo-muted">{ p.text }</p>
				</div>
			) ) }
			<div className="qcseo-card">
				<div className="qcseo-head">
					<h3>{ __( 'Prospects', 'chatbot' ) }</h3>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Kind', 'chatbot' ) }
						hideLabelFromVision
						value={ kind }
						options={ options(
							KIND,
							__( 'All kinds', 'chatbot' )
						) }
						onChange={ setKind }
						__nextHasNoMarginBottom
					/>
					{ data.can.use && ! form && (
						<Button
							variant="secondary"
							onClick={ () => setForm( { ...BLANK } ) }
						>
							{ __( 'Add a prospect', 'chatbot' ) }
						</Button>
					) }
				</div>
				<p className="qcseo-muted">
					{ __(
						'Sites worth contacting, and why they fit. Only what you enter or a published contact page is kept; your competitors are left out.',
						'chatbot'
					) }
				</p>
				{ form && (
					<div className="qcseo-grid qcseo-prospect-form">
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Kind', 'chatbot' ) }
							value={ form.kind }
							options={ options( KIND ) }
							onChange={ set( 'kind' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Name', 'chatbot' ) }
							value={ form.name }
							onChange={ set( 'name' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Website', 'chatbot' ) }
							value={ form.website }
							onChange={ set( 'website' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Page (where a link fits)',
								'chatbot'
							) }
							value={ form.page_url }
							onChange={ set( 'page_url' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Published contact page',
								'chatbot'
							) }
							value={ form.contact_url }
							onChange={ set( 'contact_url' ) }
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Location', 'chatbot' ) }
							value={ form.location }
							onChange={ set( 'location' ) }
							__nextHasNoMarginBottom
						/>
						<TextareaControl
							label={ __( 'Why it fits', 'chatbot' ) }
							value={ form.reason }
							onChange={ set( 'reason' ) }
							rows={ 2 }
							__nextHasNoMarginBottom
						/>
						<div>
							<Button
								variant="primary"
								onClick={ () =>
									seo( 'prospects/add', {
										method: 'POST',
										data: form,
									} )
										.then( () => {
											setForm( null );
											setMessage( '' );
											load();
										} )
										.catch( ( e ) =>
											setMessage( errorMessage( e ) )
										)
								}
							>
								{ __( 'Save', 'chatbot' ) }
							</Button>{ ' ' }
							<Button
								variant="tertiary"
								onClick={ () => setForm( null ) }
							>
								{ __( 'Cancel', 'chatbot' ) }
							</Button>
						</div>
					</div>
				) }
				{ message && <p className="qcseo-error">{ message }</p> }
				{ ! items && <Spinner /> }
				{ items && 0 === items.length && (
					<p className="qcseo-muted">
						{ __( 'No prospects yet.', 'chatbot' ) }
					</p>
				) }
				{ items && items.length > 0 && (
					<table className="widefat striped qcseo-table qcseo-prospects-table">
						<tbody>
							{ items.map( ( p ) => (
								<tr key={ p.id }>
									<td>
										<strong>{ p.name || p.host }</strong>{ ' ' }
										<span className="qcseo-badge">
											{ KIND[ p.kind ] }
										</span>
										{ 'ai' === p.source && (
											<span className="qcseo-badge">
												{ __(
													'Found by AI',
													'chatbot'
												) }
											</span>
										) }
										{ 'data' === p.source && (
											<span className="qcseo-badge">
												{ __(
													'From backlink data',
													'chatbot'
												) }
											</span>
										) }
										<br />
										<a
											href={ p.page_url || p.website }
											target="_blank"
											rel="noreferrer noopener"
										>
											{ p.page_url || p.website }
										</a>
										{ p.contact_url && (
											<>
												{ ' · ' }
												<a
													href={ p.contact_url }
													target="_blank"
													rel="noreferrer noopener"
												>
													{ __(
														'Contact page',
														'chatbot'
													) }
												</a>
											</>
										) }
										{ p.reason && (
											<p className="qcseo-muted">
												{ p.reason }
											</p>
										) }
										{ p.flags.map( ( f ) => (
											<p
												key={ f }
												className="qcseo-error"
											>
												{ FLAG[ f ] || f }
											</p>
										) ) }
									</td>
									<td>
										{ data.can.use &&
											'started' !== p.status && (
												<Button
													variant="secondary"
													onClick={ () =>
														seo(
															`prospects/${ p.id }/outreach`,
															{ method: 'POST' }
														)
															.then( ( o ) => {
																load();
																if (
																	onStarted
																) {
																	onStarted(
																		o
																	);
																}
															} )
															.catch( ( e ) =>
																setMessage(
																	errorMessage(
																		e
																	)
																)
															)
													}
												>
													{ sprintf(
														/* translators: %s: outreach type. */
														__(
															'Start outreach (%s)',
															'chatbot'
														),
														TYPE[ p.type ]
													) }
												</Button>
											) }
										{ 'started' === p.status && (
											<span className="qcseo-muted">
												{ __(
													'In outreach',
													'chatbot'
												) }
											</span>
										) }{ ' ' }
										{ data.can.use && (
											<Button
												variant="link"
												onClick={ () =>
													seo(
														`prospects/${ p.id }`,
														{
															method: 'POST',
															data: {
																status: 'dismissed',
															},
														}
													).then( load )
												}
											>
												{ __(
													'Dismiss',
													'chatbot'
												) }
											</Button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>
			<div className="qcseo-columns">
				<Linkable />
				<Citations />
			</div>
		</div>
	);
};

export default Prospects;
