<?php
/** Shared presentation only; no route, CPT or settings changes. */
defined( 'ABSPATH' ) || exit;
function swat_child_shared_surface() {
    return is_front_page() || ( function_exists( 'swat_os_is_gameday_request' ) && swat_os_is_gameday_request() );
}
function swat_child_shared_assets() {
    if ( ! swat_child_shared_surface() ) { return; }
    wp_enqueue_style( 'swat-shared-fonts', SWAT_CHILD_URI . '/assets/css/shared-fonts.css', array(), (string) filemtime( SWAT_CHILD_DIR . '/assets/css/shared-fonts.css' ) );
    wp_enqueue_style( 'swat-shared-tokens', SWAT_CHILD_URI . '/style.css', array( 'swat-shared-fonts' ), (string) filemtime( SWAT_CHILD_DIR . '/style.css' ) );
    wp_enqueue_style( 'swat-shared-components', SWAT_CHILD_URI . '/assets/css/shared-components.css', array( 'swat-shared-tokens' ), (string) filemtime( SWAT_CHILD_DIR . '/assets/css/shared-components.css' ) );
    wp_enqueue_script( 'swat-appearance-boot', SWAT_CHILD_URI . '/assets/js/appearance-boot.js', array(), (string) filemtime( SWAT_CHILD_DIR . '/assets/js/appearance-boot.js' ), false );
    wp_enqueue_script( 'swat-shared-ui', SWAT_CHILD_URI . '/assets/js/shared-ui.js', array(), (string) filemtime( SWAT_CHILD_DIR . '/assets/js/shared-ui.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'swat_child_shared_assets', 110 );
function swat_child_shared_body_class( $classes ) {
    if ( swat_child_shared_surface() ) { $classes[] = 'swat-shared-ui'; }
    return $classes;
}
add_filter( 'body_class', 'swat_child_shared_body_class' );
