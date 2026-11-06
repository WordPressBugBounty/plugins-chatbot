/**
 * Review of "Write descriptions with AI" from the Products list: each
 * product's new long and short description with claim flags; only
 * approved products are saved.
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { RawHTML, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { errorMessage, writer } from '../../api';
import JobProgress from '../JobProgress';

const STATE_LABELS = () => ( {
	pending: __( 'Writing…', 'chatbot' ),
	ready: __( 'Ready to review', 'chatbot' ),
	saved: __( 'Saved', 'chatbot' ),
	skipped: __( 'Skipped', 'chatbot' ),
	failed: __( 'Could not be written', 'chatbot' ),
} );

const ProductsReview = ( { jobId, onExit } ) => {
	const [ view, setView ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ busy, setBusy ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	// Reload the review only when another product is finished.
	const doneRef = useRef( -1 );

	const onProgress = ( job ) => {
		if ( job.done !== doneRef.current ) {
			doneRef.current = job.done;
			load();
		}
	};

	const load = () =>
		writer( `woo/jobs/${ jobId }` )
			.then( setView )
			.catch( ( err ) => setError( errorMessage( err ) ) );

	useEffect( () => {
		load();
	}, [ jobId ] );

	const decide = ( ids, what ) => {
		setBusy( ids.length === 1 ? ids[ 0 ] : -1 );
		writer( `woo/jobs/${ jobId }/decide`, {
			method: 'POST',
			data: { products: ids, what },
		} )
			.then( ( result ) => {
				setErrors( { ...errors, ...result.errors } );
				setView( { ...view, review: result.review } );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( 0 ) );
	};

	const labels = STATE_LABELS();
	const items = view && view.review ? view.review.items : [];
	const unflagged = items
		.filter(
			( item ) => 'ready' === item.state && ! ( item.flags || [] ).length
		)
		.map( ( item ) => item.product_id );
	const running =
		view && view.job && [ 'queued', 'running' ].includes( view.job.status );

	return (
		<div className="qcwr-flow qcwr-products-review">
			<div className="qcwr-flow__head">
				<Button variant="link" onClick={ onExit }>
					{ __( '← Back to start', 'chatbot' ) }
				</Button>
				<strong className="qcwr-flow__topic">
					{ view && view.review ? view.review.title : '' }
				</strong>
			</div>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ ! view && <Spinner /> }
			{ running && (
				<div className="qcwr-card">
					<JobProgress jobId={ jobId } onChange={ onProgress } />
				</div>
			) }
			{ unflagged.length > 0 && (
				<div className="qcwr-card">
					<Button
						variant="primary"
						isBusy={ -1 === busy }
						onClick={ () => decide( unflagged, 'save' ) }
					>
						{ sprintf(
							/* translators: %d: number of products. */
							_n(
								'Save %d product with no flags',
								'Save %d products with no flags',
								unflagged.length,
								'chatbot'
							),
							unflagged.length
						) }
					</Button>
					<p className="qcwr-muted">
						{ __(
							'Products with flags need a look first: their copy mentions something the product data does not say.',
							'chatbot'
						) }
					</p>
				</div>
			) }
			{ items.map( ( item ) => (
				<div
					key={ item.product_id }
					className={ `qcwr-card qcwr-product-item is-${ item.state }` }
				>
					<h3>
						<a href={ item.edit_url }>{ item.name }</a>{ ' ' }
						<span className="qcwr-badge">
							{ labels[ item.state ] }
						</span>
					</h3>
					{ item.error && (
						<p className="qcwr-over">{ item.error }</p>
					) }
					{ errors[ item.product_id ] && (
						<p className="qcwr-over">
							{ errors[ item.product_id ] }
						</p>
					) }
					{ ( item.flags || [] ).length > 0 &&
						'ready' === item.state && (
							<Notice status="warning" isDismissible={ false }>
								<strong>
									{ __(
										'Check before saving:',
										'chatbot'
									) }
								</strong>
								<ul>
									{ item.flags.map( ( flag, index ) => (
										<li key={ index }>
											“{ flag.sentence }” —{ ' ' }
											{ flag.reason }
										</li>
									) ) }
								</ul>
							</Notice>
						) }
					{ 'ready' === item.state && (
						<>
							<div className="qcwr-product-item__copy">
								<div>
									<span className="qcwr-muted">
										{ __(
											'Short description',
											'chatbot'
										) }
									</span>
									<RawHTML>{ item.short }</RawHTML>
								</div>
								<div>
									<span className="qcwr-muted">
										{ __(
											'Description',
											'chatbot'
										) }
									</span>
									<RawHTML>{ item.long }</RawHTML>
								</div>
							</div>
							{ item.before_long && (
								<details>
									<summary>
										{ __(
											'Current description',
											'chatbot'
										) }
									</summary>
									<p className="qcwr-muted">
										{ item.before_long }
									</p>
								</details>
							) }
							<div className="qcwr-actions">
								<Button
									variant="primary"
									isBusy={ busy === item.product_id }
									onClick={ () =>
										decide( [ item.product_id ], 'save' )
									}
								>
									{ __(
										'Approve and save',
										'chatbot'
									) }
								</Button>
								<Button
									variant="tertiary"
									onClick={ () =>
										decide( [ item.product_id ], 'skip' )
									}
								>
									{ __( 'Skip', 'chatbot' ) }
								</Button>
							</div>
						</>
					) }
				</div>
			) ) }
		</div>
	);
};

export default ProductsReview;
