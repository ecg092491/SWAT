<?php
/**
 * Virtual tournament directory backed by synchronized SWAT game data.
 *
 * @package SwatTournamentOS
 */

defined( 'ABSPATH' ) || exit;

// Load this template dependency explicitly across theme deployment transitions.
require_once SWAT_OS_DIR . '/inc/os-registration-details.php';
$events = swat_child_event_directory();
foreach ( $events as &$swat_directory_event ) {
	$swat_registration = swat_os_registration_event( $swat_directory_event );
	if ( ! empty( $swat_registration['Divisions'] ) ) {
		$swat_directory_event['divisions'] = array();
		foreach ( $swat_registration['Divisions'] as $swat_division ) {
			if ( ! empty( $swat_division['Age'] ) ) { $swat_directory_event['divisions'][] = trim( $swat_division['Age'] . ' ' . ( $swat_division['Subdivison'] ?? '' ) ); }
		}
	}
}
unset( $swat_directory_event );
$today  = current_time( 'Y-m-d' );
$event_date_ts = static function ( $date ) {
	if ( ! $date ) {
		return false;
	}
	$value = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
	return $value ? $value->getTimestamp() : false;
};
$months = array();
$divisions = array();
foreach ( $events as $item ) {
	if ( ! empty( $item['start_date'] ) ) {
		$start_ts = $event_date_ts( $item['start_date'] );
		if ( $start_ts ) {
			$months[ wp_date( 'Y-m', $start_ts ) ] = wp_date( 'F Y', $start_ts );
		}
	}
	foreach ( (array) ( $item['divisions'] ?? array() ) as $division ) {
		$divisions[ $division ] = $division;
	}
}
ksort( $months );
natcasesort( $divisions );
$past_events = array();
$current_events = array();
foreach ( $events as $directory_event ) {
    $directory_end = $directory_event['end_date'] ?? ( $directory_event['start_date'] ?? '' );
    if ( $directory_end && $directory_end < $today ) { $past_events[] = $directory_event; }
    else { $current_events[] = $directory_event; }
}
$events = array_merge( $current_events, $past_events );
$display_titles = swat_child_directory_titles( $events ); // 1.0.31: short titles; repeated ones keep their age groups.
$past_section_started = false;
get_header();
?>
<section class="swat-page-hero">
	<div class="swat-shell">
		<p class="swat-kicker">PLAY SWAT</p>
		<h1>TOURNAMENTS</h1>
		<p>Find youth baseball tournaments in Clemmons, NC for teams across Winston-Salem, Greensboro and the NC Triad. Review dates, divisions and schedules, then register or open Game Day.</p>
	</div>
</section>
<section class="swat-section">
	<div class="swat-shell">
		<div class="swat-directory-filters" data-swat-directory-filters>
			<label><span>Month</span><select data-swat-tournament-filter="month"><option value="">All months</option><?php foreach ( $months as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><span>Division</span><select data-swat-tournament-filter="division"><option value="">All divisions</option><?php foreach ( $divisions as $division ) : ?><option value="<?php echo esc_attr( $division ); ?>"><?php echo esc_html( $division ); ?></option><?php endforeach; ?></select></label>
			<button type="button" data-swat-tournament-clear>CLEAR FILTERS</button>
		</div>
		<p class="swat-directory-status" data-swat-directory-status aria-live="polite"><?php echo esc_html( count( $events ) ); ?> synchronized events</p>
		<div class="swat-event-grid" data-swat-tournament-grid>
			<?php foreach ( $events as $event_index => $item ) :
				$start = $item['start_date'] ?? '';
				$end   = $item['end_date'] ?? $start;
				$state = $end < $today ? 'COMPLETE' : ( $start <= $today && $end >= $today ? 'THIS WEEKEND' : 'UPCOMING' );
				if ( 'COMPLETE' === $state && ! $past_section_started ) {
    $past_section_started = true;
    echo '<details class="swat-past-results" data-swat-past-results><summary>Past Results <span data-swat-past-count>' . esc_html( count( $past_events ) ) . ' completed tournaments</span></summary><div class="swat-past-grid">';
}
$start_ts = $event_date_ts( $start );
					$end_ts   = $event_date_ts( $end );
					$month = $start_ts ? wp_date( 'Y-m', $start_ts ) : '';
				$division_data = implode( '||', (array) ( $item['divisions'] ?? array() ) );
					$reg_state = swat_child_registration_state( $item, array( 'fetch' => true ) );
					// 1.0.19: same rule as the homepage (inc/home-data.php, unit tested). A schedule exists only
					// when synced games or bracket fixtures exist; "Sold Out" no longer implies a schedule.
					$has_schedule = ! empty( $item['game_count'] ) || ( 'COMPLETE' !== $state && function_exists( 'swat_os_path_event_games' ) && swat_os_path_event_games( (string) ( $item['event_name'] ?? '' ) ) );
					$rule = swat_child_home_action_rule(
						array(
							'has_schedule' => $has_schedule,
							'started'      => '' !== $start && $start <= $today,
							'complete'     => 'COMPLETE' === $state,
							'reg_status'   => (string) $reg_state['status'],
						)
					);
				?>
				<article class="swat-event-card swat-directory-card is-reg-<?php echo esc_attr( str_replace( '_', '-', $reg_state['status'] ) ); ?><?php echo 'sold_out' === $reg_state['status'] ? ' is-sold-out' : ''; ?>" data-swat-tournament-card data-month="<?php echo esc_attr( $month ); ?>" data-divisions="<?php echo esc_attr( $division_data ); ?>">
					<div class="swat-event-date"><span><?php echo esc_html( $start_ts ? wp_date( 'M', $start_ts ) : 'TBD' ); ?></span><strong><?php echo esc_html( $start_ts ? wp_date( 'j', $start_ts ) : '—' ); ?></strong></div>
					<div class="swat-event-card-body">
						<div class="swat-reg-row"><?php if ( 'COMPLETE' === $state || $rule['badge'] ) { echo swat_child_status_badge( $reg_state ); } ?><?php if ( $has_schedule && 'COMPLETE' !== $state ) : ?><span class="swat-reg-when is-schedule"><?php esc_html_e( 'Schedule posted', 'swat-tournament-os-child' ); ?></span><?php endif; ?><?php if ( 'THIS WEEKEND' === $state ) : /* 1.0.31: "UPCOMING" dropped; every card above Past Results is upcoming. */ ?><span class="swat-reg-when is-now"><?php esc_html_e( 'This weekend', 'swat-tournament-os-child' ); ?></span><?php endif; ?></div>
						<h2 title="<?php echo esc_attr( $item['event_name'] ); ?>"><?php echo esc_html( $display_titles[ $event_index ] ); ?></h2>
						<p><?php echo esc_html( $start_ts ? wp_date( 'F j', $start_ts ) : 'Date TBD' ); ?><?php echo $end_ts && $end !== $start ? '–' . esc_html( wp_date( 'j, Y', $end_ts ) ) : ( $start_ts ? ', ' . esc_html( wp_date( 'Y', $start_ts ) ) : '' ); ?> · <?php echo ! empty( $item['game_count'] ) ? esc_html( absint( $item['game_count'] ) . ' games' ) : esc_html( swat_child_schedule_release( $item ) ); ?></p>
						<?php if ( ! empty( $item['divisions'] ) ) : ?><p><?php echo esc_html( implode( ' · ', array_slice( $item['divisions'], 0, 5 ) ) ); ?><?php if ( count( $item['divisions'] ) > 5 ) : ?> · <a href="<?php echo esc_url( home_url( '/tournaments/' . $item['slug'] . '/' ) ); ?>"><?php echo esc_html( '+' . ( count( $item['divisions'] ) - 5 ) . ' more divisions' ); ?></a><?php endif; ?></p><?php endif; ?>
						<?php echo swat_child_deadline_html( $reg_state ); ?>
						<div class="swat-event-actions">
							<a href="<?php echo esc_url( home_url( '/tournaments/' . $item['slug'] . '/' ) ); ?>">EVENT DETAILS</a>
							<?php /* Schedule action below replaces the duplicate Game Day link. */ ?>
							<?php if ( in_array( 'schedule', $rule['actions'], true ) || in_array( 'results', $rule['actions'], true ) ) : ?>
									<a href="<?php echo esc_url( swat_os_gameday_url( array( 'event' => $item['slug'], 'view' => 'COMPLETE' === $state ? 'scores' : 'schedule' ) ) ); ?>" aria-label="<?php echo esc_attr( ( 'COMPLETE' === $state ? 'View results for ' : 'View schedule for ' ) . $item['event_name'] ); ?>"><?php echo 'COMPLETE' === $state ? 'VIEW RESULTS' : 'VIEW SCHEDULE'; ?></a>
								<?php elseif ( 'COMPLETE' !== $state && ( $start > $today || 'cancelled' === $reg_state['status'] ) ) : ?>
									<?php echo swat_child_register_action( $reg_state, swat_os_registration_url( $item ), '' ); // REGISTER when accepting entries; JOIN WAITLIST when sold out or closed; EVENT CANCELLED as text. ?>
								<?php endif; ?>
						</div>
					</div>
				</article>
<?php if ( $past_section_started && $item === end( $events ) ) : ?></div></details><?php endif; ?>
			<?php endforeach; ?>
		</div>
		<div class="swat-empty-state" data-swat-directory-empty hidden><strong>No matching tournaments</strong><p>Clear a filter to see all synchronized SWAT events.</p></div>
	</div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function(){
 const past=document.querySelector('[data-swat-past-results]');
 if(!past)return;
 const filters=Array.from(document.querySelectorAll('[data-swat-tournament-filter]'));
 const status=document.querySelector('[data-swat-directory-status]');
 function update(){
  const active=filters.some(x=>x.value);
  const pastCards=Array.from(past.querySelectorAll('[data-swat-tournament-card]'));
  const count=pastCards.filter(x=>!x.hidden).length;
  past.hidden=count===0;
  if(active)past.open=count>0;
  past.querySelector('[data-swat-past-count]').textContent=count+' completed tournaments';
  const current=Array.from(document.querySelectorAll('[data-swat-tournament-card]')).filter(x=>!past.contains(x)&&!x.hidden).length;
  if(status)status.textContent=current+' upcoming tournaments · '+count+' past results';
 }
 filters.forEach(x=>x.addEventListener('change',()=>queueMicrotask(update)));
 document.querySelector('[data-swat-tournament-clear]')?.addEventListener('click',()=>queueMicrotask(()=>{past.open=false;update()}));
 update();
});
</script>
<?php get_footer(); ?>
