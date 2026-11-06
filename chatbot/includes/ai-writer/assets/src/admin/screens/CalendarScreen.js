/**
 * Content calendar: month and week views of WordPress posts by their dates
 * (site time zone), drag to reschedule drafts and scheduled posts, click a
 * day to write, an ideas list to drag from, filters and warnings.
 *
 * Every item also has a keyboard path: open it and pick a new date.
 */
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import ArticleRegenerate from '../components/regenerate/ArticleRegenerate';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import { ProSpots } from '../components/pro/ProPreview';

const pad = ( n ) => String( n ).padStart( 2, '0' );
const ymd = ( d ) =>
	`${ d.getFullYear() }-${ pad( d.getMonth() + 1 ) }-${ pad( d.getDate() ) }`;
// Dates are plain calendar days (site time zone): build them at noon to avoid DST edges.
const parse = ( value ) => {
	const [ y, m, d ] = value.split( '-' ).map( Number );
	return new Date( y, m - 1, d, 12 );
};
const addDays = ( d, n ) => {
	const next = new Date( d );
	next.setDate( next.getDate() + n );
	return next;
};

const STATUS_LABELS = () => ( {
	draft: __( 'Draft', 'chatbot' ),
	pending: __( 'Pending', 'chatbot' ),
	future: __( 'Scheduled', 'chatbot' ),
	publish: __( 'Published', 'chatbot' ),
	private: __( 'Private', 'chatbot' ),
} );

/**
 * Days shown for a view.
 *
 * @param {string} view   month or week.
 * @param {Date}   anchor A day in the period.
 * @return {Date[]} Days.
 */
const visibleDays = ( view, anchor ) => {
	const startOfWeek = ( data.calendar && data.calendar.startOfWeek ) || 0;
	const first =
		'month' === view
			? new Date( anchor.getFullYear(), anchor.getMonth(), 1, 12 )
			: new Date( anchor );
	const start = addDays(
		first,
		-( ( first.getDay() - startOfWeek + 7 ) % 7 )
	);
	const count = 'month' === view ? 42 : 7;
	return Array.from( { length: count }, ( unused, i ) =>
		addDays( start, i )
	);
};

const Item = ( { item, onOpen, warned } ) => (
	<button
		type="button"
		className={ `qcwr-cal__item is-${ item.status }${ item.floating ? ' is-floating' : '' }${ warned ? ' is-warned' : '' }` }
		draggable={ item.movable }
		onDragStart={ ( event ) => {
			event.dataTransfer.setData(
				'text/plain',
				JSON.stringify( { kind: 'post', id: item.post_id } )
			);
			event.dataTransfer.effectAllowed = 'move';
		} }
		onClick={ () => onOpen( item ) }
		title={ item.title }
	>
		<span className="qcwr-cal__time">
			{ item.floating ? '—' : item.date.slice( 11, 16 ) }
		</span>{ ' ' }
		{ item.title }
	</button>
);

const CalendarScreen = () => {
	const [ view, setView ] = useState( 'month' );
	const [ anchor, setAnchor ] = useState(
		parse( data.calendar ? data.calendar.today : ymd( new Date() ) )
	);
	const [ result, setResult ] = useState( null );
	const [ ideas, setIdeas ] = useState( [] );
	const [ newIdea, setNewIdea ] = useState( '' );
	const [ filters, setFilters ] = useState( {
		post_types: [],
		statuses: [],
		mine: false,
	} );
	const [ open, setOpen ] = useState( null );
	const [ dayMenu, setDayMenu ] = useState( null );
	const [ moveTo, setMoveTo ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const days = visibleDays( view, anchor );
	const start = ymd( days[ 0 ] );
	const end = ymd( days[ days.length - 1 ] );
	const labels = STATUS_LABELS();
	const postTypes = ( data.calendar && data.calendar.postTypes ) || {};

	const load = () => {
		const query = new URLSearchParams( { start, end } );
		filters.post_types.forEach( ( type ) =>
			query.append( 'post_types[]', type )
		);
		filters.statuses.forEach( ( status ) =>
			query.append( 'statuses[]', status )
		);
		if ( filters.mine ) {
			query.append( 'mine', '1' );
		} else if ( filters.author ) {
			query.append( 'author', String( filters.author ) );
		}
		writer( `calendar?${ query.toString() }` )
			.then( setResult )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			);
	};
	const loadIdeas = () =>
		writer( 'calendar/ideas' )
			.then( setIdeas )
			.catch( () => {} );

	useEffect( load, [ start, end, filters ] );
	useEffect( () => {
		loadIdeas();
	}, [] );

	const done = ( text ) => {
		setMessage( { status: 'success', text } );
		load();
	};
	const fail = ( err ) =>
		setMessage( { status: 'error', text: errorMessage( err ) } );

	const move = ( postId, date ) =>
		writer( 'calendar/move', {
			method: 'POST',
			data: { post_id: postId, date },
		} )
			.then( () =>
				done(
					__(
						'Moved. The post itself has the new date.',
						'chatbot'
					)
				)
			)
			.catch( fail );
	const place = ( ideaId, date ) =>
		writer( `calendar/ideas/${ ideaId }/place`, {
			method: 'POST',
			data: { date },
		} )
			.then( () => {
				loadIdeas();
				done(
					__( 'A draft was created for that day.', 'chatbot' )
				);
			} )
			.catch( fail );

	const onDrop = ( event, date ) => {
		event.preventDefault();
		event.currentTarget.classList.remove( 'is-over' );
		let payload = null;
		try {
			payload = JSON.parse( event.dataTransfer.getData( 'text/plain' ) );
		} catch {
			return;
		}
		if ( payload && 'post' === payload.kind ) {
			move( payload.id, date );
		} else if ( payload && 'idea' === payload.kind ) {
			place( payload.id, date );
		}
	};

	const shift = ( direction ) =>
		setAnchor(
			'month' === view
				? new Date(
						anchor.getFullYear(),
						anchor.getMonth() + direction,
						1,
						12
					)
				: addDays( anchor, 7 * direction )
		);

	const items = result ? result.items : [];
	const warnings = result ? result.warnings : [];
	const warnedIds = new Set( warnings.flatMap( ( warning ) => warning.ids ) );
	const title =
		'month' === view
			? anchor.toLocaleDateString( undefined, {
					month: 'long',
					year: 'numeric',
				} )
			: `${ days[ 0 ].toLocaleDateString() } – ${ days[ 6 ].toLocaleDateString() }`;
	const weekdays = days
		.slice( 0, 7 )
		.map( ( d ) =>
			d.toLocaleDateString( undefined, { weekday: 'short' } )
		);
	const today = data.calendar ? data.calendar.today : ymd( new Date() );

	return (
		<div className="qcwr-cal">
			<div className="qcwr-cal__bar">
				<div className="qcwr-actions">
					<Button
						variant="secondary"
						onClick={ () => shift( -1 ) }
						label={ __( 'Previous', 'chatbot' ) }
					>
						‹
					</Button>
					<Button
						variant="secondary"
						onClick={ () => setAnchor( parse( today ) ) }
					>
						{ __( 'Today', 'chatbot' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => shift( 1 ) }
						label={ __( 'Next', 'chatbot' ) }
					>
						›
					</Button>
					<h2 className="qcwr-cal__title">{ title }</h2>
				</div>
				<div className="qcwr-actions">
					<Button
						variant={ 'month' === view ? 'primary' : 'secondary' }
						onClick={ () => setView( 'month' ) }
					>
						{ __( 'Month', 'chatbot' ) }
					</Button>
					<Button
						variant={ 'week' === view ? 'primary' : 'secondary' }
						onClick={ () => setView( 'week' ) }
					>
						{ __( 'Week', 'chatbot' ) }
					</Button>
				</div>
			</div>
			{ applyFilters( 'qcld_wpbot_writer.calendar.panels', null, {
				start,
				end,
				reload: () => {
					load();
					loadIdeas();
				},
			} ) }
			<ProSpots
				spots={ [
					'calendar.ai_plan',
					'calendar.drafts_ahead',
					'calendar.reminders',
				] }
			/>
			<div className="qcwr-cal__filters">
				{ applyFilters( 'qcld_wpbot_writer.calendar.filters', null, {
					filters,
					setFilters,
				} ) }
				<SelectControl
					label={ __( 'Show', 'chatbot' ) }
					value={ filters.post_types[ 0 ] || '' }
					options={ [
						{
							value: '',
							label: __( 'All content', 'chatbot' ),
						},
						...Object.keys( postTypes ).map( ( type ) => ( {
							value: type,
							label: postTypes[ type ],
						} ) ),
					] }
					onChange={ ( value ) =>
						setFilters( {
							...filters,
							post_types: value ? [ value ] : [],
						} )
					}
					__nextHasNoMarginBottom
				/>
				{ Object.keys( labels ).map( ( status ) => (
					<CheckboxControl
						key={ status }
						label={ labels[ status ] }
						checked={
							! filters.statuses.length ||
							filters.statuses.includes( status )
						}
						onChange={ ( checked ) => {
							const current = filters.statuses.length
								? filters.statuses
								: Object.keys( labels );
							const next = checked
								? [ ...current, status ]
								: current.filter( ( s ) => s !== status );
							setFilters( {
								...filters,
								statuses:
									next.length === Object.keys( labels ).length
										? []
										: next,
							} );
						} }
						__nextHasNoMarginBottom
					/>
				) ) }
				<CheckboxControl
					label={ __( 'Only mine', 'chatbot' ) }
					checked={ filters.mine }
					onChange={ ( mine ) => setFilters( { ...filters, mine } ) }
					__nextHasNoMarginBottom
				/>
			</div>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ warnings.length > 0 && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="qcwr-cal__warnings"
				>
					<ul>
						{ warnings.map( ( warning, index ) => (
							<li key={ index }>
								<strong>
									{ parse(
										warning.date
									).toLocaleDateString() }
									:
								</strong>{ ' ' }
								{ warning.message }
							</li>
						) ) }
					</ul>
				</Notice>
			) }

			<div className="qcwr-cal__body">
				<div className={ `qcwr-cal__grid is-${ view }` }>
					{ weekdays.map( ( name ) => (
						<div key={ name } className="qcwr-cal__weekday">
							{ name }
						</div>
					) ) }
					{ ! result && <Spinner /> }
					{ result &&
						days.map( ( d ) => {
							const key = ymd( d );
							const dayItems = items.filter(
								( item ) => item.date.slice( 0, 10 ) === key
							);
							const outside =
								'month' === view &&
								d.getMonth() !== anchor.getMonth();
							return (
								<div
									key={ key }
									className={ `qcwr-cal__day${ outside ? ' is-outside' : '' }${ key === today ? ' is-today' : '' }` }
									data-date={ key }
									onDragOver={ ( event ) => {
										event.preventDefault();
										event.currentTarget.classList.add(
											'is-over'
										);
									} }
									onDragLeave={ ( event ) =>
										event.currentTarget.classList.remove(
											'is-over'
										)
									}
									onDrop={ ( event ) => onDrop( event, key ) }
								>
									<button
										type="button"
										className="qcwr-cal__date"
										onClick={ () => setDayMenu( key ) }
										aria-label={ sprintf(
											/* translators: %s: date. */ __(
												'Plan something on %s',
												'chatbot'
											),
											d.toLocaleDateString()
										) }
									>
										{ d.getDate() }
									</button>
									{ ( 'month' === view
										? dayItems.slice( 0, 6 )
										: dayItems
									).map( ( item ) => (
										<Item
											key={ item.id }
											item={ item }
											warned={ warnedIds.has( item.id ) }
											onOpen={ ( next ) => {
												setOpen( next );
												setMoveTo(
													next.date.slice( 0, 10 )
												);
											} }
										/>
									) ) }
									{ 'month' === view &&
										dayItems.length > 6 && (
											<Button
												variant="link"
												className="qcwr-cal__more"
												onClick={ () => {
													setAnchor( d );
													setView( 'week' );
												} }
											>
												{ sprintf(
													/* translators: %d: number of items. */
													__(
														'+%d more',
														'chatbot'
													),
													dayItems.length - 6
												) }
											</Button>
										) }
								</div>
							);
						} ) }
				</div>

				<aside className="qcwr-cal__ideas">
					<h3>{ __( 'Ideas', 'chatbot' ) }</h3>
					<p className="qcwr-muted">
						{ __(
							'Drag an idea onto a day to plan a draft there.',
							'chatbot'
						) }
					</p>
					{ data.can.use && (
						<div className="qcwr-actions">
							<TextControl
								label={ __( 'New idea', 'chatbot' ) }
								hideLabelFromVision
								placeholder={ __(
									'New idea',
									'chatbot'
								) }
								value={ newIdea }
								onChange={ setNewIdea }
								__nextHasNoMarginBottom
							/>
							<Button
								variant="secondary"
								disabled={ ! newIdea.trim() }
								onClick={ () =>
									writer( 'calendar/ideas', {
										method: 'POST',
										data: { title: newIdea },
									} )
										.then( () => {
											setNewIdea( '' );
											loadIdeas();
										} )
										.catch( fail )
								}
							>
								{ __( 'Add', 'chatbot' ) }
							</Button>
						</div>
					) }
					<ul>
						{ ideas.map( ( idea ) => (
							<li key={ idea.id }>
								<span
									className="qcwr-cal__idea"
									draggable={ data.can.use }
									onDragStart={ ( event ) =>
										event.dataTransfer.setData(
											'text/plain',
											JSON.stringify( {
												kind: 'idea',
												id: idea.id,
											} )
										)
									}
								>
									{ idea.title }
								</span>
								<Button
									variant="link"
									isDestructive
									label={ __(
										'Remove idea',
										'chatbot'
									) }
									onClick={ () =>
										writer( `calendar/ideas/${ idea.id }`, {
											method: 'DELETE',
										} )
											.then( loadIdeas )
											.catch( fail )
									}
								>
									×
								</Button>
							</li>
						) ) }
					</ul>
				</aside>
			</div>

			{ open && (
				<Modal
					title={ open.title }
					onRequestClose={ () => setOpen( null ) }
					size="small"
				>
					<p>
						{ open.status_label ||
							labels[ open.status ] ||
							open.status }{ ' ' }
						· { open.type_label } · { open.author }
					</p>
					{ open.floating && (
						<p className="qcwr-muted">
							{ __(
								'This draft has no date yet: it shows on its last saved day. Moving it gives it a date.',
								'chatbot'
							) }
						</p>
					) }
					{ 'post' === open.type &&
						open.edit_url &&
						data.can.use &&
						( data.formats || [] ).length > 0 && (
							<SelectControl
								label={ __( 'Format', 'chatbot' ) }
								help={ __(
									'AI Writer writes this piece in this format (drafts ahead and Write with AI in the editor).',
									'chatbot'
								) }
								value={ open.format || '' }
								options={ [
									{
										value: '',
										label: __(
											'Article (no format)',
											'chatbot'
										),
									},
									...data.formats.map( ( format ) => ( {
										value: format.value,
										label: format.label,
									} ) ),
								] }
								onChange={ ( format ) =>
									writer(
										`calendar/items/${ open.post_id }/format`,
										{ method: 'POST', data: { format } }
									)
										.then( ( item ) => {
											setOpen( { ...open, ...item } );
											load();
										} )
										.catch( () => {} )
								}
								__nextHasNoMarginBottom
							/>
						) }
					{ open.unfinished && open.unfinished.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ open.unfinished.join( ', ' ) }
						</Notice>
					) }
					{ open.movable && (
						<div className="qcwr-actions">
							<TextControl
								type="date"
								label={ __( 'Move to', 'chatbot' ) }
								value={ moveTo }
								onChange={ setMoveTo }
								__nextHasNoMarginBottom
							/>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={ busy || ! moveTo }
								onClick={ () => {
									setBusy( true );
									move( open.post_id, moveTo ).finally(
										() => {
											setBusy( false );
											setOpen( null );
										}
									);
								} }
							>
								{ __( 'Move', 'chatbot' ) }
							</Button>
						</div>
					) }
					{ open.note && <p>{ open.note }</p> }
					{ open.action_url && (
						<p>
							<a
								className="components-button is-primary"
								href={ open.action_url }
							>
								{ open.action_label }
							</a>
						</p>
					) }
					{ open.edit_url && (
						<p>
							<a href={ open.edit_url }>
								{ __(
									'Open in the editor',
									'chatbot'
								) }
							</a>
						</p>
					) }
					{ 'post' === open.type && open.edit_url && data.can.use && (
						// CR-14: rewrite the whole draft or post from here.
						<details className="qcwr-calendar__regenerate">
							<summary>
								{ __( 'Regenerate', 'chatbot' ) }
							</summary>
							<ArticleRegenerate
								postId={ open.post_id }
								isDraft={ [
									'draft',
									'pending',
									'auto-draft',
								].includes( open.status ) }
								onDone={ load }
							/>
						</details>
					) }
				</Modal>
			) }
			{ dayMenu && (
				<Modal
					title={ parse( dayMenu ).toLocaleDateString() }
					onRequestClose={ () => setDayMenu( null ) }
					size="small"
				>
					{ data.can.use ? (
						<>
							<p>
								<a
									className="components-button is-primary"
									href={ `${ data.adminUrl }admin.php?page=wpbot-ai-writer&date=${ dayMenu }` }
								>
									{ __(
										'Write with AI for this day',
										'chatbot'
									) }
								</a>
							</p>
							{ ideas.length > 0 && (
								<>
									<p>
										{ __(
											'Or plan one of your ideas here:',
											'chatbot'
										) }
									</p>
									<ul className="qcwr-cal__pick">
										{ ideas
											.slice( 0, 10 )
											.map( ( idea ) => (
												<li key={ idea.id }>
													<Button
														variant="link"
														onClick={ () => {
															place(
																idea.id,
																dayMenu
															);
															setDayMenu( null );
														} }
													>
														{ idea.title }
													</Button>
												</li>
											) ) }
									</ul>
								</>
							) }
						</>
					) : (
						<p>
							{ __(
								'Ask an administrator for the AI Writer "Use" permission to plan content.',
								'chatbot'
							) }
						</p>
					) }
				</Modal>
			) }
		</div>
	);
};

export default CalendarScreen;
