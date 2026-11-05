/**
 * A draft for one place: two optional quick questions and a "Steer this
 * draft" note, the cost first, then the draft card: copy, open the thread,
 * why this works, what to check, what the rules left out, and a short
 * "Before you post" checklist. Long articles can open in AI Writer.
 * Nothing is posted for the owner.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, seo } from '../../api';
import { aiBlocked, useAiReady } from '../../ai';
import { costText } from './SiteContext';
import { IPostedIt } from './PostedLog';

const LABEL = {
	reply: __( 'Draft a reply', 'chatbot' ),
	answer: __( 'Draft an answer', 'chatbot' ),
	article: __( 'Write the article', 'chatbot' ),
	video: __( 'Video idea', 'chatbot' ),
};

const KIND = {
	reddit: 'reply',
	forum: 'reply',
	reviews: 'reply',
	quora: 'answer',
	stackexchange: 'answer',
	linkedin: 'article',
	medium: 'article',
	youtube: 'video',
};

const Checklist = () => {
	const [ ticks, setTicks ] = useState( {} );
	const items = [
		__( 'I read this community’s rules', 'chatbot' ),
		__( 'I post from my own account', 'chatbot' ),
		__( 'I will come back to answer replies', 'chatbot' ),
	];
	return (
		<div className="qcseo-draft__checklist">
			<strong>{ __( 'Before you post', 'chatbot' ) }</strong>
			{ items.map( ( item, i ) => (
				<CheckboxControl
					key={ i }
					__nextHasNoMarginBottom
					label={ item }
					checked={ !! ticks[ i ] }
					onChange={ ( on ) => setTicks( { ...ticks, [ i ]: on } ) }
				/>
			) ) }
		</div>
	);
};

const DraftCard = ( { place, draft } ) => {
	const [ copied, setCopied ] = useState( false );
	const copy = () => {
		const done = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2500 );
		};
		const text = draft.title
			? `${ draft.title }\n\n${ draft.text }`
			: draft.text;
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard.writeText( text ).then( done, done );
		} else {
			done();
		}
	};
	const checks = [
		...draft.check_claims,
		...draft.honesty.map(
			( flag ) =>
				`${ flag.label }: ${ flag.words.join( ', ' ) }${
					flag.message ? ` (${ flag.message })` : ''
				}`
		),
	];
	return (
		<div className="qcseo-draft">
			{ draft.title && (
				<p>
					<strong>{ draft.title }</strong>
				</p>
			) }
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Your draft', 'chatbot' ) }
				hideLabelFromVision
				value={ draft.text }
				rows={ Math.min( 14, 4 + draft.text.split( '\n' ).length ) }
				readOnly
			/>
			<div className="qcseo-actions">
				<Button variant="primary" size="compact" onClick={ copy }>
					{ copied
						? __( 'Copied', 'chatbot' )
						: __( 'Copy text', 'chatbot' ) }
				</Button>
				{ place.url && (
					<Button
						variant="secondary"
						size="compact"
						href={ place.url }
						target="_blank"
						rel="noreferrer noopener"
					>
						{ __( 'Open the thread', 'chatbot' ) }
					</Button>
				) }
				{ place.writer_url && (
					<Button
						variant="secondary"
						size="compact"
						href={ place.writer_url }
					>
						{ __( 'Write in AI Writer', 'chatbot' ) }
					</Button>
				) }
			</div>
			{ draft.why && (
				<p>
					<strong>
						{ __( 'Why this works:', 'chatbot' ) }
					</strong>{ ' ' }
					{ draft.why }
				</p>
			) }
			{ checks.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<strong>
						{ __(
							'Check this before posting',
							'chatbot'
						) }
					</strong>
					<ul>
						{ checks.map( ( c, i ) => (
							<li key={ i }>{ c }</li>
						) ) }
					</ul>
				</Notice>
			) }
			{ draft.left_out && (
				<p className="qcseo-muted">
					<strong>
						{ __( 'Left out by the rules:', 'chatbot' ) }
					</strong>{ ' ' }
					{ draft.left_out }
				</p>
			) }
			{ draft.disclosure_added && (
				<p className="qcseo-muted">
					{ __(
						'The disclosure line was added because the draft mentions your business.',
						'chatbot'
					) }
				</p>
			) }
			{ draft.similar && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This draft is close to one you made for another place. Change it before posting: communities treat repeated text as spam.',
						'chatbot'
					) }
				</Notice>
			) }
			<Checklist />
		</div>
	);
};

/**
 * @param {Object}   props        Props.
 * @param {Object}   props.place  Place.
 * @param {Function} props.reload Reload the list.
 * @return {Element} Draft area.
 */
const PlaceDraft = ( { place, reload } ) => {
	const ai = useAiReady();
	const [ open, setOpen ] = useState( false );
	const [ form, setForm ] = useState( { tip: '', example: '', steer: '' } );
	const [ estimate, setEstimate ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ current, setCurrent ] = useState( place );
	const [ message, setMessage ] = useState( '' );
	const kind = KIND[ place.platform ] || 'reply';
	const draft = current.draft;
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const start = () => {
		setOpen( true );
		setMessage( '' );
		seo( 'cited/drafts/estimate', { method: 'POST', data: {} } )
			.then( setEstimate )
			.catch( ( e ) => setMessage( errorMessage( e ) ) );
	};
	const write = () => {
		setBusy( true );
		setMessage( '' );
		seo( 'cited/drafts/create', {
			method: 'POST',
			data: { id: place.id, ...form },
		} )
			.then( ( next ) => {
				setCurrent( next );
				setOpen( false );
			} )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<div className="qcseo-place__draft">
			{ draft && ! open && (
				<DraftCard place={ current } draft={ draft } />
			) }
			{ open && (
				<div className="qcseo-form qcseo-draft-ask">
					<p className="qcseo-muted">
						{ __(
							'Two quick questions make the draft yours. Both are optional.',
							'chatbot'
						) }
					</p>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'One tip you’d give a customer about this',
							'chatbot'
						) }
						value={ form.tip }
						onChange={ set( 'tip' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'A real example or number from your work',
							'chatbot'
						) }
						value={ form.example }
						onChange={ set( 'example' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Steer this draft', 'chatbot' ) }
						help={ __(
							'For example: "Mention the free version" or "Keep it under 80 words".',
							'chatbot'
						) }
						value={ form.steer }
						onChange={ set( 'steer' ) }
					/>
					<div className="qcseo-actions">
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || aiBlocked( ai ) }
							onClick={ write }
						>
							{ __( 'Write the draft', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
						{ estimate && (
							<span className="qcseo-muted">
								{ sprintf(
									/* translators: %s: cost. */
									__(
										'One AI request, %s.',
										'chatbot'
									),
									costText( estimate.cost )
								) }
							</span>
						) }
					</div>
				</div>
			) }
			{ message && <p className="qcseo-error">{ message }</p> }
			<div className="qcseo-actions">
				{ ! open && (
					<Button
						variant={ draft ? 'tertiary' : 'secondary' }
						size="compact"
						onClick={ start }
					>
						{ draft
							? __( 'Rewrite', 'chatbot' )
							: LABEL[ kind ] }
					</Button>
				) }
				{ ! open && (
					<IPostedIt
						place={ place }
						onLogged={ () => reload( { posted: true } ) }
					/>
				) }
				{ applyFilters( 'qcld_wpbot_seo.cited.draftActions', null, {
					place: current,
					reload,
				} ) }
			</div>
		</div>
	);
};

export default PlaceDraft;
