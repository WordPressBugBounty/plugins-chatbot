/**
 * Improve Content: pick a page (or a decay alert) and review section by
 * section. Opens with a post or job handed over by another screen, the
 * posts list's "Improve with AI" link, or empty.
 */
import { useState } from '@wordpress/element';
import { data } from '../api';
import ImproveFlow from '../components/improve/ImproveFlow';
import { navigate, takeIntent } from '../nav';

const start = () => {
	const intent = takeIntent() || {};
	if ( intent.jobId || intent.post ) {
		return intent;
	}
	if ( data.improvePost ) {
		const post = { id: data.improvePost, title: data.improveTitle || '' };
		data.improvePost = 0;
		return { post };
	}
	return {};
};

const ImproveScreen = () => {
	const [ setup ] = useState( start );
	return (
		<ImproveFlow
			initialJobId={ setup.jobId || 0 }
			initialPost={ setup.post || null }
			initialFix={ setup.fix || '' }
			onExit={ () => navigate( 'overview' ) }
		/>
	);
};

export default ImproveScreen;
