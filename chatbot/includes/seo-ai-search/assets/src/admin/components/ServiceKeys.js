/**
 * Optional keys: PageSpeed Insights and Bing Webmaster Tools. Saved
 * encrypted; only the last characters are ever shown.
 */
import { Button, ExternalLink, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage, seo } from '../api';

const SERVICES = () => [
	{
		id: 'pagespeed',
		label: __(
			'PageSpeed Insights API key (optional)',
			'chatbot'
		),
		help: __(
			'Speed checks work without a key at a low daily limit; a free key raises it to 25,000 checks a day.',
			'chatbot'
		),
		link: 'https://developers.google.com/speed/docs/insights/v5/get-started',
	},
	{
		id: 'bing',
		label: __(
			'Bing Webmaster Tools API key (optional)',
			'chatbot'
		),
		help: __(
			'Adds inbound link counts to Links. Verify your site in Bing Webmaster Tools, then copy the key from Settings > API access.',
			'chatbot'
		),
		link: 'https://www.bing.com/webmasters/',
	},
];

const ServiceKeys = () => {
	const [ view, setView ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		seo( 'secrets' )
			.then( setView )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	const save = ( extra = {} ) => {
		setBusy( true );
		setError( '' );
		seo( 'secrets', { method: 'POST', data: { ...values, ...extra } } )
			.then( ( next ) => {
				setView( next );
				setValues( {} );
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! view ) {
		return error ? <p className="qcseo-error">{ error }</p> : null;
	}
	return (
		<div className="qcseo-card">
			<h3>{ __( 'Other services', 'chatbot' ) }</h3>
			{ SERVICES().map( ( service ) => (
				<div key={ service.id } className="qcseo-key-row">
					<TextControl
						__next40pxDefaultSize
						label={ service.label }
						type="password"
						autoComplete="off"
						value={ values[ service.id ] || '' }
						placeholder={
							view[ service.id ].set
								? view[ service.id ].hint
								: ''
						}
						onChange={ ( value ) =>
							setValues( { ...values, [ service.id ]: value } )
						}
						__nextHasNoMarginBottom
					/>
					<p className="qcseo-muted">
						{ service.help }{ ' ' }
						<ExternalLink href={ service.link }>
							{ __( 'Get a key', 'chatbot' ) }
						</ExternalLink>
					</p>
					{ view[ service.id ].set && (
						<Button
							variant="link"
							isDestructive
							onClick={ () =>
								save( { [ `clear_${ service.id }` ]: true } )
							}
						>
							{ __( 'Remove key', 'chatbot' ) }
						</Button>
					) }
				</div>
			) ) }
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy || 0 === Object.keys( values ).length }
				onClick={ () => save() }
			>
				{ __( 'Save keys', 'chatbot' ) }
			</Button>
			{ error && <p className="qcseo-error">{ error }</p> }
		</div>
	);
};

export default ServiceKeys;
