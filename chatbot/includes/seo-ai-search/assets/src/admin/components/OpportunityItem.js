/**
 * One opportunity: type badge, page or search, reason, numbers, actions.
 * Actions open AI Writer (Improve, Write, Refresh) or save a calendar idea.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../api';
import { num, pct, pos } from '../format';
import Sparkline from './Sparkline';
import { navigate } from '../nav';

const ACTIONS = () => ( {
	improve: __( 'Improve this page', 'chatbot' ),
	write: __( 'Write a new page', 'chatbot' ),
	refresh: __( 'Refresh in AI Writer', 'chatbot' ),
	rewrite_meta: __( 'Rewrite title and description', 'chatbot' ),
	fix_page: __( 'Fix this page', 'chatbot' ),
	technical: __( 'Open Technical SEO', 'chatbot' ),
	review_ads: __( 'Review in Google Ads', 'chatbot' ),
	edit_page: __( 'Edit this page', 'chatbot' ),
	edit_term: __( 'Edit this category', 'chatbot' ),
	local_seo: __( 'Add your business details', 'chatbot' ),
} );

// Actions handled in this app (no AI Writer needed).
const LOCAL = [
	'fix_page',
	'technical',
	'review_ads',
	'edit_page',
	'edit_term',
	'local_seo',
];

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.item     Opportunity.
 * @param {boolean}  props.writer   Whether AI Writer is available.
 * @param {Function} props.onChange Called after dismiss/restore.
 * @param {boolean}  props.compact  Short form for the Overview.
 * @return {Element} Item.
 */
const OpportunityItem = ( { item, writer, onChange, compact } ) => {
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( '' );

	const act = ( action ) => {
		if ( 'fix_page' === action ) {
			navigate( 'fixes', { post_id: item.post_id } );
			return;
		}
		if ( 'technical' === action ) {
			navigate(
				'technical',
				'llms_txt' === item.type ? { tab: 'llms' } : {}
			);
			return;
		}
		const edit = ( item.numbers || {} ).edit;
		if ( ( 'edit_page' === action || 'edit_term' === action ) && edit ) {
			window.location.href = edit;
			return;
		}
		if ( 'local_seo' === action ) {
			navigate( 'local' );
			return;
		}
		if ( 'review_ads' === action ) {
			// Read only: the owner makes any bid change in Google Ads.
			window.open( 'https://ads.google.com/', '_blank', 'noopener' );
			return;
		}
		setBusy( action );
		setMessage( '' );
		seo( `opportunities/${ item.id }/act`, {
			method: 'POST',
			data: { action },
		} )
			.then( ( result ) => {
				if ( 'calendar' === action ) {
					setMessage(
						__(
							'Saved as an idea in AI Writer. Place it on the calendar there.',
							'chatbot'
						)
					);
				} else if ( result.url ) {
					window.location.href = result.url;
				}
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const status = ( what ) => {
		setBusy( what );
		seo( `opportunities/${ item.id }/${ what }`, { method: 'POST' } )
			.then( () => onChange && onChange() )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const n = item.numbers || {};
	const where = item.title || item.url;
	const labels = ACTIONS();
	const canAct = data.can.use && ( writer || LOCAL.includes( item.action ) );
	const needsPost = ! [ 'write', 'review_ads', 'technical' ].includes(
		item.action
	);

	return (
		<li className={ `qcseo-opp qcseo-opp--${ item.type }` }>
			<div className="qcseo-opp__main">
				<span className="qcseo-badge">{ item.label }</span>
				<strong className="qcseo-opp__title">
					{ item.query && 'missing_page' === item.type
						? `"${ item.query }"`
						: where }
				</strong>
				{ item.query && 'missing_page' !== item.type && (
					<span className="qcseo-muted"> · “{ item.query }”</span>
				) }
				<p className="qcseo-opp__reason">{ item.reason }</p>
				{ ! compact && (
					<p className="qcseo-opp__numbers qcseo-muted">
						{ undefined !== n.impressions &&
							`${ num( n.impressions ) } ${ __( 'impressions', 'chatbot' ) } · ` }
						{ undefined !== n.clicks &&
							`${ num( n.clicks ) } ${ __( 'clicks', 'chatbot' ) } · ` }
						{ undefined !== n.ctr && `${ pct( n.ctr ) } CTR · ` }
						{ n.position > 0 &&
							`${ __( 'position', 'chatbot' ) } ${ pos( n.position ) } · ` }
						{ `${ __( 'about', 'chatbot' ) } ${ num( item.potential ) } ${ __( 'more clicks a month', 'chatbot' ) }` }
					</p>
				) }
				{ ! compact && item.kdata && null !== item.kdata.volume && (
					<p className="qcseo-opp__volume">
						<span className="qcseo-badge">
							{ sprintf(
								/* translators: %s: monthly searches. */
								__( '%s searches a month', 'chatbot' ),
								num( item.kdata.volume )
							) }
						</span>
						{ null !== item.kdata.cpc && (
							<span className="qcseo-muted">
								{ sprintf(
									/* translators: %s: cost per click. */
									__(
										'Cost per click %s',
										'chatbot'
									),
									item.kdata.cpc.toFixed( 2 )
								) }
								{ ' · ' }
							</span>
						) }
						<span className="qcseo-muted">
							{ sprintf(
								/* translators: %s: clicks. */
								__(
									'up to %s clicks a month at position 3',
									'chatbot'
								),
								num( item.kdata.potential )
							) }
						</span>
						<Sparkline
							points={ item.kdata.trend }
							label={ __(
								'Searches over 12 months',
								'chatbot'
							) }
						/>
					</p>
				) }
			</div>
			<div className="qcseo-opp__actions">
				{ canAct && ( ! needsPost || item.can_edit ) && (
					<Button
						variant="primary"
						size="compact"
						isBusy={ busy === item.action }
						disabled={ !! busy }
						onClick={ () => act( item.action ) }
					>
						{ labels[ item.action ] || labels.improve }
					</Button>
				) }
				{ canAct && writer && ! compact && (
					<Button
						variant="secondary"
						size="compact"
						isBusy={ 'calendar' === busy }
						disabled={ !! busy }
						onClick={ () => act( 'calendar' ) }
					>
						{ __( 'Add to calendar', 'chatbot' ) }
					</Button>
				) }
				{ data.can.use && ! compact && 'open' === item.status && (
					<Button
						variant="tertiary"
						size="compact"
						disabled={ !! busy }
						onClick={ () => status( 'dismiss' ) }
					>
						{ __( 'Dismiss', 'chatbot' ) }
					</Button>
				) }
				{ data.can.use && 'dismissed' === item.status && (
					<Button
						variant="tertiary"
						size="compact"
						disabled={ !! busy }
						onClick={ () => status( 'restore' ) }
					>
						{ __( 'Restore', 'chatbot' ) }
					</Button>
				) }
				{ ! writer && ! LOCAL.includes( item.action ) && (
					<span className="qcseo-muted">
						{ __(
							'AI Writer is needed for this action.',
							'chatbot'
						) }
					</span>
				) }
			</div>
			{ message && <p className="qcseo-opp__message">{ message }</p> }
		</li>
	);
};

export default OpportunityItem;
