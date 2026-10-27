<?php
/**
 * Every AI prompt the CRM sends, in one place.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Prompt builders. Each method returns a plain string; filters let site owners tune them.
 */
final class Prompts {

	/**
	 * Tone guidance per writing style.
	 *
	 * @var array<string,string>
	 */
	const STYLES = array(
		'professional' => 'Clear, confident and businesslike. Short paragraphs, no slang, one focused call to action.',
		'friendly'     => 'Warm and conversational, like a note from a helpful person on the team. Contractions are fine; keep it upbeat but not pushy.',
		'formal'       => 'Polite and formal. Complete sentences, no contractions, respectful closing. Suitable for legal, finance or B2B audiences.',
		'humorous'     => 'Light-hearted and witty, with one or two tasteful jokes or playful lines that never distract from the offer. No sarcasm about the reader.',
		'minimalist'   => 'Very short and calm. A heading, two or three short sentences and a single button. Plenty of white space, no decoration.',
	);

	/**
	 * System prompt for the campaign email writer.
	 *
	 * @param string $style       Style key (see STYLES).
	 * @param string $layout_mode simple-html|table-safe.
	 * @return string
	 */
	public static function email_system( $style, $layout_mode ) {
		$tone = isset( self::STYLES[ $style ] ) ? self::STYLES[ $style ] : self::STYLES['professional'];

		if ( 'table-safe' === $layout_mode ) {
			$layout = 'Build the body as nested tables for maximum email-client support: an outer table width="100%" with a light background, '
				. 'an inner centered table width="600" (style="max-width:600px;width:100%") with a white background, role="presentation", '
				. 'cellpadding="0" cellspacing="0" border="0". Put content in <td> cells with padding. Make the button a table cell with a background color '
				. 'containing an <a> tag. It must still read well on a 320px wide phone.';
		} else {
			$layout = 'Use simple semantic HTML: one wrapper <div style="max-width:600px;margin:0 auto;..."> containing headings, paragraphs, '
				. 'an optional bulleted list and one button-styled <a> link. No tables unless the content really is tabular.';
		}

		$rules = array(
			'You are an expert email marketer and copywriter who writes high-converting, spam-filter-friendly marketing emails.',
			'Tone: ' . $tone,
			'Layout: ' . $layout,
			'HTML rules: return only the email body fragment (no <html>, <head>, <body>, <style> or <script> tags). '
				. 'Every element is styled with inline style attributes only. Use web-safe fonts (Arial, Helvetica, sans-serif), font sizes of 14-16px for text, '
				. 'line-height around 1.5, and a single accent color for the button. Images are optional; if you add one, use an https placeholder and include width and alt.',
			'Start with a greeting that uses the merge tag {{first_name}} exactly as written (for example "Hi {{first_name}},").',
			'End with a small grey footer line that contains a link whose href is exactly {{unsubscribe_url}} and whose text is "Unsubscribe".',
			'Allowed merge tags: {{first_name}}, {{last_name}}, {{email}}, {{unsubscribe_url}}. Do not invent other placeholders, prices, dates or facts that the brief does not give; '
				. 'use neutral wording or a clear [placeholder] instead.',
			'Avoid spam triggers: no ALL CAPS sentences, no more than one exclamation mark, no misleading subject lines.',
			'Also write a subject line under 60 characters that matches the email.',
			'Respond with JSON only, no commentary, in exactly this shape: {"subject": "…", "html": "…"}',
		);

		/**
		 * Filters the system prompt used by the AI email writer.
		 *
		 * @param string $prompt      Prompt.
		 * @param string $style       Style.
		 * @param string $layout_mode Layout mode.
		 */
		return (string) apply_filters( 'wpbot_crm/prompts/email_system', implode( "\n\n", $rules ), $style, $layout_mode );
	}

	/**
	 * User prompt for the campaign email writer.
	 *
	 * @param string $brief What the user typed.
	 * @return string
	 */
	public static function email_user( $brief ) {
		$site  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$text  = "Sender / brand: {$site}\n";
		$text .= 'Website: ' . home_url( '/' ) . "\n\n";
		$text .= "Email brief:\n" . trim( (string) $brief ) . "\n\n";
		$text .= 'Write the campaign email now and return the JSON object.';
		return $text;
	}

	/**
	 * System prompt for the connection test.
	 *
	 * @return string
	 */
	public static function test_system() {
		return 'You are a connectivity check. Reply with the single word OK and nothing else.';
	}

	/**
	 * User prompt for the connection test.
	 *
	 * @return string
	 */
	public static function test_user() {
		return 'Connection test from the WPBot email marketing settings. Reply OK.';
	}

	/**
	 * System prompt that turns a prospecting request into lead-search parameters.
	 *
	 * @return string
	 */
	public static function lead_params_system() {
		$prompt = implode(
			"\n",
			array(
				'You convert a B2B prospecting request into search parameters for a business directory (Google Maps style) search.',
				'Return JSON only, with exactly these keys:',
				'{"industry": "", "role": "", "location": "", "company_size": "", "keyword": ""}',
				'- industry: the kind of business to search for, as a short plural noun phrase usable in a maps search (e.g. "dental clinics", "SaaS companies").',
				'- role: the job role of the person to reach. Use one of founder, marketing_vp, sales_vp, hr_director, it_manager when it fits, otherwise a short job title, or "".',
				'- location: a city, region or country exactly as a person would type it in a maps search, or "" for anywhere.',
				'- company_size: one of "", "1-10", "11-50", "51-200", "201-500", "500+".',
				'- keyword: one or two extra words that narrow the search (a product, niche or technology), or "".',
				'Never invent details the request does not imply; use "" instead.',
			)
		);
		return (string) apply_filters( 'wpbot_crm/prompts/lead_params_system', $prompt );
	}

	/**
	 * User prompt for lead-parameter extraction.
	 *
	 * @param string $request Natural-language prospecting request.
	 * @return string
	 */
	public static function lead_params_user( $request ) {
		return "Prospecting request:\n" . trim( (string) $request ) . "\n\nReturn the JSON object.";
	}
}
