<?php
/**
 * AI Settings (round R2): one screen for the chatbot's AI, in the ChatBot design, with its own
 * left menu: Providers, Answers, Knowledge Base, Limits and cost. Hosts show it on their AI
 * Settings page (`admin.php?page=wpbot_openAi`) by calling render(), print their header on
 * qcld_wpbot_chat/ai_settings_header, the AI Wizard on qcld_wpbot_chat/ai_settings_wizard and the
 * knowledge base screens on qcld_wpbot_chat/ai_settings_knowledge.
 *
 * Every value is stored where WPBot stores it today (each provider's key, model and on/off
 * options; the rate-limit options; …), so nothing on an existing site moves. API keys are only
 * ever shown as their last 4 characters; posting the mask back keeps the saved key.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Ai\Ai_Settings;
use QuantumCloud\Chatbot\Ai\Answer_Scope;
use QuantumCloud\Chatbot\Ai\Budget;
use QuantumCloud\Chatbot\Ai\Prompt_Presets;
use QuantumCloud\Chatbot\Ai\Provider_Settings;
use QuantumCloud\Chatbot\Ai\Routing;
use QuantumCloud\Chatbot\Ai\Safe_Answer;
use QuantumCloud\Chatbot\Conversation\Steps\Knowledge_Step;
use QuantumCloud\Chatbot\RateLimit\Role_Limits;

defined( 'ABSPATH' ) || exit;

/**
 * AI Settings page.
 */
final class Ai_Settings_Page {

	/**
	 * The host's AI Settings page slug.
	 */
	const SLUG = 'wpbot_openAi';

	/**
	 * Save action (admin-post) and nonce.
	 */
	const ACTION = 'qcld_wpbot_chat_ai_settings';

	/**
	 * Provider test (AJAX).
	 */
	const TEST = 'qcld_wpbot_chat_ai_test';

	/**
	 * Option: delete the module's data when the last host is deleted (read by uninstall-helper.php).
	 */
	const DELETE_OPTION = 'qcld_wpbot_chat_delete_on_uninstall';

	/**
	 * AJAX action: read a provider's model list again.
	 */
	const REFRESH = 'qcld_wpbot_chat_ai_models';

	/**
	 * Script handle for the page's inline scripts.
	 */
	const SCRIPT = 'qcld-wpbot-chat-ai-settings';

	/**
	 * WPBot Core's live model lists (newer WPBot Core only).
	 */
	const CATALOG = '\\QuantumCloud\\WPBotCore\\AI\\Model_Catalog';

	/**
	 * Provider order on the screen (owner, 10 November 2026). Since round 6 (owner, 12 November
	 * 2026) WPBot Free shows Ollama too; the `qcld_wpbot_chat/ai_providers` filter can still add
	 * or remove providers.
	 */
	const ORDER = array( 'openai', 'claude', 'gemini', 'openrouter', 'grok', 'mistral', 'deepseek', 'ollama' );

	/**
	 * Providers on by default, in WPBot Free and Pro.
	 */
	const DEFAULT_PROVIDERS = array( 'openai', 'claude', 'gemini', 'openrouter', 'grok', 'mistral', 'deepseek', 'ollama' );

	/**
	 * Hook in (admin).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_' . self::TEST, array( __CLASS__, 'test' ) );
		add_action( 'wp_ajax_' . self::REFRESH, array( __CLASS__, 'refresh_models' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Sections: id => [ title, dashicon ].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function sections() {
		$sections = array(
			'providers' => array( __( 'Providers', 'chatbot' ), 'dashicons-admin-network' ),
			'answers'   => array( __( 'Answers', 'chatbot' ), 'dashicons-format-chat' ),
			'knowledge' => array( __( 'Knowledge Base', 'chatbot' ), 'dashicons-book' ),
			'known'     => array( __( 'What the chatbot knows', 'chatbot' ), 'dashicons-list-view' ),
			'cache'     => array( __( 'Answer cache', 'chatbot' ), 'dashicons-backup' ),
			'limits'    => array( \QuantumCloud\Chatbot\Spend\Spend_Screen::available() ? __( 'Spending and limits', 'chatbot' ) : __( 'Limits and cost', 'chatbot' ), 'dashicons-dashboard' ),
		);
		if ( \QuantumCloud\Chatbot\Spend\Spend_Screen::available() ) {
			// Rebuild 6: the AI Usage report lives here (owner, 11 November 2026: no separate menu item).
			$sections['usage'] = array( __( 'AI Usage', 'chatbot' ), 'dashicons-chart-bar' );
		}
		if ( \QuantumCloud\Chatbot\Diagnostics\Error_Log_Screen::available() ) {
			// Every error of every part of WPBot, and the support report (owner, 11 November 2026).
			$sections['errors'] = array( __( 'Error log', 'chatbot' ), 'dashicons-warning' );
		}
		// Round 6: what happens to the chatbot's data when the plugin is deleted.
		$sections['data'] = array( __( 'Chatbot data', 'chatbot' ), 'dashicons-database' );
		return $sections;
	}

	/**
	 * The current section.
	 *
	 * @return string
	 */
	public static function section() {
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'providers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A tab.
		// WPBot Core's spending links use section=spending.
		$section = 'spending' === $section ? 'limits' : $section;
		return isset( self::sections()[ $section ] ) ? $section : 'providers';
	}

	/**
	 * A section's URL.
	 *
	 * @param string $section Section.
	 * @param array  $args    Extra query arguments.
	 * @return string
	 */
	public static function url( $section = 'providers', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'section' => $section ), $args ), admin_url( 'admin.php' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	/**
	 * The module's admin look on the AI Settings page.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		if ( strtolower( self::SLUG ) !== $page ) {
			return;
		}
		wp_enqueue_style( Admin_Pages::STYLE, plugins_url( 'assets/admin/pages.css', defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php' ), array( 'dashicons' ), defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1' );
		// The page's small scripts are added to this handle with wp_add_inline_script() (printed in the footer).
		wp_register_script( self::SCRIPT, false, array(), defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1', true );
		wp_enqueue_script( self::SCRIPT );
		// Links from older screens and notices point at the old tab anchor (#ai-knowledge-base-tab...).
		// Open the Knowledge Base section for them, keeping the anchor for its own tabs. In the page head,
		// so the wrong section never shows.
		wp_register_script( self::SCRIPT . '-links', false, array(), defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1', false );
		wp_enqueue_script( self::SCRIPT . '-links' );
		wp_add_inline_script( self::SCRIPT . '-links', self::legacy_links_script() );
	}

	/**
	 * The script that sends old Knowledge Base anchors to the Knowledge Base section.
	 *
	 * @return string
	 */
	public static function legacy_links_script() {
		return '( function () { var h = window.location.hash || ""; if ( 0 !== h.indexOf( "#ai-knowledge-base-tab" ) || ! window.URL ) { return; } var u = new URL( window.location.href ); if ( "knowledge" === u.searchParams.get( "section" ) ) { return; } u.searchParams.set( "section", "knowledge" ); window.location.replace( u.toString() ); } )();';
	}

	/**
	 * Providers with their screen label and catalogue (WPBot Core's list of models and prices).
	 *
	 * @return array<string,array{label:string,models:array,needs_key:bool,typed:bool}>
	 */
	public static function providers() {
		$catalogue = class_exists( '\QuantumCloud\WPBotCore\AI\Providers' ) ? \QuantumCloud\WPBotCore\AI\Providers::all() : array();
		$labels    = array(
			'openai'     => 'OpenAI',
			'claude'     => 'Anthropic Claude',
			'gemini'     => 'Google Gemini',
			'openrouter' => 'OpenRouter',
			'grok'       => 'xAI Grok',
			'mistral'    => 'Mistral',
			'deepseek'   => 'DeepSeek',
			'ollama'     => 'Ollama',
		);
		$map       = Provider_Settings::map();
		$out       = array();
		/**
		 * The providers AI Settings offers (ids from the ORDER list; the screen keeps that order).
		 *
		 * @param string[] $ids Provider ids.
		 */
		$allowed = (array) apply_filters( 'qcld_wpbot_chat/ai_providers', self::DEFAULT_PROVIDERS );
		foreach ( self::ORDER as $id ) {
			if ( ! isset( $map[ $id ] ) || ! in_array( $id, $allowed, true ) ) {
				continue;
			}
			$models = array();
			if ( isset( $catalogue[ $id ]['models'] ) ) {
				foreach ( (array) $catalogue[ $id ]['models'] as $model => $info ) {
					if ( ! empty( $info['text'] ) ) {
						$models[ (string) $model ] = (array) $info;
					}
				}
			}
			$out[ $id ] = array(
				'label'     => $labels[ $id ],
				'models'    => $models,
				'needs_key' => 'ollama' !== $id,
				'typed'     => in_array( $id, array( 'openrouter', 'ollama' ), true ),
			);
		}
		return $out;
	}

	/**
	 * A model's line under the list: price per million tokens and what it can do.
	 *
	 * @param string $provider Provider.
	 * @param string $model    Model.
	 * @param array  $info     Catalogue entry.
	 * @return string
	 */
	public static function model_note( $provider, $model, array $info ) {
		$parts = array();
		if ( ! empty( $info['price_known'] ) || isset( $info['price_in'] ) ) {
			/* translators: 1: price per million input tokens, 2: per million output tokens (USD). */
			$parts[] = sprintf( __( '$%1$s / $%2$s per 1M tokens', 'chatbot' ), number_format_i18n( (float) $info['price_in'], 2 ), number_format_i18n( (float) $info['price_out'], 2 ) );
		}
		$providers = '\QuantumCloud\WPBotCore\AI\Providers';
		if ( class_exists( $providers ) && $providers::supports_tools( $provider, $model ) ) {
			$parts[] = __( 'AI Actions', 'chatbot' );
		}
		if ( class_exists( $providers ) && $providers::reads_pdf( $provider ) ) {
			$parts[] = __( 'reads PDFs', 'chatbot' );
		}
		return implode( ' · ', $parts );
	}

	/**
	 * The mask shown for a saved key: four dots and its last 4 characters ('' = none).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask( $key ) {
		$key = (string) $key;
		return '' === $key ? '' : str_repeat( "\u{2022}", 4 ) . substr( $key, -4 );
	}

	/**
	 * Whether a posted key is the mask (keep the saved key).
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_mask( $value ) {
		return (bool) preg_match( '/^(?:\x{2022}|\*)+.{0,4}$/u', (string) $value );
	}

	/**
	 * The new key in a posted value: a key pasted after the mask (without first clearing the
	 * field) arrives as "••••abcd" + key; the mask and its last 4 characters are not part of it.
	 *
	 * @param string $value Posted value.
	 * @return string
	 */
	public static function typed_key( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(?:\x{2022}|\*)+(.*)$/us', $value, $m ) ) {
			$value = mb_substr( $m[1], 4 );
		}
		return $value;
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$section = self::section();
		echo '<div class="wrap qcld-wpbot-page qcld-wpbot-ai-settings">';
		if ( has_action( 'qcld_wpbot_chat/ai_settings_header' ) ) {
			/**
			 * Print the host's header at the top of AI Settings.
			 */
			do_action( 'qcld_wpbot_chat/ai_settings_header' );
		}
		// WordPress moves admin notices to just after this marker (not into the header).
		echo '<hr class="wp-header-end" style="display:none">';
		echo '<div class="qcwp-layout">';
		echo '<nav class="qcwp-side" aria-label="' . esc_attr__( 'AI Settings menu', 'chatbot' ) . '"><p class="qcwp-side-title">' . esc_html__( 'AI Settings', 'chatbot' ) . '</p>';
		foreach ( self::sections() as $id => $item ) {
			printf( '<a class="qcwp-side-item%1$s" href="%2$s"%3$s><span class="dashicons %4$s" aria-hidden="true"></span>%5$s</a>', $id === $section ? ' is-active' : '', esc_url( self::url( $id ) ), $id === $section ? ' aria-current="page"' : '', esc_attr( $item[1] ), esc_html( $item[0] ) );
		}
		echo '</nav><div class="qcwp-main">';
		$titles = array(
			'providers' => __( 'Which AI answers the chatbot, with a backup and a cheaper model for short questions. Your other AI features (writing, SEO, social and more) use these keys too, unless you give a feature its own keys.', 'chatbot' ),
			'answers'   => __( 'How the chatbot answers. These settings apply to every provider, so the backup answers the same way as the primary.', 'chatbot' ),
			'knowledge' => __( 'What the chatbot learns from: your site content, database tables and documents.', 'chatbot' ),
			'known'     => __( 'Everything in the knowledge base, with its status. Answers come from this list.', 'chatbot' ),
			'usage'     => __( 'What WPBot\'s AI features cost, by day, feature, task and model. All figures are estimates from the providers\' list prices.', 'chatbot' ),
			'errors'    => __( 'Errors from every part of WPBot, not only AI. If something does not work, describe it and send the report to WPBot support.', 'chatbot' ),
			'limits'    => \QuantumCloud\Chatbot\Spend\Spend_Screen::available() ? __( 'What AI costs your site, the spending limits of every AI feature, alerts, and the chatbot\'s rate limits.', 'chatbot' ) : __( 'What the chatbot\'s AI answers cost, its monthly budget and its rate limits.', 'chatbot' ),
			'cache'     => __( 'Answers saved for repeated questions, how often each was reused, and an AI check that they are correct.', 'chatbot' ),
			'data'      => __( 'What the chatbot keeps on your site, and whether it is deleted with the plugin.', 'chatbot' ),
		);
		echo '<div class="qcwp-card-head"><div><h1 class="qcld-wpbot-page-title">' . esc_html( self::sections()[ $section ][0] ) . '</h1><p class="qcwp-lead">' . esc_html( $titles[ $section ] ) . '</p></div>';
		if ( has_action( 'qcld_wpbot_chat/ai_settings_wizard' ) ) {
			echo '<button type="button" id="wpbot-trigger-wizard" class="qcwp-btn">' . esc_html__( 'AI Wizard', 'chatbot' ) . '</button>';
		}
		echo '</div>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice only.
			echo '<div class="qcwp-notice" role="status">' . esc_html__( 'Saved.', 'chatbot' ) . '</div>';
		}
		Removed_Settings_Notice::render_inline();
		// AI spending this period, on every section (rebuild 6).
		\QuantumCloud\Chatbot\Spend\Spend_Screen::render_strip();
		switch ( $section ) {
			case 'answers':
				self::render_answers();
				break;
			case 'knowledge':
				self::render_knowledge();
				break;
			case 'known':
				self::render_known();
				break;
			case 'limits':
				self::render_limits();
				break;
			case 'usage':
				\QuantumCloud\Chatbot\Spend\Spend_Screen::render_usage();
				break;
			case 'errors':
				\QuantumCloud\Chatbot\Diagnostics\Error_Log_Screen::render();
				break;
			case 'cache':
				Answer_Cache_Screen::render();
				break;
			case 'data':
				self::render_data();
				break;
			default:
				self::render_providers();
		}//end switch
		echo '</div></div>';
		if ( has_action( 'qcld_wpbot_chat/ai_settings_wizard' ) ) {
			/**
			 * Print the host's AI Wizard (its button is in the page header).
			 */
			do_action( 'qcld_wpbot_chat/ai_settings_wizard' );
		}
		echo '</div>';
	}

	/**
	 * Open the section's form.
	 *
	 * @param string $section Section.
	 * @return void
	 */
	private static function form_open( $section ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="qcwp-form" autocomplete="off">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="section" value="' . esc_attr( $section ) . '">';
	}

	/**
	 * Close the section's form with its Save button.
	 *
	 * @return void
	 */
	private static function form_close() {
		echo '<div class="qcwp-actions qcwp-sticky"><button type="submit" class="qcwp-btn is-primary">' . esc_html__( 'Save settings', 'chatbot' ) . '</button></div></form>';
	}

	/**
	 * Providers: who answers, the provider cards, the cheaper model.
	 *
	 * @return void
	 */
	private static function render_providers() {
		if ( class_exists( self::CATALOG ) ) {
			$catalog = self::CATALOG;
			$catalog::maybe_refresh_prices();
		}
		$providers = self::providers();
		$primary   = Provider_Settings::answering_provider();
		$backup    = Routing::backup();
		self::form_open( 'providers' );

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Who answers', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'One provider answers every question. The backup is used only when the primary fails: a bad key, no credit, a rate limit or an outage. Other providers keep their keys but stay off.', 'chatbot' ) . '</p><div class="qcwp-two">';
		echo '<div><label class="qcwp-label" for="qcwp-primary">' . esc_html__( 'Primary provider', 'chatbot' ) . '</label><select class="qcwp-select" id="qcwp-primary" name="primary"><option value="">' . esc_html__( 'None (AI off)', 'chatbot' ) . '</option>';
		if ( '' !== $primary && ! isset( $providers[ $primary ] ) ) {
			// The provider answering now is not offered here (Ollama after WPBot Pro was deactivated):
			// keep it selected so saving this page does not switch the AI off.
			/* translators: %s: AI provider name. */
			echo '<option value="' . esc_attr( $primary ) . '" selected>' . esc_html( sprintf( __( '%s (kept as it is)', 'chatbot' ), ucfirst( $primary ) ) ) . '</option>';
		}
		foreach ( $providers as $id => $p ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $primary, $id, false ) . '>' . esc_html( $p['label'] ) . '</option>';
		}
		echo '</select></div><div><label class="qcwp-label" for="qcwp-backup">' . esc_html__( 'Backup provider', 'chatbot' ) . '</label><select class="qcwp-select" id="qcwp-backup" name="backup"><option value="">' . esc_html__( 'None (show the friendly message)', 'chatbot' ) . '</option>';
		foreach ( $providers as $id => $p ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $backup, $id, false ) . '>' . esc_html( $p['label'] ) . '</option>';
		}
		echo '</select></div></div></div>';

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'AI providers and keys', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Keys stay on your server and are shown only as their last 4 characters. Prices are per 1 million tokens (in / out), from the providers\' list prices.', 'chatbot' ) . '</p><div class="qcwp-grid" style="margin-top:16px">';
		foreach ( $providers as $id => $p ) {
			self::render_card( $id, $p, $primary, $backup );
		}
		echo '</div></div>';

		$short = Routing::short_models();
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Cheaper model for short questions', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Greetings and short questions go to a cheaper model of the same provider. Longer questions use the model chosen above. Leave a provider on "Same model" to turn this off for it.', 'chatbot' ) . '</p><div class="qcwp-two">';
		echo '<div><label class="qcwp-label" for="qcwp-short-words">' . esc_html__( 'Short means up to', 'chatbot' ) . '</label><div class="qcwp-row"><input type="number" min="1" max="50" class="qcwp-input" style="width:100px" id="qcwp-short-words" name="short_words" value="' . esc_attr( (string) Routing::short_words() ) . '"><span class="qcwp-help" style="margin:0">' . esc_html__( 'words', 'chatbot' ) . '</span></div></div>';
		foreach ( array_filter( array( $primary, $backup ) ) as $id ) {
			if ( ! isset( $providers[ $id ] ) ) {
				continue;
			}
			$role = $id === $primary ? __( 'primary', 'chatbot' ) : __( 'backup', 'chatbot' );
			/* translators: 1: provider name, 2: primary or backup. */
			echo '<div><label class="qcwp-label" for="qcwp-short-' . esc_attr( $id ) . '">' . esc_html( sprintf( __( '%1$s (%2$s) uses', 'chatbot' ), $providers[ $id ]['label'], $role ) ) . '</label>';
			if ( $providers[ $id ]['typed'] ) {
				echo '<input type="text" class="qcwp-input" id="qcwp-short-' . esc_attr( $id ) . '" name="short_models[' . esc_attr( $id ) . ']" value="' . esc_attr( isset( $short[ $id ] ) ? $short[ $id ] : '' ) . '" placeholder="' . esc_attr__( 'Same model (empty)', 'chatbot' ) . '">';
			} else {
				echo '<select class="qcwp-select" id="qcwp-short-' . esc_attr( $id ) . '" name="short_models[' . esc_attr( $id ) . ']"><option value="">' . esc_html__( 'Same model', 'chatbot' ) . '</option>';
				self::model_options( $id, $providers[ $id ], isset( $short[ $id ] ) ? (string) $short[ $id ] : '', self::CHEAP_FIRST );
				echo '</select>';
			}
			echo '</div>';
		}//end foreach
		echo '</div></div>';
		self::form_close();
		self::test_script();
	}

	/**
	 * One provider card.
	 *
	 * @param string $id      Provider.
	 * @param array  $p       Provider info.
	 * @param string $primary Primary provider.
	 * @param string $backup  Backup provider.
	 * @return void
	 */
	private static function render_card( $id, array $p, $primary, $backup ) {
		$settings = Provider_Settings::get( $id, false );
		$key      = (string) $settings['key'];
		$model    = (string) $settings['model'];
		$role     = $id === $primary ? 'primary' : ( $id === $backup ? 'backup' : 'off' );
		$badges   = array(
			'primary' => __( 'Primary', 'chatbot' ),
			'backup'  => __( 'Backup', 'chatbot' ),
			'off'     => __( 'Off', 'chatbot' ),
		);
		$ready    = $p['needs_key'] ? '' !== $key : '' !== (string) get_option( 'qcld_ollama_url', '' );
		$f        = 'providers[' . $id . ']';
		echo '<div class="qcwp-provider is-' . esc_attr( $role ) . '" id="provider-' . esc_attr( $id ) . '" data-provider="' . esc_attr( $id ) . '">';
		echo '<div class="qcwp-row" style="justify-content:space-between"><strong>' . esc_html( $p['label'] ) . '</strong><span class="qcwp-pill qcwp-role-' . esc_attr( $role ) . '">' . esc_html( $badges[ $role ] ) . '</span></div>';
		echo '<p class="qcwp-state ' . ( $ready ? 'is-ready' : '' ) . '">' . esc_html( $ready ? __( 'Ready', 'chatbot' ) : ( $p['needs_key'] ? __( 'No key', 'chatbot' ) : __( 'Not set up', 'chatbot' ) ) ) . '</p>';
		if ( $p['needs_key'] ) {
			echo '<label class="qcwp-label" for="qcwp-key-' . esc_attr( $id ) . '">' . esc_html__( 'API key', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-key-' . esc_attr( $id ) . '" name="' . esc_attr( $f ) . '[key]" value="' . esc_attr( self::mask( $key ) ) . '" placeholder="' . esc_attr__( 'Paste a key', 'chatbot' ) . '" spellcheck="false">';
		}
		if ( 'openrouter' === $id || 'ollama' === $id ) {
			$url = 'ollama' === $id ? preg_replace( '#/v1$#', '', (string) $settings['url'] ) : (string) $settings['url'];
			echo '<label class="qcwp-label" for="qcwp-url-' . esc_attr( $id ) . '">' . esc_html( 'ollama' === $id ? __( 'Server URL', 'chatbot' ) : __( 'Endpoint URL', 'chatbot' ) ) . '</label><input type="url" class="qcwp-input" id="qcwp-url-' . esc_attr( $id ) . '" name="' . esc_attr( $f ) . '[url]" value="' . esc_attr( $url ) . '" placeholder="' . esc_attr( 'ollama' === $id ? 'http://localhost:11434' : 'https://openrouter.ai/api/v1' ) . '">';
		}
		echo '<label class="qcwp-label" for="qcwp-model-' . esc_attr( $id ) . '">' . esc_html__( 'Model', 'chatbot' ) . '</label>';
		$choices = self::model_choices( $id, $p );
		$info    = self::info( $id, $model );
		if ( $p['typed'] ) {
			echo '<input type="text" class="qcwp-input" id="qcwp-model-' . esc_attr( $id ) . '" name="' . esc_attr( $f ) . '[model]" value="' . esc_attr( $model ) . '" placeholder="' . esc_attr__( 'Model name', 'chatbot' ) . '" list="qcwp-models-' . esc_attr( $id ) . '" autocomplete="off">';
			echo '<datalist id="qcwp-models-' . esc_attr( $id ) . '">';
			foreach ( array_merge( $choices['recommended'], $choices['all'] ) as $m => $label ) {
				echo '<option value="' . esc_attr( $m ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</datalist>';
			$note = ! empty( $info['price_known'] ) && '' !== $model ? self::model_note( $id, $model, $info ) : ( 'ollama' === $id ? __( 'A model installed on your Ollama server. No cost per answer.', 'chatbot' ) : __( 'Any OpenRouter model name. The price shows when the model is known.', 'chatbot' ) );
		} else {
			echo '<select class="qcwp-select qcwp-model" id="qcwp-model-' . esc_attr( $id ) . '" name="' . esc_attr( $f ) . '[model]">';
			self::model_options( $id, $p, $model );
			echo '</select>';
			$note = '' !== $model ? self::model_note( $id, $model, $info ) : '';
		}
		echo '<p class="qcwp-help qcwp-model-note">' . esc_html( $note ) . '</p>';
		self::list_state( $id, $model, $choices );
		echo '<div class="qcwp-row" style="margin-top:12px"><button type="button" class="qcwp-btn is-small qcwp-test" data-provider="' . esc_attr( $id ) . '">' . esc_html__( 'Test', 'chatbot' ) . '</button>';
		if ( $p['needs_key'] && '' !== $key ) {
			echo '<label class="qcwp-check" style="margin:0;font-size:13px"><input type="checkbox" name="' . esc_attr( $f ) . '[remove]" value="1">' . esc_html__( 'Remove saved key', 'chatbot' ) . '</label>';
		}
		echo '<span class="qcwp-test-status qcwp-help" role="status" style="margin:0"></span></div>';
		self::render_options( $id, $f );
		echo '</div>';
	}

	/**
	 * Settings only some providers have (the card's Options panel).
	 *
	 * @param string $id Provider.
	 * @param string $f  Field name prefix.
	 * @return void
	 */
	private static function render_options( $id, $f ) {
		if ( 'grok' === $id ) {
			echo '<details class="qcwp-options"><summary>' . esc_html__( 'Options', 'chatbot' ) . '</summary>';
			echo '<label class="qcwp-label" for="qcwp-grok-collection">' . esc_html__( 'Collection ID', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-grok-collection" name="' . esc_attr( $f ) . '[collection]" value="' . esc_attr( (string) get_option( 'qcld_grok_collection_id', '' ) ) . '" placeholder="' . esc_attr__( 'Leave empty if you don\'t use a Grok collection', 'chatbot' ) . '">';
			echo '<label class="qcwp-label" for="qcwp-grok-management">' . esc_html__( 'Management key', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-grok-management" name="' . esc_attr( $f ) . '[management_key]" value="' . esc_attr( self::mask( (string) get_option( 'qcld_grok_management_api_key', '' ) ) ) . '" placeholder="' . esc_attr__( 'Paste a key', 'chatbot' ) . '" spellcheck="false">';
			echo '<div class="qcwp-notice is-warn" style="margin:10px 0 0">' . esc_html__( 'With a collection ID set, Grok answers from its collection with the old engine: no backup provider, cheaper model, cost tracking or source links for those answers.', 'chatbot' ) . '</div></details>';
		} elseif ( 'claude' === $id ) {
			echo '<details class="qcwp-options"><summary>' . esc_html__( 'Options', 'chatbot' ) . '</summary>';
			echo '<label class="qcwp-label" for="qcwp-voyage">' . esc_html__( 'Voyage AI key (Knowledge Base)', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-voyage" name="' . esc_attr( $f ) . '[voyage_key]" value="' . esc_attr( self::mask( (string) get_option( 'qcld_voyage_api_key', '' ) ) ) . '" placeholder="' . esc_attr__( 'Paste a key', 'chatbot' ) . '" spellcheck="false">';
			echo '<p class="qcwp-help">' . esc_html__( 'Claude has no embeddings of its own: with Claude as the primary provider, the Knowledge Base uses Voyage AI to find your content.', 'chatbot' ) . '</p></details>';
		}
	}

	/**
	 * Order of the recommended models in the "cheaper model" lists: cheapest first.
	 */
	const CHEAP_FIRST = true;

	/**
	 * A model's catalogue entry, or the price feed's, or an unpriced entry.
	 *
	 * @param string $provider Provider.
	 * @param string $model    Model.
	 * @return array
	 */
	private static function info( $provider, $model ) {
		$providers = '\QuantumCloud\WPBotCore\AI\Providers';
		if ( '' !== $model && class_exists( $providers ) ) {
			return (array) $providers::model_info( $provider, $model );
		}
		return array( 'price_known' => false );
	}

	/**
	 * The models a card offers: the recommended ones (with their label) and all the others,
	 * from the provider's own list when a key is saved, else from WPBot Core's catalogue.
	 *
	 * @param string $id Provider.
	 * @param array  $p  Provider info.
	 * @return array{recommended:array<string,string>,picks:array<string,string>,all:array<string,string>,live:bool,error:string,fetched:int}
	 */
	public static function model_choices( $id, array $p ) {
		$out = array(
			'recommended' => array(),
			'picks'       => array(),
			'all'         => array(),
			'live'        => false,
			'error'       => '',
			'fetched'     => 0,
		);
		if ( ! class_exists( self::CATALOG ) ) {
			foreach ( $p['models'] as $m => $info ) {
				$out['all'][ (string) $m ] = isset( $info['label'] ) ? (string) $info['label'] : (string) $m;
			}
			return $out;
		}
		$catalog = self::CATALOG;
		foreach ( $catalog::picks( $id ) as $m => $pick ) {
			$out['picks'][ $m ]       = $pick;
			$out['recommended'][ $m ] = self::option_label( $id, $m, $catalog::pick_label( $pick ) );
		}
		$settings = Provider_Settings::get( $id, false );
		$key      = null !== $settings ? (string) $settings['key'] : '';
		$url      = null !== $settings ? (string) $settings['url'] : '';
		$ids      = array();
		if ( ( $p['needs_key'] && '' !== $key ) || ( ! $p['needs_key'] && '' !== $url ) ) {
			$list           = $catalog::available( $id, $key, $url );
			$out['error']   = (string) $list['error'];
			$out['fetched'] = (int) $list['fetched'];
			$out['live']    = '' === $out['error'];
			$ids            = (array) $list['models'];
		}
		if ( ! $out['live'] ) {
			$ids = array_keys( $p['models'] );
		}
		foreach ( $ids as $m ) {
			$m = (string) $m;
			if ( ! isset( $out['recommended'][ $m ] ) ) {
				$out['all'][ $m ] = self::option_label( $id, $m, '' );
			}
		}
		return $out;
	}

	/**
	 * The options of a model list: the saved model if it is in neither group, the recommended
	 * models (highlighted), then all the others.
	 *
	 * @param string $id          Provider.
	 * @param array  $p           Provider info.
	 * @param string $selected    Saved model.
	 * @param bool   $cheap_first Put the cheapest recommended model first (short questions).
	 * @return void
	 */
	private static function model_options( $id, array $p, $selected, $cheap_first = false ) {
		$choices     = self::model_choices( $id, $p );
		$recommended = $choices['recommended'];
		if ( $cheap_first ) {
			uksort(
				$recommended,
				function ( $a, $b ) use ( $choices ) {
					return (int) ( 'cheap' !== $choices['picks'][ $a ] ) - (int) ( 'cheap' !== $choices['picks'][ $b ] );
				}
			);
		}
		if ( '' !== $selected && ! isset( $recommended[ $selected ] ) && ! isset( $choices['all'][ $selected ] ) ) {
			/* translators: %s: model id. */
			echo '<option value="' . esc_attr( $selected ) . '" selected data-note="' . esc_attr( self::model_note( $id, $selected, self::info( $id, $selected ) ) ) . '">' . esc_html( sprintf( __( '%s (saved)', 'chatbot' ), $selected ) ) . '</option>';
		}
		$groups = array(
			__( 'Recommended', 'chatbot' ) => $recommended,
			/* translators: %s: AI provider name. */
			( $choices['live'] ? sprintf( __( 'All models from your %s account', 'chatbot' ), $p['label'] ) : __( 'Other models', 'chatbot' ) ) => $choices['all'],
		);
		foreach ( $groups as $label => $models ) {
			if ( ! $models ) {
				continue;
			}
			echo '<optgroup label="' . esc_attr( $label ) . '">';
			foreach ( $models as $m => $text ) {
				echo '<option value="' . esc_attr( $m ) . '"' . selected( $selected, $m, false ) . ' data-note="' . esc_attr( self::model_note( $id, $m, self::info( $id, $m ) ) ) . '">' . esc_html( $text ) . '</option>';
			}
			echo '</optgroup>';
		}
	}

	/**
	 * One option's text: "★ GPT-6.1 Terra · Best value" for picks, else the name.
	 *
	 * @param string $id    Provider.
	 * @param string $model Model.
	 * @param string $pick  Pick label ('' when not recommended).
	 * @return string
	 */
	private static function option_label( $id, $model, $pick ) {
		$info  = self::info( $id, $model );
		$label = isset( $info['label'] ) && '' !== (string) $info['label'] ? (string) $info['label'] : $model;
		return '' !== $pick ? '★ ' . $label . ' · ' . $pick : $label;
	}

	/**
	 * Under a card's model: where the list comes from, a Refresh link, and a warning when the
	 * saved model is no longer offered.
	 *
	 * @param string $id      Provider.
	 * @param string $model   Saved model.
	 * @param array  $choices Model choices.
	 * @return void
	 */
	private static function list_state( $id, $model, array $choices ) {
		if ( ! class_exists( self::CATALOG ) ) {
			return;
		}
		$catalog  = self::CATALOG;
		$settings = Provider_Settings::get( $id, false );
		$key      = null !== $settings ? (string) $settings['key'] : '';
		$url      = null !== $settings ? (string) $settings['url'] : '';
		$label    = self::providers()[ $id ]['label'];
		if ( '' !== $model && false === $catalog::offered( $id, $model, $key, $url ) ) {
			$best = array_search( 'value', $choices['picks'], true );
			/* translators: 1: AI provider name, 2: model id. */
			$text = sprintf( __( '%1$s no longer offers %2$s, so answers from it fail. Pick another model.', 'chatbot' ), $label, $model );
			if ( false !== $best ) {
				/* translators: %s: model name. */
				$text .= ' ' . sprintf( __( 'Recommended: %s.', 'chatbot' ), self::option_label( $id, (string) $best, '' ) );
			}
			echo '<div class="qcwp-notice is-warn qcwp-model-gone" style="margin:8px 0 0">' . esc_html( $text ) . '</div>';
		}
		if ( $choices['live'] ) {
			/* translators: 1: number of models, 2: time since the list was read. */
			$state = sprintf( __( '%1$s models from your account, checked %2$s ago.', 'chatbot' ), number_format_i18n( count( $choices['all'] ) + count( $choices['recommended'] ) ), human_time_diff( $choices['fetched'] ) );
		} elseif ( '' !== $choices['error'] ) {
			$state = __( 'Could not read the model list from the provider, so the built-in list is shown.', 'chatbot' );
		} else {
			$state = __( 'Save a key to list every model your account can use.', 'chatbot' );
		}
		echo '<p class="qcwp-help qcwp-models-state" style="margin-top:4px">' . esc_html( $state );
		if ( $choices['live'] || '' !== $choices['error'] ) {
			echo ' <button type="button" class="button-link qcwp-refresh-models" data-provider="' . esc_attr( $id ) . '">' . esc_html__( 'Refresh models', 'chatbot' ) . '</button>';
		}
		echo '</p>';
	}

	/**
	 * AJAX: read a provider's model list again, then the page reloads.
	 *
	 * @return void
	 */
	public static function refresh_models() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::REFRESH, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'chatbot' ) ), 403 );
		}
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$settings = Provider_Settings::get( $provider, false );
		if ( null === $settings || ! class_exists( self::CATALOG ) || ! isset( self::providers()[ $provider ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This provider can\'t list its models here.', 'chatbot' ) ) );
		}
		$catalog = self::CATALOG;
		$list    = $catalog::available( $provider, (string) $settings['key'], (string) $settings['url'], true );
		if ( '' !== $list['error'] ) {
			wp_send_json_error( array( 'message' => $list['error'] ) );
		}
		wp_send_json_success( array( 'count' => count( $list['models'] ) ) );
	}

	/**
	 * The provider Test buttons.
	 *
	 * @return void
	 */
	private static function test_script() {
		$data                 = array(
			'url'    => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( self::TEST ),
			'wait'   => __( 'Testing…', 'chatbot' ),
			'ok'     => __( 'Works.', 'chatbot' ),
			'fail'   => __( 'Could not test. Reload the page and try again.', 'chatbot' ),
			'save'   => __( 'Save first, then test the saved key.', 'chatbot' ),
			'action' => self::TEST,
		);
		$data['refresh']      = self::REFRESH;
		$data['refreshNonce'] = wp_create_nonce( self::REFRESH );
		$data['refreshing']   = __( 'Checking…', 'chatbot' );
		wp_add_inline_script( self::SCRIPT, 'window.qcwpAiTest = ' . wp_json_encode( $data ) . ';' );
		wp_add_inline_script(
			self::SCRIPT,
			<<<'JS'
		( function ( cfg ) {
			document.querySelectorAll( 'select.qcwp-model' ).forEach( function ( select ) {
				select.addEventListener( 'change', function () {
					var note = select.closest( '.qcwp-provider' ).querySelector( '.qcwp-model-note' ), option = select.options[ select.selectedIndex ], gone = select.closest( '.qcwp-provider' ).querySelector( '.qcwp-model-gone' );
					if ( note && option ) { note.textContent = option.getAttribute( 'data-note' ) || ''; }
					if ( gone ) { gone.hidden = true; }
				} );
			} );
			document.querySelectorAll( '.qcwp-refresh-models' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var state = button.closest( '.qcwp-models-state' ), body = new FormData();
					button.disabled = true;
					button.textContent = cfg.refreshing;
					body.append( 'action', cfg.refresh );
					body.append( 'nonce', cfg.refreshNonce );
					body.append( 'provider', button.getAttribute( 'data-provider' ) );
					fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( r ) {
							if ( r && r.success ) { window.location.reload(); return; }
							state.textContent = r && r.data && r.data.message ? r.data.message : cfg.fail;
						} )
						.catch( function () { state.textContent = cfg.fail; } );
				} );
			} );
		} )( window.qcwpAiTest );
JS
		);
		wp_add_inline_script(
			self::SCRIPT,
			<<<'JS'
		( function ( cfg ) {
			document.querySelectorAll( '.qcwp-test' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var card = button.closest( '.qcwp-provider' ), status = card.querySelector( '.qcwp-test-status' ), key = card.querySelector( 'input[name$="[key]"]' );
					if ( key && key.value && ! /^(•|\*)+.{0,4}$/.test( key.value ) ) { status.textContent = cfg.save; return; }
					status.textContent = cfg.wait;
					button.disabled = true;
					var body = new FormData();
					body.append( 'action', cfg.action );
					body.append( 'nonce', cfg.nonce );
					body.append( 'provider', button.getAttribute( 'data-provider' ) );
					fetch( cfg.url, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( r ) { status.textContent = r && r.success ? cfg.ok : ( r && r.data && r.data.message ? r.data.message : cfg.fail ); } )
						.catch( function () { status.textContent = cfg.fail; } )
						.then( function () { button.disabled = false; } );
				} );
			} );
		} )( window.qcwpAiTest );
JS
		);
	}

	/**
	 * Answers: personality, what the AI may answer, knowledge and context, reply style, files.
	 *
	 * @return void
	 */
	private static function render_answers() {
		$set     = Ai_Settings::get();
		$foreign = Ai_Settings::foreign_url( $set['system_prompt'] . "\n" . $set['instructions'] );
		if ( '' !== $foreign ) {
			echo '<div class="qcwp-notice is-warn"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="qcwp-row" style="justify-content:space-between">';
			wp_nonce_field( self::ACTION );
			/* translators: 1: an address in the prompt, 2: this site's address. */
			echo '<span>' . esc_html( sprintf( __( 'Your prompt mentions %1$s, but this site is %2$s.', 'chatbot' ), $foreign, home_url() ) ) . '</span>';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="section" value="answers"><input type="hidden" name="use_own_address" value="1"><button type="submit" class="qcwp-btn is-small">' . esc_html__( 'Use this site\'s address', 'chatbot' ) . '</button></form></div>';
		}
		self::form_open( 'answers' );
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Personality', 'chatbot' ) . '</h2>';
		echo '<label class="qcwp-label" for="qcwp-prompt">' . esc_html__( 'System prompt', 'chatbot' ) . '</label><textarea class="qcwp-textarea" rows="6" id="qcwp-prompt" name="system_prompt" placeholder="' . esc_attr( Ai_Settings::default_prompt() ) . '">' . esc_textarea( $set['system_prompt'] ) . '</textarea>';
		echo '<p class="qcwp-help">' . esc_html__( '{site_name} and {site_url} are filled in with this site\'s name and address. Empty = the short default shown in the box is used. Start from a preset:', 'chatbot' ) . '</p><div class="qcwp-row" style="margin-top:8px">';
		foreach ( Prompt_Presets::all() as $id => $preset ) {
			echo '<button type="button" class="qcwp-tab qcwp-preset" data-text="' . esc_attr( $preset['text'] ) . '">' . esc_html( $preset['label'] ) . '</button>';
		}
		echo '</div>';
		echo '<label class="qcwp-label" for="qcwp-instructions">' . esc_html__( 'Extra instructions', 'chatbot' ) . '</label><textarea class="qcwp-textarea" rows="3" id="qcwp-instructions" name="instructions" placeholder="' . esc_attr__( 'For example: Always answer in Spanish. Keep answers under 80 words.', 'chatbot' ) . '">' . esc_textarea( $set['instructions'] ) . '</textarea><p class="qcwp-help">' . esc_html__( 'Added to the system prompt. Visitors never see it.', 'chatbot' ) . '</p></div>';

		$scope = Answer_Scope::scope();
		$help  = Answer_Scope_Field::help();
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'What the AI may answer', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Added to your system prompt. When a question about your business is not covered by your content, the AI says it is not sure and suggests contacting you; it never guesses.', 'chatbot' ) . '</p>';
		foreach ( Answer_Scope::choices() as $value => $label ) {
			$label .= Answer_Scope::DEFAULT_SCOPE === $value ? ' ' . __( '(recommended)', 'chatbot' ) : '';
			echo '<label class="qcwp-check"><input type="radio" name="scope" value="' . esc_attr( $value ) . '"' . checked( $scope, $value, false ) . '><span><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( isset( $help[ $value ] ) ? $help[ $value ] : '' ) . '</small></span></label>';
		}
		echo '<div class="qcwp-two"><div><label class="qcwp-label" for="qcwp-contact">' . esc_html__( 'Contact details for "not sure" answers', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-contact" name="contact" value="' . esc_attr( (string) get_option( Answer_Scope::CONTACT_OPTION, '' ) ) . '" placeholder="' . esc_attr__( 'Email, phone or contact page link', 'chatbot' ) . '"><p class="qcwp-help">' . esc_html__( 'Leave empty to use your Business Context.', 'chatbot' ) . '</p></div>';
		echo '<div><label class="qcwp-label" for="qcwp-decline">' . esc_html__( 'Message for questions outside your business', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-decline" name="decline" value="' . esc_attr( (string) get_option( Answer_Scope::DECLINE_OPTION, '' ) ) . '" placeholder="' . esc_attr( Answer_Scope::default_decline() ) . '"><p class="qcwp-help">' . esc_html__( 'Used with "Business only". Leave empty for the default.', 'chatbot' ) . '</p></div>';
		echo '<div><label class="qcwp-label" for="qcwp-error">' . esc_html__( 'Message when the AI can\'t answer', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-error" name="error_text" value="' . esc_attr( (string) get_option( 'qcld_wpbot_ai_error_text', '' ) ) . '" placeholder="' . esc_attr__( 'Sorry, I can\'t answer that right now. Please try again in a little while.', 'chatbot' ) . '"><p class="qcwp-help">' . esc_html__( 'Shown when the provider fails. Site admins still see the real error.', 'chatbot' ) . '</p></div></div>';
		$items = Safe_Answer::menu_items();
		if ( $items ) {
			$chosen = wp_list_pluck( Safe_Answer::handoff(), 'label' );
			echo '<input type="hidden" name="handoff_sent" value="1"><span class="qcwp-label">' . esc_html__( 'When the bot isn\'t sure, show these buttons', 'chatbot' ) . '</span><div class="qcwp-row">';
			foreach ( array_keys( $items ) as $label ) {
				echo '<label class="qcwp-tab" style="cursor:pointer"><input type="checkbox" name="handoff[]" value="' . esc_attr( $label ) . '"' . checked( in_array( $label, $chosen, true ), true, false ) . ' style="margin:0 6px 0 0">' . esc_html( $label ) . '</label>';
			}
			echo '</div><div class="qcwp-row" style="margin-top:10px"><label class="qcwp-help" style="margin:0" for="qcwp-handoff-max">' . esc_html__( 'Most buttons', 'chatbot' ) . '</label><input type="number" min="1" max="6" class="qcwp-input" style="width:80px" id="qcwp-handoff-max" name="handoff_max" value="' . esc_attr( (string) (int) get_option( Safe_Answer::MAX_OPTION, 3 ) ) . '"></div>';
		}
		echo '</div>';

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Knowledge and context', 'chatbot' ) . '</h2>';
		self::toggle( 'knowledge_base', $set['knowledge_base'], __( 'Use the Knowledge Base', 'chatbot' ), __( 'Answers use your site content and documents first. Choose the sources on the Knowledge Base page.', 'chatbot' ) );
		self::toggle( 'citations', Knowledge_Step::citations_enabled(), __( 'Show source links under answers', 'chatbot' ), __( 'The pages an answer came from are listed under it.', 'chatbot' ) );
		self::toggle( 'page_context', $set['page_context'], __( 'Use the current page', 'chatbot' ), __( 'Tell the AI which page the visitor is on, so "this product" or "this course" makes sense.', 'chatbot' ) );
		echo '</div>';

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Reply style', 'chatbot' ) . '</h2><div class="qcwp-two">';
		echo '<div><label class="qcwp-label" for="qcwp-temperature">' . esc_html__( 'Creativity (temperature)', 'chatbot' ) . '</label><input type="number" min="0" max="2" step="0.1" class="qcwp-input" style="width:120px" id="qcwp-temperature" name="temperature" value="' . esc_attr( null === $set['temperature'] ? '' : (string) $set['temperature'] ) . '" placeholder="' . esc_attr__( 'Default', 'chatbot' ) . '"><p class="qcwp-help">' . esc_html__( '0 = precise, 1 = creative. Empty = the provider\'s default.', 'chatbot' ) . '</p></div>';
		echo '<div><label class="qcwp-label" for="qcwp-max">' . esc_html__( 'Longest answer (tokens)', 'chatbot' ) . '</label><input type="number" min="1" max="32000" class="qcwp-input" style="width:140px" id="qcwp-max" name="max_tokens" value="' . esc_attr( null === $set['max_tokens'] ? '' : (string) $set['max_tokens'] ) . '" placeholder="' . esc_attr__( 'Default', 'chatbot' ) . '"><p class="qcwp-help">' . esc_html__( 'A token is about 3/4 of a word. Empty = the provider\'s default.', 'chatbot' ) . '</p></div></div>';
		self::toggle( 'stream', $set['stream'], __( 'Stream replies', 'chatbot' ), __( 'Words appear as the AI writes them.', 'chatbot' ) );
		echo '</div>';

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Files from visitors', 'chatbot' ) . '</h2>';
		self::toggle( 'file_upload', '1' === (string) get_option( 'qcld_wpbot_ai_file_upload_enabled', '0' ), __( 'Chat file upload', 'chatbot' ), __( 'A paperclip next to the send button lets visitors attach files during AI chat.', 'chatbot' ) );
		echo '<label class="qcwp-label" for="qcwp-ext">' . esc_html__( 'Allowed file types', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-ext" name="file_extensions" value="' . esc_attr( (string) get_option( 'qcld_wpbot_ai_file_upload_extensions', 'jpg, jpeg, png, gif, webp, pdf, txt, csv, doc, docx' ) ) . '"></div>';
		self::form_close();
		wp_add_inline_script(
			self::SCRIPT,
			<<<'JS'
document.querySelectorAll( '.qcwp-preset' ).forEach( function ( b ) {
	b.addEventListener( 'click', function () { var t = document.getElementById( 'qcwp-prompt' ); t.value = b.getAttribute( 'data-text' ); t.focus(); } );
} );
JS
		);
	}

	/**
	 * An on/off switch.
	 *
	 * @param string $name  Field.
	 * @param bool   $on    On.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @return void
	 */
	private static function toggle( $name, $on, $label, $help ) {
		echo '<label class="qcwp-switch"><span><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $help ) . '</small></span><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" role="switch" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $on, true, false ) . '></label>';
	}

	/**
	 * Knowledge Base: the host's knowledge base screens in this page.
	 *
	 * @return void
	 */
	private static function render_knowledge() {
		echo '<div class="qcwp-kb">';
		if ( has_action( 'qcld_wpbot_chat/ai_settings_knowledge' ) ) {
			/**
			 * Print the host's knowledge base screens.
			 */
			do_action( 'qcld_wpbot_chat/ai_settings_knowledge' );
		} else {
			/** This action is documented in src/Admin/Kb_Panel.php */
			do_action( 'qcld_wpbot_chat/kb_settings_panel' );
		}
		echo '</div>';
	}

	/**
	 * What the chatbot knows: the full status, the searchable list and the host's content table.
	 *
	 * @return void
	 */
	private static function render_known() {
		echo '<div class="qcwp-kb">';
		Kb_Panel::render();
		if ( has_action( 'qcld_wpbot_chat/ai_settings_known' ) ) {
			/**
			 * Print the host's knowledge base content table under the list.
			 */
			do_action( 'qcld_wpbot_chat/ai_settings_known' );
		}
		echo '</div>';
	}

	/**
	 * Limits and cost.
	 *
	 * @return void
	 */
	private static function render_limits() {
		self::form_open( 'limits' );
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'ChatBot AI cost this month', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'The chatbot\'s AI answers only. Estimates from the providers\' list prices; your provider\'s bill is the exact figure.', 'chatbot' ) . '</p>';
		$usage  = '\QuantumCloud\WPBotCore\AI\Usage';
		$models = array();
		if ( class_exists( $usage ) && method_exists( $usage, 'summary' ) ) {
			$summary = $usage::summary( 'chatbot', 1 );
			$models  = isset( $summary['models'] ) ? (array) $summary['models'] : array();
		}
		if ( ! $models ) {
			echo '<p class="qcwp-empty">' . esc_html__( 'No AI answers recorded yet this month.', 'chatbot' ) . '</p>';
		} else {
			echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr><th>' . esc_html__( 'Provider', 'chatbot' ) . '</th><th>' . esc_html__( 'Model', 'chatbot' ) . '</th><th>' . esc_html__( 'Answers', 'chatbot' ) . '</th><th>' . esc_html__( 'Estimated cost (USD)', 'chatbot' ) . '</th></tr></thead><tbody>';
			$names = wp_list_pluck( self::providers(), 'label' );
			foreach ( $models as $row ) {
				$known           = ! method_exists( '\QuantumCloud\WPBotCore\AI\Providers', 'price_known' ) || \QuantumCloud\WPBotCore\AI\Providers::price_known( (string) $row['provider'], (string) $row['model'] );
				$row['provider'] = isset( $names[ (string) $row['provider'] ] ) ? $names[ (string) $row['provider'] ] : (string) $row['provider'];
				echo '<tr><td>' . esc_html( (string) $row['provider'] ) . '</td><td>' . esc_html( (string) $row['model'] ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['calls'] ) ) . '</td><td>' . ( $known ? esc_html( '$' . number_format_i18n( (float) $row['cost'], 4 ) ) : esc_html__( 'price unknown', 'chatbot' ) ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		$month = Budget::month();
		/* translators: 1: number of conversations, 2: cost per conversation (USD). */
		echo '<p class="qcwp-help">' . esc_html( sprintf( _n( '%1$s conversation with AI answers this month, about $%2$s each.', '%1$s conversations with AI answers this month, about $%2$s each.', (int) $month['conversations'], 'chatbot' ), number_format_i18n( $month['conversations'] ), number_format_i18n( $month['per_conversation'], 4 ) ) ) . '</p></div>';

		if ( \QuantumCloud\Chatbot\Spend\Spend_Screen::available() ) {
			\QuantumCloud\Chatbot\Spend\Spend_Screen::render_settings();
		} else {
			$budget = Budget::budget();
			$cost   = Budget::month_cost();
			echo '<div class="qcwp-card"><h2>' . esc_html__( 'ChatBot monthly budget', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Applies to the chatbot\'s AI answers only.', 'chatbot' ) . '</p><div class="qcwp-two">';
			echo '<div><label class="qcwp-label" for="qcwp-budget">' . esc_html__( 'Budget in US dollars', 'chatbot' ) . '</label><input type="number" min="0" step="0.01" class="qcwp-input" style="width:160px" id="qcwp-budget" name="budget" value="' . esc_attr( (string) $budget ) . '"><p class="qcwp-help">' . esc_html__( '0 = no budget. The site admin gets an email at 80% and 100%. At 100% the chatbot stops using AI until next month.', 'chatbot' ) . '</p></div>';
			if ( $budget > 0 ) {
				$pct = min( 100, (int) round( $cost / $budget * 100 ) );
				/* translators: 1: cost this month, 2: budget (USD). */
				echo '<div><p class="qcwp-help" style="margin-top:14px">' . esc_html__( 'Used this month', 'chatbot' ) . '</p><strong>' . esc_html( sprintf( __( '$%1$s of $%2$s', 'chatbot' ), number_format_i18n( $cost, 2 ), number_format_i18n( $budget, 2 ) ) ) . '</strong><div class="qcwp-bar" role="img" aria-label="' . esc_attr( $pct . '%' ) . '"><span style="width:' . (int) $pct . '%"></span></div></div>';
			}
			echo '</div></div>';
		}

		echo '<div class="qcwp-card">';
		self::toggle( 'cache', Routing::cache_on(), __( 'Answer cache', 'chatbot' ), __( 'When a visitor who is not logged in asks a question that was answered before, the saved answer is used: no AI cost and an instant reply. Never for logged-in visitors, answers that used their personal data, or answers that ran an AI Action.', 'chatbot' ) );
		echo '</div>';

		echo '<div class="qcwp-card">';
		self::toggle( 'rate_limiting', Role_Limits::enabled(), __( 'ChatBot rate limits', 'chatbot' ), __( 'The most chatbot AI answers each user role can get in a period. Leave a limit empty or 0 for no limit.', 'chatbot' ) );
		echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr><th>' . esc_html__( 'Who', 'chatbot' ) . '</th><th>' . esc_html__( 'Most answers', 'chatbot' ) . '</th><th>' . esc_html__( 'Per (hours)', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( Role_Limits::roles() as $role => $name ) {
			list( $max, $window ) = Role_Limits::limit( $role );
			echo '<tr><td>' . esc_html( $name ) . '</td><td><input type="number" min="0" class="qcwp-input" style="width:120px" aria-label="' . esc_attr( $name ) . '" name="limits[' . esc_attr( $role ) . '][max]" value="' . esc_attr( $max > 0 ? (string) $max : '' ) . '"></td><td><input type="number" min="1" max="744" class="qcwp-input" style="width:120px" aria-label="' . esc_attr( $name ) . '" name="limits[' . esc_attr( $role ) . '][hours]" value="' . esc_attr( (string) (int) round( $window / HOUR_IN_SECONDS ) ) . '"></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<label class="qcwp-label" for="qcwp-limit-message">' . esc_html__( 'Message when the limit is reached', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-limit-message" name="limit_message" value="' . esc_attr( Role_Limits::message() ) . '" placeholder="' . esc_attr__( 'You have reached the limit of AI answers for today. Please try again tomorrow.', 'chatbot' ) . '"></div>';
		self::form_close();
	}

	/**
	 * Save a section.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$post    = wp_unslash( $_POST );
		$section = isset( $post['section'] ) && isset( self::sections()[ $post['section'] ] ) ? (string) $post['section'] : 'providers';
		switch ( $section ) {
			case 'answers':
				self::save_answers( $post );
				break;
			case 'limits':
				self::save_limits( $post );
				break;
			case 'providers':
				self::save_providers( $post );
				break;
			case 'data':
				// Not an AI setting: saved answers stay.
				self::save_data( $post );
				wp_safe_redirect( self::url( $section, array( 'updated' => 1 ) ) );
				exit;
		}
		// Saved answers were made with the old settings: start again (the key changes too, but this frees the table).
		\QuantumCloud\Chatbot\Ai\Answer_Cache::clear();
		wp_safe_redirect( self::url( $section, array( 'updated' => 1 ) ) );
		exit;
	}

	/**
	 * Save Providers.
	 *
	 * @param array $post Posted values (unslashed).
	 * @return void
	 */
	public static function save_providers( array $post ) {
		$map       = Provider_Settings::map();
		$providers = self::providers();
		$rows      = isset( $post['providers'] ) && is_array( $post['providers'] ) ? $post['providers'] : array();
		foreach ( $providers as $id => $p ) {
			$row = isset( $rows[ $id ] ) ? (array) $rows[ $id ] : array();
			if ( $p['needs_key'] && null !== $map[ $id ]['key'] ) {
				$typed = isset( $row['key'] ) ? trim( (string) $row['key'] ) : '';
				if ( ! empty( $row['remove'] ) ) {
					update_option( $map[ $id ]['key'], '' );
				} elseif ( '' !== $typed && ! self::is_mask( $typed ) && '' !== self::typed_key( $typed ) ) {
					update_option( $map[ $id ]['key'], Provider_Settings::clean_key( self::typed_key( $typed ) ) );
				}
			}
			if ( isset( $row['model'] ) ) {
				$option = null !== $map[ $id ]['model'] ? $map[ $id ]['model'] : Provider_Settings::new_option( $id, 'model' );
				update_option( $option, sanitize_text_field( (string) $row['model'] ) );
			}
			if ( isset( $row['url'] ) && null !== $map[ $id ]['url'] ) {
				$url = esc_url_raw( trim( (string) $row['url'] ), array( 'http', 'https' ) );
				update_option( $map[ $id ]['url'], 'ollama' === $id ? preg_replace( '#/v1$#', '', rtrim( $url, '/' ) ) : $url );
				if ( 'ollama' === $id ) {
					update_option( 'qcld_ollama_port', '' );
				}
			}
		}//end foreach
		$grok = isset( $rows['grok'] ) ? (array) $rows['grok'] : array();
		if ( isset( $grok['collection'] ) ) {
			update_option( 'qcld_grok_collection_id', sanitize_text_field( (string) $grok['collection'] ) );
		}
		self::save_secret( 'qcld_grok_management_api_key', isset( $grok['management_key'] ) ? (string) $grok['management_key'] : '' );
		$claude = isset( $rows['claude'] ) ? (array) $rows['claude'] : array();
		self::save_secret( 'qcld_voyage_api_key', isset( $claude['voyage_key'] ) ? (string) $claude['voyage_key'] : '' );

		// One provider is on: the primary (WPBot's rule; the old handlers read these flags).
		$primary = isset( $post['primary'] ) ? sanitize_key( (string) $post['primary'] ) : '';
		if ( '' !== $primary && ! isset( $providers[ $primary ] ) && Provider_Settings::answering_provider() !== $primary ) {
			$primary = '';
			// Only a provider offered here, or the one already answering.
		}
		foreach ( $map as $id => $fields ) {
			update_option( $fields['enabled'], $id === $primary ? 1 : 0 );
		}
		if ( '' !== $primary ) {
			// As the old provider screens did: the AI answers, not WordPress site search.
			update_option( 'disable_wp_chatbot_site_search', 1 );
		}
		$short = isset( $post['short_models'] ) && is_array( $post['short_models'] ) ? $post['short_models'] : array();
		Routing::save(
			array(
				'backup'       => isset( $post['backup'] ) && $post['backup'] !== $primary ? (string) $post['backup'] : '',
				'short_words'  => isset( $post['short_words'] ) ? (int) $post['short_words'] : 8,
				'short_models' => array_merge( Routing::short_models(), array_map( 'strval', $short ) ),
			)
		);
	}

	/**
	 * Save a secret option: a typed value replaces it, the mask or an empty field keeps it.
	 *
	 * @param string $option Option.
	 * @param string $typed  Posted value.
	 * @return void
	 */
	private static function save_secret( $option, $typed ) {
		$typed = trim( (string) $typed );
		if ( '' !== $typed && ! self::is_mask( $typed ) && '' !== self::typed_key( $typed ) ) {
			update_option( $option, Provider_Settings::clean_key( self::typed_key( $typed ) ) );
		}
	}

	/**
	 * Save Answers.
	 *
	 * @param array $post Posted values (unslashed).
	 * @return void
	 */
	public static function save_answers( array $post ) {
		if ( ! empty( $post['use_own_address'] ) ) {
			Ai_Settings::use_own_address();
			return;
		}
		Ai_Settings::save(
			array(
				'system_prompt'  => isset( $post['system_prompt'] ) ? sanitize_textarea_field( (string) $post['system_prompt'] ) : '',
				'instructions'   => isset( $post['instructions'] ) ? sanitize_textarea_field( (string) $post['instructions'] ) : '',
				'temperature'    => isset( $post['temperature'] ) ? (string) $post['temperature'] : '',
				'max_tokens'     => isset( $post['max_tokens'] ) ? (string) $post['max_tokens'] : '',
				'stream'         => ! empty( $post['stream'] ),
				'knowledge_base' => ! empty( $post['knowledge_base'] ),
				'page_context'   => ! empty( $post['page_context'] ),
			)
		);
		$scope = isset( $post['scope'] ) ? sanitize_key( (string) $post['scope'] ) : '';
		if ( array_key_exists( $scope, Answer_Scope::choices() ) ) {
			update_option( Answer_Scope::OPTION, $scope );
		}
		update_option( Answer_Scope::CONTACT_OPTION, mb_substr( sanitize_text_field( isset( $post['contact'] ) ? (string) $post['contact'] : '' ), 0, 300 ) );
		update_option( Answer_Scope::DECLINE_OPTION, mb_substr( sanitize_textarea_field( isset( $post['decline'] ) ? (string) $post['decline'] : '' ), 0, 500 ) );
		update_option( 'qcld_wpbot_ai_error_text', mb_substr( sanitize_text_field( isset( $post['error_text'] ) ? (string) $post['error_text'] : '' ), 0, 500 ), false );
		update_option( Knowledge_Step::CITATIONS_OPTION, empty( $post['citations'] ) ? '0' : '1', false );
		if ( ! empty( $post['handoff_sent'] ) ) {
			$labels = array_keys( Safe_Answer::menu_items() );
			$chosen = isset( $post['handoff'] ) ? array_values( array_intersect( array_map( 'sanitize_text_field', (array) $post['handoff'] ), $labels ) ) : array();
			update_option( Safe_Answer::ITEMS_OPTION, $chosen, false );
			update_option( Safe_Answer::MAX_OPTION, isset( $post['handoff_max'] ) ? max( 1, min( 6, (int) $post['handoff_max'] ) ) : 3, false );
		}
		update_option( 'qcld_wpbot_ai_file_upload_enabled', empty( $post['file_upload'] ) ? '0' : '1' );
		if ( isset( $post['file_extensions'] ) ) {
			update_option( 'qcld_wpbot_ai_file_upload_extensions', sanitize_text_field( (string) $post['file_extensions'] ) );
		}
	}

	/**
	 * Chatbot data: what is kept, and the "delete on uninstall" switch.
	 *
	 * @return void
	 */
	private static function render_data() {
		global $wpdb;
		self::form_open( 'data' );
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'What the chatbot keeps', 'chatbot' ) . '</h2>';
		$tables = array(
			\QuantumCloud\Chatbot\Sessions\Schema::messages_table()     => __( 'Chat messages', 'chatbot' ),
			\QuantumCloud\Chatbot\Sessions\Schema::gaps_table()         => __( 'Questions the chatbot could not answer', 'chatbot' ),
			\QuantumCloud\Chatbot\Sessions\Schema::changes_table()      => __( 'Change log and approvals', 'chatbot' ),
			\QuantumCloud\Chatbot\Sessions\Schema::answer_cache_table() => __( 'Answer cache', 'chatbot' ),
		);
		echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr><th>' . esc_html__( 'Data', 'chatbot' ) . '</th><th>' . esc_html__( 'Rows', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( $tables as $table => $label ) {
			$rows = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A count for the admin.
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( null === $rows ? '—' : number_format_i18n( (int) $rows ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'When the plugin is deleted', 'chatbot' ) . '</h2>';
		echo '<label class="qcwp-check"><input type="checkbox" name="delete_on_uninstall" value="1"' . checked( self::delete_on_uninstall(), true, false ) . '><span><strong>' . esc_html__( 'Delete chatbot data when the plugin is deleted', 'chatbot' ) . '</strong><small>' . esc_html__( 'Deletes the data above and the chatbot\'s AI Settings. This happens only when no other WPBot plugin is installed: WPBot Free and WPBot Pro share this data, so it stays while either one is still on the site. Your AI keys and the knowledge base belong to WPBot Core and are not deleted here. Off by default.', 'chatbot' ) . '</small></span></label>';
		echo '</div>';
		self::form_close();
	}

	/**
	 * True when the owner chose to delete the chatbot's data with the plugin.
	 *
	 * @return bool
	 */
	public static function delete_on_uninstall() {
		return '1' === (string) get_option( self::DELETE_OPTION, '' );
	}

	/**
	 * Save Chatbot data.
	 *
	 * @param array $post Posted values (unslashed).
	 * @return void
	 */
	public static function save_data( array $post ) {
		update_option( self::DELETE_OPTION, empty( $post['delete_on_uninstall'] ) ? '' : '1', false );
	}

	/**
	 * Save Limits and cost.
	 *
	 * @param array $post Posted values (unslashed).
	 * @return void
	 */
	public static function save_limits( array $post ) {
		if ( \QuantumCloud\Chatbot\Spend\Spend_Screen::available() ) {
			\QuantumCloud\Chatbot\Spend\Spend_Screen::save( $post );
		} else {
			Budget::set_budget( isset( $post['budget'] ) ? (float) $post['budget'] : 0 );
		}
		Routing::save( array( 'cache' => ! empty( $post['cache'] ) ) );
		Role_Limits::save( ! empty( $post['rate_limiting'] ), isset( $post['limits'] ) && is_array( $post['limits'] ) ? $post['limits'] : array(), isset( $post['limit_message'] ) ? (string) $post['limit_message'] : '' );
	}

	/**
	 * AJAX: test a provider's saved key and model with a tiny request.
	 *
	 * @return void
	 */
	public static function test() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::TEST, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'chatbot' ) ), 403 );
		}
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$settings = Provider_Settings::get( $provider, false );
		$client   = '\QuantumCloud\WPBotCore\AI\Provider_Client';
		$request  = '\QuantumCloud\WPBotCore\AI\Request';
		if ( null === $settings || ! class_exists( $client ) || ! class_exists( $request ) ) {
			wp_send_json_error( array( 'message' => __( 'This provider can\'t be tested here.', 'chatbot' ) ) );
		}
		if ( 'ollama' !== $provider && '' === (string) $settings['key'] ) {
			wp_send_json_error( array( 'message' => __( 'No key saved.', 'chatbot' ) ) );
		}
		$response = $client::send( $provider, (string) $settings['model'], (string) $settings['key'], $request::make( 'You are a connection test.', 'Reply with OK.', array( 'max_tokens' => 16 ) ), (string) $settings['url'] );
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$detail = is_array( $data ) && ! empty( $data['detail'] ) ? (string) $data['detail'] : $response->get_error_message();
			/* translators: %s: the provider's error. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Did not work: %s', 'chatbot' ), '' !== $detail ? $detail : __( 'no reply', 'chatbot' ) ) ) );
		}
		wp_send_json_success();
	}
}
