<?php
/**
 * 1.0.31: directory title cleanup and the JOIN WAITLIST action.
 *   php tests/waitlist-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return (string) $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function home_url( $p = '' ) { return 'https://swatourneys.com' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . implode( '&', array_map( fn( $k, $v ) => $k . '=' . $v, array_keys( $args ), $args ) ); }
$GLOBALS['opt'] = array();
function get_option( $name, $default = false ) { return $GLOBALS['opt'][ $name ] ?? $default; }

require __DIR__ . '/../inc/registration-status.php';
require __DIR__ . '/../inc/waitlist.php';

$fail = 0;
$n    = 0;
$check = function ( $label, $got, $want ) use ( &$fail, &$n ) {
	$n++;
	if ( $got !== $want ) {
		$fail++;
		printf( "FAIL %s\n  got:  %s\n  want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
	}
};

// Titles from the live directory (2026-10-16 screenshot) and edge cases.
$titles = array(
	'FRIDAY NIGHT LIGHTS 10/16 - 9U & 11U'                => 'Friday Night Lights',
	'SATURDAY NIGHT LIGHTS 10/17 - 8U & 10U'              => 'Saturday Night Lights',
	'UDC SPORTS/RETURF SOLUTIONS PUMPKIN SMASH'           => 'UDC Sports/Returf Solutions Pumpkin Smash',
	'MLB FRIDAY NIGHT LIGHTS 10/23 - 8U & 12U'            => 'MLB Friday Night Lights',
	'MLB PLAYER SERIES'                                   => 'MLB Player Series',
	'SPOOKY FRIDAY NIGHT LIGHTS 10/30 - 7U & 11U'         => 'Spooky Friday Night Lights',
	'SATURDAY NIGHT LIGHTS 11/7 - 12U'                    => 'Saturday Night Lights',
	'Friday Night Lights 11/13 – 8U & 11U'                => 'Friday Night Lights',
	'Wood Bat World Series'                               => 'Wood Bat World Series',
	'Turkey Trot Championship'                            => 'Turkey Trot Championship',
	'Fall Classic (7U-11U)'                               => 'Fall Classic',
	'12U Showcase'                                        => '12U Showcase',
	'10/16'                                               => '10/16',
	'SWAT USSSA STATE QUALIFIER'                          => 'SWAT USSSA State Qualifier',
	'Autumn Harvest Series'                               => 'Autumn Harvest Series',
);
foreach ( $titles as $in => $want ) {
	$check( 'title ' . $in, swat_child_clean_event_title( $in ), $want );
}

// Age lists and repeated-title disambiguation.
$check( 'ages two', swat_child_event_title_ages( 'FRIDAY NIGHT LIGHTS 11/6 - 9U & 10U' ), '9U & 10U' );
$check( 'ages one', swat_child_event_title_ages( 'Saturday Night Lights 11/7 - 12U' ), '12U' );
$check( 'ages three', swat_child_event_title_ages( 'Classic 10/2 - 8U, 10U and 12U' ), '8U, 10U & 12U' );
$check( 'ages none', swat_child_event_title_ages( 'Wood Bat World Series' ), '' );
$list = array(
	array( 'event_name' => 'Friday Night Lights 10/16 - 9U & 11U' ),
	array( 'event_name' => 'Saturday Night Lights 10/17 - 8U & 10U' ),
	array( 'event_name' => 'MLB Friday Night Lights 10/23 - 8U & 12U' ),
	array( 'event_name' => 'Friday Night Lights 11/6 - 9U & 10U' ),
	array( 'event_name' => 'Saturday Night Lights 11/7 - 12U' ),
	array( 'event_name' => 'Wood Bat World Series' ),
	array( 'event_name' => 'Friday Night Lights 11/13 - 8U & 11U' ),
	array( 'event_name' => 'Turkey Trot Championship' ),
);
$check( 'directory titles', swat_child_directory_titles( $list ), array(
	'Friday Night Lights · 9U & 11U',
	'Saturday Night Lights · 8U & 10U',
	'MLB Friday Night Lights',
	'Friday Night Lights · 9U & 10U',
	'Saturday Night Lights · 12U',
	'Wood Bat World Series',
	'Friday Night Lights · 8U & 11U',
	'Turkey Trot Championship',
) );

// Waitlist applies to sold out and closed only.
foreach ( array( 'sold_out' => true, 'closed' => true, 'open' => false, 'limited' => false, 'complete' => false, 'cancelled' => false ) as $status => $want ) {
	$check( 'applies ' . $status, swat_child_waitlist_applies( $status ), $want );
}

// URL resolution: Contact fallback -> default -> per-event.
$key = 'friday-night-lights-11-6|2026-11-06';
$check( 'fallback contact', swat_child_waitlist_url( $key, 'Friday Night Lights 11/6' ), 'https://swatourneys.com/contact/?subject=waitlist&event=Friday%20Night%20Lights%2011%2F6' );
$GLOBALS['opt']['swat_child_waitlist'] = array( '_default' => 'https://forms.gle/abc' );
$check( 'default link', swat_child_waitlist_url( $key, 'x' ), 'https://forms.gle/abc' );
$GLOBALS['opt']['swat_child_waitlist'][ $key ] = 'https://play.swatourneys.com/waitlist/42';
$check( 'event link', swat_child_waitlist_url( $key, 'x' ), 'https://play.swatourneys.com/waitlist/42' );
$GLOBALS['opt']['swat_child_waitlist'][ $key ] = 'javascript:alert(1)';
$check( 'unsafe event link ignored', swat_child_waitlist_url( $key, 'x' ), 'https://forms.gle/abc' );
$check( 'sanitize rejects relative', swat_child_sanitize_waitlist_url( '/waitlist' ), '' );
$check( 'sanitize keeps https', swat_child_sanitize_waitlist_url( ' https://a.example/w?x=1 ' ), 'https://a.example/w?x=1' );

// Register action output per status.
$state = fn( $status ) => array( 'status' => $status, 'label' => ucfirst( $status ), 'can_register' => in_array( $status, array( 'open', 'limited' ), true ), 'key' => $key, 'name' => 'Friday Night Lights 11/6' );
$sold  = swat_child_register_action( $state( 'sold_out' ), 'https://play.swatourneys.com/r', '' );
$check( 'sold out shows waitlist', (bool) preg_match( '#^<a class="swat-waitlist-link" href="https://forms\.gle/abc" aria-label="Join the waitlist for Friday Night Lights 11/6">JOIN WAITLIST</a>$#', $sold ), true );
$closed = swat_child_register_action( $state( 'closed' ), 'https://play.swatourneys.com/r', 'swat-btn swat-btn-ghost' );
$check( 'closed keeps caller classes', false !== strpos( $closed, 'class="swat-btn swat-btn-ghost swat-waitlist-link"' ), true );
$check( 'closed never links to registration', false === strpos( $closed, 'play.swatourneys.com/r' ), true );
$check( 'open still registers', swat_child_register_action( $state( 'open' ), 'https://play.swatourneys.com/r', '' ), '<a href="https://play.swatourneys.com/r">REGISTER</a>' );
$check( 'cancelled has no waitlist', false === strpos( swat_child_register_action( $state( 'cancelled' ), 'u', '' ), 'WAITLIST' ), true );
$check( 'complete is empty', swat_child_register_action( $state( 'complete' ), 'u', '' ), '' );

printf( "%d waitlist/title checks, %d failure(s)\n", $n, $fail );
exit( $fail ? 1 : 0 );
