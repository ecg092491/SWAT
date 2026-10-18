<?php
/**
 * Team profile and team directory (child 1.0.19). Routed by inc/team-profile.php.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

$view = $GLOBALS['swat_child_team_view'] ?? array( 'mode' => 'directory' );

$facility = static function ( $field ) {
	$code = strtoupper( trim( (string) $field ) );
	if ( preg_match( '/^SW\s*[- ]?\d/', $code ) ) {
		return 'Southwest Athletics Complex';
	}
	if ( preg_match( '/^WP\s*[- ]?\d/', $code ) ) {
		return 'Wilson Park';
	}
	return 0 === strpos( $code, 'WFHS' ) ? 'West Forsyth High School' : '';
};
$time12 = static function ( $time ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', (string) $time, $m ) ) {
		return 'Time TBD';
	}
	$h = (int) $m[1];
	return ( ( $h % 12 ) ?: 12 ) . ':' . $m[2] . ' ' . ( $h >= 12 ? 'PM' : 'AM' );
};
$day = static function ( $date, $format = 'D, M j' ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
	return $d ? $d->format( $format ) : '';
};
$num = static function ( $value ) {
	return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
};
$signed = static function ( $value ) use ( $num ) {
	return ( $value > 0 ? '+' : '' ) . $num( $value );
};

get_header();

if ( 'directory' === $view['mode'] ) :
	$rows = swat_child_team_directory_rows();
	?>
	<div class="stp">
		<section class="stp-hero" aria-labelledby="stp-title">
			<div class="stp-wrap">
				<p class="stp-kicker"><?php esc_html_e( 'SWAT Tournaments', 'swat-tournament-os-child' ); ?></p>
				<h1 class="stp-title" id="stp-title"><?php esc_html_e( 'Teams', 'swat-tournament-os-child' ); ?></h1>
				<p class="stp-lede"><?php esc_html_e( 'Season records, results and upcoming games for every team that has played a SWAT event.', 'swat-tournament-os-child' ); ?></p>
			</div>
		</section>
		<section class="stp-band">
			<div class="stp-wrap">
				<label class="stp-label" for="stp-search"><?php esc_html_e( 'Find a team', 'swat-tournament-os-child' ); ?></label>
				<input class="stp-search" id="stp-search" type="search" autocomplete="off" placeholder="<?php esc_attr_e( 'Team name or division', 'swat-tournament-os-child' ); ?>" data-stp-search aria-describedby="stp-count">
				<p class="stp-muted" id="stp-count" role="status" data-stp-count><?php echo esc_html( sprintf( _n( '%d team', '%d teams', count( $rows ), 'swat-tournament-os-child' ), count( $rows ) ) ); ?></p>
				<ul class="stp-teamlist" data-stp-list>
					<?php foreach ( $rows as $row ) : ?>
						<li data-stp-text="<?php echo esc_attr( strtolower( $row['name'] . ' ' . $row['divisions'] ) ); ?>">
							<a href="<?php echo esc_url( home_url( '/teams/' . $row['slug'] . '/' ) ); ?>"><strong><?php echo esc_html( $row['name'] ); ?></strong><span><?php echo esc_html( implode( ' · ', array_filter( array( $row['divisions'], $row['last'] ? 'Last played ' . $day( $row['last'], 'M j, Y' ) : '' ) ) ) ); ?></span></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	</div>
	<script>
	(() => {
		const input = document.querySelector('[data-stp-search]');
		const rows = Array.from(document.querySelectorAll('[data-stp-list] li'));
		const count = document.querySelector('[data-stp-count]');
		if (!input) return;
		input.addEventListener('input', () => {
			const tokens = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
			let shown = 0;
			rows.forEach((li) => { const hit = tokens.every((t) => li.dataset.stpText.includes(t)); li.hidden = !hit; if (hit) shown++; });
			count.textContent = shown === 1 ? '1 team' : shown + ' teams';
		});
	})();
	</script>
	<?php
elseif ( ! empty( $view['profile']['chooser'] ) ) :
	?>
	<div class="stp">
		<section class="stp-hero" aria-labelledby="stp-title"><div class="stp-wrap"><p class="stp-kicker"><a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>"><?php esc_html_e( 'Teams', 'swat-tournament-os-child' ); ?></a> · <?php esc_html_e( 'Team profile', 'swat-tournament-os-child' ); ?></p><h1 class="stp-title" id="stp-title"><?php echo esc_html( $view['profile']['name'] ); ?></h1><p class="stp-lede"><?php esc_html_e( 'More than one team uses this name. Choose the age group.', 'swat-tournament-os-child' ); ?></p></div></section>
		<section class="stp-band"><div class="stp-wrap">
			<ul class="stp-teamlist">
				<?php foreach ( $view['profile']['chooser'] as $row ) : ?>
					<li><a href="<?php echo esc_url( home_url( '/teams/' . $row['slug'] . '/' ) ); ?>"><strong><?php echo esc_html( $row['name'] ); ?></strong><span><?php echo esc_html( implode( ' · ', array_filter( array( $row['divisions'], $row['last'] ? 'Last played ' . $day( $row['last'], 'M j, Y' ) : '' ) ) ) ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</div></section>
	</div>
	<?php
elseif ( empty( $view['profile'] ) ) :
	?>
	<div class="stp">
		<section class="stp-hero"><div class="stp-wrap"><p class="stp-kicker"><?php esc_html_e( 'Team profile', 'swat-tournament-os-child' ); ?></p><h1 class="stp-title"><?php esc_html_e( 'Team not found', 'swat-tournament-os-child' ); ?></h1><p class="stp-lede"><?php esc_html_e( 'No SWAT games are on file for this team name.', 'swat-tournament-os-child' ); ?></p><a class="stp-btn stp-btn--primary" href="<?php echo esc_url( home_url( '/teams/' ) ); ?>"><?php esc_html_e( 'SEARCH ALL TEAMS', 'swat-tournament-os-child' ); ?></a></div></section>
	</div>
	<?php
else :
	$profile  = $view['profile'];
	$seasons  = $profile['seasons'];
	$keys     = array_keys( $seasons );
	$wanted   = isset( $_GET['season'] ) ? sanitize_key( wp_unslash( $_GET['season'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
	$current  = in_array( $wanted, $keys, true ) ? $wanted : ( $keys[0] ?? '' );
	$season   = $current ? $seasons[ $current ] : null;
	$rec      = $season ? $season['record'] : null;
	$gameday  = function_exists( 'swat_os_gameday_url' ) ? 'swat_os_gameday_url' : null;
	?>
	<div class="stp">
		<section class="stp-hero" aria-labelledby="stp-title">
			<div class="stp-wrap">
				<p class="stp-kicker"><a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>"><?php esc_html_e( 'Teams', 'swat-tournament-os-child' ); ?></a> · <?php esc_html_e( 'Team profile', 'swat-tournament-os-child' ); ?></p>
				<h1 class="stp-title" id="stp-title"><?php echo esc_html( $profile['name'] ); ?></h1>
				<?php if ( $season ) : ?>
					<p class="stp-record"><span><?php echo esc_html( $rec['w'] . '-' . $rec['l'] . '-' . $rec['t'] ); ?></span> <?php echo esc_html( $season['label'] ); ?></p>
				<?php endif; ?>
				<?php if ( count( $seasons ) > 1 ) : ?>
					<nav class="stp-seasons" aria-label="<?php esc_attr_e( 'Season', 'swat-tournament-os-child' ); ?>">
						<?php foreach ( $seasons as $key => $s ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'season', $key, home_url( '/teams/' . $profile['slug'] . '/' ) ) ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $s['label'] ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $profile['upcoming'] ) : ?>
			<section class="stp-band stp-band--next" aria-labelledby="stp-next">
				<div class="stp-wrap">
					<h2 class="stp-h2" id="stp-next"><?php esc_html_e( 'Upcoming games', 'swat-tournament-os-child' ); ?></h2>
					<ul class="stp-games">
						<?php foreach ( array_slice( $profile['upcoming'], 0, 8 ) as $g ) :
							$placeholder = function_exists( 'swat_child_is_placeholder_team' ) && swat_child_is_placeholder_team( $g['opponent'] );
							$place = $facility( $g['field'] );
							?>
							<li class="stp-game stp-game--next">
								<p class="stp-game__when"><strong><?php echo esc_html( $day( $g['date'] ) ); ?></strong> · <?php echo esc_html( $time12( $g['time'] ) ); ?><?php echo false !== strpos( $g['status'], 'DELAY' ) ? ' · <em>' . esc_html__( 'Delayed', 'swat-tournament-os-child' ) . '</em>' : ''; ?></p>
								<p class="stp-game__vs"><?php echo $placeholder ? esc_html__( 'Opponent TBD', 'swat-tournament-os-child' ) . ' <span>(' . esc_html( preg_replace( '/^Division Place (\d+)$/', '#$1 Seed', $g['opponent'] ) ) . ')</span>' : 'vs. <strong>' . ( function_exists( 'swat_child_team_key_link_html' ) ? swat_child_team_key_link_html( $g['opponent_key'] ?? '', $g['opponent'] ) : esc_html( $g['opponent'] ) ) . '</strong>'; ?></p>
								<p class="stp-muted"><?php echo esc_html( implode( ' · ', array_filter( array( $place, $g['field'] ? 'Field ' . $g['field'] : 'Field TBD', $g['event'] ) ) ) ); ?></p>
								<?php if ( $gameday ) : ?><a class="stp-btn stp-btn--primary" href="<?php echo esc_url( call_user_func( $gameday, array( 'event' => $g['event_slug'], 'team' => $profile['name'], 'view' => 'schedule' ) ) ); ?>"><?php esc_html_e( 'TEAM SCHEDULE & DIRECTIONS', 'swat-tournament-os-child' ); ?></a><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $season ) : ?>
			<section class="stp-band" aria-labelledby="stp-stats">
				<div class="stp-wrap">
					<h2 class="stp-h2" id="stp-stats"><?php echo esc_html( $season['label'] ); ?></h2>
					<dl class="stp-stats">
						<div><dt><?php esc_html_e( 'Record', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $rec['w'] . '-' . $rec['l'] . '-' . $rec['t'] ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Win %', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $num( $rec['pct'] ) . '%' ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Runs scored', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $rec['rs'] ); ?><small><?php echo esc_html( $num( $rec['avg_rs'] ) ); ?> / game</small></dd></div>
						<div><dt><?php esc_html_e( 'Runs allowed', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $rec['ra'] ); ?><small><?php echo esc_html( $num( $rec['avg_ra'] ) ); ?> / game</small></dd></div>
						<div><dt><?php esc_html_e( 'Run difference', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $signed( $rec['rd'] ) ); ?><small><?php echo esc_html( $signed( $rec['avg_rd'] ) ); ?> / game</small></dd></div>
						<div><dt><?php esc_html_e( 'Games', 'swat-tournament-os-child' ); ?></dt><dd><?php echo esc_html( $rec['gp'] ); ?></dd></div>
					</dl>

					<h3 class="stp-h3"><?php echo esc_html( sprintf( _n( 'Event (%d)', 'Events (%d)', count( $season['events'] ), 'swat-tournament-os-child' ), count( $season['events'] ) ) ); ?></h3>
					<ul class="stp-events">
						<?php foreach ( $season['events'] as $e ) :
							$r = $e['record'];
							?>
							<li class="stp-event">
								<div class="stp-event__main">
									<a class="stp-event__name" href="<?php echo esc_url( home_url( '/tournaments/' . $e['event_slug'] . '/' ) ); ?>"><?php echo esc_html( $e['event'] ); ?></a>
									<span class="stp-muted"><?php echo esc_html( $day( $e['date'], 'M j, Y' ) . ( $e['divisions'] ? ' · ' . $e['divisions'] : '' ) ); ?></span>
								</div>
								<div class="stp-event__nums">
									<span class="stp-chip"><?php echo esc_html( $r['w'] . '-' . $r['l'] . '-' . $r['t'] ); ?></span>
									<span><abbr title="<?php esc_attr_e( 'Average runs scored', 'swat-tournament-os-child' ); ?>">RS</abbr> <?php echo esc_html( $num( $r['avg_rs'] ) ); ?></span>
									<span><abbr title="<?php esc_attr_e( 'Average runs allowed', 'swat-tournament-os-child' ); ?>">RA</abbr> <?php echo esc_html( $num( $r['avg_ra'] ) ); ?></span>
									<span><abbr title="<?php esc_attr_e( 'Average run difference', 'swat-tournament-os-child' ); ?>">RD</abbr> <?php echo esc_html( $signed( $r['avg_rd'] ) ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>

					<h3 class="stp-h3"><?php echo esc_html( sprintf( _n( 'Result (%d)', 'Results (%d)', count( $season['games'] ), 'swat-tournament-os-child' ), count( $season['games'] ) ) ); ?></h3>
					<ul class="stp-games">
						<?php foreach ( array_reverse( $season['games'] ) as $g ) : ?>
							<li class="stp-game is-<?php echo esc_attr( strtolower( $g['result'] ) ); ?>">
								<span class="stp-result" aria-label="<?php echo esc_attr( array( 'W' => 'Win', 'L' => 'Loss', 'T' => 'Tie' )[ $g['result'] ] ); ?>"><?php echo esc_html( $g['result'] ); ?></span>
								<div class="stp-game__body">
									<p class="stp-game__vs"><?php echo $g['home'] ? 'vs.' : '@'; ?> <strong><?php echo function_exists( 'swat_child_team_key_link_html' ) && ! ( function_exists( 'swat_child_is_placeholder_team' ) && swat_child_is_placeholder_team( $g['opponent'] ) ) ? swat_child_team_key_link_html( $g['opponent_key'] ?? '', $g['opponent'] ) : esc_html( $g['opponent'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></strong> <span class="stp-score"><?php echo esc_html( $g['us'] . '–' . $g['them'] ); ?></span></p>
									<p class="stp-muted"><?php echo esc_html( implode( ' · ', array_filter( array( $day( $g['date'] ), $g['event'], 'Pool' === $g['type'] ? '' : $g['type'] ) ) ) ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $profile['playbook'][ $current ] ) ) : ?>
						<p class="stp-note"><?php esc_html_e( 'Results and record from this team\'s', 'swat-tournament-os-child' ); ?> <a href="<?php echo esc_url( $profile['playbook'][ $current ] ); ?>" rel="noopener"><?php esc_html_e( 'Playbook profile', 'swat-tournament-os-child' ); ?></a>. <?php esc_html_e( 'Missing or incorrect result? Contact SWAT.', 'swat-tournament-os-child' ); ?></p>
					<?php else : ?>
						<p class="stp-note"><?php esc_html_e( 'Includes final scores from SWAT events synced to swatourneys.com. Missing or incorrect result? Contact SWAT.', 'swat-tournament-os-child' ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		<?php elseif ( ! $profile['upcoming'] ) : ?>
			<section class="stp-band"><div class="stp-wrap"><p><?php esc_html_e( 'No final scores are on file for this team yet.', 'swat-tournament-os-child' ); ?></p></div></section>
		<?php endif; ?>
	</div>
	<?php
endif;

get_footer();
