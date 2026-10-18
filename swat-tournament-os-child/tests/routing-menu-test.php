<?php
define( 'ABSPATH', __DIR__ );
function add_action() {}
function add_filter() {}
function home_url( $path = '/' ) { return $GLOBALS['test_home'] . $path; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
require __DIR__ . '/../inc/templates.php';
require __DIR__ . '/../inc/menu-links.php';
$cases = array(
 array( 'https://example.test', '/tournaments/', '/tournaments/' ),
 array( 'https://example.test', '/tournaments?x=1', '/tournaments/' ),
 array( 'https://example.test/staging/2948', '/staging/2948/tournaments/', '/tournaments/' ),
 array( 'https://example.test/staging/2948', '/staging/2948/tournaments/autumn-harvest-series/?x=1', '/tournaments/autumn-harvest-series/' ),
 array( 'https://example.test/staging/2948', '/tournaments/', '' ),
 array( 'https://example.test/staging/2948', '/staging/29480/tournaments/', '' ),
 array( 'https://example.test/staging/2948', '/staging/2948/', '/' ),
 array( 'https://example.test', '/weather-updates/', '/weather-updates/' ),
);
$fail = 0;
foreach ( $cases as $case ) {
 $GLOBALS['test_home'] = $case[0];
 if ( swat_child_request_path( $case[1] ) !== $case[2] ) { $fail++; echo 'FAIL path: ' . $case[1] . "\n"; }
}
foreach ( array( 'https://example.test', 'https://example.test/staging/2948' ) as $home ) {
 $GLOBALS['test_home'] = $home;
 $items = array(
  (object) array( 'title' => ' <span>Tournaments</span> ', 'menu_item_parent' => 0, 'url' => 'https://play.swatourneys.com/' ),
  (object) array( 'title' => 'Tournaments', 'menu_item_parent' => 4, 'url' => '/child-link/' ),
  (object) array( 'title' => 'Scores', 'menu_item_parent' => 0, 'url' => '/scores/' ),
 );
 $out = swat_child_tournaments_menu_url( $items );
 if ( $out[0]->url !== $home . '/tournaments/' || $out[1]->url !== '/child-link/' || $out[2]->url !== '/scores/' ) { $fail++; echo "FAIL menu\n"; }
}
printf( "%d path cases + 2 menu cases, %d failure(s)\n", count( $cases ), $fail );
exit( $fail ? 1 : 0 );
