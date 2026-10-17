<?php
/**
 * Local Barlow + Inter, with the parent's Google Fonts request removed.
 *
 * Safety rule: the swap only happens when ALL three font files exist in
 * assets/fonts/. Until then the parent theme's fonts keep loading unchanged and
 * an admin notice explains what is missing. This prevents a half-installed
 * staging copy from falling back to bare system fonts.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Required font files, relative to the child theme directory.
 *
 * @return array<string,string>
 */
function swat_child_font_files() {
	return array(
		'inter-variable' => 'assets/fonts/inter-variable.woff2',
		'barlow-700'     => 'assets/fonts/barlow-700.woff2',
		'barlow-800'     => 'assets/fonts/barlow-800.woff2',
	);
}

/**
 * Files that are missing.
 *
 * @return string[]
 */
function swat_child_missing_fonts() {
	$missing = array();
	foreach ( swat_child_font_files() as $relative ) {
		if ( ! is_readable( get_stylesheet_directory() . '/' . $relative ) ) {
			$missing[] = $relative;
		}
	}
	return $missing;
}

/**
 * Whether the local fonts are installed.
 *
 * @return bool
 */
function swat_child_fonts_ready() {
	static $ready = null;
	if ( null === $ready ) {
		$ready = ( array() === swat_child_missing_fonts() );
	}
	return $ready;
}

/**
 * Replace the parent's Google Fonts stylesheet and load the override CSS last.
 *
 * Runs at priority 100 so every parent stylesheet is already enqueued; the
 * override depends on all enqueued swat-* handles so it prints after them.
 *
 * @return void
 */
function swat_child_enqueue_fonts() {
	if ( ! swat_child_fonts_ready() ) {
		return;
	}

	wp_dequeue_style( 'swat-fonts' );
	wp_deregister_style( 'swat-fonts' );

	$deps = array();
	foreach ( wp_styles()->queue as $handle ) {
		if ( 0 === strpos( (string) $handle, 'swat-' ) && ! in_array( $handle, array( 'swat-child-fonts', 'swat-fonts' ), true ) ) {
			$deps[] = $handle;
		}
	}

	$file = get_stylesheet_directory() . '/assets/css/child-fonts.css';
	wp_enqueue_style(
		'swat-child-fonts',
		SWAT_CHILD_URI . '/assets/css/child-fonts.css',
		$deps,
		is_readable( $file ) ? (string) filemtime( $file ) : SWAT_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'swat_child_enqueue_fonts', 100 );

/**
 * Preload the two faces used above the fold. Barlow 700 covers most headings
 * (the parent's 500/600/700 rules all resolve to it); 800 is only used on some
 * pages, so it is left to load on demand rather than preloaded.
 *
 * @return void
 */
function swat_child_preload_fonts() {
	if ( ! swat_child_fonts_ready() ) {
		return;
	}
	$files = swat_child_font_files();
	foreach ( array( 'inter-variable', 'barlow-700' ) as $slug ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( SWAT_CHILD_URI . '/' . $files[ $slug ] )
		);
	}
}
add_action( 'wp_head', 'swat_child_preload_fonts', 1 );

/**
 * Tell admins when the font files are not installed yet.
 *
 * @return void
 */
function swat_child_fonts_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || swat_child_fonts_ready() ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
		esc_html__( 'SWAT Tournament OS Child:', 'swat-tournament-os-child' ),
		esc_html__( 'Barlow/Inter font files are missing, so the parent theme fonts are still in use. Add:', 'swat-tournament-os-child' ),
		esc_html( implode( ', ', swat_child_missing_fonts() ) )
	);
}
add_action( 'admin_notices', 'swat_child_fonts_admin_notice' );
