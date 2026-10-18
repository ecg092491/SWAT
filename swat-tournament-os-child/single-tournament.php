<?php
/**
 * Virtual single tournament page.
 *
 * @package SwatTournamentOS
 */

defined( 'ABSPATH' ) || exit;

// Load this template dependency explicitly across theme deployment transitions.
require_once SWAT_OS_DIR . '/inc/os-registration-details.php';

$slug  = sanitize_title( (string) get_query_var( 'swat_event' ) );
$event = function_exists( 'swat_child_event_by_slug' ) ? swat_child_event_by_slug( $slug ) : swat_os_event_by_slug( $slug );

if ( ! $event ) {
	status_header( 404 );
	get_header();
	?>
	<section class="swat-error-page"><div class="swat-shell swat-narrow"><p class="swat-kicker">TOURNAMENT</p><h1>EVENT NOT FOUND</h1><p>This event is not present in the synchronized SWAT game data.</p><a class="swat-btn swat-btn-gold" href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>">VIEW TOURNAMENTS</a></div></section>
	<?php
	get_footer();
	return;
}

$games = swat_os_event_games( $event['event_name'] );
$bracket_fixtures = function_exists( 'swat_os_api_bracket_fixtures' )
	? swat_os_api_bracket_fixtures( $event['event_name'] )
	: array();

$display_games = array();
$seen_games    = array();

/*
 * 1.0.20: swat_os_event_games() rows carry no playbook_id, while the bracket
 * rows for the same posts do, so a synced bracket game used to be listed twice
 * once the event finished. Match on the post id as well as the Playbook id;
 * bracket rows go first because they carry the round and game number.
 */
foreach ( array_merge( (array) $bracket_fixtures, (array) $games ) as $game ) {
	$keys        = array();
	$playbook_id = absint( $game['playbook_id'] ?? 0 );
	$id          = absint( $game['id'] ?? 0 );
	if ( $playbook_id ) {
		$keys[] = 'pb:' . $playbook_id;
	}
	if ( $id ) {
		$keys[] = 'id:' . $id;
	}
	if ( $keys && array_intersect_key( $seen_games, array_flip( $keys ) ) ) {
		continue;
	}
	foreach ( $keys as $key ) {
		$seen_games[ $key ] = true;
	}
	$display_games[] = $game;
}

// 1.0.26: use corrected CSV finals in cards, results and standings.
if ( function_exists( 'swat_child_export_event_games' ) ) {
	$export = swat_child_export_event_games( $event['event_name'] );
	$display_games = swat_child_export_reconcile_event( $display_games, $export );
	$games = swat_child_export_reconcile_event( $games, $export );
}

// One source for event cards and pool standings, including deletions/corrections.
if ( function_exists( 'swat_child_source_apply' ) ) {
	foreach ( array( 'display_games', 'games' ) as $list_name ) {
		foreach ( ${$list_name} as &$row ) { $row['event_name'] = $event['event_name']; }
		unset( $row );
		${$list_name} = swat_child_source_apply( ${$list_name}, array( $event['event_name'] ), $event['start_date'] );
	}
}

// 1.0.19: name split finals Gold / Silver / Bronze (same rule as Game Day; inc/bracket-schedule.php).
if ( function_exists( 'swat_child_bracket_tier_labels' ) ) {
	$display_games = swat_child_apply_bracket_labels(
		$display_games,
		swat_child_bracket_tier_labels( $bracket_fixtures, function_exists( 'swat_child_bracket_labels' ) ? swat_child_bracket_labels( $event ) : array() )
	);
}

usort(
	$display_games,
	static function ( $a, $b ) {
		$left  = (string) ( $a['date'] ?? '' ) . ' ' . (string) ( $a['time'] ?? '' ) . ' ' . (string) ( $a['field'] ?? '' );
		$right = (string) ( $b['date'] ?? '' ) . ' ' . (string) ( $b['time'] ?? '' ) . ' ' . (string) ( $b['field'] ?? '' );
		return strcmp( $left, $right );
	}
);

$today     = current_time( 'Y-m-d' );
$start_ts  = false;
$end_ts    = false;
$is_future = $event['start_date'] > $today;

$event_date_ts = static function ( $date ) {
	if ( ! $date ) {
		return false;
	}
	$value = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
	return $value ? $value->getTimestamp() : false;
};

$effective_end_date = sanitize_text_field( (string) ( $event['end_date'] ?? $event['start_date'] ) );
foreach ( $display_games as $game ) {
	$game_date = sanitize_text_field( (string) ( $game['date'] ?? '' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $game_date ) && ( ! $effective_end_date || $game_date > $effective_end_date ) ) {
		$effective_end_date = $game_date;
	}
}

$start_ts = $event_date_ts( $event['start_date'] );
$end_ts   = $event_date_ts( $effective_end_date );

$format_time = static function ( $value ) {
	$value = sanitize_text_field( (string) $value );
	if ( preg_match( '/^(\d{1,2}):(\d{2})/', $value, $parts ) ) {
		$hour   = (int) $parts[1];
		$minute = (int) $parts[2];
		return sprintf( '%d:%02d %s', ( ( $hour + 11 ) % 12 ) + 1, $minute, $hour >= 12 ? 'PM' : 'AM' );
	}
	return $value ?: 'TBD';
};

// 1.0.29: real team names link to their team profile (inc/team-links.php).
$team_html = static function ( $raw, $label, $division ) {
	return function_exists( 'swat_child_team_link_html' ) ? swat_child_team_link_html( (string) $raw, (string) $label, (string) $division ) : esc_html( $label );
};

$display_team = static function ( $value ) {
	$value = sanitize_text_field( (string) $value );
	if ( preg_match( '/^Division Place\s+(\d+)/i', $value, $parts ) ) {
		return '#' . (int) $parts[1] . ' Seed';
	}
	if ( preg_match( '/^Winner\s*#?\s*(\d+)/i', $value, $parts ) ) {
		return 'Winner of Game ' . (int) $parts[1];
	}
	if ( preg_match( '/^Loser\s*#?\s*(\d+)/i', $value, $parts ) ) {
		return 'Loser of Game ' . (int) $parts[1];
	}
	return $value ?: 'TBD';
};

$division_names = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $event['divisions'] ?? array() ) ) ) );
foreach ( $display_games as $game ) {
	$division = sanitize_text_field( (string) ( $game['division'] ?? '' ) );
	if ( $division && ! in_array( $division, $division_names, true ) ) {
		$division_names[] = $division;
	}
}
natcasesort( $division_names );
$division_names = array_values( $division_names );

$standings = function_exists( 'swat_os_standings_data' ) ? swat_os_standings_data( $games ) : array();
$results   = array_values(
	array_filter(
		(array) $games,
		static function ( $game ) {
			return function_exists( 'swat_os_game_is_final' ) && swat_os_game_is_final( $game );
		}
	)
);
usort(
	$results,
	static function ( $a, $b ) {
		$left  = (string) ( $a['date'] ?? '' ) . ' ' . (string) ( $a['time'] ?? '' );
		$right = (string) ( $b['date'] ?? '' ) . ' ' . (string) ( $b['time'] ?? '' );
		return strcmp( $right, $left );
	}
);

$date_groups = array();
foreach ( $display_games as $game ) {
	$date = sanitize_text_field( (string) ( $game['date'] ?? '' ) );
	$date_groups[ $date ?: 'TBD' ][] = $game;
}

$all_division_attr = implode( '||', array_merge( array( 'all' ), $division_names ) );
$reg_state = swat_child_registration_state( $event, array( 'fetch' => true, 'end' => $effective_end_date ) );

get_header();
?>
<section class="swat-tournament-hero">
	<div class="swat-shell">
		<p class="swat-kicker swat-kicker-gold">SWAT TOURNAMENT</p>
		<h1><?php echo esc_html( $event['event_name'] ); ?></h1>
		<p class="swat-tournament-date"><?php echo esc_html( $start_ts ? wp_date( 'F j', $start_ts ) : 'Date TBD' ); ?><?php echo $end_ts && $effective_end_date !== $event['start_date'] ? '–' . esc_html( wp_date( $start_ts && wp_date( 'm', $start_ts ) !== wp_date( 'm', $end_ts ) ? 'F j, Y' : 'j, Y', $end_ts ) ) : ( $start_ts ? ', ' . esc_html( wp_date( 'Y', $start_ts ) ) : '' ); ?></p>
		<?php if ( ( $is_future && ! $display_games ) || 'cancelled' === $reg_state['status'] ) : ?><p class="swat-reg-hero"><?php echo swat_child_status_badge( $reg_state ); ?></p><?php echo swat_child_deadline_html( $reg_state ); ?><?php endif; ?>
		<p class="swat-tournament-summary"><?php echo esc_html( $display_games ? 'Everything families need for this event: pool games, bracket times, fields, results and standings in one simple view.' : ( $is_future ? ( $reg_state['can_register'] ? 'Choose your division and register before schedules are posted. ' : '' ) . swat_child_schedule_release( $event ) : 'Game times and fields are not available for this event.' ) ); ?></p>
		<div class="swat-button-row">
			<?php if ( $display_games ) : ?><a class="swat-btn swat-btn-gold swat-btn-lg" href="#event-schedule">VIEW SCHEDULE</a><?php endif; ?>
			<?php if ( $display_games ) : ?><a class="swat-btn swat-btn-ghost swat-btn-lg" href="<?php echo esc_url( swat_os_gameday_url( array( 'event' => $event['slug'] ) ) ); ?>">OPEN GAME DAY</a><?php endif; ?>
			<?php if ( $is_future && ! $display_games ) { echo swat_child_register_action( $reg_state, swat_os_registration_url( $event ), 'swat-btn swat-btn-ghost swat-btn-lg' ); } ?>
		</div>
	</div>
</section>

<section class="swat-section" id="event-schedule">
	<div class="swat-shell">
		<?php // Registration planning card omitted from the tournament page. ?>
		<div class="swat-quick-facts<?php echo $display_games ? '' : ' swat-quick-facts--pending'; ?>">
			<div><span>DATES</span><strong><?php echo esc_html( $start_ts ? wp_date( 'M j', $start_ts ) : 'TBD' ); ?><?php echo $end_ts && $effective_end_date !== $event['start_date'] ? '–' . esc_html( wp_date( $start_ts && wp_date( 'm', $start_ts ) !== wp_date( 'm', $end_ts ) ? 'M j' : 'j', $end_ts ) ) : ''; ?></strong></div>
			<?php if ( $display_games ) : ?>
				<div><span>GAMES</span><strong><?php echo esc_html( count( $display_games ) ); ?></strong></div>
				<div><span>DIVISIONS</span><strong><?php echo esc_html( count( $division_names ) ); ?></strong></div>
				<div><span>FIELDS</span><strong><?php echo esc_html( count( (array) ( $event['fields'] ?? array() ) ) ); ?></strong></div>
			<?php else : ?>
				<div><span>SCHEDULE</span><strong><?php echo $is_future ? 'COMING SOON' : 'UNAVAILABLE'; ?></strong></div>
			<?php endif; ?>
		</div>

		<?php if ( $display_games ) : ?>
			<div class="swat-event-explorer" data-swat-event-explorer>
				<div class="swat-event-explorer__top">
					<div>
						<p class="swat-kicker">PLAYBOOK-SYNCED</p>
						<h2>EVENT SCHEDULE</h2>
					</div>
					<span class="swat-event-explorer__count" data-event-visible-count><?php echo esc_html( count( $display_games ) ); ?> games</span>
				</div>

				<div class="swat-event-tabs" role="tablist" aria-label="Tournament information">
					<button type="button" class="is-active" role="tab" aria-selected="true" data-event-tab="schedule">Schedule</button>
					<button type="button" role="tab" aria-selected="false" tabindex="-1" data-event-tab="standings">Standings</button>
					<button type="button" role="tab" aria-selected="false" tabindex="-1" data-event-tab="results">Results</button>
					<button type="button" role="tab" aria-selected="false" tabindex="-1" data-event-tab="rules">Rules</button>
				</div>

				<?php if ( count( $division_names ) > 1 ) : ?>
					<div class="swat-division-filter" aria-label="Filter by division">
						<button type="button" class="is-active" aria-pressed="true" data-event-division="all">All Divisions</button>
						<?php foreach ( $division_names as $division ) : ?>
							<button type="button" aria-pressed="false" data-event-division="<?php echo esc_attr( $division ); ?>"><?php echo esc_html( $division ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<section class="swat-event-panel" data-event-panel="schedule">
					<?php foreach ( $date_groups as $date => $group_games ) : ?>
						<?php
						$date_ts    = $event_date_ts( $date );
						$date_label = $date_ts ? wp_date( 'l · F j', $date_ts ) : 'Date TBD';
						?>
						<div class="swat-event-date-group" data-date-group>
							<div class="swat-event-date-heading">
								<h3><?php echo esc_html( $date_label ); ?></h3>
								<span><?php echo esc_html( count( $group_games ) ); ?> games</span>
							</div>
							<div class="swat-easy-schedule">
								<?php foreach ( $group_games as $game ) : ?>
									<?php
									$division      = sanitize_text_field( (string) ( $game['division'] ?? 'Tournament' ) );
									$round         = sanitize_text_field( (string) ( $game['round'] ?? '' ) );
									$is_pool       = function_exists( 'swat_os_game_is_pool' ) ? swat_os_game_is_pool( $game ) : true;
									$is_title      = false !== stripos( $round, 'championship' );
									$tier          = sanitize_text_field( (string) ( $game['tier'] ?? '' ) );
									$type_label    = $is_pool ? 'Pool' : ( $is_title ? trim( $tier . ' Championship' ) : ( $round ?: 'Bracket' ) );
									$type_class    = $is_pool ? 'is-pool' : ( $is_title ? 'is-championship' . ( $tier ? ' is-tier-' . sanitize_html_class( strtolower( $tier ) ) : '' ) : 'is-bracket' );
									$status        = function_exists( 'swat_os_game_status' ) ? swat_os_game_status( $game ) : 'SCHEDULED';
									$is_live       = function_exists( 'swat_os_game_is_live' ) && swat_os_game_is_live( $game );
									$is_final      = function_exists( 'swat_os_game_is_final' ) && swat_os_game_is_final( $game );
									$show_score    = $is_live || $is_final;
									$field         = sanitize_text_field( (string) ( $game['field'] ?? 'Field TBD' ) );
									$away          = $display_team( $game['away'] ?? 'Away' );
									$home          = $display_team( $game['home'] ?? 'Home' );
									?>
									<article class="swat-easy-game <?php echo esc_attr( $type_class ); ?>" data-division="<?php echo esc_attr( $division ); ?>">
										<div class="swat-easy-game__meta">
											<span class="swat-easy-game__time"><?php echo esc_html( $format_time( $game['time'] ?? '' ) ); ?></span>
											<a class="swat-easy-game__field" href="<?php echo esc_url( swat_os_gameday_url( array( 'event' => $event['slug'], 'field' => $field ) ) ); ?>"><?php echo esc_html( $field ); ?></a>
											<span class="swat-easy-game__type"><?php echo esc_html( $type_label ); ?></span>
										</div>
										<div class="swat-easy-game__teams">
											<div class="swat-easy-game__team"><span><?php echo $team_html( $game['away'] ?? '', $away, $division ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span><strong><?php echo $show_score ? esc_html( (string) ( $game['away_score'] ?? '' ) ) : ''; ?></strong></div>
											<div class="swat-easy-game__team"><span><?php echo $team_html( $game['home'] ?? '', $home, $division ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span><strong><?php echo $show_score ? esc_html( (string) ( $game['home_score'] ?? '' ) ) : ''; ?></strong></div>
										</div>
										<div class="swat-easy-game__division"><?php echo esc_html( $division ); ?></div>
										<div class="swat-easy-game__status">
											<span><?php echo esc_html( $is_final ? 'Final' : ( $is_live ? 'Live' : $status ) ); ?></span>
											<a href="<?php echo esc_url( swat_os_gameday_url( array( 'event' => $event['slug'] ) ) ); ?>">Game Day →</a>
										</div>
									</article>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</section>

				<section class="swat-event-panel" data-event-panel="standings" hidden>
					<?php if ( $standings ) : ?>
						<div class="swat-event-standings">
							<?php foreach ( $standings as $division => $standing_data ) : ?>
								<article class="swat-event-standing" data-division="<?php echo esc_attr( $division ); ?>">
									<div class="swat-event-standing__head">
										<h3><?php echo esc_html( $division ); ?></h3>
										<span>Pool standings</span>
									</div>
									<div>
										<table class="swat-event-standing__table">
											<thead><tr><th>Seed</th><th>Team</th><th>W</th><th>L</th><th>RA</th><th>RD</th></tr></thead>
											<tbody>
												<?php foreach ( (array) ( $standing_data['rows'] ?? array() ) as $index => $row ) : ?>
													<tr>
														<td><span class="swat-event-seed"><?php echo ! empty( $row['coin_flip'] ) ? '—' : esc_html( (string) ( $index + 1 ) ); ?></span></td>
														<td><?php echo $team_html( $row['team'] ?? '', $row['team'] ?? '', $division ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
														<td><?php echo esc_html( (string) ( $row['w'] ?? 0 ) ); ?></td>
														<td><?php echo esc_html( (string) ( $row['l'] ?? 0 ) ); ?></td>
														<td><?php echo esc_html( (string) ( $row['ra'] ?? 0 ) ); ?></td>
														<td><?php echo esc_html( (string) ( (int) ( $row['rf'] ?? 0 ) - (int) ( $row['ra'] ?? 0 ) ) ); ?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="swat-event-empty" data-division="<?php echo esc_attr( $all_division_attr ); ?>"><strong>Standings start with final scores.</strong>Official pool standings will populate here automatically as results are posted.</div>
					<?php endif; ?>
				</section>

				<section class="swat-event-panel" data-event-panel="results" hidden>
					<?php if ( $results ) : ?>
						<div class="swat-event-results">
							<?php foreach ( $results as $game ) : ?>
								<?php
								$division = sanitize_text_field( (string) ( $game['division'] ?? 'Tournament' ) );
								$field    = sanitize_text_field( (string) ( $game['field'] ?? '' ) );
								?>
								<article class="swat-event-result" data-division="<?php echo esc_attr( $division ); ?>">
									<div class="swat-event-result__meta"><?php echo esc_html( $division ); ?><br><?php echo esc_html( $field ); ?></div>
									<div class="swat-event-result__matchup">
										<div class="swat-event-result__team"><span><?php echo $team_html( $game['away'] ?? '', $display_team( $game['away'] ?? 'Away' ), $division ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span><strong><?php echo esc_html( (string) ( $game['away_score'] ?? '' ) ); ?></strong></div>
										<div class="swat-event-result__team"><span><?php echo $team_html( $game['home'] ?? '', $display_team( $game['home'] ?? 'Home' ), $division ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span><strong><?php echo esc_html( (string) ( $game['home_score'] ?? '' ) ); ?></strong></div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="swat-event-empty" data-division="<?php echo esc_attr( $all_division_attr ); ?>"><strong>No finals yet.</strong>Final scores will appear here as the event is played.</div>
					<?php endif; ?>
				</section>

				<section class="swat-event-panel" data-event-panel="rules" hidden>
					<?php
					swat_os_render_division_rules(
						$division_names,
						array(
							'event_name' => $event['event_name'],
							'heading'    => 'RULES FOR THIS EVENT',
							'intro'      => 'Choose your division above. This view shows the SWAT rules that apply most directly to that age group without making you search the full handbook.',
							'compact'    => true,
							'show_index' => false,
						)
					);
					?>
				</section>

				<div class="swat-event-footer-cta">
					<div><strong>FOLLOW YOUR TEAM IN GAME DAY</strong><span>Get the focused itinerary, fields, scores and tournament updates for your team.</span></div>
					<a class="swat-btn swat-btn-gold" href="<?php echo esc_url( swat_os_gameday_url( array( 'event' => $event['slug'] ) ) ); ?>">OPEN GAME DAY</a>
				</div>
			</div>
		<?php else : ?>
			<div class="swat-schedule-pending" role="status">
				<p class="swat-kicker">GAME DAY</p>
				<h2><?php echo $is_future ? 'SCHEDULE COMING SOON' : 'SCHEDULE UNAVAILABLE'; ?></h2>
				<p><?php echo $is_future ? esc_html( swat_child_schedule_release( $event ) ) : 'No games are currently listed for this event.'; ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
