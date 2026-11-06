/**
 * Basic style guide: words to use and avoid, product names, reading level.
 * Applied to every prompt and to the editor's checklist.
 */
import {
	Button,
	Notice,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';

const LISTS = () => [
	[
		'style_use_words',
		__( 'Words to use (one per line)', 'chatbot' ),
	],
	[
		'style_avoid_words',
		__( 'Words to avoid (one per line)', 'chatbot' ),
	],
	[
		'style_product_names',
		__(
			'Product and brand names, written exactly (one per line)',
			'chatbot'
		),
	],
];

const StyleGuideSettings = () => {
	const [ values, setValues ] = useState( () => {
		const start = { reading_level: data.settings.reading_level || 'any' };
		LISTS().forEach(
			( [ key ] ) =>
				( start[ key ] = ( data.settings[ key ] || [] ).join( '\n' ) )
		);
		return start;
	} );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const save = () => {
		setBusy( true );
		writer( 'settings', { method: 'POST', data: { settings: values } } )
			.then( ( next ) => {
				data.settings = next;
				setMessage( {
					status: 'success',
					text: __( 'Style guide saved.', 'chatbot' ),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-card qcwr-style-guide">
			<h2>{ __( 'Style guide', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'Everything AI Writer writes follows it, and the editor checklist points out sentences that do not.',
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
			<div className="qcwr-grid">
				{ LISTS().map( ( [ key, label ] ) => (
					<TextareaControl
						key={ key }
						label={ label }
						rows={ 4 }
						value={ values[ key ] }
						onChange={ ( value ) =>
							setValues( { ...values, [ key ]: value } )
						}
						__nextHasNoMarginBottom
					/>
				) ) }
				<SelectControl
					label={ __( 'Reading level', 'chatbot' ) }
					value={ values.reading_level }
					options={ [
						{
							value: 'any',
							label: __( 'No preference', 'chatbot' ),
						},
						{
							value: 'simple',
							label: __(
								'Simple: short sentences, everyday words',
								'chatbot'
							),
						},
						{
							value: 'standard',
							label: __(
								'Standard: plain words, terms explained',
								'chatbot'
							),
						},
						{
							value: 'advanced',
							label: __(
								'Advanced: expert readers',
								'chatbot'
							),
						},
					] }
					onChange={ ( value ) =>
						setValues( { ...values, reading_level: value } )
					}
					help={ __(
						'US or UK spelling is set under Writing defaults.',
						'chatbot'
					) }
					__nextHasNoMarginBottom
				/>
			</div>
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy }
				onClick={ save }
			>
				{ __( 'Save style guide', 'chatbot' ) }
			</Button>
		</div>
	);
};

export default StyleGuideSettings;
