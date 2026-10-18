<?php
/**
 * Template routing and child stylesheet.
 *
 * The parent renders /tournaments/ and /tournaments/{slug}/ by include()-ing its
 * own archive-tournament.php / single-tournament.php from a template_redirect
 * callback at priority 1, which bypasses normal template hierarchy. A child
 * copy of those files would therefore never load. This callback runs at
 * priority 0 and includes the CHILD versions instead, using the same URL rules
 * as the parent. Remove this file's route hook to fall back to parent output.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/** Match theme routes relative to the site URL, including subdirectory staging. */
function swat_child_request_path( $uri ) {
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $base ) {
		if ( $path === $base ) {
			$path = '/';
		} elseif ( 0 === strpos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		} else {
			return ''; // Do not capture requests outside this site's base path.
		}
	}
	return trailingslashit( '/' . ltrim( $path, '/' ) );
}

/**
 * Render the child tournament directory/single templates.
 *
 * @return void
 */
function swat_child_route_templates() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( is_admin() || wp_doing_ajax() || 'GET' !== $method ) {
		return;
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only parsed for its path and matched against fixed patterns.
	$path = swat_child_request_path( $uri );

	if ( '/tournaments/' === $path ) {
		set_query_var( 'swat_virtual', 'tournaments' );
		status_header( 200 );
		include SWAT_CHILD_DIR . '/archive-tournament.php';
		exit;
	}

	if ( preg_match( '#^/tournaments/([^/]+)/$#', $path, $matches ) ) {
		set_query_var( 'swat_virtual', 'tournament' );
		set_query_var( 'swat_event', sanitize_title( $matches[1] ) );
		status_header( 200 );
		include SWAT_CHILD_DIR . '/single-tournament.php';
		exit;
	}
}
add_action( 'template_redirect', 'swat_child_route_templates', 0 );

/**
 * Enqueue the child stylesheet after every parent stylesheet.
 *
 * @return void
 */
function swat_child_enqueue_styles() {
	$deps = array();
	foreach ( wp_styles()->queue as $handle ) {
		if ( 0 === strpos( (string) $handle, 'swat-' ) && ! in_array( $handle, array( 'swat-child', 'swat-child-fonts', 'swat-fonts' ), true ) ) {
			$deps[] = $handle;
		}
	}

	$file = get_stylesheet_directory() . '/assets/css/child.css';
	wp_enqueue_style(
		'swat-child',
		SWAT_CHILD_URI . '/assets/css/child.css',
		$deps,
		is_readable( $file ) ? (string) filemtime( $file ) : SWAT_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'swat_child_enqueue_styles', 99 );
