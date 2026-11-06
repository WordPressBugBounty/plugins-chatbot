/**
 * Templates: every Quick Write template with its fields and length limit;
 * "Use this template" opens it in Quick Write. The Pro module adds custom
 * custom templates and bulk variations (qcld_wpbot_writer.templates.panels).
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import { navigate } from '../nav';

const TemplatesScreen = () => {
	const [ templates, setTemplates ] = useState( null );
	const [ error, setError ] = useState( '' );

	const load = useCallback( () => {
		writer( 'quick/templates' )
			.then( ( body ) => setTemplates( body.templates ) )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	}, [] );
	useEffect( load, [ load ] );

	if ( error ) {
		return <Notice status="error">{ error }</Notice>;
	}
	if ( ! templates ) {
		return <Spinner />;
	}
	return (
		<>
			<div className="qcwr-card">
				<h2>{ __( 'Templates', 'chatbot' ) }</h2>
				<p className="qcwr-muted">
					{ __(
						'Short pieces you write in a few clicks with Quick Write. Each template asks for two to four details.',
						'chatbot'
					) }
				</p>
				<ul className="qcwr-templates">
					{ templates.map( ( template ) => (
						<li key={ template.id } className="qcwr-template">
							<strong>{ template.label }</strong>
							<span className="qcwr-muted">
								{ template.fields
									.map( ( field ) => field.label )
									.join( ' · ' ) }
							</span>
							{ template.limit > 0 && (
								<span className="qcwr-muted">
									{ sprintf(
										/* translators: %d: characters. */
										__(
											'Up to %d characters',
											'chatbot'
										),
										template.limit
									) }
								</span>
							) }
							{ data.can.use && (
								<Button
									variant="secondary"
									onClick={ () =>
										navigate( 'quick', {
											template: template.id,
										} )
									}
								>
									{ __(
										'Use this template',
										'chatbot'
									) }
								</Button>
							) }
						</li>
					) ) }
				</ul>
			</div>
			{ applyFilters( 'qcld_wpbot_writer.templates.panels', null, {
				templates,
				reload: load,
			} ) }
		</>
	);
};

export default TemplatesScreen;
