/**
 * Topic Map: the topics your pages cover (shared with AI Writer), as an
 * expandable list and a visual view. Coverage: covered, thin, missing,
 * planned. Move pages, rename topics, write the missing ones.
 */
import {
	Button,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';
import JobProgress from '../JobProgress';

const COVERAGE = {
	covered: __( 'Covered', 'chatbot' ),
	thin: __( 'Thin', 'chatbot' ),
	missing: __( 'Missing', 'chatbot' ),
	planned: __( 'Planned', 'chatbot' ),
	'': __( 'Not rated', 'chatbot' ),
};

const COLOR = {
	covered: '#2e7d32',
	thin: '#f9a825',
	missing: '#c62828',
	planned: '#1565c0',
	'': '#9e9e9e',
};

const Visual = ( { topics, onPick } ) => {
	const top = topics.filter( ( t ) => ! t.parent_id );
	const cols = 4;
	const size = 150;
	const rows = Math.max( 1, Math.ceil( top.length / cols ) );
	return (
		<svg
			className="qcseo-topic-visual"
			viewBox={ `0 0 ${ cols * size } ${ rows * size }` }
			role="img"
			aria-label={ __( 'Topic map', 'chatbot' ) }
		>
			{ top.map( ( t, i ) => {
				const x = ( i % cols ) * size + size / 2;
				const y = Math.floor( i / cols ) * size + size / 2;
				const r = Math.min(
					60,
					18 + 8 * Math.sqrt( ( t.live || 0 ) + ( t.planned || 0 ) )
				);
				return (
					<g
						key={ t.id }
						onClick={ () => onPick( t.id ) }
						style={ { cursor: 'pointer' } }
					>
						<title>{ `${ t.name }: ${ COVERAGE[ t.coverage || '' ] }` }</title>
						<circle
							cx={ x }
							cy={ y }
							r={ r }
							fill={ COLOR[ t.coverage || '' ] }
							fillOpacity="0.25"
							stroke={ COLOR[ t.coverage || '' ] }
							strokeWidth="2"
						/>
						<text x={ x } y={ y } textAnchor="middle" fontSize="12">
							{ t.name.length > 18
								? `${ t.name.slice( 0, 17 ) }…`
								: t.name }
						</text>
						<text
							x={ x }
							y={ y + 15 }
							textAnchor="middle"
							fontSize="10"
						>
							{ t.live || 0 }
						</text>
					</g>
				);
			} ) }
		</svg>
	);
};

const TopicMap = () => {
	const [ view, setView ] = useState( null );
	const [ mode, setMode ] = useState( 'list' );
	const [ open, setOpen ] = useState( {} );
	const [ rename, setRename ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const [ groupJob, setGroupJob ] = useState( 0 );
	const reload = () =>
		seo( 'topics' )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	useEffect( () => {
		reload();
	}, [] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	const call = ( route, payload ) =>
		seo( route, { method: 'POST', data: payload } )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	const children = ( id ) =>
		view.topics.filter( ( t ) => t.parent_id === id );
	const renderTopic = ( t, depth ) => (
		<li
			key={ t.id }
			className={ `qcseo-topic is-${ t.coverage || 'none' }` }
			style={ { marginLeft: depth * 16 } }
		>
			<Button
				variant="link"
				onClick={ () =>
					setOpen( { ...open, [ t.id ]: ! open[ t.id ] } )
				}
				aria-expanded={ !! open[ t.id ] }
			>
				{ open[ t.id ] ? '▾' : '▸' } { t.name }
			</Button>{ ' ' }
			<span className="qcseo-badge">
				{ COVERAGE[ t.coverage || '' ] }
			</span>{ ' ' }
			<small className="qcseo-muted">
				{ sprintf(
					/* translators: 1: live pages, 2: planned pages. */
					__( '%1$d pages, %2$d planned', 'chatbot' ),
					t.live || 0,
					t.planned || 0
				) }
			</small>
			{ null !== t.searches && undefined !== t.searches && (
				<small className="qcseo-topic-searches">
					{ ' · ' }
					{ sprintf(
						/* translators: 1: monthly searches, 2: data source. */
						__(
							'%1$s searches a month (%2$s)',
							'chatbot'
						),
						Number( t.searches ).toLocaleString(),
						t.source
					) }
				</small>
			) }
			{ t.needs_pillar && (
				<span
					className="qcseo-badge is-warning"
					title={ __(
						'Pick one page as the pillar: the overview the other pages link to and from. Internal-link opportunities follow.',
						'chatbot'
					) }
				>
					{ __( 'Needs a pillar page', 'chatbot' ) }
				</span>
			) }
			{ applyFilters( 'qcld_wpbot_seo.topics.badges', null, t ) }
			{ open[ t.id ] && (
				<div className="qcseo-topic-body">
					<ul>
						{ t.pages.map( ( p ) => (
							<li key={ `${ p.post_id }-${ p.query }` }>
								{ p.title }{ ' ' }
								<small className="qcseo-muted">
									“{ p.query }”
									{ null !== p.volume &&
									undefined !== p.volume
										? ` · ${ sprintf(
												/* translators: %s: monthly searches. */
												__(
													'%s searches a month',
													'chatbot'
												),
												Number(
													p.volume
												).toLocaleString()
											) }`
										: '' }
									{ 'planned' === p.status && ! p.vault
										? ` · ${ __( 'planned', 'chatbot' ) }`
										: '' }
									{ p.vault
										? ` · ${ __(
												'planned (Keyword Vault)',
												'chatbot'
											) }`
										: '' }
								</small>
								{ p.post_id > 0 && data.can.use && (
									<SelectControl
										__next40pxDefaultSize
										label={ __(
											'Move to',
											'chatbot'
										) }
										hideLabelFromVision
										value={ String( t.id ) }
										options={ view.topics.map( ( o ) => ( {
											value: String( o.id ),
											label: o.name,
										} ) ) }
										onChange={ ( v ) =>
											call( 'topics/move', {
												post_id: p.post_id,
												topic_id: Number( v ),
											} )
										}
										__nextHasNoMarginBottom
									/>
								) }
							</li>
						) ) }
					</ul>
					{ data.can.use && (
						<div className="qcseo-grid">
							<TextControl
								__next40pxDefaultSize
								label={ __( 'Rename', 'chatbot' ) }
								value={
									undefined === rename[ t.id ]
										? t.name
										: rename[ t.id ]
								}
								onChange={ ( v ) =>
									setRename( { ...rename, [ t.id ]: v } )
								}
								__nextHasNoMarginBottom
							/>
							<Button
								variant="secondary"
								onClick={ () =>
									call( `topics/${ t.id }`, {
										name: rename[ t.id ] || t.name,
									} )
								}
							>
								{ __( 'Save name', 'chatbot' ) }
							</Button>
							{ view.writer &&
								[ 'missing', 'thin' ].includes(
									t.coverage
								) && (
									<Button
										variant="primary"
										onClick={ () =>
											seo( `topics/${ t.id }/write`, {
												method: 'POST',
											} ).then( ( r ) => {
												window.location.href = r.url;
											} )
										}
									>
										{ __(
											'Write a page in AI Writer',
											'chatbot'
										) }
									</Button>
								) }
						</div>
					) }
				</div>
			) }
			{ children( t.id ).length > 0 && (
				<ul>
					{ children( t.id ).map( ( c ) =>
						renderTopic( c, depth + 1 )
					) }
				</ul>
			) }
		</li>
	);
	return (
		<div className="qcseo-card qcseo-topic-map">
			<div className="qcseo-head">
				<h3>{ __( 'Topic Map', 'chatbot' ) }</h3>
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'View', 'chatbot' ) }
					hideLabelFromVision
					value={ mode }
					options={ [
						{
							value: 'list',
							label: __( 'List', 'chatbot' ),
						},
						{
							value: 'visual',
							label: __( 'Visual', 'chatbot' ),
						},
					] }
					onChange={ setMode }
					__nextHasNoMarginBottom
				/>
			</div>
			<p className="qcseo-muted">
				{ __(
					'The topics your site covers, shared with AI Writer. Green: covered; yellow: thin; red: missing; blue: planned.',
					'chatbot'
				) }
				{ view.unmapped > 0 &&
					' ' +
						sprintf(
							/* translators: %d: pages. */
							__(
								'%d pages have no topic yet.',
								'chatbot'
							),
							view.unmapped
						) }
			</p>
			{ view.grouping && data.can.use && (
				<p>
					<Button
						variant="secondary"
						disabled={ !! groupJob }
						onClick={ () =>
							seo( 'topics/group', { method: 'POST' } )
								.then( ( r ) => setGroupJob( r.job_id ) )
								.catch( ( e ) =>
									setMessage( errorMessage( e ) )
								)
						}
					>
						{ __(
							'Group pages into topics with AI Writer',
							'chatbot'
						) }
					</Button>
				</p>
			) }
			{ groupJob > 0 && (
				<JobProgress
					jobId={ groupJob }
					onChange={ ( job ) => {
						if ( job && 'done' === job.status ) {
							setGroupJob( 0 );
							reload();
						}
					} }
				/>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
			{ 0 === view.topics.length && (
				<p className="qcseo-muted">
					{ __(
						'No topics yet. Keywords you save from Research bring their topics; AI Writer can group your pages, and you can move a page to a topic from its row.',
						'chatbot'
					) }
				</p>
			) }
			{ 'visual' === mode ? (
				<Visual
					topics={ view.topics }
					onPick={ ( id ) => {
						setOpen( { ...open, [ id ]: true } );
						setMode( 'list' );
					} }
				/>
			) : (
				<ul className="qcseo-topic-list">
					{ view.topics
						.filter( ( t ) => ! t.parent_id )
						.map( ( t ) => renderTopic( t, 0 ) ) }
				</ul>
			) }
		</div>
	);
};

export default TopicMap;
