/**
 * The "Help Center" panel in the article editor: who can read the article,
 * the version it applies to, and alternative questions (one per line) that
 * help search and the chatbot find it.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var cfg = window.qcldWpbotHelpCenterPanel || {};

	if ( ! Panel ) {
		return;
	}

	function HelpCenterPanel() {
		var ids = wp.data.useSelect( function ( select ) {
			return { type: select( 'core/editor' ).getCurrentPostType(), id: select( 'core/editor' ).getCurrentPostId() };
		}, [] );
		var postType = ids.type;
		var entity = wp.coreData.useEntityProp( 'postType', postType, 'meta' );
		var meta = entity[ 0 ] || {};
		var setMeta = entity[ 1 ];
		// Unsaved meta changes so far. Only changed keys are sent: sending the whole meta object
		// would include read-only keys (votes, views), which the REST API refuses.
		var pending = wp.data.useSelect(
			function ( select ) {
				var edits = select( 'core' ).getEntityRecordEdits( 'postType', postType, ids.id );
				return ( edits && edits.meta ) || {};
			},
			[ postType, ids.id ]
		);
		var linesState = wp.element.useState( null );
		if ( 'wpbot_article' !== postType ) {
			return null;
		}
		var access = Array.isArray( meta._wpbot_access ) && meta._wpbot_access.indexOf( 'logged_in' ) !== -1 ? 'logged_in' : 'public';
		var questions = Array.isArray( meta._wpbot_alt_questions ) ? meta._wpbot_alt_questions : [];
		function update( key, value ) {
			var next = {};
			next[ key ] = value;
			setMeta( Object.assign( {}, pending, next ) );
		}
		return el(
			Panel,
			{ name: 'wpbot-help-center', title: __( 'Help Center', 'chatbot' ), className: 'wpbot-help-center-panel' },
			el( c.SelectControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Who can read this article', 'chatbot' ),
				value: access,
				disabled: ! cfg.canPublish,
				help: cfg.canPublish ? __( 'The chatbot follows this too.', 'chatbot' ) : __( 'An editor decides who can read it.', 'chatbot' ),
				options: [
					{ value: 'public', label: __( 'Everyone', 'chatbot' ) },
					{ value: 'logged_in', label: __( 'Signed-in visitors', 'chatbot' ) },
				],
				onChange: function ( value ) {
					update( '_wpbot_access', 'logged_in' === value ? [ 'logged_in' ] : [] );
				},
			} ),
			el( 'div', { style: { height: '16px' } } ),
			el( c.TextControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Applies to version', 'chatbot' ),
				value: meta._wpbot_product_version || '',
				placeholder: __( 'For example 15.8', 'chatbot' ),
				onChange: function ( value ) {
					update( '_wpbot_product_version', value );
				},
			} ),
			el( 'div', { style: { height: '16px' } } ),
			el( c.TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Other ways people ask', 'chatbot' ),
				help: __( 'One question per line. Search and the chatbot use them to find this article.', 'chatbot' ),
				value: null !== linesState[ 0 ] ? linesState[ 0 ] : questions.join( '\n' ),
				onChange: function ( value ) {
					linesState[ 1 ]( value );
					update(
						'_wpbot_alt_questions',
						value
							.split( '\n' )
							.map( function ( q ) {
								return q.trim();
							} )
							.filter( Boolean )
					);
				},
			} )
		);
	}

	wp.plugins.registerPlugin( 'wpbot-help-center-panel', { render: HelpCenterPanel } );
} )( window.wp );
