/**
 * Page Audit (F1): pick a post type and a page, set a target keyword
 * (pre-filled from the Vault, the SEO plugin, the Keyword Map or Search
 * Console), optionally save it as the focus keyword, and run the audit.
 */
import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { data, errorMessage, seo } from '../../api';

const SOURCES = {
	vault: __( 'from your Keyword Vault', 'chatbot' ),
	seo_plugin: __( 'your SEO plugin’s focus keyword', 'chatbot' ),
	map: __( 'from the Keyword Map', 'chatbot' ),
	search_console: __(
		'the page’s main search in Search Console',
		'chatbot'
	),
};

/**
 * @param {Object}                 props        Props.
 * @param {(view: Object) => void} props.onDone Called with the audit view.
 * @return {Element} Card.
 */
const RunAudit = ( { onDone } ) => {
	const [ types, setTypes ] = useState( [] );
	const [ type, setType ] = useState( 'post' );
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState( [] );
	const [ postId, setPostId ] = useState( '' );
	const [ keyword, setKeyword ] = useState( '' );
	const [ source, setSource ] = useState( '' );
	const [ save, setSave ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		seo( 'audit/types' )
			.then( setTypes )
			.catch( () => {} );
	}, [] );
	useEffect( () => {
		const timer = setTimeout( () => {
			seo(
				`audit/content?type=${ encodeURIComponent(
					type
				) }&search=${ encodeURIComponent( search ) }`
			)
				.then( ( list ) => {
					setItems( list );
					if (
						list.length &&
						! list.some( ( i ) => String( i.id ) === postId )
					) {
						setPostId( '' );
					}
				} )
				.catch( () => setItems( [] ) );
		}, 250 );
		return () => clearTimeout( timer );
		// postId only checked, not a trigger.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ type, search ] );
	useEffect( () => {
		if ( ! postId ) {
			setKeyword( '' );
			setSource( '' );
			return;
		}
		seo( `audit/keyword?post_id=${ postId }` )
			.then( ( s ) => {
				setKeyword( s.keyword || '' );
				setSource( s.source || '' );
			} )
			.catch( () => {} );
	}, [ postId ] );

	const run = () => {
		setBusy( true );
		setMessage( '' );
		seo( 'audit/run', {
			method: 'POST',
			data: {
				post_id: Number( postId ),
				keyword,
				save_focus: save,
			},
		} )
			.then( ( view ) => onDone( view ) )
			.catch( ( e ) => setMessage( errorMessage( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="qcseo-card qcseo-run-audit">
			<h3>{ __( 'Run an audit', 'chatbot' ) }</h3>
			<div className="qcseo-grid">
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Content type', 'chatbot' ) }
					value={ type }
					options={ types.map( ( t ) => ( {
						value: t.id,
						label: t.label,
					} ) ) }
					onChange={ ( value ) => {
						setType( value );
						setPostId( '' );
					} }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Find content', 'chatbot' ) }
					value={ search }
					onChange={ setSearch }
					placeholder={ __( 'Search titles…', 'chatbot' ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Content', 'chatbot' ) }
					value={ postId }
					options={ [
						{
							value: '',
							label: __( 'Choose…', 'chatbot' ),
						},
						...items.map( ( i ) => ( {
							value: String( i.id ),
							label:
								( i.title || `#${ i.id }` ) +
								( null !== i.score ? ` (${ i.score })` : '' ) +
								( 'publish' !== i.status
									? ` — ${ i.status }`
									: '' ),
						} ) ),
					] }
					onChange={ setPostId }
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __(
						'Target keyword (optional)',
						'chatbot'
					) }
					value={ keyword }
					onChange={ ( value ) => {
						setKeyword( value );
						setSource( '' );
					} }
					help={
						source && SOURCES[ source ]
							? sprintf(
									/* translators: %s: where the keyword came from. */
									__( 'Suggested: %s.', 'chatbot' ),
									SOURCES[ source ]
								)
							: ''
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="qcseo-actions">
				{ data.can.publish && (
					<CheckboxControl
						label={ __(
							'Save as focus keyword (your SEO plugin and the Keyword Map; can be undone)',
							'chatbot'
						) }
						checked={ save }
						disabled={ ! keyword.trim() }
						onChange={ setSave }
						__nextHasNoMarginBottom
					/>
				) }
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ ! postId || busy || ! data.can.use }
					onClick={ run }
				>
					{ __( 'Run audit', 'chatbot' ) }
				</Button>
				<span className="qcseo-muted">
					{ __(
						'Instant, free: no AI is used.',
						'chatbot'
					) }
				</span>
			</div>
			{ message && <p className="qcseo-error">{ message }</p> }
		</div>
	);
};

export default RunAudit;
