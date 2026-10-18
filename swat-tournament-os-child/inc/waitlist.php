<?php
/**
 * 1.0.31: Waitlist links for sold-out / closed events, and readable event titles
 * for the tournament directory.
 *
 * Waitlist URLs live in their own option (swat_child_waitlist) so registration
 * records keep their existing shape:
 *   '_default'          => URL used for every event without its own link
 *   'event-slug|Y-m-d'  => URL for one event (same key as swat_child_registration)
 * With nothing saved, visitors go to the Contact page with the event named.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SWAT_CHILD_WAITLIST_OPTION' ) ) {
	define( 'SWAT_CHILD_WAITLIST_OPTION', 'swat_child_waitlist' );
}

/**
 * Statuses that offer a waitlist instead of registration.
 *
 * @param string $status Registration status slug.
 * @return bool
 */
function swat_child_waitlist_applies( $status ) {
	return in_array( (string) $status, array( 'sold_out', 'closed' ), true );
}

/**
 * Accept only absolute http(s) URLs.
 *
 * @param mixed $url Candidate.
 * @return string Clean URL or ''.
 */
function swat_child_sanitize_waitlist_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || ! preg_match( '#^https?://[^\s/$.?\#][^\s]*$#i', $url ) ) {
		return '';
	}
	return function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $url, array( 'http', 'https' ) ) : $url;
}

/**
 * Stored waitlist links (key => URL).
 *
 * @return array<string,string>
 */
function swat_child_waitlist_links() {
	$links = get_option( SWAT_CHILD_WAITLIST_OPTION, array() );
	return is_array( $links ) ? $links : array();
}

/**
 * Waitlist URL for an event: its own link, else the default link, else Contact.
 *
 * @param string $key  Event key (slug|start).
 * @param string $name Event name, passed to the Contact fallback.
 * @return string
 */
function swat_child_waitlist_url( $key, $name = '' ) {
	$links = swat_child_waitlist_links();
	foreach ( array( (string) $key, '_default' ) as $candidate ) {
		$url = '' === $candidate ? '' : swat_child_sanitize_waitlist_url( $links[ $candidate ] ?? '' );
		if ( '' !== $url ) {
			return $url;
		}
	}
	$args = array( 'subject' => 'waitlist' );
	if ( '' !== (string) $name ) {
		$args['event'] = (string) $name;
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), home_url( '/contact/' ) );
}

/**
 * Directory-card title: drops the date and age-group list that the card
 * already shows in its date tile and division line.
 *   "FRIDAY NIGHT LIGHTS 10/16 - 9U & 11U" -> "Friday Night Lights"
 *   "MLB Player Series"                    -> unchanged
 *
 * @param string $name Event name as synced from Playbook.
 * @return string
 */
function swat_child_clean_event_title( $name ) {
	$original = trim( preg_replace( '/\s+/', ' ', (string) $name ) );
	$title    = $original;
	$age      = '\d{1,2}\s?U';
	// Trailing age-group list: " - 9U & 11U", " – 8U, 10U and 12U", " (7U-11U)".
	$title = preg_replace( '/\s*[-–—:|(]?\s*' . $age . '(?:\s*(?:&|and|,|\/|-|–)\s*' . $age . ')*\s*\)?\s*$/iu', '', $title );
	// Calendar dates written into the name: "10/16", "11/7", "10/16/26".
	$title = preg_replace( '/\s*\b\d{1,2}\/\d{1,2}(?:\/\d{2,4})?\b/u', '', $title );
	$title = trim( preg_replace( '/\s+/', ' ', $title ), " \t-–—:|" );
	if ( '' === $title ) {
		return $original;
	}
	// Names entered in capitals read as shouting; title-case them but keep short acronyms (MLB, UDC, SWAT).
	if ( strtoupper( $title ) === $title && preg_match( '/[A-Z]{2}/', $title ) ) {
		$title = preg_replace_callback(
			'/[A-Za-z][A-Za-z\']*/',
			static function ( $m ) {
				$word = $m[0];
				return in_array( $word, array( 'MLB', 'UDC', 'SWAT', 'USSSA', 'NC', 'USA' ), true ) ? $word : ucfirst( strtolower( $word ) );
			},
			$title
		);
	}
	return $title;
}

/**
 * Age groups written at the end of an event name: "… 11/6 - 9U & 10U" -> "9U & 10U".
 *
 * @param string $name Event name.
 * @return string '' when the name carries no trailing age list.
 */
function swat_child_event_title_ages( $name ) {
	$age = '\d{1,2}\s?U';
	if ( ! preg_match( '/(' . $age . '(?:\s*(?:&|and|,|\/|-|–)\s*' . $age . ')*)\s*\)?\s*$/iu', trim( (string) $name ), $m ) ) {
		return '';
	}
	preg_match_all( '/\d{1,2}/', $m[1], $ages );
	$ages = array_map( static fn( $a ) => $a . 'U', $ages[0] );
	if ( count( $ages ) < 2 ) {
		return implode( '', $ages );
	}
	return implode( ', ', array_slice( $ages, 0, -1 ) ) . ' & ' . end( $ages );
}

/**
 * Directory titles for a list of events, keyed by list index. Cleaned titles that
 * would repeat ("Friday Night Lights" three times) keep their age groups:
 * "Friday Night Lights · 9U & 10U".
 *
 * @param array $events Event rows with event_name (or name).
 * @return array<int|string,string>
 */
function swat_child_directory_titles( $events ) {
	$clean = array();
	foreach ( (array) $events as $i => $event ) {
		$clean[ $i ] = swat_child_clean_event_title( (string) ( $event['event_name'] ?? $event['name'] ?? '' ) );
	}
	$counts = array_count_values( array_map( 'strtolower', $clean ) );
	foreach ( $clean as $i => $title ) {
		if ( $counts[ strtolower( $title ) ] > 1 ) {
			$ages = swat_child_event_title_ages( (string) ( $events[ $i ]['event_name'] ?? $events[ $i ]['name'] ?? '' ) );
			if ( '' !== $ages ) {
				$clean[ $i ] = $title . ' · ' . $ages;
			}
		}
	}
	return $clean;
}
