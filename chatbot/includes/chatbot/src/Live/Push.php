<?php
/**
 * Live chat notifications on any device (live chat LC9): Web Push with the site's own VAPID keys,
 * no outside service and no payload encryption.
 *
 * A push carries no content: the browser's service worker asks the site for the waiting
 * notifications of its own subscription (POST /live/push/pending with its endpoint) and shows them.
 * Endpoints are long random URLs only the browser and its push service know.
 *
 * Who is notified (in the background, like the agent channels):
 * - agents with a device switched on: a new chat (their departments; everyone without departments),
 *   a visitor message in their chat (or in a waiting chat nobody took, at most once a minute),
 *   a chat transferred to them. Not while their Inbox is open and visible (it shows the chat itself);
 * - visitors who asked for it in the chat window: an agent's reply while they are away from the page;
 * - visitors with an email address who left the page: one email with the reply, two minutes after
 *   it, when they have not come back (at most one every 30 minutes per chat).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Web Push and reply emails.
 */
final class Push {

	/**
	 * Option: the site's VAPID keys { public (base64url), private (PEM) }. Not autoloaded.
	 */
	const KEYS = 'qcld_wpbot_chat_live_vapid';

	/**
	 * User meta: an agent's devices, md5( endpoint ) => subscription.
	 */
	const META = 'qcld_wpbot_live_push';

	/**
	 * Option: visitors' devices, conversation id => [ md5( endpoint ) => subscription ]. Not autoloaded.
	 */
	const VISITORS = 'qcld_wpbot_chat_live_push_visitors';

	/**
	 * Background job (one event).
	 */
	const HOOK = 'qcld_wpbot_chat_live_push';

	/**
	 * Background job (reply email for one conversation).
	 */
	const EMAIL_HOOK = 'qcld_wpbot_chat_live_reply_email';

	/**
	 * Devices kept per agent.
	 */
	const MAX_DEVICES = 10;

	/**
	 * Conversations kept with visitor devices.
	 */
	const MAX_VISITORS = 500;

	/**
	 * Seconds after which a visitor who has not asked for news counts as away.
	 */
	const AWAY_AFTER = 25;

	/**
	 * Transport for tests: fn( string $url, array $args ) => array|WP_Error. Null: wp_remote_post().
	 *
	 * @var callable|null
	 */
	public static $transport = null;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/live_event', array( __CLASS__, 'on_event' ), 20, 4 );
		add_action( self::HOOK, array( __CLASS__, 'dispatch' ) );
		add_action( self::EMAIL_HOOK, array( __CLASS__, 'reply_email' ) );
		add_action( 'init', array( __CLASS__, 'serve_worker' ), 1 );
	}

	/**
	 * Whether this server can sign pushes (OpenSSL with elliptic curves).
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_pkey_new' ) && function_exists( 'openssl_sign' ) && defined( 'OPENSSL_KEYTYPE_EC' ) && '' !== self::public_key();
	}

	/**
	 * Visitor notifications switched on (WPBot Pro's setting; default on).
	 *
	 * @return bool
	 */
	public static function visitors_on() {
		/**
		 * Whether visitors may ask for a browser notification when an agent replies.
		 *
		 * @param bool $on Default true.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_visitor_push', true );
	}

	/**
	 * The VAPID keys, created on first use.
	 *
	 * @return array { public, private } (empty strings when OpenSSL can't make them).
	 */
	public static function keys() {
		$keys = get_option( self::KEYS, array() );
		if ( is_array( $keys ) && ! empty( $keys['public'] ) && ! empty( $keys['private'] ) ) {
			return $keys;
		}
		$empty = array(
			'public'  => '',
			'private' => '',
		);
		if ( ! function_exists( 'openssl_pkey_new' ) || ! defined( 'OPENSSL_KEYTYPE_EC' ) ) {
			return $empty;
		}
		$key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		if ( ! $key || ! openssl_pkey_export( $key, $pem ) ) {
			return $empty;
		}
		$details = openssl_pkey_get_details( $key );
		if ( empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) ) {
			return $empty;
		}
		$keys = array(
			'public'  => self::b64url( "\x04" . str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT ) ),
			'private' => (string) $pem,
		);
		update_option( self::KEYS, $keys, false );
		return $keys;
	}

	/**
	 * The public key browsers subscribe with (base64url).
	 *
	 * @return string
	 */
	public static function public_key() {
		return (string) self::keys()['public'];
	}

	/**
	 * A subscription from the browser, checked: an https endpoint and its keys.
	 *
	 * @param mixed $sub PushSubscription.toJSON().
	 * @return array|null { endpoint, p256dh, auth, created }
	 */
	public static function clean( $sub ) {
		$sub      = is_array( $sub ) ? $sub : array();
		$endpoint = isset( $sub['endpoint'] ) ? trim( (string) $sub['endpoint'] ) : '';
		if ( 0 !== strpos( $endpoint, 'https://' ) || strlen( $endpoint ) > 1000 || preg_match( '/\s/', $endpoint ) ) {
			return null;
		}
		$keys = isset( $sub['keys'] ) && is_array( $sub['keys'] ) ? $sub['keys'] : array();
		$b64  = static function ( $value ) {
			$value = (string) $value;
			return preg_match( '/^[A-Za-z0-9_\-=]{1,200}$/', $value ) ? $value : '';
		};
		return array(
			'endpoint' => $endpoint,
			'p256dh'   => $b64( isset( $keys['p256dh'] ) ? $keys['p256dh'] : '' ),
			'auth'     => $b64( isset( $keys['auth'] ) ? $keys['auth'] : '' ),
			'created'  => time(),
		);
	}

	/**
	 * Switch notifications on for an agent's device.
	 *
	 * @param int   $user_id User.
	 * @param mixed $sub     Subscription.
	 * @return bool
	 */
	public static function subscribe_agent( $user_id, $sub ) {
		$sub = self::clean( $sub );
		if ( ! $sub || ! Agents::is_agent( (int) $user_id ) ) {
			return false;
		}
		$devices                            = self::agent_devices( (int) $user_id );
		$devices[ md5( $sub['endpoint'] ) ] = $sub;
		uasort(
			$devices,
			static function ( $a, $b ) {
				return (int) $b['created'] - (int) $a['created'];
			}
		);
		update_user_meta( (int) $user_id, self::META, array_slice( $devices, 0, self::MAX_DEVICES, true ) );
		return true;
	}

	/**
	 * Switch notifications off for an agent's device.
	 *
	 * @param int    $user_id  User.
	 * @param string $endpoint Endpoint.
	 * @return void
	 */
	public static function unsubscribe_agent( $user_id, $endpoint ) {
		$devices = self::agent_devices( (int) $user_id );
		unset( $devices[ md5( (string) $endpoint ) ] );
		if ( $devices ) {
			update_user_meta( (int) $user_id, self::META, $devices );
		} else {
			delete_user_meta( (int) $user_id, self::META );
		}
	}

	/**
	 * An agent's devices.
	 *
	 * @param int $user_id User.
	 * @return array[] md5( endpoint ) => subscription.
	 */
	public static function agent_devices( $user_id ) {
		$devices = get_user_meta( (int) $user_id, self::META, true );
		return is_array( $devices ) ? $devices : array();
	}

	/**
	 * A visitor asks to hear about replies in this chat.
	 *
	 * @param int   $conversation_id Conversation.
	 * @param mixed $sub             Subscription.
	 * @return bool
	 */
	public static function subscribe_visitor( $conversation_id, $sub ) {
		$sub = self::clean( $sub );
		if ( ! $sub || ! self::visitors_on() ) {
			return false;
		}
		$all = get_option( self::VISITORS, array() );
		$all = is_array( $all ) ? $all : array();
		$id  = (int) $conversation_id;
		unset( $all[ $id ] );
		$all[ $id ] = array( md5( $sub['endpoint'] ) => $sub );
		if ( count( $all ) > self::MAX_VISITORS ) {
			$all = array_slice( $all, -self::MAX_VISITORS, null, true );
		}
		update_option( self::VISITORS, $all, false );
		return true;
	}

	/**
	 * A conversation's visitor devices.
	 *
	 * @param int $conversation_id Conversation.
	 * @return array[]
	 */
	public static function visitor_devices( $conversation_id ) {
		$all = get_option( self::VISITORS, array() );
		return is_array( $all ) && isset( $all[ (int) $conversation_id ] ) ? (array) $all[ (int) $conversation_id ] : array();
	}

	/**
	 * Forget a visitor device (gone at the push service).
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $hash            md5( endpoint ).
	 * @return void
	 */
	private static function forget_visitor( $conversation_id, $hash ) {
		$all = get_option( self::VISITORS, array() );
		if ( is_array( $all ) && isset( $all[ (int) $conversation_id ][ $hash ] ) ) {
			unset( $all[ (int) $conversation_id ][ $hash ] );
			if ( ! $all[ (int) $conversation_id ] ) {
				unset( $all[ (int) $conversation_id ] );
			}
			update_option( self::VISITORS, $all, false );
		}
	}

	/**
	 * The visitor asked the site for news (events, message, typing): remember when.
	 *
	 * @param int $conversation_id Conversation.
	 * @return void
	 */
	public static function visitor_seen( $conversation_id ) {
		set_transient( 'qcld_wpbot_chat_live_seen_' . (int) $conversation_id, time(), HOUR_IN_SECONDS );
	}

	/**
	 * Whether the visitor is on the page now.
	 *
	 * @param int $conversation_id Conversation.
	 * @return bool
	 */
	public static function visitor_here( $conversation_id ) {
		$seen = (int) get_transient( 'qcld_wpbot_chat_live_seen_' . (int) $conversation_id );
		return $seen > 0 && time() - $seen < self::AWAY_AFTER;
	}

	/**
	 * The agent's Inbox is open and visible (it shows new chats itself): from the agent's sync.
	 *
	 * @param int  $user_id User.
	 * @param bool $visible Visible now.
	 * @return void
	 */
	public static function agent_seen( $user_id, $visible ) {
		$key = 'qcld_wpbot_chat_live_active_' . (int) $user_id;
		if ( $visible ) {
			set_transient( $key, time(), 30 );
		} else {
			delete_transient( $key );
		}
	}

	/**
	 * Whether an agent is looking at the Inbox now.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function agent_here( $user_id ) {
		$seen = (int) get_transient( 'qcld_wpbot_chat_live_active_' . (int) $user_id );
		return $seen > 0 && time() - $seen < 12;
	}

	/**
	 * A live chat event: notify in the background (and plan the reply email).
	 *
	 * @param int    $event_id        Event.
	 * @param int    $conversation_id Conversation.
	 * @param string $type            Type.
	 * @param array  $payload         Payload.
	 * @return void
	 */
	public static function on_event( $event_id, $conversation_id, $type, $payload ) {
		$role = is_array( $payload ) && isset( $payload['role'] ) ? (string) $payload['role'] : '';
		if ( ! in_array( $type, array( 'queued', 'message', 'transferred' ), true ) || ( 'message' === $type && ! in_array( $role, array( 'user', 'agent' ), true ) ) ) {
			return;
		}
		if ( 'message' === $type && 'agent' === $role && self::emails_on() ) {
			// Same arguments within ten minutes are one job: one email for a burst of replies.
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, self::EMAIL_HOOK, array( (int) $conversation_id ) );
		}
		if ( ! self::available() ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( (int) $event_id ), 'wpbot-live' );
			return;
		}
		wp_schedule_single_event( time(), self::HOOK, array( (int) $event_id ) );
	}

	/**
	 * Who to notify about an event, and what: [ [ 'agent'|'visitor', id, note ] ].
	 *
	 * @param array $event Event (Events::get()).
	 * @param array $conv  Conversation.
	 * @return array[]
	 */
	public static function plan( array $event, array $conv ) {
		$type    = (string) $event['type'];
		$payload = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : array();
		$name    = '' !== (string) $conv['visitor_name'] ? (string) $conv['visitor_name'] : __( 'A visitor', 'chatbot' );
		$id      = (int) $conv['id'];
		$out     = array();
		$inbox   = self::agent_url( $id );
		if ( 'queued' === $type ) {
			$note = array(
				/* translators: %s: visitor name */
				'title' => sprintf( __( 'New live chat: %s', 'chatbot' ), $name ),
				'body'  => '' !== (string) $conv['page_title'] ? (string) $conv['page_title'] : __( 'A visitor is waiting for a person.', 'chatbot' ),
				'url'   => $inbox,
				'tag'   => 'qcld-live-' . $id,
			);
			foreach ( self::eligible_agents( $conv ) as $user ) {
				$out[] = array( 'agent', $user, $note );
			}
			return $out;
		}
		if ( 'transferred' === $type && ! empty( $payload['agent_id'] ) ) {
			$out[] = array(
				'agent',
				(int) $payload['agent_id'],
				array(
					/* translators: %s: visitor name */
					'title' => sprintf( __( 'Chat transferred to you: %s', 'chatbot' ), $name ),
					'body'  => (string) $conv['page_title'],
					'url'   => $inbox,
					'tag'   => 'qcld-live-' . $id,
				),
			);
			return $out;
		}
		if ( 'message' !== $type ) {
			return $out;
		}
		$role = isset( $payload['role'] ) ? (string) $payload['role'] : '';
		$text = wp_html_excerpt( wp_strip_all_tags( isset( $payload['text'] ) ? (string) $payload['text'] : '' ), 140, '…' );
		if ( 'user' === $role ) {
			$note = array(
				'title' => $name,
				'body'  => $text,
				'url'   => $inbox,
				'tag'   => 'qcld-live-' . $id,
			);
			if ( (int) $conv['agent_id'] > 0 ) {
				$out[] = array( 'agent', (int) $conv['agent_id'], $note );
			} elseif ( 'queued' === (string) $conv['status'] && ! get_transient( 'qcld_wpbot_chat_live_nudge_' . $id ) ) {
				set_transient( 'qcld_wpbot_chat_live_nudge_' . $id, 1, MINUTE_IN_SECONDS );
				foreach ( self::eligible_agents( $conv ) as $user ) {
					$out[] = array( 'agent', $user, $note );
				}
			}
			return $out;
		}
		if ( 'agent' === $role && self::visitors_on() && ! self::visitor_here( $id ) ) {
			$agent = isset( $payload['agent_name'] ) && '' !== (string) $payload['agent_name'] ? (string) $payload['agent_name'] : get_bloginfo( 'name' );
			$out[] = array(
				'visitor',
				$id,
				array(
					/* translators: %s: agent or site name */
					'title' => sprintf( __( '%s replied', 'chatbot' ), $agent ),
					'body'  => $text,
					'url'   => '' !== (string) $conv['page_url'] ? (string) $conv['page_url'] : home_url( '/' ),
					'tag'   => 'qcld-live-reply-' . $id,
				),
			);
		}
		return $out;
	}

	/**
	 * Agents to tell about a new or waiting chat: with a device on, answering its department, not
	 * looking at the Inbox now.
	 *
	 * @param array $conv Conversation.
	 * @return int[]
	 */
	public static function eligible_agents( array $conv ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only users with a device switched on (a few).
		$users = get_users(
			array(
				'meta_key' => self::META,
				'fields'   => 'ID',
				'number'   => 200,
			)
		);
		// phpcs:enable
		$out = array();
		foreach ( (array) $users as $user ) {
			$user = (int) $user;
			if ( ! Agents::is_agent( $user ) || self::agent_here( $user ) ) {
				continue;
			}
			$departments = Agents::departments( $user );
			if ( $departments && '' !== (string) $conv['department'] && ! in_array( (string) $conv['department'], $departments, true ) ) {
				continue;
			}
			$out[] = $user;
		}
		return $out;
	}

	/**
	 * Where a notification takes an agent.
	 *
	 * @param int $conversation_id Conversation.
	 * @return string
	 */
	public static function agent_url( $conversation_id ) {
		/**
		 * The page an agent's notification opens (WPBot Pro: its Inbox or the phone app).
		 *
		 * @param string $url             Default the admin.
		 * @param int    $conversation_id Conversation.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/live_push_agent_url', admin_url(), (int) $conversation_id );
	}

	/**
	 * The background job: send the event's notifications.
	 *
	 * @param int $event_id Event.
	 * @return int Pushes sent.
	 */
	public static function dispatch( $event_id ) {
		$event = Events::get( (int) $event_id );
		$conv  = $event ? Conversations::get( (int) $event['conversation'] ) : null;
		if ( ! $event || ! $conv ) {
			return 0;
		}
		$sent = 0;
		foreach ( self::plan( $event, $conv ) as $item ) {
			list( $who, $id, $note ) = $item;
			$devices                 = 'agent' === $who ? self::agent_devices( $id ) : self::visitor_devices( $id );
			foreach ( $devices as $hash => $device ) {
				$code = self::send( $device, $note );
				if ( in_array( $code, array( 404, 410 ), true ) ) {
					if ( 'agent' === $who ) {
						self::unsubscribe_agent( $id, $device['endpoint'] );
					} else {
						self::forget_visitor( $id, $hash );
					}
				} elseif ( $code >= 200 && $code < 300 ) {
					++$sent;
				}
			}
		}
		return $sent;
	}

	/**
	 * Queue a note for a device and wake it.
	 *
	 * @param array $device Subscription.
	 * @param array $note   { title, body, url, tag }.
	 * @return int HTTP status (0 on a transport error).
	 */
	public static function send( array $device, array $note ) {
		$key   = 'qcld_wpbot_chat_live_note_' . md5( (string) $device['endpoint'] );
		$queue = get_transient( $key );
		$queue = is_array( $queue ) ? $queue : array();
		array_unshift( $queue, $note );
		set_transient( $key, array_slice( $queue, 0, 5 ), 30 * MINUTE_IN_SECONDS );
		$jwt = self::jwt( (string) $device['endpoint'] );
		if ( '' === $jwt ) {
			return 0;
		}
		$args = array(
			'timeout' => 8,
			'headers' => array(
				'TTL'            => '1800',
				'Urgency'        => 'high',
				'Content-Length' => '0',
				'Authorization'  => 'vapid t=' . $jwt . ', k=' . self::public_key(),
			),
			'body'    => '',
		);
		$res  = self::$transport ? call_user_func( self::$transport, (string) $device['endpoint'], $args ) : wp_remote_post( (string) $device['endpoint'], $args );
		return is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * The notes waiting for a device (its service worker asks after a push).
	 *
	 * @param string $endpoint Endpoint.
	 * @return array[]
	 */
	public static function pending( $endpoint ) {
		$key   = 'qcld_wpbot_chat_live_note_' . md5( (string) $endpoint );
		$queue = get_transient( $key );
		delete_transient( $key );
		return is_array( $queue ) ? array_values( $queue ) : array();
	}

	/**
	 * VAPID token for a push service (ES256, valid 12 hours).
	 *
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	public static function jwt( $endpoint ) {
		$keys  = self::keys();
		$parts = wp_parse_url( $endpoint );
		if ( '' === $keys['private'] || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$home    = home_url( '/' );
		$subject = 0 === strpos( $home, 'https://' ) ? untrailingslashit( $home ) : 'mailto:push@' . (string) wp_parse_url( $home, PHP_URL_HOST );
		$header  = self::b64url( (string) wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$claims  = self::b64url(
			(string) wp_json_encode(
				array(
					'aud' => $parts['scheme'] . '://' . $parts['host'],
					'exp' => time() + 12 * HOUR_IN_SECONDS,
					'sub' => $subject,
				)
			)
		);
		$input   = $header . '.' . $claims;
		if ( ! openssl_sign( $input, $der, $keys['private'], OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}
		$raw = self::der_to_raw( $der );
		return '' === $raw ? '' : $input . '.' . self::b64url( $raw );
	}

	/**
	 * An ECDSA signature from DER to the 64 bytes JWT wants (r || s).
	 *
	 * @param string $der DER.
	 * @return string
	 */
	public static function der_to_raw( $der ) {
		$pos = 2;
		if ( ord( $der[1] ) & 0x80 ) {
			$pos += ord( $der[1] ) & 0x7f;
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			if ( ! isset( $der[ $pos ] ) || "\x02" !== $der[ $pos ] ) {
				return '';
			}
			$len  = ord( $der[ $pos + 1 ] );
			$int  = ltrim( substr( $der, $pos + 2, $len ), "\0" );
			$out .= str_pad( $int, 32, "\0", STR_PAD_LEFT );
			$pos += 2 + $len;
		}
		return 64 === strlen( $out ) ? $out : '';
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $data Bytes.
	 * @return string
	 */
	public static function b64url( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Web Push keys.
	}

	/**
	 * The service worker's code: on a push, fetch this device's notes and show them; a click opens
	 * the page (or focuses it when already open).
	 *
	 * @param string $pending_url REST URL of /live/push/pending.
	 * @param string $fallback    Title when nothing is waiting (the browser must show something).
	 * @return string
	 */
	public static function worker_script( $pending_url, $fallback ) {
		return 'var P=' . wp_json_encode( esc_url_raw( $pending_url ) ) . ',F=' . wp_json_encode( (string) $fallback ) . ';'
			. "self.addEventListener('install',function(){self.skipWaiting();});"
			. "self.addEventListener('activate',function(e){e.waitUntil(self.clients.claim());});"
			. "self.addEventListener('push',function(e){e.waitUntil(self.registration.pushManager.getSubscription().then(function(s){"
			. "if(!s){return [];}return fetch(P,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({endpoint:s.endpoint}),credentials:'omit'}).then(function(r){return r.ok?r.json():{notes:[]};}).then(function(j){return j.notes||[];}).catch(function(){return [];});"
			. "}).then(function(n){if(!n.length){n=[{title:F,body:'',url:self.registration.scope,tag:'qcld-live'}];}"
			. 'return Promise.all(n.map(function(x){return self.registration.showNotification(x.title,{body:x.body,tag:x.tag,renotify:true,data:{url:x.url}});}));}));});'
			. "self.addEventListener('notificationclick',function(e){e.notification.close();var u=(e.notification.data&&e.notification.data.url)||'/';"
			. "e.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(function(c){for(var i=0;i<c.length;i++){if(c[i].url===u&&'focus' in c[i]){return c[i].focus();}}return self.clients.openWindow(u);}));});";
	}

	/**
	 * The visitors' service worker (/?qcld_wpbot_live_sw=1, registered on a scope no page uses, so
	 * it never takes over a site's own service worker).
	 *
	 * @return void
	 */
	public static function serve_worker() {
		if ( ! isset( $_GET['qcld_wpbot_live_sw'] ) || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public script.
			return;
		}
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		echo self::worker_script( rest_url( Rest::NS . '/live/push/pending' ), __( 'New reply in your chat', 'chatbot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript, values JSON-encoded.
		exit;
	}

	/**
	 * Reply emails switched on (WPBot Pro's setting; default on).
	 *
	 * @return bool
	 */
	public static function emails_on() {
		/**
		 * Whether a visitor who left gets the agent's reply by email.
		 *
		 * @param bool $on Default true.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_reply_email', true );
	}

	/**
	 * Two minutes after an agent's reply: email it to a visitor who left and gave an email address.
	 *
	 * @param int $conversation_id Conversation.
	 * @return bool Sent.
	 */
	public static function reply_email( $conversation_id ) {
		$conv = Conversations::get( (int) $conversation_id );
		if ( ! $conv || ! self::emails_on() || ! is_email( (string) $conv['visitor_email'] ) || self::visitor_here( (int) $conv['id'] ) ) {
			return false;
		}
		$throttle = 'qcld_wpbot_chat_live_mailed_' . (int) $conv['id'];
		if ( get_transient( $throttle ) ) {
			return false;
		}
		$replies = array();
		foreach ( array_reverse( Conversations::transcript( $conv ) ) as $message ) {
			if ( 'agent' !== $message['role'] ) {
				if ( $replies ) {
					break;
				}
				continue;
			}
			array_unshift( $replies, wp_strip_all_tags( (string) $message['text'] ) );
		}
		if ( ! $replies ) {
			return false;
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$link = '' !== (string) $conv['page_url'] ? (string) $conv['page_url'] : home_url( '/' );
		/* translators: %s: site name */
		$subject = sprintf( __( 'New reply from %s', 'chatbot' ), $site );
		$body    = implode( "\n\n", $replies ) . "\n\n"
			/* translators: %s: page address */
			. sprintf( __( 'Continue the chat: %s', 'chatbot' ), $link );
		set_transient( $throttle, 1, 30 * MINUTE_IN_SECONDS );
		return (bool) wp_mail( (string) $conv['visitor_email'], $subject, $body );
	}
}
