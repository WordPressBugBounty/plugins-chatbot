/**
 * Local SEO > Reviews (round 3, J): Business Profile reviews in one place,
 * an AI reply draft in the owner's voice (cost first), edited and posted
 * with one click, and the review link to share with every customer.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';
import { aiBlocked, useAiReady } from '../../ai';
import ReviewQr from './ReviewQr';

const Stars = ( { n } ) => (
	<span
		className="qcseo-stars"
		aria-label={ sprintf(
			/* translators: %d: stars. */
			__( '%d of 5 stars', 'chatbot' ),
			n
		) }
	>
		{ '★'.repeat( n ) }
		<span className="qcseo-muted">{ '★'.repeat( 5 - n ) }</span>
	</span>
);

const ReviewItem = ( { review, canReply, estimate, onChange } ) => {
	const ai = useAiReady();
	const [ text, setText ] = useState(
		review.draft ? review.draft.reply : ''
	);
	const [ steer, setSteer ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const run = ( key, path, body ) => {
		setBusy( key );
		setError( '' );
		return seo( path, { method: 'POST', data: body } )
			.then( ( next ) => {
				onChange( next );
				return next;
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};
	return (
		<li className="qcseo-review">
			<div className="qcseo-review__head">
				<Stars n={ review.stars } />
				<strong>{ review.reviewer }</strong>
				<span className="qcseo-muted">
					{ review.created.slice( 0, 10 ) }
				</span>
			</div>
			{ review.text && <p>{ review.text }</p> }
			{ review.reply ? (
				<p className="qcseo-review__reply">
					<strong>{ __( 'Your reply:', 'chatbot' ) }</strong>{ ' ' }
					{ review.reply }
				</p>
			) : (
				<div className="qcseo-form qcseo-review__draft">
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Your reply', 'chatbot' ) }
						rows={ 4 }
						value={ text }
						onChange={ setText }
						help={
							review.draft && review.draft.why
								? review.draft.why
								: undefined
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Note for the AI (optional)',
							'chatbot'
						) }
						placeholder={ __(
							'For example: mention our new Saturday hours',
							'chatbot'
						) }
						value={ steer }
						onChange={ setSteer }
					/>
					<div className="qcseo-actions">
						<Button
							variant="secondary"
							size="compact"
							isBusy={ 'draft' === busy }
							disabled={ !! busy || aiBlocked( ai ) }
							onClick={ () =>
								run( 'draft', 'local/reviews/draft', {
									id: review.id,
									steer,
								} ).then( ( next ) => {
									if ( next && next.draft ) {
										setText( next.draft.reply );
									}
								} )
							}
						>
							{ text
								? __(
										'Draft again with AI',
										'chatbot'
									)
								: __(
										'Draft a reply with AI',
										'chatbot'
									) }
						</Button>
						<span className="qcseo-muted">
							{ estimate < 0.01
								? __( 'less than $0.01', 'chatbot' )
								: `$${ Number( estimate ).toFixed( 2 ) }` }
						</span>
						{ canReply && (
							<Button
								variant="primary"
								size="compact"
								isBusy={ 'post' === busy }
								disabled={ !! busy || ! text.trim() }
								onClick={ () =>
									run( 'post', 'local/reviews/reply', {
										id: review.id,
										reply: text,
									} )
								}
							>
								{ __(
									'Post reply on Google',
									'chatbot'
								) }
							</Button>
						) }
					</div>
				</div>
			) }
			{ error && <p className="qcseo-error">{ error }</p> }
		</li>
	);
};

const LocalReviews = () => {
	const [ view, setView ] = useState( null );
	const [ filter, setFilter ] = useState( 'all' );
	const [ busy, setBusy ] = useState( false );
	const [ copied, setCopied ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const load = ( path = `local/reviews?filter=${ filter }`, body = null ) => {
		setBusy( true );
		return seo( path, body ? { method: 'POST', data: body } : {} )
			.then( setView )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	useEffect( () => {
		load();
	}, [ filter ] );
	if ( ! view ) {
		return message ? (
			<p className="qcseo-error">{ message }</p>
		) : (
			<Spinner />
		);
	}
	if ( ! view.linked ) {
		return null;
	}
	const copy = () => {
		const done = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2500 );
		};
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard
				.writeText( view.review_link )
				.then( done, done );
		} else {
			done();
		}
	};
	const s = view.summary;
	return (
		<div className="qcseo-card qcseo-reviews">
			<div className="qcseo-head">
				<h3>{ __( 'Reviews', 'chatbot' ) }</h3>
				<Button
					variant="tertiary"
					size="compact"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => load( 'local/reviews/sync', { filter } ) }
				>
					{ __( 'Refresh from Google', 'chatbot' ) }
				</Button>
			</div>
			{ message && (
				<Notice status="error" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ view.error && <p className="qcseo-error">{ view.error }</p> }
			<p>
				{ sprintf(
					/* translators: 1: reviews, 2: average stars, 3: unanswered. */
					__(
						'%1$d reviews, %2$s stars on average, %3$d without a reply.',
						'chatbot'
					),
					s.count,
					null === s.average ? '—' : s.average,
					s.unanswered
				) }
			</p>
			{ view.review_link && (
				<div className="qcseo-review-link">
					<strong>
						{ __(
							'Ask every customer for a review',
							'chatbot'
						) }
					</strong>
					<p className="qcseo-muted">
						{ __(
							'Share this link in receipts, emails and your chatbot. Ask everyone, not only happy customers: asking selectively breaks Google’s rules.',
							'chatbot'
						) }
					</p>
					<div className="qcseo-actions">
						<code>{ view.review_link }</code>
						<Button
							variant="secondary"
							size="compact"
							onClick={ copy }
						>
							{ copied
								? __( 'Copied', 'chatbot' )
								: __( 'Copy link', 'chatbot' ) }
						</Button>
					</div>
					<ReviewQr url={ view.review_link } />
				</div>
			) }
			{ applyFilters( 'qcld_wpbot_seo.local.reviews', null, {
				view,
				reload: load,
			} ) }
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Show', 'chatbot' ) }
				value={ filter }
				options={ [
					{
						value: 'all',
						label: __( 'All reviews', 'chatbot' ),
					},
					{
						value: 'unanswered',
						label: __( 'Without a reply', 'chatbot' ),
					},
					{
						value: 'negative',
						label: __( '1 to 3 stars', 'chatbot' ),
					},
				] }
				onChange={ setFilter }
			/>
			{ 0 === view.reviews.length && (
				<p className="qcseo-muted">
					{ __( 'No reviews here yet.', 'chatbot' ) }
				</p>
			) }
			<ul className="qcseo-review-list">
				{ view.reviews.map( ( review ) => (
					<ReviewItem
						key={ review.id }
						review={ review }
						canReply={ view.can_reply }
						estimate={ view.estimate }
						onChange={ () => load() }
					/>
				) ) }
			</ul>
		</div>
	);
};

export default LocalReviews;
