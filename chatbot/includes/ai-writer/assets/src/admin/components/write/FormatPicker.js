/**
 * CR-16: the article format, chosen under the topic. AI Writer suggests one
 * from the topic (rules only, no AI call) and selects it until the owner
 * picks a format themselves; "Article" means no format.
 */
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, writer } from '../../api';
import { previewFor, ProSpot } from '../pro/ProPreview';

const FormatPicker = ( { brief, setBrief } ) => {
	const formats = data.formats || [];
	const [ suggestion, setSuggestion ] = useState( null );
	const [ hint, setHint ] = useState( null );
	const suggesting = false !== data.settings.format_suggest;

	const choose = ( value, picked = true ) =>
		setBrief( ( current ) => ( {
			...current,
			format: value,
			format_picked: picked || current.format_picked,
		} ) );

	useEffect( () => {
		if ( ! suggesting ) {
			return undefined;
		}
		if ( ( brief.topic || '' ).trim().length < 8 ) {
			setSuggestion( null );
			setHint( null );
			return undefined;
		}
		const timer = setTimeout( () => {
			const query = new URLSearchParams( {
				topic: brief.topic,
				intent: brief.intent || '',
				type: brief.type || '',
			} ).toString();
			writer( `write/formats/suggest?${ query }` )
				.then( ( result ) => {
					const next = result && result.suggestion;
					setSuggestion( next || null );
					setHint( ( result && result.hint ) || null );
					if ( next ) {
						setBrief( ( current ) =>
							current.format_picked
								? current
								: { ...current, format: next.id }
						);
					}
				} )
				.catch( () => {
					setSuggestion( null );
					setHint( null );
				} );
		}, 400 );
		return () => clearTimeout( timer );
		// The suggestion follows the topic, intent and type only.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ brief.topic, brief.intent, brief.type ] );

	if ( ! formats.length ) {
		return null;
	}
	const current = brief.format || '';
	const suggested = suggestion
		? formats.find( ( format ) => format.value === suggestion.id )
		: null;
	const chosen = formats.find( ( format ) => format.value === current );
	// Pro formats: greyed chips in the same row, each opening what Pro adds.
	const proFormats = ( data.proPreviews || [] )
		.map( ( item ) => item.spot )
		.filter(
			( spot ) =>
				0 === String( spot ).indexOf( 'format.' ) && previewFor( spot )
		);

	return (
		<div className="qcwr-formats">
			<p className="qcwr-formats__label">
				{ __( 'Format', 'chatbot' ) }
			</p>
			<div
				className="qcwr-formats__chips"
				role="radiogroup"
				aria-label={ __( 'Format', 'chatbot' ) }
			>
				<button
					type="button"
					role="radio"
					aria-checked={ '' === current }
					className={ `qcwr-chip${ '' === current ? ' is-on' : '' }` }
					onClick={ () => choose( '' ) }
				>
					{ __( 'Article (no format)', 'chatbot' ) }
				</button>
				{ formats.map( ( format ) => (
					<button
						key={ format.value }
						type="button"
						role="radio"
						aria-checked={ current === format.value }
						title={ format.short }
						className={ `qcwr-chip${
							current === format.value ? ' is-on' : ''
						}${
							suggested && suggested.value === format.value
								? ' is-suggested'
								: ''
						}` }
						onClick={ () => choose( format.value ) }
					>
						{ format.label }
					</button>
				) ) }
				{ proFormats.map( ( spot ) => (
					<ProSpot key={ spot } spot={ spot } chip />
				) ) }
			</div>
			{ suggested && (
				<div className="qcwr-formats__suggestion">
					<span>
						{ sprintf(
							/* translators: 1: format name, 2: reason. */
							__( 'Suggested: %1$s. %2$s', 'chatbot' ),
							suggested.label,
							suggestion.reason
						) }
					</span>
					{ current !== suggested.value && (
						<Button
							variant="link"
							onClick={ () => choose( suggested.value ) }
						>
							{ __( 'Use it', 'chatbot' ) }
						</Button>
					) }
				</div>
			) }
			{ hint && previewFor( 'format.' + hint.id ) && (
				<p className="qcwr-formats__hint">
					{ sprintf(
						/* translators: %s: format name, for example "Case study (Pro)". */
						__( 'A %s fits this topic.', 'chatbot' ),
						hint.label
					) }{ ' ' }
					<ProSpot
						spot={ 'format.' + hint.id }
						label={ __( 'See what it adds', 'chatbot' ) }
						variant="link"
					/>
				</p>
			) }
			{ chosen && (
				<p className="qcwr-muted qcwr-formats__short">
					{ chosen.short }
				</p>
			) }
		</div>
	);
};

export default FormatPicker;
