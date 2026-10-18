<?php
/**
 * SWAT homepage (child 1.0.19; hero redesigned 1.0.23).
 *
 * Parent-first: the hero itself holds the team search ("when and where does
 * my child play?"), then This Weekend, then registration.
 * All labels and destinations come from swat_child_home_sections()
 * (inc/home-data.php); styles live in assets/css/home.css only.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

// Load this template dependency explicitly across theme deployment transitions.
require_once SWAT_OS_DIR . '/inc/os-registration-details.php';

get_header();

$sections    = swat_child_home_sections();
$weekend     = $sections['weekend'];
$gameday_url = function_exists( 'swat_os_gameday_url' ) ? swat_os_gameday_url() : home_url( '/gameday/' );

/**
 * One event card / row.
 *
 * @param array  $event   Event model.
 * @param string $variant 'weekend' | 'list'.
 */
$render_event = static function ( $event, $variant ) {
	$tag = 'h3';
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $event['start'], wp_timezone() );
	?>
	<article class="swh-event swh-event--<?php echo esc_attr( $variant ); ?><?php echo $event['is_live'] ? ' is-live' : ''; ?>">
		<p class="swh-event__when">
			<span class="swh-event__date"><?php if ( $date ) : ?><small><?php echo esc_html( wp_date( 'M', $date->getTimestamp() ) ); ?></small><?php echo esc_html( wp_date( 'j', $date->getTimestamp() ) ); ?><?php else : ?><?php esc_html_e( 'TBD', 'swat-tournament-os-child' ); ?><?php endif; ?></span>
			<?php if ( $event['is_live'] && $event['has_schedule'] ) : ?><span class="swh-chip swh-chip--live"><?php esc_html_e( 'Happening now', 'swat-tournament-os-child' ); ?></span><?php endif; ?>
			<?php if ( $event['show_badge'] ) { echo swat_child_home_status_badge( $event['reg_state'] ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</p>
		<<?php echo esc_html( $tag ); ?> class="swh-event__title"><a href="<?php echo esc_url( $event['detail_url'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a></<?php echo esc_html( $tag ); ?>>
		<p class="swh-event__meta"><?php echo esc_html( $event['dates'] ?: __( 'Dates TBD', 'swat-tournament-os-child' ) ); ?></p>
		<?php if ( $event['divisions'] ) : ?><p class="swh-event__meta"><?php echo esc_html( $event['divisions'] ); ?> · <?php esc_html_e( 'Clemmons, NC', 'swat-tournament-os-child' ); ?></p><?php else : ?><p class="swh-event__meta"><?php esc_html_e( 'Divisions TBD · Clemmons, NC', 'swat-tournament-os-child' ); ?></p><?php endif; ?>
		<?php if ( $event['note'] ) : ?><p class="swh-event__note<?php echo $event['has_schedule'] ? ' is-posted' : ''; ?>"><?php echo esc_html( $event['note'] ); ?></p><?php endif; ?>
		<?php if ( $event['deadline_label'] ) : ?><p class="swh-event__note"><?php echo esc_html( $event['deadline_label'] ); ?></p><?php elseif ( $event['show_badge'] && in_array( $event['reg_state']['status'], array( 'open', 'limited' ), true ) ) : ?><p class="swh-event__note"><?php esc_html_e( 'Registration deadline TBD', 'swat-tournament-os-child' ); ?></p><?php endif; ?>
		<div class="swh-event__actions">
			<?php if ( in_array( $event['reg_state']['status'], array( 'sold_out', 'closed' ), true ) ) : ?><button type="button" class="swh-btn" disabled><?php esc_html_e( 'Registration unavailable', 'swat-tournament-os-child' ); ?></button><?php endif; ?>
			<?php foreach ( $event['actions'] as $action ) { swat_child_home_action_link( $action ); } ?>
		</div>
	</article>
	<?php
};
?>
<noscript><style>.swh .swh-disclosure__panel[hidden]{display:block}.swh .swh-disclosure__toggle .swh-chevron{display:none}</style></noscript>
<div class="swh" data-swh>
	<?php
	// 1.0.23 hero: the team finder lives inside the hero (right column on desktop,
	// straight under the headline on phones). The status line is driven by the
	// same weekend models as the This Weekend cards, so it never claims more.
	$hero_live = false;
	foreach ( $weekend as $event ) {
		$hero_live = $hero_live || ( $event['is_live'] && 'cancelled' !== $event['reg_state']['status'] );
	}
	?>
	<?php if ( $hero_live ) : ?><a class="swh-live-banner swh-wrap" href="<?php echo esc_url( $gameday_url ); ?>"><span><?php esc_html_e( 'Game Day is live', 'swat-tournament-os-child' ); ?></span><span><?php esc_html_e( 'Open Game Day →', 'swat-tournament-os-child' ); ?></span></a><?php endif; ?>
	<section class="swh-hero" aria-labelledby="swh-hero-title">
		<div class="swh-wrap swh-hero__grid">
			<div class="swh-hero__copy">
				<p class="swh-kicker"><?php esc_html_e( 'Youth baseball · Clemmons, NC', 'swat-tournament-os-child' ); ?></p>
				<h1 class="swh-hero__title" id="swh-hero-title"><?php esc_html_e( 'Youth baseball tournaments', 'swat-tournament-os-child' ); ?> <span><?php esc_html_e( 'in the NC Triad', 'swat-tournament-os-child' ); ?></span></h1>
				<p class="swh-hero__text"><?php esc_html_e( 'Pool play, brackets and Game Day for every SWAT event.', 'swat-tournament-os-child' ); ?></p>

			</div>
			<div class="swh-hero__finder">
				<?php swat_child_render_team_finder( array( 'events' => $weekend, 'label' => $sections['weekend_label'], 'variant' => 'home' ) ); ?>
				<p class="swh-hero__teams"><a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>"><?php esc_html_e( 'Browse all teams', 'swat-tournament-os-child' ); ?> <span aria-hidden="true">→</span></a></p>
			</div>
			<div class="swh-hero__trust">
				<ul class="swh-hero__proof" aria-label="<?php esc_attr_e( 'Why SWAT', 'swat-tournament-os-child' ); ?>">
					<li><?php esc_html_e( 'Clemmons, NC', 'swat-tournament-os-child' ); ?></li>
					<li><?php esc_html_e( 'Local & independent', 'swat-tournament-os-child' ); ?></li>
					<li><?php esc_html_e( 'No sanction fees', 'swat-tournament-os-child' ); ?></li>
				</ul>
			</div>
			<div class="swh-hero__ctas">
				<a class="swh-btn swh-btn--primary swh-btn--lg" href="https://play.swatourneys.com/"><?php esc_html_e( 'REGISTER FOR A TOURNAMENT', 'swat-tournament-os-child' ); ?></a>
				<a class="swh-btn swh-btn--secondary swh-btn--lg" href="<?php echo esc_url( $gameday_url ); ?>"><?php esc_html_e( 'OPEN GAME DAY', 'swat-tournament-os-child' ); ?></a>
			</div>
		</div>
	</section>

	<?php if ( $weekend ) : ?>
		<section class="swh-band swh-weekend" id="this-weekend" aria-labelledby="swh-weekend-title">
			<div class="swh-wrap">
				<div class="swh-head">
					<p class="swh-kicker"><?php esc_html_e( 'This weekend', 'swat-tournament-os-child' ); ?></p>
					<h2 class="swh-head__title" id="swh-weekend-title"><?php echo esc_html( $sections['weekend_label'] ); ?> <span><?php esc_html_e( '· Clemmons, NC', 'swat-tournament-os-child' ); ?></span></h2>
				</div>
				<div class="swh-grid">
					<?php foreach ( $weekend as $event ) { $render_event( $event, 'weekend' ); } ?>
				</div>
				<?php if ( $sections['any_pending'] ) : ?>
					<p class="swh-footnote"><?php esc_html_e( 'Friday schedules are expected Wednesday by 9 pm ET. Weekend schedules are expected Thursday by 10 pm ET.', 'swat-tournament-os-child' ); ?></p>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="swh-band swh-lists" id="tournaments" aria-label="<?php esc_attr_e( 'Tournaments', 'swat-tournament-os-child' ); ?>">
		<div class="swh-wrap">
			<div class="swh-disclosure is-open" data-swh-disclosure="upcoming">
				<h2 class="swh-disclosure__heading">
					<button type="button" class="swh-disclosure__toggle" aria-expanded="true" aria-controls="swh-upcoming-panel" id="swh-upcoming-toggle" data-swh-toggle>
						<span class="swh-disclosure__copy"><span class="swh-kicker"><?php esc_html_e( 'Register', 'swat-tournament-os-child' ); ?></span><span class="swh-disclosure__title"><?php esc_html_e( 'Upcoming tournaments', 'swat-tournament-os-child' ); ?></span></span>
						<span class="swh-disclosure__count"><?php echo esc_html( count( $sections['upcoming'] ) + $sections['upcoming_more'] ); ?></span>
						<svg class="swh-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
					</button>
				</h2>
				<div class="swh-disclosure__panel" id="swh-upcoming-panel" role="region" aria-labelledby="swh-upcoming-toggle">
					<?php if ( $sections['upcoming'] ) : ?>
						<div class="swh-list">
							<?php foreach ( $sections['upcoming'] as $event ) { $render_event( $event, 'list' ); } ?>
						</div>
					<?php else : ?>
						<p class="swh-empty"><?php esc_html_e( 'New tournaments are added regularly. Check back soon.', 'swat-tournament-os-child' ); ?></p>
					<?php endif; ?>
					<a class="swh-btn swh-btn--secondary swh-btn--block" href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>"><?php esc_html_e( 'VIEW ALL TOURNAMENTS', 'swat-tournament-os-child' ); ?></a>
				</div>
			</div>

			<?php if ( $sections['previous'] ) : ?>
				<div class="swh-disclosure swh-disclosure--past" data-swh-disclosure="previous">
					<h2 class="swh-disclosure__heading">
						<button type="button" class="swh-disclosure__toggle" aria-expanded="false" aria-controls="swh-previous-panel" id="swh-previous-toggle" data-swh-toggle>
							<span class="swh-disclosure__copy"><span class="swh-kicker swh-kicker--muted"><?php esc_html_e( 'Results', 'swat-tournament-os-child' ); ?></span><span class="swh-disclosure__title"><?php esc_html_e( 'Previous tournaments', 'swat-tournament-os-child' ); ?></span></span>
							<span class="swh-disclosure__count"><?php echo esc_html( count( $sections['previous'] ) ); ?></span>
							<svg class="swh-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
						</button>
					</h2>
					<div class="swh-disclosure__panel" id="swh-previous-panel" role="region" aria-labelledby="swh-previous-toggle" hidden>
						<div class="swh-list">
							<?php foreach ( $sections['previous'] as $event ) { $render_event( $event, 'list' ); } ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="swh-band swh-gameday" aria-labelledby="swh-gameday-title">
		<div class="swh-wrap swh-gameday__inner">
			<div>
				<p class="swh-kicker"><?php esc_html_e( 'SWAT Game Day', 'swat-tournament-os-child' ); ?></p>
				<h2 class="swh-head__title" id="swh-gameday-title"><?php esc_html_e( 'Everything you need. One place.', 'swat-tournament-os-child' ); ?></h2>
				<p class="swh-text"><?php esc_html_e( 'Schedules, scores, standings, pitching, fields, rules and tournament updates — organized for your phone.', 'swat-tournament-os-child' ); ?></p>
			</div>
			<a class="swh-btn swh-btn--primary swh-btn--lg" href="<?php echo esc_url( $gameday_url ); ?>"><?php esc_html_e( 'OPEN GAME DAY', 'swat-tournament-os-child' ); ?></a>
		</div>
	</section>

	<section class="swh-band" aria-labelledby="swh-facility-title"><div class="swh-wrap"><div class="swh-card">
        <h2 class="swh-head__title" id="swh-facility-title"><?php esc_html_e( 'Southwest Athletics Complex', 'swat-tournament-os-child' ); ?></h2>
        <p class="swh-text"><?php esc_html_e( 'Clemmons, NC', 'swat-tournament-os-child' ); ?></p>
        <a class="swh-btn swh-btn--secondary swh-btn--lg" href="<?php echo esc_url( home_url( '/fields-facilities/' ) ); ?>"><?php esc_html_e( 'Fields & directions', 'swat-tournament-os-child' ); ?></a>
    </div></div></section>

	<section class="swh-band swh-light swh-arrival" aria-labelledby="swh-arrival-title">
		<div class="swh-wrap">
			<p class="swh-kicker swh-kicker--dark"><?php esc_html_e( 'Know before you arrive', 'swat-tournament-os-child' ); ?></p>
			<h2 class="swh-head__title" id="swh-arrival-title"><?php esc_html_e( 'Make game day easy.', 'swat-tournament-os-child' ); ?></h2>
            <nav class="swh-quick-links" aria-label="<?php esc_attr_e( 'Know before you go', 'swat-tournament-os-child' ); ?>">
                <?php foreach ( array( '/rules/' => __( 'Rules', 'swat-tournament-os-child' ), '/admissions/' => __( 'Admissions', 'swat-tournament-os-child' ), '/fields-facilities/' => __( 'Directions', 'swat-tournament-os-child' ), '/contact/' => __( 'Contact', 'swat-tournament-os-child' ) ) as $path => $label ) : ?>
                <a class="swh-card" href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
			<div class="swh-faq">
				<details>
					<summary><span><?php esc_html_e( 'Admission', 'swat-tournament-os-child' ); ?> <strong><?php esc_html_e( '$13 adults', 'swat-tournament-os-child' ); ?></strong></span><svg class="swh-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></summary>
					<div><p><?php esc_html_e( 'Teens 13–17, seniors 60+ & military $10 · Kids 7–12 $8 · Kids 6 & under free. Cashless.', 'swat-tournament-os-child' ); ?></p><a href="<?php echo esc_url( home_url( '/admissions/' ) ); ?>"><?php esc_html_e( 'Admission info', 'swat-tournament-os-child' ); ?> <span aria-hidden="true">→</span></a></div>
				</details>
				<details>
					<summary><span><?php esc_html_e( 'Parking & field directions', 'swat-tournament-os-child' ); ?></span><svg class="swh-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></summary>
					<div><p><?php esc_html_e( 'Parking, field maps, directions, concessions and complex information.', 'swat-tournament-os-child' ); ?></p><a href="<?php echo esc_url( home_url( '/fields-facilities/' ) ); ?>"><?php esc_html_e( 'Fields & facilities', 'swat-tournament-os-child' ); ?> <span aria-hidden="true">→</span></a></div>
				</details>
				<details>
					<summary><span><?php esc_html_e( 'Rules & policies', 'swat-tournament-os-child' ); ?></span><svg class="swh-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></summary>
					<div><p><?php esc_html_e( 'Review facility and division rules before your weekend begins.', 'swat-tournament-os-child' ); ?></p><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>"><?php esc_html_e( 'View rules', 'swat-tournament-os-child' ); ?> <span aria-hidden="true">→</span></a></div>
				</details>
			</div>
		</div>
	</section>

	<section class="swh-band swh-light swh-about" aria-labelledby="swh-about-title">
		<div class="swh-wrap">
			<h2 class="swh-head__title swh-head__title--sm" id="swh-about-title"><?php esc_html_e( 'Local. Independent. SWAT.', 'swat-tournament-os-child' ); ?></h2>
			<p class="swh-text"><?php esc_html_e( 'Independent youth travel baseball tournaments in the NC Triad. Competitive entry fees, no sanction fees, and a better game-day experience.', 'swat-tournament-os-child' ); ?></p>
			<a class="swh-textlink" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About SWAT', 'swat-tournament-os-child' ); ?> <span aria-hidden="true">→</span></a>
		</div>
	</section>
    <div class="swh-wrap swh-appearance" role="group" aria-label="<?php esc_attr_e( 'Appearance', 'swat-tournament-os-child' ); ?>"><button type="button" data-swat-home-theme="light" aria-pressed="false"><?php esc_html_e( 'Light', 'swat-tournament-os-child' ); ?></button><button type="button" data-swat-home-theme="dark" aria-pressed="true"><?php esc_html_e( 'Dark', 'swat-tournament-os-child' ); ?></button></div>
</div>
<?php
get_footer();
