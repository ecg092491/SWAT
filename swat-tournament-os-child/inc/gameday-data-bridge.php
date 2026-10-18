<?php
/**
 * Game Day data bridge for event-specific sync metadata.
 *
 * The parent /swat/v1/games facade intentionally scopes games by event, but
 * currently sets last_sync to null in that branch. Use the event-specific sync registry and its Unix timestamp. Do not borrow
 * the active event timestamp or hide the last failed attempt.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the upstream Playbook sync timestamp for one Game Day event.
 *
 * @param string $event_slug Event slug.
 * @return string|null
 */
function swat_child_event_sync_timestamp( $event_slug ) {
	$event_slug = sanitize_title( (string) $event_slug );
	if ( ! $event_slug ) {
		return null;
	}

	foreach ( (array) get_option( 'swat_playbook_multi_event_sync_v1', array() ) as $event ) {
		if ( sanitize_title( (string) ( $event['event_name'] ?? '' ) ) !== $event_slug ) { continue; }
		$ts = (int) ( $event['last_success_ts'] ?? 0 );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
	return null;
}

/**
 * Fill the missing last_sync field on the public SWAT facade.
 *
 * @param WP_REST_Response|WP_Error $response REST response.
 * @param array                     $handler  Route handler.
 * @param WP_REST_Request           $request  REST request.
 * @return WP_REST_Response|WP_Error
 */
function swat_child_fill_event_last_sync( $response, $handler, $request ) {
	if ( '/swat/v1/games' !== $request->get_route() || is_wp_error( $response ) ) {
		return $response;
	}

	$event_slug = sanitize_title( (string) $request->get_param( 'event' ) );
	if ( ! $event_slug ) {
		return $response;
	}

	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	$sync = swat_child_event_sync_timestamp( $event_slug );
	if ( $sync ) { $data['last_sync'] = $sync; }
	foreach ( (array) get_option( 'swat_playbook_multi_event_sync_v1', array() ) as $event ) {
		if ( sanitize_title( (string) ( $event['event_name'] ?? '' ) ) !== $event_slug ) { continue; }
		$data['last_success_ts'] = (int) ( $event['last_success_ts'] ?? 0 );
		$data['sync_status'] = 'error' === ( $event['status'] ?? '' ) || ( isset( $event['result']['ok'] ) && ! $event['result']['ok'] ) ? 'error' : 'success';
		$data['source_stale'] = (int) ( $event['last_attempt_ts'] ?? 0 ) > $data['last_success_ts'];
		break;
	}
	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'swat_child_fill_event_last_sync', 10, 3 );
