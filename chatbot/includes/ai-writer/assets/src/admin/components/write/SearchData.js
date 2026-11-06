/**
 * Search data in the brief (CR-6 W1): the numbers SEO & AI Search sent
 * (each with its source), the supporting keywords' monthly searches, and
 * the pages to link to and from. Shows nothing when there is no data.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Search intents (value => label).
 *
 * @return {Array} Options for a SelectControl.
 */
export const INTENTS = () => [
	{ value: '', label: __( 'Not set', 'chatbot' ) },
	{
		value: 'informational',
		label: __( 'Learn something (a guide)', 'chatbot' ),
	},
	{
		value: 'commercial',
		label: __( 'Compare options (a comparison)', 'chatbot' ),
	},
	{
		value: 'transactional',
		label: __(
			'Buy or book (a product or service page)',
			'chatbot'
		),
	},
	{
		value: 'navigational',
		label: __( 'Find something (a short page)', 'chatbot' ),
	},
	{ value: 'local', label: __( 'Nearby (a local page)', 'chatbot' ) },
];

/**
 * "1,200 a month" or "1,000–5,000 a month".
 *
 * @param {Object} row volume, volume_range.
 * @return {string} Text ('' when unknown).
 */
export const monthly = ( row ) => {
	if ( ! row ) {
		return '';
	}
	if ( null !== row.volume && undefined !== row.volume ) {
		return sprintf(
			/* translators: %s: number of searches. */
			__( '%s a month', 'chatbot' ),
			Number( row.volume ).toLocaleString()
		);
	}
	if ( Array.isArray( row.volume_range ) && 2 === row.volume_range.length ) {
		return sprintf(
			/* translators: 1: lowest, 2: highest number of searches. */
			__( '%1$s–%2$s a month', 'chatbot' ),
			Number( row.volume_range[ 0 ] ).toLocaleString(),
			Number( row.volume_range[ 1 ] ).toLocaleString()
		);
	}
	return '';
};

/**
 * A source badge ("Google Keyword Planner").
 *
 * @param {Object} props        Props.
 * @param {string} props.source Source name.
 * @return {Element|null} Badge.
 */
export const SourceBadge = ( { source } ) =>
	source ? (
		<span className="qcwr-badge qcwr-badge--source">{ source }</span>
	) : null;

const LinkList = ( { title, help, posts, ids, onRemove } ) => {
	if ( ! ids || ! ids.length ) {
		return null;
	}
	const named = Object.fromEntries(
		( posts || [] ).map( ( post ) => [ post.id, post.title ] )
	);
	return (
		<div className="qcwr-search-data__links">
			<strong>{ title }</strong>
			<p className="qcwr-muted">{ help }</p>
			<ul>
				{ ids.map( ( id ) => (
					<li key={ id }>
						{ named[ id ] ||
							sprintf(
								/* translators: %d: post ID. */
								__( 'Page %d', 'chatbot' ),
								id
							) }{ ' ' }
						<Button
							variant="link"
							isDestructive
							onClick={ () => onRemove( id ) }
						>
							{ __( 'Remove', 'chatbot' ) }
						</Button>
					</li>
				) ) }
			</ul>
		</div>
	);
};

const SearchData = ( { brief, set, linkPosts } ) => {
	const numbers = Object.entries( brief.numbers || {} );
	const volumes = ( brief.keywords || [] )
		.map( ( keyword ) => [
			keyword,
			( brief.keyword_numbers || {} )[ keyword ],
		] )
		.filter( ( [ , row ] ) => monthly( row ) );
	const hasLinks =
		( brief.link_to || [] ).length || ( brief.link_from || [] ).length;
	if ( ! numbers.length && ! volumes.length && ! hasLinks ) {
		return null;
	}
	return (
		<div className="qcwr-search-data">
			{ numbers.length > 0 && (
				<>
					<strong>
						{ __( 'Search numbers', 'chatbot' ) }
					</strong>
					<dl className="qcwr-search-data__numbers">
						{ numbers.map( ( [ label, value ] ) => (
							<div key={ label }>
								<dt>{ label }</dt>
								<dd>{ value }</dd>
							</div>
						) ) }
					</dl>
				</>
			) }
			{ volumes.length > 0 && (
				<>
					<strong>
						{ __(
							'Searches for your keywords (most searched are covered first)',
							'chatbot'
						) }
					</strong>
					<ul className="qcwr-search-data__keywords">
						{ volumes.map( ( [ keyword, row ] ) => (
							<li key={ keyword }>
								{ keyword } · { monthly( row ) }{ ' ' }
								<SourceBadge source={ row.source } />
							</li>
						) ) }
					</ul>
				</>
			) }
			<LinkList
				title={ __( 'Link to these pages', 'chatbot' ) }
				help={ __(
					'The article links to them where it fits naturally.',
					'chatbot'
				) }
				posts={ linkPosts && linkPosts.to }
				ids={ brief.link_to }
				onRemove={ ( id ) =>
					set(
						'link_to',
						brief.link_to.filter( ( item ) => item !== id )
					)
				}
			/>
			<LinkList
				title={ __(
					'Pages that should link to the article',
					'chatbot'
				) }
				help={ __(
					'After you publish, the editor lists them with an "Add link" button. Nothing is changed without your click.',
					'chatbot'
				) }
				posts={ linkPosts && linkPosts.from }
				ids={ brief.link_from }
				onRemove={ ( id ) =>
					set(
						'link_from',
						brief.link_from.filter( ( item ) => item !== id )
					)
				}
			/>
		</div>
	);
};

export default SearchData;
