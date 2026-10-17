<?php
/**
 * Homepage button/badge rules (inc/home-data.php swat_child_home_action_rule)
 * and bracket placeholder detection (inc/team-finder.php).
 * Run in any PHP 8 environment, no WordPress needed:
 *   php tests/home-actions-test.php
 * Exit code 0 = all pass.
 */

define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }

require __DIR__ . '/../inc/home-data.php';
require __DIR__ . '/../inc/team-finder.php';

$statuses = array( 'open', 'limited', 'sold_out', 'closed', 'complete', 'cancelled' );
$fail     = 0;
$count    = 0;

foreach ( array( true, false ) as $has_schedule ) {
	foreach ( array( true, false ) as $started ) {
		foreach ( array( true, false ) as $complete ) {
			if ( $complete && ! $started ) {
				continue; // An event cannot end before it starts.
			}
			foreach ( $statuses as $status ) {
				$in   = compact( 'has_schedule', 'started', 'complete' ) + array( 'reg_status' => $status );
				$out  = swat_child_home_action_rule( $in );
				$acts = $out['actions'];
				$why  = array();
				++$count;

				// Every card links to its event details; every action is known.
				if ( ! in_array( 'details', $acts, true ) ) { $why[] = 'missing EVENT DETAILS'; }
				if ( array_diff( $acts, array( 'schedule', 'register', 'results', 'details' ) ) ) { $why[] = 'unknown action'; }
				// Published + live schedule removes registration actions and badges.
				if ( $has_schedule && ! $complete && 'cancelled' !== $status ) {
					if ( 'schedule' !== $acts[0] ) { $why[] = 'VIEW SCHEDULE must be primary'; }
					if ( in_array( 'register', $acts, true ) || $out['badge'] ) { $why[] = 'registration shown beside a published schedule'; }
				}
				// VIEW SCHEDULE only when a schedule exists.
				if ( ! $has_schedule && in_array( 'schedule', $acts, true ) ) { $why[] = 'VIEW SCHEDULE without a schedule'; }
				// REGISTER only for a future event that is accepting entries.
				$may_register = ! $has_schedule && ! $started && in_array( $status, array( 'open', 'limited' ), true );
				if ( in_array( 'register', $acts, true ) !== $may_register ) { $why[] = 'REGISTER shown=' . var_export( in_array( 'register', $acts, true ), true ) . ' expected=' . var_export( $may_register, true ); }
				// Never an OPEN badge unless registration is actually open.
				if ( $out['badge'] && in_array( $status, array( 'open', 'limited' ), true ) && ! $may_register ) { $why[] = 'OPEN/LIMITED badge on an event not accepting entries'; }
				// Completed events: results (when games exist), never register.
				if ( $complete && 'cancelled' !== $status && ( in_array( 'register', $acts, true ) || ( $has_schedule && 'results' !== $acts[0] ) ) ) { $why[] = 'completed event actions'; }
				// Cancelled: details only, with the Cancelled badge.
				if ( 'cancelled' === $status && ( array( 'details' ) !== $acts || ! $out['badge'] ) ) { $why[] = 'cancelled handling'; }

				if ( $why ) {
					++$fail;
					printf( "FAIL %s => %s (%s)\n", json_encode( $in ), json_encode( $out ), implode( '; ', $why ) );
				}
			}
		}
	}
}

$placeholders = array( 'Division Place 4' => true, 'Winner # 1' => true, 'TBD' => true, 'Loser # 2' => true, '#2 Seed' => true, '' => true, 'Pool A #1' => true, 'Canes Triad Gold 13U' => false, '9U Canes Triad Gold' => false, 'Winners Baseball 10U' => false, 'The Legacy' => false, "H'Town Bombers" => false );
foreach ( $placeholders as $label => $expected ) {
	++$count;
	if ( swat_child_is_placeholder_team( $label ) !== $expected ) {
		++$fail;
		printf( "FAIL placeholder '%s' expected %s\n", $label, var_export( $expected, true ) );
	}
}

printf( "%d home action/placeholder checks, %d failure(s)\n", $count, $fail );
exit( $fail ? 1 : 0 );
