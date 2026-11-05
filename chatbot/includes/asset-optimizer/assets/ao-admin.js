/**
 * Performance tab: Clear and Rebuild buttons.
 *
 * @package QuantumCloud\AssetOptimizer
 */
( function ( $ ) {
	'use strict';

	var config = window.qcldWpbotAo || {};

	$( document ).on( 'click', '[data-qcld-wpbot-ao-action]', function ( event ) {
		var $button = $( this );
		var $result = $button.closest( '.qcld-wpbot-ao-actions' ).find( '.qcld-wpbot-ao-result' );

		event.preventDefault();
		if ( $button.prop( 'disabled' ) ) {
			return;
		}
		$button.prop( 'disabled', true );
		$result.text( config.i18n.working );

		$.post( config.ajaxUrl, {
			action: 'qcld_wpbot_ao_' + $button.data( 'qcldWpbotAoAction' ),
			nonce: config.nonce
		} ).done( function ( response ) {
			var text = response && response.data && response.data.message ? response.data.message : config.i18n.failed;
			if ( response && response.data && response.data.notice ) {
				text += ' ' + response.data.notice;
			}
			$result.text( text );
		} ).fail( function () {
			$result.text( config.i18n.failed );
		} ).always( function () {
			$button.prop( 'disabled', false );
		} );
	} );
}( jQuery ) );

/*
 * Open the Performance tab when the URL asks for it (&tab=performance), for
 * example from the update invitation. The host's tabs otherwise remember the
 * last tab in localStorage only.
 */
jQuery( window ).on( 'load', function () {
	'use strict';

	if ( ! /[?&]tab=performance(&|$)/.test( window.location.search ) ) {
		return;
	}
	// After the host's own tab script has bound its click handlers and restored
	// its remembered tab (document ready). jQuery 3 runs ready callbacks
	// asynchronously, so the load event can come first: queue behind them.
	jQuery( function () {
		setTimeout( function () {
			jQuery( '.wp-chatbot-tabs nav li[tab-data="performance"] > a' ).first().trigger( 'click' );
		}, 0 );
	} );
} );
