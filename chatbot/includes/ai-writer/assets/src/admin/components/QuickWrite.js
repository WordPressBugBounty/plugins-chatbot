/**
 * Quick write: pick a template, fill two to four fields (or start from a
 * post), get three variations in the site voice, edit, copy or save them
 * to the Content Library. Used on the Quick write tab and in the editor.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import CompareTechniques from './persuasion/CompareTechniques';
import RegenerateControl, { VersionPager } from './regenerate/Regenerate';
import HonestyFlags from './persuasion/HonestyFlags';
import PersuasionPicker from './persuasion/PersuasionPicker';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import { ProSpots } from './pro/ProPreview';

// Templates whose copy can be compared, and as what (round 3 P3).
const COMPARE = {
	linkedin: 'social_post',
	facebook: 'social_post',
	instagram: 'social_post',
	x: 'social_post',
	google_business: 'social_post',
	email: 'email_subject',
};

const copy = ( text ) =>
	window.navigator.clipboard
		? window.navigator.clipboard.writeText( text )
		: Promise.reject( new Error( 'clipboard' ) );

const Variation = ( { variation, limit, template, sourcePost, onSaved } ) => {
	const [ text, setText ] = useState( variation.text );
	const [ state, setState ] = useState( '' );
	const length = [ ...text ].length;
	const over = limit > 0 && length > limit;

	const save = () => {
		setState( 'saving' );
		writer( 'quick/save', {
			method: 'POST',
			data: { template, text, source_post: sourcePost },
		} )
			.then( () => {
				setState( 'saved' );
				if ( onSaved ) {
					onSaved();
				}
			} )
			.catch( ( err ) => setState( errorMessage( err ) ) );
	};

	return (
		<div className="qcwr-card qcwr-variation">
			<TextareaControl
				label={ __( 'Variation', 'chatbot' ) }
				hideLabelFromVision
				value={ text }
				onChange={ setText }
				rows={ Math.min(
					14,
					Math.max( 4, text.split( '\n' ).length + 1 )
				) }
				__nextHasNoMarginBottom
			/>
			<p className={ over ? 'qcwr-over' : 'qcwr-muted' }>
				{ limit
					? sprintf(
							/* translators: 1: characters, 2: limit. */
							__( '%1$d / %2$d characters', 'chatbot' ),
							length,
							limit
						)
					: sprintf(
							/* translators: %d: characters. */
							__( '%d characters', 'chatbot' ),
							length
						) }
				{ variation.trimmed &&
					` · ${ __( 'shortened to fit', 'chatbot' ) }` }
			</p>
			<HonestyFlags flags={ variation.honesty } />
			{ variation.flags.length > 0 && (
				<ul className="qcwr-flags-list">
					{ variation.flags.map( ( flag, index ) => (
						<li key={ index }>{ flag.reason }</li>
					) ) }
				</ul>
			) }
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					onClick={ () =>
						copy( text )
							.then( () => setState( 'copied' ) )
							.catch( () =>
								setState(
									__(
										'Select the text and copy it.',
										'chatbot'
									)
								)
							)
					}
				>
					{ 'copied' === state
						? __( 'Copied', 'chatbot' )
						: __( 'Copy', 'chatbot' ) }
				</Button>
				<Button
					variant="secondary"
					isBusy={ 'saving' === state }
					disabled={ 'saving' === state || 'saved' === state }
					onClick={ save }
				>
					{ 'saved' === state
						? __( 'Saved to Library', 'chatbot' )
						: __( 'Save to Library', 'chatbot' ) }
				</Button>
			</div>
			{ ! [ '', 'saving', 'saved', 'copied' ].includes( state ) && (
				<p className="qcwr-over">{ state }</p>
			) }
		</div>
	);
};

const QuickWrite = ( {
	sourcePost = 0,
	compact = false,
	initialTemplate = '',
} ) => {
	const [ templates, setTemplates ] = useState( null );
	const [ template, setTemplate ] = useState( '' );
	const [ fields, setFields ] = useState( {} );
	const [ fromPost, setFromPost ] = useState( sourcePost > 0 );
	const [ result, setResult ] = useState( null );
	// CR-14: earlier sets of variations, from the server (kept a week).
	const [ history, setHistory ] = useState( { versions: [], index: 0 } );
	const [ items, setItems ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ comparing, setComparing ] = useState( false );

	const loadItems = () =>
		! compact &&
		writer( 'quick/items' )
			.then( ( body ) => setItems( body.items ) )
			.catch( () => {} );

	useEffect( () => {
		writer( 'quick/templates' )
			.then( ( body ) => {
				setTemplates( body.templates );
				const chosen = body.templates.find(
					( item ) => item.id === initialTemplate
				);
				setTemplate(
					( chosen || body.templates[ 0 ] || { id: '' } ).id
				);
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );
		loadItems();
	}, [] );

	if ( ! templates ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const current =
		templates.find( ( item ) => item.id === template ) || templates[ 0 ];

	const generate = ( regenerate = null ) => {
		setBusy( true );
		setError( '' );
		writer( 'quick/generate', {
			method: 'POST',
			data: {
				template,
				fields: regenerate ? { ...fields, regenerate } : fields,
				source_post: fromPost ? sourcePost : 0,
			},
		} )
			.then( ( body ) => {
				setResult( body );
				const list = body.versions || [];
				setHistory( { versions: list, index: list.length - 1 } );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div
			className={
				compact ? 'qcwr-quick qcwr-quick--compact' : 'qcwr-quick'
			}
		>
			<div className={ compact ? '' : 'qcwr-card' }>
				{ ! compact && (
					<h2>{ __( 'Quick write', 'chatbot' ) }</h2>
				) }
				{ ! compact && (
					<ProSpots
						spots={ [
							'quick.templates',
							'quick.bulk',
							'quick.campaigns',
						] }
					/>
				) }
				<SelectControl
					label={ __( 'What do you need?', 'chatbot' ) }
					value={ template }
					options={ templates.map( ( item ) => ( {
						value: item.id,
						label: item.label,
					} ) ) }
					onChange={ ( next ) => {
						setTemplate( next );
						setResult( null );
						setHistory( { versions: [], index: 0 } );
						// Choices are per piece: a new template detects again.
						setFields( ( was ) => ( {
							...was,
							persuasion: {},
						} ) );
					} }
					__nextHasNoMarginBottom
				/>
				{ sourcePost > 0 && (
					<label
						className="qcwr-inline-check"
						htmlFor="qcwr-quick-from-post"
					>
						<input
							id="qcwr-quick-from-post"
							type="checkbox"
							checked={ fromPost }
							onChange={ ( event ) =>
								setFromPost( event.target.checked )
							}
						/>
						{ __(
							'Start from this post (saved version)',
							'chatbot'
						) }
					</label>
				) }
				{ current.fields.map( ( field ) => {
					const props = {
						label: field.label,
						value: fields[ field.key ] || '',
						onChange: ( value ) =>
							setFields( { ...fields, [ field.key ]: value } ),
						__nextHasNoMarginBottom: true,
					};
					if ( 'select' === field.type ) {
						return (
							<SelectControl
								key={ field.key }
								{ ...props }
								options={ [
									{ value: '', label: '—' },
									...field.options.map( ( option ) => ( {
										value: option,
										label: option,
									} ) ),
								] }
							/>
						);
					}
					return 'textarea' === field.type ? (
						<TextareaControl
							key={ field.key }
							{ ...props }
							rows={ 3 }
						/>
					) : (
						<TextControl key={ field.key } { ...props } />
					);
				} ) }
				<PersuasionPicker
					key={ template }
					value={ fields.persuasion }
					onChange={ ( persuasion ) =>
						setFields( ( was ) => ( {
							...was,
							persuasion,
						} ) )
					}
					type={ template }
				/>
				<div className="qcwr-actions">
					{ result ? (
						<RegenerateControl
							kind="quick"
							busy={ busy }
							label={ __( 'Write 3 more', 'chatbot' ) }
							onRegenerate={ generate }
						/>
					) : (
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy }
							onClick={ () => generate() }
						>
							{ __( 'Write 3 variations', 'chatbot' ) }
						</Button>
					) }
					<VersionPager
						versions={ history.versions }
						index={ history.index }
						onChange={ ( index ) => {
							setHistory( { ...history, index } );
							if ( history.versions[ index ] ) {
								setResult( history.versions[ index ].data );
							}
						} }
					/>
					{ COMPARE[ template ] &&
						data.persuasion &&
						data.persuasion.compare && (
							<Button
								variant="secondary"
								isPressed={ comparing }
								onClick={ () => setComparing( ! comparing ) }
							>
								{ 'email_subject' === COMPARE[ template ]
									? __(
											'Compare subject lines',
											'chatbot'
										)
									: __(
											'Compare techniques',
											'chatbot'
										) }
							</Button>
						) }
				</div>
				{ comparing && COMPARE[ template ] && (
					<CompareTechniques
						key={ template }
						kind={ COMPARE[ template ] }
						getInput={ () => ( {
							network:
								'social_post' === COMPARE[ template ]
									? template
									: '',
							title: fields.about || '',
							notes: current.fields
								.filter(
									( field ) =>
										'about' !== field.key &&
										fields[ field.key ]
								)
								.map(
									( field ) =>
										`${ field.label }: ${ fields[ field.key ] }`
								)
								.join( '\n' ),
							post_id: fromPost ? sourcePost : 0,
							stage: ( fields.persuasion || {} ).stage || '',
						} ) }
						onUse={ ( answer ) => {
							setResult( ( was ) => ( {
								limit: was ? was.limit : 0,
								...( was || {} ),
								variations: [
									{
										text: answer.text,
										flags: [],
										honesty: answer.honesty,
										trimmed: false,
									},
									...( was ? was.variations : [] ),
								],
							} ) );
							setComparing( false );
						} }
						onClose={ () => setComparing( false ) }
					/>
				) }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
			</div>
			{ result && (
				<div
					className={
						compact
							? 'qcwr-variations-list'
							: 'qcwr-grid qcwr-variations-list'
					}
				>
					{ result.variations.map( ( variation, index ) => (
						<Variation
							key={ `${ index }-${ variation.text.slice( 0, 20 ) }` }
							variation={ variation }
							limit={ result.limit }
							template={ template }
							sourcePost={ fromPost ? sourcePost : 0 }
							onSaved={ loadItems }
						/>
					) ) }
				</div>
			) }
			{ ! compact && items.length > 0 && (
				<div className="qcwr-card">
					<h3>{ __( 'Saved pieces', 'chatbot' ) }</h3>
					<ul className="qcwr-library">
						{ items.map( ( item ) => (
							<li key={ item.id }>
								<strong>{ item.title }</strong>
								<Button
									variant="link"
									onClick={ () =>
										copy( item.content ).catch( () => {} )
									}
								>
									{ __( 'Copy', 'chatbot' ) }
								</Button>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
};

export default QuickWrite;
