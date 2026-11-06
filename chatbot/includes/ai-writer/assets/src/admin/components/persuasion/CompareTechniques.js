/**
 * Compare techniques (round 3, CR-13 P3): the same copy written with two
 * to four techniques side by side. Each version shows its label and "why
 * this works", reader-stage fit, length, reading level, the proof it used
 * from the Persuasion Kit and honesty flags. A cost estimate comes first;
 * nothing is generated or used without the owner's click.
 *
 * With `lab` set (headlines only) it is the headline lab: labelled
 * variations per technique (specific, curiosity, how-to, gain, loss).
 */
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import RegenerateControl from '../regenerate/Regenerate';
import HonestyFlags from './HonestyFlags';
import { WhyNote } from './PersuasionPicker';
import { ProSpots } from '../pro/ProPreview';
import './style.scss';

const LETTERS = [ 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J' ];

/**
 * Techniques that can be compared for a kind of copy (frameworks only
 * shape longer, multi-part copy).
 *
 * @param {Object} lib  Persuasion app data.
 * @param {Object} kind Kind (parts).
 * @return {Object[]} Techniques.
 */
const choicesFor = ( lib, kind ) => [
	...( kind && kind.parts ? lib.frameworks || [] : [] ),
	...( lib.angles || [] ),
	...( lib.techniques || [] ),
];

/**
 * One version card.
 *
 * @param {Object}                    props              Props.
 * @param {Object}                    props.version      Version.
 * @param {string}                    props.letter       Letter shown (A, B…).
 * @param {() => void}                props.onUse        Use this version.
 * @param {boolean}                   props.busy         A request is running.
 * @param {(options: Object) => void} props.onRegenerate Write this version again.
 * @return {Element} Card.
 */
const VersionCard = ( { version, letter, onUse, busy, onRegenerate } ) => (
	<div className="qcwr-compare__card">
		<div className="qcwr-compare__head">
			<span className="qcwr-compare__letter">{ letter }</span>
			<WhyNote item={ version.technique } />
		</div>
		<p className="qcwr-compare__text">{ version.text }</p>
		<ul className="qcwr-compare__facts">
			{ version.fit && version.fit.label && (
				<li className={ `is-fit-${ version.fit.level }` }>
					{ version.fit.label }
				</li>
			) }
			<li className={ version.length.over ? 'is-over' : undefined }>
				{ version.length.limit
					? sprintf(
							/* translators: 1: characters, 2: limit. */
							__( '%1$d of %2$d characters', 'chatbot' ),
							version.length.chars,
							version.length.limit
						)
					: sprintf(
							/* translators: %d: words. */
							_n(
								'%d word',
								'%d words',
								version.length.words,
								'chatbot'
							),
							version.length.words
						) }
			</li>
			<li>
				{ sprintf(
					/* translators: %s: reading level. */
					__( 'Reading level: %s', 'chatbot' ),
					version.reading.label
				) }
			</li>
			<li>
				{ version.proof.length
					? sprintf(
							/* translators: %s: kit entries. */
							__( 'Proof used: %s', 'chatbot' ),
							version.proof
								.map( ( item ) => item.label )
								.join( '; ' )
						)
					: __( 'No proof from your kit', 'chatbot' ) }
			</li>
		</ul>
		<HonestyFlags flags={ version.honesty } />
		<div className="qcwr-compare__card-actions">
			<Button
				variant="primary"
				disabled={ busy }
				onClick={ onUse }
				className="qcwr-compare__use"
			>
				{ __( 'Use this', 'chatbot' ) }
			</Button>
			{ onRegenerate && (
				<RegenerateControl
					kind="variations"
					busy={ busy }
					hide={ [ 'technique' ] }
					onRegenerate={ onRegenerate }
				/>
			) }
		</div>
	</div>
);

/**
 * Mixing parts (Pro): pick the opening, middle and close from any version.
 *
 * @param {Object}                  props          Props.
 * @param {Object[]}                props.versions Versions.
 * @param {(parts: Object) => void} props.onMix    Use the mix (role => index).
 * @param {boolean}                 props.busy     A request is running.
 * @return {Element} Element.
 */
const Mixer = ( { versions, onMix, busy } ) => {
	const roles = ( data.persuasion.compare || {} ).roles || {};
	const [ parts, setParts ] = useState( {
		opening: 0,
		middle: 0,
		close: 0,
	} );
	return (
		<div className="qcwr-compare__mix">
			<h4>{ __( 'Mix parts', 'chatbot' ) }</h4>
			<div className="qcwr-compare__mix-row">
				{ Object.keys( roles ).map( ( role ) => (
					<SelectControl
						key={ role }
						label={ roles[ role ] }
						value={ String( parts[ role ] ) }
						options={ versions.map( ( version, index ) => ( {
							value: String( index ),
							label: `${ LETTERS[ index ] }: ${ version.technique.label }`,
						} ) ) }
						onChange={ ( value ) =>
							setParts( { ...parts, [ role ]: Number( value ) } )
						}
						__nextHasNoMarginBottom
					/>
				) ) }
			</div>
			<Button
				variant="secondary"
				disabled={ busy }
				onClick={ () => onMix( parts ) }
			>
				{ __( 'Use the mix', 'chatbot' ) }
			</Button>
		</div>
	);
};

/**
 * Compare techniques.
 *
 * @param {Object}                                       props             Props.
 * @param {string}                                       props.kind        headline, intro, section, cta, product_description, email_subject, meta_title or social_post.
 * @param {Object}                                       props.input       source, title, content, post_id, product_id, network, stage.
 * @param {(answer: Object) => void}                     props.onUse       Called with the applied result (text, html, techniques, honesty).
 * @param {() => void}                                   props.onClose     Close (optional).
 * @param {boolean}                                      props.lab         Headline lab.
 * @param {() => Object}                                 props.getInput    Read the input at click time (optional; overrides `input`).
 * @param {(result: Object, applied: Object) => Element} props.renderAfter Extra actions per result (P4 adds "Send to a test").
 * @return {Element|null} Element.
 */
const CompareTechniques = ( {
	kind,
	input = {},
	onUse,
	onClose,
	lab = false,
	getInput,
	renderAfter,
} ) => {
	const lib = data.persuasion;
	const compare = lib && lib.compare;
	const [ picked, setPicked ] = useState( () =>
		lab && compare ? compare.lab.map( ( item ) => item.id ) : []
	);
	const [ estimate, setEstimate ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ applied, setApplied ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	if ( ! compare || ! compare.kinds[ kind ] ) {
		return null;
	}
	const kindInfo = compare.kinds[ kind ];
	const max = lab ? 6 : compare.max;
	const options = lab
		? [ ...compare.lab, lib.none ]
		: [ lib.none, ...choicesFor( lib, kindInfo ) ];
	const body = ( suggest ) => ( {
		kind,
		...( getInput ? getInput() : input ),
		techniques: suggest ? 'suggest' : picked,
		lab,
	} );

	const toggle = ( id ) => {
		setEstimate( null );
		if ( picked.includes( id ) ) {
			setPicked( picked.filter( ( item ) => item !== id ) );
		} else if ( picked.length < max ) {
			setPicked( [ ...picked, id ] );
		}
	};

	const ask = ( suggest = false ) => {
		setBusy( 'estimate' );
		setError( '' );
		setResult( null );
		setApplied( null );
		writer( 'persuasion/compare', {
			method: 'POST',
			data: { ...body( suggest ), step: 'estimate' },
		} )
			.then( ( answer ) => {
				setEstimate( answer );
				setPicked( answer.techniques.map( ( item ) => item.id ) );
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const generate = () => {
		setBusy( 'generate' );
		setError( '' );
		writer( 'persuasion/compare', {
			method: 'POST',
			data: { ...body( false ), step: 'generate' },
		} )
			.then( ( answer ) => {
				setResult( answer );
				setEstimate( null );
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const use = ( pick ) => {
		setBusy( 'apply' );
		setError( '' );
		writer( 'persuasion/apply', {
			method: 'POST',
			data: { id: result.id, ...pick },
		} )
			.then( ( answer ) => {
				setApplied( answer );
				if ( onUse ) {
					onUse( answer );
				}
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	// CR-14: write one version again, keeping its technique.
	const regenerateOne = ( index, chosen ) => {
		setBusy( 'regenerate' );
		setError( '' );
		writer( 'persuasion/compare/regenerate', {
			method: 'POST',
			data: { id: result.id, version: index, regenerate: chosen },
		} )
			.then( ( answer ) =>
				setResult( {
					...result,
					versions: result.versions.map( ( version, i ) =>
						i === index ? answer.version : version
					),
				} )
			)
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( '' ) );
	};

	const groups = [];
	if ( result && result.lab ) {
		result.versions.forEach( ( version, index ) => {
			const id = version.technique ? version.technique.id : '';
			let group = groups.find( ( item ) => item.id === id );
			if ( ! group ) {
				group = { id, technique: version.technique, items: [] };
				groups.push( group );
			}
			group.items.push( { version, index } );
		} );
	}

	return (
		<div
			className={ `qcwr-compare${ lab ? ' qcwr-compare--lab' : '' }` }
			data-kind={ kind }
		>
			<div className="qcwr-compare__bar">
				<strong>
					{ lab
						? __( 'Headline lab', 'chatbot' )
						: sprintf(
								/* translators: %s: kind of copy. */
								__(
									'Compare techniques: %s',
									'chatbot'
								),
								kindInfo.label
							) }
				</strong>
				{ onClose && (
					<Button variant="link" onClick={ onClose }>
						{ __( 'Close', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcwr-compare__hint">
				{ lab
					? __(
							'Labelled headline variations for each technique. Untick the ones you do not want.',
							'chatbot'
						)
					: sprintf(
							/* translators: %d: most versions. */
							__(
								'Choose two to %d techniques, or let AI Writer suggest the best for this reader.',
								'chatbot'
							),
							max
						) }
			</p>
			<div className="qcwr-compare__chips">
				{ options.filter( Boolean ).map( ( item ) => (
					<Button
						key={ item.id }
						className="qcwr-compare__chip"
						variant={
							picked.includes( item.id ) ? 'primary' : 'secondary'
						}
						isPressed={ picked.includes( item.id ) }
						title={ [ item.why, item.method ]
							.filter( Boolean )
							.join( ' — ' ) }
						onClick={ () => toggle( item.id ) }
						size="compact"
					>
						{ item.label }
					</Button>
				) ) }
			</div>
			{ ! lab && compare.max < 4 && (
				<ProSpots
					spots={ [
						'compare.mix',
						'compare.ab_tests',
						'compare.subject_tests',
					] }
				/>
			) }
			<div className="qcwr-actions">
				{ ! lab && (
					<Button
						variant="secondary"
						disabled={ !! busy }
						onClick={ () => ask( true ) }
					>
						{ __( 'Suggest the best', 'chatbot' ) }
					</Button>
				) }
				<Button
					variant="secondary"
					disabled={ !! busy || picked.length < ( lab ? 1 : 2 ) }
					onClick={ () => ask( false ) }
				>
					{ __( 'Show the cost', 'chatbot' ) }
				</Button>
				{ busy && <Spinner /> }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ estimate && (
				<div className="qcwr-compare__estimate">
					{ estimate.skipped && estimate.skipped.length > 0 && (
						<Notice status="info" isDismissible={ false }>
							{ sprintf(
								/* translators: %s: technique names. */
								__(
									'Left out because they need real data first: %s.',
									'chatbot'
								),
								estimate.skipped
									.map( ( item ) => item.label )
									.join( ', ' )
							) }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: 1: versions, 2: cost in US dollars. */
							__(
								'%1$d versions in one AI call, about $%2$s.',
								'chatbot'
							),
							estimate.estimate.versions,
							Number( estimate.estimate.usd ).toFixed( 3 )
						) }
					</p>
					<Button
						variant="primary"
						disabled={ !! busy }
						onClick={ generate }
					>
						{ __( 'Write the versions', 'chatbot' ) }
					</Button>
				</div>
			) }
			{ result && result.stage && (
				<p className="qcwr-compare__stage">
					{ sprintf(
						/* translators: %s: reader stage. */
						__( 'Reader stage: %s', 'chatbot' ),
						result.stage.label
					) }
				</p>
			) }
			{ result && ! result.lab && (
				<div
					className={ `qcwr-compare__grid is-${ result.versions.length }` }
				>
					{ result.versions.map( ( version, index ) => (
						<VersionCard
							key={ index }
							version={ version }
							letter={ LETTERS[ index ] }
							busy={ !! busy }
							onUse={ () => use( { version: index } ) }
							onRegenerate={ ( chosen ) =>
								regenerateOne( index, chosen )
							}
						/>
					) ) }
				</div>
			) }
			{ result && result.lab && (
				<div className="qcwr-compare__lab">
					{ groups.map( ( group ) => (
						<div className="qcwr-compare__group" key={ group.id }>
							<WhyNote item={ group.technique } />
							<ul className="qcwr-choices">
								{ group.items.map( ( { version, index } ) => (
									<li key={ index }>
										<Button
											variant="link"
											disabled={ !! busy }
											onClick={ () =>
												use( { version: index } )
											}
										>
											{ version.text }
										</Button>{ ' ' }
										<span
											className={
												version.length.over
													? 'qcwr-compare__len is-over'
													: 'qcwr-compare__len'
											}
										>
											{ version.length.chars }
										</span>
										<HonestyFlags
											flags={ version.honesty }
										/>
									</li>
								) ) }
							</ul>
						</div>
					) ) }
				</div>
			) }
			{ result && result.mix && result.versions.length > 1 && (
				<Mixer
					versions={ result.versions }
					busy={ !! busy }
					onMix={ ( parts ) => use( { parts } ) }
				/>
			) }
			{ applied && (
				<Notice status="success" isDismissible={ false }>
					{ applied.mixed
						? __( 'The mix was used.', 'chatbot' )
						: __( 'The version was used.', 'chatbot' ) }
				</Notice>
			) }
			{ applied && <HonestyFlags flags={ applied.honesty } /> }
			{ result && renderAfter && renderAfter( result, applied ) }
			{ result &&
				applyFilters( 'qcld_wpbot_writer.compare.after', null, {
					result,
					applied,
					kind,
					input: getInput ? getInput() : input,
				} ) }
		</div>
	);
};

export default CompareTechniques;
