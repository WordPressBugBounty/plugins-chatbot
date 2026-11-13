<?php
/**
 * Suggested privacy-policy text (Settings > Privacy > Policy Guide).
 *
 * Recordings are anonymous: no names, emails, user IDs or IP addresses are
 * stored, so there is nothing to export for a person's email address.
 * Owners can delete single recordings or everything from the Insights screen.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Privacy;

use QuantumCloud\Insights\AI\AI_Settings;
use QuantumCloud\Insights\AI\Providers;
use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;

defined( 'ABSPATH' ) || exit;

final class Privacy_Tools {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * Name shown in Tools > Export / Erase Personal Data.
	 *
	 * @return string
	 */
	private function name() {
		$host = Module::instance()->host();
		return $host ? $host->get_menu_title() : __( 'Insights', 'chatbot' );
	}

	/**
	 * Register the personal-data exporter.
	 *
	 * @param array<string,array<string,mixed>> $exporters Exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public function exporters( $exporters ) {
		$exporters['qcin-insights'] = array(
			'exporter_friendly_name' => $this->name(),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal-data eraser.
	 *
	 * @param array<string,array<string,mixed>> $erasers Erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public function erasers( $erasers ) {
		$erasers['qcin-insights'] = array(
			'eraser_friendly_name' => $this->name(),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Recordings are not linked to email addresses or user accounts, so
	 * there is never anything to export for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page.
	 * @return array<string,mixed>
	 */
	public function export( $email, $page = 1 ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * Nothing is linked to an email address; say so in the erase report.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page.
	 * @return array<string,mixed>
	 */
	public function erase( $email, $page = 1 ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(
				sprintf(
					/* translators: %s: feature name, e.g. "Site Insight". */
					__( '%s recordings are anonymous and not linked to email addresses or user accounts, so there is nothing to erase for this person. You can delete single recordings or all recordings from its screen.', 'chatbot' ),
					$this->name()
				),
			),
			'done'           => true,
		);
	}

	/**
	 * Add suggested policy text.
	 */
	public function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$host = Module::instance()->host();
		$name = $host ? $host->get_name() . ' Insights' : 'Insights';

		$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for sites that turn on session recording:', 'chatbot' ) . '</p>';
		$text .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'chatbot' ) . ' </strong>';
		$text .= esc_html__( 'To improve this website, we record how visitors use our pages: mouse movements, clicks, scrolling and the pages visited. Text typed into forms is hidden before anything is saved. Recordings are stored on our own server, are not linked to your name or email address, do not include your IP address, and are deleted automatically after a fixed period.', 'chatbot' );
		$text .= ' ' . sprintf(
			/* translators: %d: number of days. */
			esc_html__( 'Recordings are currently kept for %d days.', 'chatbot' ),
			(int) Settings::get( 'retention_days' )
		) . '</p>';

		$ai = AI_Settings::all();
		if ( $ai['enabled'] ) {
			$provider = Providers::get( (string) $ai['provider'] );
			$text    .= '<p>' . sprintf(
				/* translators: %s: AI provider name, e.g. "OpenAI". */
				esc_html__( 'We may use %s, an AI service, to summarize recorded visits. Only a text description of the visit is sent (pages visited, buttons and links clicked, and errors). Text typed into forms is never sent, and email addresses and phone numbers are removed first.', 'chatbot' ),
				esc_html( $provider ? (string) $provider['label'] : (string) $ai['provider'] )
			) . '</p>';
		}

		/**
		 * Suggested privacy-policy text.
		 *
		 * @param string $text HTML.
		 */
		wp_add_privacy_policy_content( $name, wp_kses_post( apply_filters( 'qcin/privacy_policy_text', $text ) ) );
	}
}
