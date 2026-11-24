<?php
/**
 * Fine-grained email notification settings (owner, 23 November 2026): "users can turn off
 * individual notifications by features and each notification separately", with a few simple
 * frequencies.
 *
 * AI / Settings → Notifications (the section WPBot Core adds) gets a second card:
 * - a master switch for every WPBot notification email to site staff;
 * - one group per feature, with an on/off switch and a frequency: Instantly, Daily digest,
 *   Weekly digest (Off = the switch);
 * - one switch per notification, and a frequency of its own ("Same as the group" by default)
 *   where a digest makes sense;
 * - quick presets above the groups: Instantly, Daily digest, Weekly digest, Off.
 *
 * Emails to visitors and customers (verification codes, replies, order and return emails, campaign
 * emails) are never held or switched off: they show as "Always sent instantly" with the reason.
 * Scheduled reports already arrive on their own schedule: they can be switched off, not digested.
 *
 * Nothing is saved until the owner saves the section: until then every notification is on and
 * sent instantly, exactly as before.
 *
 * The gate itself is QCLD_WPBot_Notification_Gate; the list of notifications is
 * QCLD_WPBot_Notification_Catalog.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Notification_Settings' ) ) {

	/**
	 * Saved choices and the settings card.
	 */
	final class QCLD_WPBot_Notification_Settings {

		/**
		 * Option holding the owner's choices (absent until saved).
		 */
		const OPTION = 'qcld_wpbot_notification_prefs';

		/**
		 * AI Settings section (added by WPBot Core).
		 */
		const SECTION = 'notifications';

		/**
		 * Posted field.
		 */
		const FIELD = 'wpbot_notify';

		/**
		 * Frequencies.
		 */
		const INSTANT = 'instant';
		const DAILY   = 'daily';
		const WEEKLY  = 'weekly';
		const OFF     = 'off';
		const INHERIT = 'group';

		/**
		 * Cached choices for this request.
		 *
		 * @var array|null
		 */
		private static $prefs = null;

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function hook() {
			// After WPBot Core's "Notification emails" card (priority 10).
			add_action( 'qcld_wpbot_chat/ai_settings_section', array( __CLASS__, 'render' ), 20 );
			add_action( 'qcld_wpbot_chat/ai_settings_save', array( __CLASS__, 'save_section' ), 20, 2 );
			add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
			add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
			add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		}

		/**
		 * Forget the cached choices.
		 *
		 * @return void
		 */
		public static function flush() {
			self::$prefs = null;
		}

		/**
		 * Frequencies a group can have (Off is the group's switch).
		 *
		 * @return array<string,string>
		 */
		public static function frequencies() {
			return array(
				self::INSTANT => __( 'Instantly', 'chatbot' ),
				self::DAILY   => __( 'Daily digest', 'chatbot' ),
				self::WEEKLY  => __( 'Weekly digest', 'chatbot' ),
			);
		}

		/**
		 * The defaults: everything on and instant (today's behaviour).
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'master' => true,
				'groups' => array(),
				'items'  => array(),
			);
		}

		/**
		 * The saved choices, cleaned, merged over the defaults.
		 *
		 * @return array{master:bool,groups:array,items:array}
		 */
		public static function prefs() {
			if ( null === self::$prefs ) {
				$saved       = get_option( self::OPTION, array() );
				self::$prefs = self::sanitize( is_array( $saved ) ? $saved : array() );
			}
			return self::$prefs;
		}

		/**
		 * Whether the owner saved choices (false = today's behaviour everywhere).
		 *
		 * @return bool
		 */
		public static function is_saved() {
			return is_array( get_option( self::OPTION, null ) );
		}

		/**
		 * Clean stored or posted choices. Unknown groups and notifications are dropped.
		 *
		 * @param array $raw master (bool), groups[gid] = [on, freq], items[nid] = [on, freq].
		 * @return array{master:bool,groups:array,items:array}
		 */
		public static function sanitize( array $raw ) {
			$out    = self::defaults();
			$groups = QCLD_WPBot_Notification_Catalog::groups();
			if ( array_key_exists( 'master', $raw ) ) {
				$out['master'] = self::truthy( $raw['master'] );
			}
			$raw_groups = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();
			$raw_items  = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array();
			foreach ( $groups as $gid => $group ) {
				$g                     = isset( $raw_groups[ $gid ] ) && is_array( $raw_groups[ $gid ] ) ? $raw_groups[ $gid ] : array();
				$out['groups'][ $gid ] = array(
					'on'   => array_key_exists( 'on', $g ) ? self::truthy( $g['on'] ) : true,
					'freq' => isset( $g['freq'] ) && isset( self::frequencies()[ (string) $g['freq'] ] ) ? (string) $g['freq'] : self::INSTANT,
				);
				foreach ( $group['items'] as $nid => $item ) {
					$i    = isset( $raw_items[ $nid ] ) && is_array( $raw_items[ $nid ] ) ? $raw_items[ $nid ] : array();
					$freq = isset( $i['freq'] ) ? (string) $i['freq'] : self::INHERIT;
					if ( self::INHERIT !== $freq && ! isset( self::frequencies()[ $freq ] ) ) {
						$freq = self::INHERIT;
					}
					if ( empty( $item['digest'] ) ) {
						$freq = self::INHERIT;
					}
					$out['items'][ $nid ] = array(
						'on'   => array_key_exists( 'on', $i ) ? self::truthy( $i['on'] ) : true,
						'freq' => $freq,
					);
				}
			}
			return $out;
		}

		/**
		 * What happens to one notification: instant, daily, weekly or off.
		 *
		 * @param string     $nid   Notification id.
		 * @param array|null $prefs Choices (default: the saved ones).
		 * @return string
		 */
		public static function effective( $nid, $prefs = null ) {
			$item = QCLD_WPBot_Notification_Catalog::item( $nid );
			if ( null === $item ) {
				return self::INSTANT;
			}
			if ( ! empty( $item['locked'] ) ) {
				return self::INSTANT;
			}
			$prefs = is_array( $prefs ) ? $prefs : self::prefs();
			if ( empty( $prefs['master'] ) ) {
				return self::OFF;
			}
			$gid   = $item['group'];
			$group = isset( $prefs['groups'][ $gid ] ) ? $prefs['groups'][ $gid ] : array(
				'on'   => true,
				'freq' => self::INSTANT,
			);
			$own   = isset( $prefs['items'][ $nid ] ) ? $prefs['items'][ $nid ] : array(
				'on'   => true,
				'freq' => self::INHERIT,
			);
			if ( empty( $group['on'] ) || empty( $own['on'] ) ) {
				return self::OFF;
			}
			if ( empty( $item['digest'] ) ) {
				return self::INSTANT;
			}
			$freq = self::INHERIT !== $own['freq'] ? $own['freq'] : $group['freq'];
			return isset( self::frequencies()[ $freq ] ) ? $freq : self::INSTANT;
		}

		/**
		 * Save the owner's choices (WPBot Core's section save; the page checked the nonce and the user).
		 *
		 * @param string $section Section id.
		 * @param array  $post    Posted values (unslashed).
		 * @return void
		 */
		public static function save_section( $section, $post ) {
			if ( self::SECTION !== $section || ! is_array( $post ) || ! isset( $post[ self::FIELD ] ) || ! is_array( $post[ self::FIELD ] ) ) {
				return;
			}
			$raw = $post[ self::FIELD ];
			if ( empty( $raw['present'] ) ) {
				return;
			}
			// Unticked switches are not posted: a group or notification without "on" is off.
			$clean  = array(
				'master' => ! empty( $raw['master'] ),
				'groups' => array(),
				'items'  => array(),
			);
			$groups = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();
			$items  = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array();
			foreach ( QCLD_WPBot_Notification_Catalog::groups() as $gid => $group ) {
				$editable = array_filter(
					$group['items'],
					static function ( $item ) {
						return empty( $item['locked'] );
					}
				);
				if ( ! $editable ) {
					continue; // Only emails to visitors and customers: no switch, always on.
				}
				$g                       = isset( $groups[ $gid ] ) && is_array( $groups[ $gid ] ) ? $groups[ $gid ] : array();
				$clean['groups'][ $gid ] = array(
					'on'   => ! empty( $g['on'] ),
					'freq' => isset( $g['freq'] ) ? sanitize_key( (string) $g['freq'] ) : self::INSTANT,
				);
				foreach ( $group['items'] as $nid => $item ) {
					if ( ! empty( $item['locked'] ) ) {
						continue;
					}
					$i                      = isset( $items[ $nid ] ) && is_array( $items[ $nid ] ) ? $items[ $nid ] : array();
					$clean['items'][ $nid ] = array(
						'on'   => ! empty( $i['on'] ),
						'freq' => isset( $i['freq'] ) ? sanitize_key( (string) $i['freq'] ) : self::INHERIT,
					);
				}
			}
			self::save( $clean );
		}

		/**
		 * Store choices and (un)schedule the digests.
		 *
		 * @param array $prefs Choices.
		 * @return array What was stored.
		 */
		public static function save( array $prefs ) {
			$clean = self::sanitize( $prefs );
			update_option( self::OPTION, $clean, false );
			self::flush();
			QCLD_WPBot_Notification_Gate::schedule();
			return $clean;
		}

		/**
		 * A posted or stored switch value.
		 *
		 * @param mixed $value Value.
		 * @return bool
		 */
		private static function truthy( $value ) {
			if ( is_bool( $value ) ) {
				return $value;
			}
			return in_array( strtolower( trim( (string) $value ) ), array( '1', 'on', 'yes', 'true' ), true );
		}

		/**
		 * Print the card (inside the AI Settings form).
		 *
		 * @param string $section Section id.
		 * @return void
		 */
		public static function render( $section ) {
			if ( self::SECTION !== $section ) {
				return;
			}
			$prefs  = self::prefs();
			$freqs  = self::frequencies();
			$f      = self::FIELD;
			$queued = QCLD_WPBot_Notification_Gate::queued_count();

			echo '<div class="qcwp-card wpbot-nf" data-wpbot-nf>';
			echo '<h2>' . esc_html__( 'Which emails you get, and how often', 'chatbot' ) . '</h2>';
			echo '<p class="qcwp-help">' . esc_html__( 'Turn off whole features or single notifications, and choose to get them instantly or bundled into one daily or weekly digest email. Emails to your visitors and customers are always sent right away.', 'chatbot' ) . '</p>';
			echo '<input type="hidden" name="' . esc_attr( $f ) . '[present]" value="1">';

			echo '<div class="wpbot-nf__master">';
			echo '<label class="wpbot-switch"><input type="checkbox" name="' . esc_attr( $f ) . '[master]" value="1" data-nf-master' . checked( $prefs['master'], true, false ) . '> <strong>' . esc_html__( 'Send WPBot notification emails to staff', 'chatbot' ) . '</strong></label>';
			echo '<p class="wpbot-nf__off-note" data-nf-off-note' . ( $prefs['master'] ? ' hidden' : '' ) . '>' . esc_html__( 'All WPBot notifications to you and your team are off. Emails to visitors and customers are still sent.', 'chatbot' ) . '</p>';
			echo '</div>';

			echo '<div class="wpbot-nf__presets" role="group" aria-label="' . esc_attr__( 'Quick settings for every feature', 'chatbot' ) . '">';
			echo '<span class="wpbot-nf__presets-label">' . esc_html__( 'Set every feature to:', 'chatbot' ) . '</span>';
			foreach ( array_merge( $freqs, array( self::OFF => __( 'Off', 'chatbot' ) ) ) as $key => $label ) {
				echo '<button type="button" class="qcwp-btn is-small wpbot-nf__preset" data-nf-preset="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
			}
			echo '</div>';
			if ( $queued > 0 ) {
				/* translators: %d: number of notifications waiting for the next digest. */
				echo '<p class="wpbot-nf__queued">' . esc_html( sprintf( _n( '%d notification is waiting for the next digest.', '%d notifications are waiting for the next digest.', $queued, 'chatbot' ), $queued ) ) . '</p>';
			}

			echo '<div class="wpbot-nf__groups">';
			foreach ( QCLD_WPBot_Notification_Catalog::groups() as $gid => $group ) {
				$g        = $prefs['groups'][ $gid ];
				$editable = array_filter(
					$group['items'],
					static function ( $item ) {
						return empty( $item['locked'] );
					}
				);
				$digest   = array_filter(
					$editable,
					static function ( $item ) {
						return ! empty( $item['digest'] );
					}
				);
				$count    = count( $group['items'] );
				echo '<details class="wpbot-nf__group" data-nf-group="' . esc_attr( $gid ) . '">';
				echo '<summary class="wpbot-nf__group-head">';
				echo '<span class="wpbot-nf__group-title">' . esc_html( $group['label'] ) . '</span>';
				/* translators: %d: number of emails in the group. */
				echo '<span class="wpbot-nf__count">' . esc_html( sprintf( _n( '%d email', '%d emails', $count, 'chatbot' ), $count ) ) . '</span>';
				echo '<span class="wpbot-nf__state" data-nf-state></span>';
				echo '</summary>';
				echo '<div class="wpbot-nf__group-body">';
				if ( ! empty( $group['about'] ) ) {
					echo '<p class="qcwp-help">' . esc_html( $group['about'] ) . '</p>';
				}
				if ( $editable ) {
					echo '<div class="wpbot-nf__group-controls">';
					/* translators: %s: feature name. */
					echo '<label class="wpbot-switch"><input type="checkbox" name="' . esc_attr( $f ) . '[groups][' . esc_attr( $gid ) . '][on]" value="1" data-nf-group-on' . checked( $g['on'], true, false ) . '> ' . esc_html( sprintf( __( '%s emails on', 'chatbot' ), $group['label'] ) ) . '</label>';
					if ( $digest ) {
						echo '<label class="wpbot-nf__freq"><span>' . esc_html__( 'Frequency', 'chatbot' ) . '</span> <select name="' . esc_attr( $f ) . '[groups][' . esc_attr( $gid ) . '][freq]" data-nf-group-freq>';
						foreach ( $freqs as $key => $label ) {
							echo '<option value="' . esc_attr( $key ) . '"' . selected( $g['freq'], $key, false ) . '>' . esc_html( $label ) . '</option>';
						}
						echo '</select></label>';
					} else {
						echo '<input type="hidden" name="' . esc_attr( $f ) . '[groups][' . esc_attr( $gid ) . '][freq]" value="' . esc_attr( self::INSTANT ) . '">';
					}
					echo '</div>';
				}
				echo '<ul class="wpbot-nf__items">';
				foreach ( $group['items'] as $nid => $item ) {
					self::render_item( $nid, $item, $prefs );
				}
				echo '</ul></div></details>';
			}
			echo '</div>';
			echo '<p class="qcwp-help">' . esc_html__( 'Digests are sent by WordPress\'s scheduler: daily around 08:00 and weekly on Mondays (site time), one email per recipient. Attachments are not included in digests.', 'chatbot' ) . '</p>';
			echo '</div>';
			self::assets();
		}

		/**
		 * One notification row.
		 *
		 * @param string $nid   Notification id.
		 * @param array  $item  Catalog entry.
		 * @param array  $prefs Choices.
		 * @return void
		 */
		private static function render_item( $nid, array $item, array $prefs ) {
			$f = self::FIELD;
			echo '<li class="wpbot-nf__item' . ( ! empty( $item['locked'] ) ? ' is-locked' : '' ) . '" data-nf-item="' . esc_attr( $nid ) . '">';
			echo '<div class="wpbot-nf__item-main">';
			if ( ! empty( $item['locked'] ) ) {
				echo '<span class="wpbot-nf__item-name">' . esc_html( $item['label'] ) . '</span>';
			} else {
				$own = $prefs['items'][ $nid ];
				echo '<label class="wpbot-switch"><input type="checkbox" name="' . esc_attr( $f ) . '[items][' . esc_attr( $nid ) . '][on]" value="1" data-nf-item-on' . checked( $own['on'], true, false ) . '> <span class="wpbot-nf__item-name">' . esc_html( $item['label'] ) . '</span></label>';
			}
			echo '<span class="wpbot-nf__to">' . esc_html( $item['to'] ) . '</span>';
			echo '</div>';
			echo '<div class="wpbot-nf__item-side">';
			if ( ! empty( $item['locked'] ) ) {
				echo '<span class="qcwp-pill wpbot-nf__pill">' . esc_html__( 'Always sent instantly', 'chatbot' ) . '</span>';
				echo '<span class="wpbot-nf__why">' . esc_html( $item['locked'] ) . '</span>';
			} elseif ( ! empty( $item['digest'] ) ) {
				$own = $prefs['items'][ $nid ];
				echo '<label class="wpbot-nf__freq is-item"><span class="screen-reader-text">' . esc_html__( 'Frequency', 'chatbot' ) . '</span><select name="' . esc_attr( $f ) . '[items][' . esc_attr( $nid ) . '][freq]" data-nf-item-freq>';
				echo '<option value="' . esc_attr( self::INHERIT ) . '"' . selected( $own['freq'], self::INHERIT, false ) . '>' . esc_html__( 'Same as the group', 'chatbot' ) . '</option>';
				foreach ( self::frequencies() as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $own['freq'], $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select></label>';
			} else {
				$why = ! empty( $item['instant'] ) ? $item['instant'] : __( 'Sent instantly', 'chatbot' );
				echo '<span class="wpbot-nf__why">' . esc_html( $why ) . '</span>';
			}
			echo '</div></li>';
		}

		/**
		 * Styles (design tokens of WPBot Core's admin design, emerald accent) and the small script.
		 *
		 * @return void
		 */
		private static function assets() {
			$css = '.wpbot-nf{margin-top:20px}'
				. '.wpbot-nf__master{padding:12px 0;border-bottom:1px solid var(--wpb-line,#e2e8f0)}'
				. '.wpbot-nf__off-note{margin:8px 0 0;padding:8px 12px;border-radius:8px;background:var(--wpb-warn-bg,#fef3c7);color:var(--wpb-warn-text,#78350f)}'
				. '.wpbot-nf__presets{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:14px 0}'
				. '.wpbot-nf__presets-label{font-weight:600;margin-right:4px}'
				. '.wpbot-nf__preset.is-active{background:var(--wpb-accent,#059669);border-color:var(--wpb-accent,#059669);color:var(--wpb-on-accent,#fff)}'
				. '.wpbot-nf__queued{margin:0 0 10px;color:var(--wpb-muted,#64748b)}'
				. '.wpbot-nf__groups{display:flex;flex-direction:column;gap:8px}'
				. '.wpbot-nf__group{border:1px solid var(--wpb-line,#e2e8f0);border-radius:var(--wpb-radius,10px);background:var(--wpb-soft,transparent)}'
				. '.wpbot-nf__group-head{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;padding:12px 14px;cursor:pointer;list-style:none}'
				. '.wpbot-nf__group-head::-webkit-details-marker{display:none}'
				. '.wpbot-nf__group-head::before{content:"";width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);transition:transform .15s;opacity:.6}'
				. '.wpbot-nf__group[open]>.wpbot-nf__group-head::before{transform:rotate(45deg)}'
				. '.wpbot-nf__group-title{font-weight:600;color:var(--wpb-text,inherit)}'
				. '.wpbot-nf__count{color:var(--wpb-muted,#64748b);font-size:12px}'
				. '.wpbot-nf__state{margin-left:auto;font-size:12px;font-weight:600;padding:2px 10px;border-radius:999px;background:var(--wpb-accent-soft,#d1fae5);color:var(--wpb-accent-text,#065f46)}'
				. '.wpbot-nf__state.is-off{background:var(--wpb-err-bg,#fee2e2);color:var(--wpb-err-text,#991b1b)}'
				. '.wpbot-nf__group-body{padding:0 14px 12px}'
				. '.wpbot-nf__group-controls{display:flex;flex-wrap:wrap;gap:10px 24px;align-items:center;padding:8px 0 12px;border-bottom:1px solid var(--wpb-line,#e2e8f0)}'
				. '.wpbot-nf__freq{display:inline-flex;gap:8px;align-items:center}'
				. '.wpbot-nf__freq select{min-width:150px;max-width:100%}'
				. '.wpbot-nf__items{margin:0;padding:0;list-style:none}'
				. '.wpbot-nf__item{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--wpb-line,#e2e8f0)}'
				. '.wpbot-nf__item:last-child{border-bottom:0}'
				. '.wpbot-nf__item-main{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1 1 260px}'
				. '.wpbot-nf__item.is-locked .wpbot-nf__item-name{font-weight:500}'
				. '.wpbot-nf__to,.wpbot-nf__why{font-size:12px;color:var(--wpb-muted,#64748b)}'
				. '.wpbot-nf__item-side{display:flex;flex-direction:column;align-items:flex-end;gap:2px;flex:0 1 auto;max-width:100%;text-align:right}'
				. '.wpbot-nf__pill{font-size:12px}'
				. '.wpbot-nf.is-master-off .wpbot-nf__presets,.wpbot-nf.is-master-off .wpbot-nf__groups{opacity:.5}'
				. '.wpbot-nf__item.is-dim{opacity:.55}'
				. '@media (max-width:600px){.wpbot-nf__item-side{align-items:flex-start;text-align:left;flex-basis:100%}.wpbot-nf__state{margin-left:0}.wpbot-nf__freq select{min-width:0;width:100%}.wpbot-nf__freq{width:100%}}';
			echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS.
			$labels = wp_json_encode(
				array(
					'instant' => __( 'Instantly', 'chatbot' ),
					'daily'   => __( 'Daily digest', 'chatbot' ),
					'weekly'  => __( 'Weekly digest', 'chatbot' ),
					'off'     => __( 'Off', 'chatbot' ),
					'mixed'   => __( 'Custom', 'chatbot' ),
					'always'  => __( 'Always on', 'chatbot' ),
				)
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static script, JSON-encoded labels.
			echo '<script>(function(){var c=document.querySelector("[data-wpbot-nf]");if(!c){return;}var L=' . $labels . ';'
				. 'var m=c.querySelector("[data-nf-master]");'
				. 'function state(g){var on=g.querySelector("[data-nf-group-on]"),fr=g.querySelector("[data-nf-group-freq]"),s=g.querySelector("[data-nf-state]"),t,off=false;'
				. 'if(!on){t=L.always;}else if(!on.checked||(m&&!m.checked)){t=L.off;off=true;}else{t=fr?L[fr.value]:L.instant;var items=g.querySelectorAll("[data-nf-item-on]"),n=0;items.forEach(function(i){if(!i.checked){n++;}});var cust=false;g.querySelectorAll("[data-nf-item-freq]").forEach(function(s){if(s.value!=="group"){cust=true;}});if(n||cust){t+=" · "+L.mixed;}}'
				. 's.textContent=t;s.classList.toggle("is-off",off);g.querySelectorAll(".wpbot-nf__item:not(.is-locked)").forEach(function(li){li.classList.toggle("is-dim",!!(on&&!on.checked));});}'
				. 'function all(){c.classList.toggle("is-master-off",!!(m&&!m.checked));var n=c.querySelector("[data-nf-off-note]");if(n&&m){n.hidden=m.checked;}c.querySelectorAll("[data-nf-group]").forEach(state);}'
				. 'c.addEventListener("change",all);'
				. 'c.addEventListener("click",function(e){var b=e.target.closest("[data-nf-preset]");if(!b){return;}var p=b.getAttribute("data-nf-preset");if(m&&p!=="off"){m.checked=true;}'
				. 'c.querySelectorAll("[data-nf-group]").forEach(function(g){var on=g.querySelector("[data-nf-group-on]"),fr=g.querySelector("[data-nf-group-freq]");if(!on){return;}if(p==="off"){on.checked=false;return;}on.checked=true;if(fr){fr.value=p;}g.querySelectorAll("[data-nf-item-on]").forEach(function(i){i.checked=true;});g.querySelectorAll("[data-nf-item-freq]").forEach(function(s){s.value="group";});});'
				. 'c.querySelectorAll("[data-nf-preset]").forEach(function(x){x.classList.toggle("is-active",x===b);});all();var f=c.closest("form");if(f){f.dispatchEvent(new Event("change",{bubbles:true}));}});'
				. 'all();})();</script>';
		}
	}
}
