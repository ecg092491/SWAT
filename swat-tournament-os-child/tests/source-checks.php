<?php
/** Pure regression checks, runnable inside WordPress or tests/source-test.php. */
function swat_child_source_checks() {
	$checks = array();
	$check = static function ( $name, $ok ) use ( &$checks ) { $checks[ $name ] = (bool) $ok; };
	$event = array( 'name' => 'Regression Cup', 'start' => '2026-10-17', 'end' => '2026-10-18' );
	$raw = array( 'schedule_game_id' => 901, 'schedule_time' => '2026-10-17 09:00:00', 'division' => '9U Open ', 'location' => 'SW 1', 'team_name_1' => 'Alpha', 'team_name_2' => 'Beta', 'team_score_1' => '00', 'team_score_2' => '00', 'game_type' => 1, 'final' => false, 'game_number' => 1 );
	$wrap = static function ( $games ) { return array( 'schedules' => array( array( 'teams' => $games ) ) ); };
	$parsed = swat_child_source_parse( $wrap( array( $raw ) ), $event );
	$check( 'unplayed 00-00 is not a final or a tie', 1 === count( $parsed ) && 'scheduled' === $parsed[0]['status'] && null === $parsed[0]['away_score'] );
	$final = array_merge( $raw, array( 'final' => true, 'team_score_1' => '4', 'team_score_2' => '0' ) );
	$rows = swat_child_source_parse( $wrap( array( $final ) ), $event );
	$check( 'zero-run final is retained', 'final' === $rows[0]['status'] && '0' === $rows[0]['home_score'] );
	$check( 'home-away order follows Playbook', 'Alpha' === $rows[0]['away'] && 'Beta' === $rows[0]['home'] && '4' === $rows[0]['away_score'] );
	$check( 'empty schedules do not replace saved games', null === swat_child_source_parse( array( 'schedules' => array() ), $event ) );
	$check( 'missing schedule collection rejected', null === swat_child_source_parse( array( 'error' => 'no' ), $event ) );
	$check( 'duplicate game IDs reject whole response', null === swat_child_source_parse( $wrap( array( $final, $final ) ), $event ) );
	$check( 'malformed row does not erase other finals', null === swat_child_source_parse( $wrap( array( $final, array() ) ), $event ) );
	$check( 'different event dates rejected', null === swat_child_source_parse( $wrap( array( array_merge( $final, array( 'schedule_time' => '2026-10-19 09:00:00' ) ) ) ), $event ) );
	$check( 'final without both scores rejected', null === swat_child_source_parse( $wrap( array( array_merge( $final, array( 'team_score_2' => null ) ) ) ), $event ) );
	$cancelled = swat_child_source_parse( $wrap( array( array_merge( $final, array( 'game_status_label' => 'Rained Out' ) ) ) ), $event );
	$check( 'rainout beats stale final flag', 'cancelled' === $cancelled[0]['status'] && null === $cancelled[0]['home_score'] );
	$old = array_merge( $rows[0], array( 'id' => 123, 'away_score' => '9', 'round' => 'Championship', 'away_id' => '45' ) );
	$deleted = array_merge( $old, array( 'playbook_id' => '902', 'id' => 124 ) );
	$another = array_merge( $old, array( 'event_name' => 'Other Cup', 'id' => 125, 'playbook_id' => '1901' ) );
	$prior_season = array_merge( $old, array( 'date' => '2025-10-17', 'id' => 126, 'playbook_id' => '2901' ) );
	$snapshot = array( 'event' => $event, 'games' => $rows );
	$merged = swat_child_source_reconcile( array( $old, $deleted, $another, $prior_season ), $snapshot );
	$match = array_values( array_filter( $merged, static function ( $g ) { return 123 === $g['id']; } ) );
	$check( 'score correction replaces old final once', 3 === count( $merged ) && 1 === count( $match ) && '4' === $match[0]['away_score'] );
	$check( 'existing WP ID and bracket metadata retained', 'Championship' === $match[0]['round'] && '45' === $match[0]['away_id'] );
	$check( 'game removed from full source disappears', ! in_array( 124, array_column( $merged, 'id' ), true ) );
	$check( 'concurrent event remains separate', in_array( 125, array_column( $merged, 'id' ), true ) );
	$check( 'same event name in previous season retained', in_array( 126, array_column( $merged, 'id' ), true ) );
	$changed = $snapshot;
	$changed['games'][0]['away'] = 'Replacement';
	$new = swat_child_source_reconcile( array( $old ), $changed );
	$check( 'changed team does not inherit old team ID', '' === $new[0]['away_id'] );
	$moved = swat_child_source_reconcile( array( array_merge( $old, array( 'date' => '2026-10-16' ) ) ), $snapshot );
	$check( 'moved game follows stable ID without duplication', 1 === count( $moved ) && '2026-10-17' === $moved[0]['date'] );
	$again = swat_child_source_reconcile( $merged, $snapshot );
	$check( 'reconciliation is idempotent', $again === $merged );
	$check( 'no roster or credentials in normalized result', ! array_intersect( array( 'pitchers_1', 'pitchers_2', 'cookie', 'csrf', 'roster' ), array_keys( $rows[0] ) ) );
	$check( 'snapshot supersedes stale CSV score', '4' === swat_child_source_reconcile( array( array_merge( $old, array( 'source' => 'export' ) ) ), $snapshot )[0]['away_score'] );
	$cases = json_decode( file_get_contents( SWAT_CHILD_DIR . '/tests/resolver-cases.json' ), true );
	foreach ( $cases as $i => $case ) {
		$check( 'registration precedence ' . $i, array( $case['status'], $case['source'] ) === swat_child_resolve_status( $case['input'] ) );
	}
	$csv = array();
	foreach ( swat_child_export_games() as $g ) { $csv[ $g['playbook_id'] ] = $g; }
	$compared = 0;
	$differences = array();
	foreach ( swat_child_source_snapshots() as $snapshot ) {
		foreach ( $snapshot['games'] as $g ) {
			$id = $g['playbook_id'];
			if ( ! isset( $csv[ $id ] ) ) { continue; }
			++$compared;
			foreach ( array( 'date', 'time', 'field', 'division', 'away', 'home', 'away_score', 'home_score', 'game_type_id' ) as $field ) {
				if ( (string) $g[ $field ] !== (string) $csv[ $id ][ $field ] ) { $differences[] = array( 'id' => $id, 'field' => $field ); }
			}
		}
	}
	return array( 'checks' => count( $checks ), 'failures' => array_keys( array_filter( $checks, static function ( $ok ) { return ! $ok; } ) ), 'csv_games_checked_against_playbook' => $compared, 'csv_field_differences' => $differences );
}
