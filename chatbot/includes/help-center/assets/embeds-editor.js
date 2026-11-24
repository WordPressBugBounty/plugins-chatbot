/**
 * Editor side of the Help Center parts for any page (HC1-13): each block shows
 * the server's rendering and its settings in the sidebar. No build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;

	function text( key, label, help ) {
		return { key: key, type: 'text', label: label, help: help };
	}
	function number( key, label, min, max ) {
		return { key: key, type: 'number', label: label, min: min, max: max };
	}

	var FIELDS = {
		'search-box': [ text( 'placeholder', __( 'Placeholder', 'chatbot' ) ) ],
		'kb-grid': [],
		topics: [ number( 'articles', __( 'Articles per topic', 'chatbot' ), 1, 10 ) ],
		'article-list': [
			text( 'title', __( 'Heading', 'chatbot' ) ),
			text( 'category', __( 'Category', 'chatbot' ), __( 'Slugs or ids, separated by commas.', 'chatbot' ) ),
			text( 'kb', __( 'Knowledge base', 'chatbot' ), __( 'Slugs or ids, separated by commas.', 'chatbot' ) ),
			text( 'tag', __( 'Tag', 'chatbot' ), __( 'Slugs or ids, separated by commas.', 'chatbot' ) ),
			number( 'limit', __( 'Number of articles', 'chatbot' ), 1, 50 ),
			{
				key: 'orderby',
				type: 'select',
				label: __( 'Order', 'chatbot' ),
				options: [
					{ value: 'order', label: __( 'Help Center order', 'chatbot' ) },
					{ value: 'recent', label: __( 'Recently updated', 'chatbot' ) },
					{ value: 'title', label: __( 'Title', 'chatbot' ) },
					{ value: 'popular', label: __( 'Most read', 'chatbot' ) },
				],
			},
		],
		popular: [ text( 'title', __( 'Heading', 'chatbot' ) ), number( 'limit', __( 'Number of articles', 'chatbot' ), 1, 20 ) ],
		glossary: [],
		toc: [],
		feedback: [],
		launcher: [ text( 'label', __( 'Label', 'chatbot' ), __( 'Default: Get help', 'chatbot' ) ) ],
	};

	function control( field, props ) {
		var common = {
			key: field.key,
			label: field.label,
			help: field.help,
			value: props.attributes[ field.key ],
			onChange: function ( value ) {
				var next = {};
				next[ field.key ] = 'number' === field.type ? parseInt( value, 10 ) || field.min : value;
				props.setAttributes( next );
			},
		};
		if ( 'number' === field.type ) {
			common.min = field.min;
			common.max = field.max;
			return el( c.RangeControl, common );
		}
		if ( 'select' === field.type ) {
			common.options = field.options;
			return el( c.SelectControl, common );
		}
		return el( c.TextControl, common );
	}

	Object.keys( FIELDS ).forEach( function ( name ) {
		var full = 'wpbot-help/' + name;
		wp.blocks.registerBlockType( full, {
			edit: function ( props ) {
				var fields = FIELDS[ name ];
				return el(
					wp.element.Fragment,
					null,
					fields.length
						? el(
								InspectorControls,
								null,
								el(
									c.PanelBody,
									{ title: __( 'Settings', 'chatbot' ) },
									fields.map( function ( f ) {
										return control( f, props );
									} )
								)
						  )
						: null,
					el(
						'div',
						useBlockProps(),
						el( ServerSideRender, {
							block: full,
							attributes: props.attributes,
							urlQueryArgs: { post_id: wp.data && wp.data.select( 'core/editor' ) ? wp.data.select( 'core/editor' ).getCurrentPostId() : 0 },
							EmptyResponsePlaceholder: function () {
								return el( c.Placeholder, { label: wp.blocks.getBlockType( full ) ? wp.blocks.getBlockType( full ).title : full }, __( 'Nothing to show here yet. On the site this part appears once there is content for it (for example on a help article page).', 'chatbot' ) );
							},
						} )
					)
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );
