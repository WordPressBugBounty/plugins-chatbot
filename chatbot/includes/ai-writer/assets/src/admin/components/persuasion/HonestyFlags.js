/**
 * Honesty flags (round 3, CR-13): invented proof, fake urgency, ended
 * offers, prices that do not match, quotes without permission and
 * engagement bait, each with the words found. They cannot be dismissed;
 * the owner can still use the copy.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * The flags.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.flags Flags: code, label, message, words.
 * @return {Element|null} Notice.
 */
const HonestyFlags = ( { flags } ) =>
	flags && flags.length ? (
		<Notice
			status="warning"
			isDismissible={ false }
			className="qcwr-honesty"
		>
			<strong>{ __( 'Honesty check', 'chatbot' ) }</strong>
			<ul>
				{ flags.map( ( flag ) => (
					<li key={ flag.code }>
						<strong>{ flag.label }:</strong> { flag.message }{ ' ' }
						<span className="qcwr-honesty__words">
							{ ( flag.words || [] )
								.map( ( word ) => `"${ word }"` )
								.join( ', ' ) }
						</span>
					</li>
				) ) }
			</ul>
		</Notice>
	) : null;

export default HonestyFlags;
