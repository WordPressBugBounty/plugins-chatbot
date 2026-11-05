/**
 * Keywords & Topics > Vault (S14): every keyword the owner researched,
 * targets or ranks for. Search, filters, sortable columns, bulk actions,
 * CSV import and export, and per keyword Write, Assign to page, Add to
 * calendar and Archive. Numbers appear only from a real source.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import {
	AiLabel,
	INTENTS,
	RealFact,
	STATUSES,
	SourceBadge,
	download,
} from './shared';

const PER_PAGE = 50;

const SOURCES = {
	research: __( 'Research', 'chatbot' ),
	niche: __( 'Niche', 'chatbot' ),
	gap: __( 'Competitor gap', 'chatbot' ),
	search_console: __( 'Search Console', 'chatbot' ),
	google_ads: __( 'Google Ads', 'chatbot' ),
	manual: __( 'Manual', 'chatbot' ),
	csv: __( 'CSV', 'chatbot' ),
};

/**
 * Page picker for "Assign to page".
 *
 * @param {Object}                       props          Props.
 * @param {(...args: unknown[]) => void} props.onPick   ( postId ).
 * @param {(...args: unknown[]) => void} props.onCancel Close.
 * @return {Element} Picker.
 */
const PagePicker = ( { onPick, onCancel } ) => {
	const [ search, setSearch ] = useState( '' );
	const [ pages, setPages ] = useState( [] );
	useEffect( () => {
		const timer = setTimeout( () => {
			seo( `vault/pages?search=${ encodeURIComponent( search ) }` )
				.then( setPages )
				.catch( () => setPages( [] ) );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ search ] );
	return (
		<div className="qcseo-page-picker">
			<TextControl
				__next40pxDefaultSize
				label={ __( 'Find a page', 'chatbot' ) }
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			<ul>
				{ pages.map( ( p ) => (
					<li key={ p.id }>
						<Button variant="link" onClick={ () => onPick( p.id ) }>
							{ p.title || `#${ p.id }` }
						</Button>{ ' ' }
						<small className="qcseo-muted">{ p.type }</small>
					</li>
				) ) }
			</ul>
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'chatbot' ) }
			</Button>
		</div>
	);
};

const TRENDS = {
	rising: __( 'Rising', 'chatbot' ),
	steady: __( 'Steady', 'chatbot' ),
	falling: __( 'Falling', 'chatbot' ),
};

/**
 * Trend, competition and value (the top-of-page bid range: what a click
 * would cost if you paid for it), from the same source as the volume.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Vault row.
 * @return {Element|null} Facts.
 */
const VaultExtras = ( { item } ) => {
	const parts = [];
	if ( item.trend ) {
		parts.push(
			{ rising: '↗ ', falling: '↘ ', steady: '→ ' }[ item.trend ] +
				TRENDS[ item.trend ]
		);
	}
	if ( item.competition ) {
		parts.push(
			sprintf(
				/* translators: %s: low, medium or high. */
				__( 'Competition: %s', 'chatbot' ),
				item.competition
			)
		);
	}
	if ( null !== item.bid_low && null !== item.bid_high ) {
		parts.push(
			sprintf(
				/* translators: 1: low bid, 2: high bid. */
				__( 'Value: %1$s–%2$s a click', 'chatbot' ),
				Number( item.bid_low ).toFixed( 2 ),
				Number( item.bid_high ).toFixed( 2 )
			)
		);
	}
	return parts.length ? (
		<span className="qcseo-muted qcseo-vault-extras">
			{ parts.join( ' · ' ) }
		</span>
	) : null;
};

const Vault = () => {
	const [ view, setView ] = useState( null );
	const [ filters, setFilters ] = useState( {
		search: '',
		status: '',
		intent: '',
		source: '',
		trend: '',
		competition: '',
		min_volume: '',
		orderby: 'updated_at',
		order: 'desc',
	} );
	const [ page, setPage ] = useState( 1 );
	const [ picked, setPicked ] = useState( {} );
	const [ message, setMessage ] = useState( null );
	const [ assigning, setAssigning ] = useState( 0 );
	const [ bulkAssign, setBulkAssign ] = useState( false );
	const [ bulk, setBulk ] = useState( '' );
	const [ bulkValue, setBulkValue ] = useState( '' );
	const [ manual, setManual ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const canUse = !! data.can.use;

	const query = useCallback( () => {
		const params = new URLSearchParams();
		Object.keys( filters ).forEach( ( key ) => {
			if ( filters[ key ] ) {
				params.set( key, filters[ key ] );
			}
		} );
		params.set( 'page', String( page ) );
		params.set( 'per_page', String( PER_PAGE ) );
		return params.toString();
	}, [ filters, page ] );

	const load = useCallback(
		() =>
			seo( `vault?${ query() }` )
				.then( ( v ) => {
					setView( v );
					setPicked( {} );
				} )
				.catch( ( e ) =>
					setMessage( { status: 'error', text: errorMessage( e ) } )
				),
		[ query ]
	);

	useEffect( () => {
		const timer = setTimeout( load, 200 );
		return () => clearTimeout( timer );
	}, [ load ] );

	const done = ( text ) => {
		setMessage( text ? { status: 'success', text } : null );
		return load();
	};
	const fail = ( e ) =>
		setMessage( { status: 'error', text: errorMessage( e ) } );
	const set = ( key ) => ( value ) => {
		setPage( 1 );
		setFilters( { ...filters, [ key ]: value } );
	};
	const sortBy = ( column ) =>
		setFilters( {
			...filters,
			orderby: column,
			order:
				filters.orderby === column && 'desc' === filters.order
					? 'asc'
					: 'desc',
		} );
	const ids = Object.keys( picked )
		.filter( ( id ) => picked[ id ] )
		.map( Number );

	const write = ( id ) =>
		seo( `vault/${ id }/write`, { method: 'POST' } )
			.then( ( r ) => {
				window.location.href = r.url;
			} )
			.catch( fail );
	const assign = ( id, postId ) =>
		seo( `vault/${ id }/assign`, {
			method: 'POST',
			data: { post_id: postId },
		} )
			.then( () => {
				setAssigning( 0 );
				return done(
					__(
						'Assigned: the page now targets this keyword (manual, kept on refresh).',
						'chatbot'
					)
				);
			} )
			.catch( fail );
	const ideas = ( list ) =>
		seo( 'vault/ideas', { method: 'POST', data: { ids: list } } )
			.then( ( r ) =>
				done(
					sprintf(
						/* translators: %d: ideas. */
						__(
							'%d keywords added to AI Writer’s ideas and calendar.',
							'chatbot'
						),
						r.added
					)
				)
			)
			.catch( fail );
	const runBulk = ( action, value ) => {
		if (
			'delete' === action &&
			// eslint-disable-next-line no-alert -- A native confirm is enough here.
			! window.confirm(
				__(
					'Delete the selected keywords from the Vault?',
					'chatbot'
				)
			)
		) {
			return;
		}
		setBusy( 'bulk' );
		seo( 'vault/bulk', {
			method: 'POST',
			data: { ids, action, value },
		} )
			.then( ( r ) =>
				done(
					sprintf(
						/* translators: %d: keywords changed. */
						__( '%d keywords changed.', 'chatbot' ),
						r.changed
					)
				)
			)
			.catch( fail )
			.finally( () => {
				setBusy( '' );
				setBulk( '' );
				setBulkValue( '' );
				setBulkAssign( false );
			} );
	};
	const importCsv = ( event ) => {
		const file = event.target.files && event.target.files[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		if ( file.size > 2 * 1024 * 1024 ) {
			fail(
				new Error(
					__(
						'The file is larger than 2 MB. Split it and import the parts.',
						'chatbot'
					)
				)
			);
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () => {
			setBusy( 'import' );
			seo( 'vault/import', {
				method: 'POST',
				data: { csv: String( reader.result ) },
			} )
				.then( ( r ) =>
					done(
						sprintf(
							/* translators: 1: added, 2: updated, 3: skipped. */
							__(
								'Import finished: %1$d added, %2$d updated, %3$d rows skipped.',
								'chatbot'
							),
							r.added,
							r.updated,
							r.skipped
						)
					)
				)
				.catch( fail )
				.finally( () => setBusy( '' ) );
		};
		reader.readAsText( file );
	};
	const exportCsv = () =>
		seo( `vault/export?${ query() }` )
			.then( ( r ) => download( r.csv, r.filename ) )
			.catch( fail );
	const addManual = () => {
		setBusy( 'add' );
		seo( 'vault/add', { method: 'POST', data: { keyword: manual } } )
			.then( () => {
				setManual( '' );
				return done( __( 'Keyword added.', 'chatbot' ) );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message.text }</p>
		) : (
			<Spinner />
		);
	}

	const header = ( column, label ) => (
		<th>
			<Button variant="link" onClick={ () => sortBy( column ) }>
				{ label }
				{ filters.orderby === column &&
					( 'asc' === filters.order ? ' ↑' : ' ↓' ) }
			</Button>
		</th>
	);
	const pages = Math.max( 1, Math.ceil( view.total / PER_PAGE ) );

	return (
		<div className="qcseo-vault">
			<div className="qcseo-vault-totals">
				{ Object.keys( STATUSES ).map( ( id ) => (
					<Button
						key={ id }
						className={ `qcseo-vault-total is-${ id }` }
						variant={
							filters.status === id ? 'primary' : 'secondary'
						}
						onClick={ () =>
							set( 'status' )( filters.status === id ? '' : id )
						}
					>
						<strong>{ view.totals[ id ] || 0 }</strong>{ ' ' }
						{ STATUSES[ id ] }
					</Button>
				) ) }
			</div>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<div className="qcseo-card">
				<div className="qcseo-grid qcseo-vault-filters">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Search', 'chatbot' ) }
						value={ filters.search }
						onChange={ set( 'search' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Status', 'chatbot' ) }
						value={ filters.status }
						options={ [
							{
								value: '',
								label: __(
									'Active (not archived)',
									'chatbot'
								),
							},
							...Object.keys( STATUSES ).map( ( id ) => ( {
								value: id,
								label: STATUSES[ id ],
							} ) ),
						] }
						onChange={ set( 'status' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Intent', 'chatbot' ) }
						value={ filters.intent }
						options={ [
							{
								value: '',
								label: __( 'Any', 'chatbot' ),
							},
							...Object.keys( INTENTS ).map( ( id ) => ( {
								value: id,
								label: INTENTS[ id ],
							} ) ),
						] }
						onChange={ set( 'intent' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Source', 'chatbot' ) }
						value={ filters.source }
						options={ [
							{
								value: '',
								label: __( 'Any', 'chatbot' ),
							},
							...Object.keys( SOURCES ).map( ( id ) => ( {
								value: id,
								label: SOURCES[ id ],
							} ) ),
						] }
						onChange={ set( 'source' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Trend', 'chatbot' ) }
						value={ filters.trend }
						options={ [
							{
								value: '',
								label: __( 'Any', 'chatbot' ),
							},
							...Object.keys( TRENDS ).map( ( id ) => ( {
								value: id,
								label: TRENDS[ id ],
							} ) ),
						] }
						onChange={ set( 'trend' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Competition', 'chatbot' ) }
						value={ filters.competition }
						options={ [
							{
								value: '',
								label: __( 'Any', 'chatbot' ),
							},
							{
								value: 'low',
								label: __( 'Low', 'chatbot' ),
							},
							{
								value: 'medium',
								label: __( 'Medium', 'chatbot' ),
							},
							{
								value: 'high',
								label: __( 'High', 'chatbot' ),
							},
						] }
						onChange={ set( 'competition' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						type="number"
						min={ 0 }
						label={ __(
							'At least … searches a month',
							'chatbot'
						) }
						value={ filters.min_volume }
						onChange={ set( 'min_volume' ) }
						__nextHasNoMarginBottom
					/>
				</div>

				{ canUse && (
					<div className="qcseo-actions qcseo-vault-tools">
						<TextControl
							__next40pxDefaultSize
							label={ __( 'Add a keyword', 'chatbot' ) }
							hideLabelFromVision
							placeholder={ __(
								'Add a keyword…',
								'chatbot'
							) }
							value={ manual }
							onChange={ setManual }
							__nextHasNoMarginBottom
						/>
						<Button
							variant="secondary"
							disabled={ ! manual.trim() || !! busy }
							isBusy={ 'add' === busy }
							onClick={ addManual }
						>
							{ __( 'Add', 'chatbot' ) }
						</Button>
						<label
							className="components-button is-secondary"
							htmlFor="qcseo-vault-import"
						>
							{ __( 'Import CSV', 'chatbot' ) }
						</label>
						<input
							id="qcseo-vault-import"
							type="file"
							accept=".csv,text/csv"
							className="screen-reader-text"
							onChange={ importCsv }
						/>
						<Button variant="tertiary" onClick={ exportCsv }>
							{ __( 'Export CSV', 'chatbot' ) }
						</Button>
						<span className="qcseo-muted">
							{ __(
								'CSV columns: keyword, then optional language, country, notes. Up to 2 MB and 5,000 rows.',
								'chatbot'
							) }
						</span>
					</div>
				) }

				{ canUse && ids.length > 0 && (
					<div className="qcseo-actions qcseo-vault-bulk">
						<strong>
							{ sprintf(
								/* translators: %d: selected keywords. */
								__( '%d selected', 'chatbot' ),
								ids.length
							) }
						</strong>
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Bulk action', 'chatbot' ) }
							hideLabelFromVision
							value={ bulk }
							options={ [
								{
									value: '',
									label: __(
										'Bulk action…',
										'chatbot'
									),
								},
								{
									value: 'status',
									label: __(
										'Change status',
										'chatbot'
									),
								},
								{
									value: 'assign',
									label: __(
										'Assign to a page',
										'chatbot'
									),
								},
								{
									value: 'topic',
									label: __(
										'Add to a topic',
										'chatbot'
									),
								},
								{
									value: 'ideas',
									label: __(
										'Add to AI Writer ideas',
										'chatbot'
									),
								},
								{
									value: 'archive',
									label: __( 'Archive', 'chatbot' ),
								},
								{
									value: 'delete',
									label: __( 'Delete', 'chatbot' ),
								},
							] }
							onChange={ ( value ) => {
								setBulk( value );
								setBulkValue( '' );
								setBulkAssign( 'assign' === value );
							} }
							__nextHasNoMarginBottom
						/>
						{ 'status' === bulk && (
							<SelectControl
								__next40pxDefaultSize
								label={ __( 'New status', 'chatbot' ) }
								hideLabelFromVision
								value={ bulkValue }
								options={ [
									{ value: '', label: '—' },
									...Object.keys( STATUSES ).map(
										( id ) => ( {
											value: id,
											label: STATUSES[ id ],
										} )
									),
								] }
								onChange={ setBulkValue }
								__nextHasNoMarginBottom
							/>
						) }
						{ 'topic' === bulk && (
							<TextControl
								__next40pxDefaultSize
								label={ __( 'Topic', 'chatbot' ) }
								hideLabelFromVision
								placeholder={ __(
									'Topic name',
									'chatbot'
								) }
								value={ bulkValue }
								onChange={ setBulkValue }
								__nextHasNoMarginBottom
							/>
						) }
						{ [ 'status', 'topic', 'archive', 'delete' ].includes(
							bulk
						) && (
							<Button
								variant="primary"
								isBusy={ 'bulk' === busy }
								disabled={
									!! busy ||
									( [ 'status', 'topic' ].includes( bulk ) &&
										! bulkValue )
								}
								onClick={ () => runBulk( bulk, bulkValue ) }
							>
								{ __( 'Apply', 'chatbot' ) }
							</Button>
						) }
						{ 'ideas' === bulk && (
							<Button
								variant="primary"
								onClick={ () => ideas( ids ) }
							>
								{ __( 'Apply', 'chatbot' ) }
							</Button>
						) }
					</div>
				) }
				{ bulkAssign && (
					<PagePicker
						onPick={ ( postId ) => runBulk( 'assign', postId ) }
						onCancel={ () => {
							setBulkAssign( false );
							setBulk( '' );
						} }
					/>
				) }

				{ 0 === view.items.length ? (
					<p className="qcseo-muted">
						{ __(
							'No keywords here yet. Save keywords from Research, import a CSV, or add one by hand.',
							'chatbot'
						) }
					</p>
				) : (
					<table className="widefat striped qcseo-table qcseo-vault-table">
						<thead>
							<tr>
								<th className="check-column">
									<input
										type="checkbox"
										aria-label={ __(
											'Select all',
											'chatbot'
										) }
										checked={ view.items.every(
											( item ) => picked[ item.id ]
										) }
										onChange={ ( event ) => {
											const on = event.target.checked;
											{
												const next = {};
												view.items.forEach(
													( item ) => {
														next[ item.id ] = on;
													}
												);
												setPicked( next );
											}
										} }
									/>
								</th>
								{ header(
									'keyword',
									__( 'Keyword', 'chatbot' )
								) }
								{ header(
									'status',
									__( 'Status', 'chatbot' )
								) }
								{ header(
									'intent',
									__( 'Intent', 'chatbot' )
								) }
								<th>{ __( 'Topic', 'chatbot' ) }</th>
								<th>{ __( 'Page', 'chatbot' ) }</th>
								{ header(
									'volume',
									__( 'Demand / volume', 'chatbot' )
								) }
								{ header(
									'rank',
									__( 'Rank', 'chatbot' )
								) }
								<th>{ __( 'Actions', 'chatbot' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ view.items.map( ( item ) => (
								<tr key={ item.id }>
									<td>
										<input
											type="checkbox"
											aria-label={ item.keyword }
											checked={ !! picked[ item.id ] }
											onChange={ ( event ) => {
												const on = event.target.checked;
												setPicked( {
													...picked,
													[ item.id ]: on,
												} );
											} }
										/>
									</td>
									<td>
										<strong>{ item.keyword }</strong>
										<small className="qcseo-muted">
											{ ' ' }
											{ [ item.language, item.country ]
												.filter( Boolean )
												.join( ' / ' ) }
										</small>
										{ item.notes && (
											<div className="qcseo-muted">
												{ item.notes }
											</div>
										) }
									</td>
									<td>
										<span
											className={ `qcseo-status is-${ item.status }` }
										>
											{ STATUSES[ item.status ] }
										</span>
									</td>
									<td>{ INTENTS[ item.intent ] || '—' }</td>
									<td>{ item.topic || '—' }</td>
									<td>
										{ item.page ? (
											<a
												href={ item.page.url }
												target="_blank"
												rel="noreferrer"
											>
												{ item.page.title }
											</a>
										) : (
											'—'
										) }
									</td>
									<td>
										{ null !== item.volume ? (
											<>
												<RealFact
													fact={ {
														value: item.volume,
														source: item.volume_label,
													} }
													kind="volume"
												/>
												{ null !== item.cpc && (
													<RealFact
														fact={ {
															value: item.cpc,
															source: item.volume_label,
														} }
														kind="cpc"
													/>
												) }
												<VaultExtras item={ item } />
											</>
										) : (
											<AiLabel
												level={ item.demand }
												reason={ item.context.reason }
											/>
										) }
									</td>
									<td>
										{ null !== item.rank ? (
											<span title={ item.rank_at }>
												{ item.rank }{ ' ' }
												<SourceBadge
													source={ item.rank_label }
												/>
											</span>
										) : (
											'—'
										) }
									</td>
									<td className="qcseo-vault-actions">
										{ canUse && data.writer && (
											<Button
												variant="link"
												onClick={ () =>
													write( item.id )
												}
											>
												{ __(
													'Write',
													'chatbot'
												) }
											</Button>
										) }
										{ canUse && (
											<Button
												variant="link"
												onClick={ () =>
													setAssigning( item.id )
												}
											>
												{ __(
													'Assign to page',
													'chatbot'
												) }
											</Button>
										) }
										{ canUse && data.writer && (
											<Button
												variant="link"
												onClick={ () =>
													ideas( [ item.id ] )
												}
											>
												{ __(
													'Add to calendar',
													'chatbot'
												) }
											</Button>
										) }
										{ canUse &&
											'archived' !== item.status && (
												<Button
													variant="link"
													onClick={ () =>
														seo(
															`vault/${ item.id }`,
															{
																method: 'POST',
																data: {
																	status: 'archived',
																},
															}
														)
															.then( () =>
																done( '' )
															)
															.catch( fail )
													}
												>
													{ __(
														'Archive',
														'chatbot'
													) }
												</Button>
											) }
										{ assigning === item.id && (
											<PagePicker
												onPick={ ( postId ) =>
													assign( item.id, postId )
												}
												onCancel={ () =>
													setAssigning( 0 )
												}
											/>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
				{ pages > 1 && (
					<div className="qcseo-pager">
						<Button
							variant="tertiary"
							disabled={ page <= 1 }
							onClick={ () => setPage( page - 1 ) }
						>
							{ __( '← Previous', 'chatbot' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: 1: page, 2: pages. */
								__( 'Page %1$d of %2$d', 'chatbot' ),
								page,
								pages
							) }
						</span>
						<Button
							variant="tertiary"
							disabled={ page >= pages }
							onClick={ () => setPage( page + 1 ) }
						>
							{ __( 'Next →', 'chatbot' ) }
						</Button>
					</div>
				) }
			</div>
		</div>
	);
};

export default Vault;
