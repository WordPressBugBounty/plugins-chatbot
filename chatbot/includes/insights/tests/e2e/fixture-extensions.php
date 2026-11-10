<?php
/**
 * Test-only fixture for tests/e2e/extensions.py: a tiny "feature" that uses
 * every core extension point. Copy to wp-content/mu-plugins/qcin-ext-fixture.php
 * and fixture-extensions.js to the site's web root. It does nothing unless the
 * option qcin_test_ext is '1'. Never install it on a live site.
 */
defined( 'ABSPATH' ) || exit;

if ( '1' !== get_option( 'qcin_test_ext' ) ) {
	return;
}

// 0.5.0: an extra signal type (and one with a core bit, which must be ignored).
add_filter( 'qcin/signal_types', function ( $t ) {
	$t['wiggle'] = array( 'bit' => 256, 'label' => 'Wiggles', 'short' => 'Wiggle', 'help' => 'Fixture signal.' );
	$t['bad']    = array( 'bit' => 4, 'label' => 'Bad' );
	return $t;
} );

// 0.5.0: a segment: ?demo_seg=mobile keeps mobile visits only.
add_filter( 'qcin/segment', function ( $seg, $params ) {
	if ( isset( $params['demo_seg'] ) && 'mobile' === $params['demo_seg'] ) {
		return array( 'where' => array( 's.device = %s' ), 'params' => array( 'mobile' ) );
	}
	return $seg;
}, 10, 2 );

// AI feature switch (added early, as the docs ask).
add_filter( 'qcin/ai_features', function ( $f ) {
	$f['demo'] = array( 'label' => 'Demo insight', 'help' => 'Fixture AI feature.', 'default' => true );
	return $f;
} );

add_action( 'qcin/register_features', function ( $registry ) {
	$registry->add( new class() implements \QuantumCloud\Insights\Features\Feature {
		public function id() {
			return 'test.demo';
		}
		public function register( \QuantumCloud\Insights\Module $module ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcin_demo';

			add_filter( 'qcin/admin_screens', function ( $s ) {
				$s[] = array( 'id' => 'demo', 'label' => 'Demo screen', 'icon' => 'heatmaps' );
				return $s;
			} );
			add_filter( 'qcin/admin_scripts', function ( $s ) {
				$s[] = array( 'handle' => 'qcin-demo', 'src' => home_url( '/fixture-extensions.js' ), 'data' => array( 'hello' => 'world' ), 'data_var' => 'qcinDemo' );
				return $s;
			} );
			add_filter( 'qcin/admin_data', function ( $d ) {
				$d['demoFlag'] = true;
				return $d;
			} );
			add_filter( 'qcin/admin_session', function ( $d, $row ) {
				$d['demo'] = (int) $row->id;
				return $d;
			}, 10, 2 );
			// ?demo=first shows only the oldest recorded session.
			add_filter( 'qcin/session_query', function ( $q, $filters ) {
				global $wpdb;
				if ( isset( $filters['extra']['demo'] ) && 'first' === $filters['extra']['demo'] ) {
					$q['where'][]  = 's.id = %d';
					$q['params'][] = (int) $wpdb->get_var( "SELECT MIN(id) FROM {$wpdb->prefix}qcin_sessions WHERE has_recording = 1" );
				}
				return $q;
			}, 10, 2 );
			add_filter( 'qcin/tracker_extensions', function ( $e ) {
				$e[] = array( 'id' => 'demo', 'src' => home_url( '/fixture-extensions.js?tracker=1' ), 'when' => 'any' );
				return $e;
			} );
			add_action( 'qcin/ingest_ext', function ( $ext, $session ) {
				if ( empty( $ext['demo'] ) ) {
					return;
				}
				$seen = (array) get_option( 'qcin_test_ext_seen', array() );
				foreach ( $ext['demo'] as $item ) {
					$seen[] = array( 'session' => (int) $session->id, 'n' => isset( $item['n'] ) ? (int) $item['n'] : -1 );
				}
				update_option( 'qcin_test_ext_seen', $seen, false );
			}, 10, 2 );
			// Feature table, cleared by "Delete all".
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				$wpdb->query( "CREATE TABLE {$table} ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, session_id bigint(20) unsigned NOT NULL DEFAULT 0, PRIMARY KEY (id) )" );
			}
			add_filter( 'qcin/schema_tables', function ( $t ) use ( $table ) {
				$t[] = $table;
				$t[] = 'wp_posts'; // Must be ignored (not a qcin_ table).
				return $t;
			} );
			add_action( 'qcin/delete_all', function () {
				update_option( 'qcin_test_delete_all', (int) get_option( 'qcin_test_delete_all', 0 ) + 1, false );
			} );
			add_action( 'qcin/session_deleted', function ( $id ) {
				$d   = (array) get_option( 'qcin_test_deleted', array() );
				$d[] = (int) $id;
				update_option( 'qcin_test_deleted', $d, false );
			} );
			add_filter( 'qcin/health_jobs', function ( $j ) {
				$j[] = 'qcin_test_demo_job';
				return $j;
			} );
			// Settings capability switch for the role test.
			add_filter( 'qcin/capability', function ( $cap, $context ) {
				return ( 'settings' === $context && '1' === get_option( 'qcin_test_cap' ) ) ? 'do_not_allow' : $cap;
			}, 20, 2 );
			add_action( 'rest_api_init', function () {
				register_rest_route( 'qcin/v1', '/test/demo-ai', array(
					'methods'             => 'POST',
					'permission_callback' => function () {
						return \QuantumCloud\Insights\Module::instance()->can( 'view' );
					},
					'callback'            => function ( $r ) {
						$out = ( new \QuantumCloud\Insights\AI\Insight_Service() )->run(
							'demo',
							'page:' . (int) $r['page'],
							'd1',
							array( array( 'role' => 'user', 'content' => 'DEMO_JSON aggregates for page ' . (int) $r['page'] ) ),
							array( 'force' => (bool) $r['force'] )
						);
						return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
					},
				) );
			} );
		}
	} );
} );
