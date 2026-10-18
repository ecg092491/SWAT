<?php defined( 'ABSPATH' ) || exit;
$gdc_event_slug = function_exists( 'swat_os_current_event' ) ? sanitize_title( (string) ( swat_os_current_event()['slug'] ?? '' ) ) : sanitize_title( (string) ( $_GET['event'] ?? '' ) );
$gdc_pitching_enabled = function_exists( 'gdc_gameday_pitching_enabled' ) ? gdc_gameday_pitching_enabled( $gdc_event_slug ) : true;
?>
<section class="swat-home-tools" data-swat-home-only aria-labelledby="swat-home-tools-title">
<div class="swat-home-section-head"><h2 id="swat-home-tools-title">TOURNAMENT HUB</h2></div>
<div class="swat-home-tool-grid">
<button type="button" class="is-primary" data-swat-view="schedule"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></span><strong>SCHEDULE</strong><small>Games &amp; results</small></button>
<button type="button" data-swat-view="scores"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V9M12 19V4M20 19v-7"/></svg></span><strong>SCORES</strong><small>Live results</small></button>
<button type="button" data-swat-view="standings"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4h8v5a4 4 0 0 1-8 0ZM8 6H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4M12 13v7M8 20h8"/></svg></span><strong>STANDINGS</strong><small>Pool rankings</small></button>
<button type="button" data-swat-view="fields"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-6 6-12a6 6 0 1 0-12 0c0 6 6 12 6 12Z"/><circle cx="12" cy="9" r="2"/></svg></span><strong>FIELDS</strong><small>Fields &amp; directions</small></button>
<?php if ( $gdc_pitching_enabled ) : ?><button type="button" data-swat-view="pitching"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M8 5c5 5 5 9 0 14M16 5c-5 5-5 9 0 14"/></svg></span><strong>PITCHING</strong><small>Team event innings</small></button><?php endif; ?>
<a <?php if ( ( $args['rules_url'] ?? '' ) === '#swat-gameday-rules' ) echo 'data-swat-view="rules"'; ?> href="<?php echo esc_url( $args['rules_url'] ?? home_url( '/rules/' ) ); ?>"><span class="swat-home-tool-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5ZM8 8h8M8 12h8M8 16h5"/></svg></span><strong>RULES</strong><small>Your age division</small></a>
</div>
	</section>
