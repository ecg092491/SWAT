<?php
/**
 * Read synchronized bracket fixtures without changing canonical game records.
 */
defined( 'ABSPATH' ) || exit;

function swat_child_latest_fixture_cache( $cache ) {
	if ( ! is_array( $cache ) ) {
		return $cache;
	}
	$latest = array();
	foreach ( $cache as $key => $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}
		$slug = sanitize_title( (string) ( $entry['event_name'] ?? '' ) );
		if ( ! $slug ) {
			continue;
		}
		if ( ! isset( $latest[ $slug ] ) || strcmp( (string) ( $entry['updated_at'] ?? '' ), (string) ( $latest[ $slug ]['entry']['updated_at'] ?? '' ) ) > 0 ) {
			$latest[ $slug ] = array( 'key' => $key, 'entry' => $entry );
		}
	}
	$result = array();
	foreach ( $latest as $item ) {
		$result[ $item['key'] ] = $item['entry'];
	}
	return $result;
}
add_filter( 'option_swat_playbook_bracket_fixture_cache_v1', 'swat_child_latest_fixture_cache' );

function swat_child_bracket_event_dates( $rows ) {
	if ( ! is_array( $rows ) ) {
		return $rows;
	}
	$cache = get_option( 'swat_playbook_bracket_fixture_cache_v1', array() );
	$dates = array();
	foreach ( is_array( $cache ) ? $cache : array() as $entry ) {
		$slug = sanitize_title( (string) ( $entry['event_name'] ?? '' ) );
		foreach ( (array) ( $entry['games'] ?? array() ) as $fixture ) {
			$date = (string) ( $fixture['date'] ?? '' );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$dates[ $slug ][] = $date;
			}
		}
	}
	foreach ( $rows as &$row ) {
		$slug = sanitize_title( (string) ( $row['event_name'] ?? '' ) );
		if ( ! empty( $dates[ $slug ] ) ) {
			$start = isset( $row['start_date'] ) && '' !== $row['start_date'] ? array( $row['start_date'] ) : array();
			$end   = isset( $row['end_date'] ) && '' !== $row['end_date'] ? array( $row['end_date'] ) : array();
			$row['start_date'] = min( array_merge( $start, $dates[ $slug ] ) );
			$row['end_date']   = max( array_merge( $end, $dates[ $slug ] ) );
		}
	}
	unset( $row );
	return $rows;
}
add_filter( 'transient_swat_os_event_directory', 'swat_child_bracket_event_dates' );
add_filter( 'pre_set_transient_swat_os_event_directory', 'swat_child_bracket_event_dates' );

// Prime the directory before templates/REST callbacks so a cold cache also has
// bracket dates on its first visible request.
add_action( 'init', static function () {
	if ( function_exists( 'swat_os_event_directory' ) && false === get_transient( 'swat_os_event_directory' ) ) {
		$rows = swat_os_event_directory();
		set_transient( 'swat_os_event_directory', swat_child_bracket_event_dates( $rows ), 15 * MINUTE_IN_SECONDS );
	}
}, 99 );

add_filter( 'script_loader_src', static function ( $src, $handle ) {
	if ( 'swat-gameday' !== $handle ) {
		return $src;
	}
	// File mtime as the cache-buster: a fixed version string let browsers keep serving an old copy of this script.
	$file = SWAT_CHILD_DIR . '/assets/js/swat-gameday.js';
	return SWAT_CHILD_URI . '/assets/js/swat-gameday.js?ver=' . ( is_readable( $file ) ? (string) filemtime( $file ) : SWAT_CHILD_VERSION );
}, 20, 2 );

/**
 * Gold / Silver / Bronze names for split finals (1.0.19). Server-side twin of
 * bracketLabelMap() in assets/js/swat-gameday.js so the event page and Game Day
 * always agree.
 *
 * Playbook publishes a split final (seeds 1 v 2 and 3 v 4) as games in ONE
 * schedule without bracket names: game 1 arrives as "Championship", game 2 as
 * "Bracket Game". When every bracket game in a division schedule is a
 * standalone final (no game feeds it and it feeds nothing), they are separate
 * title games, ordered by Playbook game number: Gold, Silver, Bronze.
 * Per-game overrides come from the swat_child_bracket_labels filter.
 *
 * @param array $games Bracket fixtures (swat_os_api_bracket_fixtures()).
 * @param array $overrides Playbook id => array( 'bracket' => …, 'round' => … ).
 * @return array<string,array{tier?:string,bracket_name?:string,round?:string}> keyed 'pb:{id}' and 'id:{id}'.
 */
function swat_child_bracket_tier_labels( $games, $overrides = array() ) {
	$tiers  = array( 'Gold', 'Silver', 'Bronze' );
	$map    = array();
	$groups = array();
	foreach ( (array) $games as $game ) {
		if ( ! is_array( $game ) || ( function_exists( 'swat_os_game_is_pool' ) ? swat_os_game_is_pool( $game ) : 1 === absint( $game['game_type_id'] ?? 1 ) ) ) {
			continue;
		}
		$groups[ (string) ( $game['division'] ?? '' ) . '|' . (string) ( $game['schedule_id'] ?? '' ) ][] = $game;
	}
	foreach ( $groups as $group ) {
		if ( count( $group ) < 2 || count( $group ) > count( $tiers ) ) {
			continue;
		}
		$targets = array();
		foreach ( $group as $game ) {
			foreach ( array( 'winner_to', 'loser_to' ) as $link ) {
				if ( absint( $game[ $link ] ?? 0 ) ) {
					$targets[ (string) absint( $game[ $link ] ) ] = true;
				}
			}
		}
		$standalone = true;
		foreach ( $group as $game ) {
			if ( absint( $game['winner_to'] ?? 0 ) || absint( $game['loser_to'] ?? 0 ) || isset( $targets[ (string) absint( $game['id'] ?? 0 ) ] ) || isset( $targets[ (string) absint( $game['playbook_id'] ?? 0 ) ] ) ) {
				$standalone = false;
				break;
			}
		}
		if ( ! $standalone ) {
			continue;
		}
		usort(
			$group,
			static function ( $a, $b ) {
				$na = absint( $a['game_number'] ?? 0 ) ?: 99;
				$nb = absint( $b['game_number'] ?? 0 ) ?: 99;
				return ( $na <=> $nb ) ?: strcmp( (string) ( $a['time'] ?? '' ), (string) ( $b['time'] ?? '' ) ) ?: ( absint( $a['playbook_id'] ?? 0 ) <=> absint( $b['playbook_id'] ?? 0 ) );
			}
		);
		$base = trim( preg_replace( '/\s+Open$/i', '', (string) ( $group[0]['division'] ?? '' ) ) );
		foreach ( $group as $i => $game ) {
			$label = array( 'tier' => $tiers[ $i ], 'bracket_name' => trim( $base . ' ' . $tiers[ $i ] ), 'round' => 'Championship' );
			if ( ! empty( $game['playbook_id'] ) ) {
				$map[ 'pb:' . absint( $game['playbook_id'] ) ] = $label;
			}
			if ( ! empty( $game['id'] ) ) {
				$map[ 'id:' . absint( $game['id'] ) ] = $label;
			}
		}
	}
	foreach ( (array) $overrides as $playbook_id => $label ) {
		$playbook_id = absint( $playbook_id );
		if ( ! $playbook_id || ! is_array( $label ) ) {
			continue;
		}
		$item = $map[ 'pb:' . $playbook_id ] ?? array();
		if ( ! empty( $label['bracket'] ) ) {
			$item['bracket_name'] = (string) $label['bracket'];
			$item['tier']         = preg_match( '/\b(Gold|Silver|Bronze)\b/i', (string) $label['bracket'], $m ) ? ucfirst( strtolower( $m[1] ) ) : ( $item['tier'] ?? '' );
		}
		if ( ! empty( $label['round'] ) ) {
			$item['round'] = (string) $label['round'];
		}
		$map[ 'pb:' . $playbook_id ] = $item;
	}
	return $map;
}

/**
 * Apply tier labels to a list of games (adds bracket_name / tier / round).
 *
 * @param array $games  Games to label (pool games pass through).
 * @param array $labels From swat_child_bracket_tier_labels().
 * @return array
 */
function swat_child_apply_bracket_labels( $games, $labels ) {
	foreach ( $games as &$game ) {
		$key = ! empty( $game['playbook_id'] ) && isset( $labels[ 'pb:' . absint( $game['playbook_id'] ) ] ) ? 'pb:' . absint( $game['playbook_id'] ) : 'id:' . absint( $game['id'] ?? 0 );
		if ( isset( $labels[ $key ] ) && empty( $game['bracket_name'] ) ) {
			$game = array_merge( $game, $labels[ $key ] );
		}
	}
	unset( $game );
	return $games;
}
