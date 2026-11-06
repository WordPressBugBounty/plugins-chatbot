/**
 * Site voice: create it from your own writing, adjust it, try it, save it.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	RangeControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage, writer } from '../api';
import RegenerateControl, {
	VersionPager,
	optionsLabel,
	useVersions,
} from './regenerate/Regenerate';

const SLIDERS = () => [
	[ 'formal', __( 'Formal ↔ casual', 'chatbot' ) ],
	[ 'serious', __( 'Serious ↔ playful', 'chatbot' ) ],
	[ 'technical', __( 'Technical ↔ simple', 'chatbot' ) ],
	[ 'reserved', __( 'Reserved ↔ enthusiastic', 'chatbot' ) ],
];

const TEXT_FIELDS = () => [
	[ 'summary', __( 'In one sentence', 'chatbot' ) ],
	[
		'point_of_view',
		__( 'Point of view (we, I or you)', 'chatbot' ),
	],
	[ 'sentence_length', __( 'Sentence length', 'chatbot' ) ],
	[ 'openings', __( 'Typical openings', 'chatbot' ) ],
	[ 'formatting', __( 'Formatting habits', 'chatbot' ) ],
	[ 'spelling', __( 'Spelling (US or UK)', 'chatbot' ) ],
	[ 'reading_level', __( 'Reading level', 'chatbot' ) ],
	[ 'cta_style', __( 'Call-to-action style', 'chatbot' ) ],
];

const VoiceSettings = () => {
	const [ profile, setProfile ] = useState( null );
	const [ loaded, setLoaded ] = useState( false );
	const [ creating, setCreating ] = useState( false );
	const [ samples, setSamples ] = useState( {
		post_ids: [],
		text: '',
		answers: {},
	} );
	const [ posts, setPosts ] = useState( [] );
	const [ trial, setTrial ] = useState( { topic: '', paragraph: '' } );
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	// CR-14: AI drafts of the profile and sample paragraphs, to flip between.
	const drafts = useVersions();
	const paragraphs = useVersions();

	const fail = ( err ) =>
		setMessage( { status: 'error', text: errorMessage( err ) } );

	useEffect( () => {
		writer( 'voices/site' )
			.then( ( voice ) => setProfile( voice ? voice.profile : null ) )
			.catch( fail )
			.finally( () => setLoaded( true ) );
		writer( 'write/sources?search=' )
			.then( setPosts )
			.catch( () => {} );
	}, [] );

	const set = ( key, value ) => setProfile( { ...profile, [ key ]: value } );

	const generate = ( options = null ) => {
		setBusy( 'generate' );
		writer( 'voices/generate', {
			method: 'POST',
			data: {
				post_ids: samples.post_ids,
				texts: samples.text.trim() ? [ samples.text ] : [],
				answers: samples.answers,
				...( options ? { again: true, regenerate: options } : {} ),
			},
		} )
			.then( ( result ) => {
				setProfile( result.profile );
				if ( ! options ) {
					drafts.reset();
				}
				drafts.add(
					result.profile,
					options
						? optionsLabel( options )
						: __( 'First draft', 'chatbot' )
				);
				setCreating( false );
				setMessage( {
					status: 'info',
					text: __(
						'Here is your voice. Adjust anything, try it, then save.',
						'chatbot'
					),
				} );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};
	const tryIt = ( options = null ) => {
		setBusy( 'try' );
		writer( 'voices/try', {
			method: 'POST',
			data: {
				profile,
				topic: trial.topic,
				...( options ? { again: true, regenerate: options } : {} ),
			},
		} )
			.then( ( result ) => {
				setTrial( { ...trial, paragraph: result.paragraph } );
				if ( ! options ) {
					paragraphs.reset();
				}
				paragraphs.add(
					result.paragraph,
					options ? optionsLabel( options ) : ''
				);
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};
	const save = () => {
		setBusy( 'save' );
		writer( 'voices/site', { method: 'POST', data: { profile } } )
			.then( ( voice ) => {
				setProfile( voice.profile );
				setMessage( {
					status: 'success',
					text: __(
						'Site voice saved. New writing uses it.',
						'chatbot'
					),
				} );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	if ( ! loaded ) {
		return <Spinner />;
	}

	return (
		<div className="qcwr-card qcwr-voice" id="qcwr-site-voice">
			<h2>{ __( 'Site voice', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'How your business sounds. AI Writer matches it in everything it writes.',
					'chatbot'
				) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }

			{ ( ! profile || creating ) && (
				<div className="qcwr-voice__create">
					<p>
						{ __(
							'Pick three to five of your best posts or pages, or paste something you wrote.',
							'chatbot'
						) }
					</p>
					<div className="qcwr-source-list">
						{ posts.map( ( post ) => (
							<CheckboxControl
								key={ post.id }
								label={ post.title }
								checked={ samples.post_ids.includes( post.id ) }
								onChange={ ( checked ) =>
									setSamples( {
										...samples,
										post_ids: checked
											? [ ...samples.post_ids, post.id ]
											: samples.post_ids.filter(
													( id ) => id !== post.id
												),
									} )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					<TextareaControl
						label={ __(
							'Or paste your own writing',
							'chatbot'
						) }
						rows={ 5 }
						value={ samples.text }
						onChange={ ( text ) =>
							setSamples( { ...samples, text } )
						}
						__nextHasNoMarginBottom
					/>
					<div className="qcwr-grid">
						{ [
							[
								'audience',
								__(
									'Who do you write for?',
									'chatbot'
								),
							],
							[
								'point_of_view',
								__( '"We" or "I"?', 'chatbot' ),
							],
							[
								'humour',
								__( 'Any humour?', 'chatbot' ),
							],
							[
								'never_words',
								__( 'Words you never use', 'chatbot' ),
							],
						].map( ( [ key, label ] ) => (
							<TextControl
								key={ key }
								label={ label }
								value={ samples.answers[ key ] || '' }
								onChange={ ( value ) =>
									setSamples( {
										...samples,
										answers: {
											...samples.answers,
											[ key ]: value,
										},
									} )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					<div className="qcwr-actions">
						<Button
							variant="primary"
							isBusy={ 'generate' === busy }
							disabled={
								!! busy ||
								( ! samples.post_ids.length &&
									! samples.text.trim() )
							}
							onClick={ () => generate() }
						>
							{ __( 'Create my voice', 'chatbot' ) }
						</Button>
						{ profile && (
							<Button
								variant="tertiary"
								onClick={ () => setCreating( false ) }
							>
								{ __( 'Cancel', 'chatbot' ) }
							</Button>
						) }
					</div>
				</div>
			) }

			{ profile && ! creating && (
				<div className="qcwr-voice__profile">
					{ drafts.versions.length > 0 && (
						<div className="qcwr-actions qcwr-voice__again">
							<RegenerateControl
								kind="voice"
								label={ __(
									'Regenerate the voice',
									'chatbot'
								) }
								hide={ [
									'tone',
									'length',
									'technique',
									'language',
									'voice',
								] }
								busy={ 'generate' === busy }
								onRegenerate={ generate }
							/>
							{ drafts.versions.length > 1 && (
								<VersionPager
									versions={ drafts.versions }
									index={ drafts.index }
									onChange={ ( index ) => {
										drafts.show( index );
										setProfile(
											drafts.versions[ index ].data
										);
									} }
								/>
							) }
						</div>
					) }
					<div className="qcwr-grid">
						{ SLIDERS().map( ( [ key, label ] ) => (
							<RangeControl
								key={ key }
								label={ label }
								min={ 0 }
								max={ 100 }
								value={
									profile.sliders
										? profile.sliders[ key ]
										: 50
								}
								onChange={ ( value ) =>
									set( 'sliders', {
										...profile.sliders,
										[ key ]: value,
									} )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					<div className="qcwr-grid">
						{ TEXT_FIELDS().map( ( [ key, label ] ) => (
							<TextControl
								key={ key }
								label={ label }
								value={ profile[ key ] || '' }
								onChange={ ( value ) => set( key, value ) }
								__nextHasNoMarginBottom
							/>
						) ) }
						<TextControl
							label={ __(
								'Phrases you use (comma separated)',
								'chatbot'
							) }
							value={ ( profile.signature_phrases || [] ).join(
								', '
							) }
							onChange={ ( value ) =>
								set(
									'signature_phrases',
									value
										.split( ',' )
										.map( ( item ) => item.trim() )
										.filter( Boolean )
								)
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __(
								'Words to avoid (comma separated)',
								'chatbot'
							) }
							value={ ( profile.avoid_words || [] ).join( ', ' ) }
							onChange={ ( value ) =>
								set(
									'avoid_words',
									value
										.split( ',' )
										.map( ( item ) => item.trim() )
										.filter( Boolean )
								)
							}
							__nextHasNoMarginBottom
						/>
					</div>
					{ ( profile.excerpts || [] ).length > 0 && (
						<details>
							<summary>
								{ __(
									'Style examples from your own writing',
									'chatbot'
								) }
							</summary>
							{ profile.excerpts.map( ( excerpt, index ) => (
								<blockquote key={ index }>
									{ excerpt }
								</blockquote>
							) ) }
						</details>
					) }
					<h3>{ __( 'Try this voice', 'chatbot' ) }</h3>
					<div className="qcwr-actions">
						<TextControl
							label={ __( 'Topic', 'chatbot' ) }
							hideLabelFromVision
							placeholder={ __(
								'Any topic, for example "our opening hours"',
								'chatbot'
							) }
							value={ trial.topic }
							onChange={ ( topic ) =>
								setTrial( { ...trial, topic } )
							}
							__nextHasNoMarginBottom
						/>
						<Button
							variant="secondary"
							isBusy={ 'try' === busy }
							disabled={ !! busy }
							onClick={ () => tryIt() }
						>
							{ __(
								'Write a sample paragraph',
								'chatbot'
							) }
						</Button>
					</div>
					{ trial.paragraph && (
						<blockquote className="qcwr-voice__sample">
							{ trial.paragraph.replace( /<[^>]+>/g, '' ) }
						</blockquote>
					) }
					{ trial.paragraph && (
						<div className="qcwr-actions qcwr-voice__sample-again">
							<RegenerateControl
								kind="voice-try"
								hide={ [ 'technique', 'voice' ] }
								busy={ 'try' === busy }
								onRegenerate={ tryIt }
							/>
							{ paragraphs.versions.length > 1 && (
								<VersionPager
									versions={ paragraphs.versions }
									index={ paragraphs.index }
									onChange={ ( index ) => {
										paragraphs.show( index );
										setTrial( {
											...trial,
											paragraph:
												paragraphs.versions[ index ]
													.data,
										} );
									} }
								/>
							) }
						</div>
					) }
					<div className="qcwr-actions">
						<Button
							variant="primary"
							isBusy={ 'save' === busy }
							disabled={ !! busy }
							onClick={ save }
						>
							{ __( 'Save voice', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setCreating( true ) }
						>
							{ __(
								'Create again from samples',
								'chatbot'
							) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
};

export default VoiceSettings;
