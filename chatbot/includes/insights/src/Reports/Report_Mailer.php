<?php
/**
 * Renders the email report (HTML and a plain-text copy) and sends it with
 * wp_mail(). Colours come from the host's brand tokens; the credit line
 * and an optional upgrade line come from the host (Reports::branding()).
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Reports;

use QuantumCloud\Insights\Admin\Admin_Controller;
use QuantumCloud\Insights\Module;

defined( 'ABSPATH' ) || exit;

final class Report_Mailer {

	/**
	 * Facts from Report_Builder::facts().
	 *
	 * @var array<string,mixed>
	 */
	private $facts;

	/**
	 * Colours.
	 *
	 * @var array<string,string>
	 */
	private $c;

	/**
	 * Credit and upgrade line.
	 *
	 * @var array<string,string>
	 */
	private $brand;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $facts Facts.
	 */
	public function __construct( array $facts ) {
		$this->facts = $facts;
		$this->brand = Reports::branding();
		$this->c     = self::colours();
	}

	/**
	 * Subject line.
	 *
	 * @return string
	 */
	public function subject() {
		$t    = $this->facts['totals'];
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site = '' !== trim( $site ) ? $site : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $t['sessions'] ) {
			/* translators: 1: site name, 2: report title, e.g. "WPBot Site Insight weekly report" */
			return sprintf( __( '[%1$s] %2$s: no visits', 'chatbot' ), $site, $this->title() );
		}
		return sprintf(
			/* translators: 1: site name, 2: report title, 3: number of visits, 4: percent of visits with frustration */
			_n( '[%1$s] %2$s: %3$s visit, %4$s%% frustrated', '[%1$s] %2$s: %3$s visits, %4$s%% frustrated', $t['sessions'], 'chatbot' ),
			$site,
			$this->title(),
			number_format_i18n( $t['sessions'] ),
			number_format_i18n( $t['frustrated_pct'], $t['frustrated_pct'] < 10 && floor( $t['frustrated_pct'] ) !== (float) $t['frustrated_pct'] ? 1 : 0 )
		);
	}

	/**
	 * "WPBot Site Insight weekly report" or "... monthly report" (the name
	 * comes from the host, default the menu title).
	 *
	 * @return string
	 */
	public function title() {
		$name = $this->brand['report_name'];
		return 'monthly' === $this->facts['period']['frequency']
			/* translators: %s: report name, e.g. "WPBot Site Insight" */
			? sprintf( __( '%s monthly report', 'chatbot' ), $name )
			/* translators: %s: report name, e.g. "WPBot Site Insight" */
			: sprintf( __( '%s weekly report', 'chatbot' ), $name );
	}

	/**
	 * Rows of the headline table.
	 *
	 * @return array<int,array<string,mixed>> label, value, delta (percent or null), good (higher is better: true/false/null).
	 */
	public function headline() {
		$t = $this->facts['totals'];
		$p = $this->facts['prev'];
		return array(
			array( __( 'Visits', 'chatbot' ), number_format_i18n( $t['sessions'] ), $this->change( $t['sessions'], $p['sessions'] ), true ),
			array( __( 'Recorded visits', 'chatbot' ), number_format_i18n( $t['recorded'] ), $this->change( $t['recorded'], $p['recorded'] ), null ),
			array( __( 'Pages per visit', 'chatbot' ), number_format_i18n( $t['avg_pages'], 1 ), $this->change( $t['avg_pages'], $p['avg_pages'] ), true ),
			array( __( 'Average visit length', 'chatbot' ), self::duration( $t['avg_duration'] ), $this->change( $t['avg_duration'], $p['avg_duration'] ), true ),
			array( __( 'Average scroll depth', 'chatbot' ), $t['avg_scroll'] . '%', $this->change( $t['avg_scroll'], $p['avg_scroll'] ), true ),
			array( __( 'Visits with frustration', 'chatbot' ), self::pct( $t['frustrated_pct'] ), $this->change( $t['frustrated_pct'], $p['frustrated_pct'] ), false ),
		);
	}

	/**
	 * Extra sections from features.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function extra_sections() {
		/**
		 * Extra sections in the email report, after the core sections.
		 * Each: array( 'title' => ..., 'rows' => array( array( label, value, url? ), ... ), 'note' => plain text? ).
		 * Text is escaped; only send totals, never visitor data.
		 *
		 * @param array<int,array<string,mixed>> $sections Sections.
		 * @param array<string,mixed>            $facts    Report facts.
		 */
		$raw = (array) apply_filters( 'qcin/report_sections', array(), $this->facts );
		$out = array();
		foreach ( $raw as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['title'] ) ) {
				continue;
			}
			$rows = array();
			foreach ( (array) ( $sec['rows'] ?? array() ) as $r ) {
				if ( is_array( $r ) && isset( $r[0], $r[1] ) ) {
					$rows[] = array( (string) $r[0], (string) $r[1], isset( $r[2] ) ? (string) $r[2] : '' );
				}
			}
			$out[] = array(
				'title' => (string) $sec['title'],
				'rows'  => $rows,
				'note'  => isset( $sec['note'] ) ? (string) $sec['note'] : '',
			);
		}
		return $out;
	}

	/**
	 * HTML body (inline styles and tables, so email clients show it as meant).
	 *
	 * @return string
	 */
	public function html() {
		$f   = $this->facts;
		$t   = $f['totals'];
		$c   = $this->c;
		$e   = static function ( $s ) {
			return esc_html( (string) $s );
		};
		$h3  = static function ( $s ) use ( $c, $e ) {
			return '<h3 style="margin:28px 0 10px;font-size:15px;line-height:1.3;color:' . $c['primary'] . '">' . $e( $s ) . '</h3>';
		};
		$td  = 'padding:8px 0;border-bottom:1px solid ' . $c['border'] . ';font-size:14px;vertical-align:top;';
		$out = '';

		// Warnings.
		foreach ( $f['warnings'] as $w ) {
			$out .= '<div style="margin:16px 0 0;padding:10px 14px;border-radius:10px;background:#fff6e0;border:1px solid #f3d48a;color:#5c4400;font-size:14px">&#9888;&#65039; ' . $e( $w ) . '</div>';
		}

		if ( ! $t['sessions'] ) {
			$out .= '<p style="margin:20px 0 0;font-size:15px">' . $e( __( 'There were no visits in this period. Scheduled reports are skipped when a period has no visits; tests and previews show it anyway.', 'chatbot' ) ) . '</p>';
		} else {
			// Headline tiles, two per row.
			$tiles = $this->headline();
			$out  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;border-collapse:separate;border-spacing:0 10px">';
			foreach ( array_chunk( $tiles, 2 ) as $pair ) {
				$out .= '<tr>';
				foreach ( $pair as $i => $tile ) {
					$out .= '<td width="50%" style="padding:' . ( 0 === $i ? '0 5px 0 0' : '0 0 0 5px' ) . ';vertical-align:top">'
						. '<div style="background:' . $c['soft'] . ';border-radius:12px;padding:14px 16px">'
						. '<div style="font-size:13px;color:' . $c['muted'] . '">' . $e( $tile[0] ) . '</div>'
						. '<div style="font-size:24px;font-weight:700;line-height:1.3;color:' . $c['text'] . '">' . $e( $tile[1] ) . '</div>'
						. '<div style="font-size:12px;line-height:1.4">' . $this->delta_html( $tile[2], $tile[3] ) . '</div>'
						. '</div></td>';
				}
				$out .= '</tr>';
			}
			$out .= '</table>';

			// Frustration signals.
			$range = 'monthly' === $f['period']['frequency'] ? '30' : '7';
			$rows  = '';
			foreach ( $t['flags'] as $type => $n ) {
				$before = (int) ( $f['prev']['flags'][ $type ] ?? 0 );
				if ( ! $n && ! $before ) {
					continue;
				}
				$label = $f['signals'][ $type ] ?? $type;
				$rows .= '<tr><td style="' . $td . '">' . $e( $label ) . '</td>'
					. '<td style="' . $td . 'text-align:right;white-space:nowrap"><strong>' . $e( number_format_i18n( $n ) ) . '</strong> <span style="color:' . $c['muted'] . '">' . $e( _n( 'visit', 'visits', $n, 'chatbot' ) ) . '</span><br>' . $this->delta_html( $this->change( $n, $before ), false ) . '</td>'
					. '<td style="' . $td . 'text-align:right;width:90px">' . ( $n ? '<a href="' . esc_url( self::admin_link( '#/recordings?f=' . rawurlencode( $type ) . '&r=' . $range ) ) . '" style="color:' . $c['primary'] . '">' . $e( __( 'Watch', 'chatbot' ) ) . '</a>' : '' ) . '</td></tr>';
			}
			$out .= $h3( __( 'Frustration signals', 'chatbot' ) );
			$out .= '' !== $rows
				? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $rows . '</table>'
				: '<p style="margin:0;font-size:14px;color:' . $c['muted'] . '">' . $e( __( 'No rage clicks, dead clicks, JavaScript errors or quick backs. Nice.', 'chatbot' ) ) . '</p>';

			// Top pages.
			if ( $f['pages'] ) {
				$out .= $h3( __( 'Top pages', 'chatbot' ) ) . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
				foreach ( $f['pages'] as $pg ) {
					$name = '' !== $pg['title'] ? $pg['title'] : $pg['path'];
					$out .= '<tr><td style="' . $td . '"><div style="font-weight:600">' . $e( $name ) . '</div><div style="font-size:12px;color:' . $c['muted'] . ';word-break:break-all">' . $e( $pg['path'] ) . '</div></td>'
						. '<td style="' . $td . 'text-align:right;white-space:nowrap"><strong>' . $e( number_format_i18n( $pg['views'] ) ) . '</strong> <span style="color:' . $c['muted'] . '">' . $e( _n( 'view', 'views', $pg['views'], 'chatbot' ) ) . '</span><br><span style="font-size:12px;color:' . $c['muted'] . '">'
						/* translators: 1: average time on the page, 2: average scroll depth in percent */
						. $e( sprintf( __( '%1$s · %2$s%% scrolled', 'chatbot' ), self::duration( $pg['avg_time_s'] ), $pg['avg_scroll'] ) ) . '</span></td>'
						. '<td style="' . $td . 'text-align:right;width:90px">' . ( $pg['url_id'] ? '<a href="' . esc_url( self::admin_link( '#/heatmaps?url=' . (int) $pg['url_id'] ) ) . '" style="color:' . $c['primary'] . '">' . $e( __( 'Heatmap', 'chatbot' ) ) . '</a>' : '' ) . '</td></tr>';
				}
				$out .= '</table>';
			}

			// JavaScript errors.
			if ( $f['errors'] ) {
				$out .= $h3( __( 'Top JavaScript errors', 'chatbot' ) )
					. '<p style="margin:-4px 0 6px;font-size:12px;color:' . $c['muted'] . '">' . $e( __( 'As reported by visitors\' browsers. Links and contact details are removed.', 'chatbot' ) ) . '</p>'
					. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
				foreach ( $f['errors'] as $err ) {
					$out .= '<tr><td style="' . $td . 'font-family:Menlo,Consolas,monospace;font-size:13px;word-break:break-word">' . $e( $err['message'] ) . '</td>'
						. '<td style="' . $td . 'text-align:right;white-space:nowrap;width:90px"><strong>' . $e( number_format_i18n( $err['sessions'] ) ) . '</strong> <span style="color:' . $c['muted'] . '">' . $e( _n( 'visit', 'visits', $err['sessions'], 'chatbot' ) ) . '</span></td></tr>';
				}
				$out .= '</table>';
			}

			// Visitors.
			$out .= $h3( __( 'Visitors', 'chatbot' ) ) . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
			foreach ( $this->visitor_rows() as $r ) {
				$out .= '<tr><td style="' . $td . 'color:' . $c['muted'] . ';width:38%">' . $e( $r[0] ) . '</td><td style="' . $td . '">' . $e( $r[1] ) . '</td></tr>';
			}
			$out .= '</table>';
		}

		// Sections from features.
		foreach ( $this->extra_sections() as $sec ) {
			$out .= $h3( $sec['title'] );
			if ( $sec['rows'] ) {
				$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
				foreach ( $sec['rows'] as $r ) {
					$val  = '' !== $r[2] ? '<a href="' . esc_url( $r[2] ) . '" style="color:' . $c['primary'] . '">' . $e( $r[1] ) . '</a>' : $e( $r[1] );
					$out .= '<tr><td style="' . $td . 'color:' . $c['muted'] . '">' . $e( $r[0] ) . '</td><td style="' . $td . 'text-align:right;font-weight:600">' . $val . '</td></tr>';
				}
				$out .= '</table>';
			}
			if ( '' !== $sec['note'] ) {
				$out .= '<p style="margin:8px 0 0;font-size:14px">' . $e( $sec['note'] ) . '</p>';
			}
		}

		// Button.
		$out .= '<p style="margin:28px 0 0"><a href="' . esc_url( self::admin_link( '#/overview' ) ) . '" style="display:inline-block;background:' . $c['primary'] . ';color:#ffffff;padding:12px 20px;border-radius:10px;font-weight:600;text-decoration:none">'
			/* translators: %s: menu title, e.g. "Site Insight" */
			. $e( sprintf( __( 'Open %s', 'chatbot' ), Admin_Controller::title() ) ) . '</a></p>';

		// Upgrade line from the host.
		if ( '' !== $this->brand['promo_text'] ) {
			$promo = '' !== $this->brand['promo_url']
				? '<a href="' . esc_url( $this->brand['promo_url'] ) . '" style="color:' . $c['primary'] . ';font-weight:600">' . $e( $this->brand['promo_text'] ) . '</a>'
				: $e( $this->brand['promo_text'] );
			$out .= '<p style="margin:24px 0 0;padding:12px 14px;border-radius:10px;background:' . $c['soft'] . ';font-size:14px">&#10024; ' . $promo . '</p>';
		}

		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$credit = $this->credit_html();
		return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $this->title() ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . $c['page'] . ';font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:' . $c['text'] . ';line-height:1.5">'
			. '<div style="max-width:620px;margin:0 auto;padding:24px 12px">'
			. '<div style="background:#ffffff;border-radius:16px;padding:24px;border:1px solid ' . $c['border'] . '">'
			. '<div style="font-size:13px;font-weight:600;color:' . $c['primary'] . ';text-transform:uppercase;letter-spacing:.04em">' . $e( $host ) . '</div>'
			. '<h2 style="margin:4px 0 4px;font-size:22px;line-height:1.3;color:' . $c['text'] . '">' . $e( $this->title() ) . '</h2>'
			. '<p style="margin:0;font-size:14px;color:' . $c['muted'] . '">' . $e( $this->period_label() ) . '</p>'
			. $out
			. '</div>'
			. '<p style="margin:16px 0 0;text-align:center;font-size:12px;color:' . $c['muted'] . '">' . $credit . '<br>'
			. '<a href="' . esc_url( self::admin_link( '#/settings?section=reports' ) ) . '" style="color:' . $c['muted'] . '">' . $e( __( 'Change who gets these emails, how often, or turn them off', 'chatbot' ) ) . '</a></p>'
			. '</div></body></html>';
	}

	/**
	 * Plain-text copy.
	 *
	 * @return string
	 */
	public function text() {
		$f     = $this->facts;
		$t     = $f['totals'];
		$lines = array( $this->title(), (string) wp_parse_url( home_url(), PHP_URL_HOST ) . ' - ' . $this->period_label(), '' );
		foreach ( $f['warnings'] as $w ) {
			$lines[] = '! ' . $w;
		}
		if ( ! $t['sessions'] ) {
			$lines[] = __( 'There were no visits in this period.', 'chatbot' );
		} else {
			foreach ( $this->headline() as $r ) {
				$lines[] = $r[0] . ': ' . $r[1] . ( null !== $r[2] ? ' (' . self::delta_text( $r[2] ) . ')' : '' );
			}
			$lines[] = '';
			$lines[] = strtoupper( __( 'Frustration signals', 'chatbot' ) );
			foreach ( $t['flags'] as $type => $n ) {
				if ( $n || ! empty( $f['prev']['flags'][ $type ] ) ) {
					$lines[] = '- ' . ( $f['signals'][ $type ] ?? $type ) . ': ' . number_format_i18n( $n );
				}
			}
			if ( $f['pages'] ) {
				$lines[] = '';
				$lines[] = strtoupper( __( 'Top pages', 'chatbot' ) );
				foreach ( $f['pages'] as $pg ) {
					/* translators: 1: page path, 2: views, 3: average time, 4: scroll depth */
					$lines[] = '- ' . sprintf( __( '%1$s: %2$s views, %3$s, %4$s%% scrolled', 'chatbot' ), $pg['path'], number_format_i18n( $pg['views'] ), self::duration( $pg['avg_time_s'] ), $pg['avg_scroll'] );
				}
			}
			if ( $f['errors'] ) {
				$lines[] = '';
				$lines[] = strtoupper( __( 'Top JavaScript errors', 'chatbot' ) );
				foreach ( $f['errors'] as $err ) {
					$lines[] = '- ' . $err['message'] . ' (' . number_format_i18n( $err['sessions'] ) . ')';
				}
			}
			$lines[] = '';
			$lines[] = strtoupper( __( 'Visitors', 'chatbot' ) );
			foreach ( $this->visitor_rows() as $r ) {
				$lines[] = '- ' . $r[0] . ': ' . $r[1];
			}
		}
		foreach ( $this->extra_sections() as $sec ) {
			$lines[] = '';
			$lines[] = strtoupper( $sec['title'] );
			foreach ( $sec['rows'] as $r ) {
				$lines[] = '- ' . $r[0] . ': ' . $r[1];
			}
			if ( '' !== $sec['note'] ) {
				$lines[] = $sec['note'];
			}
		}
		$lines[] = '';
		/* translators: %s: menu title, e.g. "Site Insight" */
		$lines[] = sprintf( __( 'Open %s', 'chatbot' ), Admin_Controller::title() ) . ': ' . self::admin_link( '#/overview' );
		if ( '' !== $this->brand['promo_text'] ) {
			$lines[] = '';
			$lines[] = $this->brand['promo_text'] . ( '' !== $this->brand['promo_url'] ? ' ' . $this->brand['promo_url'] : '' );
		}
		$lines[] = '';
		$lines[] = '--';
		$lines[] = $this->brand['credit_text'] . ( '' !== $this->brand['credit_url'] ? ' (' . $this->brand['credit_url'] . ')' : '' );
		$lines[] = __( 'Change who gets these emails, how often, or turn them off', 'chatbot' ) . ': ' . self::admin_link( '#/settings?section=reports' );
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Send to a list of addresses.
	 *
	 * @param string[] $to Addresses.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function send( array $to ) {
		$email = array(
			'to'          => $to,
			'subject'     => $this->subject(),
			'html'        => $this->html(),
			'text'        => $this->text(),
			'headers'     => array( 'Content-Type: text/html; charset=UTF-8' ),
			'attachments' => array(),
		);
		/**
		 * The email report just before it is sent: to, subject, html, text,
		 * headers, attachments. Features may add attachments.
		 *
		 * @param array<string,mixed> $email Email.
		 * @param array<string,mixed> $facts Report facts.
		 */
		$filtered = apply_filters( 'qcin/report_email', $email, $this->facts );
		$email    = is_array( $filtered ) ? array_merge( $email, $filtered ) : $email;

		$text = (string) $email['text'];
		$alt  = static function ( $mailer ) use ( $text ) {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};
		// After SMTP plugins have set things up: how this site really sends mail.
		$used = array(
			'mailer' => '',
			'from'   => '',
		);
		$seen = static function ( $mailer ) use ( &$used ) {
			$used['mailer'] = is_string( $mailer->Mailer ) ? $mailer->Mailer : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$used['from']   = is_string( $mailer->From ) ? $mailer->From : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};
		$failed = '';
		$fail   = static function ( $error ) use ( &$failed ) {
			if ( is_wp_error( $error ) ) {
				$failed = $error->get_error_message();
			}
		};
		add_action( 'phpmailer_init', $alt );
		add_action( 'phpmailer_init', $seen, PHP_INT_MAX );
		add_action( 'wp_mail_failed', $fail );
		$ok = wp_mail( (array) $email['to'], (string) $email['subject'], (string) $email['html'], (array) $email['headers'], (array) $email['attachments'] );
		remove_action( 'phpmailer_init', $alt );
		remove_action( 'phpmailer_init', $seen, PHP_INT_MAX );
		remove_action( 'wp_mail_failed', $fail );

		if ( ! $ok ) {
			$message = __( 'WordPress could not send the email. Check that this site can send mail (an SMTP plugin often helps).', 'chatbot' );
			if ( '' !== $failed ) {
				/* translators: %s: error message from the mailer */
				$message .= ' ' . sprintf( __( 'The mailer said: %s', 'chatbot' ), wp_strip_all_tags( $failed ) );
			}
			return new \WP_Error( 'qcin_report_mail', $message, array( 'status' => 502 ) );
		}
		return array(
			'sent'    => true,
			'to'      => array_values( (array) $email['to'] ),
			'subject' => (string) $email['subject'],
			// 'mail' = PHP mail(), 'smtp' = an SMTP plugin or server, '' = a plugin that replaces wp_mail().
			'mailer'  => $used['mailer'],
			'from'    => $used['from'],
		);
	}

	/**
	 * Rows of the Visitors table.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private function visitor_rows() {
		$f    = $this->facts;
		$t    = $f['totals'];
		$rows = array();
		$dev  = array();
		$name = array(
			'desktop' => __( 'Desktop', 'chatbot' ),
			'mobile'  => __( 'Mobile', 'chatbot' ),
			'tablet'  => __( 'Tablet', 'chatbot' ),
		);
		$d = $t['devices'];
		arsort( $d );
		foreach ( $d as $key => $pct ) {
			if ( $pct > 0 ) {
				$dev[] = $name[ $key ] . ' ' . self::pct( $pct );
			}
		}
		if ( $dev ) {
			$rows[] = array( __( 'Devices', 'chatbot' ), implode( ' · ', $dev ) );
		}
		$rows[] = array(
			__( 'New and returning', 'chatbot' ),
			/* translators: 1: new visits, 2: returning visits */
			sprintf( __( '%1$s new · %2$s returning', 'chatbot' ), number_format_i18n( $t['new'] ), number_format_i18n( $t['returning'] ) ),
		);
		$list = static function ( $items ) {
			return implode(
				' · ',
				array_map(
					static function ( $i ) {
						return $i['label'] . ' (' . number_format_i18n( $i['sessions'] ) . ')';
					},
					$items
				)
			);
		};
		if ( $f['sources'] ) {
			$rows[] = array( __( 'Top traffic sources', 'chatbot' ), $list( $f['sources'] ) );
		}
		if ( $f['countries'] ) {
			$rows[] = array( __( 'Top countries', 'chatbot' ), $list( $f['countries'] ) );
		}
		if ( $f['busiest'] ) {
			$ts     = strtotime( $f['busiest']['day'] . ' 12:00:00 UTC' );
			$fmt    = (string) get_option( 'date_format' );
			// Add the weekday unless the site's date format already shows it.
			$fmt = preg_match( '/(?<!\\\\)[lD]/', $fmt ) ? $fmt : 'l, ' . $fmt;
			$rows[] = array(
				__( 'Busiest day', 'chatbot' ),
				/* translators: 1: date, 2: number of visits */
				sprintf( _n( '%1$s (%2$s visit)', '%1$s (%2$s visits)', $f['busiest']['sessions'], 'chatbot' ), wp_date( $fmt, $ts, new \DateTimeZone( 'UTC' ) ), number_format_i18n( $f['busiest']['sessions'] ) ),
			);
		}
		return $rows;
	}

	/**
	 * "14 October 2026 – 20 October 2026, compared with the 7 days before".
	 *
	 * @return string
	 */
	public function period_label() {
		$p    = $this->facts['period'];
		$fmt  = get_option( 'date_format' );
		$utc  = new \DateTimeZone( 'UTC' );
		$from = wp_date( $fmt, strtotime( $p['start'] . ' 12:00:00 UTC' ), $utc );
		$to   = wp_date( $fmt, strtotime( $p['end'] . ' 12:00:00 UTC' ), $utc );
		return 'monthly' === $p['frequency']
			/* translators: 1: first day, 2: last day */
			? sprintf( __( '%1$s – %2$s, compared with the month before', 'chatbot' ), $from, $to )
			/* translators: 1: first day, 2: last day */
			: sprintf( __( '%1$s – %2$s, compared with the 7 days before', 'chatbot' ), $from, $to );
	}

	/**
	 * Credit line, linked when the host gives a URL.
	 *
	 * @return string HTML.
	 */
	private function credit_html() {
		$text = esc_html( $this->brand['credit_text'] );
		if ( '' === $this->brand['credit_url'] ) {
			return $text;
		}
		$label = '' !== $this->brand['credit_label'] ? $this->brand['credit_label'] : (string) wp_parse_url( $this->brand['credit_url'], PHP_URL_HOST );
		return $text . ' · <a href="' . esc_url( $this->brand['credit_url'] ) . '" style="color:' . $this->c['primary'] . ';font-weight:600">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Change against the period before.
	 *
	 * @param float|int $now    This period.
	 * @param float|int $before Period before.
	 * @return int|string|null Percent; 'up' when it rose from 0; null when the period before had no visits at all.
	 */
	private function change( $now, $before ) {
		if ( empty( $this->facts['prev']['sessions'] ) ) {
			return null;
		}
		if ( ! $before ) {
			return $now ? 'up' : 0;
		}
		return (int) round( 100 * ( $now - $before ) / $before );
	}

	/**
	 * Coloured change: green when it went the good way, red when not.
	 *
	 * @param int|string|null $d    Percent, 'up' or null.
	 * @param bool|null       $good Whether higher is better (null: neutral).
	 * @return string HTML.
	 */
	private function delta_html( $d, $good ) {
		if ( null === $d ) {
			return '<span style="color:' . $this->c['muted'] . '">' . esc_html__( 'No earlier data', 'chatbot' ) . '</span>';
		}
		$colour = $this->c['muted'];
		if ( 0 !== $d && null !== $good ) {
			$colour = ( 'up' === $d || $d > 0 ) === $good ? '#1b8a55' : '#c43d4b';
		}
		return '<span style="color:' . $colour . ';font-weight:600">' . esc_html( self::delta_text( $d ) ) . '</span>';
	}

	/**
	 * "▲ 12%", "▼ 3%", "▲ Up from 0" or "No change".
	 *
	 * @param int|string $d Percent or 'up'.
	 * @return string
	 */
	private static function delta_text( $d ) {
		if ( 'up' === $d ) {
			return "\u{25B2} " . __( 'Up from 0', 'chatbot' );
		}
		if ( 0 === $d ) {
			return __( 'No change', 'chatbot' );
		}
		return ( $d > 0 ? "\u{25B2} " : "\u{25BC} " ) . number_format_i18n( abs( $d ) ) . '%';
	}

	/**
	 * "2 min 5 s" or "45 s".
	 *
	 * @param int $s Seconds.
	 * @return string
	 */
	public static function duration( $s ) {
		$s = max( 0, (int) $s );
		if ( $s < 60 ) {
			/* translators: %d: seconds */
			return sprintf( __( '%d s', 'chatbot' ), $s );
		}
		/* translators: 1: minutes, 2: seconds */
		return sprintf( __( '%1$d min %2$d s', 'chatbot' ), intdiv( $s, 60 ), $s % 60 );
	}

	/**
	 * Percent with one decimal below 10.
	 *
	 * @param float $v Value.
	 * @return string
	 */
	private static function pct( $v ) {
		$v = (float) $v;
		return number_format_i18n( $v, $v < 10 && floor( $v ) !== $v ? 1 : 0 ) . '%';
	}

	/**
	 * Link to a screen of the admin app.
	 *
	 * @param string $hash Route, e.g. "#/overview".
	 * @return string
	 */
	public static function admin_link( $hash ) {
		return admin_url( 'admin.php?page=' . Admin_Controller::PAGE ) . $hash;
	}

	/**
	 * Colours from the host's brand tokens, with WPBot-like defaults.
	 *
	 * @return array<string,string>
	 */
	private static function colours() {
		$host  = Module::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		$pick  = static function ( $key, $fallback ) use ( $brand ) {
			$v = isset( $brand[ $key ] ) ? trim( (string) $brand[ $key ] ) : '';
			return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ? $v : $fallback;
		};
		return array(
			'primary' => $pick( 'primary', '#5B4E96' ),
			'soft'    => $pick( 'primary_soft', '#f3f1fa' ),
			'border'  => $pick( 'primary_border', '#e4e0f2' ),
			'page'    => $pick( 'page_bg', '#F5F7FD' ),
			'text'    => $pick( 'text', '#2d2a3a' ),
			'muted'   => $pick( 'muted', '#6b6680' ),
		);
	}
}
