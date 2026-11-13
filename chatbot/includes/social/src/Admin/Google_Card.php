<?php
/**
 * The Google connection, set up right in Social (owner, 12 Nov 2026: the connect screens sent people to
 * "WPBot settings › Connections › Google", which Social does not have).
 *
 * It is WPBot Core's one Google connection, the same that AI Writer and SEO & AI Search use: saving a
 * client or reconnecting here changes it there too. Google Business Profile (screen 03) and the Master
 * module's YouTube Shorts steps print it through the action qcld_wpbot_social/google_card.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Admin;

use QuantumCloud\WPBotCore\Connections\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Google connection card.
 */
final class Google_Card {

	/**
	 * Option: Social uses Google Business Profile (asks Google for the business.manage permission).
	 */
	const GBP_OPTION = 'qcld_wpbot_social_gbp_access';

	/**
	 * Hook the card and the Business Profile permission.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_social/google_card', array( __CLASS__, 'render' ) );
		add_filter( 'qcld_wpbot_core/business_profile_enabled', array( __CLASS__, 'business_profile_wanted' ) );
	}

	/**
	 * Whether WPBot Core can be used (older cores have no Google connection).
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Google::class ) && method_exists( Google::class, 'public_view' );
	}

	/**
	 * Business Profile permission: on when the owner turned it on in Social (or another module did).
	 *
	 * @param bool $wanted Whether another module wants it.
	 * @return bool
	 */
	public static function business_profile_wanted( $wanted ) {
		return (bool) $wanted || (bool) get_option( self::GBP_OPTION, false );
	}

	/**
	 * Whether WPBot asks Google for Business Profile access (Social's switch, or another module).
	 *
	 * @return bool
	 */
	public static function business_profile_on() {
		$class = '\\QuantumCloud\\WPBotCore\\Connections\\Business_Profile';
		if ( class_exists( $class ) && method_exists( $class, 'wanted' ) ) {
			return (bool) $class::wanted();
		}
		return (bool) get_option( self::GBP_OPTION, false );
	}

	/**
	 * Turn Social's Business Profile permission on or off.
	 *
	 * @param bool $on On.
	 * @return void
	 */
	public static function set_business_profile( $on ) {
		if ( $on ) {
			update_option( self::GBP_OPTION, 1, false );
		} else {
			delete_option( self::GBP_OPTION );
		}
	}

	/**
	 * The card's state.
	 *
	 * @param string $purpose business or youtube.
	 * @return array{state:string,client:bool,client_id:string,secret_hint:string,redirect:string,missing:string[]}
	 */
	public static function state( $purpose = 'business' ) {
		return self::classify( Google::public_view(), $purpose );
	}

	/**
	 * The card's state from WPBot Core's public view of the Google connection.
	 *
	 * @param array  $view    Google::public_view().
	 * @param string $purpose business or youtube.
	 * @return array
	 */
	public static function classify( array $view, $purpose = 'business' ) {
		$view    = array_merge(
			array(
				'client_id'    => '',
				'has_secret'   => false,
				'secret_hint'  => '',
				'connected'    => false,
				'status'       => 'not_connected',
				'message'      => '',
				'redirect_uri' => '',
				'missing'      => array(),
			),
			$view
		);
		$client  = '' !== (string) $view['client_id'] && ! empty( $view['has_secret'] );
		$missing = array();
		foreach ( (array) $view['missing'] as $scope ) {
			if ( false !== strpos( (string) $scope, 'business.manage' ) ) {
				$missing[] = __( 'Business Profile', 'chatbot' );
			} elseif ( false !== strpos( (string) $scope, 'youtube' ) ) {
				$missing[] = __( 'YouTube uploads', 'chatbot' );
			}
		}
		if ( ! $client ) {
			$state = 'no_client';
		} elseif ( empty( $view['connected'] ) ) {
			$state = 'not_connected';
		} elseif ( 'failing' === $view['status'] ) {
			$state = 'failing';
		} elseif ( array() !== $missing ) {
			$state = 'reconnect';
		} else {
			$state = 'connected';
		}
		return array(
			'state'       => $state,
			'purpose'     => 'youtube' === $purpose ? 'youtube' : 'business',
			'client'      => $client,
			'client_id'   => (string) $view['client_id'],
			'secret_hint' => (string) $view['secret_hint'],
			'redirect'    => (string) $view['redirect_uri'],
			'message'     => (string) $view['message'],
			'missing'     => $missing,
		);
	}

	/**
	 * Print the card.
	 *
	 * @param string $purpose business or youtube.
	 * @return void
	 */
	public static function render( $purpose = 'business' ) {
		if ( ! self::available() ) {
			echo '<p class="qcsoc-muted">' . esc_html__( 'Update WPBot to set up the Google connection here.', 'chatbot' ) . '</p>';
			return;
		}
		$s     = self::state( $purpose );
		$pills = array(
			'no_client'     => array( 'warn', __( 'Not set up', 'chatbot' ) ),
			'not_connected' => array( 'warn', __( 'Not connected', 'chatbot' ) ),
			'failing'       => array( 'bad', __( 'Needs attention', 'chatbot' ) ),
			'reconnect'     => array( 'warn', __( 'Reconnect needed', 'chatbot' ) ),
			'connected'     => array( 'ok', __( 'Connected', 'chatbot' ) ),
		);
		$pill  = $pills[ $s['state'] ];
		?>
		<div class="qcsoc-google-card" data-qcsoc-google-card data-purpose="<?php echo esc_attr( $s['purpose'] ); ?>">
			<p><strong><?php esc_html_e( 'Google connection', 'chatbot' ); ?></strong> <span class="qcsoc-pill qcsoc-pill--<?php echo esc_attr( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?></span></p>
			<p class="qcsoc-muted"><?php esc_html_e( 'One Google connection for all of WPBot: AI Writer and SEO & AI Search use it too, and keep working when you reconnect here.', 'chatbot' ); ?></p>
			<?php if ( 'failing' === $s['state'] && '' !== $s['message'] ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $s['message'] ); ?></p></div>
			<?php elseif ( 'reconnect' === $s['state'] ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					/* translators: %s: permissions, comma separated (for example "Business Profile"). */
					echo esc_html( sprintf( __( 'Reconnect Google once to allow: %s.', 'chatbot' ), implode( ', ', $s['missing'] ) ) );
					?>
				</p></div>
			<?php endif; ?>
			<details class="qcsoc-google-client" <?php echo $s['client'] ? '' : 'open'; ?>>
				<summary><?php echo esc_html( $s['client'] ? __( 'Google client', 'chatbot' ) . ' · ' . $s['client_id'] : __( 'Set up your Google client', 'chatbot' ) ); ?></summary>
				<ol class="qcsoc-steps">
					<li><strong><?php esc_html_e( 'Create an OAuth client', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'At console.cloud.google.com › APIs & Services › Credentials › Create credentials › OAuth client ID › Web application.', 'chatbot' ); ?></p></li>
					<li>
						<strong><?php esc_html_e( 'Add this redirect address to the client', 'chatbot' ); ?></strong>
						<p class="qcsoc-muted"><?php esc_html_e( 'Authorized redirect URIs', 'chatbot' ); ?></p>
						<div class="qcsoc-copy"><code data-qcsoc-google-redirect><?php echo esc_html( $s['redirect'] ); ?></code> <button type="button" class="button" data-qcsoc-google-copy><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'chatbot' ); ?></button></div>
					</li>
					<li>
						<strong><?php esc_html_e( 'Paste the client ID and secret', 'chatbot' ); ?></strong>
						<p class="qcsoc-muted"><?php esc_html_e( 'The secret is encrypted on this site and never shown again.', 'chatbot' ); ?></p>
						<div class="qcsoc-fields">
							<label><?php esc_html_e( 'Client ID', 'chatbot' ); ?><input type="text" name="google_client_id" autocomplete="off" value="<?php echo esc_attr( $s['client_id'] ); ?>"></label>
							<label><?php esc_html_e( 'Client secret', 'chatbot' ); ?><input type="password" name="google_client_secret" autocomplete="new-password" placeholder="<?php echo esc_attr( $s['secret_hint'] ); ?>"></label>
						</div>
						<button type="button" class="button" data-qcsoc-google-save><?php esc_html_e( 'Save client', 'chatbot' ); ?></button>
					</li>
				</ol>
			</details>
			<p>
				<button type="button" class="button button-primary" data-qcsoc-google-connect <?php disabled( ! $s['client'] ); ?>><?php echo esc_html( in_array( $s['state'], array( 'connected', 'reconnect', 'failing' ), true ) ? __( 'Reconnect Google', 'chatbot' ) : __( 'Connect Google', 'chatbot' ) ); ?></button>
				<?php if ( in_array( $s['state'], array( 'connected', 'reconnect', 'failing' ), true ) ) : ?>
					<button type="button" class="button qcsoc-danger" data-qcsoc-google-disconnect><?php esc_html_e( 'Disconnect Google', 'chatbot' ); ?></button>
				<?php endif; ?>
				<span class="qcsoc-status" data-qcsoc-status role="status"></span>
			</p>
		</div>
		<?php
	}
}
