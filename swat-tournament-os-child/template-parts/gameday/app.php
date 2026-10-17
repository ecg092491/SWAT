<?php
/**
 * SWAT Game Day application shell.
 *
 * @package SwatTournamentOS
 */

defined( 'ABSPATH' ) || exit;
$gdc_event_slug = function_exists( 'swat_os_current_event' ) ? sanitize_title( (string) ( swat_os_current_event()['slug'] ?? '' ) ) : sanitize_title( (string) ( $_GET['event'] ?? '' ) );
$gdc_pitching_enabled = function_exists( 'gdc_gameday_pitching_enabled' ) ? gdc_gameday_pitching_enabled( $gdc_event_slug ) : true;

$view   = isset( $args['view'] ) ? sanitize_key( $args['view'] ) : 'today';
$action = isset( $args['action'] ) ? sanitize_key( $args['action'] ) : sanitize_key( (string) get_query_var( 'swat_action' ) );

$team  = isset( $_GET['team'] ) ? sanitize_text_field( wp_unslash( $_GET['team'] ) ) : '';
$field = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : '';
$game  = isset( $_GET['game'] ) ? absint( $_GET['game'] ) : 0;
$event = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';

$current_event  = swat_os_current_event();
$initial_payload = swat_os_public_payload();

if ( ! $event && ! empty( $current_event['slug'] ) && 'active-window' === ( $current_event['source'] ?? '' ) ) {
	$event = sanitize_title( (string) $current_event['slug'] );
}

if ( $event ) {
	$requested_event = swat_os_event_by_slug( $event );
	if ( $requested_event ) {
		$initial_payload = array(
			'loaded_event' => $current_event,
			'last_sync'    => function_exists( 'swat_child_event_sync_timestamp' ) ? swat_child_event_sync_timestamp( $event ) : null,
			'games'        => swat_os_event_games( $requested_event['event_name'] ),
		);
	}
}
if ( ! isset( $initial_payload['bracket_fixtures'] ) ) {
	$initial_event_name = sanitize_text_field( (string) ( $initial_payload['loaded_event']['name'] ?? '' ) );
	$initial_payload['bracket_fixtures'] = function_exists( 'swat_os_api_bracket_fixtures' )
		? swat_os_api_bracket_fixtures( $initial_event_name )
		: array();
}
$initial_games   = isset( $initial_payload['games'] ) && is_array( $initial_payload['games'] ) ? $initial_payload['games'] : array();
$initial_live    = array_values( array_filter( $initial_games, 'swat_os_game_is_live' ) );
$initial_finals  = array_slice( array_values( array_reverse( array_filter( $initial_games, 'swat_os_game_is_final' ) ) ), 0, 5 );
$initial_upcoming = array_slice(
	array_values(
		array_filter(
			$initial_games,
			static function ( $item ) {
				return ! swat_os_game_is_live( $item ) && ! swat_os_game_is_final( $item );
			}
		)
	),
	0,
	6
);
$hero_name     = sanitize_text_field( (string) ( $current_event['name'] ?? 'SWAT Game Day' ) );
$hero_title    = $hero_name;
$hero_subtitle = '';
$hero_lower    = strtolower( $hero_name );

if ( false !== strpos( $hero_lower, 'friday night lights' ) ) {
	$hero_title = 'Friday Night Lights';
} elseif ( false !== strpos( $hero_lower, 'saturday night lights' ) ) {
	$hero_title = 'Saturday Night Lights';
} elseif ( false !== strpos( $hero_name, ':' ) ) {
	$hero_parts    = array_map( 'trim', explode( ':', $hero_name, 2 ) );
	$hero_title    = $hero_parts[0];
	$hero_subtitle = $hero_parts[1] ?? '';
}

$hero_start = ! empty( $current_event['start'] ) ? strtotime( (string) $current_event['start'] ) : false;
$hero_end   = ! empty( $current_event['end'] ) ? strtotime( (string) $current_event['end'] ) : false;
$hero_date  = $hero_start ? wp_date( 'M j', $hero_start ) : '';
if ( $hero_start && $hero_end && wp_date( 'Y-m-d', $hero_start ) !== wp_date( 'Y-m-d', $hero_end ) ) {
	$hero_date .= '–' . wp_date( 'j', $hero_end );
}
$hero_status = $initial_games ? 'SCHEDULE LIVE' : 'SCHEDULE PENDING';

$gameday_rule_divisions = array();
foreach ( array_merge( $initial_games, (array) ( $initial_payload['bracket_fixtures'] ?? array() ) ) as $rule_game ) {
	$rule_division = sanitize_text_field( (string) ( $rule_game['division'] ?? '' ) );
	if ( $rule_division && swat_os_division_rule_key( $rule_division ) && ! in_array( $rule_division, $gameday_rule_divisions, true ) ) {
		$gameday_rule_divisions[] = $rule_division;
	}
}

if ( ! $gameday_rule_divisions ) {
	$rules_event_slug = $event ?: sanitize_title( (string) ( $current_event['slug'] ?? '' ) );
	$rules_event      = $rules_event_slug ? swat_os_event_by_slug( $rules_event_slug ) : null;
	foreach ( (array) ( $rules_event['divisions'] ?? array() ) as $rule_division ) {
		$rule_division = sanitize_text_field( (string) $rule_division );
		if ( $rule_division && swat_os_division_rule_key( $rule_division ) && ! in_array( $rule_division, $gameday_rule_divisions, true ) ) {
			$gameday_rule_divisions[] = $rule_division;
		}
	}
}

$gameday_rules_url = $gameday_rule_divisions ? '#swat-gameday-rules' : home_url( '/rules/' );

$pitch_event_slug = $event ?: sanitize_title( (string) ( $current_event['slug'] ?? '' ) );
$pitch_event_data = $pitch_event_slug ? swat_os_event_by_slug( $pitch_event_slug ) : null;
$pitch_event_name = sanitize_text_field( (string) ( $pitch_event_data['event_name'] ?? $current_event['name'] ?? '' ) );
$pitch_event_start = sanitize_text_field( (string) ( $pitch_event_data['start_date'] ?? $current_event['start'] ?? '' ) );
$pitch_playbook_slug = swat_os_pitch_count_event_slug( $pitch_event_slug, $pitch_event_name, $pitch_event_start );
$pitch_count_url = $pitch_playbook_slug ? swat_os_pitch_count_url( $pitch_playbook_slug ) : '';
$GLOBALS['swat_gameday_pitch_event_slug'] = $pitch_playbook_slug;

?>
<script>(function(){try{var mode=localStorage.getItem('swatAppearanceV1');document.body.setAttribute('data-swat-appearance',mode==='light'?'light':'dark');}catch(e){}})();</script>
<div class="swat-gameday swat-hub" data-swat-app data-initial-view="<?php echo esc_attr( $view ); ?>" data-team="<?php echo esc_attr( $team ); ?>" data-field="<?php echo esc_attr( $field ); ?>" data-game="<?php echo esc_attr( $game ? (string) $game : '' ); ?>" data-event="<?php echo esc_attr( $event ); ?>">
	<?php echo function_exists( 'swat_child_gameday_sponsor_strip' ) ? swat_child_gameday_sponsor_strip( 'header', $current_event ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<?php get_template_part( 'template-parts/gameday/home-hero', null, array( 'current_event' => $current_event, 'games' => $initial_games, 'is_action' => (bool) $action ) ); ?>

	<div class="swat-app-statusbar">
		<div>
			<span class="swat-status-pill" data-swat-connection>CONNECTING</span>
			<span class="swat-updated" data-swat-updated>Loading tournament data…</span>
		</div>

	</div>


	<script type="application/json" data-swat-initial-payload><?php echo wp_json_encode( $initial_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>


		<p class="screen-reader-text" data-swat-refresh-announcement role="status" aria-live="polite"></p>
		<div class="swat-alert-stack" data-swat-alerts aria-live="polite"></div>

	<?php if ( 'report-score' === $action ) : ?>
		<section class="swat-app-panel swat-form-panel" aria-labelledby="swat-score-heading">
			<div class="swat-app-section-heading">
				<div><p class="swat-app-eyebrow">GAME RESULT</p><h1 id="swat-score-heading">Report score</h1></div>
				<a href="<?php echo esc_url( swat_os_gameday_url() ); ?>">CANCEL</a>
			</div>
			<form class="swat-mobile-form" data-swat-score-form novalidate>
				<label>
					<span>Game</span>
					<select name="game_id" data-swat-score-game required>
						<option value="">Select your game</option>
					</select>
				</label>
				<div class="swat-score-entry">
					<label>
						<span data-swat-away-label>Away score</span>
						<input type="number" inputmode="numeric" min="0" max="99" name="away_score" required>
					</label>
					<label>
						<span data-swat-home-label>Home score</span>
						<input type="number" inputmode="numeric" min="0" max="99" name="home_score" required>
					</label>
				</div>
				<label>
					<span>Optional note</span>
					<textarea name="notes" rows="3" maxlength="300" placeholder="Only if SWAT needs context"></textarea>
				</label>
				<div class="swat-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<button class="swat-btn swat-btn-gold swat-btn-block" type="submit">VERIFY &amp; SUBMIT SCORE</button>
				<p class="swat-form-note">Submitted scores are pending until reviewed or accepted by SWAT.</p>
				<div class="swat-form-result" data-swat-score-result aria-live="polite"></div>
			</form>
		</section>
	<?php elseif ( 'field-help' === $action ) : ?>
		<section class="swat-app-panel swat-form-panel" aria-labelledby="swat-help-heading">
			<div class="swat-app-section-heading">
				<div><p class="swat-app-eyebrow">OPERATIONS</p><h1 id="swat-help-heading"><?php echo $field ? esc_html( $field . ' help' ) : 'Field help'; ?></h1></div>
				<a href="<?php echo esc_url( swat_os_gameday_url() ); ?>">CANCEL</a>
			</div>
			<form class="swat-mobile-form" data-swat-help-form novalidate>
				<label>
					<span>Field</span>
					<select name="field" data-swat-help-field required>
						<option value="">Select field</option>
					</select>
				</label>
				<fieldset class="swat-issue-grid">
					<legend>What do you need?</legend>
					<?php
					$issues = array(
						'baseballs'       => 'Baseballs',
						'bases'           => 'Bases',
						'field-condition' => 'Field Condition',
						'pitching-mound'  => 'Pitching Mound',
						'dugout'          => 'Dugout',
						'scoreboard'      => 'Scoreboard',
						'restrooms'       => 'Restrooms',
						'trash'           => 'Trash / Cleanup',
						'gate'            => 'Gate',
						'concessions'     => 'Concessions',
						'parking'         => 'Parking Issue',
							'equipment'       => 'Equipment Issue',
							'umpire'          => 'Umpire Needed',
							'medical-safety'  => 'Medical / Safety Issue',
							'other'           => 'Other',
					);
					foreach ( $issues as $value => $label ) :
						?>
						<label class="swat-issue-choice"><input type="radio" name="category" value="<?php echo esc_attr( $value ); ?>" required><span><?php echo esc_html( $label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
				<label>
					<span>Optional details</span>
					<textarea name="details" rows="3" maxlength="400" placeholder="Anything staff should know?"></textarea>
				</label>
				<input type="hidden" name="event" value="<?php echo esc_attr( $event ?: ( $current_event['name'] ?? '' ) ); ?>">
				<div class="swat-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<button class="swat-btn swat-btn-gold swat-btn-block" type="submit">SEND TO SWAT OPS</button>
				<div class="swat-form-result" data-swat-help-result aria-live="polite"></div>
			</form>
		</section>
	<?php else : ?>
		<?php if ( $team || $field || $game ) : ?><section class="swat-context-card" data-swat-context hidden></section><?php endif; ?>
			<?php
			if ( $game && $pitch_playbook_slug ) {
				$detail_pitch_game = null;
				foreach ( $initial_games as $candidate_game ) {
					if ( (string) ( $candidate_game['id'] ?? '' ) === (string) $game ) {
						$detail_pitch_game = $candidate_game;
						break;
					}
				}
				if ( $detail_pitch_game ) {
					$detail_pitch_markup = swat_os_pitch_count_game_markup( $detail_pitch_game );
					if ( $detail_pitch_markup ) {
						echo '<section id="game-pitching-log" class="swat-game-detail-pitching"><div class="swat-app-section-heading"><div><p class="swat-app-eyebrow">PITCHING</p><h2>GAME PITCHING LOG</h2><p>Pitchers and innings reported for this game.</p></div></div>' . $detail_pitch_markup . '</section>';
					}
				}
			}
			?>



<nav class="swat-bottom-nav" role="tablist" aria-label="Game Day navigation">
<button type="button" data-swat-view="today"><span>Today</span></button>
	<button type="button" data-swat-view="schedule"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M8 14h3M13 14h3M8 17h3"/></svg><span>Schedule</span></button>

	<button type="button" data-swat-view="scores"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14v12H5z"/><path d="M8 10h3M13 10h3M8 14h3M13 14h3"/></svg><span>Scores</span></button>
	<button type="button" data-swat-view="standings"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V9M12 19V5M19 19v-7"/><path d="M3 19h18"/></svg><span>Standings</span></button>
		<button type="button" data-swat-view="more"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg><span>More</span></button>
</nav>


				<div class="swat-smart-alert-row" data-swat-smart-alert-row data-swat-home-only hidden>
					<button type="button" class="swat-alert-dismiss" data-swat-dismiss-alerts aria-label="Dismiss alerts invitation">×</button><div><strong>SMART TEAM ALERTS</strong><span>Get your team’s time, field and weather changes.</span></div>
					<button type="button" data-swat-enable-alerts aria-pressed="false">TURN ON ALERTS</button>
				</div>


		<?php /* Essentials moved into Tournament Hub; intentionally omitted from Today feed. */ ?>





		<?php if ( $gameday_rule_divisions ) : ?>
			<details id="swat-gameday-rules" class="swat-gameday-rules" data-swat-section="rules" hidden>
				<summary><span><small>QUICK REFERENCE</small><strong>DIVISION RULES</strong></span></summary>
				<div class="swat-gameday-rules__body">
					<?php
					swat_os_render_division_rules(
						$gameday_rule_divisions,
						array(
							'event_name' => $hero_name,
							'heading'    => 'RULES FOR THIS EVENT',
							'intro'      => 'Open your age division for the field, time, bat, pitching, baserunning and mercy rules that apply here.',
							'compact'    => true,
							'show_index' => count( $gameday_rule_divisions ) > 1,
						)
					);
					?>
				</div>
			</details>
		<?php endif; ?>

			<?php
			$reported_pitch_games = array();
			if ( $pitch_playbook_slug ) {
				foreach ( $initial_games as $pitch_game ) {
					$pitch_row = swat_os_pitch_count_for_game( $pitch_game );
					if ( $pitch_row ) {
						foreach ( (array) ( $pitch_row['teams'] ?? array() ) as $pitch_team ) {
							if ( ! empty( $pitch_team['pitchers'] ) ) { $reported_pitch_games[] = $pitch_game; break; }
						}
					}
				}
			}
			?>
			<?php if ( isset( $reported_pitch_games ) ) : ?>
				<?php if ( $gdc_pitching_enabled ) : ?><section class="swat-pitch-count-live swat-app-view" data-swat-section="pitching" data-pitch-event="<?php echo esc_attr( $pitch_event_slug ); ?>" data-pitch-endpoint="<?php echo esc_url( rest_url( 'swat/v1/weekend-pitching' ) ); ?>" data-pitch-source="<?php echo esc_url( $pitch_count_url ); ?>" hidden>
					<div class="swat-app-section-heading">
						<div><p class="swat-app-eyebrow">PITCHING</p><h2>PITCH COUNT / INNINGS</h2></div>
						<span><?php echo esc_html( count( $reported_pitch_games ) ); ?> games reported</span>
					</div>

					<div class="swat-game-list">
						<?php foreach ( $reported_pitch_games as $pitch_game ) : echo swat_os_game_card( $pitch_game ); endforeach; ?>
<?php if ( ! $reported_pitch_games ) : ?><div class="swat-app-empty"><strong>No pitching reported yet</strong><p>Player innings will appear here when reported.</p><?php if ( $pitch_count_url ) : ?><a href="<?php echo esc_url( $pitch_count_url ); ?>" target="_blank" rel="noopener">Open official pitching log</a><?php endif; ?></div><?php endif; ?>
					</div>
				</section><?php endif; ?>
			<?php endif; ?>

<?php if ( $gdc_pitching_enabled ) : ?><section class="swat-pitch-limits swat-app-view" data-swat-section="pitching" hidden aria-label="Pitching limits"><h3>Pitching limits</h3><table><caption>Maximum innings per tournament</caption><thead><tr><th scope="col">Division</th><th scope="col">1 day</th><th scope="col">3 days</th></tr></thead><tbody><tr><th scope="row">8U Kid Pitch</th><td>6</td><td>6</td></tr><tr><th scope="row">9U–12U</th><td>6</td><td>8</td></tr><tr><th scope="row">13U–15U</th><td>7</td><td>9</td></tr></tbody></table><p>One pitch counts as one inning. More than 3 innings Saturday makes a pitcher ineligible Sunday. Once removed, a pitcher cannot return to the mound.</p><a href="<?php echo esc_url( home_url( '/pitching/' ) ); ?>">All pitching rules and logs →</a></section><?php endif; ?>

		<section class="swat-app-view" data-swat-section="today">
<section class="swat-my-weekend swat-team-strip" data-swat-my-weekend data-swat-team-summary data-swat-home-only aria-labelledby="swat-your-teams">
    <div class="swat-my-weekend-head"><h2 id="swat-your-teams">Your teams</h2><button type="button" class="swat-follow-primary" data-swat-manage-teams>Follow your team</button></div>
    <div class="swat-followed-chips" data-swat-followed-chips></div>
    <div data-swat-team-strips></div>
    <details class="swat-team-all-games" hidden><summary>View all my games</summary><div id="swat-team-itinerary" class="swat-weekend-timeline" data-swat-weekend-timeline hidden></div></details>
</section>
<?php get_template_part( 'template-parts/gameday/home-tools', null, array( 'rules_url' => $gameday_rules_url ) ); ?>

				<div class="swat-championships-panel" data-swat-championships-wrap hidden>
					<div class="swat-app-section-heading">
						<div><p class="swat-app-eyebrow">TITLE GAMES</p><h2>CHAMPIONSHIPS</h2></div>
						<span data-swat-championship-count></span>
					</div>
					<div class="swat-game-list swat-championships-list" data-swat-championships></div>
				</div>
			<div class="swat-app-section-heading">
				<div><p class="swat-app-eyebrow">RIGHT NOW</p><h2>PLAYING NOW</h2></div>
				<span data-swat-live-count><?php echo esc_html( count( $initial_live ) ); ?> live</span>
			</div>
			<div class="swat-game-list" data-swat-playing><?php if ( $initial_live ) : foreach ( $initial_live as $initial_game ) : echo swat_os_game_card( $initial_game ); endforeach; else : ?><div class="swat-empty-state swat-app-empty"><strong>No live games</strong><p>No games are currently marked in progress.</p></div><?php endif; ?></div>

			<div class="swat-app-section-heading swat-section-gap">
				<div><p class="swat-app-eyebrow">COMING UP</p><h2>UP NEXT</h2></div>
			</div>
			<div class="swat-game-list" data-swat-upcoming><?php if ( $initial_upcoming ) : foreach ( $initial_upcoming as $initial_game ) : echo swat_os_game_card( $initial_game ); endforeach; else : ?><div class="swat-empty-state swat-app-empty"><strong>No upcoming games</strong><p>No upcoming games are currently listed.</p></div><?php endif; ?></div>

			<div class="swat-app-section-heading swat-section-gap">
				<div><p class="swat-app-eyebrow">LATEST</p><h2>RECENT FINALS</h2></div>
			</div>
			<div class="swat-game-list" data-swat-finals><?php if ( $initial_finals ) : foreach ( $initial_finals as $initial_game ) : echo swat_os_game_card( $initial_game ); endforeach; else : ?><div class="swat-empty-state swat-app-empty"><strong>No finals yet</strong><p>Completed games will appear here.</p></div><?php endif; ?></div>
		</section>

		<section id="swat-schedule" class="swat-app-view" data-swat-section="schedule" hidden>
			<?php echo function_exists( 'swat_child_gameday_sponsor_strip' ) ? swat_child_gameday_sponsor_strip( 'schedule', $current_event ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php
			// 1.0.19: weekend-wide team search (same component as the homepage). Hidden in team/field/game views.
			if ( function_exists( 'swat_child_render_team_finder' ) && ! $team && ! $field && ! $game ) {
				$swat_finder_weekend = swat_child_finder_weekend();
				swat_child_render_team_finder( array( 'events' => $swat_finder_weekend['events'], 'label' => $swat_finder_weekend['label'], 'variant' => 'gameday' ) );
			}
			?>
			<div class="swat-app-section-heading">

				<button type="button" class="swat-clear-filters" data-swat-clear hidden>Clear filters</button>
			</div>
			<div class="swat-day-tabs" data-swat-day-tabs role="group" aria-label="Schedule by day"></div>

			<details class="swat-filter-disclosure" data-swat-filter-disclosure>
                    <summary aria-controls="swat-schedule-filters"><span data-swat-filter-label>Filters</span><span class="swat-filter-chevron" aria-hidden="true">⌄</span></summary>
                    <div class="swat-filter-bar" id="swat-schedule-filters">
				<label><span>Division</span><select data-swat-filter="division" aria-label="Filter by division"><option value="">All divisions</option></select></label>
				<label><span>Team</span><select data-swat-filter="team" aria-label="Filter by team"><option value="">All teams</option></select></label>
				<label><span>Field</span><select data-swat-filter="field" aria-label="Filter by field"><option value="">All fields</option></select></label>
				<select data-swat-filter="date" aria-label="Filter by date" hidden><option value="">All dates</option></select>
			</div>
                </details>
			<div class="swat-game-list" data-swat-schedule><?php foreach ( $initial_games as $initial_game ) : echo swat_os_game_card( $initial_game ); endforeach; ?></div>
		</section>

		<section class="swat-app-view" data-swat-section="scores" hidden>
			<?php echo function_exists( 'swat_child_gameday_sponsor_strip' ) ? swat_child_gameday_sponsor_strip( 'scores', $current_event ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>

			<div class="swat-game-list" data-swat-scores><?php foreach ( array_merge( $initial_live, $initial_finals ) as $initial_game ) : echo swat_os_game_card( $initial_game ); endforeach; ?></div>
		</section>

		<section class="swat-app-view swat-ps" id="swat-postseason" data-swat-section="standings" hidden>
			<header class="swat-ps-head"><p class="swat-ps-eyebrow">Bracket &amp; Standings</p><h2 class="swat-ps-title" data-swat-ps-title><?php echo esc_html( (string) ( $current_event['name'] ?? 'Tournament' ) ); ?></h2></header>
			<div class="swat-postseason-controls"><label class="swat-ps-label" for="swat-bracket-division">Choose your division</label><div class="swat-ps-row"><div class="swat-bracket-filter"><select id="swat-bracket-division" data-swat-bracket-division aria-controls="swat-bracket-results swat-pool-results"><option value="">Loading divisions…</option></select></div><button type="button" class="swat-bracket-export" data-swat-bracket-download disabled>Download Bracket</button></div></div>
			<div class="swat-postseason-tabs" role="tablist" aria-label="Bracket and standings">
				<button type="button" role="tab" id="swat-ps-tab-bracket" aria-controls="swat-ps-panel-bracket" aria-selected="false" data-swat-postseason-tab="bracket">Bracket</button>
				<button type="button" class="is-active" role="tab" id="swat-ps-tab-pool" aria-controls="swat-ps-panel-pool" aria-selected="true" data-swat-postseason-tab="pool">Standings</button>
			</div>
			<div id="swat-ps-panel-bracket" role="tabpanel" aria-labelledby="swat-ps-tab-bracket" data-swat-postseason-panel="bracket" hidden><?php echo function_exists( 'swat_child_gameday_sponsor_strip' ) ? swat_child_gameday_sponsor_strip( 'bracket', $current_event ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div id="swat-bracket-results" data-swat-bracket></div></div>
			<div id="swat-ps-panel-pool" role="tabpanel" aria-labelledby="swat-ps-tab-pool" data-swat-postseason-panel="pool"><?php echo function_exists( 'swat_child_gameday_sponsor_strip' ) ? swat_child_gameday_sponsor_strip( 'standings', $current_event ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div id="swat-pool-results" data-swat-standings><?php echo swat_os_standings_markup( $initial_games ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
		</section>

		<section class="swat-app-view" data-swat-section="weather" hidden>
			<div class="swat-app-section-heading"><div><p class="swat-app-eyebrow">OFFICIAL SWAT STATUS</p><h2>WEATHER &amp; DELAYS</h2></div></div>
			<div class="swat-weather-card" data-swat-weather>
				<div class="swat-weather-message" data-swat-weather-message><strong>NO ACTIVE WEATHER ALERT</strong>
				<p>For official SWAT weather updates, delays, field closures, and restart information, check the SWAT Facebook page.</p><p>For the fastest updates, subscribe to SWAT Game Day Alerts and receive official tournament notifications directly.</p></div><div class="swat-weather-actions"><button type="button" id="swatEnableAlerts" class="swat-weather-action swat-weather-action--primary" data-swat-weather-alerts>SUBSCRIBE TO ALERTS</button><a class="swat-weather-action swat-weather-action--secondary" href="https://www.facebook.com/SWATourneys" target="_blank" rel="noopener noreferrer">VIEW SWAT FACEBOOK</a></div><p class="swat-weather-alert-feedback" data-swat-weather-alert-feedback hidden aria-live="polite"></p><p class="swat-weather-note">Official weather and delay information is communicated through SWAT Facebook and SWAT Game Day Alerts only.</p>
			</div>
		</section>

		<section class="swat-app-view" data-swat-section="more" hidden>
<h2 class="swat-more-label">At the field</h2><div class="swat-more-grid">
<button type="button" data-swat-view="fields"><strong>Fields &amp; Directions</strong><span>Maps, field view and directions</span></button>
<button type="button" data-swat-view="weather"><strong>Weather &amp; Delays</strong><span>Official SWAT status</span></button>
<a href="<?php echo esc_url( home_url( '/gameday/help/' ) ); ?>" data-swat-event-link><strong>Field Help</strong><span>Alert operations</span></a></div>
<h2 class="swat-more-label">Rules</h2><div class="swat-more-grid">
<?php if ( $gameday_rule_divisions ) : ?><a href="#rules" data-swat-view="rules"><strong>Rules</strong><span>Rules for this event, by division</span></a><?php else : ?><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>"><strong>Rules</strong><span>Find tournament rules</span></a><?php endif; ?>
<?php if ( $gdc_pitching_enabled ) : ?><button type="button" data-swat-view="pitching"><strong>Pitching</strong><span>Innings, pitch logs and limits</span></button><?php endif; ?></div>
<h2 class="swat-more-label">Coaches</h2><div class="swat-more-grid">
<a href="<?php echo esc_url( home_url( '/gameday/report-score/' ) ); ?>" data-swat-event-link><strong>Report Score</strong><span>Submit a final</span></a>
<?php if ( $gdc_pitching_enabled ) : ?><?php if ( $pitch_count_url ) : ?><a href="<?php echo esc_url( $pitch_count_url ); ?>" target="_blank" rel="noopener"><strong>Pitch logs</strong><span>Official game-by-game pitching</span></a><?php else : ?><button type="button" data-swat-view="pitching"><strong>Pitch logs</strong><span>Game-by-game pitching</span></button><?php endif; ?><?php endif; ?></div>
<div class="swat-more-footer"><div class="swat-more-grid"><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><strong>Contact SWAT</strong><span>Get help</span></a></div>
<button type="button" class="swat-refresh-link" data-swat-refresh>Refresh tournament data</button>
<button class="swat-install-link" type="button" data-swat-install hidden>ADD TO HOME SCREEN</button>
<div class="swat-appearance-setting"><strong>Appearance</strong><div class="swat-appearance" role="group" aria-label="Page appearance"><button type="button" data-swat-appearance-choice="light" aria-pressed="false">Light</button><button type="button" data-swat-appearance-choice="dark" aria-pressed="false">Dark</button></div></div></div>
</section>
<section class="swat-app-view" data-swat-section="fields" hidden>
			<div class="swat-app-section-heading"><div><p class="swat-app-eyebrow">COMPLEX VIEW</p><h2>FIELDS</h2></div></div>
			<div class="swat-field-grid" data-swat-fields></div>
		</section>
	<?php endif; ?>
</div>

<div class="swat-toast" data-swat-toast role="status" aria-live="polite"></div>

<?php if ( ! $action ) : ?>
<div class="swat-team-picker" data-swat-team-picker hidden>
	<div class="swat-team-picker-backdrop" data-swat-team-picker-close></div>
	<section class="swat-team-picker-sheet" role="dialog" aria-modal="true" aria-labelledby="swat-team-picker-title">
		<div class="swat-team-picker-head">
			<div><p class="swat-app-eyebrow">PERSONALIZE GAME DAY</p><h2 id="swat-team-picker-title">FOLLOW TEAMS</h2></div>
			<button type="button" data-swat-team-picker-close aria-label="Close team picker">DONE</button>
		</div>
		<p class="swat-team-picker-help">Choose up to 3 teams. Great for multi-kid families and coaches.</p>
		<label class="swat-team-search"><span>Search teams</span><input type="search" placeholder="Team name" autocomplete="off" data-swat-team-search></label>
		<div class="swat-team-picker-list" data-swat-team-picker-list></div>
		<div class="swat-team-picker-count" data-swat-team-picker-count>0 of 3 followed</div>
	</section>
</div>

<?php endif; ?>

