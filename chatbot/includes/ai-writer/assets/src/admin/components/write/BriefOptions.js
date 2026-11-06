/**
 * The brief's options (CR-9): always visible and compact (two columns on
 * wide screens). Main search, who it is for, search words, exact length,
 * tone, intent and questions. Empty options are filled from free sources as
 * the topic is typed (each with its source under the field); "Suggest with
 * AI" fills what is still empty with one small AI call, on click only. A
 * value the owner typed is never replaced.
 */
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage, writer } from '../../api';
import ListField from '../ListField';
import { INTENTS } from './SearchData';

const FIELDS = [ 'main_query', 'audience', 'keywords', 'words', 'tone' ];

const isEmpty = ( value ) =>
	Array.isArray( value ) ? ! value.length : ! value;

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

const BriefOptions = ( { brief, setBrief } ) => {
	// field => { source, value }: values that came from a suggestion.
	const [ filled, setFilled ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const latest = useRef( brief );
	latest.current = brief;

	/**
	 * Fill empty options. Suggestions from free sources may also replace
	 * an earlier suggestion of theirs (the topic changed); AI suggestions
	 * only fill what is empty.
	 *
	 * @param {Object}  suggestions field => { value|values, source }.
	 * @param {boolean} replace     Replace earlier suggestions too.
	 */
	const apply = ( suggestions, replace = true ) => {
		const current = latest.current;
		const next = { ...current };
		const marks = { ...filled };
		FIELDS.forEach( ( field ) => {
			const item = suggestions[ field ];
			if ( ! item ) {
				return;
			}
			const value = 'keywords' === field ? item.values : item.value;
			const mine =
				replace &&
				marks[ field ] &&
				same( current[ field ], marks[ field ].value );
			if ( isEmpty( current[ field ] ) || mine ) {
				next[ field ] = value;
				marks[ field ] = { source: item.source, value };
			}
		} );
		setFilled( marks );
		setBrief( next );
	};

	// Free sources, as the topic is typed.
	useEffect( () => {
		const topic = ( brief.topic || '' ).trim();
		if ( topic.length < 4 ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			writer(
				`write/prefill?topic=${ encodeURIComponent(
					topic
				) }&type=${ encodeURIComponent( brief.type || '' ) }`
			)
				.then( ( suggestions ) => apply( suggestions ) )
				.catch( () => {} );
		}, 600 );
		return () => clearTimeout( timer );
		// Re-run on topic and type only; apply() reads the latest brief.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ brief.topic, brief.type ] );

	const suggestWithAi = () => {
		setBusy( true );
		setError( '' );
		writer( 'write/prefill/ai', {
			method: 'POST',
			data: { topic: brief.topic, type: brief.type },
		} )
			.then( ( suggestions ) => apply( suggestions, false ) )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	// Typing in a field makes it the owner's own value.
	const set = ( field, value ) => {
		if ( filled[ field ] ) {
			const marks = { ...filled };
			delete marks[ field ];
			setFilled( marks );
		}
		setBrief( { ...latest.current, [ field ]: value } );
	};
	const from = ( field ) =>
		filled[ field ] && filled[ field ].source
			? sprintf(
					/* translators: %s: where the value came from. */
					__( 'From %s', 'chatbot' ),
					filled[ field ].source
				)
			: undefined;
	const anyEmpty = [ 'main_query', 'audience', 'keywords', 'tone' ].some(
		( field ) => isEmpty( brief[ field ] )
	);

	return (
		<div className="qcwr-brief-options">
			<div className="qcwr-brief-options__grid">
				<TextControl
					label={ __(
						'Main search this page should be found for',
						'chatbot'
					) }
					placeholder={ __(
						'e.g. kids bike sizes',
						'chatbot'
					) }
					help={ from( 'main_query' ) }
					value={ brief.main_query || '' }
					onChange={ ( value ) => set( 'main_query', value ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Who is it for?', 'chatbot' ) }
					placeholder={ __(
						'e.g. parents of young riders',
						'chatbot'
					) }
					help={ from( 'audience' ) }
					value={ brief.audience || '' }
					onChange={ ( value ) => set( 'audience', value ) }
					__nextHasNoMarginBottom
				/>
				<ListField
					label={ __(
						'Words people search for (comma separated)',
						'chatbot'
					) }
					placeholder={ __(
						'e.g. bike size chart, 20 inch bike',
						'chatbot'
					) }
					help={ from( 'keywords' ) }
					list={ brief.keywords || [] }
					onChange={ ( value ) => set( 'keywords', value ) }
					__nextHasNoMarginBottom
				/>
				<div className="qcwr-brief-options__pair">
					<TextControl
						type="number"
						min={ 0 }
						label={ __(
							'Exact length in words (optional)',
							'chatbot'
						) }
						placeholder={ __( 'Default', 'chatbot' ) }
						help={ from( 'words' ) }
						value={ brief.words || '' }
						onChange={ ( value ) =>
							set( 'words', Number( value ) || 0 )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __(
							'Tone for this piece (optional)',
							'chatbot'
						) }
						placeholder={ __(
							'e.g. friendly, expert',
							'chatbot'
						) }
						help={ from( 'tone' ) }
						value={ brief.tone || '' }
						onChange={ ( value ) => set( 'tone', value ) }
						__nextHasNoMarginBottom
					/>
				</div>
				<SelectControl
					label={ __(
						'What the searcher wants (intent)',
						'chatbot'
					) }
					value={ brief.intent || '' }
					options={ INTENTS() }
					onChange={ ( value ) => set( 'intent', value ) }
					__nextHasNoMarginBottom
				/>
				<ListField
					label={ __(
						'Questions to answer (one per line, optional)',
						'chatbot'
					) }
					separator={ '\n' }
					rows={ 2 }
					list={ brief.questions || [] }
					onChange={ ( value ) => set( 'questions', value ) }
					__nextHasNoMarginBottom
				/>
			</div>
			{ ( brief.topic || '' ).trim().length >= 4 && anyEmpty && (
				<p className="qcwr-brief-options__ai">
					<Button
						variant="secondary"
						size="small"
						isBusy={ busy }
						disabled={ busy }
						onClick={ suggestWithAi }
					>
						{ __( 'Suggest with AI', 'chatbot' ) }
					</Button>{ ' ' }
					<span className="qcwr-muted">
						{ __(
							'Fills the empty options (one small AI request).',
							'chatbot'
						) }
					</span>
					{ error && <span className="qcwr-error">{ error }</span> }
				</p>
			) }
		</div>
	);
};

export default BriefOptions;
