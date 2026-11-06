/**
 * AI Writer admin app entry: mounts the app into #qcwr-app.
 */
import { createRoot, render } from '@wordpress/element';
import App from './App';
import { core, data, errorMessage, writer } from './api';
import JobProgress from './components/JobProgress';
import KeyNotice from './components/KeyNotice';
import CompareTechniques from './components/persuasion/CompareTechniques';
import ArticleRegenerate from './components/regenerate/ArticleRegenerate';
import RegenerateControl, {
	OptionsForm,
	VersionPager,
	optionsLabel,
	useVersions,
} from './components/regenerate/Regenerate';
import HonestyFlags from './components/persuasion/HonestyFlags';
import PersuasionPicker, {
	WhyNote,
} from './components/persuasion/PersuasionPicker';
import './style.scss';

/*
 * Shared pieces for extensions (the Pro module) that add screens and
 * panels through the qcld_wpbot_writer.admin.* and .topics.* filters.
 */
window.qcldWpbotWriterUI = {
	JobProgress,
	KeyNotice,
	// Round 3: shared persuasion pieces for the Pro screens.
	HonestyFlags,
	PersuasionPicker,
	WhyNote,
	CompareTechniques,
	// CR-14: the shared Regenerate control for Pro screens.
	RegenerateControl,
	ArticleRegenerate,
	OptionsForm,
	VersionPager,
	optionsLabel,
	useVersions,
	api: { core, data, errorMessage, writer },
};

const mount = () => {
	const node = document.getElementById( 'qcwr-app' );
	if ( ! node ) {
		return;
	}
	if ( createRoot ) {
		createRoot( node ).render( <App /> );
	} else {
		render( <App />, node );
	}
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
