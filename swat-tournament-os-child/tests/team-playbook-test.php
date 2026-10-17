<?php
/**
 * Playbook team page parsing + merge (inc/team-playbook.php), live state of
 * 2026-10-08: WordPress has 15 of Canes Triad 7U Black's 17 Fall games (the two
 * August Heat bracket wins never synced). Expected after merge: 15-2-0, 214/77.
 * Run: php tests/team-playbook-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( preg_replace( "/['’]/u", '', (string) $s ) ) ), '-' ); }
require __DIR__ . '/../inc/team-profile.php';
require __DIR__ . '/../inc/team-playbook.php';

$fail  = 0;
$n     = 0;
$check = static function ( $name, $ok ) use ( &$fail, &$n ) { ++$n; if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };

$page = swat_child_pb_parse_team_page( file_get_contents( __DIR__ . '/fixtures/playbook-team-canes-7u-black.html' ) );
$check( 'parsed 18 game rows', 18 === count( $page['games'] ) );
$check( 'parsed 5 events with dates', 5 === count( $page['events'] ) && '2026-08-22' === $page['events']['August Heat Championship']['date'] );
$check( 'event name decoded', isset( $page['events']['Friday Night Lights 9/4 - 7U & 12U'] ) );
$check( 'score with nbsp', 18 === $page['games'][2]['score_1'] && 1 === $page['games'][2]['score_2'] );
$check( 'unplayed row has no score', null === $page['games'][17]['score_1'] );
$check( 'learned 8 team ids', 8 === count( $page['teams'] ) && '059739fb-c054-4f26-a2a4-1cfce0f578cc' === $page['teams']['flying-pigs'] );
$check( 'no roster data', false === strpos( serialize( $page ), 'Player X' ) && false === strpos( serialize( $page ), '2018-01-01' ) );
$check( 'url parse', array( 'season' => '2026-fall', 'id' => '1f1476ea-fab9-47d9-9f4b-80a62d3ff9ab' ) === swat_child_pb_parse_team_url( 'https://play.swatourneys.com/team/details/2026-fall/1f1476ea-fab9-47d9-9f4b-80a62d3ff9ab' ) );
$check( 'url parse rejects other hosts', null === swat_child_pb_parse_team_url( 'https://evil.example/team/details/2026-fall/1f1476ea-fab9-47d9-9f4b-80a62d3ff9ab' ) );

// WordPress data as live: Aug Heat bracket games missing; one wrong WP-only final that Playbook does not list.
$t = 'Canes Triad 7U Black';
$wp = array(
	array( 'August Heat Championship', '2026-08-22', '09:00', 'NC Made 7U', 0, $t, 15, 1 ), array( 'August Heat Championship', '2026-08-22', '11:30', $t, 21, 'Flying Pigs', 4, 1 ),
	array( 'Friday Night Lights 9/4 - 7U & 12U', '2026-09-04', '18:00', $t, 11, 'Flying Pigs', 1, 1 ), array( 'Friday Night Lights 9/4 - 7U & 12U', '2026-09-04', '19:15', 'Flying Pigs', 2, $t, 6, 1 ), array( 'Friday Night Lights 9/4 - 7U & 12U', '2026-09-04', '20:30', $t, 15, 'Carolina Rapid Fire Fall 7u', 9, 2 ),
	array( 'Strike Out Cancer: Jonah Hammett Memorial Tournament', '2026-09-12', '09:00', 'Carolina Strutterz 8U', 3, $t, 11, 1 ), array( 'Strike Out Cancer: Jonah Hammett Memorial Tournament', '2026-09-12', '11:00', $t, 2, 'Lightning Jacks', 11, 1 ),
	array( 'Strike Out Cancer: Jonah Hammett Memorial Tournament', '2026-09-13', '10:00', $t, 9, 'Lightning Jacks', 7, 2 ), array( 'Strike Out Cancer: Jonah Hammett Memorial Tournament', '2026-09-13', '12:00', 'FS Bulldogs 8U (Brooks)', 10, $t, 0, 2 ),
	array( 'Saturday Night Lights 9/26 - 7U & 12U', '2026-09-26', '16:00', $t, 19, 'NC Made 7U', 7, 1 ), array( 'Saturday Night Lights 9/26 - 7U & 12U', '2026-09-26', '17:15', 'Flying Pigs', 2, $t, 11, 1 ), array( 'Saturday Night Lights 9/26 - 7U & 12U', '2026-09-26', '18:30', $t, 13, 'Carolina Rapid Fire Fall 7u', 5, 2 ),
	array( 'Friday Night Lights 10/2 7U & 11U', '2026-10-02', '19:00', 'Flying Pigs', 1, $t, 16, 1 ), array( 'Friday Night Lights 10/2 7U & 11U', '2026-10-02', '20:00', $t, 11, 'Carolina Rapid Fire Fall 7u', 3, 1 ), array( 'Friday Night Lights 10/2 7U & 11U', '2026-10-02', '21:00', $t, 19, 'Carolina Chaos', 9, 2 ),
	array( 'Bogus Event', '2026-09-20', '10:00', $t, 1, 'Nobody', 9, 1 ),
);
$games = array();
foreach ( $wp as $r ) {
	$games[] = array( 'event_name' => $r[0], 'date' => $r[1], 'time' => $r[2], 'field' => 'SW 7', 'division' => '7U Open', 'away' => $r[3], 'away_score' => (string) $r[4], 'home' => $r[5], 'home_score' => (string) $r[6], 'status' => 'final', 'game_type_id' => $r[7] );
}
$games[] = array( 'event_name' => 'Pink Ribbon Classic', 'date' => '2026-10-10', 'time' => '15:00', 'field' => 'SW 2', 'division' => '8U Open', 'away' => $t, 'away_score' => '', 'home' => 'Carolina Strutterz 8U', 'home_score' => '', 'status' => 'scheduled' );
$games[] = array( 'event_name' => 'X', 'date' => '2026-09-01', 'time' => '10:00', 'field' => 'SW 1', 'division' => '9U Open', 'away' => 'Other A', 'away_score' => '3', 'home' => 'Other B', 'home_score' => '2', 'status' => 'final' );

$before = swat_child_team_profile_build( $games, 'canes-triad-7u-black', '2026-10-08' );
$check( 'before merge 13-3 incl. bogus game (live symptom 13-2)', 13 === $before['seasons']['2026-fall']['record']['w'] && 3 === $before['seasons']['2026-fall']['record']['l'] );

$merged = swat_child_pb_merge_games( $games, 'canes-triad-7u-black', '2026-fall', $page, '2026-10-08' );
$p   = swat_child_team_profile_build( $merged, 'canes-triad-7u-black', '2026-10-08' );
$rec = $p['seasons']['2026-fall']['record'];
$check( 'record 15-3-0 (Playbook 15-2-0 + the WP-only game, which is kept)', 15 === $rec['w'] && 3 === $rec['l'] && 0 === $rec['t'] );
$check( '18 games', 18 === $rec['gp'] );
$check( 'runs 215 / 86', 215 === $rec['rs'] && 86 === $rec['ra'] );
$ev = array();
foreach ( $p['seasons']['2026-fall']['events'] as $e ) { $ev[ $e['event'] ] = $e['record']['w'] . '-' . $e['record']['l']; }
$check( 'August Heat 4-0', '4-0' === ( $ev['August Heat Championship'] ?? '' ) );
$check( 'WP-only final kept (add-only merge)', isset( $ev['Bogus Event'] ) );
$check( 'upcoming kept', 1 === count( $p['upcoming'] ) );
$check( 'other teams untouched', 1 === count( array_filter( $merged, static function ( $g ) { return 'Other A' === $g['away']; } ) ) );
$order = array_map( static function ( $g ) { return $g['us'] . '-' . $g['them']; }, $p['seasons']['2026-fall']['games'] );
$check( 'Aug Heat order 15-0, 21-4, 18-1, 17-2', array( '15-0', '21-4', '18-1', '17-2' ) === array_slice( $order, 0, 4 ) );
$matched = array_values( array_filter( $p['seasons']['2026-fall']['games'], static function ( $g ) { return 'Friday Night Lights 10/2 7U & 11U' === $g['event'] && 19 === $g['us']; } ) );
$check( 'matched WP game keeps its type', 'Bracket' === $matched[0]['type'] );
$added = array_values( array_filter( $p['seasons']['2026-fall']['games'], static function ( $g ) { return 18 === $g['us']; } ) );
$check( 'Playbook-only game has date and no invented type', '2026-08-22' === $added[0]['date'] && '' === $added[0]['type'] );
$check( 'empty page → nothing', array() === swat_child_pb_parse_team_page( '' )['games'] );

echo "$n Playbook team checks, $fail failure(s)\n";
exit( $fail ? 1 : 0 );
