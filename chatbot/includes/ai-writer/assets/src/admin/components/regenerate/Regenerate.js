/**
 * Regenerate (CR-14): one shared control next to every piece of AI text.
 *
 * - "↻ Regenerate": a new version with the same options, in one click.
 * - "▾": "Regenerate with changes": tone, length, reading level,
 *   technique (or No specific technique), language and "What to change".
 * - Above the owner's cost threshold it asks first, with "Don't ask again
 *   this session" (kept in the browser's session storage).
 *
 * `onRegenerate( options )` does the work; the caller sends `options` as
 * `regenerate` (with `again: true`) to its own route.
 */
import {
	Button,
	CheckboxControl,
	Dropdown,
	Modal,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, writer } from '../../api';
import './style.scss';

const NO_ASK = 'qcwr_regenerate_no_ask';
const estimates = {};

/**
 * The estimate of a kind (fetched once per page).
 *
 * @param {string} kind Kind.
 * @return {Promise<Object>} usd, confirm, threshold.
 */
export const estimateFor = ( kind ) => {
	if ( ! estimates[ kind ] ) {
		estimates[ kind ] = writer(
			`regenerate/estimate?kind=${ encodeURIComponent( kind ) }`
		).catch( () => ( { usd: 0, confirm: false, threshold: 0 } ) );
	}
	return estimates[ kind ];
};

/**
 * Whether the owner chose not to be asked again in this browser session.
 *
 * @return {boolean} Skip.
 */
const skipAsking = () => {
	try {
		return '1' === window.sessionStorage.getItem( NO_ASK );
	} catch {
		return false;
	}
};

const rememberSkip = () => {
	try {
		window.sessionStorage.setItem( NO_ASK, '1' );
	} catch {}
};

/**
 * Techniques that can be chosen (from AI Writer's library).
 *
 * @return {Object[]} Options for a select.
 */
const techniqueOptions = () => {
	const lib = data.persuasion;
	if ( ! lib ) {
		return [];
	}
	return [
		{ value: '', label: __( 'As before', 'chatbot' ) },
		lib.none && { value: lib.none.id, label: lib.none.label },
		...[
			...( lib.frameworks || [] ),
			...( lib.angles || [] ),
			...( lib.techniques || [] ),
		].map( ( item ) => ( { value: item.id, label: item.label } ) ),
	].filter( Boolean );
};

/**
 * Voices that can be chosen (WPBot Pro fills the list; empty hides the option).
 *
 * @return {Object[]} Options for a select.
 */
const voiceOptions = () => {
	const voices = ( data.regenerate && data.regenerate.voices ) || [];
	return voices.length
		? [
				{ value: '', label: __( 'As before', 'chatbot' ) },
				...voices,
			]
		: [];
};

const EMPTY = {
	voice: '',
	tone: '',
	length: 'same',
	level: '',
	technique: '',
	language: '',
	change: '',
};

/**
 * The "Regenerate with changes" form.
 *
 * @param {Object}                  props             Props.
 * @param {Object}                  props.value       Options.
 * @param {(value: Object) => void} props.onChange    Change.
 * @param {() => void}              props.onSubmit    Regenerate.
 * @param {string[]}                props.hide        Options not used by this kind.
 * @param {string}                  props.submitLabel Button label.
 * @return {Element} Form.
 */
export const OptionsForm = ( {
	value,
	onChange,
	onSubmit,
	hide = [],
	submitLabel = __( 'Regenerate', 'chatbot' ),
} ) => {
	const set = ( key ) => ( next ) => onChange( { ...value, [ key ]: next } );
	const techniques = techniqueOptions();
	const voices = voiceOptions();
	return (
		<div className="qcwr-regen__form">
			{ ! hide.includes( 'tone' ) && (
				<SelectControl
					label={ __( 'Tone', 'chatbot' ) }
					value={ value.tone }
					options={ [
						{
							value: '',
							label: __( 'As before', 'chatbot' ),
						},
						{
							value: 'friendly',
							label: __( 'Friendly', 'chatbot' ),
						},
						{
							value: 'professional',
							label: __( 'Professional', 'chatbot' ),
						},
						{
							value: 'confident',
							label: __( 'Confident', 'chatbot' ),
						},
						{
							value: 'playful',
							label: __( 'Playful', 'chatbot' ),
						},
						{
							value: 'empathetic',
							label: __( 'Empathetic', 'chatbot' ),
						},
						{
							value: 'direct',
							label: __( 'Direct', 'chatbot' ),
						},
					] }
					onChange={ set( 'tone' ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ! hide.includes( 'length' ) && (
				<SelectControl
					label={ __( 'Length', 'chatbot' ) }
					value={ value.length }
					options={ [
						{
							value: 'same',
							label: __( 'About the same', 'chatbot' ),
						},
						{
							value: 'shorter',
							label: __( 'Shorter', 'chatbot' ),
						},
						{
							value: 'longer',
							label: __( 'Longer', 'chatbot' ),
						},
					] }
					onChange={ set( 'length' ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ! hide.includes( 'level' ) && (
				<SelectControl
					label={ __( 'Reading level', 'chatbot' ) }
					value={ value.level }
					options={ [
						{
							value: '',
							label: __( 'As before', 'chatbot' ),
						},
						{
							value: 'simple',
							label: __( 'Simple', 'chatbot' ),
						},
						{
							value: 'standard',
							label: __( 'Standard', 'chatbot' ),
						},
						{
							value: 'advanced',
							label: __( 'Advanced', 'chatbot' ),
						},
					] }
					onChange={ set( 'level' ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ! hide.includes( 'technique' ) && techniques.length > 0 && (
				<SelectControl
					label={ __( 'Technique', 'chatbot' ) }
					value={ value.technique }
					options={ techniques }
					onChange={ set( 'technique' ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ! hide.includes( 'voice' ) && voices.length > 0 && (
				<SelectControl
					label={ __( 'Voice', 'chatbot' ) }
					value={ value.voice || '' }
					options={ voices }
					onChange={ set( 'voice' ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ ! hide.includes( 'language' ) && (
				<TextControl
					label={ __( 'Language', 'chatbot' ) }
					placeholder={ __( 'As before', 'chatbot' ) }
					value={ value.language }
					onChange={ set( 'language' ) }
					__nextHasNoMarginBottom
				/>
			) }
			<TextareaControl
				label={ __( 'What to change', 'chatbot' ) }
				placeholder={ __(
					'For example: mention free delivery, less formal, start with a question',
					'chatbot'
				) }
				value={ value.change }
				onChange={ set( 'change' ) }
				rows={ 3 }
				maxLength={ 500 }
				__nextHasNoMarginBottom
			/>
			<Button variant="primary" onClick={ onSubmit }>
				{ submitLabel }
			</Button>
		</div>
	);
};

/**
 * The control.
 *
 * @param {Object}                    props              Props.
 * @param {string}                    props.kind         Kind (for the estimate).
 * @param {(options: Object) => void} props.onRegenerate Do it.
 * @param {boolean}                   props.busy         A request runs.
 * @param {string}                    props.label        Button label.
 * @param {string[]}                  props.hide         Options not used by this kind.
 * @return {Element} Element.
 */
const RegenerateControl = ( {
	kind,
	onRegenerate,
	busy = false,
	label = __( 'Regenerate', 'chatbot' ),
	hide = [],
} ) => {
	const [ options, setOptions ] = useState( EMPTY );
	const [ asking, setAsking ] = useState( null );
	const [ noAsk, setNoAsk ] = useState( false );
	const [ tip, setTip ] = useState( '' );

	const run = ( chosen ) =>
		estimateFor( kind ).then( ( estimate ) => {
			if ( estimate.confirm && ! skipAsking() ) {
				setAsking( { estimate, chosen } );
				return;
			}
			onRegenerate( chosen );
		} );

	const loadTip = () =>
		! tip &&
		estimateFor( kind ).then( ( estimate ) =>
			setTip(
				sprintf(
					/* translators: %s: cost in US dollars. */
					__( 'A new version, about $%s', 'chatbot' ),
					Number( estimate.usd ).toFixed( 3 )
				)
			)
		);

	return (
		<span className="qcwr-regen">
			<Button
				variant="secondary"
				size="compact"
				icon="update"
				disabled={ busy }
				isBusy={ busy }
				title={ tip || undefined }
				onMouseEnter={ loadTip }
				onFocus={ loadTip }
				onClick={ () => run( {} ) }
				className="qcwr-regen__button"
			>
				{ label }
			</Button>
			<Dropdown
				popoverProps={ { placement: 'bottom-end' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="secondary"
						size="compact"
						icon="arrow-down-alt2"
						label={ __(
							'Regenerate with changes',
							'chatbot'
						) }
						aria-expanded={ isOpen }
						disabled={ busy }
						onClick={ onToggle }
						className="qcwr-regen__more"
					/>
				) }
				renderContent={ ( { onClose } ) => (
					<OptionsForm
						value={ options }
						onChange={ setOptions }
						hide={ hide }
						onSubmit={ () => {
							onClose();
							run( options );
						} }
					/>
				) }
			/>
			{ asking && (
				<Modal
					title={ __( 'Regenerate?', 'chatbot' ) }
					onRequestClose={ () => setAsking( null ) }
					size="small"
					className="qcwr-regen__confirm"
				>
					<p>
						{ sprintf(
							/* translators: 1: cost, 2: threshold. */
							__(
								'This will cost about $%1$s (above your $%2$s limit for asking).',
								'chatbot'
							),
							Number( asking.estimate.usd ).toFixed( 3 ),
							Number( asking.estimate.threshold ).toFixed( 2 )
						) }
					</p>
					<CheckboxControl
						label={ __(
							"Don't ask again this session",
							'chatbot'
						) }
						checked={ noAsk }
						onChange={ setNoAsk }
						__nextHasNoMarginBottom
					/>
					<div className="qcwr-actions">
						<Button
							variant="primary"
							onClick={ () => {
								if ( noAsk ) {
									rememberSkip();
								}
								const { chosen } = asking;
								setAsking( null );
								onRegenerate( chosen );
							} }
						>
							{ __( 'Regenerate', 'chatbot' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setAsking( null ) }
						>
							{ __( 'Cancel', 'chatbot' ) }
						</Button>
					</div>
				</Modal>
			) }
		</span>
	);
};

/**
 * Version pager: ‹ 2 of 5 › with the options each version used.
 *
 * @param {Object}                  props          Props.
 * @param {Object[]}                props.versions Versions (label each).
 * @param {number}                  props.index    Shown version.
 * @param {(index: number) => void} props.onChange Show another.
 * @return {Element|null} Element.
 */
export const VersionPager = ( { versions, index, onChange } ) => {
	if ( ! versions || versions.length < 2 ) {
		return null;
	}
	const current = versions[ index ] || {};
	return (
		<span className="qcwr-regen__pager">
			<Button
				size="compact"
				icon="arrow-left-alt2"
				label={ __( 'Earlier version', 'chatbot' ) }
				disabled={ index <= 0 }
				onClick={ () => onChange( index - 1 ) }
			/>
			<span className="qcwr-regen__count">
				{ sprintf(
					/* translators: 1: version number, 2: versions. */
					__( '%1$d of %2$d', 'chatbot' ),
					index + 1,
					versions.length
				) }
			</span>
			<Button
				size="compact"
				icon="arrow-right-alt2"
				label={ __( 'Later version', 'chatbot' ) }
				disabled={ index >= versions.length - 1 }
				onClick={ () => onChange( index + 1 ) }
			/>
			{ current.label && (
				<span className="qcwr-regen__label">{ current.label }</span>
			) }
		</span>
	);
};

/**
 * Versions kept on the screen: add each result; flip between them.
 *
 * @return {Object} versions, index, current, add( data, label, all ), show( index ).
 */
export const useVersions = () => {
	const [ state, setState ] = useState( { versions: [], index: 0 } );
	return {
		versions: state.versions,
		index: state.index,
		current: state.versions[ state.index ]
			? state.versions[ state.index ].data
			: null,
		/**
		 * Add a result, or take the server's list when it sends one.
		 *
		 * @param {Object|string} result Result.
		 * @param {string}        label  Options summary.
		 * @param {Object[]}      server Versions from the server.
		 */
		add: ( result, label = '', server = null ) =>
			setState( ( was ) => {
				const versions =
					server && server.length
						? server
						: [ ...was.versions, { data: result, label } ];
				return { versions, index: versions.length - 1 };
			} ),
		show: ( index ) => setState( ( was ) => ( { ...was, index } ) ),
		reset: () => setState( { versions: [], index: 0 } ),
	};
};

/**
 * A short label of chosen options (for versions kept on the screen).
 *
 * @param {Object} options Options.
 * @return {string} Label.
 */
export const optionsLabel = ( options ) => {
	const parts = [];
	if ( options.tone ) {
		parts.push( options.tone );
	}
	if ( options.length && 'same' !== options.length ) {
		parts.push( options.length );
	}
	if ( options.level ) {
		parts.push( options.level );
	}
	if ( options.technique ) {
		parts.push( options.technique.replace( /_/g, ' ' ) );
	}
	if ( options.language ) {
		parts.push( options.language );
	}
	if ( options.voice ) {
		const voice = voiceOptions().find(
			( item ) => item.value === options.voice
		);
		parts.push( voice ? voice.label : options.voice );
	}
	if ( options.change ) {
		parts.push( `“${ options.change.slice( 0, 40 ) }”` );
	}
	return parts.length
		? parts.join( ', ' )
		: __( 'Same options', 'chatbot' );
};

export default RegenerateControl;
