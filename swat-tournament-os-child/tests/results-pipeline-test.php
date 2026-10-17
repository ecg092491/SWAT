<?php
/** Production profile pipeline plus event reconciliation and season migration. */
define( 'ABSPATH', __DIR__ );
define( 'SWAT_CHILD_DIR', dirname( __DIR__ ) );
define( 'DAY_IN_SECONDS', 86400 );
function add_filter() {} function add_action() {} function __( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( preg_replace( "/['’]/u", '', (string) $s ) ) ), '-' ); }
function get_option( $k, $default = array() ) { return $GLOBALS['options'][ $k ] ?? $default; }
function get_transient( $k ) { return $GLOBALS['cache'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['cache'][ $k ] = $v; }
function current_time() { return '2026-10-09'; }
function swat_os_all_synchronized_games() { return $GLOBALS['wp_games']; }
require SWAT_CHILD_DIR . '/inc/team-profile.php';
require SWAT_CHILD_DIR . '/inc/team-playbook.php';
require SWAT_CHILD_DIR . '/inc/season-export.php';
$fail = 0; $n = 0;
$check = static function ( $name, $ok ) use ( &$fail, &$n ) { ++$n; if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };
$csv = swat_child_export_parse_csv( file_get_contents( SWAT_CHILD_DIR . '/data/playbook-season-2026-fall.csv' ) )['games'];
$moved = $csv[0]; $moved['date'] = '2026-06-30';
$GLOBALS['options']['swat_child_export_seasons'] = array( '2026-spring' => array( 'games' => array( $moved ) ) );
$all = swat_child_export_games();
$rows = array_values( array_filter( $all, static function ( $g ) use ( $moved ) { return $g['playbook_id'] === $moved['playbook_id']; } ) );
$check( 'read path deduplicates moved ID across shipped/upload seasons', 1 === count( $rows ) && '2026-06-30' === $rows[0]['date'] );
$stored = array( '2026-fall' => array( 'games' => array( $csv[0], $csv[1] ) ) );
$updated = swat_child_export_update_seasons( $stored, array( $moved ), 'now', 'test.csv' );
$check( 'upload moves ID out of old season', 1 === count( $updated['2026-fall']['games'] ) && 1 === count( $updated['2026-spring']['games'] ) );
$check( 'upload preserves omitted game', $csv[1] === $updated['2026-fall']['games'][0] );
$check( 'repeated migration is idempotent', $updated === swat_child_export_update_seasons( $updated, array( $moved ), 'now', 'test.csv' ) );
$canes = array_values( array_filter( $csv, static function ( $g ) { return 'Canes Triad 7U Black' === $g['away'] || 'Canes Triad 7U Black' === $g['home']; } ) );
$one = $canes[0]; $old = $one; $old['away_score'] = '99'; $old['source'] = 'wordpress'; $old['id'] = 123; $old['round'] = 'Pool';
$GLOBALS['wp_games'] = array( $old );
$uuid = '1f1476ea-fab9-47d9-9f4b-80a62d3ff9ab';
$GLOBALS['options']['swat_child_pb_team_ids'] = array( 'canes-triad-7u-black' => $uuid );
$GLOBALS['cache'][ 'swat_child_pb_team_' . md5( $uuid . '|2026-fall' ) ] = array(
 'games' => array( array( 'event' => $one['event_name'], 'team_1' => $one['away'], 'team_2' => $one['home'], 'score_1' => 99, 'score_2' => 15 ) ),
 'events' => array( $one['event_name'] => array( 'date' => $one['date'], 'division' => '7U' ) ),
);
$profile = swat_child_team_profile( 'canes-triad-7u-black' );
$r = $profile['seasons']['2026-fall']['record'];
$check( 'full production profile ignores stale HTML score', 17 === $r['gp'] && 15 === $r['w'] && 2 === $r['l'] && 214 === $r['rs'] && 77 === $r['ra'] );
$result = swat_child_export_reconcile_event( array( $old ), array( $one ) );
$check( 'event corrected score replaces old score', 1 === count( $result ) && '0' === $result[0]['away_score'] );
$check( 'event keeps post and round metadata', 123 === $result[0]['id'] && 'Pool' === $result[0]['round'] );
$legacy = $old; unset( $legacy['playbook_id'] ); $legacy['status'] = 'in_progress';
$result = swat_child_export_reconcile_event( array( $legacy ), array( $one ) );
$check( 'unique ID-less stale live row replaced by final', 1 === count( $result ) && 'final' === $result[0]['status'] );
$upcoming = $old; $upcoming['playbook_id'] = '999999'; $upcoming['status'] = 'scheduled'; $upcoming['date'] = '2026-10-10';
$check( 'unrelated upcoming retained', 2 === count( swat_child_export_reconcile_event( array( $upcoming ), array( $one ) ) ) );
$check( 'events without exports remain intact', array( $old, $upcoming ) === swat_child_export_reconcile_event( array( $old, $upcoming ), array() ) );
$dupe = $legacy; $dupe['status'] = 'final';
$check( 'post plus bracket duplicate produces one final', 1 === count( swat_child_export_reconcile_event( array( $dupe, $old ), array( $one ) ) ) );

// ---- 1.0.27 regressions: parent-shaped event rows (no playbook_id, no event_name). ----
$aug = array_values( array_filter( $csv, static function ( $g ) { return 'August Heat Championship' === $g['event_name']; } ) );
$parent = static function ( $g, $post_id ) {
	unset( $g['playbook_id'], $g['event_name'], $g['source'], $g['away_pb_team'], $g['home_pb_team'] );
	$g['id'] = $post_id;
	return $g;
};
$same_game = static function ( $rows, $x ) {
	return count( array_filter( $rows, static function ( $g ) use ( $x ) {
		return $g['date'] === $x['date'] && in_array( array( $g['away'], $g['home'] ), array( array( $x['away'], $x['home'] ), array( $x['home'], $x['away'] ) ), true );
	} ) );
};
$x = $aug[0];
$row = $parent( $x, 555 );
$row['time'] = '07:45';
$out = swat_child_export_reconcile_event( array( $row ), $aug );
$check( 'time edited after sync, no event_name: one copy', 1 === $same_game( $out, $x ) );
$kept = array_values( array_filter( $out, static function ( $g ) use ( $x ) { return $g['playbook_id'] === $x['playbook_id']; } ) );
$check( 'time edited: post id kept, CSV time used', 555 === $kept[0]['id'] && $x['time'] === $kept[0]['time'] );
$unpadded = array();
foreach ( $aug as $i => $g ) {
	$r = $parent( $g, 1000 + $i );
	$r['time'] = ltrim( $r['time'], '0' );
	$unpadded[] = $r;
}
$out = swat_child_export_reconcile_event( $unpadded, $aug );
$check( 'all 64 rows with "9:00"-style times: 64 games, all post ids kept', 64 === count( $out ) && 0 === count( array_filter( $out, static function ( $g ) { return $g['id'] >= 800000000; } ) ) );
$ampm = $parent( $x, 556 );
$ampm['time'] = date( 'g:i A', strtotime( $x['time'] ) );
$check( '"9:00 AM"-style time matches', 1 === count( swat_child_export_reconcile_event( array( $ampm ), array( $x ) ) ) );
$stale = $parent( $x, 557 );
$stale['time'] = '07:45';
$stale['status'] = 'in_progress';
$stale['away_score'] = '1';
$stale['home_score'] = '0';
$out = swat_child_export_reconcile_event( array( $stale ), $aug );
$check( 'live row with only date/team evidence is preserved', 2 === $same_game( $out, $x ) && 1 === count( array_filter( $out, static function ( $g ) { return 'in_progress' === $g['status']; } ) ) );
$fixture = $x;
$fixture['round'] = 'Championship';
$fixture['id'] = 777;
unset( $fixture['event_name'] );
$out = swat_child_export_reconcile_event( array( $fixture ), $aug );
$check( 'bracket fixture with id, no event_name: one copy, round kept', 1 === $same_game( $out, $x ) && 'Championship' === array_values( array_filter( $out, static function ( $g ) { return 777 === ( $g['id'] ?? 0 ); } ) )[0]['round'] );
$upcoming = $parent( $x, 558 );
$upcoming['date'] = '2026-10-17';
$upcoming['status'] = 'scheduled';
$upcoming['away_score'] = $upcoming['home_score'] = '';
$check( 'unrelated upcoming game of the event kept', 65 === count( swat_child_export_reconcile_event( array( $upcoming ), $aug ) ) );
$standings_in = array_merge( $unpadded, array( $parent( $x, 559 ) ) );
$check( 'standings input: duplicate post row of one game still 64', 64 === count( swat_child_export_reconcile_event( $standings_in, $aug ) ) );

// ---- 1.0.27: stored-season ordering and metadata. ----
$fall = $csv[0];
$spring = $csv[0];
$spring['date'] = '2026-06-20';
$legacy = array(
	'2026-spring' => array( 'games' => array( $spring ), 'uploaded' => '2026-10-01 10:00:00', 'file' => 'fix.csv' ),
	'2026-fall'   => array( 'games' => array( $fall, $csv[1] ), 'uploaded' => '2026-09-01 10:00:00', 'file' => 'old.csv' ),
);
$u = swat_child_export_update_seasons( $legacy, array( $csv[2] ), '2026-10-09 09:00:00', 'new.csv' );
$hits = array();
foreach ( $u as $bucket ) {
	foreach ( $bucket['games'] as $g ) {
		if ( $g['playbook_id'] === $fall['playbook_id'] ) { $hits[] = $g['date']; }
	}
}
$check( 'legacy cross-season duplicate: newest upload wins', array( '2026-06-20' ) === $hits );
$check( 'season not in upload keeps its own file and time', 'fix.csv' === $u['2026-spring']['file'] && '2026-10-01 10:00:00' === $u['2026-spring']['uploaded'] );
$check( 'season in upload gets the new file and time', 'new.csv' === $u['2026-fall']['file'] && '2026-10-09 09:00:00' === $u['2026-fall']['uploaded'] );
$check( 'norm time', '09:00' === swat_child_export_norm_time( '9:00' ) && '18:30' === swat_child_export_norm_time( '6:30 PM' ) && '00:15' === swat_child_export_norm_time( '12:15 AM' ) && '12:05' === swat_child_export_norm_time( '12:05 PM' ) && '18:00' === swat_child_export_norm_time( '18:00:00' ) );

// 1.0.28: distinct rematches survive weak matches; metadata is bidirectionally unique.
$final = $canes[0];
foreach ( array( 'scheduled', 'in_progress' ) as $status ) {
	$rematch = $parent( $final, 880 );
	$rematch['time'] = '15:00'; $rematch['status'] = $status;
	$rematch['away_score'] = $rematch['home_score'] = '';
	$out = swat_child_export_reconcile_event( array( $rematch ), array( $final ) );
	$check( "distinct $status rematch kept", 2 === count( $out ) && 1 === count( array_filter( $out, static function ( $g ) use ( $status ) { return $status === $g['status']; } ) ) );
}
$second = $final; $second['playbook_id'] = '999999'; $second['time'] = '15:00';
$ambiguous = $parent( $second, 881 ); $ambiguous['time'] = '17:00'; $ambiguous['round'] = 'Championship';
foreach ( array( array( $final, $second ), array( $second, $final ) ) as $exports ) {
	$out = swat_child_export_reconcile_event( array( $ambiguous ), $exports );
	$check( 'ambiguous equal-score doubleheader never transfers round', 2 === count( $out ) && 0 === count( array_filter( $out, static function ( $g ) { return 'Championship' === ( $g['round'] ?? '' ); } ) ) );
}
$exact = $parent( $second, 882 ); $exact['round'] = 'Championship';
$out = swat_child_export_reconcile_event( array( $exact ), array( $final, $second ) );
$match = array_values( array_filter( $out, static function ( $g ) { return '999999' === $g['playbook_id']; } ) );
$check( 'unique time still transfers championship to correct game', 882 === $match[0]['id'] && 'Championship' === $match[0]['round'] );
$second['away_score'] = '2';
$out = swat_child_export_reconcile_event( array( $ambiguous ), array( $second, $final ) );
$match = array_values( array_filter( $out, static function ( $g ) use ( $final ) { return $final['playbook_id'] === $g['playbook_id']; } ) );
$check( 'unique score recovers edited-time metadata', 881 === $match[0]['id'] && 'Championship' === $match[0]['round'] );
$weak = $parent( $final, 883 ); $weak['time'] = '17:00'; $weak['away_score'] = '7'; $weak['home_score'] = '8'; $weak['round'] = 'Semifinal';
$out = swat_child_export_reconcile_event( array( $weak ), array( $final ) );
$check( 'unique finished pairing still recovers metadata', 1 === count( $out ) && 883 === $out[0]['id'] && 'Semifinal' === $out[0]['round'] );

echo "$n results pipeline checks, $fail failure(s)\n";
exit( $fail ? 1 : 0 );
