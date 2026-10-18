<?php
/**
 * Menu link fixes.
 *
 * The top-level "Tournaments" menu item must open the all-tournaments directory
 * (/tournaments/), whatever URL the saved WordPress menu item carries (a page,
 * an updates page, or a #tournaments anchor). Sub-items are left alone.
 *
 * @package SWAT_Tournament_OS_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Point the top-level Tournaments menu item at the tournaments directory.
 *
 * @param array $items Sorted nav menu item objects.
 * @return array
 */
function swat_child_tournaments_menu_url( $items ) {
	if ( ! is_array( $items ) ) {
		return $items;
	}
	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! empty( $item->menu_item_parent ) ) {
			continue;
		}
		$title = strtolower( trim( wp_strip_all_tags( (string) ( $item->title ?? '' ) ) ) );
		if ( 'tournaments' === $title ) {
			$item->url = home_url( '/tournaments/' );
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'swat_child_tournaments_menu_url', 20 );
