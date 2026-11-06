/**
 * WooCommerce (when active): where product writing lives, buying guides and
 * comparisons, and the review of a bulk "Write descriptions with AI" job.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data } from '../api';
import ProductsReview from '../components/woo/ProductsReview';
import { emptyBrief } from '../components/write/WriteFlow';
import { navigate, takeIntent } from '../nav';
import { ProSpots } from '../components/pro/ProPreview';

const start = () => {
	const intent = takeIntent() || {};
	if ( intent.productsJob ) {
		return intent.productsJob;
	}
	if ( data.productsJob ) {
		const job = data.productsJob;
		data.productsJob = 0;
		return job;
	}
	return 0;
};

const WooScreen = () => {
	const [ jobId, setJobId ] = useState( start );
	const types = ( data.contentTypes || [] ).map( ( type ) => type.value );

	if ( jobId ) {
		return (
			<ProductsReview jobId={ jobId } onExit={ () => setJobId( 0 ) } />
		);
	}

	const products = `${ data.adminUrl }edit.php?post_type=product`;
	return (
		<div className="qcwr-starts">
			<div className="qcwr-card qcwr-start">
				<h2>{ __( 'Product descriptions', 'chatbot' ) }</h2>
				<p>
					{ __(
						'Open a product to write its description, short description, FAQ and image alt text from the AI Writer panel. For many products at once, select them in the Products list and choose "Write descriptions with AI" under Bulk actions; you review every product before anything is saved.',
						'chatbot'
					) }
				</p>
				<Button variant="primary" href={ products }>
					{ __( 'Open Products', 'chatbot' ) }
				</Button>
				<ProSpots spots={ [ 'woo.voice', 'woo.chat_questions' ] } />
			</div>
			<div className="qcwr-card qcwr-start">
				<h2>
					{ __( 'Buying guides and comparisons', 'chatbot' ) }
				</h2>
				<p>
					{ __(
						'An article that helps shoppers choose, built from two or more of your products.',
						'chatbot'
					) }
				</p>
				<div className="qcwr-actions">
					{ types.includes( 'buying_guide' ) && (
						<Button
							variant="secondary"
							disabled={ ! data.can.use }
							onClick={ () =>
								navigate( 'write', {
									brief: {
										...emptyBrief(),
										type: 'buying_guide',
									},
								} )
							}
						>
							{ __( 'Write a buying guide', 'chatbot' ) }
						</Button>
					) }
					{ types.includes( 'comparison' ) && (
						<Button
							variant="secondary"
							disabled={ ! data.can.use }
							onClick={ () =>
								navigate( 'write', {
									brief: {
										...emptyBrief(),
										type: 'comparison',
									},
								} )
							}
						>
							{ __( 'Write a comparison', 'chatbot' ) }
						</Button>
					) }
				</div>
			</div>
			<div className="qcwr-card qcwr-start">
				<h2>{ __( 'Category descriptions', 'chatbot' ) }</h2>
				<p>
					{ __(
						'Open a product category and click "Write with AI" next to its description.',
						'chatbot'
					) }
				</p>
				<Button
					variant="secondary"
					href={ `${ data.adminUrl }edit-tags.php?taxonomy=product_cat&post_type=product` }
				>
					{ __( 'Open categories', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default WooScreen;
