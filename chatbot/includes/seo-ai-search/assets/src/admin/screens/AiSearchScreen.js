/**
 * AI Search: readiness checklist per page (with fixes), AI crawler rules,
 * and the Visibility tab (Pro; a labelled preview in Free hosts).
 */
import {
	Button,
	SelectControl,
	Spinner,
	TabPanel,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { data, errorMessage, seo } from '../api';
import ProPreview, { withPreviewTabs } from '../components/ProPreview';
import { navigate } from '../nav';

const Readiness = () => {
	const [ view, setView ] = useState( null );
	const [ open, setOpen ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		seo( 'readiness' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	}, [] );
	const fix = ( page, item ) => {
		if ( 'crawlers' === item.fix ) {
			navigate( 'ai-search' );
			return;
		}
		if ( 'schema' === item.fix ) {
			navigate( 'technical' );
			return;
		}
		seo( `readiness/${ page.post_id }/fix`, {
			method: 'POST',
			data: { item: item.id },
		} )
			.then( ( result ) => {
				window.location.href = result.url;
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	};
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	return (
		<div>
			<p className="qcseo-muted">
				{ __(
					"How easy each page is for AI assistants and Google's answers to quote. Your most-visited pages come first.",
					'chatbot'
				) }
			</p>
			{ message && <p className="qcseo-error">{ message }</p> }
			<table className="widefat striped qcseo-table">
				<thead>
					<tr>
						<th>{ __( 'Page', 'chatbot' ) }</th>
						<th className="num">
							{ __( 'Ready', 'chatbot' ) }
						</th>
						<th>{ __( 'To do', 'chatbot' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ view.pages.map( ( page ) => {
						const todo = page.items.filter(
							( item ) => ! item.pass
						);
						return (
							<tr key={ page.post_id }>
								<td>
									<Button
										variant="link"
										onClick={ () =>
											setOpen(
												open === page.post_id
													? 0
													: page.post_id
											)
										}
									>
										{ page.title }
									</Button>
									{ open === page.post_id && (
										<ul className="qcseo-readiness">
											{ page.items.map( ( item ) => (
												<li
													key={ item.id }
													className={
														item.pass
															? 'is-pass'
															: 'is-fail'
													}
												>
													<span aria-hidden="true">
														{ item.pass
															? '✓'
															: '•' }
													</span>{ ' ' }
													<strong>
														{ item.label }
													</strong>
													{ ! item.pass && (
														<>
															<span className="qcseo-muted">
																{ ' ' }
																—{ ' ' }
																{ item.message }
															</span>{ ' ' }
															{ data.can.use &&
																page.can_edit &&
																( 'writer' !==
																	item.fix ||
																	view.writer ) &&
																'manual' !==
																	item.fix && (
																	<Button
																		variant="link"
																		onClick={ () =>
																			fix(
																				page,
																				item
																			)
																		}
																	>
																		{ 'writer' ===
																		item.fix
																			? __(
																					'Fix with AI Writer',
																					'chatbot'
																				)
																			: __(
																					'Fix',
																					'chatbot'
																				) }
																	</Button>
																) }
														</>
													) }
												</li>
											) ) }
										</ul>
									) }
								</td>
								<td className="num">{ page.score }%</td>
								<td>
									{ todo
										.map( ( item ) => item.label )
										.join( ', ' ) || '✓' }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
};

const Crawlers = () => {
	const [ view, setView ] = useState( null );
	const [ rules, setRules ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const load = () =>
		seo( 'crawlers' ).then( ( result ) => {
			setView( result );
			setRules(
				Object.fromEntries(
					result.bots.map( ( bot ) => [ bot.id, bot.rule ] )
				)
			);
		} );
	useEffect( () => {
		load();
	}, [] );
	const save = () =>
		seo( 'crawlers/save', { method: 'POST', data: { rules } } )
			.then( ( result ) => {
				setView( result );
				setMessage( __( 'Saved.', 'chatbot' ) );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	if ( ! view ) {
		return <Spinner />;
	}
	return (
		<div className="qcseo-card">
			<p className="qcseo-muted">
				{ __(
					'Choose which AI bots may read your site. Search bots let assistants cite your pages; training bots only collect content for models.',
					'chatbot'
				) }
			</p>
			<table className="widefat striped qcseo-table">
				<tbody>
					{ view.bots.map( ( bot ) => (
						<tr key={ bot.id }>
							<td>
								<strong>{ bot.agent }</strong> ({ bot.company })
								<br />
								<span className="qcseo-muted">
									{ bot.purpose }
								</span>
								{ view.blocked.includes( bot.id ) && (
									<span className="qcseo-badge">
										{ __(
											'blocked in robots.txt now',
											'chatbot'
										) }
									</span>
								) }
							</td>
							<td>
								<SelectControl
									__next40pxDefaultSize
									label={ bot.agent }
									hideLabelFromVision
									value={ rules[ bot.id ] }
									disabled={ ! data.can.settings }
									options={ [
										{
											value: 'allow',
											label: __(
												'Allow',
												'chatbot'
											),
										},
										{
											value: 'block',
											label: __(
												'Block',
												'chatbot'
											),
										},
									] }
									onChange={ ( value ) =>
										setRules( {
											...rules,
											[ bot.id ]: value,
										} )
									}
									__nextHasNoMarginBottom
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			{ data.can.settings && (
				<Button variant="primary" onClick={ save }>
					{ __( 'Save AI crawler rules', 'chatbot' ) }
				</Button>
			) }
			{ message && <span className="qcseo-muted"> { message }</span> }
			{ view.physical && view.lines && (
				<TextareaControl
					label={ __(
						'Your site has a robots.txt file that WordPress cannot change. Add these lines to it:',
						'chatbot'
					) }
					value={ view.lines }
					readOnly
					__nextHasNoMarginBottom
				/>
			) }
		</div>
	);
};

const Visibility = () => {
	const preview = ( data.previews || [] ).find(
		( p ) => 'preview-visibility' === p.id
	);
	if ( preview ) {
		return (
			<ProPreview
				preview={ preview }
				title={ __( 'AI search visibility', 'chatbot' ) }
				as="h3"
			/>
		);
	}
	return (
		<div className="qcseo-card qcseo-preview">
			<h3>{ __( 'AI search visibility', 'chatbot' ) }</h3>
			<p>
				{ __(
					'Tracking how AI assistants mention your business arrives in a later update.',
					'chatbot'
				) }
			</p>
		</div>
	);
};

const AiSearchScreen = () => {
	const tabs = withPreviewTabs(
		'aiSearch',
		applyFilters( 'qcld_wpbot_seo.aiSearch.tabs', [
			{
				name: 'readiness',
				title: __( 'Readiness', 'chatbot' ),
				render: () => <Readiness />,
			},
			{
				name: 'crawlers',
				title: __( 'AI crawlers', 'chatbot' ),
				render: () => <Crawlers />,
			},
			{
				name: 'visibility',
				title: __( 'Visibility', 'chatbot' ),
				render: () => <Visibility />,
			},
		] )
	);
	return (
		<div className="qcseo-ai-search">
			<h2 className="qcseo-screen-title">
				{ __( 'AI Search', 'chatbot' ) }
			</h2>
			<TabPanel
				className="qcseo-tabs"
				tabs={ tabs.map( ( t ) => ( {
					name: t.name,
					title: t.title,
					className: t.className,
				} ) ) }
			>
				{ ( tab ) => {
					const found = tabs.find( ( t ) => t.name === tab.name );
					return found ? found.render() : null;
				} }
			</TabPanel>
			{ ! data.can.use && (
				<p className="qcseo-muted">
					{ sprintf(
						/* translators: %s: permission level. */
						__(
							'Ask an administrator for the "%s" permission to fix pages.',
							'chatbot'
						),
						__( 'Use', 'chatbot' )
					) }
				</p>
			) }
		</div>
	);
};

export default AiSearchScreen;
