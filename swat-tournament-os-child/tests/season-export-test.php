<?php
/**
 * Playbook season games export (inc/season-export.php) with the real 2026 Fall
 * export shipped in data/. Run: php tests/season-export-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( preg_replace( "/['’]/u", '', (string) $s ) ) ), '-' ); }
require __DIR__ . '/../inc/team-profile.php';
require __DIR__ . '/../inc/season-export.php';

$fail  = 0;
$n     = 0;
$check = static function ( $name, $ok ) use ( &$fail, &$n ) { ++$n; if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };

$p = swat_child_export_parse_csv( file_get_contents( __DIR__ . '/../data/playbook-season-2026-fall.csv' ) );
$g = $p['games'];
$check( '563 games, no errors', 563 === count( $g ) && ! $p['errors'] );
$check( 'all finished', 563 === count( array_filter( $g, static function ( $x ) { return 'final' === $x['status']; } ) ) );
$check( 'date + 24h time', '2026-08-07' === $g[0]['date'] && '18:00' === $g[0]['time'] );
$check( 'team 1 away', 'Canes Triad Yellow' === $g[0]['away'] && '15' === $g[0]['away_score'] );
$check( 'pool/bracket', 1 === $g[0]['game_type_id'] && 2 === $g[3]['game_type_id'] && 'Bracket' === $g[3]['game_type'] );
$check( 'double spaces collapsed', ! preg_grep( '/\s{2,}/', array_merge( array_column( $g, 'away' ), array_column( $g, 'home' ) ) ) );
$aug = array_filter( $g, static function ( $x ) { return 'August Heat Championship' === $x['event_name']; } );
$check( 'August Heat 64 games', 64 === count( $aug ) );
$check( 'one season', array( '2026-fall' ) === array_keys( swat_child_export_by_season( $g ) ) );

$bad = swat_child_export_parse_csv( "Name,Score\nA,1\n" );
$check( 'rejects other CSVs', ! $bad['games'] && $bad['errors'] );

// Live WordPress state: Aug Heat bracket games missing; a current-weekend upcoming game; a different event not in the export.
$t  = 'Canes Triad 7U Black';
$wp = array(
	array( 'event_name' => 'August Heat Championship', 'date' => '2026-08-22', 'time' => '09:00', 'away' => 'NC Made 7U', 'home' => $t, 'away_score' => '0', 'home_score' => '15', 'status' => 'final', 'division' => '7U Open' ),
	array( 'event_name' => 'August Heat Championship', 'date' => '2026-08-22', 'time' => '11:30', 'away' => $t, 'home' => 'Flying Pigs', 'away_score' => '21', 'home_score' => '4', 'status' => 'final', 'division' => '7U Open' ),
	array( 'event_name' => 'Pink Ribbon Classic', 'date' => '2026-10-10', 'time' => '15:00', 'away' => $t, 'home' => 'Carolina Strutterz 8U', 'away_score' => '', 'home_score' => '', 'status' => 'scheduled', 'division' => '8U Open' ),
	array( 'event_name' => 'Fall Ball Invitational', 'date' => '2026-10-04', 'time' => '10:00', 'away' => $t, 'home' => 'Z', 'away_score' => '5', 'home_score' => '4', 'status' => 'final', 'division' => '7U Open' ),
);
$merged = swat_child_export_merge( $wp, $g );
$prof   = swat_child_team_profile_build( $merged, 'canes-triad-7u-black', '2026-10-08' );
$rec    = $prof['seasons']['2026-fall']['record'];
$check( 'Canes 16-2-0 (Playbook 15-2-0 + the non-export event)', 16 === $rec['w'] && 2 === $rec['l'] && 0 === $rec['t'] );
$only = swat_child_team_profile_build( swat_child_export_merge( array_slice( $wp, 0, 3 ), $g ), 'canes-triad-7u-black', '2026-10-08' );
$r2   = $only['seasons']['2026-fall']['record'];
$check( 'Canes 15-2-0, 214/77 = Playbook profile', 15 === $r2['w'] && 2 === $r2['l'] && 214 === $r2['rs'] && 77 === $r2['ra'] && 17 === $r2['gp'] );
$check( 'upcoming kept', 1 === count( $only['upcoming'] ) );
$bracket = array_values( array_filter( $only['seasons']['2026-fall']['games'], static function ( $x ) { return 18 === $x['us']; } ) );
$check( 'Aug Heat bracket game has real date/time/type', '2026-08-23' === $bracket[0]['date'] && '11:30' === $bracket[0]['time'] && 'Bracket' === $bracket[0]['type'] );

// 1.0.25: uploads combine with earlier exports by Playbook id.
$one = $g[0];
$other = $g[1];
$corrected = $one;
$corrected['away_score'] = '99';
$partial = swat_child_export_combine( array( $one, $other ), array( $corrected ) );
$check( 'partial upload preserves omitted game', 2 === count( $partial ) && in_array( $other, $partial, true ) );
$check( 'same ID updates score', '99' === $partial[0]['away_score'] );
$check( 'repeated uploads are idempotent', $partial === swat_child_export_combine( $partial, array( $corrected ) ) );
$second = $one; $second['playbook_id'] = '99999999';
$check( 'different IDs retain identical-looking games', 2 === count( swat_child_export_combine( array( $one ), array( $second ) ) ) );
$check( 'shipped 563 + full re-upload = 563', 563 === count( swat_child_export_combine( $g, $g ) ) );

// WordPress vs export: a stale same-id live row is replaced, not doubled.
$live = $one; $live['status'] = 'in_progress';
$check( 'export final replaces same-ID live row', 1 === count( swat_child_export_merge( array( $live ), array( $one ) ) ) );
$legacy = $one; unset( $legacy['playbook_id'] );
$check( 'id-less WordPress final replaced by export', 1 === count( swat_child_export_merge( array( $legacy ), array( $one ) ) ) );

// Regression (1.0.24 double counted these): WordPress rows that differ from the
// export in id, time, division label or duplication. Playbook: 15-2-0, 17 games.
$canes = array_values( array_filter( $g, static function ( $x ) use ( $t ) { return $x['away'] === $t || $x['home'] === $t; } ) );
$strip = static function ( $x ) { unset( $x['playbook_id'] ); return $x; };
$record = static function ( $wp_rows ) use ( $g ) {
	$p = swat_child_team_profile_build( swat_child_export_merge( $wp_rows, $g ), 'canes-triad-7u-black', '2026-10-08' );
	$r = $p['seasons']['2026-fall']['record'];
	return $r['w'] . '-' . $r['l'] . '-' . $r['t'] . '/' . $r['gp'];
};
$base = array_map( $strip, $canes );
$check( 'WP rows identical to export: 15-2-0/17', '15-2-0/17' === $record( $base ) );
$moved = $base; $moved[0]['time'] = '09:30' === $moved[0]['time'] ? '10:00' : '09:30';
$check( 'rescheduled WP time: still 15-2-0/17', '15-2-0/17' === $record( $moved ) );
$label = array_map( static function ( $x ) { $x['division'] = preg_replace( '/\s+Open$/', '', $x['division'] ); return $x; }, $base );
$check( 'WP division without "Open": still 15-2-0/17', '15-2-0/17' === $record( $label ) );
$dupe = $base; $dupe[] = $canes[3];
$check( 'post row + bracket row of one game: still 15-2-0/17', '15-2-0/17' === $record( $dupe ) );
$check( 'WP rows with ids: 15-2-0/17', '15-2-0/17' === $record( $canes ) );

// Event page: only the missing games are added.
$aug     = array_values( $aug );
$display = array_values( array_filter( $aug, static function ( $x ) { return 'Pool' === $x['game_type']; } ) );
foreach ( $display as &$d ) { unset( $d['playbook_id'] ); } unset( $d ); // pool posts carry no playbook id in swat_os_event_games()
$missing = swat_child_export_missing_for_event( $display, $aug );
$check( 'Aug Heat event page gains the 28 bracket games', 28 === count( $missing ) && ! array_diff( array_column( $missing, 'game_type' ), array( 'Bracket' ) ) );
$check( 'nothing added when all present', ! swat_child_export_missing_for_event( $aug, $aug ) );
$check( 'virtual ids', 800007374 === array_values( array_filter( $missing, static function ( $x ) { return '7374' === $x['playbook_id']; } ) )[0]['id'] );

// Same-name teams.
$keyed = swat_child_team_assign_keys( array_merge( $g, array(
	array( 'event_name' => 'Pink Ribbon Classic', 'date' => '2026-10-10', 'time' => '09:00', 'division' => '9U Open', 'away' => 'Camel City', 'home' => 'Q', 'away_score' => '', 'home_score' => '', 'status' => 'scheduled' ),
) ) );
$cc = array();
foreach ( array( '9u', '10u', '12u' ) as $age ) {
	$cc[ $age ] = swat_child_team_profile_build( $keyed, 'camel-city-' . $age, '2026-10-08' );
}
$check( 'three Camel City teams', $cc['9u'] && $cc['10u'] && $cc['12u'] && 'Camel City (9U)' === $cc['9u']['name'] );
$check( 'plain camel-city has no single profile', null === swat_child_team_profile_build( $keyed, 'camel-city', '2026-10-08' ) );
$check( 'WP game without team id assigned by division', 1 === count( $cc['9u']['upcoming'] ) && ! $cc['10u']['upcoming'] );
$sum = 0;
foreach ( $cc as $x ) { $sum += $x['seasons']['2026-fall']['record']['gp']; }
$check( 'Camel City games split, none lost (18)', 18 === $sum );
$canes = swat_child_team_profile_build( $keyed, 'canes-triad-7u-black', '2026-10-08' );
$check( 'Canes (one id, 7U + 8U) keeps one profile 15-2-0', 15 === $canes['seasons']['2026-fall']['record']['w'] && 'Canes Triad 7U Black' === $canes['name'] );
$fp = array_values( array_filter( $canes['seasons']['2026-fall']['games'], static function ( $x ) { return 18 === $x['us']; } ) );
$check( 'opponent labelled by age group', 'Flying Pigs (7U)' === $fp[0]['opponent'] );

echo "$n season export checks, $fail failure(s)\n";
exit( $fail ? 1 : 0 );
