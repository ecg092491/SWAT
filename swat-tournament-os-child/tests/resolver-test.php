<?php
/**
 * Replays tests/resolver-cases.json against swat_child_resolve_status().
 * Run in any PHP 8 environment, no WordPress needed:
 *   php tests/resolver-test.php
 * Exit code 0 = all pass.
 */

define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function add_action() {}
function __( $s ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }

require __DIR__ . '/../inc/registration-status.php';

$cases = json_decode( file_get_contents( __DIR__ . '/resolver-cases.json' ), true );
$fail  = 0;
foreach ( $cases as $n => $case ) {
	list( $status, $source ) = swat_child_resolve_status( $case['input'] );
	if ( $status !== $case['status'] || $source !== $case['source'] ) {
		$fail++;
		printf( "FAIL #%d %s => got %s/%s expected %s/%s\n", $n, json_encode( $case['input'] ), $status, $source, $case['status'], $case['source'] );
	}
}

// Record sanitising.
$checks = array(
	array( swat_child_sanitize_record( array( 'status' => 'Sold_Out', 'deadline' => '2026-10-07', 'deadline_time' => '17:30' ) ), array( 'status' => 'sold_out', 'deadline' => '2026-10-07', 'deadline_time' => '17:30' ) ),
	array( swat_child_sanitize_record( array( 'status' => 'bogus', 'deadline' => '2026-02-31', 'deadline_time' => '25:00' ) ), array( 'status' => '', 'deadline' => '', 'deadline_time' => '' ) ),
	array( swat_child_sanitize_record( array( 'deadline_time' => '17:30' ) ), array( 'status' => '', 'deadline' => '', 'deadline_time' => '' ) ),
	array( swat_child_sanitize_record( 'not-an-array' ), array( 'status' => '', 'deadline' => '', 'deadline_time' => '' ) ),
);
foreach ( $checks as $n => $pair ) {
	if ( $pair[0] !== $pair[1] ) {
		$fail++;
		printf( "FAIL sanitize #%d got %s\n", $n, json_encode( $pair[0] ) );
	}
}

// Public copy must not retain SOLD OUT as an unavailable action.
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
$state = array( 'status' => 'sold_out', 'label' => 'Sold Out', 'can_register' => false );
if ( false !== stripos( swat_child_status_badge( $state ), 'Sold Out' ) || false !== stripos( swat_child_register_action( $state, '', '' ), 'SOLD OUT' ) ) {
    ++$fail;
    echo "FAIL public Sold Out copy persists\n";
}

printf( "%d resolver cases + %d sanitize checks, %d failure(s)\n", count( $cases ), count( $checks ), $fail );
exit( $fail ? 1 : 0 );
