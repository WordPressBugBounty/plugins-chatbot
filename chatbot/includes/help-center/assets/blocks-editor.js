/**
 * Help Center content blocks: editor side (plain JavaScript, no build step).
 *
 * Block metadata comes from blocks/<name>/block.json, registered in PHP.
 * Callout, In short, Applies to and Related articles are rendered by PHP
 * (their translated labels are never saved into the post); the other blocks
 * save only what the author typed, so they stay valid in any site language.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var be = wp.blockEditor;
	var RichText = be.RichText;
	var InnerBlocks = be.InnerBlocks;
	var useBlockProps = be.useBlockProps;
	var InspectorControls = be.InspectorControls;
	var BlockControls = be.BlockControls;
	var MediaPlaceholder = be.MediaPlaceholder;
	var MediaReplaceFlow = be.MediaReplaceFlow;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	var CALLOUT_TYPES = [
		{ value: 'note', label: __( 'Note', 'chatbot' ) },
		{ value: 'tip', label: __( 'Tip', 'chatbot' ) },
		{ value: 'warning', label: __( 'Warning', 'chatbot' ) },
		{ value: 'important', label: __( 'Important', 'chatbot' ) },
	];
	var TEXT_ONLY = [ [ 'core/paragraph', { placeholder: __( 'Write here…', 'chatbot' ) } ] ];

	function calloutLabel( type ) {
		for ( var i = 0; i < CALLOUT_TYPES.length; i++ ) {
			if ( CALLOUT_TYPES[ i ].value === type ) {
				return CALLOUT_TYPES[ i ].label;
			}
		}
		return CALLOUT_TYPES[ 0 ].label;
	}

	// Callout: the wrapper and its label are rendered by PHP.
	registerBlockType( 'wpbot-help/callout', {
		edit: function ( props ) {
			var type = props.attributes.type || 'note';
			return el(
				Fragment,
				null,
				el(
					BlockControls,
					{ group: 'block' },
					el( c.ToolbarDropdownMenu, {
						icon: 'info-outline',
						label: __( 'Callout type', 'chatbot' ),
						controls: CALLOUT_TYPES.map( function ( t ) {
							return {
								title: t.label,
								isActive: t.value === type,
								onClick: function () {
									props.setAttributes( { type: t.value } );
								},
							};
						} ),
					} )
				),
				el(
					'div',
					useBlockProps( { className: 'wpbot-help-callout is-' + type, role: 'note' } ),
					el( 'p', { className: 'wpbot-help-callout__label' }, calloutLabel( type ) ),
					el( 'div', { className: 'wpbot-help-callout__body' }, el( InnerBlocks, { template: TEXT_ONLY, templateLock: false } ) )
				)
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );

	// In short: the wrapper and its label are rendered by PHP.
	registerBlockType( 'wpbot-help/summary', {
		edit: function () {
			return el(
				'aside',
				useBlockProps( { className: 'wpbot-help-summary' } ),
				el( 'p', { className: 'wpbot-help-summary__label' }, __( 'In short', 'chatbot' ) ),
				el( InnerBlocks, { template: [ [ 'core/list' ] ], templateLock: false } )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );

	// Steps: an ordered list; each step has a title (h3) the chatbot can cite.
	registerBlockType( 'wpbot-help/steps', {
		edit: function () {
			return el(
				'ol',
				useBlockProps( { className: 'wpbot-help-steps' } ),
				el( InnerBlocks, {
					allowedBlocks: [ 'wpbot-help/step' ],
					template: [ [ 'wpbot-help/step' ], [ 'wpbot-help/step' ] ],
					renderAppender: InnerBlocks.ButtonBlockAppender,
				} )
			);
		},
		save: function () {
			return el( 'ol', useBlockProps.save( { className: 'wpbot-help-steps' } ), el( InnerBlocks.Content ) );
		},
	} );

	registerBlockType( 'wpbot-help/step', {
		edit: function ( props ) {
			return el(
				'li',
				useBlockProps( { className: 'wpbot-help-step' } ),
				el( RichText, {
					tagName: 'h3',
					className: 'wpbot-help-step__title',
					value: props.attributes.title,
					allowedFormats: [ 'core/bold', 'core/italic', 'core/code' ],
					placeholder: __( 'What to do in this step', 'chatbot' ),
					onChange: function ( title ) {
						props.setAttributes( { title: title } );
					},
				} ),
				el( 'div', { className: 'wpbot-help-step__body' }, el( InnerBlocks, { template: TEXT_ONLY, templateLock: false } ) )
			);
		},
		save: function ( props ) {
			return el(
				'li',
				useBlockProps.save( { className: 'wpbot-help-step' } ),
				el( RichText.Content, { tagName: 'h3', className: 'wpbot-help-step__title', value: props.attributes.title } ),
				el( 'div', { className: 'wpbot-help-step__body' }, el( InnerBlocks.Content ) )
			);
		},
	} );

	// Tabs: sections with a label; the front end turns them into tabs.
	registerBlockType( 'wpbot-help/tabs', {
		edit: function () {
			return el(
				'div',
				useBlockProps( { className: 'wpbot-help-tabs is-editing' } ),
				el( InnerBlocks, {
					allowedBlocks: [ 'wpbot-help/tab' ],
					template: [ [ 'wpbot-help/tab' ], [ 'wpbot-help/tab' ] ],
					renderAppender: InnerBlocks.ButtonBlockAppender,
				} )
			);
		},
		save: function () {
			return el( 'div', useBlockProps.save( { className: 'wpbot-help-tabs' } ), el( InnerBlocks.Content ) );
		},
	} );

	registerBlockType( 'wpbot-help/tab', {
		edit: function ( props ) {
			return el(
				'section',
				useBlockProps( { className: 'wpbot-help-tab' } ),
				el( RichText, {
					tagName: 'h3',
					className: 'wpbot-help-tab__label',
					value: props.attributes.label,
					allowedFormats: [],
					placeholder: __( 'Tab name, for example Windows', 'chatbot' ),
					onChange: function ( label ) {
						props.setAttributes( { label: label } );
					},
				} ),
				el( 'div', { className: 'wpbot-help-tab__panel' }, el( InnerBlocks, { template: TEXT_ONLY, templateLock: false } ) )
			);
		},
		save: function ( props ) {
			return el(
				'section',
				useBlockProps.save( { className: 'wpbot-help-tab' } ),
				el( RichText.Content, { tagName: 'h3', className: 'wpbot-help-tab__label', value: props.attributes.label } ),
				el( 'div', { className: 'wpbot-help-tab__panel' }, el( InnerBlocks.Content ) )
			);
		},
	} );

	// Accordion item: details and summary, so it works without JavaScript.
	registerBlockType( 'wpbot-help/accordion', {
		edit: function ( props ) {
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Settings', 'chatbot' ) },
						el( c.ToggleControl, {
							label: __( 'Open when the page loads', 'chatbot' ),
							checked: !! props.attributes.open,
							onChange: function ( open ) {
								props.setAttributes( { open: open } );
							},
						} )
					)
				),
				el(
					'div',
					useBlockProps( { className: 'wpbot-help-accordion is-editing' } ),
					el( RichText, {
						tagName: 'div',
						className: 'wpbot-help-accordion__summary',
						value: props.attributes.summary,
						allowedFormats: [ 'core/bold', 'core/italic', 'core/code' ],
						placeholder: __( 'Question or title', 'chatbot' ),
						onChange: function ( summary ) {
							props.setAttributes( { summary: summary } );
						},
					} ),
					el( 'div', { className: 'wpbot-help-accordion__body' }, el( InnerBlocks, { template: TEXT_ONLY, templateLock: false } ) )
				)
			);
		},
		save: function ( props ) {
			return el(
				'details',
				useBlockProps.save( { className: 'wpbot-help-accordion', open: props.attributes.open ? true : undefined } ),
				el( RichText.Content, { tagName: 'summary', value: props.attributes.summary } ),
				el( 'div', { className: 'wpbot-help-accordion__body' }, el( InnerBlocks.Content ) )
			);
		},
	} );

	// Screenshot with numbered markers.
	function round1( n ) {
		return Math.round( n * 10 ) / 10;
	}

	function markerStyle( m ) {
		return { left: round1( m.x ) + '%', top: round1( m.y ) + '%' };
	}

	registerBlockType( 'wpbot-help/screenshot', {
		edit: function ( props ) {
			var a = props.attributes;
			var markers = Array.isArray( a.markers ) ? a.markers : [];
			var blockProps = useBlockProps( { className: 'wpbot-help-screenshot' } );
			function onSelect( media ) {
				props.setAttributes( { id: media.id, url: media.url, alt: media.alt || a.alt || '' } );
			}
			function setMarkers( next ) {
				props.setAttributes( { markers: next } );
			}
			if ( ! a.url ) {
				return el(
					'div',
					blockProps,
					el( MediaPlaceholder, {
						icon: 'format-image',
						labels: { title: __( 'Screenshot', 'chatbot' ), instructions: __( 'Upload a screenshot or pick one from the media library. Then click the image to add numbered markers.', 'chatbot' ) },
						accept: 'image/*',
						allowedTypes: [ 'image' ],
						onSelect: onSelect,
					} )
				);
			}
			return el(
				Fragment,
				null,
				el(
					BlockControls,
					{ group: 'other' },
					el( MediaReplaceFlow, { mediaId: a.id, mediaURL: a.url, allowedTypes: [ 'image' ], accept: 'image/*', onSelect: onSelect } )
				),
				el(
					InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Screenshot', 'chatbot' ) },
						el( c.TextareaControl, {
							label: __( 'Alternative text', 'chatbot' ),
							help: __( 'Describe what the screenshot shows, for visitors who cannot see it.', 'chatbot' ),
							value: a.alt,
							onChange: function ( alt ) {
								props.setAttributes( { alt: alt } );
							},
						} )
					)
				),
				el(
					'figure',
					blockProps,
					el(
						'div',
						{
							className: 'wpbot-help-screenshot__frame',
							title: __( 'Click to add a numbered marker', 'chatbot' ),
							onClick: function ( event ) {
								var box = event.currentTarget.getBoundingClientRect();
								if ( event.target.classList.contains( 'wpbot-help-screenshot__marker' ) || ! box.width || ! box.height ) {
									return;
								}
								setMarkers(
									markers.concat( [
										{
											x: round1( ( ( event.clientX - box.left ) / box.width ) * 100 ),
											y: round1( ( ( event.clientY - box.top ) / box.height ) * 100 ),
											label: '',
										},
									] )
								);
							},
						},
						el( 'img', { src: a.url, alt: a.alt, className: a.id ? 'wp-image-' + a.id : undefined } ),
						markers.map( function ( m, i ) {
							return el( 'span', { key: i, className: 'wpbot-help-screenshot__marker', style: markerStyle( m ) }, String( i + 1 ) );
						} )
					),
					markers.length
						? el(
								'ol',
								{ className: 'wpbot-help-screenshot__legend' },
								markers.map( function ( m, i ) {
									return el(
										'li',
										{ key: i },
										el( RichText, {
											tagName: 'span',
											value: m.label,
											allowedFormats: [ 'core/bold', 'core/italic', 'core/code' ],
											placeholder: __( 'What this marker points at', 'chatbot' ),
											onChange: function ( label ) {
												setMarkers(
													markers.map( function ( x, j ) {
														return j === i ? { x: x.x, y: x.y, label: label } : x;
													} )
												);
											},
										} ),
										el( c.Button, {
											icon: 'no-alt',
											label: __( 'Remove marker', 'chatbot' ),
											isSmall: true,
											onClick: function () {
												setMarkers(
													markers.filter( function ( x, j ) {
														return j !== i;
													} )
												);
											},
										} )
									);
								} )
						  )
						: null,
					el( RichText, {
						tagName: 'figcaption',
						value: a.caption,
						placeholder: __( 'Caption (optional)', 'chatbot' ),
						onChange: function ( caption ) {
							props.setAttributes( { caption: caption } );
						},
					} )
				)
			);
		},
		save: function ( props ) {
			var a = props.attributes;
			var markers = Array.isArray( a.markers ) ? a.markers : [];
			return el(
				'figure',
				useBlockProps.save( { className: 'wpbot-help-screenshot' } ),
				el(
					'div',
					{ className: 'wpbot-help-screenshot__frame' },
					el( 'img', { src: a.url, alt: a.alt, className: a.id ? 'wp-image-' + a.id : undefined } ),
					markers.map( function ( m, i ) {
						return el( 'span', { key: i, className: 'wpbot-help-screenshot__marker', style: markerStyle( m ), 'aria-hidden': 'true' }, String( i + 1 ) );
					} )
				),
				markers.length
					? el(
							'ol',
							{ className: 'wpbot-help-screenshot__legend' },
							markers.map( function ( m, i ) {
								return el( RichText.Content, { key: i, tagName: 'li', value: m.label } );
							} )
					  )
					: null,
				a.caption ? el( RichText.Content, { tagName: 'figcaption', value: a.caption } ) : null
			);
		},
	} );

	// Applies to: rendered by PHP.
	registerBlockType( 'wpbot-help/applies-to', {
		edit: function ( props ) {
			return el(
				'p',
				useBlockProps( { className: 'wpbot-help-applies' } ),
				el( 'span', { className: 'wpbot-help-applies__label' }, __( 'Applies to', 'chatbot' ) ),
				' ',
				el( c.TextControl, {
					__nextHasNoMarginBottom: true,
					className: 'wpbot-help-applies__input',
					value: props.attributes.text,
					placeholder: __( 'For example WPBot Pro 15.8 and later', 'chatbot' ),
					onChange: function ( text ) {
						props.setAttributes( { text: text } );
					},
				} )
			);
		},
		save: function () {
			return null;
		},
	} );

	// Related articles: rendered by PHP.
	registerBlockType( 'wpbot-help/related', {
		edit: function ( props ) {
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Settings', 'chatbot' ) },
						el( c.RangeControl, {
							label: __( 'Number of articles', 'chatbot' ),
							min: 1,
							max: 10,
							value: props.attributes.count,
							onChange: function ( count ) {
								props.setAttributes( { count: count } );
							},
						} ),
						el( c.TextControl, {
							label: __( 'Title', 'chatbot' ),
							placeholder: __( 'Related articles', 'chatbot' ),
							value: props.attributes.title,
							onChange: function ( title ) {
								props.setAttributes( { title: title } );
							},
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( ServerSideRender, {
						block: 'wpbot-help/related',
						attributes: props.attributes,
						urlQueryArgs: { post_id: wp.data.select( 'core/editor' ) ? wp.data.select( 'core/editor' ).getCurrentPostId() : 0 },
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
