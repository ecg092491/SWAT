<?php
/**
 * Verifies the asset-URL fix without WordPress: with the child active, the
 * parent's get_stylesheet_directory_uri() must resolve to the PARENT folder,
 * while SWAT_CHILD_URI stays the child folder.  Run: php tests/uri-filter-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['__f'] = array();
function add_filter( $tag, $cb ) { $GLOBALS['__f'][ $tag ][] = $cb; }
function add_action() {}
function apply_filters( $tag, $value, ...$args ) { foreach ( $GLOBALS['__f'][ $tag ] ?? array() as $cb ) { $value = $cb( $value, ...$args ); } return $value; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function get_template() { return 'swat-tournament-os-master'; }
function get_stylesheet() { return $GLOBALS['__stylesheet']; }
function get_theme_root_uri() { return 'https://example.test/wp-content/themes'; }
// Same shape as WordPress core's function.
function get_stylesheet_directory_uri() {
	$stylesheet = str_replace( '%2F', '/', rawurlencode( get_stylesheet() ) );
	$root       = get_theme_root_uri();
	return apply_filters( 'stylesheet_directory_uri', "$root/$stylesheet", $stylesheet, $root );
}
function current_user_can() { return false; }
function esc_html__( $s ) { return $s; }
function __( $s ) { return $s; }
function sanitize_title( $s ) { return $s; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }

$fail = 0;
$ok   = static function ( $cond, $msg ) use ( &$fail ) { if ( ! $cond ) { $fail++; echo "FAIL: $msg\n"; } else { echo "ok:   $msg\n"; } };

$GLOBALS['__stylesheet'] = 'swat-tournament-os-child';
// Load only the bootstrap's URL logic (the part of functions.php before the requires).
$src = file_get_contents( __DIR__ . '/../functions.php' );
$head = substr( $src, 0, strpos( $src, "require_once SWAT_CHILD_DIR" ) );
eval( '?>' . $head );

$ok( SWAT_CHILD_URI === 'https://example.test/wp-content/themes/swat-tournament-os-child', 'SWAT_CHILD_URI is the child folder' );
$ok( get_stylesheet_directory_uri() === 'https://example.test/wp-content/themes/swat-tournament-os-master', 'get_stylesheet_directory_uri() (what the parent uses for SWAT_OS_URI) now returns the PARENT folder' );

// If the parent itself is active (no child), the filter must do nothing.
$GLOBALS['__stylesheet'] = 'swat-tournament-os-master';
$ok( get_stylesheet_directory_uri() === 'https://example.test/wp-content/themes/swat-tournament-os-master', 'unchanged when the parent is the active theme' );

echo $fail ? "$fail failure(s)\n" : "all passed\n";
exit( $fail ? 1 : 0 );
