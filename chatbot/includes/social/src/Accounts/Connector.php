<?php
/**
 * Connects accounts: Meta (Facebook Pages + linked Instagram), Threads,
 * Telegram channels (through the ChatBot's Telegram bot) and Google
 * Business Profile locations (through WPBot Core's Google connection).
 *
 * The chatbot channel add-ons own the Meta and Telegram webhooks
 * (build-brief A3): nothing here registers callbacks, calls setWebhook,
 * changes a Page's subscriptions or revokes the Meta grant.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Accounts;

use QuantumCloud\Social\Support\Log;
use QuantumCloud\Social\Support\Redact;
use QuantumCloud\WPBotCore\Connections\Business_Profile;
use QuantumCloud\WPBotCore\Connections\Connection_Status;
use QuantumCloud\WPBotCore\Connections\Meta;
use QuantumCloud\WPBotCore\Connections\Social_Auth;
use QuantumCloud\WPBotCore\Connections\Social_Http;
use QuantumCloud\WPBotCore\Connections\Threads;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Account connector.
 */
final class Connector {

	/**
	 * Capability that every connect step requires.
	 */
	const CAP = 'wpbot_social_settings';

	/**
	 * Owner key Social puts in the login state, so it only takes its own logins (QA #45).
	 */
	const OWNER = 'social';

	/**
	 * User meta: the Meta login waiting for "Choose Pages".
	 */
	const PENDING = 'qcld_wpbot_social_pending_meta';

	/**
	 * Token_Vault key of Social's own Telegram bot (no ':' so it never
	 * matches an account key).
	 */
	const TELEGRAM_BOT = 'telegram_bot';

	/**
	 * Option with the bot's @username, for screens (not a secret).
	 */
	const TELEGRAM_BOT_NAME = 'qcld_wpbot_social_telegram_bot_name';

	/**
	 * Hook into WPBot Core's social login callback.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/social_authorized', array( __CLASS__, 'authorized' ), 10, 4 );
	}

	/**
	 * Consent URL for Meta or Threads.
	 *
	 * @param string   $app        "meta" or "threads".
	 * @param string[] $choices    Meta choice ids (Meta_Scopes).
	 * @param string   $return_url Where to come back.
	 * @return string|WP_Error
	 */
	public static function start( $app, array $choices, $return_url ) {
		$provider = Social_Auth::provider( $app );
		if ( null === $provider ) {
			return new WP_Error( 'qcld_wpbot_social_app', __( 'This network cannot be connected yet.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'meta' === $app ) {
			$wanted = Meta_Scopes::permissions( $choices );
			add_filter(
				'qcld_wpbot_core/meta_permissions',
				static function ( $permissions ) use ( $wanted ) {
					return array_merge( (array) $permissions, $wanted );
				}
			);
			$scopes = Meta::permissions();
		} else {
			$scopes = array( 'threads_basic', 'threads_content_publish', 'threads_manage_insights' );
		}
		return $provider->auth_url( $app, $scopes, $return_url, self::CAP, self::OWNER );
	}

	/**
	 * Store tokens from a finished login (WPBot Core filter).
	 *
	 * @param true|WP_Error|null $handled Earlier result.
	 * @param string             $app     App id.
	 * @param array              $tokens  Tokens.
	 * @param array              $payload State payload.
	 * @return true|WP_Error|null
	 */
	public static function authorized( $handled, $app, $tokens, $payload ) {
		if ( null !== $handled || ! isset( $payload['owner'] ) || self::OWNER !== $payload['owner'] ) {
			return $handled;
		}
		if ( 'meta' === $app ) {
			return self::meta_authorized( $tokens );
		}
		if ( 'threads' === $app ) {
			return self::threads_authorized( $tokens );
		}
		return $handled;
	}

	/**
	 * Meta login: remember the user token, then the owner picks Pages.
	 *
	 * @param array $tokens Long-lived user tokens.
	 * @return true|WP_Error
	 */
	private static function meta_authorized( array $tokens ) {
		$me = Meta::request( 'GET', '/me', (string) $tokens['access_token'], array( 'fields' => 'id,name' ) );
		if ( is_wp_error( $me ) || empty( $me['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_social_meta_me', __( 'Facebook did not say who signed in. Try again.', 'chatbot' ) );
		}
		$login = 'meta:' . preg_replace( '/[^0-9]/', '', (string) $me['id'] );
		if ( ! Token_Vault::put( $login, (string) wp_json_encode( $tokens ) ) ) {
			return self::no_encryption();
		}
		update_user_meta(
			get_current_user_id(),
			self::PENDING,
			array(
				'login' => $login,
				'name'  => isset( $me['name'] ) ? sanitize_text_field( $me['name'] ) : '',
				'at'    => time(),
			)
		);
		// A new login brings paused accounts of the same person back.
		Account_Store::set_status( $login, 'ok' );
		Connection_Status::set( 'meta', $login, 'ok' );
		return true;
	}

	/**
	 * Threads login: one account per Threads profile.
	 *
	 * @param array $tokens Long-lived tokens.
	 * @return true|WP_Error
	 */
	private static function threads_authorized( array $tokens ) {
		$me = Social_Http::send( 'GET', Threads::HOST . '/v1.0/me?fields=id,username,name', array( 'Authorization' => 'Bearer ' . $tokens['access_token'] ) );
		if ( is_wp_error( $me ) || empty( $me['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_social_threads_me', __( 'Threads did not say who signed in. Try again.', 'chatbot' ) );
		}
		if ( ! Token_Vault::put( 'threads:' . $me['id'], (string) wp_json_encode( $tokens ) ) ) {
			return self::no_encryption();
		}
		$id = Account_Store::upsert(
			array(
				'network'   => 'threads',
				'kind'      => 'profile',
				'remote_id' => (string) $me['id'],
				'name'      => isset( $me['name'] ) && '' !== $me['name'] ? $me['name'] : ( isset( $me['username'] ) ? '@' . $me['username'] : '' ),
				'handle'    => isset( $me['username'] ) ? '@' . $me['username'] : '',
				'login'     => 'threads:' . $me['id'],
			)
		);
		Log::add( 'ok', 'account_connected', sprintf( 'Threads %s connected', isset( $me['username'] ) ? '@' . $me['username'] : $me['id'] ) );
		return $id ? true : new WP_Error( 'qcld_wpbot_social_store', __( 'The account could not be saved.', 'chatbot' ) );
	}

	/**
	 * Error when tokens cannot be encrypted (they are never stored in plain text).
	 *
	 * @return WP_Error
	 */
	private static function no_encryption() {
		return new WP_Error( 'qcld_wpbot_social_no_encryption', __( 'This server cannot encrypt tokens (PHP sodium is missing), so WPBot will not store them. Ask your host to enable the sodium extension.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * The Meta login waiting for Choose Pages, for the current user.
	 *
	 * @return array{login:string, name:string}|null
	 */
	public static function pending() {
		$pending = get_user_meta( get_current_user_id(), self::PENDING, true );
		return is_array( $pending ) && ! empty( $pending['login'] ) && time() - (int) $pending['at'] < DAY_IN_SECONDS ? $pending : null;
	}

	/**
	 * User token of a Meta login.
	 *
	 * @param string $login Login key.
	 * @return string
	 */
	public static function login_token( $login ) {
		$tokens = json_decode( Token_Vault::get( $login ), true );
		return is_array( $tokens ) && isset( $tokens['access_token'] ) ? (string) $tokens['access_token'] : '';
	}

	/**
	 * Pages for the pending login, with what is already connected.
	 *
	 * @return array|WP_Error
	 */
	public static function pages() {
		$pending = self::pending();
		if ( null === $pending ) {
			return new WP_Error( 'qcld_wpbot_social_no_login', __( 'Sign in with Facebook first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$pages = Meta::pages( self::login_token( $pending['login'] ) );
		if ( is_wp_error( $pages ) ) {
			return new WP_Error( 'qcld_wpbot_social_pages', Redact::secrets( $pages->get_error_message() ), array( 'status' => 502 ) );
		}
		$connected = array();
		foreach ( Account_Store::all() as $account ) {
			$connected[ $account['network'] . ':' . $account['remote_id'] ] = $account['brand_id'];
		}
		return array(
			'name'  => $pending['name'],
			'pages' => array_map(
				static function ( $page ) use ( $connected ) {
					return self::page_view( $page, $connected );
				},
				$pages
			),
		);
	}

	/**
	 * A Page as the screen shows it (no token).
	 *
	 * @param array $page      Page from Meta::pages().
	 * @param array $connected Connected keys => brand id.
	 * @return array
	 */
	public static function page_view( array $page, array $connected ) {
		return array(
			'id'          => $page['id'],
			'name'        => $page['name'],
			'can_publish' => self::can_publish( $page['tasks'] ),
			'connected'   => isset( $connected[ 'facebook:' . $page['id'] ] ),
			'instagram'   => $page['instagram'] ? array(
				'id'        => $page['instagram']['id'],
				'username'  => $page['instagram']['username'],
				'connected' => isset( $connected[ 'instagram:' . $page['instagram']['id'] ] ),
			) : null,
		);
	}

	/**
	 * Whether a Page role allows publishing (Meta task CREATE_CONTENT).
	 *
	 * An empty task list means Meta did not report tasks; the publish call
	 * will tell.
	 *
	 * @param string[] $tasks Tasks.
	 * @return bool
	 */
	public static function can_publish( array $tasks ) {
		return array() === $tasks || in_array( 'CREATE_CONTENT', $tasks, true ) || in_array( 'MANAGE', $tasks, true );
	}

	/**
	 * Connect the chosen Pages and Instagram accounts.
	 *
	 * @param array $selection Each: page_id, instagram (bool), brand_id.
	 * @return array{connected:int}|WP_Error
	 */
	public static function save_pages( array $selection ) {
		$pending = self::pending();
		if ( null === $pending ) {
			return new WP_Error( 'qcld_wpbot_social_no_login', __( 'Sign in with Facebook first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$pages = Meta::pages( self::login_token( $pending['login'] ) );
		if ( is_wp_error( $pages ) ) {
			return new WP_Error( 'qcld_wpbot_social_pages', Redact::secrets( $pages->get_error_message() ), array( 'status' => 502 ) );
		}
		$by_id = array();
		foreach ( $pages as $page ) {
			$by_id[ $page['id'] ] = $page;
		}
		$count = 0;
		foreach ( $selection as $choice ) {
			$page_id = isset( $choice['page_id'] ) ? (string) $choice['page_id'] : '';
			if ( ! isset( $by_id[ $page_id ] ) || ! self::can_publish( $by_id[ $page_id ]['tasks'] ) ) {
				continue;
			}
			$page = $by_id[ $page_id ];
			if ( ! Token_Vault::put( 'facebook:' . $page['id'], $page['token'] ) ) {
				return self::no_encryption();
			}
			$brand_id = self::brand( isset( $choice['brand_id'] ) ? $choice['brand_id'] : 0 );
			$fb_id    = Account_Store::upsert(
				array(
					'network'   => 'facebook',
					'kind'      => 'page',
					'remote_id' => $page['id'],
					'name'      => $page['name'],
					'login'     => $pending['login'],
					'brand_id'  => $brand_id,
				)
			);
			++$count;
			if ( ! empty( $choice['instagram'] ) && $page['instagram'] ) {
				Account_Store::upsert(
					array(
						'network'   => 'instagram',
						'kind'      => 'professional',
						'remote_id' => $page['instagram']['id'],
						'name'      => '@' . $page['instagram']['username'],
						'handle'    => '@' . $page['instagram']['username'],
						'login'     => $pending['login'],
						'parent_id' => $fb_id,
						'brand_id'  => $brand_id,
					)
				);
				// Instagram publishing with Facebook Login uses the Page token.
				if ( ! Token_Vault::put( 'instagram:' . $page['instagram']['id'], $page['token'] ) ) {
					return self::no_encryption();
				}
				++$count;
			}
		}
		delete_user_meta( get_current_user_id(), self::PENDING );
		/* translators: %d: number of accounts. */
		Log::add( 'ok', 'account_connected', sprintf( _n( '%d Facebook/Instagram account connected', '%d Facebook/Instagram accounts connected', $count, 'chatbot' ), $count ) );
		return array( 'connected' => $count );
	}

	/**
	 * A brand id the client sent, kept only when the brand exists (and, from
	 * SM4, the user may use it); otherwise 0, "no brand" (QA #46).
	 *
	 * @param mixed $brand_id Client value.
	 * @return int
	 */
	public static function brand( $brand_id ) {
		$brand_id = is_numeric( $brand_id ) ? (int) $brand_id : 0;
		if ( $brand_id <= 0 ) {
			return 0;
		}
		/**
		 * Whether a brand exists and the current user may use it. The Master
		 * module answers; without it there are no brands.
		 *
		 * @param bool $valid    Default false.
		 * @param int  $brand_id Brand id.
		 */
		return apply_filters( 'qcld_wpbot_social/brand_valid', false, $brand_id ) ? $brand_id : 0;
	}

	/**
	 * Add a Telegram channel through the ChatBot's Telegram bot.
	 *
	 * The bot token is read server-side from the Telegram add-on's option;
	 * Social never stores a copy and never changes the bot's webhook.
	 *
	 * @param string $channel @username or numeric chat id.
	 * @return array|WP_Error Account.
	 */
	public static function add_telegram( $channel ) {
		$channel = trim( (string) $channel );
		if ( ! preg_match( '/^(@[A-Za-z0-9_]{5,32}|-?\d{5,20})$/', $channel ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg_channel', __( 'Enter the channel as @username or its numeric id.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$token = self::telegram_token();
		if ( '' === $token ) {
			return new WP_Error( 'qcld_wpbot_social_tg_bot', __( 'Add a bot token first (Connect › Telegram), or set up the ChatBot\'s Telegram bot.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$bot = self::telegram( $token, 'getMe' );
		if ( is_wp_error( $bot ) ) {
			return $bot;
		}
		$chat = self::telegram( $token, 'getChat', array( 'chat_id' => $channel ) );
		if ( is_wp_error( $chat ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg_chat', __( 'Telegram could not find this channel. Check the name, and add the bot to the channel first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$member = self::telegram(
			$token,
			'getChatMember',
			array(
				'chat_id' => $chat['id'],
				'user_id' => $bot['id'],
			)
		);
		$check  = self::telegram_admin_check( is_wp_error( $member ) ? array() : $member );
		$id     = Account_Store::upsert(
			array(
				'network'   => 'telegram',
				'kind'      => 'channel',
				'remote_id' => (string) $chat['id'],
				'name'      => isset( $chat['title'] ) ? $chat['title'] : $channel,
				'handle'    => isset( $chat['username'] ) ? '@' . $chat['username'] : '',
				'login'     => 'telegram:bot',
				'meta'      => array( 'bot' => isset( $bot['username'] ) ? '@' . $bot['username'] : '' ),
			)
		);
		if ( true !== $check ) {
			Account_Store::set_status( '', 'error', $check, array( $id ) );
		}
		Log::add( true === $check ? 'ok' : 'error', 'account_connected', 'Telegram channel ' . ( isset( $chat['title'] ) ? $chat['title'] : $channel ) . ( true === $check ? ' connected' : ': ' . $check ) );
		return Account_Store::get( $id );
	}

	/**
	 * Whether the bot can post in the channel.
	 *
	 * @param array $member getChatMember result.
	 * @return true|string True, or the problem in plain language.
	 */
	public static function telegram_admin_check( array $member ) {
		$status = isset( $member['status'] ) ? (string) $member['status'] : '';
		if ( 'creator' === $status ) {
			return true;
		}
		if ( 'administrator' !== $status ) {
			return __( 'The bot is not an admin of the channel', 'chatbot' );
		}
		if ( isset( $member['can_post_messages'] ) && ! $member['can_post_messages'] ) {
			return __( 'The bot may not post messages in the channel', 'chatbot' );
		}
		return true;
	}

	/**
	 * The bot token Social posts to Telegram with ('' when none): Social's
	 * own bot when the owner saved one, else the ChatBot Telegram add-on's.
	 * Social's own bot wins so an upgrade (WPBot Free to Pro) keeps posting
	 * through the same bot.
	 *
	 * @return string
	 */
	public static function telegram_token() {
		$own = self::own_telegram_token();
		return '' !== $own ? $own : self::chatbot_telegram_token();
	}

	/**
	 * Social's own Telegram bot token ('' when not saved).
	 *
	 * @return string
	 */
	public static function own_telegram_token() {
		return (string) Token_Vault::get( self::TELEGRAM_BOT );
	}

	/**
	 * The ChatBot Telegram add-on's bot token ('' when the add-on is not set up).
	 *
	 * @return string
	 */
	public static function chatbot_telegram_token() {
		if ( function_exists( 'qcld_wpbot_channel_secret' ) ) {
			return (string) qcld_wpbot_channel_secret( 'tg_access_token' );
		}
		return (string) get_option( 'tg_access_token', '' );
	}

	/**
	 * Which bot Social uses, for screens: never the token itself.
	 *
	 * @return array{source:string, mask:string, bot:string, channels:int}
	 */
	public static function telegram_bot_view() {
		if ( '' !== self::own_telegram_token() ) {
			$name = (string) get_option( self::TELEGRAM_BOT_NAME, '' );
			return array(
				'source'   => 'own',
				'mask'     => Token_Vault::mask( self::TELEGRAM_BOT ),
				'bot'      => $name,
				'channels' => count( self::telegram_channels( $name, true ) ),
			);
		}
		return array(
			'source'   => '' !== self::chatbot_telegram_token() ? 'chatbot' : 'none',
			'mask'     => '',
			'bot'      => '',
			'channels' => 0,
		);
	}

	/**
	 * Save Social's own Telegram bot token after checking it with getMe.
	 * Social never calls setWebhook or getUpdates (build-brief A3), so a
	 * ChatBot Telegram bot keeps working.
	 *
	 * @param string $token Bot token from BotFather.
	 * @return array|WP_Error telegram_bot_view() on success.
	 */
	public static function save_telegram_bot( $token ) {
		$token = trim( (string) $token );
		if ( ! preg_match( '/^\d{5,20}:[A-Za-z0-9_-]{30,64}$/', $token ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg_token', __( 'This does not look like a bot token. Copy it from @BotFather (it looks like 123456789:AA…).', 'chatbot' ), array( 'status' => 400 ) );
		}
		$bot = self::telegram( $token, 'getMe' );
		if ( is_wp_error( $bot ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg_token', __( 'Telegram did not accept this bot token.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! Token_Vault::put( self::TELEGRAM_BOT, $token ) ) {
			return new WP_Error( 'qcld_wpbot_social_vault', __( 'The token could not be stored safely on this site.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$name = isset( $bot['username'] ) ? '@' . sanitize_text_field( (string) $bot['username'] ) : '';
		update_option( self::TELEGRAM_BOT_NAME, $name, false );
		// Channels connected through another bot must be reconnected: never post with a bot
		// that may not be an admin there.
		$affected = self::flag_telegram_channels( self::telegram_channels( $name, false ) );
		Log::add( 'ok', 'telegram_bot_saved', 'Social\'s own Telegram bot saved' . ( '' !== $name ? ': ' . $name : '' ) . ( $affected ? sprintf( '; %d channels need reconnecting', $affected ) : '' ) );
		return array_merge( self::telegram_bot_view(), array( 'affected' => $affected ) );
	}

	/**
	 * Remove Social's own Telegram bot token (channels then use the ChatBot's
	 * bot, if any). Nothing changes on Telegram.
	 *
	 * @return array telegram_bot_view().
	 */
	public static function forget_telegram_bot() {
		$name     = (string) get_option( self::TELEGRAM_BOT_NAME, '' );
		$affected = '' !== self::own_telegram_token() ? self::flag_telegram_channels( self::telegram_channels( $name, true ) ) : 0;
		Token_Vault::forget( self::TELEGRAM_BOT );
		delete_option( self::TELEGRAM_BOT_NAME );
		Log::add( 'info', 'telegram_bot_removed', 'Social\'s own Telegram bot removed' . ( $affected ? sprintf( '; %d channels need reconnecting', $affected ) : '' ) );
		return array_merge( self::telegram_bot_view(), array( 'affected' => $affected ) );
	}

	/**
	 * Telegram channel accounts connected through a bot (or through any other bot).
	 *
	 * @param string $bot  Bot @username.
	 * @param bool   $same True: channels of this bot; false: channels of any other bot.
	 * @return int[] Account ids.
	 */
	public static function telegram_channels( $bot, $same ) {
		$ids = array();
		foreach ( Account_Store::all() as $account ) {
			if ( 'telegram' !== $account['network'] ) {
				continue;
			}
			$used = isset( $account['meta']['bot'] ) ? (string) $account['meta']['bot'] : '';
			if ( ( $used === (string) $bot ) === (bool) $same ) {
				$ids[] = (int) $account['id'];
			}
		}
		return $ids;
	}

	/**
	 * Mark channels "needs reconnecting": their targets wait (only accounts with
	 * status ok publish) until the channel is connected again.
	 *
	 * @param int[] $ids Account ids.
	 * @return int How many.
	 */
	private static function flag_telegram_channels( array $ids ) {
		if ( array() !== $ids ) {
			Account_Store::set_status( '', 'error', __( 'Social\'s Telegram bot changed. Connect this channel again (Connect › Telegram) after adding the new bot as an admin.', 'chatbot' ), $ids );
		}
		return count( $ids );
	}

	/**
	 * Call the Telegram Bot API (the token is in the URL path by design;
	 * errors are scrubbed before anyone sees them).
	 *
	 * @param string $token  Bot token.
	 * @param string $method Method.
	 * @param array  $fields Fields.
	 * @return array|WP_Error Result object.
	 */
	public static function telegram( $token, $method, array $fields = array() ) {
		$data = Social_Http::send( 'POST', 'https://api.telegram.org/bot' . $token . '/' . $method, array(), $fields );
		if ( is_wp_error( $data ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg', Redact::secrets( $data->get_error_message() ), array( 'status' => 502 ) );
		}
		if ( empty( $data['ok'] ) || ! isset( $data['result'] ) ) {
			return new WP_Error( 'qcld_wpbot_social_tg', isset( $data['description'] ) ? Redact::secrets( (string) $data['description'] ) : __( 'Telegram refused the request.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return (array) $data['result'];
	}

	/**
	 * Google Business Profile locations from WPBot Core's Google connection.
	 *
	 * @return array|WP_Error
	 */
	public static function google_locations() {
		if ( ! class_exists( Business_Profile::class ) ) {
			return new WP_Error( 'qcld_wpbot_social_gbp', __( 'Google Business Profile is not available in this version.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$locations = Business_Profile::locations();
		if ( is_wp_error( $locations ) ) {
			return new WP_Error( 'qcld_wpbot_social_gbp', $locations->get_error_message(), array( 'status' => 400 ) );
		}
		$out = array();
		foreach ( (array) $locations as $location ) {
			$location = (array) $location;
			if ( empty( $location['name'] ) ) {
				continue;
			}
			$out[] = array(
				'name'    => (string) $location['name'],
				'title'   => isset( $location['title'] ) ? (string) $location['title'] : (string) $location['name'],
				'account' => isset( $location['account'] ) ? (string) $location['account'] : '',
			);
		}
		return $out;
	}

	/**
	 * Connect a Google Business Profile location (no token: Core keeps the Google connection).
	 *
	 * @param string $name    Location resource name.
	 * @param string $title   Location title.
	 * @param string $account Account resource name.
	 * @return array|WP_Error
	 */
	public static function add_google_location( $name, $title, $account ) {
		$name = (string) $name;
		if ( ! preg_match( '#^(accounts/\d+/)?locations/\d+$#', $name ) ) {
			return new WP_Error( 'qcld_wpbot_social_gbp', __( 'Unknown location.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$id = Account_Store::upsert(
			array(
				'network'   => 'google_business',
				'kind'      => 'location',
				'remote_id' => $name,
				'name'      => $title,
				'login'     => 'google',
				'meta'      => array( 'account' => sanitize_text_field( $account ) ),
			)
		);
		Log::add( 'ok', 'account_connected', 'Google Business Profile ' . $title . ' connected' );
		return Account_Store::get( $id );
	}

	/**
	 * Remove every account and token from this site. The Meta app grant and
	 * the chatbot's connections are left alone (build-brief A3).
	 *
	 * @return int Accounts removed.
	 */
	public static function disconnect_all() {
		$logins = array();
		$count  = 0;
		foreach ( Account_Store::all() as $account ) {
			$logins[ $account['login'] ] = true;
			if ( Account_Store::delete( $account['id'] ) ) {
				++$count;
			}
		}
		foreach ( array_keys( $logins ) as $login ) {
			Token_Vault::forget( $login );
			if ( 0 === strpos( $login, 'meta:' ) ) {
				Connection_Status::forget( 'meta', $login );
			}
		}
		Token_Vault::forget( self::TELEGRAM_BOT );
		delete_option( self::TELEGRAM_BOT_NAME );
		delete_user_meta( get_current_user_id(), self::PENDING );
		Log::add( 'info', 'disconnect_all', sprintf( '%d accounts disconnected from this site', $count ) );
		return $count;
	}
}
