<?php
/**
 * 1.0.33: Game Day sponsors (inc/sponsors.php + the strip in inc/gameday-hub.php).
 *   php tests/sponsors-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SWAT_CHILD_DIR', dirname( __DIR__ ) );
define( 'SWAT_CHILD_URI', 'https://swatourneys.com/wp-content/themes/swat-tournament-os-child' );
define( 'SWAT_CHILD_VERSION', 'test' );

// Minimal filter registry so priority order is exercised for real.
$GLOBALS['filters'] = array();
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $hook ][ $priority ][] = array( $cb, $args ); }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { add_filter( $hook, $cb, $priority, $args ); }
function remove_all_filters( $hook ) { unset( $GLOBALS['filters'][ $hook ] ); }
function apply_filters( $hook, $value, ...$rest ) {
	$byp = $GLOBALS['filters'][ $hook ] ?? array();
	ksort( $byp );
	foreach ( $byp as $cbs ) {
		foreach ( $cbs as $cb ) {
			$value = call_user_func_array( $cb[0], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $cb[1] ) ) );
		}
	}
	return $value;
}
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return (string) $s; }
function home_url( $p = '' ) { return 'https://swatourneys.com' . $p; }
function current_time( $f ) { return $GLOBALS['today']; }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? strtotime( $GLOBALS['today'] ) ); }
function wp_get_attachment_image_url( $id, $size ) { return 42 === (int) $id ? 'https://swatourneys.com/wp-content/uploads/acme.png' : false; }
$GLOBALS['opt'] = array();
function get_option( $name, $default = false ) { return $GLOBALS['opt'][ $name ] ?? $default; }
$GLOBALS['today'] = '2026-10-17';

require __DIR__ . '/../inc/gameday-hub.php';
require __DIR__ . '/../inc/sponsors.php';

$fail  = 0;
$n     = 0;
$check = function ( $label, $got, $want ) use ( &$fail, &$n ) {
	$n++;
	if ( $got !== $want ) {
		$fail++;
		printf( "FAIL %s\n  got:  %s\n  want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
	}
};
$has = function ( $label, $haystack, $needle, $want = true ) use ( $check ) {
	$check( $label, false !== strpos( $haystack, $needle ), $want );
};

// ---- URL cleaning --------------------------------------------------------
$check( 'url https', swat_child_sponsor_clean_url( 'https://acme.com/x?a=1' ), 'https://acme.com/x?a=1' );
$check( 'url http', swat_child_sponsor_clean_url( ' http://acme.com ' ), 'http://acme.com' );
$check( 'url javascript', swat_child_sponsor_clean_url( 'javascript:alert(1)' ), '' );
$check( 'url data', swat_child_sponsor_clean_url( 'data:text/html,hi' ), '' );
$check( 'url relative', swat_child_sponsor_clean_url( '/gameday/' ), '' );
$check( 'url no scheme', swat_child_sponsor_clean_url( 'acme.com' ), '' );
$check( 'url quote', swat_child_sponsor_clean_url( 'https://acme.com/"onmouseover=x' ), '' );
$check( 'url space', swat_child_sponsor_clean_url( 'https://acme .com' ), '' );

// ---- Record sanitising ---------------------------------------------------
$check( 'no name -> null', swat_child_sponsor_sanitize( array( 'id' => 'abc' ) ), null );
$check( 'no id -> null', swat_child_sponsor_sanitize( array( 'name' => 'Acme' ) ), null );
$check( 'long id -> null', swat_child_sponsor_sanitize( array( 'id' => str_repeat( 'a', 17 ), 'name' => 'Acme' ) ), null );
$rec = swat_child_sponsor_sanitize(
	array(
		'id'      => 'AB12-cd',
		'name'    => ' <b>Acme</b> Ortho ',
		'url'     => 'javascript:alert(1)',
		'tagline' => str_repeat( 'x', 120 ),
		'slots'   => array( 'scores', 'evil', 'HEADER', 'scores' ),
		'fields'  => "Field 1, Field 2\nField 1",
		'events'  => array( 'Pumpkin Smash', 'pumpkin-smash', '' ),
		'start'   => '2026-10-20',
		'end'     => '2026-10-01',
		'active'  => '1',
	)
);
$check( 'id normalised', $rec['id'], 'ab12cd' );
$check( 'name stripped', $rec['name'], 'Acme Ortho' );
$check( 'unsafe url dropped', $rec['url'], '' );
$check( 'tagline capped', strlen( $rec['tagline'] ), 80 );
$check( 'slots whitelisted in definition order', $rec['slots'], array( 'header', 'scores' ) );
$check( 'fields split + deduped', $rec['fields'], array( 'Field 1', 'Field 2' ) );
$check( 'events slugged + deduped', $rec['events'], array( 'pumpkin-smash' ) );
$check( 'end before start clamps', array( $rec['start'], $rec['end'] ), array( '2026-10-20', '2026-10-20' ) );
$check( 'active bool', $rec['active'], true );
$bad = swat_child_sponsor_sanitize( array( 'id' => 'x1', 'name' => 'A', 'start' => '2026-02-30', 'end' => '10/31/2026' ) );
$check( 'invalid dates dropped', array( $bad['start'], $bad['end'] ), array( '', '' ) );
$check( 'inactive default', $bad['active'], false );

// ---- Lifecycle -----------------------------------------------------------
$base = array( 'id' => 'a', 'name' => 'A', 'slots' => array( 'scores' ), 'active' => 1 );
$s    = fn( $extra ) => swat_child_sponsor_sanitize( $extra + $base );
$check( 'state live', swat_child_sponsor_state( $s( array() ), '2026-10-17' ), 'live' );
$check( 'state paused', swat_child_sponsor_state( $s( array( 'active' => 0 ) ), '2026-10-17' ), 'paused' );
$check( 'state scheduled', swat_child_sponsor_state( $s( array( 'start' => '2026-10-18' ) ), '2026-10-17' ), 'scheduled' );
$check( 'state start day inclusive', swat_child_sponsor_state( $s( array( 'start' => '2026-10-17' ) ), '2026-10-17' ), 'live' );
$check( 'state end day inclusive', swat_child_sponsor_state( $s( array( 'end' => '2026-10-17' ) ), '2026-10-17' ), 'live' );
$check( 'state ended', swat_child_sponsor_state( $s( array( 'end' => '2026-10-16' ) ), '2026-10-17' ), 'ended' );
$check( 'live every event', swat_child_sponsor_is_live( $s( array() ), 'anything', '2026-10-17' ), true );
$check( 'live no event slug', swat_child_sponsor_is_live( $s( array() ), '', '2026-10-17' ), true );
$check( 'targeted match', swat_child_sponsor_is_live( $s( array( 'events' => array( 'pumpkin-smash' ) ) ), 'Pumpkin Smash', '2026-10-17' ), true );
$check( 'targeted other event', swat_child_sponsor_is_live( $s( array( 'events' => array( 'pumpkin-smash' ) ) ), 'mlb-player-series', '2026-10-17' ), false );

// ---- Picking -------------------------------------------------------------
$records = array();
foreach (
	array(
		array( 'id' => 'gen1', 'name' => 'General One', 'slots' => array( 'scores', 'header' ), 'active' => 1 ),
		array( 'id' => 'gen2', 'name' => 'General Two', 'slots' => array( 'scores' ), 'active' => 1 ),
		array( 'id' => 'tgt', 'name' => 'Pumpkin Partner', 'slots' => array( 'scores' ), 'events' => array( 'pumpkin-smash' ), 'active' => 1 ),
		array( 'id' => 'old', 'name' => 'Expired', 'slots' => array( 'bracket' ), 'end' => '2026-09-30', 'active' => 1 ),
		array( 'id' => 'off', 'name' => 'Paused', 'slots' => array( 'standings' ), 'active' => 0 ),
	) as $raw
) {
	$r                   = swat_child_sponsor_sanitize( $raw );
	$records[ $r['id'] ] = $r;
}
$first  = fn( $max ) => 0;
$last   = fn( $max ) => $max;
$check( 'targeted beats general', swat_child_sponsor_pick( $records, 'scores', 'pumpkin-smash', '2026-10-17', $last )['id'], 'tgt' );
$check( 'general rotates (first)', swat_child_sponsor_pick( $records, 'scores', 'mlb-player-series', '2026-10-17', $first )['id'], 'gen1' );
$check( 'general rotates (last)', swat_child_sponsor_pick( $records, 'scores', 'mlb-player-series', '2026-10-17', $last )['id'], 'gen2' );
$check( 'out-of-range rand clamped', swat_child_sponsor_pick( $records, 'scores', 'x', '2026-10-17', fn( $m ) => 99 )['id'], 'gen2' );
$check( 'slot respected', swat_child_sponsor_pick( $records, 'header', 'x', '2026-10-17' )['id'], 'gen1' );
$check( 'expired never shows', swat_child_sponsor_pick( $records, 'bracket', 'x', '2026-10-17' ), null );
$check( 'paused never shows', swat_child_sponsor_pick( $records, 'standings', 'x', '2026-10-17' ), null );
$check( 'empty slot', swat_child_sponsor_pick( $records, 'schedule', 'x', '2026-10-17' ), null );

// ---- Strip markup: existing code-filter sponsors are unchanged -----------
remove_all_filters( 'swat_child_gameday_sponsor_slots' );
add_filter( 'swat_child_gameday_sponsor_slots', fn() => array( 'header' => array( 'name' => 'Acme', 'url' => 'https://acme.com' ), 'bracket' => array( 'name' => 'No Link Co' ) ) );
$check(
	'legacy linked strip byte-identical to 1.0.32',
	swat_child_gameday_sponsor_strip( 'header' ),
	'<div class="swat-sponsor-strip" data-swat-sponsor="header"><span>Presented by <a href="https://acme.com" rel="sponsored noopener" target="_blank">Acme</a></span><span class="swat-sponsor-tag">Sponsor</span></div>'
);
$check(
	'legacy unlinked strip byte-identical to 1.0.32',
	swat_child_gameday_sponsor_strip( 'bracket' ),
	'<div class="swat-sponsor-strip" data-swat-sponsor="bracket"><span>Presented by <b>No Link Co</b></span><span class="swat-sponsor-tag">Sponsor</span></div>'
);
$check( 'empty slot renders nothing', swat_child_gameday_sponsor_strip( 'scores' ), '' );

// ---- Admin sponsors flow through the existing hook ------------------------
remove_all_filters( 'swat_child_gameday_sponsor_slots' );
add_filter( 'swat_child_gameday_sponsor_slots', 'swat_child_sponsor_fill_slots', 5, 2 );
$GLOBALS['opt']['swat_child_sponsors'] = array(
	array( 'id' => 'ortho1', 'name' => 'Triad Ortho & "Sports" <script>x</script>', 'url' => 'https://triadortho.example/visit', 'tagline' => 'Same-day "sports" injury visits', 'logo_id' => 42, 'slots' => array( 'header', 'scores' ), 'fields' => 'Field 3', 'active' => 1 ),
	array( 'id' => 'pizza1', 'name' => 'Clemmons Pizza', 'slots' => array( 'schedule' ), 'events' => array( 'pumpkin-smash' ), 'fields' => 'Field 3, Field 4', 'active' => 1 ),
	array( 'id' => 'bats1', 'name' => 'Bat Barn', 'slots' => array( 'standings' ), 'active' => 1 ),
);
$event  = array( 'slug' => 'pumpkin-smash', 'name' => 'UDC Sports/ReTurf Solutions Pumpkin Smash' );
$header = swat_child_gameday_sponsor_strip( 'header', $event );
$has( 'tracked href', $header, 'href="https://swatourneys.com/?swat_sponsor_go=ortho1&amp;slot=header"' );
$has( 'raw sponsor link not exposed', $header, 'triadortho.example', false );
$has( 'tracking id attr', $header, 'data-swat-sponsor-id="ortho1"' );
$has( 'has-logo class', $header, 'class="swat-sponsor-strip has-logo"' );
$has( 'logo img decorative', $header, '<img src="https://swatourneys.com/wp-content/uploads/acme.png" alt=""' );
$has( 'name escaped, tags stripped', $header, '>Triad Ortho &amp; &quot;Sports&quot; x</a>' );
$has( 'tagline escaped', $header, '<span class="swat-sponsor-tagline">Same-day &quot;sports&quot; injury visits</span>' );
$has( 'rel sponsored', $header, 'rel="sponsored noopener"' );
$has( 'still says Presented by', $header, '<span>Presented by <a ' );
$sched = swat_child_gameday_sponsor_strip( 'schedule', $event );
$has( 'unlinked admin sponsor: bold, no href', $sched, '<b>Clemmons Pizza</b>' );
$has( 'unlinked admin sponsor keeps id for views', $sched, 'data-swat-sponsor-id="pizza1"' );
$check( 'targeted sponsor absent at other events', swat_child_gameday_sponsor_strip( 'schedule', array( 'slug' => 'mlb-player-series' ) ), '' );
$has( 'standings slot', swat_child_gameday_sponsor_strip( 'standings', $event ), 'Bat Barn' );

// Code at the default priority still overrides one slot; admin fills the rest.
add_filter( 'swat_child_gameday_sponsor_slots', fn( $slots ) => array( 'header' => array( 'name' => 'Code Sponsor', 'url' => 'https://code.example' ) ) + $slots );
$slots = swat_child_gameday_sponsor_slots( $event );
$check( 'code filter wins header', $slots['header']['name'], 'Code Sponsor' );
$check( 'admin keeps scores', $slots['scores']['name'], 'Triad Ortho & "Sports" x' );
remove_all_filters( 'swat_child_gameday_sponsor_slots' );
add_filter( 'swat_child_gameday_sponsor_slots', 'swat_child_sponsor_fill_slots', 5, 2 );

// Bad logo URLs never reach the page.
add_filter( 'swat_child_gameday_sponsor_slots', fn( $slots ) => array( 'bracket' => array( 'name' => 'X', 'logo' => 'javascript:alert(1)' ) ) + $slots );
$has( 'unsafe logo dropped', swat_child_gameday_sponsor_strip( 'bracket', $event ), '<img', false );
remove_all_filters( 'swat_child_gameday_sponsor_slots' );

// ---- Field naming rights -------------------------------------------------
remove_all_filters( 'swat_child_gameday_field_sponsors' );
add_filter( 'swat_child_gameday_field_sponsors', 'swat_child_sponsor_fill_fields', 5, 2 );
$fields = swat_child_gameday_field_sponsors( $event );
$check( 'event-targeted sponsor wins shared field', $fields['Field 3'] ?? '', 'Clemmons Pizza' );
$check( 'field 4', $fields['Field 4'] ?? '', 'Clemmons Pizza' );
$fields = swat_child_gameday_field_sponsors( array( 'slug' => 'mlb-player-series' ) );
$check( 'other event: general sponsor gets field', $fields, array( 'Field 3' => 'Triad Ortho & "Sports" x' ) );
add_filter( 'swat_child_gameday_field_sponsors', fn( $f ) => array( 'Field 3' => 'Code Field Co' ) + $f );
$check( 'code filter wins a field', swat_child_gameday_field_sponsors( $event )['Field 3'], 'Code Field Co' );

// ---- Destination + UTM ---------------------------------------------------
$check( 'utm appended', swat_child_sponsor_destination( 'https://acme.com/p', 'scores' ), 'https://acme.com/p?utm_source=swatourneys&utm_medium=gameday&utm_campaign=swat-sponsor&utm_content=scores' );
$check( 'utm after query', swat_child_sponsor_destination( 'https://acme.com/p?ref=1', 'header' ), 'https://acme.com/p?ref=1&utm_source=swatourneys&utm_medium=gameday&utm_campaign=swat-sponsor&utm_content=header' );
$check( 'fragment kept last', swat_child_sponsor_destination( 'https://acme.com/p#book', 'header' ), 'https://acme.com/p?utm_source=swatourneys&utm_medium=gameday&utm_campaign=swat-sponsor&utm_content=header#book' );
$check( 'sponsor utm respected', swat_child_sponsor_destination( 'https://acme.com/?utm_source=theirs', 'header' ), 'https://acme.com/?utm_source=theirs' );
$check( 'no url -> nothing', swat_child_sponsor_destination( '', 'header' ), '' );
$check( 'unsafe url -> nothing', swat_child_sponsor_destination( 'javascript:x', 'header' ), '' );
$check( 'go url', swat_child_sponsor_go_url( 'ab12', 'Scores' ), 'https://swatourneys.com/?swat_sponsor_go=ab12&slot=scores' );

// ---- Counters ------------------------------------------------------------
$key = swat_child_sponsor_counter_key( 'ab12', '202610', 'scores', 'click' );
$check( 'counter key', $key, 'swat_spc_ab12_202610_scores_c' );
$check( 'counter key <= 191 chars', strlen( swat_child_sponsor_counter_key( str_repeat( 'a', 16 ), '202610', 'standings', 'view' ) ) <= 191, true );
$check( 'parse roundtrip', swat_child_sponsor_parse_counter_key( $key ), array( 'id' => 'ab12', 'month' => '202610', 'slot' => 'scores', 'kind' => 'click' ) );
$check( 'parse view', swat_child_sponsor_parse_counter_key( 'swat_spc_ab12_202610_header_v' )['kind'], 'view' );
$check( 'parse rejects other options', swat_child_sponsor_parse_counter_key( 'swat_child_sponsors' ), null );
$check( 'parse rejects bad month', swat_child_sponsor_parse_counter_key( 'swat_spc_ab12_2026_header_v' ), null );
$stats = array( 'ab12' => array( '202610' => array( 'header' => array( 'view' => 300, 'click' => 6 ), 'scores' => array( 'view' => 200 ) ) ) );
$check( 'month totals', array_slice( swat_child_sponsor_month_totals( $stats, 'ab12', '202610' ), 0, 2 ), array( 'view' => 500, 'click' => 6 ) );
$check( 'month totals empty', array_slice( swat_child_sponsor_month_totals( $stats, 'zz', '202610' ), 0, 2 ), array( 'view' => 0, 'click' => 0 ) );
$check( 'ctr', swat_child_sponsor_ctr( 500, 6 ), '1.2%' );
$check( 'ctr no views', swat_child_sponsor_ctr( 0, 3 ), '—' );

// ---- View reports and bots -----------------------------------------------
$all = swat_child_sponsor_records();
$check( 'view ok', swat_child_sponsor_view_target( array( 'id' => 'ortho1', 'slot' => 'scores' ), $all ), array( 'ortho1', 'scores' ) );
$check( 'view unknown sponsor', swat_child_sponsor_view_target( array( 'id' => 'nope', 'slot' => 'scores' ), $all ), null );
$check( 'view slot not booked', swat_child_sponsor_view_target( array( 'id' => 'ortho1', 'slot' => 'bracket' ), $all ), null );
$check( 'view junk slot', swat_child_sponsor_view_target( array( 'id' => 'ortho1', 'slot' => '<x>' ), $all ), null );
$check( 'bot googlebot', swat_child_sponsor_is_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1)' ), true );
$check( 'bot facebook preview', swat_child_sponsor_is_bot( 'facebookexternalhit/1.1' ), true );
$check( 'bot empty UA', swat_child_sponsor_is_bot( '' ), true );
$check( 'iphone is human', swat_child_sponsor_is_bot( 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1' ), false );

// ---- Template wiring -----------------------------------------------------
$app = file_get_contents( __DIR__ . '/../template-parts/gameday/app.php' );
foreach ( array_keys( swat_child_sponsor_slot_defs() ) as $slot ) {
	$has( "app.php renders the $slot slot", $app, "swat_child_gameday_sponsor_strip( '$slot'" );
}
$functions = file_get_contents( __DIR__ . '/../functions.php' );
$has( 'functions.php loads sponsors after the hub', $functions, "inc/gameday-hub.php';\nrequire_once SWAT_CHILD_DIR . '/inc/sponsors.php'" );

printf( "%d sponsor checks, %d failure(s)\n", $n, $fail );
exit( $fail ? 1 : 0 );
