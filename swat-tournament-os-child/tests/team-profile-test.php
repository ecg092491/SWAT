<?php
/**
 * Team profile aggregation (inc/team-profile.php) against the Playbook team
 * profile for Canes Triad 7U Black, 2026 Fall (15-2-0, 214 RS, 77 RA).
 * Run: php tests/team-profile-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( preg_replace( "/['’]/u", '', (string) $s ) ) ), '-' ); }
require __DIR__ . '/../inc/team-profile.php';

$t = 'Canes Triad 7U Black';
$rows = array(
	array( 'August Heat Championship', '2026-08-22', 'NC Made 7U', 0, $t, 15 ), array( 'August Heat Championship', '2026-08-22', $t, 21, 'Flying Pigs', 4 ),
	array( 'August Heat Championship', '2026-08-23', $t, 18, 'Flying Pigs', 1 ), array( 'August Heat Championship', '2026-08-23', $t, 17, 'Carolina Rapid Fire Fall 7u', 2 ),
	array( 'FNL 9/4', '2026-09-04', $t, 11, 'Flying Pigs', 1 ), array( 'FNL 9/4', '2026-09-04', 'Flying Pigs', 2, $t, 6 ), array( 'FNL 9/4', '2026-09-04', $t, 15, 'Carolina Rapid Fire Fall 7u', 9 ),
	array( 'Strike Out Cancer', '2026-09-12', 'Carolina Strutterz 8U', 3, $t, 11 ), array( 'Strike Out Cancer', '2026-09-12', $t, 2, 'Lightning Jacks', 11 ),
	array( 'Strike Out Cancer', '2026-09-13', $t, 9, 'Lightning Jacks', 7 ), array( 'Strike Out Cancer', '2026-09-13', 'FS Bulldogs 8U (Brooks)', 10, $t, 0 ),
	array( 'SNL 9/26', '2026-09-26', $t, 19, 'NC Made 7U', 7 ), array( 'SNL 9/26', '2026-09-26', 'Flying Pigs', 2, $t, 11 ), array( 'SNL 9/26', '2026-09-26', $t, 13, 'Carolina Rapid Fire Fall 7u', 5 ),
	array( 'FNL 10/2', '2026-10-02', 'Flying Pigs', 1, $t, 16 ), array( 'FNL 10/2', '2026-10-02', $t, 11, 'Carolina Rapid Fire Fall 7u', 3 ), array( 'FNL 10/2', '2026-10-02', $t, 19, 'Carolina Chaos', 9 ),
);
$games = array();
foreach ( $rows as $r ) {
	$games[] = array( 'event_name' => $r[0], 'date' => $r[1], 'time' => '10:00', 'field' => 'SW 7', 'division' => '7U Open', 'away' => $r[2], 'away_score' => (string) $r[3], 'home' => $r[4], 'home_score' => (string) $r[5], 'status' => 'final' );
}
// Noise that must NOT count: another team, an unplayed future game, a cancelled past game, a spring game.
$games[] = array( 'event_name' => 'Pink Ribbon Classic', 'date' => '2026-10-10', 'time' => '15:00', 'field' => 'SW 2', 'division' => '8U Open', 'away' => $t, 'away_score' => '', 'home' => 'Carolina Strutterz 8U', 'home_score' => '', 'status' => 'scheduled' );
$games[] = array( 'event_name' => 'X', 'date' => '2026-09-30', 'time' => '10:00', 'field' => 'SW 1', 'division' => '7U Open', 'away' => $t, 'away_score' => '', 'home' => 'Y', 'home_score' => '', 'status' => 'cancelled' );
$games[] = array( 'event_name' => 'Spring Kickoff', 'date' => '2026-04-11', 'time' => '10:00', 'field' => 'SW 1', 'division' => '7U Open', 'away' => $t, 'away_score' => '5', 'home' => 'Z', 'home_score' => '5', 'status' => 'final' );
$games[] = array( 'event_name' => 'FNL 10/2', 'date' => '2026-10-02', 'time' => '10:00', 'field' => 'SW 1', 'division' => '7U Open', 'away' => 'Flying Pigs', 'away_score' => '3', 'home' => 'NC Made 7U', 'home_score' => '2', 'status' => 'final' );

$p = swat_child_team_profile_build( $games, 'canes-triad-7u-black', '2026-10-08' );
$fall = $p['seasons']['2026-fall']['record'] ?? array();
$fail = 0;
$check = static function ( $name, $ok ) use ( &$fail ) { if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };
$check( 'name', 'Canes Triad 7U Black' === $p['name'] );
$check( 'record 15-2-0', 15 === $fall['w'] && 2 === $fall['l'] && 0 === $fall['t'] );
$check( 'win % 88.24', 88.24 === $fall['pct'] );
$check( 'runs 214 / 77 / +137', 214 === $fall['rs'] && 77 === $fall['ra'] && 137 === $fall['rd'] );
$check( 'averages 12.59 / 4.53 / 8.06', 12.59 === $fall['avg_rs'] && 4.53 === $fall['avg_ra'] && 8.06 === $fall['avg_rd'] );
$events = $p['seasons']['2026-fall']['events'];
$check( '5 fall events', 5 === count( $events ) );
$check( 'August Heat 4-0, 17.75 / 1.75 / +16', 4 === $events[0]['record']['w'] && 17.75 === $events[0]['record']['avg_rs'] && 1.75 === $events[0]['record']['avg_ra'] && 16.0 === (float) $events[0]['record']['avg_rd'] );
$check( 'Strike Out Cancer 2-2, -2.25', 2 === $events[2]['record']['l'] && -2.25 === $events[2]['record']['avg_rd'] );
$check( 'spring season separate (tie)', 1 === ( $p['seasons']['2026-spring']['record']['t'] ?? 0 ) );
$check( 'upcoming game listed, not counted', 1 === count( $p['upcoming'] ) && 'Carolina Strutterz 8U' === $p['upcoming'][0]['opponent'] );
$check( 'unknown team -> null', null === swat_child_team_profile_build( $games, 'nobody', '2026-10-08' ) );
$check( 'season labels', array( 'key' => '2026-fall', 'label' => '2026 Fall' ) === swat_child_season_for( '2026-08-22' ) && '2026-spring' === swat_child_season_for( '2026-06-30' )['key'] );
echo $fail ? "$fail failure(s)\n" : "12 team profile checks, 0 failure(s)\n";
exit( $fail ? 1 : 0 );
