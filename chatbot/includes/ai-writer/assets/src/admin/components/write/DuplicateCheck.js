/**
 * Duplicate check (brief 6.2): another page already targets this search.
 * Three choices: improve that page, write a different angle, or continue.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const REASONS = () => ( {
	map: __( 'targets the same search', 'chatbot' ),
	focus_keyword: __(
		'has the same focus keyword in your SEO plugin',
		'chatbot'
	),
	title: __( 'has nearly the same title', 'chatbot' ),
	vault: __(
		'is assigned this keyword in your Keyword Vault',
		'chatbot'
	),
} );

const DuplicateCheck = ( { matches, onBack, onAngle, onContinue } ) => {
	const first = matches[ 0 ];
	return (
		<div className="qcwr-card qcwr-duplicates">
			<h2>
				{ first.planned && ! first.post_id
					? sprintf(
							/* translators: %s: title of the planned page. */
							__(
								'"%s" is already planned for this',
								'chatbot'
							),
							first.title
						)
					: sprintf(
							/* translators: %s: title of the existing page. */
							__(
								'Your page "%s" already targets this',
								'chatbot'
							),
							first.title
						) }
			</h2>
			<ul className="qcwr-duplicates__list">
				{ matches.map( ( match ) => (
					<li key={ `${ match.post_id }-${ match.title }` }>
						{ match.edit_url ? (
							<a href={ match.edit_url }>{ match.title }</a>
						) : (
							<strong>{ match.title }</strong>
						) }{ ' ' }
						<span className="qcwr-muted">
							{ `${ REASONS()[ match.reason ] || '' }${
								match.planned
									? ` · ${
											match.post_id
												? __(
														'not published yet',
														'chatbot'
													)
												: __(
														'planned',
														'chatbot'
													)
										}`
									: ''
							}` }
						</span>
					</li>
				) ) }
			</ul>
			<p className="qcwr-muted">
				{ __(
					'Two pages for the same search compete with each other in Google. Choose what to do:',
					'chatbot'
				) }
			</p>
			<div className="qcwr-actions">
				{ first.improve_url && (
					<Button variant="primary" href={ first.improve_url }>
						{ __( 'Improve that page', 'chatbot' ) }
					</Button>
				) }
				<Button variant="secondary" onClick={ () => onAngle( first ) }>
					{ __( 'Write a different angle', 'chatbot' ) }
				</Button>
				<Button variant="tertiary" onClick={ onContinue }>
					{ __( 'Continue anyway', 'chatbot' ) }
				</Button>
				<Button variant="link" onClick={ onBack }>
					{ __( 'Back to the brief', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default DuplicateCheck;
