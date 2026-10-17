<?php
/**
 * Homepage data model (child 1.0.19).
 *
 * Builds the This Weekend / Upcoming / Previous sections and decides every
 * homepage action from real event data. Registration status and schedule
 * availability are kept as separate facts:
 *
 *  - A published schedule (at least one synced game or bracket fixture) gives
 *    VIEW SCHEDULE and removes homepage registration actions and badges.
 *  - REGISTER appears only for a future event that is accepting entries
 *    (resolved status Open/Limited) and has no published schedule.
 *  - An event that has started is never shown as open for registration.
 *  - Completed events offer RESULTS (when games exist) and EVENT DETAILS.
 *
 * Reads only. Nothing here writes to Playbook, swat_game posts or options.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise a directory / feed row.
 *
 * @param array $row Row from swat_child_event_directory() or loaded_event.
 * @return array
 */
function swat_child_home_normalize_event( $row ) {
	$name  = (string) ( $row['event_name'] ?? $row['name'] ?? '' );
	$start = (string) ( $row['start_date'] ?? $row['start'] ?? '' );
	$end   = (string) ( $row['end_date'] ?? $row['end'] ?? $start );
	return array(
		'name'       => $name,
		'slug'       => sanitize_title( (string) ( $row['slug'] ?? $name ) ),
		'start'      => $start,
		'end'        => $end ? $end : $start,
		'game_count' => absint( $row['game_count'] ?? 0 ),
		'divisions'  => is_array( $row['divisions'] ?? null ) ? $row['divisions'] : array(),
	);
}

/**
 * Split "Friday Night Lights 10/9 - 8U & 12U" into a title and a division label.
 *
 * @param string $name Event name.
 * @return array{0:string,1:string}
 */
function swat_child_home_split_title( $name ) {
	$name = (string) $name;
	if ( preg_match( '/^(.+?)\s+\d{1,2}\/\d{1,2}(?:\s*-\s*|\s+)(.+)$/', $name, $parts ) ) {
		return array( trim( $parts[1] ), trim( $parts[2] ) );
	}
	return array( $name, '' );
}

/**
 * Human date range: "Fri, Oct 9", "Oct 10–11", "Oct 31 – Nov 1".
 *
 * @param string $start Y-m-d.
 * @param string $end   Y-m-d.
 * @param bool   $weekday Prefix single days with the weekday.
 * @return string
 */
function swat_child_home_date_range( $start, $end = '', $weekday = false ) {
	$tz = wp_timezone();
	$s  = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $start, $tz );
	if ( ! $s ) {
		return '';
	}
	$e = $end ? DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $end, $tz ) : false;
	if ( ! $e || $e->format( 'Y-m-d' ) === $s->format( 'Y-m-d' ) ) {
		return $s->format( $weekday ? 'D, M j' : 'M j' );
	}
	if ( $s->format( 'Y-m' ) === $e->format( 'Y-m' ) ) {
		return ( $weekday ? $s->format( 'D' ) . '–' . $e->format( 'D' ) . ', ' : '' ) . $s->format( 'M j' ) . '–' . $e->format( 'j' );
	}
	return $s->format( 'M j' ) . ' – ' . $e->format( 'M j' );
}

/**
 * Pure action rule. No WordPress calls, so it is unit tested directly.
 *
 * @param array $in {
 *     @type bool   $has_schedule Published games or bracket fixtures exist.
 *     @type bool   $started      Event start date is today or earlier.
 *     @type bool   $complete     Event end date is before today.
 *     @type string $reg_status   Resolved registration status slug.
 * }
 * @return array{actions:string[],badge:bool,register_open:bool}
 *         actions in display order; first is primary.
 */
function swat_child_home_action_rule( $in ) {
	$has_schedule = ! empty( $in['has_schedule'] );
	$started      = ! empty( $in['started'] );
	$complete     = ! empty( $in['complete'] );
	$status       = (string) ( $in['reg_status'] ?? '' );
	$cancelled    = 'cancelled' === $status;

	if ( $cancelled ) {
		return array( 'actions' => array( 'details' ), 'badge' => true, 'register_open' => false );
	}
	if ( $complete ) {
		return array( 'actions' => $has_schedule ? array( 'results', 'details' ) : array( 'details' ), 'badge' => false, 'register_open' => false );
	}
	if ( $has_schedule ) {
		// Schedule published: registration is no longer a homepage action or badge.
		return array( 'actions' => array( 'schedule', 'details' ), 'badge' => false, 'register_open' => false );
	}
	$register_open = ! $started && in_array( $status, array( 'open', 'limited' ), true );
	if ( $register_open ) {
		return array( 'actions' => array( 'register', 'details' ), 'badge' => true, 'register_open' => true );
	}
	// Not accepting entries and no schedule yet: show the truthful status, never OPEN.
	return array( 'actions' => array( 'details' ), 'badge' => ! $started, 'register_open' => false );
}

/**
 * Full card model for one event.
 *
 * @param array  $row   Normalised event row.
 * @param string $today Y-m-d (site timezone).
 * @return array
 */
function swat_child_home_event_model( $row, $today ) {
	$games = function_exists( 'swat_os_path_event_games' ) ? swat_os_path_event_games( $row['name'] ) : array();
	$start = (string) $row['start'];
	$end   = (string) $row['end'];
	foreach ( $games as $game ) {
		$date = (string) ( $game['date'] ?? '' );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			if ( ! $start || $date < $start ) {
				$start = $date;
			}
			if ( ! $end || $date > $end ) {
				$end = $date;
			}
		}
	}
	$count        = $games ? count( $games ) : absint( $row['game_count'] );
	$has_schedule = $count > 0;
	$started      = '' !== $start && $start <= $today;
	$complete     = '' !== $end && $end < $today;
	$reg_state    = function_exists( 'swat_child_registration_state' ) ? swat_child_registration_state( $row ) : array( 'status' => 'open', 'label' => 'Open', 'can_register' => true, 'deadline_label' => '' );
	$rule         = swat_child_home_action_rule(
		array(
			'has_schedule' => $has_schedule,
			'started'      => $started,
			'complete'     => $complete,
			'reg_status'   => (string) $reg_state['status'],
		)
	);

	list( $title, $division_label ) = swat_child_home_split_title( $row['name'] );
	if ( '' === $division_label && $row['divisions'] ) {
		$labels = array();
		foreach ( $row['divisions'] as $division ) {
			$labels[] = preg_replace( '/\s+Open$/i', '', (string) $division );
		}
		natsort( $labels );
		$division_label = implode( ' · ', array_unique( $labels ) );
	}

	$detail_url   = home_url( '/tournaments/' . $row['slug'] . '/' );
	$gameday_url  = function_exists( 'swat_os_gameday_url' ) ? swat_os_gameday_url( array( 'event' => $row['slug'] ) ) : add_query_arg( 'event', $row['slug'], home_url( '/gameday/' ) );
	$register_url = function_exists( 'swat_os_registration_url' ) ? swat_os_registration_url( $row ) : 'https://play.swatourneys.com/';

	$defs = array(
		'schedule' => array( 'label' => __( 'VIEW SCHEDULE', 'swat-tournament-os-child' ), 'url' => add_query_arg( 'view', 'schedule', $gameday_url ) ),
		'register' => array( 'label' => __( 'REGISTER', 'swat-tournament-os-child' ), 'url' => $register_url ),
		'results'  => array( 'label' => __( 'RESULTS', 'swat-tournament-os-child' ), 'url' => add_query_arg( 'view', 'scores', $gameday_url ) ),
		'details'  => array( 'label' => __( 'EVENT DETAILS', 'swat-tournament-os-child' ), 'url' => $detail_url ),
	);
	$actions = array();
	foreach ( $rule['actions'] as $index => $key ) {
		$actions[] = array_merge(
			$defs[ $key ],
			array(
				'key'     => $key,
				'primary' => 0 === $index,
				/* translators: 1: action label, 2: event name. */
				'aria'    => sprintf( __( '%1$s: %2$s', 'swat-tournament-os-child' ), ucfirst( strtolower( $defs[ $key ]['label'] ) ), $row['name'] ),
			)
		);
	}

	if ( 'cancelled' === $reg_state['status'] ) {
		$note = __( 'This event has been cancelled.', 'swat-tournament-os-child' );
	} elseif ( $complete ) {
		$note = $has_schedule ? __( 'Final results available', 'swat-tournament-os-child' ) : '';
	} elseif ( $has_schedule ) {
		/* translators: %d: number of games. */
		$note = sprintf( _n( 'Schedule posted · %d game', 'Schedule posted · %d games', $count, 'swat-tournament-os-child' ), $count );
	} elseif ( $started || $start <= gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) ) ) {
		$note = function_exists( 'swat_child_schedule_release' ) ? swat_child_schedule_release( $row ) : '';
	} else {
		$note = '';
	}

	return array(
		'name'           => $row['name'],
		'slug'           => $row['slug'],
		'title'          => $title,
		'divisions'      => $division_label,
		'start'          => $start,
		'end'            => $end,
		'dates'          => swat_child_home_date_range( $start, $end, true ),
		'game_count'     => $count,
		'has_schedule'   => $has_schedule,
		'is_live'        => $started && ! $complete,
		'complete'       => $complete,
		'reg_state'      => $reg_state,
		'show_badge'     => $rule['badge'],
		'register_open'  => $rule['register_open'],
		'deadline_label' => $rule['register_open'] ? (string) ( $reg_state['deadline_label'] ?? '' ) : '',
		'note'           => $note,
		'actions'        => $actions,
		'detail_url'     => $detail_url,
		'gameday_url'    => $gameday_url,
	);
}

/**
 * Homepage sections from the event directory.
 *
 * @return array{weekend:array,weekend_label:string,upcoming:array,previous:array,any_pending:bool}
 */
function swat_child_home_sections() {
	$today    = current_time( 'Y-m-d' );
	$rows     = function_exists( 'swat_child_event_directory' ) && function_exists( 'swat_os_event_directory' ) ? swat_child_event_directory() : array();
	$platform = function_exists( 'swat_os_public_payload' ) ? swat_os_public_payload() : array();
	$loaded   = isset( $platform['loaded_event'] ) && is_array( $platform['loaded_event'] ) ? $platform['loaded_event'] : array();
	if ( ! empty( $loaded['name'] ) ) {
		$rows[] = $loaded;
	}

	$future = array();
	$past   = array();
	foreach ( $rows as $raw ) {
		$row = swat_child_home_normalize_event( $raw );
		if ( ! $row['name'] || ! swat_child_valid_date( $row['start'] ) ) {
			continue;
		}
		$key = $row['slug'] . '|' . $row['start'];
		if ( $row['end'] < $today ) {
			$past[ $key ] = $past[ $key ] ?? $row;
		} else {
			// Prefer the directory row (it carries game_count and divisions).
			$future[ $key ] = $future[ $key ] ?? $row;
		}
	}
	$future = array_values( $future );
	usort( $future, static function ( $a, $b ) { return strcmp( $a['start'], $b['start'] ) ?: strcasecmp( $a['name'], $b['name'] ); } );
	$past = array_values( $past );
	usort( $past, static function ( $a, $b ) { return strcmp( $b['end'], $a['end'] ) ?: strcmp( $b['start'], $a['start'] ); } );

	// "This weekend" = the Fri–Sun window of the next event, or the current window.
	$weekend       = array();
	$weekend_label = '';
	if ( $future ) {
		$first = DateTimeImmutable::createFromFormat( '!Y-m-d', max( $future[0]['start'], $today ), wp_timezone() );
		if ( $first ) {
			$dow    = (int) $first->format( 'N' );
			$friday = $dow >= 5 ? $first->modify( '-' . ( $dow - 5 ) . ' days' ) : $first->modify( '+' . ( 5 - $dow ) . ' days' );
			$w_start = $friday->format( 'Y-m-d' );
			$w_end   = $friday->modify( '+2 days' )->format( 'Y-m-d' );
			foreach ( $future as $row ) {
				if ( $row['start'] <= $w_end && $row['end'] >= $w_start ) {
					$weekend[] = $row;
				}
			}
			$weekend_label = swat_child_home_date_range( $w_start, $w_end );
		}
	}
	if ( ! $weekend && $future ) {
		$weekend = array_slice( $future, 0, 1 );
		$weekend_label = swat_child_home_date_range( $weekend[0]['start'], $weekend[0]['end'] );
	}
	$weekend_keys = array();
	foreach ( $weekend as $row ) {
		$weekend_keys[ $row['slug'] . '|' . $row['start'] ] = true;
	}
	$upcoming = array();
	foreach ( $future as $row ) {
		if ( ! isset( $weekend_keys[ $row['slug'] . '|' . $row['start'] ] ) ) {
			$upcoming[] = $row;
		}
	}

	$weekend_models = array_map( static function ( $row ) use ( $today ) { return swat_child_home_event_model( $row, $today ); }, $weekend );
	$any_pending    = false;
	foreach ( $weekend_models as $model ) {
		if ( ! $model['has_schedule'] && ! $model['complete'] && 'cancelled' !== $model['reg_state']['status'] ) {
			$any_pending = true;
		}
	}

	return array(
		'weekend'       => $weekend_models,
		'weekend_label' => $weekend_label,
		'upcoming'      => array_map( static function ( $row ) use ( $today ) { return swat_child_home_event_model( $row, $today ); }, array_slice( $upcoming, 0, 6 ) ),
		'upcoming_more' => max( 0, count( $upcoming ) - 6 ),
		'previous'      => array_map( static function ( $row ) use ( $today ) { return swat_child_home_event_model( $row, $today ); }, array_slice( $past, 0, 4 ) ),
		'any_pending'   => $any_pending,
	);
}

/**
 * Print one event action link.
 *
 * @param array $action Action from swat_child_home_event_model().
 * @return void
 */
function swat_child_home_action_link( $action ) {
	$external = 'register' === $action['key'];
	printf(
		'<a class="swh-btn %1$s" href="%2$s" aria-label="%3$s"%4$s>%5$s</a>',
		esc_attr( $action['primary'] ? 'swh-btn--primary' : 'swh-btn--secondary' ),
		esc_url( $action['url'] ),
		esc_attr( $action['aria'] ),
		$external ? ' rel="noopener"' : '',
		esc_html( $action['label'] )
	);
}

/**
 * Homepage assets: one scoped stylesheet and one script replace the former
 * homepage layers (front-page-v2 rules are scoped to .front-page-v2, which the
 * new markup no longer uses, so that sheet is not loaded on the homepage).
 *
 * @return void
 */
function swat_child_home_assets() {
	if ( ! is_front_page() ) {
		return;
	}
	wp_dequeue_style( 'swat-front-page-v2' );
	wp_dequeue_script( 'swat-front-page-weekend' );
	$css = SWAT_CHILD_DIR . '/assets/css/home.css';
	$js  = SWAT_CHILD_DIR . '/assets/js/home.js';
	$deps = array();
	foreach ( array( 'swat-os', 'swat-flagship', 'swat-mobile-nav-refine', 'swat-child-fonts', 'swat-child' ) as $handle ) {
		if ( wp_style_is( $handle, 'enqueued' ) ) {
			$deps[] = $handle;
		}
	}
	wp_enqueue_style( 'swat-child-home', SWAT_CHILD_URI . '/assets/css/home.css', $deps, (string) filemtime( $css ) );
	wp_enqueue_script( 'swat-child-home', SWAT_CHILD_URI . '/assets/js/home.js', array(), (string) filemtime( $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'swat_child_home_assets', 210 );
