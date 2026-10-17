<?php
/**
 * Game Day event header (child 1.0.17, Game Day Hub).
 *
 * Shown on every Game Day view so families always know which tournament they
 * are looking at. Keeps the parent's data-swat-event-switch contract and its
 * This weekend / Upcoming / Past results grouping.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

$current_event = isset( $args['current_event'] ) && is_array( $args['current_event'] ) ? $args['current_event'] : array();
$games         = isset( $args['games'] ) && is_array( $args['games'] ) ? $args['games'] : array();
$is_action     = ! empty( $args['is_action'] );

$name = sanitize_text_field( (string) ( $current_event['name'] ?? '' ) );
$name = '' !== $name ? $name : 'SWAT Game Day';

$event_date_ts = static function ( $date ) {
	if ( ! $date ) {
		return false;
	}
	$value = DateTimeImmutable::createFromFormat( '!Y-m-d', substr( (string) $date, 0, 10 ), wp_timezone() );
	return $value instanceof DateTimeImmutable ? $value->getTimestamp() : false;
};
$date_range = static function ( $start_raw, $end_raw ) use ( $event_date_ts ) {
	$start = $event_date_ts( $start_raw );
	$end   = $event_date_ts( $end_raw );
	if ( ! $start ) {
		return '';
	}
	if ( ! $end || wp_date( 'Y-m-d', $start ) === wp_date( 'Y-m-d', $end ) ) {
		return wp_date( 'D, M j', $start );
	}
	if ( wp_date( 'Y-m', $start ) === wp_date( 'Y-m', $end ) ) {
		return wp_date( 'M j', $start ) . '–' . wp_date( 'j', $end );
	}
	return wp_date( 'M j', $start ) . ' – ' . wp_date( 'M j', $end );
};

$divisions = array();
foreach ( $games as $game ) {
	$division = trim( sanitize_text_field( (string) ( $game['division'] ?? '' ) ) );
	if ( '' !== $division ) {
		$divisions[ preg_replace( '/\s+Open$/i', '', $division ) ] = true;
	}
}
$divisions = array_keys( $divisions );
natsort( $divisions );

$live_count = count( array_filter( $games, 'swat_os_game_is_live' ) );
$event_slug = sanitize_title( (string) ( $current_event['slug'] ?? '' ) );
$event_location = function_exists( 'gdc_gameday_location' ) ? gdc_gameday_location( $event_slug, 'Clemmons, NC' ) : 'Clemmons, NC';
$meta       = array_filter( array( $date_range( $current_event['start'] ?? '', $current_event['end'] ?? '' ), $event_location ) );
$choices    = function_exists( 'swat_os_event_directory' ) ? swat_os_event_directory() : array();
$lower      = strtolower( $name );
$variant    = false !== strpos( $lower, 'friday night lights' ) ? ' is-fnl' : ( false !== strpos( $lower, 'saturday night lights' ) ? ' is-snl' : '' );

$today_key  = current_datetime()->format( 'Y-m-d' );
$week_start = wp_date( 'Y-m-d', strtotime( 'friday this week', current_time( 'timestamp' ) ) );
$week_end   = wp_date( 'Y-m-d', strtotime( 'sunday this week', current_time( 'timestamp' ) ) );
$groups     = array( 'THIS WEEKEND' => array(), 'UPCOMING' => array(), 'PAST RESULTS' => array() );
foreach ( $choices as $choice ) {
	$choice_start = (string) ( $choice['start_date'] ?? '' );
	$choice_end   = (string) ( $choice['end_date'] ?? $choice_start );
	if ( $choice_end < $today_key ) {
		$groups['PAST RESULTS'][] = $choice;
	} elseif ( $choice_start <= $week_end && $choice_end >= $week_start ) {
		$groups['THIS WEEKEND'][] = $choice;
	} else {
		$groups['UPCOMING'][] = $choice;
	}
}
$title_tag = $is_action ? 'p' : 'h1';
?>
<header class="swat-event-head<?php echo esc_attr( $variant ); ?>" aria-label="Tournament">
		<div class="swat-event-name-row">
            <<?php echo esc_html( $title_tag ); ?> class="swat-event-title" id="swat-home-event-title"><?php echo esc_html( $name ); ?></<?php echo esc_html( $title_tag ); ?>>
            <?php if ( count( $choices ) > 1 && ! $is_action ) : ?><span class="swat-event-chevron" aria-hidden="true">▾</span><?php endif; ?>
	<?php if ( count( $choices ) > 1 && ! $is_action ) : ?>
		<label class="swat-event-switch"><span class="screen-reader-text">Change tournament</span>
			<select data-swat-event-switch aria-label="Change tournament" aria-describedby="swat-home-event-title">
				<?php
				$has_selected = false;
				foreach ( $choices as $choice ) {
					$has_selected = $has_selected || sanitize_title( (string) ( $choice['slug'] ?? '' ) ) === $event_slug;
				}
				if ( ! $has_selected ) :
					?>
					<option value="" selected><?php echo esc_html( $name ); ?></option>
				<?php endif; ?>
				<?php
				foreach ( $groups as $group_label => $group_events ) :
					if ( ! $group_events ) {
						continue;
					}
					?>
					<optgroup label="<?php echo esc_attr( $group_label ); ?>">
						<?php
						foreach ( $group_events as $choice ) :
							$choice_slug = sanitize_title( (string) ( $choice['slug'] ?? '' ) );
							$choice_name = sanitize_text_field( (string) ( $choice['event_name'] ?? 'SWAT Tournament' ) );
							$choice_date = $date_range( $choice['start_date'] ?? '', $choice['end_date'] ?? '' );
							?>
							<option value="<?php echo esc_attr( $choice_slug ); ?>" <?php selected( $choice_slug, $event_slug ); ?>><?php echo esc_html( ( $choice_date ? $choice_date : 'Date TBD' ) . ' · ' . $choice_name ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>
        </div>
        <details class="swat-event-details">
            <summary><span class="swat-event-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><span aria-hidden="true">ⓘ</span><span class="screen-reader-text">Event details</span></summary>
            <div class="swat-event-details-body"><?php if ( $divisions ) : ?><p class="swat-event-divisions"><?php echo esc_html( implode( ', ', $divisions ) ); ?></p><?php endif; ?><p class="swat-event-places" data-swat-event-places hidden></p><a href="<?php echo esc_url( home_url( '/tournaments/' . $event_slug . '/' ) ); ?>">Full event details →</a></div>
        </details>
</header>

