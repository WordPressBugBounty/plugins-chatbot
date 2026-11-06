/**
 * "Use my competitors" and "Suggest competitors" under Topic Finder's feed
 * field (CR-6 W3). Feeds found on the shared competitor list are shown
 * ticked; the owner unticks any, then adds them to the field (and saves the
 * settings as usual). Suggestions use AI credits, so they run only on a
 * click; ticked suggestions are also added to the shared competitor list.
 * Sites without a feed are listed as "no feed found". Nothing is added
 * without a click. Shown only when WPBot Core has the shared list.
 */
import { Button, CheckboxControl, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { errorMessage, writer } from '../api';

const CompetitorFeeds = ( { feeds, onAdd } ) => {
	const [ state, setState ] = useState( null );
	const [ picks, setPicks ] = useState( [] );
	const [ dropped, setDropped ] = useState( [] );
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( null );

	useEffect( () => {
		writer( 'topics/competitors' )
			.then( setState )
			.catch( () => setState( { available: false } ) );
	}, [] );

	if ( ! state || ! state.available ) {
		return null;
	}

	const already = ( feed ) => feeds.includes( feed );
	const merge = ( items, kind ) =>
		setPicks( ( current ) => {
			const known = current.map( ( item ) => item.domain );
			return [
				...current,
				...items
					.filter( ( item ) => ! known.includes( item.domain ) )
					.map( ( item ) => ( {
						...item,
						kind,
						checked: !! item.feed && ! already( item.feed ),
						// A suggested site can be added to the list even without a feed.
						add: 'suggested' === kind,
					} ) ),
			];
		} );

	const useMine = () => {
		setBusy( 'mine' );
		setMessage( null );
		writer( 'topics/competitors' )
			.then( ( result ) => {
				setState( result );
				if ( ! result.list.length ) {
					setMessage( {
						status: 'info',
						text: __(
							'Your competitor list is empty. Add competitors in SEO & AI Search or Competitor Watch, or ask for suggestions.',
							'chatbot'
						),
					} );
				}
				merge( result.list, 'shared' );
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const suggest = () => {
		setBusy( 'suggest' );
		setMessage( null );
		writer( 'topics/competitors/suggest', { method: 'POST' } )
			.then( ( result ) => {
				merge( result.suggestions || [], 'suggested' );
				setDropped( result.dropped || [] );
				if ( ! ( result.suggestions || [] ).length ) {
					setMessage( {
						status: 'info',
						text: __(
							'No new competitors were found.',
							'chatbot'
						),
					} );
				}
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	const toggle = ( domain, checked ) =>
		setPicks(
			picks.map( ( item ) =>
				item.domain === domain ? { ...item, checked } : item
			)
		);

	const ticked = picks.filter( ( item ) => item.checked );
	const pickHelp = ( item ) => {
		if ( 'suggested' === item.kind ) {
			return item.reason;
		}
		return already( item.feed )
			? __( 'Already in your feeds.', 'chatbot' )
			: '';
	};

	const add = () => {
		const newFeeds = ticked
			.map( ( item ) => item.feed )
			.filter( ( feed ) => feed && ! already( feed ) );
		const toList = ticked.filter( ( item ) => 'suggested' === item.kind );
		const done = () => {
			onAdd( newFeeds );
			setPicks(
				picks.filter( ( item ) => ! item.checked || ! item.feed )
			);
			setMessage( {
				status: 'success',
				text: sprintf(
					/* translators: %d: number of feeds. */
					_n(
						'%d feed added. Save the settings to keep it.',
						'%d feeds added. Save the settings to keep them.',
						newFeeds.length,
						'chatbot'
					),
					newFeeds.length
				),
			} );
		};
		if ( ! toList.length ) {
			done();
			return;
		}
		setBusy( 'add' );
		writer( 'topics/competitors/add', {
			method: 'POST',
			data: {
				items: toList.map( ( item ) => ( {
					domain: item.domain,
					reason: item.reason || '',
					source: item.source || 'ai',
				} ) ),
			},
		} )
			.then( ( result ) => {
				setState( { ...state, list: result.list } );
				done();
			} )
			.catch( ( err ) =>
				setMessage( { status: 'error', text: errorMessage( err ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="qcwr-competitor-feeds">
			<div className="qcwr-actions">
				<Button
					variant="secondary"
					isBusy={ 'mine' === busy }
					disabled={ !! busy }
					onClick={ useMine }
				>
					{ __( 'Use my competitors', 'chatbot' ) }
				</Button>
				{ state.can_suggest && (
					<Button
						variant="secondary"
						isBusy={ 'suggest' === busy }
						disabled={ !! busy }
						onClick={ suggest }
					>
						{ __( 'Suggest competitors', 'chatbot' ) }
					</Button>
				) }
				{ state.can_suggest && (
					<span className="qcwr-muted">
						{ __(
							'Suggestions use your AI provider (a few cents).',
							'chatbot'
						) }
					</span>
				) }
			</div>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ picks.length > 0 && (
				<>
					<ul className="qcwr-competitor-feeds__list">
						{ picks.map( ( item ) => (
							<li key={ item.domain }>
								{ item.feed || item.add ? (
									<CheckboxControl
										label={
											item.feed
												? `${ item.domain } — ${ item.feed }`
												: sprintf(
														/* translators: %s: website. */
														__(
															'%s — no feed found (added to your competitor list only)',
															'chatbot'
														),
														item.domain
													)
										}
										help={ pickHelp( item ) }
										checked={ item.checked }
										disabled={
											!! item.feed &&
											already( item.feed ) &&
											'shared' === item.kind
										}
										onChange={ ( value ) =>
											toggle( item.domain, value )
										}
										__nextHasNoMarginBottom
									/>
								) : (
									<span className="qcwr-muted">
										{ sprintf(
											/* translators: %s: website. */
											__(
												'%s — no feed found',
												'chatbot'
											),
											item.domain
										) }
									</span>
								) }
							</li>
						) ) }
					</ul>
					<Button
						variant="primary"
						isBusy={ 'add' === busy }
						disabled={ ! ticked.length || !! busy }
						onClick={ add }
					>
						{ __( 'Add ticked', 'chatbot' ) }
					</Button>
				</>
			) }
			{ dropped.length > 0 && (
				<details className="qcwr-muted">
					<summary>
						{ sprintf(
							/* translators: %d: number of sites. */
							_n(
								'%d site was left out',
								'%d sites were left out',
								dropped.length,
								'chatbot'
							),
							dropped.length
						) }
					</summary>
					<ul>
						{ dropped.map( ( item ) => (
							<li key={ item.domain }>
								{ item.domain }: { item.why }
							</li>
						) ) }
					</ul>
				</details>
			) }
		</div>
	);
};

export default CompetitorFeeds;
