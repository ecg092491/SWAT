<?php
/**
 * "Find my team's schedule" (child 1.0.19).
 *
 * One searchable index of every team in this weekend's published schedules,
 * with division and event so similar names can be told apart. Each result
 * opens the existing Game Day team view for the correct event:
 *   /gameday/?event={slug}&team={synced team id}&view=schedule
 *
 * Source: the synchronized swat_game records (swat_os_event_games). Bracket
 * placeholders ("Division Place 2", "Winner # 1") are never listed as teams.
 * Nothing is written; no times, fields or matchups are invented.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * True for bracket slot labels that are not real teams.
 *
 * @param string $name Team label.
 * @return bool
 */
function swat_child_is_placeholder_team( $name ) {
	$name = trim( (string) $name );
	return '' === $name || (bool) preg_match( '/^(tbd|tba|bye|winner\b|loser\b|seed\b|division place\b|#?\d+(st|nd|rd|th)?\s*seed\b|pool\s+[a-z0-9]+\s+#?\d)/i', $name );
}

/**
 * Searchable team index for a set of homepage event models.
 *
 * @param array $events Event models from swat_child_home_event_model() (need name, slug, title).
 * @return array<int,array{name:string,team:string,division:string,event:string,event_slug:string,date:string,url:string}>
 */
function swat_child_team_finder_index( $events ) {
	$index = array();
	foreach ( (array) $events as $event ) {
		if ( empty( $event['has_schedule'] ) || ! empty( $event['complete'] ) || ! function_exists( 'swat_os_event_games' ) ) {
			continue;
		}
		$event_games = swat_os_event_games( (string) $event['name'] );
		foreach ( $event_games as &$source_row ) { $source_row['event_name'] = $event['name']; }
		unset( $source_row );
		if ( function_exists( 'swat_child_source_apply' ) ) { $event_games = swat_child_source_apply( $event_games, array( $event['name'] ) ); }
		foreach ( $event_games as $game ) {
			foreach ( array( 'away', 'home' ) as $side ) {
				$name = trim( sanitize_text_field( (string) ( $game[ $side ] ?? '' ) ) );
				if ( swat_child_is_placeholder_team( $name ) ) {
					continue;
				}
				$team_id  = trim( (string) ( $game[ $side . '_id' ] ?? '' ) );
				$division = trim( sanitize_text_field( (string) ( $game['division'] ?? '' ) ) );
				$key      = $event['slug'] . '|' . strtolower( $name ) . '|' . strtolower( $division );
				$date     = (string) ( $game['date'] ?? '' );
				if ( isset( $index[ $key ] ) ) {
					if ( $date && $date < $index[ $key ]['date'] ) {
						$index[ $key ]['date'] = $date;
					}
					continue;
				}
				$index[ $key ] = array(
					'name'       => $name,
					'team'       => '' !== $team_id ? $team_id : $name,
					'division'   => preg_replace( '/\s+Open$/i', '', $division ),
					'event'      => (string) ( $event['title'] ?? $event['name'] ),
					'event_slug' => (string) $event['slug'],
					'date'       => $date,
					'url'        => swat_os_gameday_url(
						array(
							'event' => $event['slug'],
							'team'  => '' !== $team_id ? $team_id : $name,
							'view'  => 'schedule',
						)
					),
				);
			}
		}
	}
	$index = array_values( $index );
	foreach ( $index as &$item ) {
		$item['when'] = $item['date'] ? swat_child_home_date_range( $item['date'], '', true ) : '';
	}
	unset( $item );
	usort(
		$index,
		static function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] ) ?: strnatcasecmp( $a['division'], $b['division'] ) ?: strcmp( $a['date'], $b['date'] );
		}
	);
	return $index;
}

/**
 * Render the finder.
 *
 * @param array $args {
 *     @type array  $events  Weekend event models.
 *     @type string $label   Weekend label, e.g. "Oct 9–11".
 *     @type string $variant 'home' | 'gameday'.
 * }
 * @return void
 */
function swat_child_render_team_finder( $args ) {
	$events  = (array) ( $args['events'] ?? array() );
	$variant = 'gameday' === ( $args['variant'] ?? '' ) ? 'gameday' : 'home';
	$index   = swat_child_team_finder_index( $events );
	$live    = array_values( array_filter( $events, static function ( $event ) { return ! empty( $event['has_schedule'] ) && empty( $event['complete'] ); } ) );
	$pending = array_values( array_filter( $events, static function ( $event ) { return empty( $event['has_schedule'] ) && empty( $event['complete'] ) && 'cancelled' !== ( $event['reg_state']['status'] ?? '' ); } ) );
	$id      = 'swat-finder-' . $variant;
	$heading = 'h2';
	?>
	<section class="swat-finder swat-finder--<?php echo esc_attr( $variant ); ?>" id="<?php echo 'home' === $variant ? 'find-team' : 'find-team-gameday'; ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>-heading" data-swat-finder>
		<<?php echo esc_html( $heading ); ?> class="swat-finder__title" id="<?php echo esc_attr( $id ); ?>-heading"><?php esc_html_e( 'Find my team’s schedule', 'swat-tournament-os-child' ); ?></<?php echo esc_html( $heading ); ?>>
		<p class="swat-finder__lede">
			<?php
			if ( $index ) {
				esc_html_e( 'Search your team to see its next game time, field and directions.', 'swat-tournament-os-child' );
			} elseif ( $pending ) {
				esc_html_e( 'This weekend’s schedules are not posted yet. Team search opens as soon as SWAT publishes them.', 'swat-tournament-os-child' );
			} else {
				esc_html_e( 'No published schedules for this weekend yet.', 'swat-tournament-os-child' );
			}
			?>
		</p>

		<div class="swat-finder__followed" data-swat-finder-followed hidden></div>

		<?php if ( $index ) : ?>
			<div class="swat-finder__search">
				<label class="swat-finder__label" for="<?php echo esc_attr( $id ); ?>-input"><?php esc_html_e( 'Team name', 'swat-tournament-os-child' ); ?></label>
				<input class="swat-finder__input" id="<?php echo esc_attr( $id ); ?>-input" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Start typing your team name', 'swat-tournament-os-child' ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-hint" aria-controls="<?php echo esc_attr( $id ); ?>-results" data-swat-finder-input>
				<p class="swat-finder__hint" id="<?php echo esc_attr( $id ); ?>-hint">
					<?php
					/* translators: 1: number of teams, 2: event names. */
					echo esc_html( sprintf( _n( '%1$d team playing in %2$s.', '%1$d teams playing in %2$s.', count( $index ), 'swat-tournament-os-child' ), count( $index ), implode( ', ', wp_list_pluck( $live, 'title' ) ) ) );
					?>
				</p>
			</div>
			<p class="swat-finder__status" role="status" aria-live="polite" data-swat-finder-status></p>
			<ul class="swat-finder__results" id="<?php echo esc_attr( $id ); ?>-results" data-swat-finder-results hidden></ul>
			<noscript>
				<ul class="swat-finder__results">
					<?php foreach ( $index as $item ) : ?>
						<li><a class="swat-finder__result" href="<?php echo esc_url( $item['url'] ); ?>"><strong><?php echo esc_html( $item['name'] ); ?></strong><span><?php echo esc_html( implode( ' · ', array_filter( array( $item['division'], $item['event'], $item['when'] ) ) ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</noscript>
			<script type="application/json" data-swat-finder-index><?php echo wp_json_encode( $index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
		<?php endif; ?>

		<?php if ( $live ) : ?>
			<nav class="swat-finder__events" aria-label="<?php esc_attr_e( 'Full schedules this weekend', 'swat-tournament-os-child' ); ?>">
				<span class="swat-finder__events-label"><?php esc_html_e( 'Or open a full schedule:', 'swat-tournament-os-child' ); ?></span>
				<?php foreach ( $live as $event ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'schedule', $event['gameday_url'] ) ); ?>"><?php echo esc_html( $event['title'] ); ?><?php echo $event['divisions'] ? ' <span>' . esc_html( $event['divisions'] ) . '</span>' : ''; ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<?php foreach ( $pending as $event ) : ?>
			<p class="swat-finder__pending"><strong><?php echo esc_html( $event['title'] ); ?>:</strong> <?php echo esc_html( $event['note'] ? $event['note'] : __( 'Schedule coming soon.', 'swat-tournament-os-child' ) ); ?></p>
		<?php endforeach; ?>
	</section>
	<?php
}

/**
 * Finder script/style on the homepage and Game Day.
 *
 * @return void
 */
function swat_child_finder_assets() {
	$gameday = function_exists( 'swat_os_is_gameday_request' ) && swat_os_is_gameday_request();
	if ( ! is_front_page() && ! $gameday ) {
		return;
	}
	$css = SWAT_CHILD_DIR . '/assets/css/team-finder.css';
	$js  = SWAT_CHILD_DIR . '/assets/js/team-finder.js';
	wp_enqueue_style( 'swat-child-team-finder', SWAT_CHILD_URI . '/assets/css/team-finder.css', array(), (string) filemtime( $css ) );
	wp_enqueue_script( 'swat-child-team-finder', SWAT_CHILD_URI . '/assets/js/team-finder.js', array(), (string) filemtime( $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'swat_child_finder_assets', 220 );

/**
 * Weekend models for Game Day (shared with the homepage logic).
 *
 * @return array{events:array,label:string}
 */
function swat_child_finder_weekend() {
	static $cache = null;
	if ( null === $cache ) {
		$sections = function_exists( 'swat_child_home_sections' ) ? swat_child_home_sections() : array( 'weekend' => array(), 'weekend_label' => '' );
		$cache    = array( 'events' => $sections['weekend'], 'label' => $sections['weekend_label'] );
	}
	return $cache;
}
