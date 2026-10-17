<?php
/**
 * SWAT Tournament OS Child bootstrap.
 *
 * WordPress loads this file BEFORE the parent's functions.php, so nothing here
 * calls parent functions at load time. Parent helpers are only used inside
 * hooks and templates, which run after both themes are loaded.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

define( 'SWAT_CHILD_VERSION', '1.0.35-review' );
define( 'SWAT_CHILD_DIR', __DIR__ );

/**
 * URL of THIS child theme's folder. Captured before the filter below is added.
 * Use this (not get_stylesheet_directory_uri()) for the child's own assets.
 */
define( 'SWAT_CHILD_URI', untrailingslashit( get_stylesheet_directory_uri() ) );

/**
 * Make the parent's asset URLs resolve to the PARENT folder.
 *
 * The parent theme builds every asset URL (CSS, JS, logo, PWA manifest/icons)
 * from get_stylesheet_directory_uri() - via the constant SWAT_OS_URI and a few
 * direct calls. Under a child theme that function returns the CHILD folder, so
 * every parent asset would 404 (unstyled pages, dead Game Day scripts). This
 * child's functions.php loads before the parent's, so the filter is already in
 * place when SWAT_OS_URI is defined. Nothing in the parent is edited.
 *
 * Side effect: get_stylesheet_directory_uri() reports the parent URL while this
 * child is active. The child's own assets use SWAT_CHILD_URI instead.
 *
 * @param string $uri            Child stylesheet directory URI.
 * @param string $stylesheet     Active stylesheet (child folder name).
 * @param string $theme_root_uri Themes directory URI.
 * @return string
 */
function swat_child_parent_asset_uri( $uri, $stylesheet, $theme_root_uri ) {
	$template = get_template();
	if ( '' === $template || $template === $stylesheet ) {
		return $uri;
	}
	return untrailingslashit( $theme_root_uri ) . '/' . str_replace( '%2F', '/', rawurlencode( $template ) );
}
add_filter( 'stylesheet_directory_uri', 'swat_child_parent_asset_uri', 10, 3 );

require_once SWAT_CHILD_DIR . '/inc/registration-status.php';
require_once SWAT_CHILD_DIR . '/inc/waitlist.php';
require_once SWAT_CHILD_DIR . '/inc/event-display.php';
require_once SWAT_CHILD_DIR . '/inc/registration-admin.php';
require_once SWAT_CHILD_DIR . '/inc/shared-design.php';
require_once SWAT_CHILD_DIR . '/inc/fonts.php';
require_once SWAT_CHILD_DIR . '/inc/templates.php';
require_once SWAT_CHILD_DIR . '/inc/bracket-schedule.php';
require_once SWAT_CHILD_DIR . '/inc/menu-links.php';
require_once SWAT_CHILD_DIR . '/inc/gameday-field-ux.php';
require_once SWAT_CHILD_DIR . '/inc/gameday-data-bridge.php';
require_once SWAT_CHILD_DIR . '/inc/gameday-hub.php';
require_once SWAT_CHILD_DIR . '/inc/sponsors.php'; // 1.0.33: SWAT Operations -> Sponsors.
require_once SWAT_CHILD_DIR . '/inc/home-data.php';
require_once SWAT_CHILD_DIR . '/inc/team-finder.php';
require_once SWAT_CHILD_DIR . '/inc/team-profile.php';
require_once SWAT_CHILD_DIR . '/inc/team-links.php';
require_once SWAT_CHILD_DIR . '/inc/team-playbook.php';
require_once SWAT_CHILD_DIR . '/inc/season-export.php';
require_once SWAT_CHILD_DIR . '/inc/playbook-source.php';
// 1.0.19: the homepage loads assets/css/home.css + assets/js/home.js (inc/home-data.php);
// home-refined.css is retired.

function swat_child_enqueue_schedule_actions() {
    $file = SWAT_CHILD_DIR . '/assets/css/schedule-actions.css';
    wp_enqueue_style( 'swat-schedule-actions', SWAT_CHILD_URI . '/assets/css/schedule-actions.css', array(), (string) filemtime( $file ) );
}
add_action( 'wp_enqueue_scripts', 'swat_child_enqueue_schedule_actions', 300 );

/**
 * Warn administrators if the parent theme's helpers are not available.
 *
 * @return void
 */
function swat_child_parent_notice() {
	if ( ! current_user_can( 'manage_options' ) || function_exists( 'swat_os_event_directory' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
		esc_html__( 'SWAT Tournament OS Child:', 'swat-tournament-os-child' ),
		esc_html__( 'The parent theme SWAT Tournament OS (swat-tournament-os-master) is not loaded. Install it and keep it in place; this child theme depends on it.', 'swat-tournament-os-child' )
	);
}
add_action( 'admin_notices', 'swat_child_parent_notice' );
