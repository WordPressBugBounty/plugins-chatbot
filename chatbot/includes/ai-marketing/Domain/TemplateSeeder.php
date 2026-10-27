<?php
/**
 * Built-in starter email templates.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds six starter templates written for WPBot. Idempotent: a built-in
 * template that already exists (by name) is left untouched.
 */
final class TemplateSeeder {

	/**
	 * Insert missing built-in templates.
	 *
	 * @return int Number of templates inserted.
	 */
	public static function seed() {
		$repo     = new TemplateRepository();
		$inserted = 0;
		foreach ( self::templates() as $template ) {
			if ( $repo->find_builtin_by_name( $template['name'] ) ) {
				continue;
			}
			$template['is_builtin'] = true;
			if ( $repo->insert( $template ) ) {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Template definitions.
	 *
	 * @return array[]
	 */
	public static function templates() {
		return array(
			array(
				'name'         => 'Welcome Aboard',
				'category'     => 'Welcome',
				'subject'      => 'Welcome, {{first_name}}! Here is what happens next',
				'content_html' => self::shell(
					'#0f766e',
					'Welcome to the community',
					'<p style="margin:0 0 16px;">Hi {{first_name}},</p>'
					. '<p style="margin:0 0 16px;">Thanks for joining us. You will hear from us when there is something genuinely useful to share: practical tips, early news and the occasional member-only offer.</p>'
					. '<p style="margin:0 0 24px;">To get started, take a look around our site and tell us what you would like to see more of.</p>'
					. self::button( '#0f766e', 'Explore the site', '{{site_url}}' )
					. '<p style="margin:24px 0 0;">Glad to have you here,<br>The {{site_name}} team</p>'
				),
			),
			array(
				'name'         => 'Monthly Newsletter',
				'category'     => 'Newsletter',
				'subject'      => 'This month at {{site_name}}',
				'content_html' => self::shell(
					'#1d4ed8',
					'The monthly roundup',
					'<p style="margin:0 0 20px;">Hello {{first_name}}, here are the highlights you may have missed.</p>'
					. self::story( 'Featured story', 'Write two or three sentences that sum up your main piece of news and why it matters to your readers.', 'Read the full story' )
					. self::story( 'Quick tip', 'Share one small, practical idea your readers can put to use today.', 'See more tips' )
					. self::story( 'Coming up', 'Mention an upcoming date, release or event so readers can plan ahead.', 'Add it to my calendar' )
				),
			),
			array(
				'name'         => 'Seasonal Sale',
				'category'     => 'Promotional',
				'subject'      => '{{first_name}}, our sale starts now',
				'content_html' => self::shell(
					'#b91c1c',
					'Save 25% this week only',
					'<p style="margin:0 0 16px;text-align:center;font-size:17px;">Hi {{first_name}}, our biggest offer of the season is live.</p>'
					. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;"><tr><td align="center" style="border:2px dashed #b91c1c;border-radius:6px;padding:18px;">'
					. '<span style="display:block;font-size:13px;color:#6b7280;letter-spacing:1px;">USE CODE AT CHECKOUT</span>'
					. '<span style="display:block;font-size:28px;font-weight:bold;color:#b91c1c;letter-spacing:3px;">SAVE25</span></td></tr></table>'
					. '<div style="text-align:center;">' . self::button( '#b91c1c', 'Shop the sale', '{{site_url}}' ) . '</div>'
					. '<p style="margin:24px 0 0;font-size:13px;color:#6b7280;text-align:center;">Offer ends Sunday at midnight. Cannot be combined with other discounts.</p>'
				),
			),
			array(
				'name'         => 'Product Launch',
				'category'     => 'Marketing',
				'subject'      => 'Introducing something new from {{site_name}}',
				'content_html' => self::shell(
					'#7c3aed',
					'Meet our newest release',
					'<p style="margin:0 0 16px;">Hi {{first_name}},</p>'
					. '<p style="margin:0 0 20px;">We have been working on this for months and we are excited to finally share it with you.</p>'
					. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;"><tr>'
					. self::feature( 'Faster', 'Get more done in fewer steps.' )
					. self::feature( 'Simpler', 'A cleaner design that stays out of your way.' )
					. self::feature( 'Smarter', 'Helpful defaults, tuned to how you work.' )
					. '</tr></table>'
					. '<div style="text-align:center;">' . self::button( '#7c3aed', 'See it in action', '{{site_url}}' ) . '</div>'
				),
			),
			array(
				'name'         => 'We Miss You',
				'category'     => 'Marketing',
				'subject'      => 'It has been a while, {{first_name}}',
				'content_html' => self::shell(
					'#ea580c',
					'Still interested?',
					'<p style="margin:0 0 16px;">Hi {{first_name}},</p>'
					. '<p style="margin:0 0 16px;">We noticed you have not opened our emails lately. No hard feelings, inboxes get busy.</p>'
					. '<p style="margin:0 0 24px;">If you would still like to hear from us, click below and we will keep you on the list. If not, you can <a href="{{unsubscribe_url}}" style="color:#ea580c;">unsubscribe here</a> at any time.</p>'
					. self::button( '#ea580c', 'Yes, keep me subscribed', '{{site_url}}' )
				),
			),
			array(
				'name'         => 'Event Invitation',
				'category'     => 'Notification',
				'subject'      => 'You are invited, {{first_name}}',
				'content_html' => self::shell(
					'#0e7490',
					'Join us live',
					'<p style="margin:0 0 16px;">Hi {{first_name}},</p>'
					. '<p style="margin:0 0 20px;">We would love for you to join our next live session, with practical demos and an open Q&amp;A.</p>'
					. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#ecfeff;border-radius:6px;">'
					. '<tr><td style="padding:16px 20px;font-size:14px;line-height:1.8;"><strong>Date:</strong> Thursday, 10:00 AM<br><strong>Where:</strong> Online (link sent after you register)<br><strong>Length:</strong> 45 minutes</td></tr></table>'
					. self::button( '#0e7490', 'Save my seat', '{{site_url}}' )
					. '<p style="margin:24px 0 0;font-size:13px;color:#6b7280;">Can not make it? Register anyway and we will send you the recording.</p>'
				),
			),
		);
	}

	/**
	 * Responsive 600px single-column shell with a coloured header band.
	 *
	 * @param string $color   Accent colour.
	 * @param string $heading Header text.
	 * @param string $inner   Body HTML.
	 * @return string
	 */
	private static function shell( $color, $heading, $inner ) {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">'
			. '<tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Helvetica,Arial,sans-serif;color:#1f2937;">'
			. '<tr><td style="background:' . $color . ';padding:28px 32px;color:#ffffff;font-size:24px;font-weight:bold;line-height:1.3;">' . $heading . '</td></tr>'
			. '<tr><td style="padding:32px;font-size:15px;line-height:1.6;">' . $inner . '</td></tr>'
			. '<tr><td style="padding:16px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">'
			. 'You are receiving this because you subscribed at {{site_name}}. <a href="{{unsubscribe_url}}" style="color:#9ca3af;">Unsubscribe</a>'
			. '</td></tr></table></td></tr></table>';
	}

	/**
	 * Bullet-proof button.
	 *
	 * @param string $color Background colour.
	 * @param string $label Label.
	 * @param string $href  Link.
	 * @return string
	 */
	private static function button( $color, $label, $href ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr><td style="background:' . $color . ';border-radius:5px;">'
			. '<a href="' . $href . '" style="display:inline-block;padding:13px 26px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">' . $label . '</a>'
			. '</td></tr></table>';
	}

	/**
	 * Newsletter story block.
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @param string $link  Link label.
	 * @return string
	 */
	private static function story( $title, $text, $link ) {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-left:4px solid #1d4ed8;">'
			. '<tr><td style="padding:4px 0 4px 16px;">'
			. '<h2 style="margin:0 0 6px;font-size:18px;color:#111827;">' . $title . '</h2>'
			. '<p style="margin:0 0 8px;">' . $text . '</p>'
			. '<a href="{{site_url}}" style="color:#1d4ed8;font-weight:bold;text-decoration:none;">' . $link . ' &rarr;</a>'
			. '</td></tr></table>';
	}

	/**
	 * One feature column (stacks on narrow screens via inline-block).
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @return string
	 */
	private static function feature( $title, $text ) {
		return '<td valign="top" style="width:33%;padding:0 6px;text-align:center;">'
			. '<div style="background:#f5f3ff;border-radius:6px;padding:16px 10px;">'
			. '<strong style="display:block;color:#7c3aed;font-size:16px;margin-bottom:6px;">' . $title . '</strong>'
			. '<span style="font-size:13px;color:#4b5563;">' . $text . '</span></div></td>';
	}
}
