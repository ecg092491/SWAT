<?php
/**
 * Team profile results from the team's Playbook profile page (child 1.0.20).
 *
 * Playbook (play.swatourneys.com/team/details/{season}/{team-id}) is the record
 * of truth for finished games. Some games never reach WordPress (e.g. August
 * Heat bracket games), so a team profile reads that page server-side and uses
 * its game list for finished games of that season. WordPress games still supply
 * times, fields and game types where they match, and all upcoming games.
 *
 * Only the GAMES and EVENTS tables are read. Roster, birth dates and coaches on
 * the Playbook page are ignored (minors' data).
 *
 * Team ids come from Settings → SWAT Team Profiles (Playbook team links) and are
 * learned automatically from opponent links on every Playbook page read.
 * Pages are cached for 30 minutes; the last good copy is kept if Playbook is
 * unreachable, and the profile falls back to WordPress data when there is none.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

const SWAT_CHILD_PB_HOST = 'play.swatourneys.com';

/**
 * Playbook team id (uuid) and season from a team URL.
 *
 * @param string $url URL.
 * @return array{season:string,id:string}|null
 */
function swat_child_pb_parse_team_url( $url ) {
	if ( preg_match( '#^https?://' . preg_quote( SWAT_CHILD_PB_HOST, '#' ) . '/team/details/([a-z0-9-]+)/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})#i', trim( (string) $url ), $m ) ) {
		return array( 'season' => strtolower( $m[1] ), 'id' => strtolower( $m[2] ) );
	}
	return null;
}

/**
 * Team URL for a season key (e.g. 2026-fall).
 *
 * @param string $id     Team uuid.
 * @param string $season Season key.
 * @return string
 */
function swat_child_pb_team_url( $id, $season ) {
	return 'https://' . SWAT_CHILD_PB_HOST . '/team/details/' . rawurlencode( $season ) . '/' . rawurlencode( $id );
}

/**
 * Parse a Playbook team page (pure, unit tested).
 *
 * @param string $html Page HTML.
 * @return array{events:array,games:array,teams:array}
 */
function swat_child_pb_parse_team_page( $html ) {
	$out = array( 'events' => array(), 'games' => array(), 'teams' => array() );
	if ( '' === trim( (string) $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $out;
	}
	$doc = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	$text = static function ( $node ) {
		return trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', html_entity_decode( (string) $node->textContent, ENT_QUOTES, 'UTF-8' ) ) );
	};
	$link = static function ( $cell, $pattern ) {
		foreach ( $cell->getElementsByTagName( 'a' ) as $a ) {
			$href = (string) $a->getAttribute( 'href' );
			if ( preg_match( $pattern, $href ) ) {
				return array( 'href' => $href, 'text' => trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', html_entity_decode( (string) $a->textContent, ENT_QUOTES, 'UTF-8' ) ) ) );
			}
		}
		return null;
	};
	$team_re  = '#/team/details/[^/]+/[0-9a-f-]{36}#i';
	$event_re = '#/events/[^/?\#]+#i';

	foreach ( $doc->getElementsByTagName( 'tr' ) as $tr ) {
		$cells = array();
		foreach ( $tr->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && in_array( strtolower( $child->nodeName ), array( 'td', 'th' ), true ) ) {
				$cells[] = $child;
			}
		}
		$n = count( $cells );
		if ( $n < 2 ) {
			continue;
		}
		// EVENTS table: DATE | EVENT | DIVISION | W-L-T | ...
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})$/', $text( $cells[0] ), $dm ) && ( $ev = $link( $cells[1], $event_re ) ) ) {
			$out['events'][ $ev['text'] ] = array(
				'name'     => $ev['text'],
				'url'      => $ev['href'],
				'date'     => $dm[1],
				'division' => $n > 2 ? $text( $cells[2] ) : '',
			);
			continue;
		}
		// GAMES table: # | EVENT | TEAM | SCORE | TEAM.
		if ( $n < 5 ) {
			continue;
		}
		$event = null;
		$teams = array();
		$score = null;
		foreach ( $cells as $i => $cell ) {
			$t = $link( $cell, $team_re );
			if ( $t ) {
				$teams[] = $t;
				continue;
			}
			$e = $link( $cell, $event_re );
			if ( $e && ! $event ) {
				$event = $e;
				continue;
			}
			if ( preg_match( '/^(\d{1,3})\s*[-–]\s*(\d{1,3})$/u', $text( $cell ), $sm ) && 1 === count( $teams ) ) {
				$score = array( (int) $sm[1], (int) $sm[2] );
			}
		}
		if ( ! $event || 2 !== count( $teams ) ) {
			continue;
		}
		foreach ( $teams as $t ) {
			$parsed = swat_child_pb_parse_team_url( $t['href'] );
			if ( $parsed && '' !== $t['text'] ) {
				$out['teams'][ sanitize_title( $t['text'] ) ] = $parsed['id'];
			}
		}
		$out['games'][] = array(
			'event'    => $event['text'],
			'team_1'   => $teams[0]['text'],
			'team_2'   => $teams[1]['text'],
			'score_1'  => $score ? $score[0] : null,
			'score_2'  => $score ? $score[1] : null,
		);
	}
	return $out;
}

/**
 * Team uuids by team slug: Settings entries win over learned ones.
 *
 * @return array<string,string>
 */
function swat_child_pb_team_ids() {
	$learned    = get_option( 'swat_child_pb_learned_teams', array() );
	$configured = get_option( 'swat_child_pb_team_ids', array() );
	return array_merge( is_array( $learned ) ? $learned : array(), is_array( $configured ) ? $configured : array() );
}

/**
 * Remember team ids seen on a Playbook page.
 *
 * @param array $teams slug => uuid.
 * @return void
 */
function swat_child_pb_learn_teams( $teams ) {
	if ( ! $teams ) {
		return;
	}
	$learned = get_option( 'swat_child_pb_learned_teams', array() );
	$learned = is_array( $learned ) ? $learned : array();
	$merged  = array_merge( $learned, $teams );
	if ( $merged !== $learned ) {
		update_option( 'swat_child_pb_learned_teams', array_slice( $merged, -5000, null, true ), false );
	}
}

/**
 * Parsed Playbook page for a team and season (30-minute cache, stale fallback).
 *
 * @param string $id     Team uuid.
 * @param string $season Season key.
 * @param bool   $force  Skip the cache (Settings save).
 * @return array|null Null when no usable copy exists.
 */
function swat_child_pb_team_page( $id, $season, $force = false ) {
	$key   = 'swat_child_pb_team_' . md5( $id . '|' . $season );
	$fresh = $force ? false : get_transient( $key );
	if ( is_array( $fresh ) ) {
		return $fresh['games'] ? $fresh : null;
	}
	$stale = get_option( $key . '_last', null );
	$resp  = wp_remote_get( swat_child_pb_team_url( $id, $season ), array( 'timeout' => 8, 'redirection' => 2, 'user-agent' => 'swatourneys.com team profiles' ) );
	$data  = null;
	$success = false;
	if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
		$data = swat_child_pb_parse_team_page( wp_remote_retrieve_body( $resp ) );
		swat_child_pb_learn_teams( $data['teams'] );
		if ( function_exists( 'swat_child_source_remember_events' ) ) { swat_child_source_remember_events( $data ); }
		if ( $data['games'] ) {
			$success = true;
			$data['fetched_at'] = time();
			update_option( $key . '_last', $data, false );
		}
	}
	if ( ! $data || ! $data['games'] ) {
		$data = is_array( $stale ) ? $stale : array( 'events' => array(), 'games' => array(), 'teams' => array() );
	}
	// Retry a failed fetch after 5 minutes rather than on every view.
	set_transient( $key, $data, $success ? 30 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
	return $data['games'] ? $data : null;
}

/**
 * Add the team's finished Playbook games that WordPress is missing (pure, unit tested).
 *
 * WordPress games that match a Playbook game (same event, teams and score) are
 * kept for their time, field and type; Playbook games with no match are added
 * in Playbook order. Nothing is removed (a page that parses partially can only
 * add games, never hide real ones). Upcoming games are untouched. Events with
 * authoritative export finals are excluded from HTML additions (1.0.26).
 *
 * @param array  $games  All WordPress games (profile input format).
 * @param string $slug   Team slug.
 * @param string $season Season key.
 * @param array  $page   Parsed Playbook page.
 * @param string $today  Y-m-d.
 * @return array
 */
function swat_child_pb_merge_games( $games, $slug, $season, $page, $today, $authoritative_events = array() ) {
	foreach ( $games as $g ) {
		if ( 'playbook-snapshot' === ( $g['source'] ?? '' ) || ( 'export' === ( $g['source'] ?? '' ) && 'final' === ( $g['status'] ?? '' ) ) ) { $authoritative_events[] = $g['event_name']; }
	}
	$authoritative_events = array_fill_keys( array_map( 'sanitize_title', $authoritative_events ), true );
	$norm  = static function ( $s ) { return sanitize_title( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ); };
	$final = static function ( $g ) {
		return in_array( strtoupper( trim( str_replace( array( '_', '-' ), ' ', (string) ( $g['status'] ?? '' ) ) ) ), array( 'FINAL', 'COMPLETE', 'COMPLETED' ), true );
	};
	$sig = static function ( $event, $a, $b, $sa, $sb ) use ( $norm ) {
		$pair = array( $norm( $a ) . ':' . (int) $sa, $norm( $b ) . ':' . (int) $sb );
		sort( $pair );
		return $norm( $event ) . '|' . implode( '|', $pair );
	};

	$keep  = array();
	$mine  = array();
	foreach ( $games as $g ) {
		$ours = $slug === $norm( $g['away'] ?? '' ) || $slug === $norm( $g['home'] ?? '' );
		$in   = function_exists( 'swat_child_season_for' ) && swat_child_season_for( (string) ( $g['date'] ?? '' ) )['key'] === $season;
		if ( $ours && $in && $final( $g ) ) {
			$mine[ $sig( $g['event_name'] ?? $g['event'] ?? '', $g['away'] ?? '', $g['home'] ?? '', $g['away_score'] ?? 0, $g['home_score'] ?? 0 ) ][] = $g;
			continue;
		}
		$keep[] = $g;
	}

	$prev = array();
	foreach ( $page['games'] as $pg ) {
		// CSV events are authoritative; cached HTML cannot add conflicting finals.
		if ( isset( $authoritative_events[ $norm( $pg['event'] ) ] ) ) { continue; }
		if ( null === $pg['score_1'] || null === $pg['score_2'] ) {
			continue; // Not played yet; upcoming games come from WordPress.
		}
		if ( $slug !== $norm( $pg['team_1'] ) && $slug !== $norm( $pg['team_2'] ) ) {
			continue;
		}
		$s = $sig( $pg['event'], $pg['team_1'], $pg['team_2'], $pg['score_1'], $pg['score_2'] );
		if ( ! empty( $mine[ $s ] ) ) {
			$g = array_shift( $mine[ $s ] );
		} else {
			$event = $page['events'][ $pg['event'] ] ?? array();
			$last  = $prev[ $norm( $pg['event'] ) ] ?? array();
			$g     = array(
				'event_name'   => $pg['event'],
				'date'         => $last['date'] ?? ( $event['date'] ?? '' ),
				'time'         => $last['time'] ?? '',
				'field'        => '',
				'division'     => $last['division'] ?? ( isset( $event['division'] ) && '' !== $event['division'] ? $event['division'] . ' Open' : '' ),
				'away'         => $pg['team_1'],
				'home'         => $pg['team_2'],
				'away_score'   => (string) $pg['score_1'],
				'home_score'   => (string) $pg['score_2'],
				'status'       => 'final',
				'game_type_id' => 0,
				'game_type'    => '',
				'round'        => 'Game',
				'source'       => 'playbook',
			);
			if ( '' === $g['date'] || ( $g['date'] > $today ) ) {
				continue;
			}
		}
		$prev[ $norm( $pg['event'] ) ] = $g;
		$keep[] = $g;
	}
	foreach ( $mine as $left ) {
		foreach ( $left as $g ) {
			$keep[] = $g;
		}
	}
	return $keep;
}

/**
 * Apply Playbook results to a team's games for every season the team has.
 *
 * @param array  $games All games.
 * @param string $slug  Team slug.
 * @param string $today Y-m-d.
 * @return array{games:array,sources:array<string,string>} sources: season => Playbook URL used.
 */
function swat_child_pb_apply( $games, $slug, $today ) {
	$ids = swat_child_pb_team_ids();
	if ( empty( $ids[ $slug ] ) ) {
		return array( 'games' => $games, 'sources' => array() );
	}
	$seasons = array();
	foreach ( $games as $g ) {
		$norm = sanitize_title( (string) ( $g['away'] ?? '' ) ) === $slug || sanitize_title( (string) ( $g['home'] ?? '' ) ) === $slug;
		if ( $norm ) {
			$key = swat_child_season_for( (string) ( $g['date'] ?? '' ) )['key'];
			if ( '' !== $key ) {
				$seasons[ $key ] = true;
			}
		}
	}
	$authoritative_events = array();
	if ( function_exists( 'swat_child_export_games' ) ) {
		foreach ( swat_child_export_games() as $g ) {
			if ( 'final' === ( $g['status'] ?? '' ) ) { $authoritative_events[] = $g['event_name']; }
		}
	}
	$sources = array();
	foreach ( array_keys( $seasons ) as $season ) {
		$page = swat_child_pb_team_page( $ids[ $slug ], $season );
		if ( $page ) {
			if ( function_exists( 'swat_child_source_remember_events' ) ) { swat_child_source_remember_events( $page ); }
			$games               = swat_child_pb_merge_games( $games, $slug, $season, $page, $today, $authoritative_events );
			$sources[ $season ]  = swat_child_pb_team_url( $ids[ $slug ], $season );
		}
	}
	return array( 'games' => $games, 'sources' => $sources );
}

/* ---------- Settings → SWAT Team Profiles ---------- */

/**
 * Admin page.
 *
 * @return void
 */
function swat_child_pb_admin_menu() {
	add_options_page( 'SWAT Team Profiles', 'SWAT Team Profiles', 'manage_options', 'swat-team-profiles', 'swat_child_pb_admin_page' );
}
add_action( 'admin_menu', 'swat_child_pb_admin_menu' );

/**
 * Save handler + form.
 *
 * @return void
 */
function swat_child_pb_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$notice = function_exists( 'swat_child_export_handle_upload' ) ? swat_child_export_handle_upload() : '';
	if ( isset( $_POST['swat_child_pb_links'] ) && check_admin_referer( 'swat_child_pb_save' ) ) {
		$ids    = array();
		$bad    = array();
		foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['swat_child_pb_links'] ) ) as $line ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each line is parsed as a URL and team name below.
			$line = trim( sanitize_text_field( $line ) );
			if ( '' === $line ) {
				continue;
			}
			$url    = preg_match( '#https?://\S+#', $line, $m ) ? $m[0] : '';
			$parsed = swat_child_pb_parse_team_url( $url );
			$name   = trim( str_replace( $url, '', $line ), " \t|=,-" );
			if ( ! $parsed ) {
				$bad[] = $line;
				continue;
			}
			if ( '' === $name ) {
				// Name from teams already seen, else read it from the page.
				$known = array_search( $parsed['id'], (array) get_option( 'swat_child_pb_learned_teams', array() ), true );
				if ( false === $known ) {
					swat_child_pb_team_page( $parsed['id'], $parsed['season'], true );
					$known = array_search( $parsed['id'], (array) get_option( 'swat_child_pb_learned_teams', array() ), true );
				}
				$name = false === $known ? '' : (string) $known;
			}
			if ( '' === $name ) {
				$bad[] = $line . ' (team name not found; add it before the link: Team Name | link)';
				continue;
			}
			$ids[ sanitize_title( $name ) ] = $parsed['id'];
		}
		update_option( 'swat_child_pb_team_ids', $ids, false );
		$notice = sprintf( '%d team link(s) saved.', count( $ids ) ) . ( $bad ? ' Not saved: ' . implode( '; ', $bad ) : '' );
	}
	$ids     = get_option( 'swat_child_pb_team_ids', array() );
	$learned = get_option( 'swat_child_pb_learned_teams', array() );
	$lines   = array();
	foreach ( (array) $ids as $slug => $id ) {
		$lines[] = $slug . ' | ' . swat_child_pb_team_url( $id, swat_child_season_for( current_time( 'Y-m-d' ) )['key'] );
	}
	?>
	<div class="wrap">
		<h1>SWAT Team Profiles</h1>
		<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<p>Team profiles on <a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>">/teams/</a> take finished games and records from each team's Playbook profile page. Teams linked from any Playbook page already read are found automatically (<?php echo esc_html( count( (array) $learned ) ); ?> so far). Add a team here if its profile still misses games.</p>
		<form method="post">
			<?php wp_nonce_field( 'swat_child_pb_save' ); ?>
			<p><label for="swat-child-pb-links"><strong>Playbook team links</strong>, one per line. The team name is read from the page; you can also write <code>Team Name | link</code>.</label></p>
			<textarea id="swat-child-pb-links" name="swat_child_pb_links" rows="10" class="large-text code" placeholder="https://play.swatourneys.com/team/details/2026-fall/..."><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
			<?php submit_button( 'Save team links' ); ?>
		</form>
		<?php if ( function_exists( 'swat_child_export_admin_section' ) ) { swat_child_export_admin_section(); } ?>
	</div>
	<?php
}

/**
 * Seed the team link the site owner supplied (Canes Triad 7U Black), once.
 *
 * @return void
 */
function swat_child_pb_seed() {
	if ( false === get_option( 'swat_child_pb_team_ids', false ) ) {
		add_option( 'swat_child_pb_team_ids', array( 'canes-triad-7u-black' => '1f1476ea-fab9-47d9-9f4b-80a62d3ff9ab' ), '', false );
	}
}
add_action( 'init', 'swat_child_pb_seed' );
