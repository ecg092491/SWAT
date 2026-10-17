<?php
/**
 * Gold / Silver split-final labels (inc/bracket-schedule.php), using the live
 * Playbook fixtures of 2026-10-08. Run: php tests/bracket-tier-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
require __DIR__ . '/../inc/bracket-schedule.php';

$fail = 0;
$check = static function ( $name, $ok ) use ( &$fail ) { if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };
$fx = static function ( $pid, $div, $num, $sched, $wt = 0 ) { return array( 'id' => 900000000 + $pid, 'playbook_id' => (string) $pid, 'division' => $div, 'game_number' => (string) $num, 'schedule_id' => (string) $sched, 'game_type_id' => 2, 'winner_to' => $wt, 'loser_to' => 0, 'time' => '21:00' ); };

// FNL 10/9: 8U split final (game 2 arrives first to prove ordering by game number) + single 12U final.
$l = swat_child_bracket_tier_labels( array( $fx( 7875, '8U Open', 2, 1485 ), $fx( 7874, '8U Open', 1, 1485 ), $fx( 7873, '12U Open', 1, 1486 ) ) );
$check( 'FNL 8U game 1 = 8U Gold Championship', ( $l['pb:7874']['bracket_name'] ?? '' ) === '8U Gold' && 'Championship' === $l['pb:7874']['round'] );
$check( 'FNL 8U game 2 = 8U Silver Championship', ( $l['pb:7875']['tier'] ?? '' ) === 'Silver' && 'Championship' === $l['pb:7875']['round'] );
$check( 'FNL 12U single final not relabelled', ! isset( $l['pb:7873'] ) );

// SNL 10/10: 11U split final.
$l = swat_child_bracket_tier_labels( array( $fx( 7893, '11U Open', 1, 1496 ), $fx( 7894, '11U Open', 2, 1496 ) ) );
$check( 'SNL 11U Gold / Silver', 'Gold' === ( $l['pb:7893']['tier'] ?? '' ) && 'Silver' === ( $l['pb:7894']['tier'] ?? '' ) );

// Pink Ribbon 10U: linked semis feed a final -> untouched.
$l = swat_child_bracket_tier_labels( array( $fx( 7884, '10U Open', 1, 1490, 900007885 ), $fx( 7883, '10U Open', 2, 1490, 900007885 ), $fx( 7885, '10U Open', 3, 1490 ) ) );
$check( 'Linked bracket untouched', array() === $l );

// Pool games ignored; override filter wins.
$pool = $fx( 1, '8U Open', 1, 1 ); $pool['game_type_id'] = 1;
$l = swat_child_bracket_tier_labels( array( $pool, $fx( 7874, '8U Open', 1, 1485 ), $fx( 7875, '8U Open', 2, 1485 ) ), array( '7875' => array( 'bracket' => '8U Consolation', 'round' => 'Final' ) ) );
$check( 'Pool games ignored', ! isset( $l['id:1'] ) );
$check( 'Override applied', '8U Consolation' === ( $l['pb:7875']['bracket_name'] ?? '' ) && 'Final' === $l['pb:7875']['round'] );

// Labels reach games (resolved teams keep the label).
$games = swat_child_apply_bracket_labels( array( array( 'id' => 900007875, 'playbook_id' => '7875', 'away' => 'Carolina Clippers 8U' ) ), swat_child_bracket_tier_labels( array( $fx( 7874, '8U Open', 1, 1485 ), $fx( 7875, '8U Open', 2, 1485 ) ) ) );
$check( 'Applied after seeds resolve', 'Silver' === ( $games[0]['tier'] ?? '' ) );

echo $fail ? "$fail failure(s)\n" : "8 bracket tier checks, 0 failure(s)\n";
exit( $fail ? 1 : 0 );
