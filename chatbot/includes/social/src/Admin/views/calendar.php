<?php
/**
 * Calendar (screen 11): a month of Social posts (and AI Writer items when it
 * is active, through the Master module later).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Calendar\Calendar_Items;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month switch.
$qcsoc_month = isset( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : wp_date( 'Y-m' );
$qcsoc_first = strtotime( $qcsoc_month . '-01 00:00:00' );
$qcsoc_days  = (int) gmdate( 't', $qcsoc_first );
$qcsoc_start = (int) get_option( 'start_of_week', 1 );
$qcsoc_lead  = ( (int) gmdate( 'w', $qcsoc_first ) - $qcsoc_start + 7 ) % 7;
$qcsoc_items = Calendar_Items::between( get_gmt_from_date( $qcsoc_month . '-01 00:00:00' ), get_gmt_from_date( gmdate( 'Y-m-t', $qcsoc_first ) . ' 23:59:59' ) );
$qcsoc_byday = array();
foreach ( $qcsoc_items as $qcsoc_item ) {
	$qcsoc_byday[ (int) substr( $qcsoc_item['date'], 8, 2 ) ][] = $qcsoc_item;
}
$qcsoc_prev  = gmdate( 'Y-m', strtotime( '-1 month', $qcsoc_first ) );
$qcsoc_next  = gmdate( 'Y-m', strtotime( '+1 month', $qcsoc_first ) );
$qcsoc_today = wp_date( 'Y-m-d' );
?>
<section class="qcsoc-card" data-qcsoc="calendar">
	<div class="qcsoc-card__head">
		<h2><?php echo esc_html( date_i18n( 'F Y', $qcsoc_first ) ); ?></h2>
		<div class="qcsoc-actions">
			<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'calendar', array( 'month' => $qcsoc_prev ) ) ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'chatbot' ); ?>">‹</a>
			<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'calendar' ) ); ?>"><?php esc_html_e( 'Today', 'chatbot' ); ?></a>
			<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'calendar', array( 'month' => $qcsoc_next ) ) ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'chatbot' ); ?>">›</a>
		</div>
	</div>
	<div class="qcsoc-calendar" role="grid">
		<?php for ( $qcsoc_i = 0; $qcsoc_i < 7; $qcsoc_i++ ) : ?>
			<div class="qcsoc-calendar__head" role="columnheader"><?php echo esc_html( $GLOBALS['wp_locale']->get_weekday_abbrev( $GLOBALS['wp_locale']->get_weekday( ( $qcsoc_start + $qcsoc_i ) % 7 ) ) ); ?></div>
		<?php endfor; ?>
		<?php for ( $qcsoc_i = 0; $qcsoc_i < $qcsoc_lead; $qcsoc_i++ ) : ?>
			<div class="qcsoc-calendar__day is-blank" aria-hidden="true"></div>
		<?php endfor; ?>
		<?php for ( $qcsoc_d = 1; $qcsoc_d <= $qcsoc_days; $qcsoc_d++ ) : ?>
			<?php $qcsoc_date = $qcsoc_month . '-' . str_pad( (string) $qcsoc_d, 2, '0', STR_PAD_LEFT ); ?>
			<div class="qcsoc-calendar__day<?php echo $qcsoc_date === $qcsoc_today ? ' is-today' : ''; ?>" role="gridcell">
				<span class="qcsoc-calendar__num"><?php echo esc_html( (string) $qcsoc_d ); ?></span>
				<?php foreach ( isset( $qcsoc_byday[ $qcsoc_d ] ) ? $qcsoc_byday[ $qcsoc_d ] : array() as $qcsoc_item ) : ?>
					<a class="qcsoc-calendar__item is-<?php echo esc_attr( $qcsoc_item['status'] ); ?>" href="<?php echo esc_url( $qcsoc_item['url'] ); ?>"><?php echo esc_html( substr( $qcsoc_item['date'], 11, 5 ) . ' ' . $qcsoc_item['title'] ); ?></a>
				<?php endforeach; ?>
				<?php if ( current_user_can( 'wpbot_social_use' ) && $qcsoc_date >= $qcsoc_today ) : ?>
					<a class="qcsoc-calendar__add" href="<?php echo esc_url( Admin_Controller::url( 'compose', array( 'date' => $qcsoc_date ) ) ); ?>" aria-label="<?php /* translators: %s: date. */ echo esc_attr( sprintf( __( 'Create a post for %s', 'chatbot' ), $qcsoc_date ) ); ?>">+</a>
				<?php endif; ?>
			</div>
		<?php endfor; ?>
	</div>
</section>
