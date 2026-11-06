/**
 * How a piece persuades (round 3, CR-13): reader stage, framework, angle,
 * or "No specific technique". Detected automatically for each piece (from
 * search intent, the idea's source or the kind of piece) and changeable in
 * one click. Every choice shows its plain label and a "why this works"
 * note; the method name is only in a tooltip.
 */
import { Button, CheckboxControl, SelectControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, writer } from '../../api';
import './style.scss';

const SOURCES = () => ( {
	intent: __( 'detected from the search intent', 'chatbot' ),
	vault: __( 'detected from the Keyword Vault', 'chatbot' ),
	idea: __( 'detected from where the idea came from', 'chatbot' ),
	type: __( 'detected from the kind of piece', 'chatbot' ),
	owner: __( 'chosen by you', 'chatbot' ),
} );

/**
 * A label with its "why this works" note; the method name in a tooltip.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item id, label, why, method.
 * @return {Element|null} Element.
 */
export const WhyNote = ( { item } ) =>
	item ? (
		<span className="qcwr-why" title={ item.method || undefined }>
			<strong>{ item.label }</strong>
			{ item.why && (
				<span className="qcwr-why__text"> — { item.why }</span>
			) }
		</span>
	) : null;

/**
 * Find an entry in a list by id.
 *
 * @param {Object[]} list List.
 * @param {string}   id   Id.
 * @return {Object|undefined} Entry.
 */
export const byId = ( list, id ) =>
	( list || [] ).find( ( item ) => item.id === id );

/**
 * The picker.
 *
 * @param {Object}                    props          Props.
 * @param {Object}                    props.value    Choices (stage, stage_source, framework, angle, none, techniques, owner).
 * @param {(choices: Object) => void} props.onChange New choices.
 * @param {string}                    props.type     Content type or Quick write template.
 * @param {Object}                    props.signal   intent, idea_id, vault_id.
 * @param {string}                    props.title    Heading (optional).
 * @return {Element|null} Element.
 */
const PersuasionPicker = ( {
	value,
	onChange,
	type,
	signal = {},
	title = __( 'How it persuades', 'chatbot' ),
} ) => {
	const lib = data.persuasion;
	const choices = value || {};
	const [ extra, setExtra ] = useState( false );
	const latest = useRef( choices );
	latest.current = choices;
	const key = JSON.stringify( [
		type,
		signal.intent || '',
		signal.idea_id || 0,
		signal.vault_id || 0,
	] );

	// Detect for this piece unless the owner chose.
	useEffect( () => {
		if ( ! lib || latest.current.owner ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			writer( 'write/persuasion', {
				method: 'POST',
				data: {
					type,
					persuasion: { ...latest.current, stage: '', framework: '' },
					intent: signal.intent || '',
					idea_id: signal.idea_id || 0,
					vault_id: signal.vault_id || 0,
				},
			} )
				.then( ( body ) => {
					if ( ! latest.current.owner ) {
						onChange( body.choices );
					}
				} )
				.catch( () => {} );
		}, 300 );
		return () => clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	if ( ! lib ) {
		return null;
	}
	const set = ( changes ) =>
		onChange( { ...choices, ...changes, owner: true } );
	const stage = byId( lib.stages, choices.stage );
	const framework = byId( lib.frameworks, choices.framework );
	const angle = byId( lib.angles, choices.angle );
	const techniques = ( choices.techniques || [] )
		.map( ( id ) => byId( lib.techniques, id ) )
		.filter( Boolean );

	return (
		<div className="qcwr-persuasion">
			<h4>{ title }</h4>
			<CheckboxControl
				label={ lib.none.label }
				help={ lib.none.why }
				checked={ !! choices.none }
				onChange={ ( none ) =>
					set(
						none
							? {
									none: true,
									framework: '',
									angle: '',
									techniques: [],
								}
							: { none: false }
					)
				}
				__nextHasNoMarginBottom
			/>
			<div className="qcwr-persuasion__row">
				<SelectControl
					label={ __( 'Reader stage', 'chatbot' ) }
					value={ choices.stage || '' }
					options={ lib.stages.map( ( item ) => ( {
						value: item.id,
						label: item.label,
					} ) ) }
					onChange={ ( id ) =>
						set( { stage: id, stage_source: 'owner' } )
					}
					help={
						choices.stage_source &&
						SOURCES()[ choices.stage_source ]
							? sprintf(
									/* translators: %s: where the stage came from. */
									__( '(%s)', 'chatbot' ),
									SOURCES()[ choices.stage_source ]
								)
							: ''
					}
					__nextHasNoMarginBottom
				/>
				{ ! choices.none && (
					<>
						<SelectControl
							label={ __( 'Framework', 'chatbot' ) }
							value={ choices.framework || '' }
							options={ [
								{
									value: '',
									label: __(
										'No framework',
										'chatbot'
									),
								},
								...lib.frameworks.map( ( item ) => ( {
									value: item.id,
									label: item.label,
								} ) ),
							] }
							onChange={ ( id ) => set( { framework: id } ) }
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Main angle', 'chatbot' ) }
							value={ choices.angle || '' }
							options={ [
								{
									value: '',
									label: __( 'Automatic', 'chatbot' ),
								},
								...lib.angles.map( ( item ) => ( {
									value: item.id,
									label: item.label,
								} ) ),
							] }
							onChange={ ( id ) => set( { angle: id } ) }
							__nextHasNoMarginBottom
						/>
					</>
				) }
			</div>
			{ ! choices.none && (
				<Button
					variant="link"
					aria-expanded={ extra }
					onClick={ () => setExtra( ! extra ) }
				>
					{ sprintf(
						/* translators: %d: number of extra techniques chosen. */
						__( 'Extra techniques (%d chosen)', 'chatbot' ),
						( choices.techniques || [] ).length
					) }
				</Button>
			) }
			{ ! choices.none && extra && (
				<fieldset className="qcwr-persuasion__extra">
					<legend>
						{ __(
							'Extra techniques (up to 4)',
							'chatbot'
						) }
					</legend>
					{ lib.techniques
						.filter(
							( item ) =>
								! item.fits ||
								! item.fits.types.length ||
								item.fits.types.includes( type )
						)
						.map( ( item ) => {
							const on = ( choices.techniques || [] ).includes(
								item.id
							);
							return (
								<CheckboxControl
									key={ item.id }
									label={ item.label }
									help={ item.why }
									checked={ on }
									disabled={
										! on &&
										( choices.techniques || [] ).length >= 4
									}
									onChange={ ( checked ) =>
										set( {
											techniques: checked
												? [
														...( choices.techniques ||
															[] ),
														item.id,
													]
												: (
														choices.techniques || []
													).filter(
														( id ) => id !== item.id
													),
										} )
									}
									__nextHasNoMarginBottom
								/>
							);
						} ) }
				</fieldset>
			) }
			<ul className="qcwr-persuasion__why">
				{ stage && (
					<li>
						<WhyNote item={ stage } />
					</li>
				) }
				{ choices.none && (
					<li>
						<WhyNote item={ lib.none } />
					</li>
				) }
				{ framework && (
					<li>
						<WhyNote item={ framework } />
					</li>
				) }
				{ angle && (
					<li>
						<WhyNote item={ angle } />
					</li>
				) }
				{ ! choices.none &&
					techniques.map( ( item ) => (
						<li key={ item.id }>
							<WhyNote item={ item } />
						</li>
					) ) }
			</ul>
		</div>
	);
};

export default PersuasionPicker;
