/**
 * Business Profile (round 3, CR-13): the Persuasion Kit (the owner's real
 * proof; AI Writer may use only what is here) and Customer words (the
 * words customers actually use, with personal details removed). Both are
 * stored in WPBot Core so every module persuades the same honest way.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { core, data, errorMessage } from '../api';
import { AiOnly } from '../components/AiReadiness';
import JobProgress from '../components/JobProgress';
import ImportPanel from '../components/business/ImportPanel';
import { ProSpot } from '../components/pro/ProPreview';

const QUOTES = [ 'review', 'testimonial' ];
const EMPTY = {
	type: 'review',
	label: '',
	body: '',
	value: '',
	source: '',
	url: '',
	permission: false,
	expires_on: '',
};

const KitForm = ( { types, entry, onSaved, onCancel } ) => {
	const [ form, setForm ] = useState( { ...EMPTY, ...entry } );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const set = ( key ) => ( value ) =>
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
	const type = types[ form.type ] || {};
	const save = () => {
		setBusy( true );
		setError( '' );
		core( entry.id ? `kit/${ entry.id }` : 'kit', {
			method: 'POST',
			data: { ...form, module: data.module },
		} )
			.then( onSaved )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<div className="qcwr-kit-form">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<SelectControl
				label={ __( 'What kind of proof', 'chatbot' ) }
				value={ form.type }
				options={ Object.keys( types ).map( ( id ) => ( {
					value: id,
					label: types[ id ].label,
				} ) ) }
				onChange={ set( 'type' ) }
				help={ type.help }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Short title (optional)', 'chatbot' ) }
				value={ form.label }
				onChange={ set( 'label' ) }
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'The proof, in full', 'chatbot' ) }
				value={ form.body }
				onChange={ set( 'body' ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			{ type.value && (
				<TextControl
					label={ type.value }
					value={ form.value }
					onChange={ set( 'value' ) }
					__nextHasNoMarginBottom
				/>
			) }
			<div className="qcwr-kit-form__row">
				<TextControl
					label={ __( 'Where it comes from', 'chatbot' ) }
					value={ form.source }
					onChange={ set( 'source' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="url"
					label={ __( 'Link (optional)', 'chatbot' ) }
					value={ form.url }
					onChange={ set( 'url' ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					type="date"
					label={
						'offer' === form.type
							? __( 'End date (required)', 'chatbot' )
							: __(
									'Stop using it after (optional)',
									'chatbot'
								)
					}
					value={ form.expires_on }
					onChange={ set( 'expires_on' ) }
					__nextHasNoMarginBottom
				/>
			</div>
			{ QUOTES.includes( form.type ) && (
				<CheckboxControl
					label={ __(
						'Permission to quote: the customer agreed that I may publish their words',
						'chatbot'
					) }
					help={ __(
						'Without permission, AI Writer uses these words only as wording ideas, never as a quote.',
						'chatbot'
					) }
					checked={ !! form.permission }
					onChange={ set( 'permission' ) }
					__nextHasNoMarginBottom
				/>
			) }
			<p>
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ save }
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>{ ' ' }
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'chatbot' ) }
				</Button>
			</p>
		</div>
	);
};

const KitEntry = ( { entry, canEdit, onEdit, onDelete } ) => (
	<li className={ `qcwr-kit__entry${ entry.expired ? ' is-expired' : '' }` }>
		<div className="qcwr-kit__text">
			{ entry.label && <strong>{ entry.label } </strong> }
			{ entry.value && (
				<span className="qcwr-kit__value">{ entry.value } </span>
			) }
			{ entry.body && <span>{ entry.body }</span> }
			{ entry.source && (
				<span className="qcwr-muted"> — { entry.source }</span>
			) }
		</div>
		<div className="qcwr-kit__meta">
			{ QUOTES.includes( entry.type ) &&
				( entry.permission ? (
					<span className="qcwr-badge qcwr-badge--ok">
						{ __( 'May be quoted', 'chatbot' ) }
					</span>
				) : (
					<span className="qcwr-badge">
						{ __( 'Wording only', 'chatbot' ) }
					</span>
				) ) }
			{ entry.expires_on &&
				( entry.expired ? (
					<span className="qcwr-badge qcwr-badge--warning">
						{ sprintf(
							/* translators: %s: date. */
							__(
								'Ended %s: never used in writing',
								'chatbot'
							),
							entry.expires_on
						) }
					</span>
				) : (
					<span className="qcwr-badge">
						{ sprintf(
							/* translators: %s: date. */
							__( 'Until %s', 'chatbot' ),
							entry.expires_on
						) }
					</span>
				) ) }
			{ canEdit && (
				<>
					<Button variant="link" onClick={ () => onEdit( entry ) }>
						{ __( 'Edit', 'chatbot' ) }
					</Button>
					<Button
						variant="link"
						isDestructive
						onClick={ () => onDelete( entry ) }
					>
						{ __( 'Delete', 'chatbot' ) }
					</Button>
				</>
			) }
		</div>
	</li>
);

export const PersuasionKit = () => {
	const [ kit, setKit ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ error, setError ] = useState( '' );
	const canEdit = !! ( data.can && data.can.settings );
	const reload = () =>
		core( `kit?module=${ data.module }` )
			.then( setKit )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	useEffect( () => {
		reload();
	}, [] );
	if ( ! kit ) {
		return error ? <Notice status="error">{ error }</Notice> : <Spinner />;
	}
	const saved = ( next ) => {
		setKit( next );
		setEditing( null );
	};
	const remove = ( entry ) => {
		// eslint-disable-next-line no-alert
		const sure = window.confirm(
			__( 'Delete this proof?', 'chatbot' )
		);
		if ( ! sure ) {
			return;
		}
		core( `kit/${ entry.id }?module=${ data.module }`, {
			method: 'DELETE',
		} )
			.then( setKit )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	};
	const byType = Object.keys( kit.types ).filter( ( type ) =>
		kit.entries.some( ( entry ) => entry.type === type )
	);
	return (
		<section className="qcwr-card qcwr-kit">
			<div className="qcwr-kit__head">
				<h2>{ __( 'Persuasion Kit', 'chatbot' ) }</h2>
				{ canEdit && ! editing && (
					<Button
						variant="secondary"
						onClick={ () => setEditing( {} ) }
					>
						{ __( 'Add proof', 'chatbot' ) }
					</Button>
				) }
			</div>
			<p className="qcwr-muted">
				{ __(
					'Your real proof: reviews, customer numbers, awards, guarantees, real offers. AI Writer uses only what is here, and flags any proof it cannot find here.',
					'chatbot'
				) }{ ' ' }
				{ kit.store &&
					__(
						'Prices, sale dates and stock come live from WooCommerce.',
						'chatbot'
					) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ canEdit && ! editing && (
				<ImportPanel
					target="kit"
					types={ kit.types }
					onApplied={ reload }
				/>
			) }
			{ editing && (
				<KitForm
					key={ editing.id || 'new' }
					types={ kit.types }
					entry={ editing }
					onSaved={ saved }
					onCancel={ () => setEditing( null ) }
				/>
			) }
			{ ! kit.entries.length && ! editing && (
				<p>
					{ __(
						'Nothing here yet. Fill it from your content above, or add a review you may quote, your guarantee and the year you started by hand.',
						'chatbot'
					) }
				</p>
			) }
			{ byType.map( ( type ) => (
				<div key={ type } className="qcwr-kit__group">
					<h3>{ kit.types[ type ].label }</h3>
					<ul>
						{ kit.entries
							.filter( ( entry ) => entry.type === type )
							.map( ( entry ) => (
								<KitEntry
									key={ entry.id }
									entry={ entry }
									canEdit={ canEdit }
									onEdit={ setEditing }
									onDelete={ remove }
								/>
							) ) }
					</ul>
				</div>
			) ) }
		</section>
	);
};

/**
 * Free: saved chat sessions as a customer-words source, a labelled Pro
 * preview (host text).
 *
 * @return {Element|null} Preview.
 */
const ChatPreview = () => (
	<ProSpot spot="profile.chats" variant="link" as="li" />
);

export const CustomerWords = () => {
	const [ view, setView ] = useState( null );
	const [ topic, setTopic ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const canEdit = !! ( data.can && data.can.settings );
	const load = useCallback(
		() =>
			core(
				`voc?module=${ data.module }&topic=${ encodeURIComponent(
					topic
				) }`
			)
				.then( ( next ) => {
					setView( next );
					setJobId( next.job_id || 0 );
				} )
				.catch( ( err ) => setError( errorMessage( err ) ) ),
		[ topic ]
	);
	useEffect( () => {
		load();
	}, [ load ] );
	if ( ! view ) {
		return error ? <Notice status="error">{ error }</Notice> : <Spinner />;
	}
	const refresh = ( group ) =>
		core( 'voc/refresh', {
			method: 'POST',
			data: { module: data.module, group },
		} )
			.then( ( body ) => setJobId( body.job_id ) )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	const remove = ( phrase ) =>
		core( `voc/${ phrase.id }?module=${ data.module }`, {
			method: 'DELETE',
		} )
			.then( load )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	const labels = {};
	view.sources.forEach( ( source ) => {
		labels[ source.id ] = source.label;
	} );
	const hasChats = view.sources.some( ( source ) => 'chat' === source.id );
	const estimate = view.estimate || {};
	return (
		<section className="qcwr-card qcwr-voc">
			<h2>{ __( 'Customer words', 'chatbot' ) }</h2>
			<p className="qcwr-muted">
				{ __(
					'The words your customers actually use. AI Writer uses them for headlines and problems (as wording, never as quotes). Names, emails, phone numbers and addresses are removed before anything is saved.',
					'chatbot'
				) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ canEdit && <ImportPanel target="words" onApplied={ load } /> }
			<h3>{ __( 'Read from', 'chatbot' ) }</h3>
			<ul className="qcwr-voc__sources">
				{ view.sources.map( ( source ) => (
					<li key={ source.id }>
						{ source.label }
						<span className="qcwr-muted">
							{ ' ' }
							{ source.last_run
								? sprintf(
										/* translators: %s: date. */
										__( '(read %s)', 'chatbot' ),
										new Date(
											source.last_run
										).toLocaleDateString()
									)
								: __( '(not read yet)', 'chatbot' ) }
						</span>
					</li>
				) ) }
				{ ! hasChats && <ChatPreview /> }
			</ul>
			{ applyFilters( 'qcld_wpbot_writer.profile.voc_sources', null, {
				view,
				reload: load,
			} ) }
			{ jobId ? (
				<JobProgress
					jobId={ jobId }
					onChange={ ( job ) => {
						if (
							job &&
							! [ 'queued', 'running' ].includes( job.status )
						) {
							setJobId( 0 );
							load();
						}
					} }
				/>
			) : (
				data.can.use && (
					<p className="qcwr-actions">
						<Button
							variant="secondary"
							onClick={ () => refresh( false ) }
						>
							{ __(
								'Read customer words (free)',
								'chatbot'
							) }
						</Button>
						<AiOnly>
							<Button
								variant="secondary"
								disabled={ ! estimate.phrases }
								onClick={ () => refresh( true ) }
							>
								{ estimate.phrases
									? sprintf(
											/* translators: 1: number of phrases, 2: cost in US dollars. */
											_n(
												'Group %1$d phrase by topic with AI (about $%2$s)',
												'Group %1$d phrases by topic with AI (about $%2$s)',
												estimate.phrases,
												'chatbot'
											),
											estimate.phrases,
											Number( estimate.usd ).toFixed( 3 )
										)
									: __(
											'Group by topic with AI',
											'chatbot'
										) }
							</Button>
						</AiOnly>
					</p>
				)
			) }
			{ view.topics.some( ( row ) => '' !== row.topic ) && (
				<div className="qcwr-voc__topics">
					<Button
						variant={ '' === topic ? 'primary' : 'secondary' }
						onClick={ () => setTopic( '' ) }
					>
						{ __( 'All', 'chatbot' ) }
					</Button>
					{ view.topics
						.filter( ( row ) => '' !== row.topic )
						.map( ( row ) => (
							<Button
								key={ row.topic }
								variant={
									row.topic === topic
										? 'primary'
										: 'secondary'
								}
								onClick={ () => setTopic( row.topic ) }
							>
								{ row.topic } ({ row.phrases })
							</Button>
						) ) }
				</div>
			) }
			{ ! view.phrases.length && (
				<p>
					{ __(
						'No customer words yet. Click "Read customer words" to read your reviews, your kit and your site search log.',
						'chatbot'
					) }
				</p>
			) }
			<ul className="qcwr-voc__phrases">
				{ view.phrases.map( ( phrase ) => (
					<li key={ phrase.id }>
						<span>{ phrase.phrase }</span>{ ' ' }
						<span className="qcwr-badge">
							{ labels[ phrase.source ] || phrase.source }
						</span>
						{ phrase.weight > 1 && (
							<span className="qcwr-muted">
								{ ' ' }
								{ sprintf(
									/* translators: %d: times seen. */
									__( '×%d', 'chatbot' ),
									phrase.weight
								) }
							</span>
						) }
						{ phrase.topic && (
							<span className="qcwr-muted">
								{ ' ' }
								· { phrase.topic }
							</span>
						) }
						{ canEdit && (
							<Button
								variant="link"
								isDestructive
								onClick={ () => remove( phrase ) }
							>
								{ __( 'Remove', 'chatbot' ) }
							</Button>
						) }
					</li>
				) ) }
			</ul>
		</section>
	);
};

/**
 * Business Profile: sub-tabs Persuasion Kit and Customer words (CR-15: a tab
 * of Brand & Business).
 *
 * @param {Object}                props          Props.
 * @param {string}                props.initial  'kit' or 'words'.
 * @param {(tab: string) => void} props.onChange Sub-tab chosen.
 * @return {Element} Screen.
 */
const ProfileScreen = ( { initial = 'kit', onChange } ) => {
	const [ tab, setTab ] = useState( 'words' === initial ? 'words' : 'kit' );
	const choose = ( next ) => {
		setTab( next );
		if ( onChange ) {
			onChange( next );
		}
	};
	return (
		<div className="qcwr-profile">
			<div
				className="qcwr-actions qcwr-profile__tabs"
				role="group"
				aria-label={ __( 'Business Profile', 'chatbot' ) }
			>
				<Button
					variant={ 'kit' === tab ? 'primary' : 'secondary' }
					onClick={ () => choose( 'kit' ) }
				>
					{ __( 'Persuasion Kit', 'chatbot' ) }
				</Button>
				<Button
					variant={ 'words' === tab ? 'primary' : 'secondary' }
					onClick={ () => choose( 'words' ) }
				>
					{ __( 'Customer words', 'chatbot' ) }
				</Button>
			</div>
			{ 'kit' === tab ? <PersuasionKit /> : <CustomerWords /> }
		</div>
	);
};

export default ProfileScreen;
