/**
 * Writing defaults and per-step model choices.
 */
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';

const WritingSettings = () => {
	const [ settings, setSettings ] = useState( data.settings );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const set = ( key, value ) =>
		setSettings( { ...settings, [ key ]: value } );
	const setLength = ( preset, value ) =>
		set( 'lengths', {
			...settings.lengths,
			[ preset ]: Number( value ) || 0,
		} );
	const setStep = ( mode, field, value ) =>
		set( 'step_models', {
			...settings.step_models,
			[ mode ]: { ...settings.step_models[ mode ], [ field ]: value },
		} );

	const save = () => {
		setBusy( true );
		writer( 'settings', { method: 'POST', data: { settings } } )
			.then( ( next ) => {
				setSettings( next );
				data.settings = next;
				setMessage( {
					status: 'success',
					text: __( 'Settings saved.', 'chatbot' ),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcwr-card">
			<h2>{ __( 'Writing defaults', 'chatbot' ) }</h2>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<div className="qcwr-grid">
				<SelectControl
					label={ __( 'Default content type', 'chatbot' ) }
					value={ settings.default_type }
					options={ [
						{
							label: __( 'Blog post', 'chatbot' ),
							value: 'blog_post',
						},
						{
							label: __( 'Help article', 'chatbot' ),
							value: 'help_article',
						},
						{ label: __( 'FAQ', 'chatbot' ), value: 'faq' },
						{
							label: __( 'Page copy', 'chatbot' ),
							value: 'page_copy',
						},
						{
							label: __( 'Product', 'chatbot' ),
							value: 'product',
						},
					] }
					onChange={ ( value ) => set( 'default_type', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Default length', 'chatbot' ) }
					value={ settings.default_length }
					options={ [
						{
							label: __( 'Short', 'chatbot' ),
							value: 'short',
						},
						{
							label: __( 'Standard', 'chatbot' ),
							value: 'standard',
						},
						{
							label: __( 'Long', 'chatbot' ),
							value: 'long',
						},
					] }
					onChange={ ( value ) => set( 'default_length', value ) }
					__nextHasNoMarginBottom
				/>
				{ [ 'short', 'standard', 'long' ].map( ( preset ) => (
					<TextControl
						key={ preset }
						type="number"
						min={ 100 }
						max={ 10000 }
						label={
							{
								short: __( 'Short (words)', 'chatbot' ),
								standard: __(
									'Standard (words)',
									'chatbot'
								),
								long: __( 'Long (words)', 'chatbot' ),
							}[ preset ]
						}
						value={ settings.lengths[ preset ] }
						onChange={ ( value ) => setLength( preset, value ) }
						__nextHasNoMarginBottom
					/>
				) ) }
				<SelectControl
					label={ __( 'Spelling', 'chatbot' ) }
					value={ settings.spelling }
					options={ [
						{
							label: __(
								'From the site language',
								'chatbot'
							),
							value: 'auto',
						},
						{
							label: __( 'US English', 'chatbot' ),
							value: 'us',
						},
						{
							label: __( 'UK English', 'chatbot' ),
							value: 'uk',
						},
					] }
					onChange={ ( value ) => set( 'spelling', value ) }
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="qcwr-toggles">
				<ToggleControl
					label={ __(
						'Ask a few questions before writing ("Make it yours")',
						'chatbot'
					) }
					checked={ !! settings.ask_questions }
					onChange={ ( value ) => set( 'ask_questions', value ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __(
						'Show a cost estimate before each job',
						'chatbot'
					) }
					checked={ !! settings.show_estimates }
					onChange={ ( value ) => set( 'show_estimates', value ) }
					__nextHasNoMarginBottom
				/>
			</div>
			<h3>{ __( 'Article formats', 'chatbot' ) }</h3>
			<ToggleControl
				label={ __(
					'Suggest a format for each topic',
					'chatbot'
				) }
				help={ __(
					'AI Writer picks a format (listicle, comparison, checklist and so on) from the topic and says why. You can always choose another one. No AI call, no cost.',
					'chatbot'
				) }
				checked={ false !== settings.format_suggest }
				onChange={ ( value ) => set( 'format_suggest', value ) }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __(
					'Offer downloads on published articles',
					'chatbot'
				) }
				help={ __(
					'For formats that offer them (for example Checklists: PDF and Print). Shown in a small box after the article.',
					'chatbot'
				) }
				checked={ false !== settings.format_downloads }
				onChange={ ( value ) => set( 'format_downloads', value ) }
				__nextHasNoMarginBottom
			/>
			{ data.downloadGate && (
				<ToggleControl
					label={ __(
						'Ask for an email before a download',
						'chatbot'
					) }
					help={ __(
						'Uses your forms or marketing plugin.',
						'chatbot'
					) }
					checked={ !! settings.download_gate }
					onChange={ ( value ) => set( 'download_gate', value ) }
					__nextHasNoMarginBottom
				/>
			) }
			<div className="qcwr-grid">
				<TextControl
					type="number"
					min={ 5 }
					max={ 1000 }
					label={ __(
						'Original research: smallest group shown',
						'chatbot'
					) }
					help={ __(
						'Groups with fewer rows are hidden, so no one can be picked out and small samples do not mislead (at least 5; default 10).',
						'chatbot'
					) }
					value={ String( settings.research_min_group ?? 10 ) }
					onChange={ ( value ) =>
						set( 'research_min_group', Number( value ) || 10 )
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<h3>{ __( 'Brand & Business', 'chatbot' ) }</h3>
			<div className="qcwr-grid">
				<TextControl
					type="number"
					min={ 0 }
					max={ 100 }
					label={ __(
						'Pages to read per import',
						'chatbot'
					) }
					help={ sprintf(
						/* translators: %d: default number of pages. */
						__(
							'Most pages and web addresses "Fill from your content" reads at once (1 to 100). 0 uses the default (%d). More pages cost more AI credit.',
							'chatbot'
						),
						data.importDefault || 5
					) }
					value={ String( settings.import_max_pages ?? 0 ) }
					onChange={ ( value ) =>
						set(
							'import_max_pages',
							Math.max( 0, Number( value ) || 0 )
						)
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<h3>{ __( 'Regenerate', 'chatbot' ) }</h3>
			<div className="qcwr-grid">
				<TextControl
					type="number"
					min={ 1 }
					max={ 20 }
					label={ __(
						'Versions to keep per piece',
						'chatbot'
					) }
					help={ __(
						'Earlier versions you can flip back to (1 to 20).',
						'chatbot'
					) }
					value={ String( settings.versions_keep ?? 5 ) }
					onChange={ ( value ) =>
						set( 'versions_keep', Number( value ) || 5 )
					}
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="number"
					min={ 0 }
					step={ 0.01 }
					label={ __(
						'Ask before regenerating above (US$)',
						'chatbot'
					) }
					help={ __(
						'Regenerating costs more than this asks first. 0 asks every time. Whole articles and batches always show their estimate.',
						'chatbot'
					) }
					value={ String( settings.regenerate_confirm_usd ?? 0.05 ) }
					onChange={ ( value ) =>
						set( 'regenerate_confirm_usd', Number( value ) )
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<h3>{ __( 'Models per kind of step', 'chatbot' ) }</h3>
			<p className="qcwr-muted">
				{ __(
					'Leave empty to use the main provider and its model. Drafting can use a more creative model; facts, metadata and product data a strict one.',
					'chatbot'
				) }
			</p>
			<div className="qcwr-grid">
				{ [
					[ 'creative', __( 'Drafting', 'chatbot' ) ],
					[
						'strict',
						__(
							'Facts, metadata and product data',
							'chatbot'
						),
					],
				].map( ( [ mode, label ] ) => (
					<div key={ mode } className="qcwr-step-model">
						<strong>{ label }</strong>
						<TextControl
							label={ __(
								'Provider ID (for example openai, claude, gemini)',
								'chatbot'
							) }
							value={ settings.step_models[ mode ].provider }
							onChange={ ( value ) =>
								setStep( mode, 'provider', value )
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Model ID', 'chatbot' ) }
							value={ settings.step_models[ mode ].model }
							onChange={ ( value ) =>
								setStep( mode, 'model', value )
							}
							__nextHasNoMarginBottom
						/>
					</div>
				) ) }
			</div>

			<h3>{ __( 'Data', 'chatbot' ) }</h3>
			<ToggleControl
				label={ __(
					'Delete AI Writer data when the plugin is deleted',
					'chatbot'
				) }
				help={ __(
					'Off by default. Your posts and pages are never deleted.',
					'chatbot'
				) }
				checked={ !! settings.delete_on_uninstall }
				onChange={ ( value ) => set( 'delete_on_uninstall', value ) }
				__nextHasNoMarginBottom
			/>
			<div className="qcwr-actions">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ save }
				>
					{ __( 'Save settings', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default WritingSettings;
