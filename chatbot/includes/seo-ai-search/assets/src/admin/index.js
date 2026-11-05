/**
 * SEO & AI Search admin app entry: mounts the app into #qcseo-app.
 */
import { createRoot, render } from '@wordpress/element';
import App from './App';
import { core, data, errorMessage, seo } from './api';
import JobProgress from './components/JobProgress';
import KeyNotice from './components/KeyNotice';
import VolumeLookup from './components/VolumeLookup';
import { CompetitorSuggest, splitDomains } from './components/CompetitorPicker';
import { AiReady, AiReason, aiBlocked, useAiReady } from './ai';
import './style.scss';

/*
 * Shared pieces for extensions (the Pro module) that add screens through
 * the qcld_wpbot_seo.admin.* filters.
 */
window.qcldWpbotSeoUI = {
	JobProgress,
	KeyNotice,
	AiReady,
	AiReason,
	aiBlocked,
	useAiReady,
	VolumeLookup,
	CompetitorSuggest,
	splitDomains,
	api: { core, data, errorMessage, seo },
};

const mount = () => {
	const node = document.getElementById( 'qcseo-app' );
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
