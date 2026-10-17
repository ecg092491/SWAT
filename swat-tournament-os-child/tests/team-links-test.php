<?php
/**
 * Team profile links, sitemap entries and descriptions (inc/team-links.php,
 * 1.0.29), using the real 2026 Fall Playbook export. Run: php tests/team-links-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SWAT_CHILD_DIR', dirname( __DIR__ ) );
define( 'MINUTE_IN_SECONDS', 60 );
function add_filter() {} function add_action() {} function __( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( preg_replace( "/['’]/u", '', (string) $s ) ) ), '-' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function home_url( $p = '/' ) { return 'https://swatourneys.com' . $p; }
function get_transient( $k ) { return $GLOBALS['t'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['t'][ $k ] = $v; }
require SWAT_CHILD_DIR . '/inc/team-finder.php';
require SWAT_CHILD_DIR . '/inc/team-profile.php';
require SWAT_CHILD_DIR . '/inc/season-export.php';
require SWAT_CHILD_DIR . '/inc/team-links.php';

$fail = 0; $n = 0;
$check = static function ( $name, $ok ) use ( &$fail, &$n ) { ++$n; if ( ! $ok ) { ++$fail; echo "FAIL $name\n"; } };

$games = swat_child_team_assign_keys( swat_child_export_parse_csv( file_get_contents( SWAT_CHILD_DIR . '/data/playbook-season-2026-fall.csv' ) )['games'] );
$index = swat_child_team_link_index_build( $games );

// Single-name teams link to their profile, also when playing up a division.
$check( 'Canes Triad 7U Black links to its profile', '/teams/canes-triad-7u-black/' === swat_child_team_path_from_index( $index, 'Canes Triad 7U Black', '7U Open' ) );
$check( 'playing up keeps the same profile', '/teams/canes-triad-7u-black/' === swat_child_team_path_from_index( $index, 'Canes Triad 7U Black', '8U Open' ) );
$check( 'no division still links a single-name team', '/teams/canes-triad-7u-black/' === swat_child_team_path_from_index( $index, 'Canes Triad 7U Black' ) );

// Placeholders and unknown names are never links.
foreach ( array( 'Division Place 2', 'Winner # 1', 'Loser #3', 'TBD', '', 'Pool A #1' ) as $ph ) {
	$check( "placeholder not linked: '$ph'", '' === swat_child_team_path_from_index( $index, $ph, '8U Open' ) );
}
$check( 'unknown team not linked', '' === swat_child_team_path_from_index( $index, 'Nobody Here 9U', '9U Open' ) );

// Shared names: every game side resolves to its own team or to the chooser, never another team.
$shared = array_keys( $index['keys'] );
$check( 'export has shared names (e.g. Camel City)', in_array( 'camel-city', $shared, true ) && count( $shared ) >= 5 );
$wrong = 0; $direct = 0; $chooser = 0;
foreach ( $games as $g ) {
	foreach ( array( 'away', 'home' ) as $side ) {
		$slug = sanitize_title( $g[ $side ] );
		if ( ! isset( $index['keys'][ $slug ] ) ) {
			continue;
		}
		$path = swat_child_team_path_from_index( $index, $g[ $side ], $g['division'] );
		if ( '/teams/' . $slug . '/' === $path ) {
			++$chooser;
		} elseif ( '/teams/' . $g[ $side . '_key' ] . '/' === $path ) {
			++$direct;
		} else {
			++$wrong;
		}
	}
}
$check( 'shared names never link to a different team', 0 === $wrong );
$check( 'shared names mostly link straight to the team', $direct > $chooser && $direct > 0 );
// Ambiguous age group (two teams named X in the same age): chooser.
$amb = array( 'plain' => array(), 'keys' => array( 'x' => array( '9U' => array( 'x-9u' => true, 'x-10u' => true ) ) ) );
$check( 'two teams with one name in one age group link to the chooser', '/teams/x/' === swat_child_team_path_from_index( $amb, 'X', '9U Open' ) );
$check( 'shared name with unknown age links to the chooser', '/teams/camel-city/' === swat_child_team_path_from_index( $index, 'Camel City', '' ) );
$check( 'HTML entities decoded before matching', swat_child_team_path_from_index( array( 'plain' => array( 'st-johns-9u' => true ), 'keys' => array() ), 'St. John&#039;s 9U' ) === '/teams/st-johns-9u/' );

// Opponent keys on team pages.
$check( 'known profile key links', '/teams/canes-triad-7u-black/' === swat_child_team_path_for_key( $index, 'canes-triad-7u-black' ) );
$some_key = (string) key( reset( $index['keys']['camel-city'] ) );
$check( 'age-suffixed key links', '/teams/' . $some_key . '/' === swat_child_team_path_for_key( $index, $some_key ) );
$check( 'shared base name is not a profile key', '' === swat_child_team_path_for_key( $index, 'camel-city' ) );
$check( 'unknown opponent key not linked', '' === swat_child_team_path_for_key( $index, 'playbook-only-team' ) );
$profile = swat_child_team_profile_build( $games, 'canes-triad-7u-black', '2026-10-09' );
$opp_keys = array();
foreach ( $profile['seasons']['2026-fall']['games'] as $item ) {
	$opp_keys[] = $item['opponent_key'];
}
$check( 'profile games carry opponent keys that all resolve', $opp_keys && ! array_filter( $opp_keys, static function ( $k ) use ( $index ) { return '' === swat_child_team_path_for_key( $index, $k ); } ) );
$check( 'Canes record unchanged by link work', '15-2-0' === implode( '-', array( $profile['seasons']['2026-fall']['record']['w'], $profile['seasons']['2026-fall']['record']['l'], $profile['seasons']['2026-fall']['record']['t'] ) ) );

// Link HTML escapes the label.
$GLOBALS['t'][ SWAT_CHILD_TEAM_LINK_CACHE ] = array( 'plain' => array( 'a-b' => true ), 'keys' => array() );
$check( 'link html', '<a class="swat-team-link" href="https://swatourneys.com/teams/a-b/">A &lt;B&gt;</a>' === swat_child_team_link_html( 'A B', 'A <B>' ) );
$check( 'non-link html is escaped text', '#2 Seed &amp; up' === swat_child_team_link_html( 'Division Place 2', '#2 Seed & up', '8U' ) );

// Sitemap entries.
$entries = swat_child_team_sitemap_entries( array( array( 'slug' => 'canes-triad-7u-black', 'last' => '2026-10-04' ), array( 'slug' => 'camel-city-9u', 'last' => '' ), array( 'slug' => '' ) ) );
$check( 'sitemap: directory + 2 profiles', 3 === count( $entries ) && 'https://swatourneys.com/teams/' === $entries[0]['loc'] );
$check( 'sitemap: lastmod only for real dates', '2026-10-04' === $entries[1]['lastmod'] && ! isset( $entries[2]['lastmod'] ) );

// Descriptions.
$check( 'description: profile with record', 'Canes Triad 7U Black: 15-2-0 in 2026 Fall at SWAT Tournaments. Results, event records and upcoming games in Clemmons, NC.' === swat_child_team_description( array( 'mode' => 'profile', 'profile' => $profile ) ) );
$check( 'description: directory', 0 === strpos( swat_child_team_description( array( 'mode' => 'directory' ) ), 'Season records, results' ) );
$check( 'description: chooser', false !== strpos( swat_child_team_description( array( 'mode' => 'profile', 'profile' => array( 'chooser' => array( 1 ), 'name' => 'Camel City' ) ) ), 'Choose the age group' ) );
$check( 'description: not found', '' === swat_child_team_description( array( 'mode' => 'profile', 'profile' => null ) ) );

// Templates are wired.
$event = file_get_contents( SWAT_CHILD_DIR . '/single-tournament.php' );
$check( 'event page: cards, results and standings use team links (5 places)', 5 === substr_count( $event, '$team_html(' ) && false === strpos( $event, 'esc_html( $away )' ) && false === strpos( $event, "esc_html( \$row['team'] ?? '' )" ) );
$check( 'header: Teams after Tournaments', false !== strpos( file_get_contents( SWAT_CHILD_DIR . '/header.php' ), "Tournaments</a></li>\n\t\t\t\t\t<li><a href=\"<?php echo esc_url( home_url( '/teams/' ) ); ?>\">Teams</a></li>" ) );
$check( 'homepage: Browse all teams link', false !== strpos( file_get_contents( SWAT_CHILD_DIR . '/front-page.php' ), "home_url( '/teams/' )" ) );
$check( 'team page: opponents linked', 2 === substr_count( file_get_contents( SWAT_CHILD_DIR . '/team-profile.php' ), 'swat_child_team_key_link_html(' ) );

echo "$n team link checks, $fail failure(s)\n";
exit( $fail ? 1 : 0 );
