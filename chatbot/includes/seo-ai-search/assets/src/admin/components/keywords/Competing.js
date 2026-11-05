/**
 * Competing pages: several of your pages split one search. Keep the
 * stronger page; for the others, target another search, link to the
 * stronger page, or merge and redirect.
 */
import { Button, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import { num, pos } from '../../format';

const Competing = () => {
	const [ cases, setCases ] = useState( null );
	const [ target, setTarget ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'competing' )
			.then( ( r ) => setCases( r.cases ) )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	useEffect( () => {
		load();
	}, [] );
	if ( ! cases ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const fix = ( route, payload, done ) =>
		seo( route, { method: 'POST', data: payload } )
			.then( ( r ) => {
				setMessage( done );
				if ( r.url ) {
					window.location.href = r.url;
					return;
				}
				load();
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	return (
		<div className="qcseo-card qcseo-competing">
			<h3>{ __( 'Competing pages', 'chatbot' ) }</h3>
			<p className="qcseo-muted">
				{ __(
					'When several of your pages show up for one search, Google splits the traffic between them. Make one page the answer.',
					'chatbot'
				) }
			</p>
			{ message && <p className="qcseo-message">{ message }</p> }
			{ 0 === cases.length && (
				<p className="qcseo-muted">
					{ __(
						'No pages compete for the same search.',
						'chatbot'
					) }
				</p>
			) }
			{ cases.map( ( c ) => {
				const strong =
					c.pages.find( ( p ) => p.strong ) || c.pages[ 0 ];
				return (
					<div key={ c.query } className="qcseo-competing-case">
						<h4>
							“{ c.query }”{ ' ' }
							<small className="qcseo-muted">
								{ 'map' === c.source
									? __(
											'same main search in the keyword map',
											'chatbot'
										)
									: sprintf(
											/* translators: %s: impressions. */
											__(
												'%s impressions in 28 days',
												'chatbot'
											),
											num( c.impressions )
										) }
							</small>
						</h4>
						<table className="widefat striped qcseo-table">
							<tbody>
								{ c.pages.map( ( p ) => (
									<tr key={ p.post_id || p.url }>
										<td>
											<a
												href={ p.url }
												target="_blank"
												rel="noreferrer noopener"
											>
												{ p.title || p.url }
											</a>
											{ p.strong && (
												<span className="qcseo-badge">
													{ __(
														'Keep this one',
														'chatbot'
													) }
												</span>
											) }
										</td>
										<td className="num">
											{ undefined !== p.share
												? `${ Math.round( p.share * 100 ) }%`
												: '' }
										</td>
										<td className="num">
											{ p.position
												? pos( p.position )
												: '' }
										</td>
										<td>
											{ ! p.strong &&
												p.post_id > 0 &&
												strong.post_id > 0 && (
													<>
														{ data.can.publish && (
															<Button
																variant="link"
																onClick={ () =>
																	fix(
																		'competing/fix',
																		{
																			fix: 'link',
																			weak: p.post_id,
																			strong: strong.post_id,
																			query: c.query,
																		},
																		__(
																			'Link added.',
																			'chatbot'
																		)
																	)
																}
															>
																{ __(
																	'Link to the stronger page',
																	'chatbot'
																) }
															</Button>
														) }
														{ data.can.publish && (
															<span className="qcseo-retarget">
																<TextControl
																	__next40pxDefaultSize
																	label={ __(
																		'Target another search',
																		'chatbot'
																	) }
																	hideLabelFromVision
																	placeholder={ __(
																		'Another search for this page',
																		'chatbot'
																	) }
																	value={
																		target[
																			p
																				.post_id
																		] || ''
																	}
																	onChange={ (
																		v
																	) =>
																		setTarget(
																			{
																				...target,
																				[ p.post_id ]:
																					v,
																			}
																		)
																	}
																	__nextHasNoMarginBottom
																/>
																<Button
																	variant="link"
																	disabled={
																		! target[
																			p
																				.post_id
																		]
																	}
																	onClick={ () =>
																		fix(
																			'competing/fix',
																			{
																				fix: 'retarget',
																				weak: p.post_id,
																				strong: strong.post_id,
																				query: c.query,
																				target: target[
																					p
																						.post_id
																				],
																			},
																			__(
																				'Main search changed and locked.',
																				'chatbot'
																			)
																		)
																	}
																>
																	{ __(
																		'Retarget',
																		'chatbot'
																	) }
																</Button>
															</span>
														) }
														{ data.can.settings && (
															<Button
																variant="link"
																isDestructive
																onClick={ () =>
																	fix(
																		'competing/merge',
																		{
																			weak: p.post_id,
																			strong: strong.post_id,
																			query: c.query,
																		},
																		__(
																			'Redirected and set to draft.',
																			'chatbot'
																		)
																	)
																}
															>
																{ __(
																	'Merge and redirect',
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
				);
			} ) }
		</div>
	);
};

export default Competing;
