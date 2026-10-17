<?php
/**
 * Team profile links, sitemap and descriptions (child 1.0.29).
 *
 * Team names on event pages link to /teams/{slug}/ only when the target is
 * certain:
 *  - a name used by one team links to its profile;
 *  - a name shared by several teams (Camel City 9U / 10U / 12U, 1.0.22) links
 *    to that age group's profile only when exactly one team with that name has
 *    played in that age group; otherwise it links to the name's chooser page
 *    (/teams/{name}/), so a team playing up is never linked to another team;
 *  - bracket placeholders ("Division Place 2", "Winner # 1") and names with no
 *    profile are never linked.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

const SWAT_CHILD_TEAM_LINK_CACHE = 'swat_child_team_link_index_v1';

/**
 * Link index from profile games (pure, unit tested).
 *
 * @param array $games Games after swat_child_team_assign_keys().
 * @return array{plain:array<string,bool>,keys:array<string,array<string,array<string,bool>>>}
 */
function swat_child_team_link_index_build( $games ) {
	$index = array( 'plain' => array(), 'keys' => array() );
	foreach ( (array) $games as $g ) {
		foreach ( array( 'away', 'home' ) as $side ) {
			$name = trim( html_entity_decode( (string) ( $g[ $side ] ?? '' ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $name || ( function_exists( 'swat_child_is_placeholder_team' ) && swat_child_is_placeholder_team( $name ) ) ) {
				continue;
			}
			$slug = sanitize_title( $name );
			if ( '' === $slug ) {
				continue;
			}
			$key = (string) ( $g[ $side . '_key' ] ?? '' );
			if ( '' !== $key ) {
				$age = swat_child_division_age( (string) ( $g['division'] ?? '' ) );
				$index['keys'][ $slug ][ $age ][ $key ] = true;
			} else {
				$index['plain'][ $slug ] = true;
			}
		}
	}
	return $index;
}

/**
 * Profile path for a team name as shown on an event page (pure, unit tested).
 *
 * @param array  $index    From swat_child_team_link_index_build().
 * @param string $name     Team name.
 * @param string $division Division of the game ("9U Open"), '' if unknown.
 * @return string Path like "/teams/canes-triad-7u-black/", or '' for no link.
 */
function swat_child_team_path_from_index( $index, $name, $division = '' ) {
	$name = trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
	if ( '' === $name || ( function_exists( 'swat_child_is_placeholder_team' ) && swat_child_is_placeholder_team( $name ) ) ) {
		return '';
	}
	$slug = sanitize_title( $name );
	if ( '' === $slug ) {
		return '';
	}
	if ( isset( $index['keys'][ $slug ] ) ) {
		$age  = swat_child_division_age( (string) $division );
		$keys = $index['keys'][ $slug ][ $age ] ?? array();
		if ( '' !== $age && 1 === count( $keys ) ) {
			return '/teams/' . sanitize_title( (string) key( $keys ) ) . '/';
		}
		return '/teams/' . $slug . '/'; // Chooser: several teams share this name.
	}
	return isset( $index['plain'][ $slug ] ) ? '/teams/' . $slug . '/' : '';
}

/**
 * Profile path for a known profile slug (pure, unit tested), '' when no such
 * profile exists (e.g. an opponent known only from a Playbook team page).
 *
 * @param array  $index From swat_child_team_link_index_build().
 * @param string $key   Profile slug ("canes-triad-7u-black", "camel-city-9u").
 * @return string
 */
function swat_child_team_path_for_key( $index, $key ) {
	$key = sanitize_title( (string) $key );
	if ( '' === $key ) {
		return '';
	}
	if ( isset( $index['plain'][ $key ] ) && ! isset( $index['keys'][ $key ] ) ) {
		return '/teams/' . $key . '/';
	}
	foreach ( (array) ( $index['keys'] ?? array() ) as $ages ) {
		foreach ( $ages as $keys ) {
			if ( isset( $keys[ $key ] ) ) {
				return '/teams/' . $key . '/';
			}
		}
	}
	return '';
}

/**
 * Opponent name on a team profile as a link when that profile exists.
 *
 * @param string $key   Opponent profile slug.
 * @param string $label Opponent name to show.
 * @return string HTML.
 */
function swat_child_team_key_link_html( $key, $label ) {
	$path = swat_child_team_path_for_key( swat_child_team_link_index(), $key );
	if ( '' === $path ) {
		return esc_html( $label );
	}
	return '<a class="swat-team-link" href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * Cached link index (15 minutes; cleared when a season export is uploaded).
 *
 * @return array
 */
function swat_child_team_link_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}
	$cached = get_transient( SWAT_CHILD_TEAM_LINK_CACHE );
	if ( is_array( $cached ) && isset( $cached['plain'], $cached['keys'] ) ) {
		$index = $cached;
		return $index;
	}
	$index = swat_child_team_link_index_build( function_exists( 'swat_child_team_profile_games' ) ? swat_child_team_profile_games() : array() );
	set_transient( SWAT_CHILD_TEAM_LINK_CACHE, $index, 15 * MINUTE_IN_SECONDS );
	return $index;
}

/**
 * Profile URL for a team name, '' when it should not be linked.
 *
 * @param string $name     Team name.
 * @param string $division Division of the game.
 * @return string
 */
function swat_child_team_url( $name, $division = '' ) {
	$path = swat_child_team_path_from_index( swat_child_team_link_index(), $name, $division );
	return '' === $path ? '' : home_url( $path );
}

/**
 * Team name as a profile link (or plain escaped text when not linkable).
 *
 * @param string $name     Raw team name (used to find the profile).
 * @param string $label    Text to show (e.g. "#2 Seed" for placeholders).
 * @param string $division Division of the game.
 * @return string HTML.
 */
function swat_child_team_link_html( $name, $label, $division = '' ) {
	$url = swat_child_team_url( $name, $division );
	if ( '' === $url ) {
		return esc_html( $label );
	}
	return '<a class="swat-team-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/* ---------- Sitemap ---------- */

/**
 * Sitemap entries for /teams/ and every profile (pure, unit tested).
 *
 * @param array $rows Directory rows (slug, last).
 * @return array<int,array{loc:string,lastmod?:string}>
 */
function swat_child_team_sitemap_entries( $rows ) {
	$entries = array( array( 'loc' => home_url( '/teams/' ) ) );
	foreach ( (array) $rows as $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		if ( '' === $slug ) {
			continue;
		}
		$entry = array( 'loc' => home_url( '/teams/' . $slug . '/' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['last'] ?? '' ) ) ) {
			$entry['lastmod'] = $row['last'];
		}
		$entries[] = $entry;
	}
	return $entries;
}

/**
 * Register the "teams" provider with the core WordPress sitemap
 * (/wp-sitemap-teams-1.xml, listed in /wp-sitemap.xml).
 *
 * @return void
 */
function swat_child_team_sitemap_register() {
	if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
		return;
	}
	if ( ! class_exists( 'SWAT_Child_Team_Sitemap' ) ) {
		/**
		 * Core sitemap provider for team pages.
		 */
		class SWAT_Child_Team_Sitemap extends WP_Sitemaps_Provider { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
			/** Constructor. */
			public function __construct() {
				$this->name        = 'teams';
				$this->object_type = 'teams';
			}

			/**
			 * URL list.
			 *
			 * @param int    $page_num       Page.
			 * @param string $object_subtype Unused.
			 * @return array
			 */
			public function get_url_list( $page_num, $object_subtype = '' ) {
				$per     = (int) wp_sitemaps_get_max_urls( $this->object_type );
				$entries = swat_child_team_sitemap_entries( swat_child_team_directory_rows() );
				return array_slice( $entries, max( 0, ( (int) $page_num - 1 ) * $per ), $per );
			}

			/**
			 * Page count.
			 *
			 * @param string $object_subtype Unused.
			 * @return int
			 */
			public function get_max_num_pages( $object_subtype = '' ) {
				$per = max( 1, (int) wp_sitemaps_get_max_urls( $this->object_type ) );
				return (int) ceil( ( count( swat_child_team_directory_rows() ) + 1 ) / $per );
			}
		}
	}
	wp_register_sitemap_provider( 'teams', new SWAT_Child_Team_Sitemap() );
}
add_action( 'init', 'swat_child_team_sitemap_register' );

/* ---------- Description + canonical ---------- */

/**
 * Meta description for a team view (pure, unit tested).
 *
 * @param array $view swat_child_team_view global.
 * @return string '' when none.
 */
function swat_child_team_description( $view ) {
	if ( ! is_array( $view ) ) {
		return '';
	}
	if ( 'directory' === ( $view['mode'] ?? '' ) ) {
		return 'Season records, results and upcoming games for every youth baseball team that has played a SWAT Tournaments event in Clemmons, NC.';
	}
	$profile = $view['profile'] ?? null;
	if ( ! is_array( $profile ) || empty( $profile['name'] ) ) {
		return '';
	}
	if ( ! empty( $profile['chooser'] ) ) {
		return 'More than one youth baseball team plays as ' . $profile['name'] . ' at SWAT Tournaments. Choose the age group for records, results and upcoming games.';
	}
	$season = $profile['seasons'] ? reset( $profile['seasons'] ) : null;
	if ( $season && ! empty( $season['record']['gp'] ) ) {
		$r = $season['record'];
		return sprintf( '%1$s: %2$d-%3$d-%4$d in %5$s at SWAT Tournaments. Results, event records and upcoming games in Clemmons, NC.', $profile['name'], $r['w'], $r['l'], $r['t'], $season['label'] );
	}
	return $profile['name'] . ': upcoming games and results at SWAT Tournaments in Clemmons, NC.';
}

/**
 * Print description and canonical on team pages, unless an SEO plugin does.
 *
 * @return void
 */
function swat_child_team_head_meta() {
	$view = $GLOBALS['swat_child_team_view'] ?? null;
	if ( ! $view || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );
	$description = swat_child_team_description( $view );
	if ( '' !== $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( 'directory' === $view['mode'] ) {
		echo '<link rel="canonical" href="' . esc_url( home_url( '/teams/' ) ) . '">' . "\n";
	} elseif ( ! empty( $view['profile'] ) && ! empty( $view['slug'] ) ) {
		// ?season= views share the profile's canonical URL.
		echo '<link rel="canonical" href="' . esc_url( home_url( '/teams/' . sanitize_title( $view['slug'] ) . '/' ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'swat_child_team_head_meta', 1 );
