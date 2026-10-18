<?php
/**
 * Game Day Hub presentation layer (child 1.0.17).
 *
 * Game Day keeps every parent route, REST endpoint, script, data source and
 * template hook. Only the stylesheet stack changes on Game Day requests:
 *
 * - The parent's Game Day-only stylesheets and this child's former
 *   swat-field-ux.css are not printed (their rules fought each other with
 *   hundreds of !important declarations).
 * - swat-flagship.css is swapped, in place, for assets/css/gameday-site-chrome.css,
 *   a generated copy with only the Game Day component selectors removed, so the
 *   site header and menu look exactly as they do on other pages.
 * - assets/css/swat-gameday-hub.css styles every Game Day component.
 *
 * Other pages are untouched. Rollback: reinstall child 1.0.16.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stylesheet handles replaced by the hub on Game Day requests.
 *
 * @return string[]
 */
function swat_child_hub_replaced_styles() {
	return array( 'swat-gameday', 'swat-my-weekend', 'swat-gameday-premium', 'swat-team-path', 'swat-gameday-mobile', 'swat-field-ux', 'swat-flagship' );
}

/**
 * Whether the current request renders Game Day.
 *
 * @return bool
 */
function swat_child_hub_active() {
	return function_exists( 'swat_os_is_gameday_request' ) && swat_os_is_gameday_request();
}

/**
 * Suppress replaced stylesheets on Game Day. Filtering the tag (rather than
 * dequeueing) is reliable even when another handle lists them as a dependency.
 *
 * @param string $tag    Link tag.
 * @param string $handle Handle.
 * @return string
 */
function swat_child_hub_filter_style_tag( $tag, $handle ) {
	if ( ! in_array( $handle, swat_child_hub_replaced_styles(), true ) || ! swat_child_hub_active() ) {
		return $tag;
	}
	if ( 'swat-flagship' === $handle ) {
		// Print the generated site chrome exactly where flagship would print, so cascade order is unchanged.
		$chrome = SWAT_CHILD_DIR . '/assets/css/gameday-site-chrome.css';
		$href   = SWAT_CHILD_URI . '/assets/css/gameday-site-chrome.css?ver=' . ( is_readable( $chrome ) ? (string) filemtime( $chrome ) : SWAT_CHILD_VERSION );
		return "<link rel='stylesheet' id='swat-gameday-site-chrome-css' href='" . esc_url( $href ) . "' media='all' />\n";
	}
	return '';
}
add_filter( 'style_loader_tag', 'swat_child_hub_filter_style_tag', 10, 2 );

/**
 * Enqueue the site chrome and hub stylesheets after every other Game Day sheet.
 *
 * @return void
 */
function swat_child_hub_enqueue() {
	if ( ! swat_child_hub_active() ) {
		return;
	}
	wp_enqueue_script( 'swat-gameday-model', SWAT_CHILD_URI . '/assets/js/swat-gameday-model.js', array(), SWAT_CHILD_VERSION, true );
	$scripts = wp_scripts();
	if ( isset( $scripts->registered['swat-gameday'] ) ) {
		$scripts->registered['swat-gameday']->deps[] = 'swat-gameday-model';
	}
	$deps = array();
	foreach ( array( 'swat-os', 'swat-mobile-nav-refine', 'swat-child-fonts', 'swat-child', 'swat-division-rules', 'swat-weekend-pitching' ) as $handle ) {
		if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}
	$deps[] = 'swat-shared-components';
	$hub = SWAT_CHILD_DIR . '/assets/css/swat-gameday-hub.css';
	wp_enqueue_style( 'swat-gameday-hub', SWAT_CHILD_URI . '/assets/css/swat-gameday-hub.css', $deps, is_readable( $hub ) ? (string) filemtime( $hub ) : SWAT_CHILD_VERSION );
	$compact = SWAT_CHILD_DIR . '/assets/css/swat-gameday-compact.css';
	wp_enqueue_style( 'swat-gameday-compact', SWAT_CHILD_URI . '/assets/css/swat-gameday-compact.css', array( 'swat-postseason' ), is_readable( $compact ) ? (string) filemtime( $compact ) : SWAT_CHILD_VERSION );
	$team_view = SWAT_CHILD_DIR . '/assets/css/team-schedule.css';
	wp_enqueue_style( 'swat-team-schedule', SWAT_CHILD_URI . '/assets/css/team-schedule.css', array( 'swat-gameday-hub' ), is_readable( $team_view ) ? (string) filemtime( $team_view ) : SWAT_CHILD_VERSION );
	// 1.0.30: Bracket & Standings view; loads after every other Game Day sheet (field-ux included).
	$postseason      = SWAT_CHILD_DIR . '/assets/css/postseason.css';
	$postseason_deps = array( 'swat-team-schedule' );
	if ( wp_style_is( 'swat-field-ux', 'enqueued' ) ) {
		$postseason_deps[] = 'swat-field-ux';
	}
	wp_enqueue_style( 'swat-postseason', SWAT_CHILD_URI . '/assets/css/postseason.css', $postseason_deps, is_readable( $postseason ) ? (string) filemtime( $postseason ) : SWAT_CHILD_VERSION );
}
add_action( 'wp_enqueue_scripts', 'swat_child_hub_enqueue', 120 );

/**
 * Body class used to scope the hub stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function swat_child_hub_body_class( $classes ) {
	if ( swat_child_hub_active() ) {
		$classes[] = 'swat-hub-ui';
	}
	return $classes;
}
add_filter( 'body_class', 'swat_child_hub_body_class' );

/**
 * Sponsor presentation hooks. Nothing renders until real sponsor data is
 * supplied through these filters.
 *
 * add_filter( 'swat_child_gameday_sponsor_slots', fn() => array( 'header' => array( 'name' => 'Acme', 'url' => 'https://…' ) ) );
 * add_filter( 'swat_child_gameday_field_sponsors', fn() => array( 'SW-1' => 'Acme' ) );
 *
 * @param array $event Current event.
 * @return array
 */
function swat_child_gameday_sponsor_slots( $event = array() ) {
	$clean = array();
	foreach ( (array) apply_filters( 'swat_child_gameday_sponsor_slots', array(), $event ) as $slot => $sponsor ) {
		if ( is_array( $sponsor ) && ! empty( $sponsor['name'] ) ) {
			$item = array(
				'name' => sanitize_text_field( (string) $sponsor['name'] ),
				'url'  => empty( $sponsor['url'] ) ? '' : esc_url_raw( (string) $sponsor['url'] ),
			);
			// 1.0.33 optional extras (SWAT Operations -> Sponsors, or code): tagline, logo URL, tracking id.
			if ( ! empty( $sponsor['tagline'] ) ) {
				$item['tagline'] = sanitize_text_field( (string) $sponsor['tagline'] );
			}
			if ( ! empty( $sponsor['logo'] ) && preg_match( '#^https?://#i', (string) $sponsor['logo'] ) ) {
				$item['logo'] = esc_url_raw( (string) $sponsor['logo'] );
			}
			if ( ! empty( $sponsor['id'] ) ) {
				$item['id'] = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $sponsor['id'] ) );
			}
			$clean[ sanitize_key( $slot ) ] = $item;
		}
	}
	return $clean;
}

/**
 * Field sponsor names keyed by field label.
 *
 * @param array $event Current event.
 * @return array
 */
function swat_child_gameday_field_sponsors( $event = array() ) {
	$clean = array();
	foreach ( (array) apply_filters( 'swat_child_gameday_field_sponsors', array(), $event ) as $field => $name ) {
		if ( '' !== trim( (string) $name ) ) {
			$clean[ sanitize_text_field( (string) $field ) ] = sanitize_text_field( (string) $name );
		}
	}
	return $clean;
}

/**
 * Sponsor strip markup for a slot (empty string when no sponsor is set).
 *
 * @param string $slot  Slot key.
 * @param array  $event Current event.
 * @return string
 */
function swat_child_gameday_sponsor_strip( $slot, $event = array() ) {
	$slots = swat_child_gameday_sponsor_slots( $event );
	if ( empty( $slots[ $slot ] ) ) {
		return '';
	}
	$sponsor = $slots[ $slot ];
	$name    = esc_html( $sponsor['name'] );
	$id      = (string) ( $sponsor['id'] ?? '' );
	$href    = $sponsor['url'];
	if ( $href && '' !== $id && function_exists( 'swat_child_sponsor_go_url' ) ) {
		$href = swat_child_sponsor_go_url( $id, $slot ); // Counts the click, then redirects to the saved link.
	}
	$label = $href ? '<a href="' . esc_url( $href ) . '" rel="sponsored noopener" target="_blank">' . $name . '</a>' : '<b>' . $name . '</b>';
	$text  = '<span>Presented by ' . $label . '</span>';
	$logo  = '';
	if ( ! empty( $sponsor['tagline'] ) || ! empty( $sponsor['logo'] ) ) {
		$text = '<span class="swat-sponsor-text">' . $text . ( empty( $sponsor['tagline'] ) ? '' : '<span class="swat-sponsor-tagline">' . esc_html( $sponsor['tagline'] ) . '</span>' ) . '</span>';
		$logo = empty( $sponsor['logo'] ) ? '' : '<span class="swat-sponsor-logo"><img src="' . esc_url( $sponsor['logo'] ) . '" alt="" loading="lazy" decoding="async"></span>';
	}
	$attrs = ' data-swat-sponsor="' . esc_attr( $slot ) . '"' . ( '' !== $id ? ' data-swat-sponsor-id="' . esc_attr( $id ) . '"' : '' );
	$class = 'swat-sponsor-strip' . ( $logo ? ' has-logo' : '' );
	return '<div class="' . $class . '"' . $attrs . '>' . $logo . $text . '<span class="swat-sponsor-tag">Sponsor</span></div>';
}

/**
 * Optional per-game bracket labels, keyed by Playbook game id (1.0.19).
 *
 * Game Day already names split finals automatically (Gold / Silver / Bronze by
 * Playbook game number when a division's bracket is only standalone title
 * games). Use this filter only if Playbook sets a bracket up differently:
 *
 * add_filter( 'swat_child_bracket_labels', fn( $labels ) => $labels + array(
 *     '7875' => array( 'bracket' => '8U Silver', 'round' => 'Championship' ),
 * ) );
 *
 * @param array $event Current event.
 * @return array<string,array{bracket?:string,round?:string}>
 */
function swat_child_bracket_labels( $event = array() ) {
	$clean = array();
	foreach ( (array) apply_filters( 'swat_child_bracket_labels', array(), $event ) as $playbook_id => $label ) {
		$playbook_id = preg_replace( '/\D/', '', (string) $playbook_id );
		if ( '' === $playbook_id || ! is_array( $label ) ) {
			continue;
		}
		$item = array();
		foreach ( array( 'bracket', 'round' ) as $key ) {
			if ( ! empty( $label[ $key ] ) ) {
				$item[ $key ] = sanitize_text_field( (string) $label[ $key ] );
			}
		}
		if ( $item ) {
			$clean[ $playbook_id ] = $item;
		}
	}
	return $clean;
}

/**
 * Expose field sponsors to the Game Day script (presentation only).
 *
 * @return void
 */
function swat_child_hub_localize() {
	if ( ! swat_child_hub_active() || ! wp_script_is( 'swat-gameday', 'enqueued' ) ) {
		return;
	}
	$event = function_exists( 'swat_os_current_event' ) ? swat_os_current_event() : array();
	$event_slug = sanitize_title( (string) ( $event['slug'] ?? '' ) );
	$pitching_enabled = function_exists( 'gdc_gameday_pitching_enabled' ) ? gdc_gameday_pitching_enabled( $event_slug ) : true;
	wp_add_inline_script( 'swat-gameday', 'window.SWATGameDayHub=' . wp_json_encode( array( 'fieldSponsors' => swat_child_gameday_field_sponsors( $event ), 'pitchingEnabled' => $pitching_enabled, 'bracketLabels' => swat_child_bracket_labels( $event ), 'teamsUrl' => home_url( '/teams/' ) ) ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'swat_child_hub_localize', 121 );

