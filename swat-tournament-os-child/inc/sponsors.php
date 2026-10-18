<?php
/**
 * Game Day sponsors (child 1.0.33).
 *
 * SWAT Operations -> Sponsors manages sponsor banners without code. Records feed
 * the existing presentation hooks in inc/gameday-hub.php
 * (swat_child_gameday_sponsor_slots and swat_child_gameday_field_sponsors) at
 * priority 5, so a sponsor set in code at the default priority still wins.
 *
 * Storage:
 * - swat_child_sponsors: one option holding every sponsor record.
 * - swat_spc_<id>_<YYYYMM>_<slot>_<v|c>: one integer option per counter
 *   (views / clicks), incremented atomically so concurrent game-day traffic
 *   does not lose counts. Not autoloaded.
 *
 * Tracking: clicks go through /?swat_sponsor_go=<id>&slot=<slot>, which counts
 * and redirects to the sponsor's own stored link (never a URL taken from the
 * request). Views are reported by assets/js/swat-sponsors.js once per sponsor
 * and slot per browser visit, only after the banner is actually on screen.
 *
 * Rollback: remove the require in functions.php. Saved sponsors and counters
 * stay in the database and render nothing.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

const SWAT_CHILD_SPONSOR_OPTION  = 'swat_child_sponsors';
const SWAT_CHILD_SPONSOR_COUNTER = 'swat_spc_';

/**
 * Capability required to manage sponsors.
 *
 * @return string
 */
function swat_child_sponsor_capability() {
	/**
	 * Filter the capability needed to manage Game Day sponsors.
	 *
	 * @param string $capability Default manage_options.
	 */
	return (string) apply_filters( 'swat_child_sponsor_capability', 'manage_options' );
}

/**
 * Banner placements on Game Day. Keys match the slots app.php renders.
 *
 * @return array<string,string>
 */
function swat_child_sponsor_slot_defs() {
	return array(
		'header'    => 'Top of Game Day',
		'schedule'  => 'Schedule',
		'scores'    => 'Scores',
		'standings' => 'Standings',
		'bracket'   => 'Bracket',
	);
}

/**
 * Accept only absolute http(s) links.
 *
 * @param mixed $url Raw URL.
 * @return string Clean URL or ''.
 */
function swat_child_sponsor_clean_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || preg_match( '/[\s<>"]/', $url ) ) {
		return '';
	}
	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
		return '';
	}
	return esc_url_raw( $url );
}

/**
 * Whether a string is a real Y-m-d date.
 *
 * @param string $date Date.
 * @return bool
 */
function swat_child_sponsor_valid_date( $date ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Split a comma/newline list into unique trimmed items.
 *
 * @param mixed $raw String or array.
 * @return string[]
 */
function swat_child_sponsor_list( $raw ) {
	$items = is_array( $raw ) ? $raw : preg_split( '/[,\n\r]+/', (string) $raw );
	$out   = array();
	foreach ( (array) $items as $item ) {
		$item = sanitize_text_field( (string) $item );
		if ( '' !== $item && ! in_array( $item, $out, true ) ) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Sanitise one sponsor record. Returns null when it has no usable id or name.
 *
 * @param mixed $raw Raw record.
 * @return array|null
 */
function swat_child_sponsor_sanitize( $raw ) {
	$raw  = is_array( $raw ) ? $raw : array();
	$id   = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $raw['id'] ?? '' ) ) );
	$name = trim( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ) );
	if ( '' === $id || strlen( $id ) > 16 || '' === $name ) {
		return null;
	}
	$slots = array_values( array_intersect( array_keys( swat_child_sponsor_slot_defs() ), array_map( 'sanitize_key', (array) ( $raw['slots'] ?? array() ) ) ) );
	$events = array();
	foreach ( (array) ( $raw['events'] ?? array() ) as $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' !== $slug && ! in_array( $slug, $events, true ) ) {
			$events[] = $slug;
		}
	}
	$start = trim( (string) ( $raw['start'] ?? '' ) );
	$end   = trim( (string) ( $raw['end'] ?? '' ) );
	$start = swat_child_sponsor_valid_date( $start ) ? $start : '';
	$end   = swat_child_sponsor_valid_date( $end ) ? $end : '';
	if ( '' !== $start && '' !== $end && $end < $start ) {
		$end = $start;
	}
	$tagline = trim( sanitize_text_field( (string) ( $raw['tagline'] ?? '' ) ) );
	if ( function_exists( 'mb_substr' ) ) {
		$tagline = mb_substr( $tagline, 0, 80 );
	} else {
		$tagline = substr( $tagline, 0, 80 );
	}
	return array(
		'id'      => $id,
		'name'    => function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 60 ) : substr( $name, 0, 60 ),
		'url'     => swat_child_sponsor_clean_url( $raw['url'] ?? '' ),
		'tagline' => $tagline,
		'logo_id' => max( 0, (int) ( $raw['logo_id'] ?? 0 ) ),
		'slots'   => $slots,
		'fields'  => swat_child_sponsor_list( $raw['fields'] ?? array() ),
		'events'  => $events,
		'start'   => $start,
		'end'     => $end,
		'active'  => ! empty( $raw['active'] ),
	);
}

/**
 * All saved sponsors, sanitised, keyed by id, in saved order.
 *
 * @return array<string,array>
 */
function swat_child_sponsor_records() {
	$out = array();
	foreach ( (array) get_option( SWAT_CHILD_SPONSOR_OPTION, array() ) as $raw ) {
		$record = swat_child_sponsor_sanitize( $raw );
		if ( $record ) {
			$out[ $record['id'] ] = $record;
		}
	}
	return $out;
}

/**
 * Today in the site's time zone.
 *
 * @return string Y-m-d.
 */
function swat_child_sponsor_today() {
	return function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
}

/**
 * Lifecycle label for the admin list (event targeting not considered).
 *
 * @param array  $record Sponsor.
 * @param string $today  Y-m-d.
 * @return string paused|scheduled|ended|live
 */
function swat_child_sponsor_state( $record, $today ) {
	if ( empty( $record['active'] ) ) {
		return 'paused';
	}
	if ( '' !== $record['start'] && $today < $record['start'] ) {
		return 'scheduled';
	}
	if ( '' !== $record['end'] && $today > $record['end'] ) {
		return 'ended';
	}
	return 'live';
}

/**
 * Whether a sponsor shows for an event today. Empty event list = every event.
 *
 * @param array  $record     Sponsor.
 * @param string $event_slug Current event slug ('' when unknown).
 * @param string $today      Y-m-d.
 * @return bool
 */
function swat_child_sponsor_is_live( $record, $event_slug, $today ) {
	if ( 'live' !== swat_child_sponsor_state( $record, $today ) ) {
		return false;
	}
	return ! $record['events'] || in_array( sanitize_title( (string) $event_slug ), $record['events'], true );
}

/**
 * Pick the sponsor for a slot. Sponsors targeted at this event beat sponsors
 * for every event; ties rotate at random per page view.
 *
 * @param array<string,array> $records    Sponsors.
 * @param string              $slot       Slot key.
 * @param string              $event_slug Event slug.
 * @param string              $today      Y-m-d.
 * @param callable|null       $rand       fn( int $max ): int in [0, $max]; for tests.
 * @return array|null
 */
function swat_child_sponsor_pick( $records, $slot, $event_slug, $today, $rand = null ) {
	$targeted = array();
	$general  = array();
	foreach ( $records as $record ) {
		if ( ! in_array( $slot, $record['slots'], true ) || ! swat_child_sponsor_is_live( $record, $event_slug, $today ) ) {
			continue;
		}
		if ( $record['events'] ) {
			$targeted[] = $record;
		} else {
			$general[] = $record;
		}
	}
	$pool = $targeted ? $targeted : $general;
	if ( ! $pool ) {
		return null;
	}
	if ( 1 === count( $pool ) ) {
		return $pool[0];
	}
	$max   = count( $pool ) - 1;
	$index = $rand ? (int) $rand( $max ) : ( function_exists( 'wp_rand' ) ? wp_rand( 0, $max ) : mt_rand( 0, $max ) );
	return $pool[ max( 0, min( $max, $index ) ) ];
}

/**
 * Logo URL for a sponsor ('' when none).
 *
 * @param array $record Sponsor.
 * @return string
 */
function swat_child_sponsor_logo_url( $record ) {
	if ( empty( $record['logo_id'] ) || ! function_exists( 'wp_get_attachment_image_url' ) ) {
		return '';
	}
	$url = wp_get_attachment_image_url( (int) $record['logo_id'], 'medium' );
	return $url ? (string) $url : '';
}

/**
 * Event slug from the event array the Game Day hooks receive.
 *
 * @param mixed $event Event.
 * @return string
 */
function swat_child_sponsor_event_slug( $event ) {
	$event = is_array( $event ) ? $event : array();
	return sanitize_title( (string) ( $event['slug'] ?? '' ) );
}

/**
 * Feed admin-managed sponsors into the existing slot hook.
 * Slots already filled by code (default priority) are replaced by that code later.
 *
 * @param array $slots Slots so far.
 * @param array $event Current event.
 * @return array
 */
function swat_child_sponsor_fill_slots( $slots, $event = array() ) {
	$slots   = is_array( $slots ) ? $slots : array();
	$records = swat_child_sponsor_records();
	if ( ! $records ) {
		return $slots;
	}
	$slug  = swat_child_sponsor_event_slug( $event );
	$today = swat_child_sponsor_today();
	foreach ( array_keys( swat_child_sponsor_slot_defs() ) as $slot ) {
		if ( ! empty( $slots[ $slot ] ) ) {
			continue;
		}
		$record = swat_child_sponsor_pick( $records, $slot, $slug, $today );
		if ( $record ) {
			$slots[ $slot ] = array(
				'name'    => $record['name'],
				'url'     => $record['url'],
				'tagline' => $record['tagline'],
				'logo'    => swat_child_sponsor_logo_url( $record ),
				'id'      => $record['id'],
			);
		}
	}
	return $slots;
}
add_filter( 'swat_child_gameday_sponsor_slots', 'swat_child_sponsor_fill_slots', 5, 2 );

/**
 * Feed admin-managed field sponsors ("Field 1 presented by …") into the existing hook.
 *
 * @param array $fields Field label => sponsor name.
 * @param array $event  Current event.
 * @return array
 */
function swat_child_sponsor_fill_fields( $fields, $event = array() ) {
	$fields = is_array( $fields ) ? $fields : array();
	$slug   = swat_child_sponsor_event_slug( $event );
	$today  = swat_child_sponsor_today();
	$taken  = array();
	// Event-targeted sponsors first so they win a field over every-event sponsors.
	$records = swat_child_sponsor_records();
	uasort(
		$records,
		static function ( $a, $b ) {
			return (int) empty( $a['events'] ) - (int) empty( $b['events'] );
		}
	);
	foreach ( $records as $record ) {
		if ( ! $record['fields'] || ! swat_child_sponsor_is_live( $record, $slug, $today ) ) {
			continue;
		}
		foreach ( $record['fields'] as $field ) {
			if ( ! isset( $fields[ $field ] ) && ! isset( $taken[ $field ] ) ) {
				$fields[ $field ] = $record['name'];
				$taken[ $field ]  = true;
			}
		}
	}
	return $fields;
}
add_filter( 'swat_child_gameday_field_sponsors', 'swat_child_sponsor_fill_fields', 5, 2 );

/* ---------------------------------------------------------------------------
 * Tracking
 * ------------------------------------------------------------------------- */

/**
 * Tracked link for a banner.
 *
 * @param string $id   Sponsor id.
 * @param string $slot Slot key.
 * @return string
 */
function swat_child_sponsor_go_url( $id, $slot ) {
	return home_url( '/' ) . '?swat_sponsor_go=' . rawurlencode( (string) $id ) . '&slot=' . rawurlencode( sanitize_key( (string) $slot ) );
}

/**
 * Sponsor link with UTM tags so the sponsor sees SWAT traffic in their analytics.
 * Existing utm_* parameters on the saved link are respected.
 *
 * @param string $url  Saved sponsor link.
 * @param string $slot Slot key.
 * @return string
 */
function swat_child_sponsor_destination( $url, $slot ) {
	$url = swat_child_sponsor_clean_url( $url );
	if ( '' === $url || false !== stripos( $url, 'utm_source=' ) ) {
		return $url;
	}
	$fragment = '';
	$hash     = strpos( $url, '#' );
	if ( false !== $hash ) {
		$fragment = substr( $url, $hash );
		$url      = substr( $url, 0, $hash );
	}
	$query = http_build_query(
		array(
			'utm_source'   => 'swatourneys',
			'utm_medium'   => 'gameday',
			'utm_campaign' => 'swat-sponsor',
			'utm_content'  => sanitize_key( (string) $slot ),
		),
		'',
		'&'
	);
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query . $fragment;
}

/**
 * Counter option name. Example: swat_spc_a1b2c3d4_202610_scores_c.
 *
 * @param string $id    Sponsor id.
 * @param string $month YYYYMM.
 * @param string $slot  Slot key or 'field'.
 * @param string $kind  'view' or 'click'.
 * @return string
 */
function swat_child_sponsor_counter_key( $id, $month, $slot, $kind ) {
	return SWAT_CHILD_SPONSOR_COUNTER . $id . '_' . $month . '_' . sanitize_key( $slot ) . '_' . ( 'click' === $kind ? 'c' : 'v' );
}

/**
 * Parse a counter option name.
 *
 * @param string $name Option name.
 * @return array{id:string,month:string,slot:string,kind:string}|null
 */
function swat_child_sponsor_parse_counter_key( $name ) {
	if ( ! preg_match( '/^' . preg_quote( SWAT_CHILD_SPONSOR_COUNTER, '/' ) . '([a-z0-9]{1,16})_(\d{6})_([a-z0-9\-]+)_([vc])$/', (string) $name, $m ) ) {
		return null;
	}
	return array(
		'id'    => $m[1],
		'month' => $m[2],
		'slot'  => $m[3],
		'kind'  => 'c' === $m[4] ? 'click' : 'view',
	);
}

/**
 * Whether a user agent is an obvious crawler or link-preview bot.
 *
 * @param string $ua User agent.
 * @return bool
 */
function swat_child_sponsor_is_bot( $ua ) {
	$ua = (string) $ua;
	return '' === trim( $ua ) || (bool) preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|headless|lighthouse|curl|wget|python-requests/i', $ua );
}

/**
 * Atomically add one to a counter (single SQL statement; safe under concurrency).
 *
 * @param string $id   Sponsor id.
 * @param string $slot Slot key.
 * @param string $kind 'view' or 'click'.
 * @return void
 */
function swat_child_sponsor_bump( $id, $slot, $kind ) {
	global $wpdb;
	if ( ! isset( $wpdb ) ) {
		return;
	}
	$key = swat_child_sponsor_counter_key( $id, wp_date( 'Ym' ), $slot, $kind );
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
			$key
		)
	);
}

/**
 * Click-through: count, then redirect to the sponsor's saved link.
 *
 * @return void
 */
function swat_child_sponsor_handle_click() {
	if ( is_admin() || ! isset( $_GET['swat_sponsor_go'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public link.
		return;
	}
	$id      = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_GET['swat_sponsor_go'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slot    = isset( $_GET['slot'] ) ? sanitize_key( wp_unslash( $_GET['slot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$records = swat_child_sponsor_records();
	$record  = $records[ $id ] ?? null;
	$target  = $record ? swat_child_sponsor_destination( $record['url'], $slot ) : '';
	if ( '' === $target ) {
		wp_safe_redirect( home_url( '/gameday/' ), 302 );
		exit;
	}
	if ( isset( swat_child_sponsor_slot_defs()[ $slot ] ) && ! swat_child_sponsor_is_bot( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ) {
		swat_child_sponsor_bump( $id, $slot, 'click' );
	}
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	// The target is the admin-saved sponsor link, never a URL from the request.
	wp_redirect( $target, 302, 'SWAT Tournaments' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}
add_action( 'init', 'swat_child_sponsor_handle_click', 20 );

/**
 * Validate a view report. Returns [id, slot] or null.
 *
 * @param array               $params  Request params.
 * @param array<string,array> $records Sponsors.
 * @return array{0:string,1:string}|null
 */
function swat_child_sponsor_view_target( $params, $records ) {
	$id   = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $params['id'] ?? '' ) ) );
	$slot = sanitize_key( (string) ( $params['slot'] ?? '' ) );
	if ( '' === $id || ! isset( $records[ $id ] ) || ! in_array( $slot, $records[ $id ]['slots'], true ) ) {
		return null;
	}
	return array( $id, $slot );
}

/**
 * REST: POST /wp-json/swat-child/v1/sponsor-view {id, slot}.
 *
 * @return void
 */
function swat_child_sponsor_rest() {
	register_rest_route(
		'swat-child/v1',
		'/sponsor-view',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => static function ( $request ) {
				$params = $request->get_json_params();
				$params = is_array( $params ) && $params ? $params : $request->get_params();
				$target = swat_child_sponsor_view_target( (array) $params, swat_child_sponsor_records() );
				if ( $target && ! swat_child_sponsor_is_bot( (string) $request->get_header( 'user_agent' ) ) ) {
					swat_child_sponsor_bump( $target[0], $target[1], 'view' );
				}
				return new WP_REST_Response( null, 204 );
			},
		)
	);
}
add_action( 'rest_api_init', 'swat_child_sponsor_rest' );

/**
 * Load the view reporter and banner styles on Game Day when a sponsor is live.
 *
 * @return void
 */
function swat_child_sponsor_assets() {
	if ( ! function_exists( 'swat_child_hub_active' ) || ! swat_child_hub_active() || ! swat_child_sponsor_records() ) {
		return;
	}
	$css  = SWAT_CHILD_DIR . '/assets/css/sponsors.css';
	$deps = wp_style_is( 'swat-postseason', 'enqueued' ) ? array( 'swat-postseason' ) : array();
	wp_enqueue_style( 'swat-child-sponsors', SWAT_CHILD_URI . '/assets/css/sponsors.css', $deps, is_readable( $css ) ? (string) filemtime( $css ) : SWAT_CHILD_VERSION );
	$js = SWAT_CHILD_DIR . '/assets/js/swat-sponsors.js';
	wp_enqueue_script( 'swat-child-sponsors', SWAT_CHILD_URI . '/assets/js/swat-sponsors.js', array(), is_readable( $js ) ? (string) filemtime( $js ) : SWAT_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script( 'swat-child-sponsors', 'window.SWATSponsors=' . wp_json_encode( array( 'viewUrl' => rest_url( 'swat-child/v1/sponsor-view' ) ) ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'swat_child_sponsor_assets', 125 );

/**
 * Counters grouped as [id][YYYYMM][slot][view|click] => int.
 *
 * @return array
 */
function swat_child_sponsor_stats() {
	global $wpdb;
	$out = array();
	if ( ! isset( $wpdb ) ) {
		return $out;
	}
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( SWAT_CHILD_SPONSOR_COUNTER ) . '%' ),
		ARRAY_A
	);
	foreach ( (array) $rows as $row ) {
		$key = swat_child_sponsor_parse_counter_key( $row['option_name'] );
		if ( $key ) {
			$out[ $key['id'] ][ $key['month'] ][ $key['slot'] ][ $key['kind'] ] = (int) $row['option_value'];
		}
	}
	return $out;
}

/**
 * Totals for one sponsor and month.
 *
 * @param array  $stats Output of swat_child_sponsor_stats().
 * @param string $id    Sponsor id.
 * @param string $month YYYYMM.
 * @return array{view:int,click:int,slots:array}
 */
function swat_child_sponsor_month_totals( $stats, $id, $month ) {
	$slots = (array) ( $stats[ $id ][ $month ] ?? array() );
	$view  = 0;
	$click = 0;
	foreach ( $slots as $counts ) {
		$view  += (int) ( $counts['view'] ?? 0 );
		$click += (int) ( $counts['click'] ?? 0 );
	}
	return array( 'view' => $view, 'click' => $click, 'slots' => $slots );
}

/* ---------------------------------------------------------------------------
 * Admin: SWAT Operations -> Sponsors
 * ------------------------------------------------------------------------- */

/**
 * Register the submenu under the parent theme's SWAT Operations menu.
 *
 * @return void
 */
function swat_child_sponsor_menu() {
	add_submenu_page( 'swat-ops', 'Sponsors', 'Sponsors', swat_child_sponsor_capability(), 'swat-sponsors', 'swat_child_render_sponsor_admin' );
}
add_action( 'admin_menu', 'swat_child_sponsor_menu', 21 );

/**
 * Media library for the logo picker, on this screen only.
 *
 * @param string $hook Admin page hook.
 * @return void
 */
function swat_child_sponsor_admin_assets( $hook ) {
	if ( false !== strpos( (string) $hook, 'swat-sponsors' ) ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'swat_child_sponsor_admin_assets' );

/**
 * Events offered in the targeting list: current/upcoming, plus any already selected.
 *
 * @param string[] $selected Selected slugs.
 * @return array<string,string> slug => label
 */
function swat_child_sponsor_event_choices( $selected = array() ) {
	$today   = swat_child_sponsor_today();
	$choices = array();
	foreach ( function_exists( 'swat_os_event_directory' ) ? swat_os_event_directory() : array() as $event ) {
		$parts = swat_child_event_parts( $event );
		if ( '' === $parts['slug'] || isset( $choices[ $parts['slug'] ] ) ) {
			continue;
		}
		if ( '' !== $parts['end'] && $parts['end'] < $today && ! in_array( $parts['slug'], $selected, true ) ) {
			continue;
		}
		$date                       = '' !== $parts['start'] ? wp_date( 'M j', strtotime( $parts['start'] ) ) . ' · ' : '';
		$choices[ $parts['slug'] ] = $date . $parts['name'];
	}
	foreach ( $selected as $slug ) {
		if ( ! isset( $choices[ $slug ] ) ) {
			$choices[ $slug ] = $slug . ' (no longer listed)';
		}
	}
	return $choices;
}

/**
 * Save (add or update) a sponsor.
 *
 * @return void
 */
function swat_child_sponsor_save() {
	if ( ! current_user_can( swat_child_sponsor_capability() ) ) {
		wp_die( 'You do not have permission to manage sponsors.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'swat_child_sponsor_save' );
	$post    = wp_unslash( $_POST );
	$records = swat_child_sponsor_records();
	$id      = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $post['id'] ?? '' ) ) );
	if ( '' === $id || ! isset( $records[ $id ] ) ) {
		do {
			$id = substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
		} while ( isset( $records[ $id ] ) );
	}
	$raw_url = trim( (string) ( $post['url'] ?? '' ) );
	$record  = swat_child_sponsor_sanitize(
		array(
			'id'      => $id,
			'name'    => $post['name'] ?? '',
			'url'     => $raw_url,
			'tagline' => $post['tagline'] ?? '',
			'logo_id' => $post['logo_id'] ?? 0,
			'slots'   => (array) ( $post['slots'] ?? array() ),
			'fields'  => $post['fields'] ?? '',
			'events'  => (array) ( $post['events'] ?? array() ),
			'start'   => $post['start'] ?? '',
			'end'     => $post['end'] ?? '',
			'active'  => ! empty( $post['active'] ),
		)
	);
	$back = admin_url( 'admin.php?page=swat-sponsors' );
	if ( ! $record ) {
		wp_safe_redirect( add_query_arg( 'swat-sponsor-error', 'name', $back ) );
		exit;
	}
	$records[ $id ] = $record;
	update_option( SWAT_CHILD_SPONSOR_OPTION, array_values( $records ), true );
	$args = array( 'swat-sponsor-saved' => '1' );
	if ( '' !== $raw_url && '' === $record['url'] ) {
		$args['swat-sponsor-error'] = 'url';
	}
	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}
add_action( 'admin_post_swat_child_save_sponsor', 'swat_child_sponsor_save' );

/**
 * Delete a sponsor and its counters.
 *
 * @return void
 */
function swat_child_sponsor_delete() {
	if ( ! current_user_can( swat_child_sponsor_capability() ) ) {
		wp_die( 'You do not have permission to manage sponsors.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'swat_child_sponsor_delete' );
	$id      = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_POST['id'] ?? '' ) ) );
	$records = swat_child_sponsor_records();
	if ( '' !== $id && isset( $records[ $id ] ) ) {
		unset( $records[ $id ] );
		update_option( SWAT_CHILD_SPONSOR_OPTION, array_values( $records ), true );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( SWAT_CHILD_SPONSOR_COUNTER . $id . '_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	wp_safe_redirect( add_query_arg( 'swat-sponsor-deleted', '1', admin_url( 'admin.php?page=swat-sponsors' ) ) );
	exit;
}
add_action( 'admin_post_swat_child_delete_sponsor', 'swat_child_sponsor_delete' );

/**
 * Click-through rate text.
 *
 * @param int $views  Views.
 * @param int $clicks Clicks.
 * @return string
 */
function swat_child_sponsor_ctr( $views, $clicks ) {
	return $views > 0 ? number_format( 100 * $clicks / $views, 1 ) . '%' : '—';
}

/**
 * Render the admin page.
 *
 * @return void
 */
function swat_child_render_sponsor_admin() {
	if ( ! current_user_can( swat_child_sponsor_capability() ) ) {
		wp_die( 'You do not have permission to view this page.', '', array( 'response' => 403 ) );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$records   = swat_child_sponsor_records();
	$defs      = swat_child_sponsor_slot_defs();
	$today     = swat_child_sponsor_today();
	$stats     = swat_child_sponsor_stats();
	$this_m    = wp_date( 'Ym' );
	$last_m    = wp_date( 'Ym', strtotime( 'first day of last month', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$edit_id   = isset( $_GET['edit'] ) ? strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_GET['edit'] ) ) ) : '';
	$adding    = isset( $_GET['add'] );
	$editing   = $records[ $edit_id ] ?? null;
	$error     = isset( $_GET['swat-sponsor-error'] ) ? sanitize_key( wp_unslash( $_GET['swat-sponsor-error'] ) ) : '';
	$saved     = isset( $_GET['swat-sponsor-saved'] );
	$deleted   = isset( $_GET['swat-sponsor-deleted'] );
	// phpcs:enable
	$base      = admin_url( 'admin.php?page=swat-sponsors' );
	$state_txt = array(
		'live'      => 'Live',
		'scheduled' => 'Scheduled',
		'ended'     => 'Ended',
		'paused'    => 'Paused',
	);
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Game Day Sponsors</h1>
		<?php if ( ! $editing && ! $adding ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'add', '1', $base ) ); ?>">Add sponsor</a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Sponsor saved. Purge your page cache if Game Day does not update.</p></div>
		<?php endif; ?>
		<?php if ( $deleted ) : ?>
			<div class="notice notice-success is-dismissible"><p>Sponsor and its stats deleted.</p></div>
		<?php endif; ?>
		<?php if ( 'url' === $error ) : ?>
			<div class="notice notice-warning"><p>The link was not saved: it must be a full address starting with https:// or http://. The banner shows without a link until you add one.</p></div>
		<?php elseif ( 'name' === $error ) : ?>
			<div class="notice notice-error"><p>A sponsor needs a name.</p></div>
		<?php endif; ?>

		<?php if ( $editing || $adding ) : ?>
			<?php
			$r = $editing ? $editing : array(
				'id'      => '',
				'name'    => '',
				'url'     => '',
				'tagline' => '',
				'logo_id' => 0,
				'slots'   => array(),
				'fields'  => array(),
				'events'  => array(),
				'start'   => '',
				'end'     => '',
				'active'  => true,
			);
			$logo = swat_child_sponsor_logo_url( $r );
			?>
			<h2><?php echo $editing ? 'Edit ' . esc_html( $r['name'] ) : 'Add sponsor'; ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="swat_child_save_sponsor">
				<input type="hidden" name="id" value="<?php echo esc_attr( $r['id'] ); ?>">
				<?php wp_nonce_field( 'swat_child_sponsor_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="swat-sp-name">Sponsor name</label></th>
						<td><input id="swat-sp-name" name="name" type="text" class="regular-text" maxlength="60" required value="<?php echo esc_attr( $r['name'] ); ?>"><p class="description">Shown as “Presented by <em>name</em>”.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="swat-sp-url">Link</label></th>
						<td><input id="swat-sp-url" name="url" type="url" class="regular-text" placeholder="https://" value="<?php echo esc_attr( $r['url'] ); ?>"><p class="description">Optional. Clicks are counted, and the link gets utm_source=swatourneys so the sponsor sees SWAT traffic in their own analytics.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="swat-sp-tagline">Tagline</label></th>
						<td><input id="swat-sp-tagline" name="tagline" type="text" class="regular-text" maxlength="80" value="<?php echo esc_attr( $r['tagline'] ); ?>"><p class="description">Optional, one short line, e.g. “10% off for SWAT families”.</p></td>
					</tr>
					<tr>
						<th scope="row">Logo</th>
						<td>
							<input type="hidden" name="logo_id" id="swat-sp-logo-id" value="<?php echo esc_attr( (string) $r['logo_id'] ); ?>">
							<span id="swat-sp-logo-preview"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="" style="max-height:48px;max-width:160px;background:#fff;padding:4px;border:1px solid #dcdcde;border-radius:4px;vertical-align:middle"><?php endif; ?></span>
							<button type="button" class="button" id="swat-sp-logo-pick">Choose logo</button>
							<button type="button" class="button-link" id="swat-sp-logo-clear"<?php echo $logo ? '' : ' hidden'; ?>>Remove</button>
							<p class="description">Optional. A wide PNG or SVG on a transparent or white background works best; it is shown on a white tile in light and dark mode.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Where it shows</th>
						<td>
							<fieldset><legend class="screen-reader-text">Banner placements</legend>
							<?php foreach ( $defs as $key => $label ) : ?>
								<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="slots[]" value="<?php echo esc_attr( $key ); ?>"<?php checked( in_array( $key, $r['slots'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
							</fieldset>
							<p class="description">One banner per placement. If two sponsors share a placement, they rotate on each page view. A sponsor targeted at a specific event replaces every-event sponsors during that event.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swat-sp-fields">Field naming rights</label></th>
						<td><input id="swat-sp-fields" name="fields" type="text" class="regular-text" value="<?php echo esc_attr( implode( ', ', $r['fields'] ) ); ?>"><p class="description">Optional. Field names exactly as they appear on game cards, separated by commas. Those game cards then read “<em>Field</em> presented by <em>name</em>”.</p></td>
					</tr>
					<tr>
						<th scope="row">Events</th>
						<td>
							<fieldset><legend class="screen-reader-text">Events</legend>
							<p style="margin-top:0">Leave all unchecked to show at every event.</p>
							<?php foreach ( swat_child_sponsor_event_choices( $r['events'] ) as $slug => $label ) : ?>
								<label style="display:block;margin:0 0 4px"><input type="checkbox" name="events[]" value="<?php echo esc_attr( $slug ); ?>"<?php checked( in_array( $slug, $r['events'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">Run dates</th>
						<td>
							<label>Start <input name="start" type="date" value="<?php echo esc_attr( $r['start'] ); ?>"></label>
							<label style="margin-left:12px">End <input name="end" type="date" value="<?php echo esc_attr( $r['end'] ); ?>"></label>
							<p class="description">Optional. Inclusive. Leave blank to run until you pause or delete it.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Status</th>
						<td><label><input type="checkbox" name="active" value="1"<?php checked( ! empty( $r['active'] ) ); ?>> Active</label></td>
					</tr>
				</table>
				<?php submit_button( $editing ? 'Save sponsor' : 'Add sponsor' ); ?>
				<a href="<?php echo esc_url( $base ); ?>">Cancel</a>
			</form>
			<script>
			(function(){
				var pick=document.getElementById('swat-sp-logo-pick'),clear=document.getElementById('swat-sp-logo-clear'),
					input=document.getElementById('swat-sp-logo-id'),preview=document.getElementById('swat-sp-logo-preview'),frame;
				if(!pick||!window.wp||!wp.media){if(pick)pick.disabled=true;return;}
				pick.addEventListener('click',function(){
					frame=frame||wp.media({title:'Sponsor logo',button:{text:'Use this logo'},library:{type:'image'},multiple:false});
					frame.off('select').on('select',function(){
						var a=frame.state().get('selection').first().toJSON(),src=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url),img=new Image();
						input.value=a.id;img.src=src;img.alt='';img.style.cssText='max-height:48px;max-width:160px;background:#fff;padding:4px;border:1px solid #dcdcde;border-radius:4px;vertical-align:middle';
						preview.replaceChildren(img);clear.hidden=false;
					});
					frame.open();
				});
				clear.addEventListener('click',function(){input.value='0';preview.replaceChildren();clear.hidden=true;});
			})();
			</script>
		<?php else : ?>
			<p>Sponsor banners on Game Day. Views are counted once per visitor visit, only when the banner is on screen; clicks are counted when someone taps through. Use these numbers in renewal conversations.</p>
			<?php if ( ! $records ) : ?>
				<p><strong>No sponsors yet.</strong> <a href="<?php echo esc_url( add_query_arg( 'add', '1', $base ) ); ?>">Add your first sponsor</a>.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th scope="col">Sponsor</th><th scope="col">Shows on</th><th scope="col">Events</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col">This month<br><small>views / clicks / CTR</small></th><th scope="col">Last month<br><small>views / clicks / CTR</small></th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead>
					<tbody>
					<?php foreach ( $records as $r ) : ?>
						<?php
						$logo  = swat_child_sponsor_logo_url( $r );
						$where = array_map(
							static function ( $slot ) use ( $defs ) {
								return $defs[ $slot ];
							},
							$r['slots']
						);
						if ( $r['fields'] ) {
							$where[] = 'Fields: ' . implode( ', ', $r['fields'] );
						}
						$now  = swat_child_sponsor_month_totals( $stats, $r['id'], $this_m );
						$prev = swat_child_sponsor_month_totals( $stats, $r['id'], $last_m );
						?>
						<tr>
							<td>
								<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="" style="max-height:28px;max-width:90px;vertical-align:middle;margin-right:6px;background:#fff"><?php endif; ?>
								<strong><a href="<?php echo esc_url( add_query_arg( 'edit', $r['id'], $base ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a></strong>
								<?php if ( $r['url'] ) : ?><br><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', $r['url'] ) ); ?></a><?php endif; ?>
							</td>
							<td><?php echo $where ? esc_html( implode( ', ', $where ) ) : '<em>Nowhere selected</em>'; ?></td>
							<td><?php echo $r['events'] ? esc_html( (string) count( $r['events'] ) . ' selected' ) : 'All'; ?></td>
							<td><?php echo esc_html( ( $r['start'] ? $r['start'] : 'Now' ) . ' → ' . ( $r['end'] ? $r['end'] : 'Open' ) ); ?></td>
							<td><?php echo esc_html( $state_txt[ swat_child_sponsor_state( $r, $today ) ] ); ?></td>
							<td><?php echo esc_html( number_format( $now['view'] ) . ' / ' . number_format( $now['click'] ) . ' / ' . swat_child_sponsor_ctr( $now['view'], $now['click'] ) ); ?></td>
							<td><?php echo esc_html( number_format( $prev['view'] ) . ' / ' . number_format( $prev['click'] ) . ' / ' . swat_child_sponsor_ctr( $prev['view'], $prev['click'] ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', $r['id'], $base ) ); ?>">Edit</a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Delete this sponsor and its view/click stats?');">
									<input type="hidden" name="action" value="swat_child_delete_sponsor">
									<input type="hidden" name="id" value="<?php echo esc_attr( $r['id'] ); ?>">
									<?php wp_nonce_field( 'swat_child_sponsor_delete' ); ?>
									<button type="submit" class="button-link button-link-delete">Delete</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
