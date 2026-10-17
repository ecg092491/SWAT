<?php
/** Scoped presentation update; no endpoints or plugin registrations changed. */
defined( 'ABSPATH' ) || exit;
add_filter( 'script_loader_src', static function ( $src, $handle ) {
    if ( 'swat-my-weekend' !== $handle ) return $src;
    $file = SWAT_CHILD_DIR . '/assets/js/swat-my-weekend.js';
    return SWAT_CHILD_URI . '/assets/js/swat-my-weekend.js?ver=' . filemtime( $file );
}, 30, 2 );
add_action( 'wp_enqueue_scripts', static function () {
    if ( ! function_exists( 'swat_os_is_gameday_request' ) || ! swat_os_is_gameday_request() ) return;
    wp_enqueue_style( 'swat-field-ux', SWAT_CHILD_URI . '/assets/css/swat-field-ux.css', array( 'swat-child-fonts', 'swat-child' ), filemtime( SWAT_CHILD_DIR . '/assets/css/swat-field-ux.css' ) );
    wp_enqueue_script( 'swat-field-ux', SWAT_CHILD_URI . '/assets/js/swat-field-ux.js', array( 'swat-gameday', 'swat-my-weekend' ), filemtime( SWAT_CHILD_DIR . '/assets/js/swat-field-ux.js' ), true );
}, 110 );
