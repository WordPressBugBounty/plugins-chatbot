/**
 * WPBot AI Content Generator — boot.
 */
/* global jQuery, QcldWpbotAiSeo */
jQuery( function ( $ ) {
	'use strict';

	const A = window.QcldWpbotAiSeo;
	if ( ! A || ! A.content || ! $( '#qcld_wpbot_ai_seo_app' ).length ) {
		return;
	}
	const { __ } = A;

	A.createApp( {
		el: '#qcld_wpbot_ai_seo_app',
		module: 'content-generator',
		defaultView: 'analytics',
		nav: [
			{ key: 'analytics', label: __( 'Dashboard' ), icon: 'dashicons-chart-bar' },
			{ key: 'articles', label: __( 'All Articles' ), icon: 'dashicons-list-view' },
			{ key: 'new-article', label: __( 'Write New Article' ), icon: 'dashicons-edit' },
			{ key: 'brand-voices', label: __( 'Brand Voice' ), icon: 'dashicons-star-filled', badge: A.cfg.hasPro ? '' : __( 'PRO' ) },
			{ key: 'presets', label: __( 'Content Templates' ), icon: 'dashicons-media-default' },
			{ key: 'settings', label: __( 'Generator Settings' ), icon: 'dashicons-admin-generic' },
		],
		// "Go Premium" button rendered by the page shell (free version only).
		navFooter: $( '#qcld_wpbot_ai_seo_nav_footer' ).html() || '',
		navFor: ( view ) => ( 'edit-article' === view || 'preview-article' === view ? 'articles' : view ),
		// Add-on views (e.g. Brand Voices from the Pro add-on) replace the free ones.
		views: Object.assign( {}, A.content.views, A.ext.content.views ),
	} );
} );
