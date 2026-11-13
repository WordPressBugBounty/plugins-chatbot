/**
 * Library tab: finished pieces AI Writer wrote (with their summary and key
 * points, ready to reuse) and your saved Quick write items.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import { ProSpot, ProSpots } from '../components/pro/ProPreview';

const date = ( iso ) => ( iso ? new Date( iso ).toLocaleDateString() : '' );

/**
 * Free: "Regenerate several" as a labelled Pro preview (host text).
 *
 * @return {Element|null} Preview.
 */
const RegenerateSeveralPreview = () => <ProSpot spot="library.bulk" as="p" />;

/**
 * Finished pieces with search, status filter and pages.
 *
 * @return {Element} List.
 */
const Pieces = () => {
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	// Extra filters from extensions (the Pro review queue): name => value.
	const [ extra, setExtra ] = useState( {} );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		const timer = setTimeout( () => {
			writer(
				`library/pieces?search=${ encodeURIComponent(
					search
				) }&status=${ status }&page=${ page }${ Object.entries( extra )
					.map(
						( [ key, value ] ) =>
							`&${ encodeURIComponent( key ) }=${ encodeURIComponent(
								value
							) }`
					)
					.join( '' ) }`
			)
				.then( setResult )
				.catch( ( err ) => setError( errorMessage( err ) ) );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ search, status, page, extra ] );

	return (
		<section className="qcwr-card qcwr-library__pieces">
			<h2>{ __( 'Finished pieces', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ data.hasChatbot === false
					? __(
							'Everything AI Writer wrote for you, with its summary and key points to reuse in newsletters or social posts.',
							'chatbot'
						)
					: __(
							'Everything AI Writer wrote for you, with its summary and key points to reuse in newsletters, social posts or the chatbot.',
							'chatbot'
						) }
			</p>
			<div className="qcwr-library__filters">
				<TextControl
					label={ __( 'Search', 'chatbot' ) }
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Status', 'chatbot' ) }
					value={ status }
					options={ [
						{ label: __( 'All', 'chatbot' ), value: '' },
						{
							label: __( 'Draft', 'chatbot' ),
							value: 'draft',
						},
						{
							label: __( 'Pending review', 'chatbot' ),
							value: 'pending',
						},
						{
							label: __( 'Scheduled', 'chatbot' ),
							value: 'future',
						},
						{
							label: __( 'Published', 'chatbot' ),
							value: 'publish',
						},
						{
							label: __( 'Private', 'chatbot' ),
							value: 'private',
						},
					] }
					onChange={ ( value ) => {
						setStatus( value );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
				/>
			</div>
			{ applyFilters( 'qcld_wpbot_writer.library.filters', null, {
				extra,
				setExtra: ( next ) => {
					setExtra( next );
					setPage( 1 );
				},
			} ) }
			<ProSpots spots={ [ 'library.review' ] } />
			{ error && <Notice status="error">{ error }</Notice> }
			{ ! result && ! error && <Spinner /> }
			{ result && ! result.items.length && (
				<p className="qcwr-muted">
					{ search || status
						? __( 'Nothing matches.', 'chatbot' )
						: __(
								'Nothing yet. Pieces you write with AI Writer appear here.',
								'chatbot'
							) }
				</p>
			) }
			{ result &&
				result.items.length > 0 &&
				// CR-14: regenerate several pieces at once (WPBot Pro fills this).
				applyFilters(
					'qcld_wpbot_writer.library.bulk',
					<RegenerateSeveralPreview />,
					{ items: result.items }
				) }
			{ result && result.items.length > 0 && (
				<ul className="qcwr-library__list">
					{ result.items.map( ( piece ) => (
						<li key={ piece.id } className="qcwr-piece">
							<div className="qcwr-piece__main">
								<strong className="qcwr-piece__title">
									{ piece.title }
								</strong>
								<div className="qcwr-idea__meta">
									<span className="qcwr-badge">
										{ piece.type }
									</span>
									<span className="qcwr-badge">
										{ piece.status_label }
									</span>
									<span className="qcwr-muted">
										{ sprintf(
											/* translators: 1: number of words, 2: date. */
											__(
												'%1$d words · changed %2$s',
												'chatbot'
											),
											piece.words,
											piece.modified
										) }
									</span>
								</div>
								{ piece.question && (
									<p className="qcwr-muted">
										{ __( 'Answers:', 'chatbot' ) }{ ' ' }
										<em>{ piece.question }</em>
									</p>
								) }
								{ piece.summary && (
									<p className="qcwr-piece__summary">
										{ piece.summary }
									</p>
								) }
								{ applyFilters(
									'qcld_wpbot_writer.library.piece',
									null,
									piece
								) }
								{ piece.key_points.length > 0 && (
									<ul className="qcwr-idea__questions">
										{ piece.key_points.map( ( point ) => (
											<li key={ point }>{ point }</li>
										) ) }
									</ul>
								) }
							</div>
							<div className="qcwr-idea__actions">
								<Button
									variant="primary"
									href={ piece.edit_url }
								>
									{ __( 'Edit', 'chatbot' ) }
								</Button>
								{ piece.view_url && (
									<Button
										variant="secondary"
										href={ piece.view_url }
										target="_blank"
									>
										{ __( 'View', 'chatbot' ) }
									</Button>
								) }
								{ data.can.use && (
									<Button
										variant="tertiary"
										href={ piece.improve_url }
									>
										{ __( 'Improve', 'chatbot' ) }
									</Button>
								) }
							</div>
						</li>
					) ) }
				</ul>
			) }
			{ result && result.pages > 1 && (
				<div className="qcwr-library__pages">
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'chatbot' ) }
					</Button>
					<span className="qcwr-muted">
						{ sprintf(
							/* translators: 1: page, 2: pages. */
							__( 'Page %1$d of %2$d', 'chatbot' ),
							page,
							result.pages
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ page >= result.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'chatbot' ) }
					</Button>
				</div>
			) }
		</section>
	);
};

/**
 * Saved Quick write items: copy or delete.
 *
 * @return {Element} List.
 */
const QuickItems = () => {
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ copied, setCopied ] = useState( 0 );

	useEffect( () => {
		const timer = setTimeout( () => {
			writer( `library/items?search=${ encodeURIComponent( search ) }` )
				.then( ( result ) => setItems( result.items ) )
				.catch( ( err ) => setError( errorMessage( err ) ) );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ search ] );

	const copy = ( item ) => {
		const done = () => {
			setCopied( item.id );
			setTimeout( () => setCopied( 0 ), 2000 );
		};
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard
				.writeText( item.text )
				.then( done )
				.catch( () => {} );
		}
	};

	// Two clicks: Delete, then Really delete?
	const [ confirming, setConfirming ] = useState( 0 );
	const remove = ( item ) => {
		if ( confirming !== item.id ) {
			setConfirming( item.id );
			return;
		}
		setConfirming( 0 );
		writer( `library/items/${ item.id }`, { method: 'DELETE' } )
			.then( () =>
				setItems( items.filter( ( other ) => other.id !== item.id ) )
			)
			.catch( ( err ) => setError( errorMessage( err ) ) );
	};

	return (
		<section className="qcwr-card qcwr-library__items">
			<h2>{ __( 'Quick write items', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'Texts you saved from Quick write. Only you see your items.',
					'chatbot'
				) }
			</p>
			<div className="qcwr-library__filters">
				<TextControl
					label={ __( 'Search', 'chatbot' ) }
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
			</div>
			{ error && <Notice status="error">{ error }</Notice> }
			{ null === items && ! error && <Spinner /> }
			{ items && ! items.length && (
				<p className="qcwr-muted">
					{ __(
						'Nothing saved yet. Use "Save to Library" in Quick write.',
						'chatbot'
					) }
				</p>
			) }
			{ items && items.length > 0 && (
				<>
					<p className="qcwr-muted">
						{ sprintf(
							/* translators: %d: number of items. */
							_n(
								'%d item',
								'%d items',
								items.length,
								'chatbot'
							),
							items.length
						) }
					</p>
					<ul className="qcwr-library__list">
						{ items.map( ( item ) => (
							<li key={ item.id } className="qcwr-piece">
								<div className="qcwr-piece__main">
									<div className="qcwr-idea__meta">
										<span className="qcwr-badge">
											{ item.template }
										</span>
										<span className="qcwr-muted">
											{ date( item.modified ) }
										</span>
										{ item.source_url && (
											<span className="qcwr-muted">
												{ __(
													'From',
													'chatbot'
												) }{ ' ' }
												<a href={ item.source_url }>
													{ item.source_title }
												</a>
											</span>
										) }
									</div>
									<p className="qcwr-quick-item__text">
										{ item.text }
									</p>
								</div>
								<div className="qcwr-idea__actions">
									<Button
										variant="secondary"
										onClick={ () => copy( item ) }
									>
										{ copied === item.id
											? __( 'Copied', 'chatbot' )
											: __( 'Copy', 'chatbot' ) }
									</Button>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () => remove( item ) }
									>
										{ confirming === item.id
											? __(
													'Really delete?',
													'chatbot'
												)
											: __(
													'Delete',
													'chatbot'
												) }
									</Button>
								</div>
							</li>
						) ) }
					</ul>
				</>
			) }
		</section>
	);
};

const LibraryScreen = () => (
	<div className="qcwr-library">
		<Pieces />
		{ data.can.use && <QuickItems /> }
	</div>
);

export default LibraryScreen;
