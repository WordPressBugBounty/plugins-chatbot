<?php
/**
 * Test-only: known visits for tests/e2e/reports.py. Run with
 *     wp eval-file reports-seed.php
 * It deletes all Insights data first. Never run on a live site.
 *
 * This week (the 7 days before today, site time): 10 visits
 *   6 desktop, 3 mobile, 1 tablet; 4 returning; 7 recorded; 60 s each; 2 pages each
 *   (/pricing/ "Pricing" and /contact/ "Contact"); rage on 2, dead on 1, error on 1
 *   (3 frustrated); sources google.com x4, utm newsletter x3, direct x3;
 *   countries US x5, BD x3, unknown x2; 4 visits two days ago, 1 on each other day.
 * The 7 days before: 5 desktop visits, 1 page each, no frustration.
 * Outside both: 1 visit today and 1 visit 20 days ago.
 */

use QuantumCloud\Insights\Storage\Schema;
use QuantumCloud\Insights\Storage\Session_Repository;

global $wpdb;
$repo = new Session_Repository();
$repo->truncate();

$tz    = wp_timezone();
$today = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0 );
$utc   = static function ( DateTimeImmutable $d ) {
	return gmdate( 'Y-m-d H:i:s', $d->getTimestamp() );
};
$pricing = $repo->url_id( home_url( '/pricing/' ), 'Pricing' );
$contact = $repo->url_id( home_url( '/contact/' ), 'Contact' );

$add = static function ( DateTimeImmutable $at, array $s, array $pages ) use ( $wpdb, $utc ) {
	$wpdb->insert(
		Schema::table( 'sessions' ),
		array_merge(
			array(
				'uuid'          => wp_generate_uuid4(),
				'started_at'    => $utc( $at ),
				'last_seen_at'  => $utc( $at->modify( '+60 seconds' ) ),
				'duration_s'    => 60,
				'pageviews'     => count( $pages ),
				'device'        => 'desktop',
				'status'        => 1,
				'has_recording' => 0,
			),
			$s
		)
	);
	$sid = (int) $wpdb->insert_id;
	foreach ( $pages as $i => $url_id ) {
		$wpdb->insert(
			Schema::table( 'pageviews' ),
			array(
				'session_id'     => $sid,
				'uuid'           => wp_generate_uuid4(),
				'url_id'         => $url_id,
				'seq'            => $i,
				'started_at'     => $utc( $at->modify( '+' . ( $i * 30 ) . ' seconds' ) ),
				'last_seen_at'   => $utc( $at->modify( '+' . ( $i * 30 + 30 ) . ' seconds' ) ),
				'duration_ms'    => 30000,
				'max_scroll_pct' => 0 === $i ? 50 : 70,
				'aggregated'     => 1,
			)
		);
	}
	return $sid;
};

// This week. Index => day offset (two days ago gets four visits).
$days     = array( 2, 2, 2, 2, 1, 3, 4, 5, 6, 7 );
$devices  = array( 'desktop', 'desktop', 'desktop', 'desktop', 'desktop', 'desktop', 'mobile', 'mobile', 'mobile', 'tablet' );
$sources  = array( array( 'referrer_host' => 'google.com' ), array( 'referrer_host' => 'google.com' ), array( 'referrer_host' => 'google.com' ), array( 'referrer_host' => 'google.com' ), array( 'utm_source' => 'newsletter' ), array( 'utm_source' => 'newsletter' ), array( 'utm_source' => 'newsletter' ), array(), array(), array() );
$country  = array( 'US', 'US', 'US', 'US', 'US', 'BD', 'BD', 'BD', '', '' );
$flags    = array( Session_Repository::FLAG_RAGE | Session_Repository::FLAG_DEAD, Session_Repository::FLAG_RAGE, Session_Repository::FLAG_ERROR, 0, 0, 0, 0, 0, 0, 0 );
$error_id = 0;
foreach ( $days as $i => $d ) {
	$sid = $add(
		$today->modify( '-' . $d . ' days' )->setTime( 10, $i ),
		array_merge(
			array(
				'device'        => $devices[ $i ],
				'country'       => $country[ $i ],
				'is_returning'  => $i < 4 ? 1 : 0,
				'has_recording' => $i < 7 ? 1 : 0,
				'flags'         => $flags[ $i ],
			),
			$sources[ $i ]
		),
		array( $pricing, $contact )
	);
	if ( Session_Repository::FLAG_ERROR === $flags[ $i ] ) {
		$error_id = $sid;
	}
}
$wpdb->insert(
	Schema::table( 'signals' ),
	array(
		'session_id' => $error_id,
		'type'       => 'error',
		'data'       => wp_json_encode( array( 'message' => 'Checkout failed for jane@example.com' ) ),
		'created_at' => $utc( $today->modify( '-3 days' )->setTime( 10, 5 ) ),
	)
);

// The 7 days before.
for ( $i = 0; $i < 5; $i++ ) {
	$add( $today->modify( '-' . ( 8 + $i ) . ' days' )->setTime( 9, 0 ), array(), array( $pricing ) );
}

// Outside both periods.
$add( $today->setTime( 0, 30 ), array( 'flags' => Session_Repository::FLAG_RAGE ), array( $pricing ) );
$add( $today->modify( '-20 days' )->setTime( 9, 0 ), array(), array( $pricing ) );

echo 'seeded';
