/**
 * Keyword Map: each page's main and supporting searches from Search
 * Console (shared with AI Writer). Accept, change or lock; AI suggestions
 * for pages without data; focus keyword into the SEO plugin; gaps.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { num, pos } from '../../format';
import JobProgress from '../JobProgress';
import { AiReason, aiBlocked, useAiReady } from '../../ai';

const SOURCE = {
	search_console: __( 'Search Console', 'chatbot' ),
	manual: __( 'You', 'chatbot' ),
	ai: __( 'AI', 'chatbot' ),
	title: __( 'Title', 'chatbot' ),
};

const Editor = ( { item, onDone } ) => {
	const aiOff = aiBlocked( useAiReady() );
	const [ main, setMain ] = useState( item.main );
	const [ supporting, setSupporting ] = useState(
		item.supporting.join( '\n' )
	);
	const [ locked, setLocked ] = useState( item.locked );
	const [ message, setMessage ] = useState( '' );
	const suggest = () =>
		seo( `map/${ item.post_id }/suggest`, { method: 'POST' } )
			.then( ( s ) => {
				setMain( s.main );
				setSupporting( s.supporting.join( '\n' ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	return (
		<div className="qcseo-grid qcseo-map-editor">
			<TextControl
				__next40pxDefaultSize
				label={ __( 'Main search', 'chatbot' ) }
				value={ main }
				onChange={ setMain }
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __(
					'Supporting searches (one per line)',
					'chatbot'
				) }
				value={ supporting }
				onChange={ setSupporting }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Lock', 'chatbot' ) }
				value={ locked ? '1' : '' }
				options={ [
					{
						value: '',
						label: __( 'Not locked', 'chatbot' ),
					},
					{
						value: '1',
						label: __(
							'Locked: never changed automatically',
							'chatbot'
						),
					},
				] }
				onChange={ ( v ) => setLocked( '1' === v ) }
				__nextHasNoMarginBottom
			/>
			<div>
				<Button
					variant="primary"
					onClick={ () =>
						seo( `map/${ item.post_id }`, {
							method: 'POST',
							data: {
								main,
								supporting: supporting.split( '\n' ),
								locked,
							},
						} )
							.then( onDone )
							.catch( ( e ) => setMessage( errorMessage( e ) ) )
					}
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>{ ' ' }
				{ ! item.has_data && (
					<Button
						variant="secondary"
						onClick={ suggest }
						disabled={ !! aiOff }
					>
						{ __( 'Suggest with AI', 'chatbot' ) }
					</Button>
				) }{ ' ' }
				{ ! item.has_data && aiOff && <AiReason /> }
				<Button variant="tertiary" onClick={ () => onDone( false ) }>
					{ __( 'Cancel', 'chatbot' ) }
				</Button>
			</div>
			{ message && <p className="qcseo-error">{ message }</p> }
		</div>
	);
};

const Gaps = () => {
	const [ gaps, setGaps ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'map/gaps' )
			.then( ( r ) => setGaps( r.gaps ) )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );
	if ( ! gaps ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	return (
		<div className="qcseo-card qcseo-map-gaps">
			<h3>
				{ __( 'Gaps: searches no page targets', 'chatbot' ) }
			</h3>
			{ 0 === gaps.length ? (
				<p className="qcseo-muted">
					{ __(
						'Every search with real impressions has a page.',
						'chatbot'
					) }
				</p>
			) : (
				<table className="widefat striped qcseo-table">
					<thead>
						<tr>
							<th>{ __( 'Search', 'chatbot' ) }</th>
							<th className="num">
								{ __( 'Searches a month', 'chatbot' ) }
							</th>
							<th className="num">
								{ __(
									'Impressions (90 days)',
									'chatbot'
								) }
							</th>
							<th className="num">
								{ __( 'Position', 'chatbot' ) }
							</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ gaps.slice( 0, 30 ).map( ( g ) => (
							<tr key={ g.query }>
								<td>{ g.query }</td>
								<td className="num">
									{ null === g.volume
										? '—'
										: num( g.volume ) }
								</td>
								<td className="num">
									{ num( g.impressions ) }
								</td>
								<td className="num">{ pos( g.position ) }</td>
								<td>
									{ data.writer && data.can.use && (
										<Button
											variant="link"
											onClick={ () =>
												seo( 'keywords/ideas/write', {
													method: 'POST',
													data: { keyword: g.query },
												} ).then( ( r ) => {
													window.location.href =
														r.url;
												} )
											}
										>
											{ __(
												'Write a page',
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
	);
};

const KeywordMap = () => {
	const [ filter, setFilter ] = useState( '' );
	const [ view, setView ] = useState( null );
	const [ open, setOpen ] = useState( 0 );
	const [ job, setJob ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const load = useCallback( () => {
		seo( `map?filter=${ filter }` )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [ filter ] );
	useEffect( load, [ load ] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const act = ( promise ) =>
		promise.then( load ).catch( ( e ) => setMessage( errorMessage( e ) ) );
	return (
		<div className="qcseo-keyword-map">
			<div className="qcseo-card">
				<div className="qcseo-head">
					<h3>{ __( 'Keyword Map', 'chatbot' ) }</h3>
					{ data.can.use && (
						<Button
							variant="secondary"
							disabled={ !! job }
							onClick={ () =>
								seo( 'map/refresh', { method: 'POST' } )
									.then( ( r ) =>
										r.job_id ? setJob( r.job_id ) : load()
									)
									.catch( ( e ) =>
										setMessage( errorMessage( e ) )
									)
							}
						>
							{ __(
								'Update from Search Console',
								'chatbot'
							) }
						</Button>
					) }
				</div>
				<p className="qcseo-muted">
					{ __(
						'Which search each page targets, from its own Search Console data. Shared with AI Writer. Your changes and locks are never overwritten; the map updates after each Search Console import.',
						'chatbot'
					) }
				</p>
				{ job > 0 && (
					<JobProgress
						jobId={ job }
						onChange={ ( state ) => {
							if ( state && 'done' === state.status ) {
								setJob( 0 );
								load();
							}
						} }
					/>
				) }
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Show', 'chatbot' ) }
					value={ filter }
					options={ [
						{
							value: '',
							label: __( 'All pages', 'chatbot' ),
						},
						{
							value: 'mapped',
							label: sprintf(
								/* translators: %d: pages. */ __(
									'Mapped (%d)',
									'chatbot'
								),
								view.counts.mapped
							),
						},
						{
							value: 'unmapped',
							label: sprintf(
								/* translators: %d: pages. */ __(
									'Not mapped yet (%d)',
									'chatbot'
								),
								view.counts.unmapped
							),
						},
						{
							value: 'nodata',
							label: sprintf(
								/* translators: %d: pages. */ __(
									'No data yet (%d)',
									'chatbot'
								),
								view.counts.nodata
							),
						},
					] }
					onChange={ setFilter }
					__nextHasNoMarginBottom
				/>
				{ message && <p className="qcseo-error">{ message }</p> }
				<table className="widefat striped qcseo-table qcseo-map-table">
					<thead>
						<tr>
							<th>{ __( 'Page', 'chatbot' ) }</th>
							<th>{ __( 'Main search', 'chatbot' ) }</th>
							<th className="num">
								{ __( 'Clicks', 'chatbot' ) }
							</th>
							<th className="num">
								{ __( 'Position', 'chatbot' ) }
							</th>
							{ view.keywords && (
								<th className="num">
									{ __(
										'Searches a month',
										'chatbot'
									) }
								</th>
							) }
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ view.items.map( ( item ) => (
							<tr key={ item.post_id }>
								<td>
									<a
										href={ item.url }
										target="_blank"
										rel="noreferrer noopener"
									>
										{ item.title }
									</a>
									{ open === item.post_id && (
										<Editor
											item={ item }
											onDone={ ( changed ) => {
												setOpen( 0 );
												if ( false !== changed ) {
													load();
												}
											} }
										/>
									) }
								</td>
								<td>
									{ item.main ? (
										<>
											<strong>{ item.main }</strong>{ ' ' }
											<span className="qcseo-badge">
												{ SOURCE[ item.source ] ||
													item.source }
											</span>
											{ item.locked && (
												<span className="qcseo-badge">
													{ __(
														'Locked',
														'chatbot'
													) }
												</span>
											) }
											{ item.supporting.length > 0 && (
												<>
													<br />
													<small className="qcseo-muted">
														{ item.supporting.join(
															' · '
														) }
													</small>
												</>
											) }
										</>
									) : (
										<span className="qcseo-muted">
											{ item.has_data
												? __(
														'Not mapped yet',
														'chatbot'
													)
												: __(
														'No data yet',
														'chatbot'
													) }
										</span>
									) }
								</td>
								<td className="num">
									{ item.numbers
										? num( item.numbers.clicks )
										: '—' }
								</td>
								<td className="num">
									{ item.numbers
										? pos( item.numbers.position )
										: '—' }
								</td>
								{ view.keywords && (
									<td className="num">
										{ null === item.volume
											? '—'
											: num( item.volume ) }
									</td>
								) }
								<td>
									{ item.can_edit && data.can.use && (
										<>
											<Button
												variant="link"
												onClick={ () =>
													setOpen( item.post_id )
												}
											>
												{ item.main
													? __(
															'Change',
															'chatbot'
														)
													: __(
															'Set',
															'chatbot'
														) }
											</Button>{ ' ' }
											{ item.main &&
												'manual' !== item.source && (
													<Button
														variant="link"
														onClick={ () =>
															act(
																seo(
																	`map/${ item.post_id }/accept`,
																	{
																		method: 'POST',
																	}
																)
															)
														}
													>
														{ __(
															'Accept',
															'chatbot'
														) }
													</Button>
												) }{ ' ' }
											{ item.main && (
												<Button
													variant="link"
													onClick={ () =>
														act(
															seo(
																`map/${ item.post_id }/accept`,
																{
																	method: 'POST',
																	data: {
																		locked: ! item.locked,
																	},
																}
															)
														)
													}
												>
													{ item.locked
														? __(
																'Unlock',
																'chatbot'
															)
														: __(
																'Lock',
																'chatbot'
															) }
												</Button>
											) }{ ' ' }
											{ item.main &&
												'' === item.focus &&
												data.can.publish && (
													<Button
														variant="link"
														onClick={ () =>
															act(
																seo(
																	`map/${ item.post_id }/focus`,
																	{
																		method: 'POST',
																	}
																)
															)
														}
													>
														{ __(
															'Use as focus keyword',
															'chatbot'
														) }
													</Button>
												) }
										</>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			<Gaps />
		</div>
	);
};

export default KeywordMap;
