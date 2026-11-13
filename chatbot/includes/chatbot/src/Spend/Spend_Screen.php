<?php
/**
 * AI spending screens in AI Settings (rebuild 6):
 * - the spend strip at the top of every AI Settings section (Global, then each feature);
 * - the (i) panel per feature (tasks, the 10 most expensive runs, a 14-day trend);
 * - the "Spending and limits" section: period, limits, pause, alerts;
 * - AI Settings > AI Usage: the report.
 *
 * The numbers come from WPBot Core (Spend, Report); nothing here runs when WPBot Core has no
 * spend API. Every amount is an estimate and says so.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Spend;

use QuantumCloud\Chatbot\Admin\Ai_Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Spending UI.
 */
final class Spend_Screen {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		// The module boots before WPBot Core (plugins_loaded 1): look for the spend API after it.
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 20 );
	}

	/**
	 * Hook the screens when WPBot Core has the spend API.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! self::available() ) {
			return;
		}
		add_filter( 'qcld_wpbot_core/spend_settings_url', array( __CLASS__, 'settings_url' ), 10, 2 );
		add_filter( 'qcld_wpbot_core/spend_report_url', array( __CLASS__, 'report_url' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Whether WPBot Core has the spend API.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( '\QuantumCloud\WPBotCore\Spend\Spend' ) && class_exists( '\QuantumCloud\WPBotCore\Spend\Report' );
	}

	/**
	 * Capability for the spending screens (WPBot Core's).
	 *
	 * @return string
	 */
	public static function capability() {
		return self::available() ? \QuantumCloud\WPBotCore\Spend\Spend::capability() : 'manage_options';
	}

	/**
	 * Spending and limits lives in AI Settings > Spending and limits.
	 *
	 * @param string $url   Default URL.
	 * @param string $group Group id or ''.
	 * @return string
	 */
	public static function settings_url( $url, $group = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Filter signature; one screen for every group (the core adds the anchor).
		return Ai_Settings_Page::url( 'limits' );
	}

	/**
	 * The AI Usage page.
	 *
	 * @return string
	 */
	public static function report_url() {
		return Ai_Settings_Page::url( 'usage' );
	}

	/**
	 * Styles and the (i) panel script on AI Settings and AI Usage.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		if ( strtolower( Ai_Settings_Page::SLUG ) !== $page ) {
			return;
		}
		$file = defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php';
		$ver  = defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1';
		wp_enqueue_style( \QuantumCloud\Chatbot\Admin\Admin_Pages::STYLE, plugins_url( 'assets/admin/pages.css', $file ), array( 'dashicons' ), $ver );
		wp_enqueue_script( 'qcld-wpbot-spend', plugins_url( 'assets/admin/spend.js', $file ), array(), $ver, true );
		wp_localize_script(
			'qcld-wpbot-spend',
			'qcldWpbotSpend',
			array(
				'root'  => esc_url_raw( rest_url( 'wpbot-core/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'loading'  => __( 'Loading…', 'chatbot' ),
					'failed'   => __( 'Could not load the details. Reload the page and try again.', 'chatbot' ),
					'tasks'    => __( 'Tasks this period', 'chatbot' ),
					'task'     => __( 'Task', 'chatbot' ),
					'calls'    => __( 'Calls', 'chatbot' ),
					'tokens'   => __( 'Tokens', 'chatbot' ),
					'cost'     => __( 'Cost (estimated)', 'chatbot' ),
					'model'    => __( 'Model', 'chatbot' ),
					'lastRun'  => __( 'Last run', 'chatbot' ),
					'topRuns'  => __( 'The 10 most expensive runs', 'chatbot' ),
					'when'     => __( 'When', 'chatbot' ),
					'trend'    => __( 'Last 14 days', 'chatbot' ),
					'none'     => __( 'No AI calls this period.', 'chatbot' ),
					'channels' => __( 'By channel', 'chatbot' ),
					'provider' => __( 'By provider', 'chatbot' ),
					'unknown'  => __( 'price unknown', 'chatbot' ),
					'raised'   => __( 'Limit raised for this period.', 'chatbot' ),
					'close'    => __( 'Close', 'chatbot' ),
				),
			)
		);
	}

	/**
	 * The spend strip (top of every AI Settings section).
	 *
	 * @return void
	 */
	public static function render_strip() {
		if ( ! self::available() || ! current_user_can( self::capability() ) ) {
			return;
		}
		$status = \QuantumCloud\WPBotCore\Spend\Spend::status();
		$period = \QuantumCloud\WPBotCore\Spend\Period::label( $status['period']['type'] );
		$cards  = array( $status['global'] );
		foreach ( $status['groups'] as $entry ) {
			if ( $entry['limit'] > 0 || $entry['spent'] > 0 || 'paused' === $entry['state'] || 'chatbot' === $entry['id'] ) {
				$cards[] = $entry;
			}
		}
		echo '<div class="qcwp-spend-strip" role="region" aria-label="' . esc_attr__( 'AI spending this period', 'chatbot' ) . '">';
		foreach ( $cards as $entry ) {
			echo self::card_html( $entry, $period ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card_html().
		}
		echo '</div>';
		/* translators: %s: date the period resets. */
		echo '<p class="qcwp-help qcwp-spend-note">' . esc_html( sprintf( __( 'Estimated from the providers\' list prices. The period resets on %s.', 'chatbot' ), self::date( $status['period']['end'] ) ) ) . ' <a href="' . esc_url( self::report_url() ) . '">' . esc_html__( 'AI Usage report', 'chatbot' ) . '</a></p>';
		self::render_dialog();
	}

	/**
	 * One strip card (pure apart from escaping).
	 *
	 * @param array  $entry  Status entry (id, label, spent, limit, percent, state).
	 * @param string $period Period name.
	 * @return string HTML.
	 */
	public static function card_html( array $entry, $period ) {
		$spent = '$' . number_format_i18n( (float) $entry['spent'], 2 );
		/* translators: 1: spend, 2: limit, 3: period name (Monthly or Weekly). */
		$amount = $entry['limit'] > 0 ? sprintf( __( '%1$s / $%2$s %3$s', 'chatbot' ), $spent, number_format_i18n( (float) $entry['limit'], 2 ), $period ) : sprintf( /* translators: %s: spend. */ __( '%s, no limit', 'chatbot' ), $spent );
		$width  = $entry['limit'] > 0 ? min( 100, max( 0, (int) $entry['percent'] ) ) : 0;
		$state  = in_array( $entry['state'], array( 'ok', 'warn', 'over', 'paused' ), true ) ? $entry['state'] : 'ok';
		$labels = array(
			'warn'   => __( 'Close to the limit', 'chatbot' ),
			'over'   => __( 'Limit reached', 'chatbot' ),
			'paused' => __( 'Paused', 'chatbot' ),
		);
		$html   = '<div class="qcwp-spend-card is-' . esc_attr( $state ) . '" id="spend-' . esc_attr( $entry['id'] ) . '">';
		$html  .= '<div class="qcwp-spend-top"><span class="qcwp-spend-name">' . esc_html( $entry['label'] ) . '</span>';
		if ( 'global' !== $entry['id'] ) {
			/* translators: %s: feature name. */
			$html .= '<button type="button" class="qcwp-spend-info" data-spend-group="' . esc_attr( $entry['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Details of %s', 'chatbot' ), $entry['label'] ) ) . '"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></button>';
		}
		$html .= '</div><strong class="qcwp-spend-amount">' . esc_html( $amount ) . '</strong>';
		$html .= '<div class="qcwp-spend-bar" role="img" aria-label="' . esc_attr( $entry['limit'] > 0 ? $width . '%' : __( 'No limit', 'chatbot' ) ) . '"><span style="width:' . (int) $width . '%"></span></div>';
		if ( isset( $labels[ $state ] ) ) {
			$html .= '<span class="qcwp-spend-state">' . esc_html( $labels[ $state ] ) . '</span>';
			if ( 'over' === $state ) {
				$html .= ' <button type="button" class="qcwp-btn is-small" data-spend-raise="' . esc_attr( $entry['id'] ) . '">' . esc_html__( 'Raise by 50% this period', 'chatbot' ) . '</button>';
			}
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * The (i) panel (filled by assets/admin/spend.js).
	 *
	 * @return void
	 */
	private static function render_dialog() {
		echo '<dialog class="qcwp-spend-dialog" id="qcwp-spend-dialog" aria-labelledby="qcwp-spend-dialog-title"><div class="qcwp-spend-dialog-head"><h2 id="qcwp-spend-dialog-title"></h2><button type="button" class="qcwp-btn is-small" data-spend-close>' . esc_html__( 'Close', 'chatbot' ) . '</button></div><div class="qcwp-spend-dialog-body" aria-live="polite"></div></dialog>';
	}

	/**
	 * "Spending and limits" cards (AI Settings > Spending and limits, inside its form).
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! self::available() ) {
			return;
		}
		$all    = \QuantumCloud\WPBotCore\Spend\Spend_Settings::all();
		$status = \QuantumCloud\WPBotCore\Spend\Spend::status();
		$days   = array(
			1 => __( 'Monday', 'chatbot' ),
			2 => __( 'Tuesday', 'chatbot' ),
			3 => __( 'Wednesday', 'chatbot' ),
			4 => __( 'Thursday', 'chatbot' ),
			5 => __( 'Friday', 'chatbot' ),
			6 => __( 'Saturday', 'chatbot' ),
			7 => __( 'Sunday', 'chatbot' ),
		);

		echo '<div class="qcwp-card" id="spend-features"><h2>' . esc_html__( 'AI spending limits', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Limits for all of WPBot\'s AI features, in US dollars (estimated). 0 = no limit. At a limit the chatbot answers without AI (your saved answers, FAQ and site search, then your contact options), background jobs pause until the reset, and other features show "Limit reached".', 'chatbot' ) . '</p>';
		echo '<div class="qcwp-two"><div><label class="qcwp-label" for="qcwp-spend-period">' . esc_html__( 'Period', 'chatbot' ) . '</label><select class="qcwp-input" id="qcwp-spend-period" name="spend[period]">';
		foreach ( array( 'monthly' => __( 'Monthly', 'chatbot' ), 'weekly' => __( 'Weekly', 'chatbot' ) ) as $value => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $all['period'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div><div><label class="qcwp-label" for="qcwp-spend-day">' . esc_html__( 'Resets on', 'chatbot' ) . '</label>';
		echo '<input type="number" min="1" max="28" class="qcwp-input qcwp-spend-monthly" style="width:90px" id="qcwp-spend-day" name="spend[reset_day]" value="' . esc_attr( (string) (int) $all['reset_day'] ) . '" aria-label="' . esc_attr__( 'Day of the month (1–28)', 'chatbot' ) . '"> ';
		echo '<select class="qcwp-input qcwp-spend-weekly" name="spend[reset_weekday]" aria-label="' . esc_attr__( 'Day of the week', 'chatbot' ) . '">';
		foreach ( $days as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (int) $all['reset_weekday'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="qcwp-help">' . esc_html__( 'Monthly: day of the month (1–28). Weekly: day of the week. Site time zone.', 'chatbot' ) . '</p></div></div>';

		echo '<div class="qcwp-table-wrap"><table class="qcwp-table qcwp-spend-table"><thead><tr><th>' . esc_html__( 'Feature', 'chatbot' ) . '</th><th>' . esc_html__( 'Spent this period', 'chatbot' ) . '</th><th>' . esc_html__( 'Limit (USD)', 'chatbot' ) . '</th><th>' . esc_html__( 'Pause AI', 'chatbot' ) . '</th></tr></thead><tbody>';
		echo '<tr id="spend-global"><td><strong>' . esc_html__( 'All AI (global limit)', 'chatbot' ) . '</strong></td><td>' . esc_html( '$' . number_format_i18n( (float) $status['global']['spent'], 2 ) ) . '</td><td><input type="number" min="0" step="0.01" class="qcwp-input" style="width:120px" name="spend[global]" value="' . esc_attr( self::number( $all['global'] ) ) . '" aria-label="' . esc_attr__( 'Global limit', 'chatbot' ) . '"></td><td></td></tr>';
		foreach ( $status['groups'] as $id => $entry ) {
			$limit  = isset( $all['limits'][ $id ] ) ? (float) $all['limits'][ $id ] : 0.0;
			$paused = in_array( $id, (array) $all['paused'], true );
			echo '<tr id="spend-' . esc_attr( $id ) . '"><td>' . esc_html( $entry['label'] ) . '</td><td>' . esc_html( '$' . number_format_i18n( (float) $entry['spent'], 2 ) ) . '</td><td><input type="number" min="0" step="0.01" class="qcwp-input" style="width:120px" name="spend[limits][' . esc_attr( $id ) . ']" value="' . esc_attr( self::number( $limit ) ) . '" aria-label="' . esc_attr( $entry['label'] ) . '"></td><td><input type="checkbox" name="spend[paused][]" value="' . esc_attr( $id ) . '"' . checked( $paused, true, false ) . ' aria-label="' . esc_attr( $entry['label'] ) . '"></td></tr>';
		}
		echo '</tbody></table></div><p class="qcwp-help">' . esc_html__( 'Limits are checked before each AI call, so a few calls already running can pass a limit slightly.', 'chatbot' ) . '</p></div>';

		echo '<div class="qcwp-card" id="spend-alerts"><h2>' . esc_html__( 'Spending alerts', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'Emails to every administrator when the global spend passes a threshold, and when a feature reaches its limit. Each alert is sent once per period.', 'chatbot' ) . '</p><div class="qcwp-two">';
		echo '<div><label class="qcwp-label" for="qcwp-spend-thresholds">' . esc_html__( 'Thresholds (% of the global limit)', 'chatbot' ) . '</label><input type="text" class="qcwp-input" id="qcwp-spend-thresholds" name="spend[thresholds]" value="' . esc_attr( implode( ', ', array_map( 'intval', (array) $all['thresholds'] ) ) ) . '"><p class="qcwp-help">' . esc_html__( 'Up to 5 values, for example 50, 80, 100.', 'chatbot' ) . '</p></div>';
		echo '<div><label class="qcwp-label" for="qcwp-spend-prefix">' . esc_html__( 'Email subject prefix', 'chatbot' ) . '</label><input type="text" maxlength="40" class="qcwp-input" id="qcwp-spend-prefix" name="spend[prefix]" value="' . esc_attr( (string) $all['prefix'] ) . '" placeholder="' . esc_attr__( 'For example [Shop A]', 'chatbot' ) . '"></div></div>';
		echo '<label class="qcwp-label" for="qcwp-spend-recipients">' . esc_html__( 'Also send to', 'chatbot' ) . '</label><textarea class="qcwp-input" rows="2" id="qcwp-spend-recipients" name="spend[recipients]" placeholder="' . esc_attr__( 'One email address per line (up to 10)', 'chatbot' ) . '">' . esc_textarea( implode( "\n", (array) $all['recipients'] ) ) . '</textarea></div>';

		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Chatbot message while AI is paused', 'chatbot' ) . '</h2><p class="qcwp-help">' . esc_html__( 'When a limit pauses the chatbot\'s AI, it answers from your saved answers, FAQ and site search. When nothing matches, visitors see this message with your contact options (email, live chat or a support ticket when they are on). Leave it empty to use your usual "no result" message. Visitors never see an error.', 'chatbot' ) . '</p><textarea class="qcwp-input" rows="2" name="spend_message" aria-label="' . esc_attr__( 'Chatbot message while AI is paused', 'chatbot' ) . '">' . esc_textarea( Fallback::message() ) . '</textarea></div>';

		$billing = \QuantumCloud\WPBotCore\Spend\Prices::billing_urls();
		$names   = wp_list_pluck( Ai_Settings_Page::providers(), 'label' );
		echo '<p class="qcwp-help">' . esc_html__( 'Your exact charges are on each provider\'s billing page:', 'chatbot' ) . ' ';
		$links = array();
		foreach ( $billing as $provider => $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( isset( $names[ $provider ] ) ? $names[ $provider ] : $provider ) . '</a>';
		}
		echo implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link escaped above.
	}

	/**
	 * Save the Spending and limits cards.
	 *
	 * @param array $post Posted values (unslashed).
	 * @return void
	 */
	public static function save( array $post ) {
		if ( isset( $post['spend_message'] ) ) {
			Fallback::save_message( (string) $post['spend_message'] );
		}
		if ( ! self::available() || ! isset( $post['spend'] ) || ! is_array( $post['spend'] ) ) {
			return;
		}
		\QuantumCloud\WPBotCore\Spend\Spend_Settings::update( self::changes( $post['spend'] ) );
	}

	/**
	 * Posted form values to Spend_Settings changes (pure; Spend_Settings sanitizes each value).
	 *
	 * @param array $spend Posted spend[] values.
	 * @return array
	 */
	public static function changes( array $spend ) {
		$changes = array();
		foreach ( array( 'period', 'reset_day', 'reset_weekday', 'global', 'prefix', 'recipients' ) as $key ) {
			if ( isset( $spend[ $key ] ) && is_scalar( $spend[ $key ] ) ) {
				$changes[ $key ] = (string) $spend[ $key ];
			}
		}
		if ( isset( $spend['limits'] ) && is_array( $spend['limits'] ) ) {
			$changes['limits'] = array_map( 'floatval', array_filter( $spend['limits'], 'is_scalar' ) );
		}
		// Unchecked boxes are not posted: an empty list means nothing is paused.
		$changes['paused'] = isset( $spend['paused'] ) && is_array( $spend['paused'] ) ? array_values( array_filter( $spend['paused'], 'is_scalar' ) ) : array();
		if ( isset( $spend['thresholds'] ) && is_scalar( $spend['thresholds'] ) ) {
			$changes['thresholds'] = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $spend['thresholds'] ) ), 'strlen' ) );
		}
		return $changes;
	}

	/**
	 * AI Settings > AI Usage: the report (the spend strip is already at the top of the page).
	 *
	 * @return void
	 */
	public static function render_usage() {
		if ( ! self::available() || ! current_user_can( self::capability() ) ) {
			return;
		}
		$range = self::range( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A read-only filter.
		$data  = \QuantumCloud\WPBotCore\Spend\Report::report( $range['from'], $range['to'] );

		echo '<form method="get" class="qcwp-card qcwp-spend-range"><input type="hidden" name="page" value="' . esc_attr( Ai_Settings_Page::SLUG ) . '"><input type="hidden" name="section" value="usage"><label class="qcwp-label" for="qcwp-range">' . esc_html__( 'Show', 'chatbot' ) . '</label><select class="qcwp-input" id="qcwp-range" name="range">';
		foreach ( self::ranges() as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $range['id'], $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <input type="date" class="qcwp-input" name="from" value="' . esc_attr( $range['from'] ) . '" aria-label="' . esc_attr__( 'From', 'chatbot' ) . '"> <input type="date" class="qcwp-input" name="to" value="' . esc_attr( $range['to'] ) . '" aria-label="' . esc_attr__( 'To', 'chatbot' ) . '"> <button type="submit" class="qcwp-btn">' . esc_html__( 'Show', 'chatbot' ) . '</button>';
		/**
		 * Extra report actions (WPBot Pro adds CSV export).
		 *
		 * @param array $range from, to.
		 */
		do_action( 'qcld_wpbot_chat/spend_report_actions', $range );
		echo '</form>';

		/* translators: 1: total cost, 2: number of AI calls. */
		echo '<div class="qcwp-card"><h2>' . esc_html( sprintf( __( '$%1$s in %2$s AI calls (estimated)', 'chatbot' ), number_format_i18n( (float) $data['totals']['cost'], 2 ), number_format_i18n( (int) $data['totals']['calls'] ) ) ) . '</h2>';
		echo self::chart_svg( $data['days'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from numbers and escaped labels.
		if ( $data['net']['calls'] > 0 ) {
			/* translators: 1: number of calls, 2: cost. */
			echo '<p class="qcwp-help">' . esc_html( sprintf( __( '%1$s calls ($%2$s) were counted by the safety net: they come from code that does not report its feature yet. They count towards the global limit.', 'chatbot' ), number_format_i18n( $data['net']['calls'] ), number_format_i18n( $data['net']['cost'], 2 ) ) ) . '</p>';
		}
		if ( $data['unknown_price'] > 0 ) {
			/* translators: %s: number of calls. */
			echo '<p class="qcwp-help">' . esc_html( sprintf( __( '%s calls used a model without a known price; they are counted at $0. Check your provider\'s billing page.', 'chatbot' ), number_format_i18n( $data['unknown_price'] ) ) ) . '</p>';
		}
		echo '</div>';

		self::table( __( 'By feature', 'chatbot' ), array( __( 'Feature', 'chatbot' ), __( 'Calls', 'chatbot' ), __( 'Cost (estimated)', 'chatbot' ) ), $data['groups'], array( 'label', 'calls', 'cost' ) );
		self::table( __( 'By task', 'chatbot' ), array( __( 'Task', 'chatbot' ), __( 'Calls', 'chatbot' ), __( 'Cost (estimated)', 'chatbot' ) ), $data['features'], array( 'label', 'calls', 'cost' ) );
		self::table( __( 'By model', 'chatbot' ), array( __( 'Provider', 'chatbot' ), __( 'Model', 'chatbot' ), __( 'Calls', 'chatbot' ), __( 'Tokens in', 'chatbot' ), __( 'Tokens out', 'chatbot' ), __( 'Cost (estimated)', 'chatbot' ) ), $data['models'], array( 'provider', 'model', 'calls', 'tokens_in', 'tokens_out', 'cost' ) );
		if ( isset( $data['providers'] ) ) {
			self::table( __( 'By provider', 'chatbot' ), array( __( 'Provider', 'chatbot' ), __( 'Calls', 'chatbot' ), __( 'Cost (estimated)', 'chatbot' ) ), $data['providers'], array( 'name', 'calls', 'cost' ) );
		}
		if ( isset( $data['channels'] ) ) {
			self::table( __( 'By channel', 'chatbot' ), array( __( 'Channel', 'chatbot' ), __( 'Calls', 'chatbot' ), __( 'Cost (estimated)', 'chatbot' ) ), $data['channels'], array( 'name', 'calls', 'cost' ) );
		}
	}

	/**
	 * Date range from the query (pure apart from the clock).
	 *
	 * @param array $query Query values.
	 * @return array{id: string, from: string, to: string}
	 */
	public static function range( array $query ) {
		$today  = substr( current_time( 'mysql' ), 0, 10 );
		$id     = isset( $query['range'] ) ? sanitize_key( (string) $query['range'] ) : 'period';
		$period = \QuantumCloud\WPBotCore\Spend\Period::current();
		switch ( $id ) {
			case 'last_period':
				$prev = \QuantumCloud\WPBotCore\Spend\Period::previous();
				return array(
					'id'   => $id,
					'from' => $prev['start'],
					'to'   => gmdate( 'Y-m-d', strtotime( $prev['end'] . ' -1 day' ) ),
				);
			case '7':
			case '30':
			case '90':
				return array(
					'id'   => $id,
					'from' => gmdate( 'Y-m-d', strtotime( $today . ' -' . ( (int) $id - 1 ) . ' days' ) ),
					'to'   => $today,
				);
			case 'custom':
				$from = isset( $query['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $query['from'] ) ? (string) $query['from'] : $period['start'];
				$to   = isset( $query['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $query['to'] ) ? (string) $query['to'] : $today;
				if ( $to < $from ) {
					list( $from, $to ) = array( $to, $from );
				}
				// At most 400 days.
				if ( strtotime( $to ) - strtotime( $from ) > 400 * DAY_IN_SECONDS ) {
					$from = gmdate( 'Y-m-d', strtotime( $to . ' -400 days' ) );
				}
				return array(
					'id'   => $id,
					'from' => $from,
					'to'   => $to,
				);
			default:
				return array(
					'id'   => 'period',
					'from' => $period['start'],
					'to'   => $today,
				);
		}//end switch
	}

	/**
	 * Range choices.
	 *
	 * @return array<string,string>
	 */
	private static function ranges() {
		return array(
			'period'      => __( 'This period', 'chatbot' ),
			'last_period' => __( 'Last period', 'chatbot' ),
			'7'           => __( 'Last 7 days', 'chatbot' ),
			'30'          => __( 'Last 30 days', 'chatbot' ),
			'90'          => __( 'Last 90 days', 'chatbot' ),
			'custom'      => __( 'Custom dates', 'chatbot' ),
		);
	}

	/**
	 * Daily cost as an inline SVG bar chart (no library; pure).
	 *
	 * @param array $days Rows: day, calls, cost.
	 * @return string
	 */
	public static function chart_svg( array $days ) {
		if ( array() === $days ) {
			return '<p class="qcwp-empty">' . esc_html__( 'No AI calls in this range.', 'chatbot' ) . '</p>';
		}
		$max   = 0.0;
		$count = count( $days );
		foreach ( $days as $day ) {
			$max = max( $max, (float) $day['cost'] );
		}
		$width = max( 1, $count ) * 14;
		$svg   = '<svg class="qcwp-spend-chart" viewBox="0 0 ' . (int) $width . ' 120" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__( 'Daily AI cost', 'chatbot' ) . '">';
		foreach ( array_values( $days ) as $i => $day ) {
			$height = $max > 0 ? max( 1, (int) round( (float) $day['cost'] / $max * 110 ) ) : 1;
			/* translators: 1: date, 2: cost, 3: calls. */
			$title = sprintf( __( '%1$s: $%2$s, %3$s calls', 'chatbot' ), self::date( (string) $day['day'] ), number_format_i18n( (float) $day['cost'], 4 ), number_format_i18n( (int) $day['calls'] ) );
			$svg  .= '<rect x="' . (int) ( $i * 14 + 2 ) . '" y="' . (int) ( 120 - $height ) . '" width="10" height="' . (int) $height . '" rx="2"><title>' . esc_html( $title ) . '</title></rect>';
		}
		return $svg . '</svg>';
	}

	/**
	 * A report table.
	 *
	 * @param string   $title   Title.
	 * @param string[] $headers Headers.
	 * @param array    $rows    Rows.
	 * @param string[] $keys    Columns.
	 * @return void
	 */
	private static function table( $title, array $headers, array $rows, array $keys ) {
		echo '<div class="qcwp-card"><h2>' . esc_html( $title ) . '</h2>';
		if ( array() === $rows ) {
			echo '<p class="qcwp-empty">' . esc_html__( 'Nothing in this range.', 'chatbot' ) . '</p></div>';
			return;
		}
		echo '<div class="qcwp-table-wrap"><table class="qcwp-table"><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $keys as $key ) {
				$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
				if ( 'cost' === $key ) {
					$value = '$' . number_format_i18n( (float) $value, 4 );
				} elseif ( in_array( $key, array( 'calls', 'tokens_in', 'tokens_out' ), true ) ) {
					$value = number_format_i18n( (int) $value );
				}
				echo '<td>' . esc_html( (string) $value ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * A date in the site's format.
	 *
	 * @param string $ymd Y-m-d.
	 * @return string
	 */
	private static function date( $ymd ) {
		$time = strtotime( $ymd . ' 12:00:00' );
		return false === $time ? $ymd : date_i18n( (string) get_option( 'date_format', 'Y-m-d' ), $time );
	}

	/**
	 * A limit for an input ('' for 0).
	 *
	 * @param float $usd Amount.
	 * @return string
	 */
	private static function number( $usd ) {
		return (float) $usd > 0 ? (string) round( (float) $usd, 2 ) : '0';
	}
}
