/**
 * Topic Finder settings: refresh, main topics, feeds, country, store
 * events, site search log and the weekly email.
 */
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { data, errorMessage, writer } from '../api';
import CompetitorFeeds from './CompetitorFeeds';

const lines = ( list ) => ( list || [] ).join( '\n' );
const toList = ( text ) =>
	text
		.split( '\n' )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

const TopicSettings = () => {
	const saved = data.settings;
	const [ form, setForm ] = useState( {
		topic_refresh: saved.topic_refresh,
		topic_keywords: lines( saved.topic_keywords ),
		rss_feeds: lines( saved.rss_feeds ),
		seasonal_country: saved.seasonal_country,
		store_events: ( saved.store_events || [] )
			.map( ( event ) => `${ event.date } ${ event.title }` )
			.join( '\n' ),
		search_logger: saved.search_logger,
		weekly_email: saved.weekly_email,
		email_recipients: ( saved.email_recipients || [] ).join( ', ' ),
		hide_email_footer: saved.hide_email_footer,
	} );
	const [ message, setMessage ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const set = ( key, value ) => setForm( { ...form, [ key ]: value } );

	const save = () => {
		setBusy( 'save' );
		setMessage( null );
		writer( 'settings', {
			method: 'POST',
			data: {
				settings: {
					topic_refresh: form.topic_refresh,
					topic_keywords: toList( form.topic_keywords ),
					rss_feeds: toList( form.rss_feeds ),
					seasonal_country: form.seasonal_country,
					store_events: toList( form.store_events ).map(
						( line ) => ( {
							date: line.slice( 0, 10 ),
							title: line.slice( 10 ).trim(),
						} )
					),
					search_logger: form.search_logger,
					weekly_email: form.weekly_email,
					email_recipients: form.email_recipients,
					hide_email_footer: form.hide_email_footer,
				},
			},
		} )
			.then( ( next ) => {
				data.settings = next;
				set( 'email_recipients', next.email_recipients.join( ', ' ) );
				setMessage( {
					status: 'success',
					text: __( 'Saved.', 'chatbot' ),
				} );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const test = () => {
		setBusy( 'test' );
		setMessage( null );
		writer( 'topics/email/test', { method: 'POST' } )
			.then( ( result ) =>
				setMessage( {
					status: 'success',
					text: `${ __(
						'Test email sent to',
						'chatbot'
					) } ${ result.to }.`,
				} )
			)
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="qcwr-card" id="qcwr-topic-settings">
			<h2>{ __( 'Topic Finder', 'chatbot' ) }</h2>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<div className="qcwr-grid">
				<SelectControl
					label={ __( 'Find new topics', 'chatbot' ) }
					value={ form.topic_refresh }
					options={ [
						{
							label: __( 'Every week', 'chatbot' ),
							value: 'weekly',
						},
						{
							label: __(
								'Only when I click Refresh',
								'chatbot'
							),
							value: 'manual',
						},
					] }
					onChange={ ( value ) => set( 'topic_refresh', value ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __(
						'Country for holidays (two letters)',
						'chatbot'
					) }
					help={ __(
						'Empty: taken from the site language.',
						'chatbot'
					) }
					value={ form.seasonal_country }
					maxLength={ 2 }
					onChange={ ( value ) =>
						set( 'seasonal_country', value.toUpperCase() )
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<TextareaControl
				label={ __(
					'Main topics of your site (one per line)',
					'chatbot'
				) }
				help={ __(
					'Empty: your biggest categories are used.',
					'chatbot'
				) }
				value={ form.topic_keywords }
				onChange={ ( value ) => set( 'topic_keywords', value ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __(
					'News and competitor feeds (RSS URLs, one per line)',
					'chatbot'
				) }
				help={ __(
					'Only headlines and links are read. Up to 10 feeds.',
					'chatbot'
				) }
				value={ form.rss_feeds }
				onChange={ ( value ) => set( 'rss_feeds', value ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<CompetitorFeeds
				feeds={ toList( form.rss_feeds ) }
				onAdd={ ( added ) =>
					set(
						'rss_feeds',
						lines( [ ...toList( form.rss_feeds ), ...added ] )
					)
				}
			/>
			<TextareaControl
				label={ __(
					'Your store events (one per line: YYYY-MM-DD name)',
					'chatbot'
				) }
				placeholder="2026-11-20 Winter sale"
				value={ form.store_events }
				onChange={ ( value ) => set( 'store_events', value ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Count site searches', 'chatbot' ) }
				help={ __(
					'Counts what visitors search for on your site, per day. No IP address, user or visit is stored.',
					'chatbot'
				) }
				checked={ !! form.search_logger }
				onChange={ ( value ) => set( 'search_logger', value ) }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Weekly topics email', 'chatbot' ) }
				help={ __(
					'Sent only when there are new ideas.',
					'chatbot'
				) }
				checked={ !! form.weekly_email }
				onChange={ ( value ) => set( 'weekly_email', value ) }
				__nextHasNoMarginBottom
			/>
			{ form.weekly_email && (
				<>
					<TextControl
						label={ __(
							'Send to (comma separated)',
							'chatbot'
						) }
						help={ __(
							'Empty: the site admin email.',
							'chatbot'
						) }
						value={ form.email_recipients }
						onChange={ ( value ) =>
							set( 'email_recipients', value )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Hide the line about more sources',
							'chatbot'
						) }
						checked={ !! form.hide_email_footer }
						onChange={ ( value ) =>
							set( 'hide_email_footer', value )
						}
						__nextHasNoMarginBottom
					/>
				</>
			) }
			<div className="qcwr-actions">
				<Button
					variant="primary"
					isBusy={ 'save' === busy }
					disabled={ !! busy }
					onClick={ save }
				>
					{ __( 'Save', 'chatbot' ) }
				</Button>
				<Button
					variant="secondary"
					isBusy={ 'test' === busy }
					disabled={ !! busy }
					onClick={ test }
				>
					{ __( 'Send me a test email', 'chatbot' ) }
				</Button>
			</div>
		</div>
	);
};

export default TopicSettings;
