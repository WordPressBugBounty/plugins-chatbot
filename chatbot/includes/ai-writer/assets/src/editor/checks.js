/**
 * Live writing checklist rules (no AI cost). Each check returns a status
 * (good, warn or info), a short label and, where it helps, a hint.
 * Replaces the old two scores with plain, fixable items.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { getBlockContent } from '@wordpress/blocks';
import { toText } from './utils';

const words = ( text ) =>
	( String( text ).match( /[\p{L}\p{N}][\p{L}\p{N}\p{M}'’-]*/gu ) || [] )
		.length;

const sentences = ( text ) =>
	String( text )
		.split( /(?<=[.!?。！？])\s+/u )
		.map( ( s ) => s.trim() )
		.filter( Boolean );

const has = ( haystack, needle ) =>
	!! needle &&
	String( haystack ).toLowerCase().includes( String( needle ).toLowerCase() );

/**
 * Facts about the content, from the block list.
 *
 * @param {Object[]} blocks  Flat block list.
 * @param {string}   homeUrl Site address (internal links).
 * @return {Object} Facts.
 */
export const contentFacts = ( blocks, homeUrl ) => {
	const facts = {
		text: [],
		headings: [],
		images: [],
		links: { internal: 0, external: 0 },
		placeholders: 0,
		imagePlaceholders: 0,
		longParagraphs: 0,
		firstParagraph: '',
		faq: false,
	};
	const host = ( () => {
		try {
			return new URL( homeUrl ).host;
		} catch {
			return window.location.host;
		}
	} )();

	blocks.forEach( ( block ) => {
		if ( 'core/image' === block.name ) {
			facts.images.push( {
				clientId: block.clientId,
				id: block.attributes.id || 0,
				url: block.attributes.url || '',
				alt: block.attributes.alt || '',
				caption: String( block.attributes.caption || '' ),
			} );
			return;
		}
		if (
			'qcld-wpbot-writer/ai' === block.name ||
			block.innerBlocks.length
		) {
			return;
		}
		const html = getBlockContent( block );
		const text = toText( html );
		if ( ! text ) {
			return;
		}
		if ( 'core/heading' === block.name ) {
			facts.headings.push( {
				level: block.attributes.level || 2,
				text,
			} );
			if ( /frequently asked|faq/i.test( text ) ) {
				facts.faq = true;
			}
		}
		if ( /qcwr-image-placeholder/.test( html ) ) {
			facts.imagePlaceholders++;
		}
		facts.placeholders += ( text.match( /\[[^\]]{3,80}\]/g ) || [] ).length;
		if ( 'core/paragraph' === block.name ) {
			if ( ! facts.firstParagraph ) {
				facts.firstParagraph = text;
			}
			if ( words( text ) > 150 ) {
				facts.longParagraphs++;
			}
		}
		( html.match( /href="([^"]+)"/g ) || [] ).forEach( ( match ) => {
			const href = match.slice( 6, -1 );
			if ( /^(#|mailto:|tel:)/.test( href ) ) {
				return;
			}
			try {
				const url = new URL( href, homeUrl );
				facts.links[ url.host === host ? 'internal' : 'external' ]++;
			} catch {}
		} );
		facts.text.push( { clientId: block.clientId, text } );
	} );
	return facts;
};

/**
 * Checklist items.
 *
 * @param {Object} facts  From contentFacts().
 * @param {Object} post   title, excerpt, featured, metaTitle, metaDescription, focus.
 * @param {Object} remote Server check: patterns[], voice[] (or null while loading).
 * @return {Object[]} Items: id, status, label, hint.
 */
export const checklist = ( facts, post, remote ) => {
	const all = facts.text.map( ( item ) => item.text ).join( ' ' );
	const count = words( all );
	const list = sentences( all );
	const avg = list.length ? Math.round( count / list.length ) : 0;
	const items = [];
	const add = ( id, status, label, hint = '' ) =>
		items.push( { id, status, label, hint } );

	add(
		'length',
		count >= 300 ? 'good' : 'info',
		sprintf(
			/* translators: %d: word count. */
			_n( '%d word', '%d words', count, 'chatbot' ),
			count
		)
	);

	const titleLength = ( post.title || '' ).length;
	add(
		'title',
		titleLength && titleLength <= 65 ? 'good' : 'warn',
		titleLength
			? sprintf(
					/* translators: %d: characters. */
					__( 'Title: %d characters', 'chatbot' ),
					titleLength
				)
			: __( 'Add a title', 'chatbot' ),
		titleLength > 65
			? __(
					'Long titles get cut off in search results.',
					'chatbot'
				)
			: ''
	);

	const skipped = facts.headings.some(
		( heading, index ) =>
			index > 0 && heading.level > facts.headings[ index - 1 ].level + 1
	);
	add(
		'headings',
		( count < 300 || facts.headings.length ) && ! skipped ? 'good' : 'warn',
		facts.headings.length
			? sprintf(
					/* translators: %d: number of headings. */
					_n(
						'%d heading',
						'%d headings',
						facts.headings.length,
						'chatbot'
					),
					facts.headings.length
				)
			: __( 'No headings yet', 'chatbot' ),
		skipped
			? __(
					'A heading level is skipped (for example H2 to H4).',
					'chatbot'
				)
			: ''
	);

	add(
		'readability',
		avg <= 20 && ! facts.longParagraphs ? 'good' : 'warn',
		sprintf(
			/* translators: %d: average words per sentence. */
			__(
				'Readability: %d words per sentence on average',
				'chatbot'
			),
			avg
		),
		facts.longParagraphs
			? __(
					'Some paragraphs are over 150 words. Split them.',
					'chatbot'
				)
			: ''
	);

	add(
		'links',
		facts.links.internal ? 'good' : 'warn',
		sprintf(
			/* translators: 1: internal links, 2: external links. */
			__(
				'Links: %1$d to your site, %2$d to other sites',
				'chatbot'
			),
			facts.links.internal,
			facts.links.external
		),
		facts.links.internal
			? ''
			: __(
					'Link to at least one related page on your site.',
					'chatbot'
				)
	);

	const noAlt = facts.images.filter( ( image ) => ! image.alt ).length;
	add(
		'images',
		post.featured && ! noAlt && ! facts.imagePlaceholders ? 'good' : 'warn',
		post.featured
			? __( 'Featured image set', 'chatbot' )
			: __( 'No featured image', 'chatbot' ),
		[
			noAlt
				? sprintf(
						/* translators: %d: images without alt text. */
						_n(
							'%d image has no alt text.',
							'%d images have no alt text.',
							noAlt,
							'chatbot'
						),
						noAlt
					)
				: '',
			facts.imagePlaceholders
				? sprintf(
						/* translators: %d: image placeholders. */
						_n(
							'%d image placeholder to replace.',
							'%d image placeholders to replace.',
							facts.imagePlaceholders,
							'chatbot'
						),
						facts.imagePlaceholders
					)
				: '',
		]
			.filter( Boolean )
			.join( ' ' )
	);

	add(
		'touches',
		facts.placeholders ? 'warn' : 'good',
		facts.placeholders
			? sprintf(
					/* translators: %d: placeholders left. */
					_n(
						'Personal touches to add: %d [placeholder]',
						'Personal touches to add: %d [placeholders]',
						facts.placeholders,
						'chatbot'
					),
					facts.placeholders
				)
			: __( 'No [placeholders] left', 'chatbot' )
	);

	add(
		'faq',
		facts.faq ? 'good' : 'info',
		facts.faq
			? __( 'FAQ section included', 'chatbot' )
			: __( 'No FAQ section (optional)', 'chatbot' )
	);

	const metaTitle = ( post.metaTitle || '' ).length;
	const metaDescription = ( post.metaDescription || '' ).length;
	add(
		'meta',
		metaTitle &&
			metaTitle <= 60 &&
			metaDescription >= 70 &&
			metaDescription <= 160
			? 'good'
			: 'warn',
		metaTitle && metaDescription
			? sprintf(
					/* translators: 1: title length, 2: description length. */
					__(
						'Search title %1$d and description %2$d characters',
						'chatbot'
					),
					metaTitle,
					metaDescription
				)
			: __( 'Search title or description missing', 'chatbot' )
	);

	add(
		'excerpt',
		post.excerpt ? 'good' : 'info',
		post.excerpt
			? __( 'Excerpt written', 'chatbot' )
			: __( 'No excerpt (optional)', 'chatbot' )
	);

	if ( post.focus ) {
		const spots = [
			[ has( post.title, post.focus ), __( 'title', 'chatbot' ) ],
			[
				has( facts.firstParagraph, post.focus ),
				__( 'first paragraph', 'chatbot' ),
			],
			[
				facts.headings.some( ( heading ) =>
					has( heading.text, post.focus )
				),
				__( 'a heading', 'chatbot' ),
			],
			[
				has( post.metaDescription, post.focus ),
				__( 'search description', 'chatbot' ),
			],
		];
		const missing = spots
			.filter( ( spot ) => ! spot[ 0 ] )
			.map( ( spot ) => spot[ 1 ] );
		add(
			'topic',
			missing.length <= 1 ? 'good' : 'warn',
			sprintf(
				/* translators: 1: places found, 2: places checked. */
				__(
					'Topic covered in %1$d of %2$d key places',
					'chatbot'
				),
				spots.length - missing.length,
				spots.length
			),
			missing.length
				? sprintf(
						/* translators: %s: list of places. */
						__( 'Missing from: %s', 'chatbot' ),
						missing.join( ', ' )
					)
				: ''
		);
	}

	if ( remote ) {
		add(
			'patterns',
			remote.patterns.length ? 'warn' : 'good',
			remote.patterns.length
				? sprintf(
						/* translators: %d: number of flags. */
						_n(
							'%d phrase sounds like AI',
							'%d phrases sound like AI',
							remote.patterns.length,
							'chatbot'
						),
						remote.patterns.length
					)
				: __( 'Reads naturally', 'chatbot' )
		);
		add(
			'voice',
			remote.voice.length ? 'warn' : 'good',
			remote.voice.length
				? sprintf(
						/* translators: %d: number of flags. */
						_n(
							'%d sentence off voice or style guide',
							'%d sentences off voice or style guide',
							remote.voice.length,
							'chatbot'
						),
						remote.voice.length
					)
				: __( 'Matches your voice and style guide', 'chatbot' )
		);
		// Round 3: clarity, stickiness and persuasion (rules, no AI).
		( remote.persuasion || [] ).forEach( ( item ) =>
			add( `persuasion-${ item.id }`, item.status, item.label, item.hint )
		);
		// Honesty flags cannot be hidden; publishing anyway is recorded.
		( remote.honesty || [] ).forEach( ( flag ) =>
			add(
				`honesty-${ flag.code }`,
				'warn',
				sprintf(
					/* translators: %s: what was found. */
					__( 'Honesty: %s', 'chatbot' ),
					flag.label
				),
				`${ flag.message } ${ ( flag.words || [] )
					.map( ( word ) => `"${ word }"` )
					.join( ', ' ) }`
			)
		);
		if ( remote.honesty && ! remote.honesty.length && remote.persuasion ) {
			add(
				'honesty',
				'good',
				__(
					'Honest: no invented proof, urgency or prices',
					'chatbot'
				)
			);
		}
	}
	return items;
};
