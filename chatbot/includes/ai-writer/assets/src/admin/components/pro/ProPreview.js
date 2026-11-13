/**
 * Inactive "Pro" previews (WPBot Free only).
 *
 * The host lists them (Host_Adapter::get_pro_previews(), cleaned by
 * Admin\Pro_Previews); a host that includes Pro lists none, so every
 * component here renders nothing there. Each preview has a spot id that says
 * where it shows. Nothing behind a preview runs: the badge opens a card with
 * what the feature does, its benefits and features, one link, and a button
 * that hides every preview.
 *
 * Exports:
 *   previewFor( spot )   The preview for a spot, or null.
 *   ProCard              The card (a modal, or inline on a menu page).
 *   ProSpot              One inactive control with a Pro badge.
 *   ProSpots             A row of ProSpot for several spots ("More with Pro").
 */
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, proProduct, writer } from '../../api';
import './style.scss';

/**
 * Only links to the WPBot site are followed.
 *
 * @param {string} url URL from the host.
 * @return {string} The URL, or ''.
 */
const safeUrl = ( url ) =>
	/^https:\/\/(www\.)?wpbot\.pro\//.test( String( url || '' ) )
		? String( url )
		: '';

/**
 * The preview listed for a spot, or null.
 *
 * @param {string} spot Spot id, for example 'write.interview'.
 * @return {Object|null} Preview.
 */
export const previewFor = ( spot ) => {
	if ( data.settings && data.settings.hide_pro_previews ) {
		return null;
	}
	return (
		( data.proPreviews || [] ).find(
			( item ) => item && item.spot === spot
		) || null
	);
};

/**
 * Hide every Pro preview (saves the Settings switch), then reload so menus
 * and screens drop them.
 *
 * @param {(message: string) => void} onError Show an error.
 */
const hideAll = ( onError ) => {
	writer( 'settings', {
		method: 'POST',
		data: { settings: { hide_pro_previews: true } },
	} )
		.then( () => window.location.reload() )
		.catch( ( err ) => onError( errorMessage( err ) ) );
};

/**
 * A titled list, or nothing when empty.
 *
 * @param {Object}   props       Props.
 * @param {string}   props.title Heading.
 * @param {string[]} props.items Texts.
 * @return {Element|null} List.
 */
const CardList = ( { title, items } ) =>
	items && items.length ? (
		<div className="qcwr-pro-card__list">
			<h3>{ title }</h3>
			<ul>
				{ items.map( ( text, index ) => (
					<li key={ index }>{ text }</li>
				) ) }
			</ul>
		</div>
	) : null;

/**
 * The card body: text, Benefits, Features, link and "Hide Pro previews".
 *
 * @param {Object} props         Props.
 * @param {Object} props.preview Preview.
 * @return {Element} Card body.
 */
export const ProCardBody = ( { preview } ) => {
	const [ error, setError ] = useState( '' );
	const [ hiding, setHiding ] = useState( false );
	const url = safeUrl( preview.url );
	return (
		<div className="qcwr-pro-card">
			{ preview.text && (
				<p className="qcwr-pro-card__text">{ preview.text }</p>
			) }
			<CardList
				title={ __( 'Benefits', 'chatbot' ) }
				items={ preview.benefits }
			/>
			<CardList
				title={ __( 'Features', 'chatbot' ) }
				items={ preview.features }
			/>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="qcwr-pro-card__actions">
				{ url && (
					<Button
						variant="primary"
						href={ url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ preview.button ||
							sprintf(
								/* translators: %s: paid edition name, for example WPBot Pro. */
								__( 'Get %s', 'chatbot' ),
								proProduct
							) }
					</Button>
				) }
				{ data.can && data.can.settings && (
					<Button
						variant="tertiary"
						isBusy={ hiding }
						disabled={ hiding }
						onClick={ () => {
							setHiding( true );
							hideAll( ( message ) => {
								setHiding( false );
								setError( message );
							} );
						} }
					>
						{ __( 'Hide Pro previews', 'chatbot' ) }
					</Button>
				) }
			</div>
		</div>
	);
};

/**
 * The card in a modal.
 *
 * @param {Object}     props         Props.
 * @param {Object}     props.preview Preview.
 * @param {() => void} props.onClose Close it.
 * @return {Element} Modal.
 */
export const ProCard = ( { preview, onClose } ) => (
	<Modal
		className="qcwr-pro-modal"
		title={ sprintf(
			/* translators: %s: feature name. */
			__( '%s (Pro)', 'chatbot' ),
			preview.label
		) }
		onRequestClose={ onClose }
	>
		<ProCardBody preview={ preview } />
	</Modal>
);

/**
 * One inactive control with a Pro badge; nothing when the spot has no
 * preview (Pro, or previews hidden).
 *
 * @param {Object} props         Props.
 * @param {string} props.spot    Spot id.
 * @param {string} props.label   Label to show (default: the preview's).
 * @param {string} props.variant Button variant: secondary (default) or link.
 * @param {string} props.as      Wrapper element: span (default), li, p or div.
 * @return {Element|null} Control.
 */
export const ProSpot = ( {
	spot,
	label = '',
	variant = 'secondary',
	as: Wrapper = 'span',
	chip = false,
} ) => {
	const [ open, setOpen ] = useState( false );
	const preview = previewFor( spot );
	if ( ! preview ) {
		return null;
	}
	const text = (
		<>
			{ label || preview.label }
			<span className="qcwr-badge qcwr-badge--pro">
				{ __( 'Pro', 'chatbot' ) }
			</span>
		</>
	);
	return (
		<Wrapper className="qcwr-pro-item" data-pro-spot={ spot }>
			{ chip ? (
				// A greyed choice among the free ones (for example a Pro format in the
				// format row): it opens what Pro adds instead of selecting.
				<button
					type="button"
					className="qcwr-chip is-pro"
					aria-haspopup="dialog"
					onClick={ () => setOpen( true ) }
				>
					{ text }
				</button>
			) : (
				<Button
					variant={ variant }
					className="qcwr-pro-item__button"
					aria-haspopup="dialog"
					onClick={ () => setOpen( true ) }
				>
					{ text }
				</Button>
			) }
			{ open && (
				<ProCard
					preview={ preview }
					onClose={ () => setOpen( false ) }
				/>
			) }
		</Wrapper>
	);
};

/**
 * Several spots in one row, with a short lead-in; nothing when none of
 * them has a preview.
 *
 * @param {Object}       props       Props.
 * @param {string[]}     props.spots Spot ids, in order.
 * @param {string|false} props.title Lead-in (default "More with WPBot Pro:"; false for none).
 * @return {Element|null} Row.
 */
export const ProSpots = ( { spots, title = '' } ) => {
	const shown = ( spots || [] ).filter( ( spot ) => previewFor( spot ) );
	if ( ! shown.length ) {
		return null;
	}
	return (
		<div className="qcwr-pro-row">
			{ false !== title && (
				<span className="qcwr-pro-row__title">
					{ title ||
						sprintf(
							/* translators: %s: paid edition name, for example WPBot Pro. */
							__( 'More with %s:', 'chatbot' ),
							proProduct
						) }
				</span>
			) }
			{ shown.map( ( spot ) => (
				<ProSpot key={ spot } spot={ spot } variant="link" />
			) ) }
		</div>
	);
};
