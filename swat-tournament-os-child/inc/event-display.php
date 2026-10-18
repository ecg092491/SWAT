<?php
/**
 * Public event display: include every upcoming feed event and use plain language.
 * Reads the existing feed; does not change imports, game data or synchronization.
 */
defined( 'ABSPATH' ) || exit;

function swat_child_complete_event_directory( $rows ) {
    if ( ! is_array( $rows ) || ! function_exists( 'swat_os_playbook_event_feed' ) ) {
        return $rows;
    }
    $today = current_time( 'Y-m-d' );
    $known = array();
    foreach ( $rows as $row ) {
        $known[ sanitize_title( (string) ( $row['slug'] ?? $row['event_name'] ?? '' ) ) ] = true;
    }
    foreach ( (array) swat_os_playbook_event_feed() as $event ) {
        if ( ! is_array( $event ) ) { continue; }
        $name = sanitize_text_field( (string) ( $event['EventName'] ?? '' ) );
        $start = (string) ( $event['StartDate'] ?? '' );
        $end = (string) ( $event['EndDate'] ?? $start );
        $status = strtoupper( (string) ( $event['Status'] ?? '' ) );
        if ( ! $name || ! swat_child_valid_date( $start ) || in_array( $status, array( 'CANCELLED', 'CANCELED' ), true ) ) { continue; }
        $end = swat_child_valid_date( $end ) ? $end : $start;
        if ( $end < $today ) { continue; }
        $slug = sanitize_title( $name );
        if ( isset( $known[ $slug ] ) ) { continue; }
        $divisions = array();
        foreach ( (array) ( $event['Divisions'] ?? array() ) as $division ) {
            if ( is_array( $division ) && ! empty( $division['Age'] ) ) {
                $divisions[] = trim( $division['Age'] . ' ' . ( $division['Subdivison'] ?? '' ) );
            }
        }
        $rows[] = array(
            'event_name' => $name, 'slug' => $slug,
            'start_date' => $start, 'end_date' => $end, 'game_count' => 0,
            'divisions' => array_values( array_unique( $divisions ) ),
            'fields' => array(), 'source' => 'upcoming-feed',
        );
        $known[ $slug ] = true;
    }
    usort( $rows, static function ( $a, $b ) use ( $today ) {
        $a_start = (string) ( $a['start_date'] ?? '' );
        $b_start = (string) ( $b['start_date'] ?? '' );
        $a_future = (string) ( $a['end_date'] ?? $a_start ) >= $today;
        $b_future = (string) ( $b['end_date'] ?? $b_start ) >= $today;
        if ( $a_future !== $b_future ) { return $a_future ? -1 : 1; }
        $order = $a_future ? strcmp( $a_start, $b_start ) : strcmp( $b_start, $a_start );
        return $order ?: strcasecmp( (string) ( $a['event_name'] ?? '' ), (string) ( $b['event_name'] ?? '' ) );
    } );
    return function_exists( 'swat_child_source_directory' ) ? swat_child_source_directory( $rows ) : $rows;
}
// Expand both existing cache reads and future writes, so event lookup and
// the Game Day switcher use the same complete directory.
add_filter( 'transient_swat_os_event_directory', 'swat_child_complete_event_directory' );
add_filter( 'pre_set_transient_swat_os_event_directory', 'swat_child_complete_event_directory' );

function swat_child_event_directory() {
    return swat_child_complete_event_directory( swat_os_event_directory() );
}

/**
 * Honor the event explorer's hidden state despite card display rules.
 */
function swat_child_event_filter_visibility() {
    if ( ! wp_style_is( 'swat-event-page', 'enqueued' ) ) {
        return;
    }
    wp_add_inline_style(
        'swat-event-page',
        '[data-swat-event-explorer] [hidden]{display:none!important}'
    );
}
add_action( 'wp_enqueue_scripts', 'swat_child_event_filter_visibility', 200 );

function swat_child_schedule_release( $event ) {
    $message = swat_os_schedule_release( $event );
    return str_replace(
        'Schedule awaiting publication. Check Playbook for the latest update.',
        'Schedule coming soon. Updates will appear here once posted.',
        $message
    );
}

/**
 * Every synchronized event keyed by slug (1.0.20).
 *
 * The parent's event directory only holds the 12 newest synchronized events,
 * so older event pages (and team-profile links to them) returned "Event not
 * found". This is the same aggregate without the limit, used only to resolve a
 * single event page. Listings (/tournaments/, homepage) are unchanged.
 *
 * @return array<string,array>
 */
function swat_child_all_synced_events() {
	$cached = get_transient( 'swat_child_all_events_v1' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT event_meta.meta_value AS event_name,
			MIN(date_meta.meta_value) AS start_date,
			MAX(date_meta.meta_value) AS end_date,
			COUNT(DISTINCT posts.ID) AS game_count
		FROM {$wpdb->posts} posts
		INNER JOIN {$wpdb->postmeta} event_meta ON event_meta.post_id = posts.ID AND event_meta.meta_key = '_swat_game_event'
		INNER JOIN {$wpdb->postmeta} date_meta ON date_meta.post_id = posts.ID AND date_meta.meta_key = '_swat_game_date'
		WHERE posts.post_type = 'swat_game' AND posts.post_status NOT IN ('trash','auto-draft') AND event_meta.meta_value <> ''
		GROUP BY event_meta.meta_value",
		ARRAY_A
	);
	$events = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$slug = sanitize_title( (string) $row['event_name'] );
		if ( '' === $slug ) {
			continue;
		}
		$events[ $slug ] = array(
			'event_name' => (string) $row['event_name'],
			'slug'       => $slug,
			'start_date' => (string) $row['start_date'],
			'end_date'   => (string) $row['end_date'],
			'game_count' => (string) $row['game_count'],
			'divisions'  => array(),
			'fields'     => array(),
		);
	}
	set_transient( 'swat_child_all_events_v1', $events, 15 * MINUTE_IN_SECONDS );
	return $events;
}

/**
 * Event lookup for single event pages: the parent directory first, then any
 * older synchronized event (1.0.20).
 *
 * @param string $slug Event slug.
 * @return array
 */
function swat_child_event_by_slug( $slug ) {
	$slug  = sanitize_title( (string) $slug );
	$event = function_exists( 'swat_os_event_by_slug' ) ? swat_os_event_by_slug( $slug ) : array();
	if ( $event || '' === $slug || ! function_exists( 'swat_os_event_games' ) ) {
		return $event;
	}
	$all = swat_child_all_synced_events();
	if ( empty( $all[ $slug ] ) ) {
		// 1.0.22: events only in the Playbook season export.
		$export = function_exists( 'swat_child_export_events' ) ? swat_child_export_events() : array();
		return $export[ $slug ] ?? array();
	}
	$event     = $all[ $slug ];
	$divisions = array();
	$fields    = array();
	foreach ( swat_os_event_games( $event['event_name'] ) as $game ) {
		if ( ! empty( $game['division'] ) ) {
			$divisions[ (string) $game['division'] ] = true;
		}
		if ( ! empty( $game['field'] ) ) {
			$fields[ (string) $game['field'] ] = true;
		}
	}
	$event['divisions'] = array_keys( $divisions );
	$event['fields']    = array_keys( $fields );
	sort( $event['divisions'] );
	sort( $event['fields'] );
	return $event;
}
