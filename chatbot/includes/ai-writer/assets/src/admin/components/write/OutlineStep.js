/**
 * Step 3: review the outline. Edit, reorder or remove sections, choose a
 * title, then approve. Writing starts only after approval.
 */
import {
	Button,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../../api';
import ListField from '../ListField';
import { byId, WhyNote } from '../persuasion/PersuasionPicker';
import RegenerateControl, { VersionPager } from '../regenerate/Regenerate';

/**
 * A section's job and its one technique, with the label and "why this
 * works" note (round 3).
 *
 * @param {Object}                               props          Props.
 * @param {Object}                               props.section  Section (job, technique).
 * @param {(key: string, value: string) => void} props.onChange Change one field.
 * @return {Element} Element.
 */
const SectionPlan = ( { section, onChange } ) => {
	const lib = data.persuasion;
	const technique =
		'none' === section.technique
			? lib.none
			: byId( lib.techniques, section.technique );
	return (
		<div className="qcwr-outline__plan">
			<SelectControl
				label={ __( 'Job', 'chatbot' ) }
				value={ section.job || 'explain' }
				options={ Object.keys( lib.jobs ).map( ( id ) => ( {
					value: id,
					label: lib.jobs[ id ],
				} ) ) }
				onChange={ ( value ) => onChange( 'job', value ) }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Technique', 'chatbot' ) }
				value={ section.technique || 'none' }
				options={ [
					{ value: 'none', label: lib.none.label },
					...lib.techniques.map( ( item ) => ( {
						value: item.id,
						label: item.label,
					} ) ),
				] }
				onChange={ ( value ) => onChange( 'technique', value ) }
				__nextHasNoMarginBottom
			/>
			<p className="qcwr-outline__why">
				<WhyNote item={ technique } />
			</p>
		</div>
	);
};

const OutlineStep = ( { jobId, initial, onApproved } ) => {
	const [ outline, setOutline ] = useState( initial );
	// CR-14: earlier outlines (before writing starts).
	const [ outlines, setOutlines ] = useState( { versions: [], index: 0 } );
	const [ regenerating, setRegenerating ] = useState( false );
	const regenerateOutline = ( regenerate ) => {
		setRegenerating( true );
		setError( '' );
		writer( `write/jobs/${ jobId }/outline/regenerate`, {
			method: 'POST',
			data: { regenerate },
		} )
			.then( ( body ) => {
				setOutline( body.outline );
				setOutlines( {
					versions: body.versions.map( ( row ) => ( {
						data: row.outline,
						label: row.label,
					} ) ),
					index: body.versions.length - 1,
				} );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setRegenerating( false ) );
	};
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const set = ( key, value ) => setOutline( { ...outline, [ key ]: value } );
	const setSection = ( index, key, value ) =>
		set(
			'sections',
			outline.sections.map( ( section, i ) =>
				i === index ? { ...section, [ key ]: value } : section
			)
		);
	const move = ( index, delta ) => {
		const sections = [ ...outline.sections ];
		const [ item ] = sections.splice( index, 1 );
		sections.splice( index + delta, 0, item );
		set( 'sections', sections );
	};
	const remove = ( index ) =>
		set(
			'sections',
			outline.sections.filter( ( unused, i ) => i !== index )
		);
	const add = () =>
		set( 'sections', [
			...outline.sections,
			{
				heading: '',
				purpose: '',
				words: 200,
				image: null,
				job: 'explain',
				technique: 'none',
			},
		] );

	const total = outline.sections.reduce(
		( sum, section ) => sum + Number( section.words || 0 ),
		0
	);
	const titleOptions = [
		...new Set( [ outline.title, ...outline.titles ].filter( Boolean ) ),
	];

	const approve = () => {
		setBusy( true );
		setError( '' );
		writer( `write/jobs/${ jobId }/outline`, {
			method: 'POST',
			data: { outline },
		} )
			.then( onApproved )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-card qcwr-outline">
			<h2>{ __( 'Check the outline', 'chatbot' ) }</h2>
			<div className="qcwr-actions">
				<RegenerateControl
					kind="outline"
					busy={ regenerating }
					label={ __( 'Regenerate outline', 'chatbot' ) }
					onRegenerate={ regenerateOutline }
				/>
				<VersionPager
					versions={ outlines.versions }
					index={ outlines.index }
					onChange={ ( index ) => {
						setOutlines( { ...outlines, index } );
						setOutline( outlines.versions[ index ].data );
					} }
				/>
			</div>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<RadioControl
				label={ __( 'Title', 'chatbot' ) }
				selected={ outline.title }
				options={ titleOptions.map( ( title ) => ( {
					label: title,
					value: title,
				} ) ) }
				onChange={ ( value ) => set( 'title', value ) }
			/>
			<TextControl
				label={ __( 'Or write your own title', 'chatbot' ) }
				value={ outline.title }
				onChange={ ( value ) => set( 'title', value ) }
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'Angle', 'chatbot' ) }
				value={ outline.angle }
				onChange={ ( value ) => set( 'angle', value ) }
				rows={ 2 }
				__nextHasNoMarginBottom
			/>

			<h3>
				{ sprintf(
					/* translators: %d: total words. */
					__( 'Sections (about %d words)', 'chatbot' ),
					total
				) }
			</h3>
			<ol className="qcwr-outline__sections">
				{ outline.sections.map( ( section, index ) => (
					<li key={ index } className="qcwr-outline__section">
						<TextControl
							label={ __( 'Heading', 'chatbot' ) }
							value={ section.heading }
							onChange={ ( value ) =>
								setSection( index, 'heading', value )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __(
								'What this section does',
								'chatbot'
							) }
							value={ section.purpose }
							onChange={ ( value ) =>
								setSection( index, 'purpose', value )
							}
							__nextHasNoMarginBottom
						/>
						{ data.persuasion && (
							<SectionPlan
								section={ section }
								onChange={ ( key, value ) =>
									setSection( index, key, value )
								}
							/>
						) }
						<div className="qcwr-outline__row">
							<TextControl
								type="number"
								label={ __( 'Words', 'chatbot' ) }
								value={ section.words }
								onChange={ ( value ) =>
									setSection(
										index,
										'words',
										Number( value ) || 0
									)
								}
								__nextHasNoMarginBottom
							/>
							{ section.image && (
								<span className="qcwr-muted">
									{ sprintf(
										/* translators: %s: image description. */
										__( 'Image: %s', 'chatbot' ),
										section.image.alt || section.image.query
									) }
								</span>
							) }
							<div className="qcwr-outline__buttons">
								<Button
									size="small"
									variant="tertiary"
									disabled={ 0 === index }
									onClick={ () => move( index, -1 ) }
								>
									{ __( 'Up', 'chatbot' ) }
								</Button>
								<Button
									size="small"
									variant="tertiary"
									disabled={
										index === outline.sections.length - 1
									}
									onClick={ () => move( index, 1 ) }
								>
									{ __( 'Down', 'chatbot' ) }
								</Button>
								<Button
									size="small"
									variant="tertiary"
									isDestructive
									onClick={ () => remove( index ) }
								>
									{ __( 'Remove', 'chatbot' ) }
								</Button>
							</div>
						</div>
					</li>
				) ) }
			</ol>
			<Button variant="secondary" onClick={ add }>
				{ __( 'Add a section', 'chatbot' ) }
			</Button>

			<h3>{ __( 'Frequently asked questions', 'chatbot' ) }</h3>
			<ListField
				label={ __( 'Questions', 'chatbot' ) }
				hideLabelFromVision
				help={ __(
					'One question per line. Leave empty for none.',
					'chatbot'
				) }
				separator={ '\n' }
				list={ outline.faq }
				onChange={ ( value ) => set( 'faq', value ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Start with a short summary', 'chatbot' ) }
				checked={ !! outline.summary }
				onChange={ ( value ) => set( 'summary', value ) }
				__nextHasNoMarginBottom
			/>
			{ outline.missing_facts.length > 0 && (
				<Notice status="info" isDismissible={ false }>
					<strong>
						{ __(
							'Facts that would make this better:',
							'chatbot'
						) }
					</strong>
					<ul>
						{ outline.missing_facts.map( ( fact ) => (
							<li key={ fact }>{ fact }</li>
						) ) }
					</ul>
					{ __(
						'The draft will mark these with [placeholders] for you to fill in.',
						'chatbot'
					) }
				</Notice>
			) }
			<div className="qcwr-actions">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! outline.sections.length }
					onClick={ approve }
				>
					{ __( 'Write it', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default OutlineStep;
