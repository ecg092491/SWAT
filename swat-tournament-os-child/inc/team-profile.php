<?php
/**
 * Team profiles (child 1.0.19): /teams/ and /teams/{team-slug}/.
 *
 * Built only from synchronized swat_game records (swat_os_all_synchronized_games)
 * plus resolved bracket fixtures for current events. No rosters, birth dates or
 * coach names: that data is not synced to WordPress and is about minors.
 *
 * A team is identified by its exact name (sanitize_title), the same key Game
 * Day's Follow Teams uses ("team:{slug}"), so one club playing up a division
 * (e.g. a 7U team in an 8U event) keeps one profile, as on Playbook.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Season key + label for a game date. Fall = Jul–Dec, Spring = Jan–Jun.
 *
 * @param string $date Y-m-d.
 * @return array{key:string,label:string}
 */
function swat_child_season_for( $date ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-\d{2}$/', (string) $date, $m ) ) {
		return array( 'key' => '', 'label' => '' );
	}
	$fall = (int) $m[2] >= 7;
	return array(
		'key'   => $m[1] . '-' . ( $fall ? 'fall' : 'spring' ),
		'label' => $m[1] . ' ' . ( $fall ? 'Fall' : 'Spring' ),
	);
}

/**
 * Pure aggregation (no WordPress calls beyond sanitize_title), so it is unit tested.
 *
 * @param array  $games All games: event_name, date, time, field, division, away, home, away_score, home_score, status, game_type(_id).
 * @param string $slug  Team slug (sanitize_title of the team name).
 * @param string $today Y-m-d.
 * @return array|null Null when the team has no games.
 */
function swat_child_team_profile_build( $games, $slug, $today ) {
	$name    = '';
	$mine    = array();
	foreach ( (array) $games as $game ) {
		foreach ( array( 'away', 'home' ) as $side ) {
			$label = trim( html_entity_decode( (string) ( $game[ $side ] ?? '' ), ENT_QUOTES, 'UTF-8' ) );
			$key   = (string) ( $game[ $side . '_key' ] ?? sanitize_title( $label ) );
			if ( '' !== $label && $key === $slug ) {
				$name   = $name ? $name : (string) ( $game[ $side . '_label' ] ?? $label );
				$mine[] = array( 'game' => $game, 'side' => $side );
				break;
			}
		}
	}
	if ( ! $mine ) {
		return null;
	}

	$seasons  = array();
	$upcoming = array();
	foreach ( $mine as $row ) {
		$g     = $row['game'];
		$side  = $row['side'];
		$other = 'home' === $side ? 'away' : 'home';
		$date  = (string) ( $g['date'] ?? '' );
		$status = strtoupper( trim( str_replace( array( '_', '-' ), ' ', (string) ( $g['status'] ?? '' ) ) ) );
		$final  = in_array( $status, array( 'FINAL', 'COMPLETE', 'COMPLETED' ), true );
		$us     = $g[ $side . '_score' ] ?? '';
		$them   = $g[ $other . '_score' ] ?? '';
		$scored = $final && is_numeric( $us ) && is_numeric( $them );
		$type_id = absint( $g['game_type_id'] ?? 0 );
		$is_pool = $type_id ? 1 === $type_id : false !== stripos( (string) ( $g['game_type'] ?? 'Pool' ), 'pool' );
		$item   = array(
			'event'      => (string) ( $g['event_name'] ?? $g['event'] ?? '' ),
			'event_slug' => sanitize_title( (string) ( $g['event_name'] ?? $g['event'] ?? '' ) ),
			'date'       => $date,
			'time'       => (string) ( $g['time'] ?? '' ),
			'field'      => (string) ( $g['field'] ?? '' ),
			'division'   => (string) ( $g['division'] ?? '' ),
			'opponent'   => trim( html_entity_decode( (string) ( $g[ $other . '_label' ] ?? $g[ $other ] ?? '' ), ENT_QUOTES, 'UTF-8' ) ),
			// 1.0.29: profile slug of the opponent (age-suffixed for shared names), for links.
			'opponent_key' => (string) ( $g[ $other . '_key' ] ?? sanitize_title( trim( html_entity_decode( (string) ( $g[ $other ] ?? '' ), ENT_QUOTES, 'UTF-8' ) ) ) ),
			'home'       => 'home' === $side,
			'status'     => $status,
			'final'      => $final,
			'type'       => 'playbook' === ( $g['source'] ?? '' ) ? '' : ( $is_pool ? 'Pool' : ( (string) ( $g['round'] ?? '' ) ?: 'Bracket' ) ),
			'us'         => $scored ? (int) $us : null,
			'them'       => $scored ? (int) $them : null,
			'result'     => $scored ? ( (int) $us > (int) $them ? 'W' : ( (int) $us < (int) $them ? 'L' : 'T' ) ) : '',
		);
		if ( ! $final && $date >= $today && false === strpos( $status, 'CANCEL' ) ) {
			$upcoming[] = $item;
			continue;
		}
		if ( ! $scored ) {
			continue; // Unplayed, cancelled or unscored past games are not part of the record.
		}
		$season = swat_child_season_for( $date );
		if ( '' === $season['key'] ) {
			continue;
		}
		if ( ! isset( $seasons[ $season['key'] ] ) ) {
			$seasons[ $season['key'] ] = array( 'key' => $season['key'], 'label' => $season['label'], 'games' => array() );
		}
		$seasons[ $season['key'] ]['games'][] = $item;
	}

	foreach ( $seasons as &$season ) {
		usort( $season['games'], static function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } );
		$season['record'] = swat_child_team_totals( $season['games'] );
		$events = array();
		foreach ( $season['games'] as $item ) {
			$key = $item['event_slug'] . '|' . substr( $item['date'], 0, 4 );
			if ( ! isset( $events[ $key ] ) ) {
				$events[ $key ] = array( 'event' => $item['event'], 'event_slug' => $item['event_slug'], 'date' => $item['date'], 'divisions' => array(), 'games' => array() );
			}
			$events[ $key ]['divisions'][ preg_replace( '/\s+Open$/i', '', $item['division'] ) ] = true;
			$events[ $key ]['games'][] = $item;
		}
		foreach ( $events as &$event ) {
			$event['divisions'] = implode( ', ', array_keys( $event['divisions'] ) );
			$event['record']    = swat_child_team_totals( $event['games'] );
			unset( $event['games'] );
		}
		unset( $event );
		$season['events'] = array_values( $events );
	}
	unset( $season );
	krsort( $seasons );
	usort( $upcoming, static function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } );

	return array(
		'name'     => $name,
		'slug'     => $slug,
		'seasons'  => $seasons,
		'upcoming' => $upcoming,
	);
}

/**
 * Age group of a division ("9U Open" → "9U"), '' when none.
 *
 * @param string $division Division.
 * @return string
 */
function swat_child_division_age( $division ) {
	return preg_match( '/\b(\d{1,2}U)\b/i', (string) $division, $m ) ? strtoupper( $m[1] ) : '';
}

/**
 * Tell apart different teams that share a name (pure, unit tested), 1.0.22.
 *
 * Playbook gives each team its own id; the season export shows e.g. three
 * different "Camel City" teams (9U, 10U, 12U) and never two ids with one name in
 * the same division. For such names every game side gets a key and label with
 * the team's age group ("camel-city-9u", "Camel City (9U)"); games without a
 * Playbook team id are assigned by division. Names used by one team keep the
 * plain slug, so a team playing up (Canes Triad 7U Black in an 8U event) keeps
 * one profile.
 *
 * @param array $games Games.
 * @return array Games with away_key/home_key (+ _label for shared names).
 */
function swat_child_team_assign_keys( $games ) {
	$ids = array();
	foreach ( $games as $g ) {
		foreach ( array( 'away', 'home' ) as $side ) {
			$id = (string) ( $g[ $side . '_pb_team' ] ?? '' );
			if ( '' !== $id ) {
				$age = swat_child_division_age( $g['division'] ?? '' );
				$ids[ sanitize_title( (string) $g[ $side ] ) ][ $id ][ $age ] = ( $ids[ sanitize_title( (string) $g[ $side ] ) ][ $id ][ $age ] ?? 0 ) + 1;
			}
		}
	}
	$shared = array();
	foreach ( $ids as $slug => $teams ) {
		if ( count( $teams ) < 2 ) {
			continue;
		}
		foreach ( $teams as $id => $ages ) {
			arsort( $ages );
			$shared[ $slug ][ $id ] = array( 'age' => (string) key( $ages ), 'ages' => array_keys( $ages ) );
		}
	}
	if ( ! $shared ) {
		return $games;
	}
	foreach ( $games as &$g ) {
		foreach ( array( 'away', 'home' ) as $side ) {
			$name = trim( html_entity_decode( (string) ( $g[ $side ] ?? '' ), ENT_QUOTES, 'UTF-8' ) );
			$slug = sanitize_title( $name );
			if ( ! isset( $shared[ $slug ] ) ) {
				continue;
			}
			$id  = (string) ( $g[ $side . '_pb_team' ] ?? '' );
			$age = isset( $shared[ $slug ][ $id ] ) ? $shared[ $slug ][ $id ]['age'] : '';
			if ( '' === $age ) {
				$game_age = swat_child_division_age( $g['division'] ?? '' );
				foreach ( $shared[ $slug ] as $team ) {
					if ( in_array( $game_age, $team['ages'], true ) ) {
						$age = $team['age'];
						break;
					}
				}
				$age = '' === $age ? $game_age : $age;
			}
			if ( '' !== $age ) {
				$g[ $side . '_key' ]   = $slug . '-' . strtolower( $age );
				$g[ $side . '_label' ] = $name . ' (' . $age . ')';
			}
		}
	}
	unset( $g );
	return $games;
}

/**
 * W-L-T and run totals for scored games.
 *
 * @param array $games Items with result/us/them.
 * @return array
 */
function swat_child_team_totals( $games ) {
	$t = array( 'w' => 0, 'l' => 0, 't' => 0, 'gp' => 0, 'rs' => 0, 'ra' => 0 );
	foreach ( $games as $g ) {
		if ( '' === $g['result'] ) {
			continue;
		}
		++$t['gp'];
		$t['rs'] += $g['us'];
		$t['ra'] += $g['them'];
		$t[ strtolower( $g['result'] ) ]++;
	}
	$t['rd']     = $t['rs'] - $t['ra'];
	$t['pct']    = $t['gp'] ? round( 100 * $t['w'] / $t['gp'], 2 ) : 0;
	$t['avg_rs'] = $t['gp'] ? round( $t['rs'] / $t['gp'], 2 ) : 0;
	$t['avg_ra'] = $t['gp'] ? round( $t['ra'] / $t['gp'], 2 ) : 0;
	$t['avg_rd'] = $t['gp'] ? round( $t['rd'] / $t['gp'], 2 ) : 0;
	return $t;
}

/**
 * All games including resolved bracket fixtures of current/upcoming events
 * (a semifinal slot that already shows a real team name).
 *
 * @return array
 */
function swat_child_team_profile_games() {
	$games = function_exists( 'swat_os_all_synchronized_games' ) ? swat_os_all_synchronized_games() : array();
	$done  = array();
	if ( function_exists( 'swat_os_api_bracket_fixtures' ) && function_exists( 'swat_os_event_directory' ) ) {
		$today = current_time( 'Y-m-d' );
		foreach ( swat_os_event_directory() as $event ) {
			if ( (string) ( $event['end_date'] ?? '' ) < $today || empty( $event['game_count'] ) ) {
				continue;
			}
			$done[ sanitize_title( (string) $event['event_name'] ) ] = true;
			foreach ( swat_os_api_bracket_fixtures( (string) $event['event_name'] ) as $fixture ) {
				if ( ! empty( $fixture['virtual'] ) ) {
					$fixture['event_name'] = (string) $event['event_name'];
					$games[] = $fixture;
				}
			}
		}
	}
	$games = array_merge( $games, swat_child_team_profile_cached_bracket_games( $games, $done ) );
	// 1.0.25: finished games of events in the export come from the export (uploads are combined by Playbook id, so partial files never erase finals).
	if ( function_exists( 'swat_child_export_merge' ) ) {
		$games = swat_child_export_merge( $games, swat_child_export_games() );
	}
	if ( function_exists( 'swat_child_source_apply' ) ) { $games = swat_child_source_apply( $games ); }
	return swat_child_team_assign_keys( $games );
}

/**
 * Finished bracket games of past events that exist only in the Playbook bracket
 * cache (never imported as swat_game posts), 1.0.20. Only games with two real
 * team names are used; a game already present as a post (same date, time and
 * teams) is skipped.
 *
 * @param array $games Games already collected.
 * @param array $done  Event slugs already handled.
 * @return array
 */
function swat_child_team_profile_cached_bracket_games( $games, $done ) {
	$cache = get_option( 'swat_playbook_bracket_fixture_cache_v1', array() );
	if ( ! is_array( $cache ) || ! function_exists( 'swat_os_path_cached_playbook_bracket_games' ) ) {
		return array();
	}
	$fingerprint = static function ( $g ) {
		$teams = array( sanitize_title( (string) ( $g['away'] ?? '' ) ), sanitize_title( (string) ( $g['home'] ?? '' ) ) );
		sort( $teams );
		return (string) ( $g['date'] ?? '' ) . '|' . substr( (string) ( $g['time'] ?? '' ), 0, 5 ) . '|' . implode( '|', $teams );
	};
	$known = array();
	foreach ( $games as $g ) {
		$known[ $fingerprint( $g ) ] = true;
	}
	// Playbook ids already imported as posts; the cache helper skips these.
	global $wpdb;
	$synced = array();
	foreach ( (array) $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_swat_game_playbook_id' AND meta_value <> ''" ) as $playbook_id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$synced[] = array( 'playbook_id' => $playbook_id );
	}
	$extra = array();
	foreach ( $cache as $entry ) {
		$name = is_array( $entry ) ? (string) ( $entry['event_name'] ?? '' ) : '';
		$slug = sanitize_title( $name );
		if ( '' === $slug || isset( $done[ $slug ] ) ) {
			continue;
		}
		$done[ $slug ] = true;
		foreach ( (array) swat_os_path_cached_playbook_bracket_games( $name, $synced ) as $fixture ) {
			if ( ! is_array( $fixture ) || swat_child_is_placeholder_team( (string) ( $fixture['away'] ?? '' ) ) || swat_child_is_placeholder_team( (string) ( $fixture['home'] ?? '' ) ) ) {
				continue;
			}
			$key = $fingerprint( $fixture );
			if ( isset( $known[ $key ] ) ) {
				continue;
			}
			$known[ $key ]          = true;
			$fixture['event_name'] = $name;
			$extra[]               = $fixture;
		}
	}
	return $extra;
}

/**
 * Profile for a slug (null when unknown).
 *
 * @param string $slug Team slug.
 * @return array|null
 */
function swat_child_team_profile( $slug ) {
	$slug    = sanitize_title( $slug );
	$today   = current_time( 'Y-m-d' );
	$games   = swat_child_team_profile_games();
	$sources = array();
	if ( function_exists( 'swat_child_pb_apply' ) ) {
		$applied = swat_child_pb_apply( $games, $slug, $today );
		$games   = $applied['games'];
		$sources = $applied['sources'];
	}
	$profile = swat_child_team_profile_build( $games, $slug, $today );
	if ( $profile ) {
		$profile['playbook'] = $sources;
		return $profile;
	}
	// A name shared by several teams (e.g. Camel City 9U / 10U / 12U): offer them.
	$choices = array();
	foreach ( swat_child_team_directory_rows() as $row ) {
		if ( 0 === strpos( $row['slug'], $slug . '-' ) && sanitize_title( $row['base'] ) === $slug ) {
			$choices[] = $row;
		}
	}
	return $choices ? array( 'chooser' => $choices, 'name' => $choices[0]['base'] ) : null;
}

/**
 * Directory rows: every team with games, its divisions and latest-season record.
 *
 * @return array<int,array{name:string,slug:string,divisions:string,season:string,record:array,last:string}>
 */
function swat_child_team_directory_rows() {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	$teams = array();
	foreach ( swat_child_team_profile_games() as $g ) {
		foreach ( array( 'away', 'home' ) as $side ) {
			$name = trim( html_entity_decode( (string) ( $g[ $side ] ?? '' ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $name || ( function_exists( 'swat_child_is_placeholder_team' ) && swat_child_is_placeholder_team( $name ) ) ) {
				continue;
			}
			$slug = (string) ( $g[ $side . '_key' ] ?? sanitize_title( $name ) );
			if ( ! isset( $teams[ $slug ] ) ) {
				$teams[ $slug ] = array( 'name' => (string) ( $g[ $side . '_label' ] ?? $name ), 'base' => $name, 'slug' => $slug, 'divisions' => array(), 'last' => '' );
			}
			$teams[ $slug ]['divisions'][ preg_replace( '/\s+Open$/i', '', (string) ( $g['division'] ?? '' ) ) ] = true;
			$teams[ $slug ]['last'] = max( $teams[ $slug ]['last'], (string) ( $g['date'] ?? '' ) );
		}
	}
	foreach ( $teams as &$team ) {
		$divs = array_keys( array_filter( $team['divisions'] ) );
		natsort( $divs );
		$team['divisions'] = implode( ', ', $divs );
	}
	unset( $team );
	uasort( $teams, static function ( $a, $b ) { return strnatcasecmp( $a['name'], $b['name'] ); } );
	$rows = array_values( $teams );
	return $rows;
}

/**
 * Routes: /teams/, /teams/{slug}/ and /teams/?team={exact name} (redirects to the slug).
 *
 * @return void
 */
function swat_child_team_routes() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( is_admin() || wp_doing_ajax() || 'GET' !== $method || ! function_exists( 'swat_child_request_path' ) ) {
		return;
	}
	$path = swat_child_request_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path only, matched against fixed patterns.

	if ( '/teams/' === $path ) {
		if ( isset( $_GET['team'] ) && '' !== trim( (string) $_GET['team'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
			wp_safe_redirect( home_url( '/teams/' . sanitize_title( sanitize_text_field( wp_unslash( $_GET['team'] ) ) ) . '/' ), 302 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			exit;
		}
		status_header( 200 );
		$GLOBALS['swat_child_team_view'] = array( 'mode' => 'directory' );
		include SWAT_CHILD_DIR . '/team-profile.php';
		exit;
	}
	if ( preg_match( '#^/teams/([^/]+)/$#', $path, $m ) ) {
		$profile = swat_child_team_profile( $m[1] );
		status_header( $profile ? 200 : 404 );
		if ( ! $profile ) {
			nocache_headers();
		}
		$GLOBALS['swat_child_team_view'] = array( 'mode' => 'profile', 'profile' => $profile, 'slug' => sanitize_title( $m[1] ) );
		include SWAT_CHILD_DIR . '/team-profile.php';
		exit;
	}
}
add_action( 'template_redirect', 'swat_child_team_routes', 0 );

/**
 * Document title for team pages.
 *
 * @param string $title Title.
 * @return string
 */
function swat_child_team_document_title( $title ) {
	$view = $GLOBALS['swat_child_team_view'] ?? null;
	if ( ! $view ) {
		return $title;
	}
	if ( 'directory' === $view['mode'] ) {
		return 'Teams | SWAT Tournaments';
	}
	return ! empty( $view['profile']['name'] ) ? $view['profile']['name'] . ' · Team Profile | SWAT Tournaments' : 'Team not found | SWAT Tournaments';
}
add_filter( 'pre_get_document_title', 'swat_child_team_document_title', 20 );

/**
 * Team page stylesheet.
 *
 * @return void
 */
function swat_child_team_assets() {
	if ( empty( $GLOBALS['swat_child_team_view'] ) ) {
		return;
	}
	$css = SWAT_CHILD_DIR . '/assets/css/team-profile.css';
	wp_enqueue_style( 'swat-child-team-profile', SWAT_CHILD_URI . '/assets/css/team-profile.css', array(), (string) filemtime( $css ) );
}
add_action( 'wp_enqueue_scripts', 'swat_child_team_assets', 230 );
