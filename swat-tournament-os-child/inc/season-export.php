<?php
/**
 * Playbook season games export (child 1.0.22).
 *
 * Playbook's "season games" CSV export lists every game of a season with its
 * Playbook game id, date, time, field, division, both teams, scores and
 * Pool/Bracket type. The sync never imported some finished games (e.g. 28 of
 * August Heat's 64), so this export fills gaps and corrects matching FINISHED games:
 *
 * - Team profiles: for an event in the export, its finished games come from the
 *   export; WordPress keeps supplying upcoming/live games and events not in it.
 * - Uploads: combined with earlier exports by Playbook game id, so a partial
 *   file updates matching games and never erases the others (1.0.25).
 * - Event pages: finished export games missing from WordPress are listed.
 *
 * Sources, newest wins per Playbook game id: files uploaded in Settings → SWAT Team
 * Profiles, merged with files shipped in data/ (data/playbook-season-*.csv).
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse a Playbook season games CSV (pure, unit tested).
 *
 * @param string $csv CSV text.
 * @return array{games:array,errors:string[]}
 */
function swat_child_export_parse_csv( $csv ) {
	$out = array( 'games' => array(), 'errors' => array() );
	$csv = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
	$fh  = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $csv );
	rewind( $fh );
	$head = fgetcsv( $fh, 0, ',', '"', '\\' );
	$need = array( 'Event Name', 'Id', 'Game Number', 'Schedule Date', 'Schedule Time', 'Field', 'Division', 'Team 1', 'Team 1 Id', 'Team 1 Score', 'Team 2', 'Team 2 Id', 'Team 2 Score', 'Game Type' );
	$col  = is_array( $head ) ? array_flip( array_map( 'trim', $head ) ) : array();
	$miss = array_diff( $need, array_keys( $col ) );
	if ( $miss ) {
		fclose( $fh );
		$out['errors'][] = 'Not a Playbook season games export (missing columns: ' . implode( ', ', $miss ) . ').';
		return $out;
	}
	$line = 1;
	while ( false !== ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) ) {
		++$line;
		if ( array( null ) === $row || ! array_filter( $row, 'strlen' ) ) {
			continue;
		}
		$get  = static function ( $name ) use ( $row, $col ) { return trim( (string) ( $row[ $col[ $name ] ] ?? '' ) ); };
		$date = DateTime::createFromFormat( '!n/j/Y', $get( 'Schedule Date' ) );
		$time = DateTime::createFromFormat( '!g:i A', strtoupper( $get( 'Schedule Time' ) ) );
		$id   = (int) $get( 'Id' );
		if ( ! $date || ! $id || '' === $get( 'Event Name' ) || '' === $get( 'Team 1' ) || '' === $get( 'Team 2' ) ) {
			$out['errors'][] = "Line $line skipped (missing date, id, event or team).";
			continue;
		}
		$s1     = $get( 'Team 1 Score' );
		$s2     = $get( 'Team 2 Score' );
		$scored = is_numeric( $s1 ) && is_numeric( $s2 );
		$pool   = 0 === strcasecmp( $get( 'Game Type' ), 'Pool' );
		// Team 1 is the away team (TourneyOS default team_1_role), as on the event pages.
		$out['games'][] = array(
			'event_name'   => $get( 'Event Name' ),
			'date'         => $date->format( 'Y-m-d' ),
			'time'         => $time ? $time->format( 'H:i' ) : '',
			'field'        => $get( 'Field' ),
			'division'     => $get( 'Division' ),
			'away'         => preg_replace( '/\s{2,}/', ' ', $get( 'Team 1' ) ),
			'home'         => preg_replace( '/\s{2,}/', ' ', $get( 'Team 2' ) ),
			'away_pb_team' => $get( 'Team 1 Id' ),
			'home_pb_team' => $get( 'Team 2 Id' ),
			'away_score'   => $scored ? (string) (int) $s1 : '',
			'home_score'   => $scored ? (string) (int) $s2 : '',
			'status'       => $scored ? 'final' : 'scheduled',
			'game_type_id' => $pool ? 1 : 2,
			'game_type'    => $pool ? 'Pool' : 'Bracket',
			'game_number'  => $get( 'Game Number' ),
			'playbook_id'  => (string) $id,
			'source'       => 'export',
		);
	}
	fclose( $fh );
	return $out;
}

/**
 * Games grouped by season key.
 *
 * @param array $games Parsed games.
 * @return array<string,array>
 */
function swat_child_export_by_season( $games ) {
	$seasons = array();
	foreach ( $games as $g ) {
		$key = swat_child_season_for( $g['date'] )['key'];
		if ( '' !== $key ) {
			$seasons[ $key ][] = $g;
		}
	}
	return $seasons;
}

/**
 * All export games (uploaded games reconcile with shipped ones). Cached per request.
 *
 * @return array
 */
function swat_child_export_games() {
	static $games = null;
	if ( null !== $games ) {
		return $games;
	}
	$shipped = array();
	foreach ( glob( SWAT_CHILD_DIR . '/data/playbook-season-*.csv' ) ?: array() as $file ) {
		$cache_key = 'swat_child_export_file_' . md5( $file . '|' . filemtime( $file ) );
		$parsed    = get_transient( $cache_key );
		if ( ! is_array( $parsed ) ) {
			$parsed = swat_child_export_by_season( swat_child_export_parse_csv( (string) file_get_contents( $file ) )['games'] );
			set_transient( $cache_key, $parsed, DAY_IN_SECONDS );
		}
		foreach ( $parsed as $list ) { $shipped = swat_child_export_combine( $shipped, $list ); }
	}
	$uploaded = get_option( 'swat_child_export_seasons', array() );
	$games = swat_child_export_combine( $shipped, swat_child_export_flatten( $uploaded ) );

	return $games;
}

/**
 * Export games for one event (by event slug).
 *
 * @param string $event_name Event name.
 * @return array
 */
function swat_child_export_event_games( $event_name ) {
	$slug = sanitize_title( (string) $event_name );
	return array_values( array_filter( swat_child_export_games(), static function ( $g ) use ( $slug ) { return sanitize_title( $g['event_name'] ) === $slug; } ) );
}

/**
 * Flatten season buckets before reconciling globally by Playbook ID.
 *
 * 1.0.27: buckets are flattened oldest upload first, so when stored data from
 * an earlier version still holds the same id in two seasons (a date moved
 * across Jun/Jul), the copy from the most recent upload wins. Ties keep the
 * stored order.
 *
 * @param array $seasons Stored buckets: key => {games, uploaded, file}.
 * @return array
 */
function swat_child_export_flatten( $seasons ) {
	$buckets = array();
	$i       = 0;
	foreach ( is_array( $seasons ) ? $seasons : array() as $season ) {
		$buckets[] = array( (string) ( $season['uploaded'] ?? '' ), $i++, (array) ( $season['games'] ?? array() ) );
	}
	usort( $buckets, static function ( $a, $b ) { return strcmp( $a[0], $b[0] ) ?: $a[1] - $b[1]; } );
	$games = array();
	foreach ( $buckets as $bucket ) {
		$games = array_merge( $games, $bucket[2] );
	}
	return $games;
}

/**
 * Reconcile uploaded IDs across seasons, then regroup by corrected date.
 *
 * 1.0.27: only seasons present in the new upload take its file name and
 * upload time; other seasons keep their own (a season that only lost a moved
 * game keeps its earlier metadata).
 *
 * @param array  $stored   Stored buckets.
 * @param array  $new      Newly uploaded games.
 * @param string $uploaded Upload time.
 * @param string $file     Uploaded file name.
 * @return array
 */
function swat_child_export_update_seasons( $stored, $new, $uploaded, $file ) {
	$stored   = is_array( $stored ) ? $stored : array();
	$combined = swat_child_export_combine( swat_child_export_flatten( $stored ), $new );
	$touched  = swat_child_export_by_season( $new );
	$out      = array();
	foreach ( swat_child_export_by_season( $combined ) as $key => $games ) {
		$fresh       = isset( $touched[ $key ] ) || ! isset( $stored[ $key ] );
		$out[ $key ] = array(
			'games'    => $games,
			'uploaded' => $fresh ? $uploaded : (string) ( $stored[ $key ]['uploaded'] ?? $uploaded ),
			'file'     => $fresh ? $file : (string) ( $stored[ $key ]['file'] ?? $file ),
		);
	}
	return $out;
}

/**
 * Combine two sets of EXPORT games (pure, unit tested). 1.0.25.
 *
 * Every export row carries Playbook's game id, so this is the safe place to
 * reconcile: rows in $new replace same-id rows in $old; rows of $old that $new
 * omits are kept (a partial upload never erases finals). Rows without an id
 * (not produced by the parser, defensive only) key on event/date/time/teams.
 *
 * @param array $old Earlier export games (shipped file or previous uploads).
 * @param array $new Newer export games.
 * @return array
 */
function swat_child_export_combine( $old, $new ) {
	$key = static function ( $g ) {
		$id = trim( (string) ( $g['playbook_id'] ?? '' ) );
		if ( '' !== $id ) {
			return 'id:' . $id;
		}
		$teams = array( sanitize_title( (string) ( $g['away'] ?? '' ) ), sanitize_title( (string) ( $g['home'] ?? '' ) ) );
		sort( $teams );
		return 'fp:' . implode( '|', array( sanitize_title( (string) ( $g['event_name'] ?? '' ) ), (string) ( $g['date'] ?? '' ), substr( (string) ( $g['time'] ?? '' ), 0, 5 ), implode( '|', $teams ) ) );
	};
	$out = array();
	foreach ( array( $old, $new ) as $list ) {
		foreach ( (array) $list as $g ) {
			$out[ $key( $g ) ] = $g;
		}
	}
	return array_values( $out );
}

/**
 * Combine WordPress games with export games (pure, unit tested).
 *
 * The export is the record of FINISHED games for every event it contains
 * (1.0.22 rule, restored in 1.0.25): finished WordPress games of those events
 * are replaced by the export's finals, so a WordPress row whose time, division
 * label or id differs from Playbook can never be counted a second time. A
 * WordPress row of any status whose Playbook id is an export final is also
 * replaced (stale live/scheduled copy). Upcoming/live games, and every game of
 * events not in the export, are kept. Export rows that are not final are
 * ignored (WordPress has the current schedule).
 *
 * Omitted finals are protected one level up: uploads are combined with earlier
 * exports by id (swat_child_export_combine), not replaced.
 *
 * @param array $wp     WordPress games (need event_name or event, status).
 * @param array $export Export games.
 * @return array
 */
function swat_child_export_merge( $wp, $export ) {
	$events = array();
	$ids    = array();
	$finals = array();
	foreach ( $export as $g ) {
		if ( 'final' !== ( $g['status'] ?? '' ) ) {
			continue;
		}
		$events[ sanitize_title( (string) $g['event_name'] ) ] = true;
		$id = trim( (string) ( $g['playbook_id'] ?? '' ) );
		if ( '' !== $id ) {
			$ids[ $id ] = true;
		}
		$finals[] = $g;
	}
	$out = array();
	foreach ( $wp as $g ) {
		$event  = sanitize_title( (string) ( $g['event_name'] ?? $g['event'] ?? '' ) );
		$status = strtoupper( trim( str_replace( array( '_', '-' ), ' ', (string) ( $g['status'] ?? '' ) ) ) );
		$id     = trim( (string) ( $g['playbook_id'] ?? '' ) );
		if ( isset( $events[ $event ] ) && in_array( $status, array( 'FINAL', 'COMPLETE', 'COMPLETED' ), true ) ) {
			continue;
		}
		if ( '' !== $id && isset( $ids[ $id ] ) ) {
			continue;
		}
		$out[] = $g;
	}
	$merged = array_merge( $out, $finals );
	return function_exists( 'swat_child_source_apply' ) ? swat_child_source_apply( $merged ) : $merged;
}

/**
 * Event rows (directory format) for export events, keyed by slug. Used so event
 * pages of export-only events resolve.
 *
 * @return array<string,array>
 */
function swat_child_export_events() {
	$events = array();
	foreach ( swat_child_export_games() as $g ) {
		$slug = sanitize_title( $g['event_name'] );
		if ( ! isset( $events[ $slug ] ) ) {
			$events[ $slug ] = array( 'event_name' => $g['event_name'], 'slug' => $slug, 'start_date' => $g['date'], 'end_date' => $g['date'], 'game_count' => 0, 'divisions' => array(), 'fields' => array(), 'source' => 'playbook-export' );
		}
		$e = &$events[ $slug ];
		$e['start_date'] = min( $e['start_date'], $g['date'] );
		$e['end_date']   = max( $e['end_date'], $g['date'] );
		++$e['game_count'];
		$e['divisions'][ $g['division'] ] = true;
		$e['fields'][ $g['field'] ]       = true;
		unset( $e );
	}
	foreach ( $events as &$e ) {
		$e['divisions'] = array_keys( array_filter( $e['divisions'] ) );
		$e['fields']    = array_keys( array_filter( $e['fields'] ) );
		sort( $e['divisions'] );
		sort( $e['fields'] );
	}
	unset( $e );
	return $events;
}

/**
 * Finished export games for an event page that WordPress does not have (pure).
 * Matched by Playbook game id, else by date, time and teams.
 *
 * @param array $display Games already on the page.
 * @param array $export  Export games of this event.
 * @param array $synced_ids Playbook ids of this event's synced posts.
 * @return array
 */
function swat_child_export_missing_for_event( $display, $export, $synced_ids = array() ) {
	$have = array_fill_keys( array_map( 'strval', $synced_ids ), true );
	$fp   = static function ( $g ) {
		$teams = array( sanitize_title( (string) ( $g['away'] ?? '' ) ), sanitize_title( (string) ( $g['home'] ?? '' ) ) );
		sort( $teams );
		return (string) ( $g['date'] ?? '' ) . '|' . substr( (string) ( $g['time'] ?? '' ), 0, 5 ) . '|' . implode( '|', $teams );
	};
	// Same date, teams and score also counts as the same game (times can be edited after import).
	$fs = static function ( $g ) {
		$sides = array( sanitize_title( (string) ( $g['away'] ?? '' ) ) . ':' . (string) ( $g['away_score'] ?? '' ), sanitize_title( (string) ( $g['home'] ?? '' ) ) . ':' . (string) ( $g['home_score'] ?? '' ) );
		sort( $sides );
		return (string) ( $g['date'] ?? '' ) . '|' . implode( '|', $sides );
	};
	$seen = array();
	foreach ( $display as $g ) {
		if ( ! empty( $g['playbook_id'] ) ) {
			$have[ (string) absint( $g['playbook_id'] ) ] = true;
		}
		$seen[ $fp( $g ) ] = true;
		if ( '' !== (string) ( $g['away_score'] ?? '' ) ) {
			$seen[ 's:' . $fs( $g ) ] = true;
		}
	}
	$add = array();
	foreach ( $export as $g ) {
		if ( 'final' !== $g['status'] || isset( $have[ $g['playbook_id'] ] ) || isset( $seen[ $fp( $g ) ] ) || isset( $seen[ 's:' . $fs( $g ) ] ) ) {
			continue;
		}
		$g['id']     = 800000000 + (int) $g['playbook_id'];
		$g['status'] = 'final';
		$add[]       = $g;
	}
	return $add;
}

/**
 * "9:00", "09:00", "9:00 AM", "18:00:00" -> "09:00" / "18:00" (1.0.27).
 *
 * @param string $time Time as stored by WordPress or the export.
 * @return string
 */
function swat_child_export_norm_time( $time ) {
	$time = strtoupper( trim( (string) $time ) );
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/', $time, $m ) ) {
		return $time;
	}
	$h = (int) $m[1];
	if ( ! empty( $m[3] ) ) {
		$h = ( $h % 12 ) + ( 'PM' === $m[3] ? 12 : 0 );
	}
	return sprintf( '%02d:%02d', $h, (int) $m[2] );
}

/**
 * Event-page finals use the same CSV authority as team records.
 *
 * Every row passed in belongs to this event (the template builds both lists
 * from one event), so rows without an event name are stamped with it; the
 * final merge then replaces every finished WordPress row of the event, and a
 * finished game can never appear twice (1.0.27). Export rows are matched to
 * WordPress rows only to keep post ids, round and bracket metadata, in order:
 *  1. Playbook id (when both rows have one);
 *  2. date + time (normalised) + teams;
 *  3. date + teams + score (times edited after sync);
 *  4. date + teams, when exactly one export game has that pairing that day
 *     and the WordPress row is finished (metadata recovery only).
 * Tiers 2-4 require uniqueness in both directions over the original inputs.
 * Date/teams-only matching is restricted to finished WordPress rows; unrelated
 * scheduled/live rematches are retained when stronger evidence is absent.
 *
 * @param array $display WordPress rows for this event (cards or standings input).
 * @param array $export  Export games of this event.
 * @return array
 */
function swat_child_export_reconcile_event( $display, $export ) {
	$export = swat_child_export_combine( array(), $export );
	if ( ! $export ) {
		return $display;
	}
	$event_name = (string) $export[0]['event_name'];
	foreach ( $display as &$row ) {
		if ( '' === (string) ( $row['event_name'] ?? '' ) && '' === (string) ( $row['event'] ?? '' ) ) {
			$row['event_name'] = $event_name;
		}
	}
	unset( $row );

	$teams = static function ( $g ) {
		$t = array( sanitize_title( (string) ( $g['away'] ?? '' ) ), sanitize_title( (string) ( $g['home'] ?? '' ) ) );
		sort( $t );
		return implode( '|', $t );
	};
	$score = static function ( $g ) {
		$a = (string) ( $g['away_score'] ?? '' );
		$b = (string) ( $g['home_score'] ?? '' );
		if ( '' === $a || '' === $b ) {
			return '';
		}
		$s = array( sanitize_title( (string) ( $g['away'] ?? '' ) ) . ':' . (int) $a, sanitize_title( (string) ( $g['home'] ?? '' ) ) . ':' . (int) $b );
		sort( $s );
		return implode( '|', $s );
	};
	$pairs = array();
	foreach ( $export as $g ) {
		$k           = (string) ( $g['date'] ?? '' ) . '|' . $teams( $g );
		$pairs[ $k ] = ( $pairs[ $k ] ?? 0 ) + 1;
	}
	$tiers = array(
		static function ( $row, $g ) use ( $teams ) {
			return ( $row['date'] ?? '' ) === ( $g['date'] ?? '' ) && swat_child_export_norm_time( $row['time'] ?? '' ) === swat_child_export_norm_time( $g['time'] ?? '' ) && $teams( $row ) === $teams( $g );
		},
		static function ( $row, $g ) use ( $teams, $score ) {
			return ( $row['date'] ?? '' ) === ( $g['date'] ?? '' ) && '' !== $score( $row ) && $score( $row ) === $score( $g );
		},
		static function ( $row, $g ) use ( $teams, $pairs ) {
			$k = (string) ( $g['date'] ?? '' ) . '|' . $teams( $g );
			// Date/teams alone is metadata evidence for finished rows only.
			$status = strtoupper( trim( str_replace( array( '_', '-' ), ' ', (string) ( $row['status'] ?? '' ) ) ) );
			return in_array( $status, array( 'FINAL', 'COMPLETE', 'COMPLETED' ), true ) && 1 === ( $pairs[ $k ] ?? 0 ) && ( $row['date'] ?? '' ) === ( $g['date'] ?? '' ) && $teams( $row ) === $teams( $g );
		},
	);

	$enriched  = array();
	$remaining = $display;
	foreach ( $export as $g ) {
		$id      = (string) ( $g['playbook_id'] ?? '' );
		$matches = array();
		if ( '' !== $id ) {
			foreach ( $remaining as $i => $row ) {
				if ( '' !== (string) ( $row['playbook_id'] ?? '' ) && (string) absint( $row['playbook_id'] ) === (string) absint( $id ) ) {
					$matches[] = $i;
				}
			}
		}
		if ( ! $matches ) {
			foreach ( $tiers as $tier ) {
				$found = array();
				foreach ( $display as $i => $row ) {
					$row_id = (string) ( $row['playbook_id'] ?? '' );
					if ( '' !== $row_id && '' !== $id ) {
						continue; // Both have ids and they differ: never the same game.
					}
					if ( $tier( $row, $g ) ) {
						$found[] = $i;
					}
				}
				if ( 1 === count( $found ) ) {
					$i = $found[0];
					// Check against the original export set so consumption order
					// cannot make a doubleheader appear unambiguous later.
					$reverse = 0;
					foreach ( $export as $candidate ) {
						$row_id = (string) ( $display[ $i ]['playbook_id'] ?? '' );
						$other_id = (string) ( $candidate['playbook_id'] ?? '' );
						if ( '' !== $row_id && '' !== $other_id && $row_id !== $other_id ) { continue; }
						if ( $tier( $display[ $i ], $candidate ) ) { ++$reverse; }
					}
					if ( 1 === $reverse && isset( $remaining[ $i ] ) ) { $matches = $found; }
					break;
				}
				if ( $found ) {
					break; // Ambiguous: do not guess.
				}
			}
		}
		if ( 1 === count( $matches ) ) {
			$i = $matches[0];
			$g = array_merge( $remaining[ $i ], $g );
			if ( 'final' === ( $g['status'] ?? '' ) ) {
				unset( $remaining[ $i ] );
			}
		}
		if ( empty( $g['id'] ) ) {
			$g['id'] = 800000000 + (int) $g['playbook_id'];
		}
		$enriched[] = $g;
	}
	return swat_child_export_merge( array_values( $remaining ), $enriched );
}

/* ---------- Upload in Settings → SWAT Team Profiles ---------- */

/**
 * Handle an uploaded export (called from the settings page).
 *
 * @return string Notice text ('' when nothing was uploaded).
 */
function swat_child_export_handle_upload() {
	if ( empty( $_FILES['swat_child_export_csv']['tmp_name'] ) || ! check_admin_referer( 'swat_child_pb_save' ) || ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$file = $_FILES['swat_child_export_csv']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as CSV, every field sanitized below.
	if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) || $file['size'] > 5 * MB_IN_BYTES ) {
		return 'Upload failed. Choose the .csv file exported from Playbook (under 5 MB).';
	}
	$parsed = swat_child_export_parse_csv( (string) file_get_contents( $file['tmp_name'] ) );
	if ( ! $parsed['games'] ) {
		return 'No games imported. ' . implode( ' ', array_slice( $parsed['errors'], 0, 3 ) );
	}
	$clean = array();
	foreach ( $parsed['games'] as $g ) {
		$clean[] = array_map( 'sanitize_text_field', $g );
	}
	$stored = get_option( 'swat_child_export_seasons', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$stored = swat_child_export_update_seasons( $stored, $clean, current_time( 'mysql' ), sanitize_file_name( (string) $file['name'] ) );
	$labels = array();
	foreach ( swat_child_export_by_season( $clean ) as $key => $games ) {
		$labels[] = swat_child_season_for( $games[0]['date'] )['label'] . ' (' . count( $games ) . ' uploaded games)';
	}

	update_option( 'swat_child_export_seasons', $stored, false );
	if ( function_exists( 'swat_child_source_invalidate_upload' ) ) { swat_child_source_invalidate_upload( $clean ); }
	delete_transient( 'swat_child_team_link_index_v1' ); // 1.0.29: team links follow the new results.
	return 'Imported ' . implode( ', ', $labels ) . '.' . ( $parsed['errors'] ? ' ' . count( $parsed['errors'] ) . ' line(s) skipped.' : '' );
}

/**
 * Settings section (rendered inside the SWAT Team Profiles page).
 *
 * @return void
 */
function swat_child_export_admin_section() {
	$seasons = swat_child_export_by_season( swat_child_export_games() );
	$stored  = get_option( 'swat_child_export_seasons', array() );
	?>
	<h2>Playbook season results</h2>
	<p>Upload Playbook's season games export (.csv). Finished games and team records for every event in the file come from it. Upload a new export after each weekend to keep records current; matching game IDs are updated and games absent from a partial file are preserved. To remove an erroneous game, correct the source export; omission alone does not delete it.</p>
	<?php if ( $seasons ) : ?>
		<ul>
			<?php foreach ( $seasons as $key => $games ) : ?>
				<li><?php echo esc_html( swat_child_season_for( $games[0]['date'] )['label'] . ': ' . count( $games ) . ' games, through ' . max( array_column( $games, 'date' ) ) . ( isset( $stored[ $key ]['uploaded'] ) ? ' (uploaded ' . $stored[ $key ]['uploaded'] . ')' : ' (included with the theme)' ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'swat_child_pb_save' ); ?>
		<input type="file" name="swat_child_export_csv" accept=".csv,text/csv" required>
		<?php submit_button( 'Upload season export', 'secondary', 'submit', false ); ?>
	</form>
	<?php
}
