<?php
/**
 * Contact rules that do not need the database (unit tested): clean input,
 * the status a new contact gets from its consent, and which status changes
 * are allowed.
 *
 * Consent first (PLAN.md section 3): only "subscribed" contacts receive
 * marketing email. A contact leaves "unsubscribed", "bounced" or "complained"
 * only through the person's own new opt-in (a confirmed sign-up), never
 * through an import, an automation or a merge.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

defined( 'ABSPATH' ) || exit;

/**
 * Contact rules.
 */
final class Rules {

	/**
	 * Every status.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'subscribed', 'pending', 'lead', 'prospect', 'unsubscribed', 'bounced', 'complained' );

	/**
	 * Statuses that receive marketing email.
	 *
	 * @var string[]
	 */
	const MAILABLE = array( 'subscribed' );

	/**
	 * Statuses the person chose or the mail system set; only a new opt-in leaves them.
	 *
	 * @var string[]
	 */
	const OPT_OUTS = array( 'unsubscribed', 'bounced', 'complained' );

	/**
	 * Consent kinds a capture can carry.
	 * - marketing: the person asked to receive emails (sign-up form, "subscribe" in the chat);
	 * - contact: they only left an address to be contacted (support email, ticket, live chat);
	 * - none: imported or found without any consent recorded (Lead Finder prospects).
	 *
	 * @var string[]
	 */
	const CONSENTS = array( 'marketing', 'contact', 'none' );

	/**
	 * Lower-case, trimmed email, or '' when it is not an email.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function email( $email ) {
		$email = strtolower( trim( sanitize_email( (string) $email ) ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Clean contact fields that are present in the input. Unknown keys are dropped.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function clean( array $input ) {
		$out     = array();
		$lengths = array(
			'first_name' => 100,
			'last_name'  => 100,
			'phone'      => 50,
			'company'    => 190,
			'language'   => 20,
			'source'     => 50,
		);
		if ( array_key_exists( 'email', $input ) ) {
			$out['email'] = self::email( (string) $input['email'] );
		}
		foreach ( $lengths as $key => $max ) {
			if ( array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ) {
				$value       = 'source' === $key ? sanitize_key( (string) $input[ $key ] ) : sanitize_text_field( (string) $input[ $key ] );
				$out[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
			}
		}
		if ( array_key_exists( 'phone', $out ) ) {
			$out['phone'] = substr( preg_replace( '/[^0-9+()\-. ]/', '', $out['phone'] ), 0, 50 );
		}
		if ( array_key_exists( 'website', $input ) && is_scalar( $input['website'] ) ) {
			$url            = trim( (string) $input['website'] );
			$out['website'] = preg_match( '#^https?://[^\s]+$#i', $url ) ? substr( $url, 0, 255 ) : '';
		}
		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( in_array( $status, self::STATUSES, true ) ) {
				$out['status'] = $status;
			}
		}
		if ( array_key_exists( 'attributes', $input ) && is_array( $input['attributes'] ) ) {
			$attributes = array();
			foreach ( $input['attributes'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' !== $key && is_scalar( $value ) ) {
					$attributes[ $key ] = sanitize_text_field( (string) $value );
				}
			}
			$out['attributes'] = $attributes;
		}
		return $out;
	}

	/**
	 * Status of a new contact from its consent.
	 *
	 * @param string $consent       marketing, contact or none.
	 * @param bool   $double_opt_in Double opt-in is on.
	 * @param bool   $by_owner      Added by the owner in the admin (they vouch for the consent).
	 * @return string
	 */
	public static function initial_status( $consent, $double_opt_in, $by_owner = false ) {
		if ( 'marketing' === $consent ) {
			return ( $double_opt_in && ! $by_owner ) ? 'pending' : 'subscribed';
		}
		if ( 'none' === $consent ) {
			return 'prospect';
		}
		return 'lead';
	}

	/**
	 * Status after new information about an existing contact.
	 * An opt-out is never undone here unless the person themself opted in again.
	 * A stronger consent upgrades: prospect → lead → pending/subscribed.
	 *
	 * @param string $current       Current status.
	 * @param string $consent       Consent of the new capture (marketing, contact, none).
	 * @param bool   $double_opt_in Double opt-in is on.
	 * @param bool   $own_opt_in    The person opted in themselves just now (form, chat "subscribe", confirmation link).
	 * @return string
	 */
	public static function next_status( $current, $consent, $double_opt_in, $own_opt_in ) {
		if ( in_array( $current, self::OPT_OUTS, true ) ) {
			if ( 'marketing' === $consent && $own_opt_in && 'complained' !== $current ) {
				return 'pending'; // A returning person confirms again before any email.
			}
			return $current;
		}
		$rank = array(
			'prospect'   => 0,
			'lead'       => 1,
			'pending'    => 2,
			'subscribed' => 3,
		);
		$new  = self::initial_status( $consent, $double_opt_in );
		$have = isset( $rank[ $current ] ) ? $rank[ $current ] : 0;
		return ( isset( $rank[ $new ] ) && $rank[ $new ] > $have ) ? $new : $current;
	}

	/**
	 * Whether the owner may set a status by hand. The owner can unsubscribe
	 * anyone, but cannot re-subscribe someone who opted out or complained.
	 *
	 * @param string $current Current status.
	 * @param string $target  Wanted status.
	 * @return bool
	 */
	public static function owner_may_set( $current, $target ) {
		if ( ! in_array( $target, self::STATUSES, true ) ) {
			return false;
		}
		if ( in_array( $current, self::OPT_OUTS, true ) ) {
			return in_array( $target, self::OPT_OUTS, true );
		}
		return true;
	}

	/**
	 * Whether a status receives marketing email.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function mailable( $status ) {
		return in_array( $status, self::MAILABLE, true );
	}

	/**
	 * The status of two records of one person merged (pure; unit tested): an
	 * opt-out always wins (a complaint first), otherwise the stronger consent.
	 *
	 * @param string $a Status.
	 * @param string $b Status.
	 * @return string
	 */
	public static function merge_statuses( $a, $b ) {
		foreach ( array( 'complained', 'unsubscribed', 'bounced', 'subscribed', 'pending', 'lead', 'prospect' ) as $status ) {
			if ( $status === $a || $status === $b ) {
				return $status;
			}
		}
		return (string) $a;
	}

	/**
	 * A Gmail address without dots and +tag in the local part ('' for other domains) (pure; unit tested).
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function canonical_gmail( $email ) {
		$email = strtolower( trim( (string) $email ) );
		$at    = strrpos( $email, '@' );
		if ( false === $at || ! in_array( substr( $email, $at + 1 ), array( 'gmail.com', 'googlemail.com' ), true ) ) {
			return '';
		}
		$local = substr( $email, 0, $at );
		$plus  = strpos( $local, '+' );
		$local = str_replace( '.', '', false === $plus ? $local : substr( $local, 0, $plus ) );
		return '' === $local ? '' : $local . '@gmail.com';
	}

	/**
	 * Paging arguments, bounded.
	 *
	 * @param array $args page, per_page.
	 * @return array{page:int,per_page:int,offset:int}
	 */
	public static function paging( array $args ) {
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 25;
		$per_page = max( 1, min( 200, $per_page ) );
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		);
	}
}
