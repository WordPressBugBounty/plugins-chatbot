/**
 * Step 1: the brief. Two fields are enough; everything else is optional.
 */
import {
	Button,
	CheckboxControl,
	FormFileUpload,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { data, errorMessage, writer } from '../../api';
import BriefOptions from './BriefOptions';
import SearchData from './SearchData';
import WhoRanks from './WhoRanks';
import FormatPicker from './FormatPicker';
import PersuasionPicker from '../persuasion/PersuasionPicker';
import { ProSpots } from '../pro/ProPreview';

const TYPES = () => [
	{ label: __( 'Blog post', 'chatbot' ), value: 'blog_post' },
	{ label: __( 'Help article', 'chatbot' ), value: 'help_article' },
	{ label: __( 'FAQ', 'chatbot' ), value: 'faq' },
	{ label: __( 'Page copy', 'chatbot' ), value: 'page_copy' },
	{ label: __( 'Product description', 'chatbot' ), value: 'product' },
];

const BriefStep = ( { brief, setBrief, onNext, busy, linkPosts } ) => {
	const [ search, setSearch ] = useState( '' );
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ documents, setDocuments ] = useState( [] );
	const [ knowledge, setKnowledge ] = useState( null );
	const [ estimate, setEstimate ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ uploading, setUploading ] = useState( false );

	const [ questions, setQuestions ] = useState( [] );
	const answer = 'answer' === brief.mode;
	// Functional updates: replies that arrive later (knowledge on mount) must
	// not undo what the owner typed meanwhile.
	const set = ( key, value ) =>
		setBrief( ( current ) => ( { ...current, [ key ]: value } ) );
	const chosenType = ( data.contentTypes || [] ).find(
		( type ) => type.value === brief.type
	);
	const productsNeeded = chosenType ? chosenType.products : 0;
	const setSource = ( key, value ) =>
		setBrief( ( current ) => ( {
			...current,
			sources: { ...current.sources, [ key ]: value },
		} ) );
	const lengths = data.settings.lengths;

	useEffect( () => {
		writer( 'knowledge' )
			.then( ( result ) => {
				setKnowledge( result );
				if (
					result.available &&
					result.default &&
					brief.sources.chatbot === undefined
				) {
					setSource( 'chatbot', true );
				}
			} )
			.catch( () => setKnowledge( { available: false } ) );
		writer( 'documents' )
			.then( setDocuments )
			.catch( () => {} );
		// Visitor questions from sources that collect them (chat ideas).
		if ( answer ) {
			writer( 'topics' )
				.then( ( result ) => {
					const ids = ( result.sources || [] )
						.filter( ( source ) => source.questions )
						.map( ( source ) => source.id );
					setQuestions(
						ids
							.flatMap( ( id ) => result.lists[ id ] || [] )
							.slice( 0, 12 )
					);
				} )
				.catch( () => {} );
		}
		// Run once on mount.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		const timer = setTimeout( () => {
			writer( `write/sources?search=${ encodeURIComponent( search ) }` )
				.then( setSuggestions )
				.catch( () => {} );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		if ( ! data.settings.show_estimates ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			writer( 'write/estimate', { method: 'POST', data: { brief } } )
				.then( setEstimate )
				.catch( () => setEstimate( null ) );
		}, 500 );
		return () => clearTimeout( timer );
	}, [ brief ] );

	const togglePost = ( id, checked ) =>
		setSource(
			'post_ids',
			checked
				? [ ...brief.sources.post_ids, id ]
				: brief.sources.post_ids.filter( ( item ) => item !== id )
		);
	const toggleDocument = ( id, checked ) =>
		setSource(
			'document_ids',
			checked
				? [ ...brief.sources.document_ids, id ]
				: brief.sources.document_ids.filter( ( item ) => item !== id )
		);

	const upload = ( event ) => {
		const file = event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const body = new window.FormData();
		body.append( 'file', file );
		setUploading( true );
		setError( '' );
		apiFetch( { path: '/wpbot-writer/v1/documents', method: 'POST', body } )
			.then( ( document ) => {
				setDocuments( [ document, ...documents ] );
				setSource( 'document_ids', [
					...brief.sources.document_ids,
					document.id,
				] );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setUploading( false ) );
	};

	const ready =
		brief.topic.trim().length > 3 || brief.sources.document_ids.length > 0;

	return (
		<div className="qcwr-card qcwr-brief">
			<h2>
				{ answer
					? __( 'Answer a visitor question', 'chatbot' )
					: __( 'What should we write?', 'chatbot' ) }
			</h2>
			{ applyFilters( 'qcld_wpbot_writer.write.brief_tools', null, {
				brief,
				setBrief,
			} ) }
			{ answer && questions.length > 0 && (
				<div className="qcwr-questions-pick">
					<p className="qcwr-muted">
						{ __(
							'Questions visitors asked in your chats:',
							'chatbot'
						) }
					</p>
					<div className="qcwr-examples">
						{ questions.map( ( item ) => (
							<Button
								key={ item.id }
								variant="secondary"
								onClick={ () =>
									setBrief( {
										...brief,
										topic: item.title,
										sources: {
											...brief.sources,
											notes: [
												item.reason,
												...( item.questions || [] ),
											]
												.filter( Boolean )
												.join( '\n' ),
										},
									} )
								}
							>
								{ item.title }
							</Button>
						) ) }
					</div>
				</div>
			) }
			{ brief.date && (
				<p className="qcwr-muted">
					{ sprintf(
						/* translators: %s: date. */
						__(
							'The draft will be dated %s on your calendar.',
							'chatbot'
						),
						brief.date
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="qcwr-grid">
				<SelectControl
					label={ __( 'Type', 'chatbot' ) }
					value={ brief.type }
					options={ ( data.contentTypes || TYPES() ).filter(
						( option ) =>
							! answer ||
							[ 'faq', 'help_article', 'page_section' ].includes(
								option.value
							)
					) }
					onChange={ ( value ) => set( 'type', value ) }
					help={
						productsNeeded > 0
							? sprintf(
									/* translators: %d: number of products. */
									__(
										'Choose at least %d of your products under Sources below.',
										'chatbot'
									),
									productsNeeded
								)
							: undefined
					}
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Length', 'chatbot' ) }
					value={ brief.length }
					options={ [ 'short', 'standard', 'long' ].map(
						( key ) => ( {
							value: key,
							label: sprintf(
								/* translators: 1: length name, 2: number of words. */
								__(
									'%1$s (about %2$d words)',
									'chatbot'
								),
								{
									short: __( 'Short', 'chatbot' ),
									standard: __(
										'Standard',
										'chatbot'
									),
									long: __( 'Long', 'chatbot' ),
								}[ key ],
								lengths[ key ]
							),
						} )
					) }
					onChange={ ( value ) => set( 'length', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Create as', 'chatbot' ) }
					value={ brief.post_type }
					options={ [
						{
							label: __( 'Post', 'chatbot' ),
							value: 'post',
						},
						{
							label: __( 'Page', 'chatbot' ),
							value: 'page',
						},
					] }
					onChange={ ( value ) => set( 'post_type', value ) }
					__nextHasNoMarginBottom
				/>
			</div>
			<TextControl
				label={
					answer
						? __( "The visitor's question", 'chatbot' )
						: __( 'Topic in one sentence', 'chatbot' )
				}
				placeholder={
					answer
						? __(
								'For example: Do you deliver on weekends?',
								'chatbot'
							)
						: __(
								'For example: How to choose the right bike size for a child',
								'chatbot'
							)
				}
				help={
					answer
						? __(
								'Names, emails and phone numbers are removed before anything is sent to the AI. Confirm the facts under Sources.',
								'chatbot'
							)
						: undefined
				}
				value={ brief.topic }
				onChange={ ( value ) => set( 'topic', value ) }
				__nextHasNoMarginBottom
			/>
			{ ! answer && (
				<FormatPicker brief={ brief } setBrief={ setBrief } />
			) }

			{ brief.map_topic && (
				<p className="qcwr-muted qcwr-brief__topic">
					{ __( 'Topic:', 'chatbot' ) }{ ' ' }
					<strong>{ brief.map_topic }</strong>
				</p>
			) }
			<SearchData brief={ brief } set={ set } linkPosts={ linkPosts } />
			<BriefOptions brief={ brief } setBrief={ setBrief } />
			<ProSpots
				spots={ [
					'write.saved_briefs',
					'write.voices',
					'write.deep_humanize',
					'write.review',
				] }
			/>
			<PersuasionPicker
				value={ brief.persuasion }
				onChange={ ( persuasion ) =>
					setBrief( ( current ) => ( { ...current, persuasion } ) )
				}
				type={ brief.type }
				signal={ {
					intent: brief.intent,
					idea_id: brief.idea_id,
					vault_id: brief.vault_id,
				} }
			/>
			<WhoRanks brief={ brief } setBrief={ setBrief } />

			<h3>{ __( 'Sources', 'chatbot' ) }</h3>
			<p className="qcwr-muted">
				{ __(
					'Everything ticked here is used as fact. Untick what you do not want used.',
					'chatbot'
				) }
			</p>
			<div className="qcwr-sources">
				<div>
					<TextControl
						label={ __(
							'Your pages and products',
							'chatbot'
						) }
						placeholder={ __( 'Search', 'chatbot' ) }
						value={ search }
						onChange={ setSearch }
						__nextHasNoMarginBottom
					/>
					<div className="qcwr-source-list">
						{ suggestions.map( ( item ) => (
							<CheckboxControl
								key={ item.id }
								label={ `${ item.title } (${ item.type })` }
								checked={ brief.sources.post_ids.includes(
									item.id
								) }
								onChange={ ( checked ) =>
									togglePost( item.id, checked )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
				</div>
				<div>
					<p className="qcwr-label">
						{ __(
							'Documents (PDF, Word, text)',
							'chatbot'
						) }
					</p>
					{ documents.map( ( item ) => (
						<CheckboxControl
							key={ item.id }
							label={ item.title }
							checked={ brief.sources.document_ids.includes(
								Number( item.id )
							) }
							onChange={ ( checked ) =>
								toggleDocument( Number( item.id ), checked )
							}
							__nextHasNoMarginBottom
						/>
					) ) }
					<FormFileUpload
						accept=".pdf,.docx,.txt,.md"
						onChange={ upload }
						render={ ( { openFileDialog } ) => (
							<Button
								variant="secondary"
								isBusy={ uploading }
								disabled={ uploading }
								onClick={ openFileDialog }
							>
								{ __( 'Upload a document', 'chatbot' ) }
							</Button>
						) }
					/>
					{ knowledge && knowledge.available && (
						<ToggleControl
							label={ __(
								'Use what your ChatBot knows',
								'chatbot'
							) }
							checked={ !! brief.sources.chatbot }
							onChange={ ( value ) =>
								setSource( 'chatbot', value )
							}
							__nextHasNoMarginBottom
						/>
					) }
				</div>
			</div>
			<TextareaControl
				label={ __(
					'Reference notes (prices, steps, examples…)',
					'chatbot'
				) }
				value={ brief.sources.notes }
				onChange={ ( value ) => setSource( 'notes', value ) }
				rows={ 4 }
				__nextHasNoMarginBottom
			/>

			<div className="qcwr-actions">
				<Button
					variant="primary"
					disabled={ ! ready || busy }
					isBusy={ busy }
					onClick={ onNext }
				>
					{ data.settings.ask_questions
						? __( 'Next: a few questions', 'chatbot' )
						: __( 'Plan the outline', 'chatbot' ) }
				</Button>
				{ estimate && estimate.calls > 0 && (
					<span className="qcwr-muted qcwr-estimate">
						{ estimate.usd > 0
							? sprintf(
									/* translators: 1: cost in dollars, 2: number of AI calls. */
									__(
										'Estimated cost: about $%1$s (%2$d short AI calls)',
										'chatbot'
									),
									estimate.usd.toFixed( 2 ),
									estimate.calls
								)
							: sprintf(
									/* translators: %d: number of AI calls. */
									__(
										'About %d short AI calls (price unknown for this model)',
										'chatbot'
									),
									estimate.calls
								) }
					</span>
				) }
			</div>
		</div>
	);
};

export default BriefStep;
