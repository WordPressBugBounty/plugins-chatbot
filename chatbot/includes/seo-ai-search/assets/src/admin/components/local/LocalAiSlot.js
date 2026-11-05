/**
 * Local SEO > "How AI assistants answer local questions" (round 3, K).
 *
 * An extension (WPBot Pro) fills it through the `qcld_wpbot_seo.local.ai`
 * filter. When nothing fills it and the host shows Pro previews (Free hosts,
 * until the owner hides them), a labelled, inactive Pro preview explains it.
 * No Pro code runs behind the preview, and the module never checks which
 * edition is installed: it only reacts to what has been registered.
 */
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { data } from '../../api';
import ProPreview from '../ProPreview';

const preview = () => ( {
	label: __( 'Local AI visibility', 'chatbot' ),
	text: __(
		'See whether ChatGPT, Gemini and other assistants name your business when people ask for one near them.',
		'chatbot'
	),
	features: [
		__(
			'Local questions built from your town, the areas you serve and your services, tracked with one click',
			'chatbot'
		),
		__(
			'Which competitors the assistants name instead, and which sites they cite',
			'chatbot'
		),
		__(
			'Get cited there too: the cited sites become places to show up in Get cited by AI',
			'chatbot'
		),
	],
} );

const LocalAiSlot = ( { view } ) => {
	const card = applyFilters( 'qcld_wpbot_seo.local.ai', null, { view } );
	if ( card ) {
		return card;
	}
	if ( ( data.previews || [] ).length > 0 ) {
		return <ProPreview preview={ preview() } as="h3" />;
	}
	return null;
};

export default LocalAiSlot;
