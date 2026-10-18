<?php
/**
 * Shared, validated public Playbook schedule snapshots.
 *
 * Full all-division schedules supersede CSV and imported rows only within the
 * verified event/date window. A failed or partial fetch never erases results.
 * Stores game data only: no rosters, pitchers, coaches or session credentials.
 */
defined( 'ABSPATH' ) || exit;

function swat_child_source_key( $event ) {
	return sanitize_title( $event['name'] ?? '' ) . '|' . (string) ( $event['start'] ?? '' );
}

function swat_child_source_events() {
	$events = array();
	$catalog = SWAT_CHILD_DIR . '/data/playbook-event-sources.json';
	foreach ( is_readable( $catalog ) ? (array) json_decode( file_get_contents( $catalog ), true ) : array() as $event ) {
		if ( is_array( $event ) && ! empty( $event['name'] ) && ! empty( $event['start'] ) ) { $events[ swat_child_source_key( $event ) ] = $event; }
	}
	$events += (array) get_option( 'swat_child_source_events_v1', array() );
	foreach ( (array) get_option( 'swat_sync_event_registry_v3', array() ) as $event ) {
		if ( is_array( $event ) && ! empty( $event['name'] ) && ! empty( $event['start'] ) ) {
			$events[ swat_child_source_key( $event ) ] = $event;
		}
	}
	if ( function_exists( 'swat_os_playbook_event_feed' ) ) {
		foreach ( (array) swat_os_playbook_event_feed() as $row ) {
			if ( ! is_array( $row ) || empty( $row['EventName'] ) || empty( $row['StartDate'] ) ) { continue; }
			$event = array( 'name' => $row['EventName'], 'start' => $row['StartDate'], 'end' => $row['EndDate'] ?? $row['StartDate'], 'url' => $row['ScheduleUrl'] ?? $row['EventUrl'] ?? '', 'status' => $row['Status'] ?? '', 'display_schedule' => $row['DisplaySchedule'] ?? '' );
			$key = swat_child_source_key( $event );
			$events[ $key ] = array_merge( $events[ $key ] ?? array(), $event );
		}
	}
	return $events;
}

function swat_child_source_remember_events( $parsed ) {
	$known = (array) get_option( 'swat_child_source_events_v1', array() );
	$before = $known;
	foreach ( (array) ( $parsed['events'] ?? array() ) as $event ) {
		$url = (string) ( $event['url'] ?? '' );
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || 'play.swatourneys.com' !== wp_parse_url( $url, PHP_URL_HOST ) || ! preg_match( '#^/events/[^/]+/?$#', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) { continue; }
		$e = array( 'name' => $event['name'], 'start' => $event['date'], 'end' => $event['date'], 'url' => $url );
		$key = swat_child_source_key( $e );
		if ( ! isset( $known[ $key ] ) ) { $known[ $key ] = $e; }
	}
	if ( $known !== $before ) { update_option( 'swat_child_source_events_v1', $known, false ); }
}

/** Pure parser: reject the whole response if even one game has invalid identity. */
function swat_child_source_parse( $json, $event ) {
	if ( ! is_array( $json ) || ! isset( $json['schedules'] ) || ! is_array( $json['schedules'] ) ) { return null; }
	$out = array();
	foreach ( $json['schedules'] as $bucket ) {
		if ( ! is_array( $bucket ) || ! isset( $bucket['teams'] ) || ! is_array( $bucket['teams'] ) ) { return null; }
		foreach ( $bucket['teams'] as $g ) {
			if ( ! is_array( $g ) ) { return null; }
			$id = (string) ( $g['schedule_game_id'] ?? '' );
			$date = substr( (string) ( $g['schedule_time'] ?? '' ), 0, 10 );
			$time = substr( (string) ( $g['schedule_time'] ?? '' ), 11, 5 );
			if ( ! ctype_digit( $id ) || (int) $id < 1 || isset( $out[ $id ] ) || ! swat_child_valid_date( $date ) || $date < $event['start'] || $date > $event['end'] || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) || empty( $g['division'] ) || ! isset( $g['team_name_1'], $g['team_name_2'] ) ) { return null; }
			$label = strtoupper( trim( (string) ( $g['game_status_label'] ?? '' ) ) );
			$status = ! empty( $g['final'] ) ? 'final' : ( ! empty( $g['live_game_status'] ) ? 'in progress' : 'scheduled' );
			if ( preg_match( '/CANCEL|RAINED OUT/', $label ) ) { $status = 'cancelled'; }
			elseif ( false !== strpos( $label, 'POSTPON' ) ) { $status = 'postponed'; }
			elseif ( false !== strpos( $label, 'DELAY' ) ) { $status = 'delayed'; }
			$a = $g['team_score_1'] ?? null;
			$h = $g['team_score_2'] ?? null;
			if ( 'final' === $status && ( ! is_numeric( $a ) || ! is_numeric( $h ) ) ) { return null; }
			$scored = in_array( $status, array( 'final', 'in progress' ), true ) && is_numeric( $a ) && is_numeric( $h );
			$pool = 1 === (int) ( $g['game_type'] ?? 0 );
			$out[ $id ] = array(
				'id' => 800000000 + (int) $id, 'playbook_id' => $id, 'event_name' => $event['name'],
				'date' => $date, 'time' => $time, 'field' => sanitize_text_field( $g['location'] ?? '' ), 'division' => trim( sanitize_text_field( $g['division'] ) ),
				'away' => sanitize_text_field( $g['team_name_1'] ), 'home' => sanitize_text_field( $g['team_name_2'] ),
				'away_id' => '', 'home_id' => '', 'away_pb_team' => (string) ( $g['team_1_uuid'] ?? '' ), 'home_pb_team' => (string) ( $g['team_2_uuid'] ?? '' ),
				'away_score' => $scored ? (string) (int) $a : null, 'home_score' => $scored ? (string) (int) $h : null,
				'status' => $status, 'game_type_id' => $pool ? 1 : 2, 'game_type' => $pool ? 'Pool' : 'Bracket',
				'game_number' => (string) ( $g['game_number'] ?? '' ), 'source' => 'playbook-snapshot',
			);
		}
	}
	// Empty can mean unpublished/hidden. Never erase a last-good schedule on it.
	return $out ? array_values( $out ) : null;
}

function swat_child_source_refresh( $event ) {
	$key = swat_child_source_key( $event );
	$cache = 'swat_child_source_' . md5( $key );
	if ( get_transient( $cache . '_lock' ) ) { return array( 'ok' => false, 'reason' => 'busy' ); }
	$url = (string) ( $event['url'] ?? '' );
	if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || 'play.swatourneys.com' !== wp_parse_url( $url, PHP_URL_HOST ) || ! preg_match( '#^/events/[^/]+(?:/schedule(?:/all)?)?/?$#', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) { return array( 'ok' => false, 'reason' => 'invalid source URL' ); }
	if ( 'Hide Schedule' === ( $event['display_schedule'] ?? '' ) || in_array( strtolower( $event['status'] ?? '' ), array( 'cancelled', 'canceled' ), true ) ) { return array( 'ok' => false, 'reason' => 'schedule unavailable' ); }
	if ( ! function_exists( 'swat_pb_public_open_session' ) || ! function_exists( 'swat_pb_public_fetch_schedule' ) ) { return array( 'ok' => false, 'reason' => 'sync helpers unavailable' ); }
	set_transient( $cache . '_lock', 1, 90 );
	$result = array( 'ok' => false, 'reason' => 'Playbook request failed' );
	try {
		$url = preg_replace( '#(?:/schedule(?:/all)?)?/?$#', '', $url ) . '/schedule/all';
		$session = swat_pb_public_open_session( $url );
		if ( is_wp_error( $session ) ) { return $result; }
		if ( ! preg_match( '/window\.EVENT_ID\s*=\s*["\x27]?(\d+)/', $session['html'], $m ) ) { return $result; }
		$id = (int) $m[1];
		if ( ! empty( $event['id'] ) && (int) $event['id'] !== $id ) { return array( 'ok' => false, 'reason' => 'event identity mismatch' ); }
		// The canonical page verifies the actual two-day window for learned events.
		if ( preg_match( '#<title>[^<]*?(\d{2}/\d{2}/\d{4})\s*-\s*(\d{2}/\d{2}/\d{4})#i', $session['html'], $dm ) ) {
			$start = DateTimeImmutable::createFromFormat( '!m/d/Y', $dm[1] );
			$end = DateTimeImmutable::createFromFormat( '!m/d/Y', $dm[2] );
			if ( ! $start || ! $end || $start->format( 'Y-m-d' ) !== $event['start'] ) { return array( 'ok' => false, 'reason' => 'event date mismatch' ); }
			$event['end'] = $end->format( 'Y-m-d' );
		}
		$json = swat_pb_public_fetch_schedule( $session, $id );
		if ( is_wp_error( $json ) ) { return $result; }
		$games = swat_child_source_parse( $json, $event );
		if ( null === $games ) { return array( 'ok' => false, 'reason' => 'empty or invalid schedule; saved data retained' ); }
		$event['id'] = $id;
		$event['url'] = $url;
		$stored = array( 'event' => $event, 'games' => $games, 'checked_at' => time() );
		update_option( $cache, $stored, false );
		$index = (array) get_option( 'swat_child_source_index_v1', array() );
		$index[ $key ] = $cache;
		update_option( 'swat_child_source_index_v1', $index, false );
		set_transient( $cache . '_fresh', 1, $event['end'] >= current_time( 'Y-m-d' ) ? 120 : HOUR_IN_SECONDS );
		delete_transient( $cache . '_failed' );
		$result = array( 'ok' => true, 'games' => count( $games ), 'event' => $event['name'], 'checked_at' => $stored['checked_at'] );
		return $result;
	} finally {
		delete_transient( $cache . '_lock' );
		if ( ! $result['ok'] ) { set_transient( $cache . '_failed', 1, 5 * MINUTE_IN_SECONDS ); }
	}
}

function swat_child_source_snapshots() {
	$out = array();
	foreach ( (array) get_option( 'swat_child_source_index_v1', array() ) as $cache ) {
		$value = get_option( $cache, null );
		if ( is_array( $value ) && empty( $value['invalidated'] ) && ! empty( $value['games'] ) && ! empty( $value['event'] ) ) { $out[] = $value; }
	}
	return $out;
}

function swat_child_source_invalidate_upload( $games ) {
	$names = array_unique( array_map( static function ( $g ) { return sanitize_title( $g['event_name'] ?? '' ); }, $games ) );
	foreach ( (array) get_option( 'swat_child_source_index_v1', array() ) as $cache ) {
		$snapshot = get_option( $cache, null );
		if ( ! is_array( $snapshot ) || ! in_array( sanitize_title( $snapshot['event']['name'] ?? '' ), $names, true ) ) { continue; }
		$snapshot['invalidated'] = true;
		update_option( $cache, $snapshot, false );
		delete_transient( $cache . '_fresh' );
		delete_transient( $cache . '_failed' );
	}
}

/** Observe the existing sync's successful all-division responses without another fetch. */
function swat_child_source_capture( $response, $args, $url ) {
	if ( 'https://play.swatourneys.com/schedule_ajax' !== $url || is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return $response; }
	$body = $args['body'] ?? array();
	if ( is_string( $body ) ) { parse_str( $body, $body ); }
	if ( ! is_array( $body ) || 'schedules' !== ( $body['data_type'] ?? '' ) || ! isset( $body['event_price_id'], $body['event_registration_item_id'] ) || (int) $body['event_price_id'] || (int) $body['event_registration_item_id'] ) { return $response; }
	foreach ( swat_child_source_events() as $event ) {
		if ( empty( $event['id_verified'] ) || (int) ( $event['id'] ?? 0 ) !== (int) ( $body['event_id'] ?? 0 ) ) { continue; }
		$games = swat_child_source_parse( json_decode( wp_remote_retrieve_body( $response ), true ), $event );
		if ( null === $games ) { break; }
		$key = swat_child_source_key( $event );
		$cache = 'swat_child_source_' . md5( $key );
		update_option( $cache, array( 'event' => $event, 'games' => $games, 'checked_at' => time() ), false );
		$index = (array) get_option( 'swat_child_source_index_v1', array() );
		$index[ $key ] = $cache;
		update_option( 'swat_child_source_index_v1', $index, false );
		set_transient( $cache . '_fresh', 1, $event['end'] >= current_time( 'Y-m-d' ) ? 120 : HOUR_IN_SECONDS );
		delete_transient( $cache . '_failed' );
		break;
	}
	return $response;
}
add_filter( 'http_response', 'swat_child_source_capture', 20, 3 );

/** Replace only the selected event/window; preserve WP IDs and bracket metadata. */
function swat_child_source_reconcile( $rows, $snapshot ) {
	$event = $snapshot['event'];
	$slug = sanitize_title( $event['name'] );
	$keep = array();
	$meta = array();
	$source_ids = array_fill_keys( array_column( $snapshot['games'], 'playbook_id' ), true );
	foreach ( $rows as $row ) {
		$id = (string) ( $row['playbook_id'] ?? '' );
		if ( ! $id && ! empty( $row['id'] ) && (int) $row['id'] < 800000000 && function_exists( 'get_post_meta' ) ) { $id = (string) get_post_meta( $row['id'], '_swat_game_playbook_id', true ); }
		$matches = isset( $source_ids[ $id ] ) || ( sanitize_title( $row['event_name'] ?? $row['event'] ?? '' ) === $slug && ( $row['date'] ?? '' ) >= $event['start'] && ( $row['date'] ?? '' ) <= $event['end'] );
		if ( $matches ) {
			if ( $id ) { $meta[ $id ] = $row; }
		} else { $keep[] = $row; }
	}
	foreach ( $snapshot['games'] as $game ) {
		$old = $meta[ $game['playbook_id'] ] ?? array();
		$game = array_merge( $old, $game );
		unset( $game['virtual'] );
		foreach ( array( 'id', 'round', 'path_round', 'bracket_name', 'tier', 'winner_to', 'loser_to', 'away_seed', 'home_seed' ) as $field ) {
			if ( isset( $old[ $field ] ) ) { $game[ $field ] = $old[ $field ]; }
		}
		foreach ( array( 'away', 'home' ) as $side ) {
			if ( isset( $old[ $side ] ) && sanitize_title( $old[ $side ] ) === sanitize_title( $game[ $side ] ) ) { $game[ $side . '_id' ] = $old[ $side . '_id' ] ?? ''; }
		}
		$keep[] = $game;
	}
	return $keep;
}

function swat_child_source_apply( $rows, $names = null, $start = '' ) {
	if ( null === $names ) { $names = array_unique( array_map( static function ( $g ) { return sanitize_title( $g['event_name'] ?? $g['event'] ?? '' ); }, $rows ) ); }
	$names = array_map( 'sanitize_title', $names );
	foreach ( swat_child_source_snapshots() as $snapshot ) {
		$slug = sanitize_title( $snapshot['event']['name'] );
		if ( ! in_array( $slug, $names, true ) ) { continue; }
		$source_ids = array_fill_keys( array_column( $snapshot['games'], 'playbook_id' ), true );
		$scoped = array_filter( $rows, static function ( $row ) use ( $snapshot, $slug, $source_ids ) {
			return isset( $source_ids[ $row['playbook_id'] ?? '' ] ) || ( sanitize_title( $row['event_name'] ?? $row['event'] ?? '' ) === $slug && ( $row['date'] ?? '' ) >= $snapshot['event']['start'] && ( $row['date'] ?? '' ) <= $snapshot['event']['end'] );
		} );
		if ( $scoped || ( $start && $start === $snapshot['event']['start'] ) ) { $rows = swat_child_source_reconcile( $rows, $snapshot ); }
	}
	return $rows;
}

function swat_child_source_directory( $rows ) {
	foreach ( swat_child_source_snapshots() as $snapshot ) {
		$event = $snapshot['event'];
		$slug = sanitize_title( $event['name'] );
		foreach ( $rows as &$row ) {
			if ( sanitize_title( $row['event_name'] ?? $row['name'] ?? '' ) !== $slug || ( $row['start_date'] ?? '' ) !== $event['start'] ) { continue; }
			$row['game_count'] = count( $snapshot['games'] );
			$row['end_date'] = $event['end'];
			$row['divisions'] = array_values( array_unique( array_column( $snapshot['games'], 'division' ) ) );
			$row['fields'] = array_values( array_unique( array_column( $snapshot['games'], 'field' ) ) );
		}
		unset( $row );
	}
	return $rows;
}

/** Warm one event on a dedicated background tick; no network calls on profile views. */
function swat_child_source_tick() {
	$events = swat_child_source_events();
	$today = current_time( 'Y-m-d' );
	usort( $events, static function ( $a, $b ) use ( $today ) {
		$a_near = $a['end'] >= $today && $a['start'] <= gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS );
		$b_near = $b['end'] >= $today && $b['start'] <= gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS );
		return (int) $b_near - (int) $a_near;
	} );
	$near_done = 0;
	$past_done = 0;
	foreach ( $events as $event ) {
		if ( $event['start'] > gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ) || 'Hide Schedule' === ( $event['display_schedule'] ?? '' ) ) { continue; }
		$past = $event['end'] < $today;
		if ( ( $past && $past_done >= 1 ) || ( ! $past && $near_done >= 3 ) ) { continue; }
		$cache = 'swat_child_source_' . md5( swat_child_source_key( $event ) );
		if ( get_transient( $cache . '_fresh' ) || get_transient( $cache . '_failed' ) ) { continue; }
		swat_child_source_refresh( $event );
		if ( $past ) { ++$past_done; } else { ++$near_done; }
	}
}
add_action( 'swat_child_source_tick', 'swat_child_source_tick' );
add_action( 'init', static function () {
	// Preview never schedules a job that would later run with the live theme.
	if ( get_stylesheet() !== 'swat-tournament-os-child' ) { return; }
	if ( ! wp_next_scheduled( 'swat_child_source_tick' ) ) { wp_schedule_event( time() + 30, 'swat_child_two_minutes', 'swat_child_source_tick' ); }
} );
add_filter( 'cron_schedules', static function ( $schedules ) {
	$schedules['swat_child_two_minutes'] = array( 'interval' => 120, 'display' => 'Every 2 Minutes (Playbook source)' );
	return $schedules;
} );

/** Shared REST presentation; event-specific failure flags survive the facade. */
function swat_child_source_rest( $response, $handler, $request ) {
	if ( ! in_array( $request->get_route(), array( '/swat/v1/games', '/swat-gameday/v4/public', '/swat-gameday/v6/app' ), true ) ) { return $response; }
	if ( is_wp_error( $response ) || $response->is_error() ) {
		$status = is_wp_error( $response ) ? (int) ( $response->get_error_data()['status'] ?? 0 ) : $response->get_status();
		if ( '/swat/v1/games' !== $request->get_route() || 404 !== $status ) { return $response; }
		$known = function_exists( 'swat_child_event_by_slug' ) ? swat_child_event_by_slug( sanitize_title( (string) $request->get_param( 'event' ) ) ) : null;
		if ( ! $known ) { return $response; }
		$matched = false;
		foreach ( swat_child_source_snapshots() as $s ) {
			if ( sanitize_title( $s['event']['name'] ) === sanitize_title( $known['event_name'] ) && $s['event']['start'] === $known['start_date'] ) { $matched = true; break; }
		}
		if ( ! $matched ) { return $response; }
		$response = rest_ensure_response( array( 'event' => array( 'name' => $known['event_name'], 'start' => $known['start_date'], 'end' => $known['end_date'], 'slug' => $known['slug'] ), 'games' => array(), 'bracket_fixtures' => array() ) );
	}
	$data = $response->get_data();
	if ( '/swat-gameday/v6/app' === $request->get_route() ) {
		foreach ( $data['events'] as &$e ) {
			foreach ( swat_child_source_snapshots() as $s ) {
				if ( sanitize_title( $e['name'] ) !== sanitize_title( $s['event']['name'] ) || $e['start'] !== $s['event']['start'] ) { continue; }
				$rows = $e['games'];
				foreach ( $rows as &$r ) { $r['event_name'] = $e['name']; }
				unset( $r );
				$e['games'] = swat_child_source_reconcile( $rows, $s );
				$teams = array();
				foreach ( $e['games'] as $g ) {
					foreach ( array( 'away', 'home' ) as $side ) {
						if ( swat_child_is_placeholder_team( $g[ $side ] ) ) { continue; }
						$id = $g[ $side . '_id' ] ?: ( $g[ $side . '_pb_team' ] ?: 'name:' . md5( $g[ $side ] . '|' . $g['division'] ) );
						$teams[ $id ] = array( 'id' => $id, 'name' => $g[ $side ], 'division' => $g['division'] );
					}
				}
				$e['teams'] = array_values( $teams );
				$e['game_count'] = count( $e['games'] );
				$e['team_count'] = count( $e['teams'] );
				$e['last_success_ts'] = $s['checked_at'];
				$e['data_source'] = 'playbook-snapshot';
				unset( $e['schedule_pending'] );
			}
		}
		unset( $e );
		$response->set_data( $data );
		return $response;
	}
	$event = $data['event'] ?? $data['loaded_event'] ?? null;
	if ( ! is_array( $event ) || empty( $event['name'] ) ) { return $response; }
	$found = false;
	foreach ( swat_child_source_snapshots() as $snapshot ) {
		if ( sanitize_title( $snapshot['event']['name'] ) === sanitize_title( $event['name'] ) && ( empty( $event['start'] ) || $event['start'] === $snapshot['event']['start'] ) ) { $found = true; break; }
	}
	if ( ! $found ) { return $response; }
	$rows = array_merge( (array) ( $data['games'] ?? array() ), (array) ( $data['bracket_fixtures'] ?? array() ) );
	foreach ( $rows as &$row ) { $row['event_name'] = $event['name']; }
	unset( $row );
	$rows = swat_child_source_reconcile( $rows, $snapshot );
	$data['games'] = array();
	$data['bracket_fixtures'] = array();
	$team = strtolower( trim( (string) ( $request->get_param( 'team' ) ?: $request->get_param( 'team_id' ) ) ) );
	$field = trim( (string) $request->get_param( 'field' ) );
	foreach ( $rows as $row ) {
		if ( $field && 0 !== strcasecmp( $field, $row['field'] ?? '' ) ) { continue; }
		if ( $team && false === strpos( strtolower( $row['away'] ?? '' ), $team ) && false === strpos( strtolower( $row['home'] ?? '' ), $team ) && $team !== (string) ( $row['away_id'] ?? '' ) && $team !== (string) ( $row['home_id'] ?? '' ) ) { continue; }
		$placeholder = function_exists( 'swat_child_is_placeholder_team' ) && ( swat_child_is_placeholder_team( $row['away'] ?? '' ) || swat_child_is_placeholder_team( $row['home'] ?? '' ) );
		if ( $placeholder ) { $row['virtual'] = true; $data['bracket_fixtures'][] = $row; }
		else { $data['games'][] = $row; }
	}
	if ( array_key_exists( 'game_count', $data ) ) { $data['game_count'] = count( $data['games'] ); }
	if ( array_key_exists( 'bracket_games', $data ) ) {
		$data['bracket_games'] = array_values( array_filter( array_merge( $data['games'], $data['bracket_fixtures'] ), static function ( $g ) { return 2 === (int) ( $g['game_type_id'] ?? 0 ); } ) );
		$data['bracket_available'] = ! empty( $data['bracket_games'] );
	}
	if ( isset( $data['standings'] ) && function_exists( 'swat_os_standings_data' ) ) {
		$by_division = swat_os_standings_data( $data['games'] );
		$data['standings_by_division'] = $by_division;
		$division = (string) ( $data['standings']['division'] ?? '' );
		if ( $division && isset( $by_division[ $division ] ) ) { $data['standings']['rows'] = $by_division[ $division ]['rows']; }
		elseif ( $division ) { $data['standings']['rows'] = array(); }
	}
	foreach ( swat_child_source_snapshots() as $snapshot ) {
		if ( sanitize_title( $snapshot['event']['name'] ) !== sanitize_title( $event['name'] ) || ( ! empty( $event['start'] ) && $event['start'] !== $snapshot['event']['start'] ) ) { continue; }
		$data['last_success_ts'] = $snapshot['checked_at'];
		$data['last_sync'] = gmdate( 'Y-m-d H:i:s', $snapshot['checked_at'] );
		$cache = 'swat_child_source_' . md5( swat_child_source_key( $snapshot['event'] ) );
		$data['sync_status'] = get_transient( $cache . '_failed' ) ? 'error' : 'success';
		$data['data_source'] = 'playbook-snapshot';
		$data['source_stale'] = ! (bool) get_transient( $cache . '_fresh' );
	}
	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'swat_child_source_rest', 100, 3 );

// Admin-only diagnostics and one-event refresh. Uses known URLs, never user URLs.
add_action( 'rest_api_init', static function () {
	register_rest_route( 'swat-child/v1', '/source/checks', array(
		'methods' => 'GET', 'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'callback' => static function () {
			require_once SWAT_CHILD_DIR . '/tests/source-checks.php';
			return swat_child_source_checks();
		},
	) );
	register_rest_route( 'swat-child/v1', '/source', array(
		'methods' => 'GET', 'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'callback' => static function () {
			$out = array();
			foreach ( swat_child_source_snapshots() as $s ) { $out[] = array( 'event' => $s['event'], 'games' => count( $s['games'] ), 'checked_at' => $s['checked_at'] ); }
			return array( 'version' => SWAT_CHILD_VERSION, 'events' => swat_child_source_events(), 'snapshots' => $out );
		},
	) );
	register_rest_route( 'swat-child/v1', '/source/refresh', array(
		'methods' => 'POST', 'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'callback' => static function ( $request ) {
			$events = swat_child_source_events();
			$key = (string) $request->get_param( 'key' );
			if ( ! isset( $events[ $key ] ) ) { return new WP_Error( 'unknown_event', 'Unknown event.', array( 'status' => 404 ) ); }
			return swat_child_source_refresh( $events[ $key ] );
		},
	) );
	register_rest_route( 'swat-child/v1', '/source/discover', array(
		'methods' => 'POST', 'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'callback' => static function ( $request ) {
			$slug = sanitize_title( (string) $request->get_param( 'team' ) );
			$ids = swat_child_pb_team_ids();
			$season = (string) $request->get_param( 'season' );
			if ( ! isset( $ids[ $slug ] ) || ! preg_match( '/^\d{4}-(fall|spring-summer)$/', $season ) ) { return new WP_Error( 'unknown_team', 'Choose a configured team and season.', array( 'status' => 400 ) ); }
			$page = swat_child_pb_team_page( $ids[ $slug ], $season );
			if ( ! $page ) { return array( 'ok' => false ); }
			swat_child_source_remember_events( $page );
			return array( 'ok' => true, 'events' => count( $page['events'] ) );
		},
	) );
} );
