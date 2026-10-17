#!/usr/bin/env python3
"""Build the child theme's three template overrides from the parent's files.

Every edit is an exact-string replacement that asserts it matched, so a parent
update that changes these regions fails loudly instead of silently diverging.
Usage: tools/build-templates.py /path/to/parent-theme /path/to/child-theme
"""
import sys, re

parent, child = sys.argv[1], sys.argv[2]
REQ_OLD = "require_once __DIR__ . '/inc/os-registration-details.php';"
REQ_NEW = "require_once SWAT_OS_DIR . '/inc/os-registration-details.php';"

def load(name):
    return open(f'{parent}/{name}', encoding='utf-8').read()

def sub(s, old, new, label, count=1):
    assert s.count(old) == count, f'[{label}] expected {count} match(es), found {s.count(old)}'
    return s.replace(old, new)

def cut(s, start, end, repl, label):
    a = s.index(start)
    b = s.index(end, a) + len(end)
    return s[:a] + repl + s[b:]

# ------------------------------------------------------------------ front page
fp = load('front-page.php')
fp = sub(fp, REQ_OLD, REQ_NEW, 'fp.require')

# 1. Drop the homepage game-time machinery (its markup is removed below).
fp = cut(fp, "$home_format_time = static function", "unset( $day_group );\n", "", 'fp.weekend-schedule-data')

# 2. Pending-schedule flag, initialised before the template output begins.
fp = sub(fp, "$gameday_url = function_exists( 'swat_os_gameday_url' ) ? swat_os_gameday_url() : home_url( '/gameday/' );\n?>",
         "$gameday_url = function_exists( 'swat_os_gameday_url' ) ? swat_os_gameday_url() : home_url( '/gameday/' );\n$swat_any_pending = false;\n?>", 'fp.pending-init')

# 3. Hero calls to action: REGISTER (primary) + OPEN GAME DAY (secondary).
fp = sub(fp,
 """<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>">FIND A TOURNAMENT</a>
				<a class="btn btn--outline" href="<?php echo esc_url( $gameday_url ); ?>">OPEN GAME DAY</a>
			</div>""",
 """<a class="btn btn--primary" href="https://play.swatourneys.com/"><?php esc_html_e( 'REGISTER', 'swat-tournament-os-child' ); ?></a>
				<a class="btn btn--outline" href="<?php echo esc_url( $gameday_url ); ?>"><?php esc_html_e( 'OPEN GAME DAY', 'swat-tournament-os-child' ); ?></a>
			</div>
			<p class="hero__more"><a class="hero__link" href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>"><?php esc_html_e( 'Find a tournament', 'swat-tournament-os-child' ); ?> &rarr;</a></p>""", 'fp.hero-ctas')

# 4. Spotlight: pending flag + registration badge/deadline for events not yet started.
fp = sub(fp, "$has_schedule  = $schedule_count > 0;\n",
         "$has_schedule  = $schedule_count > 0;\n\t\t\t\t\t\tif ( ! $has_schedule ) { $swat_any_pending = true; }\n\t\t\t\t\t\t$reg_state     = swat_child_registration_state( $spot );\n\t\t\t\t\t\t$show_reg      = $event_start > $today;\n", 'fp.spot-state')
fp = sub(fp,
 """<p class="weekend-bar__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
""",
 """<p class="weekend-bar__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
								<?php if ( $show_reg ) { echo swat_child_deadline_html( $reg_state ); } ?>
""", 'fp.spot-deadline')
fp = sub(fp,
 """<span class="weekend-bar__status"><?php echo esc_html( $status ); ?></span>""",
 """<?php if ( $show_reg ) { echo swat_child_status_badge( $reg_state ); } ?>
								<span class="weekend-bar__status"><?php echo esc_html( $status ); ?></span>""", 'fp.spot-badge')

# 5. Replace the "Full weekend schedule" block with a neutral pending note.
start = "<?php $swat_weekend_has_games = false;"
end = "<?php endif; ?>\n\t\t</div>\n\t</section>\n\t<?php endif; ?>\n\n\t<section class=\"tournament-list\""
a = fp.index(start)
b = fp.index(end, a)
note = ("<?php if ( $swat_any_pending ) : ?><p class=\"swat-release-note\">"
        "<?php esc_html_e( 'Friday schedules are expected Wednesday by 9 pm ET. Weekend schedules are expected Thursday by 10 pm ET. Open an event above to choose your division and check availability.', 'swat-tournament-os-child' ); ?></p><?php endif; ?>\n")
fp = fp[:a] + note + fp[b + len("<?php endif; ?>\n"):]
assert 'weekend-day-schedule' not in fp and 'data-weekend-day' not in fp, 'game-time markup still present'

# 6. Upcoming list: real status badge, deadline, and gated REGISTER action.
fp = sub(fp, "$has_schedule = $row['game_count'] > 0;\n",
         "$has_schedule = $row['game_count'] > 0;\n\t\t\t\t\t\t$reg_state    = swat_child_registration_state( $row );\n", 'fp.row-state')
fp = sub(fp,
 """<p class="tournament-row__badge"><?php echo $has_schedule ? 'SCHEDULE LIVE' : 'REGISTRATION OPEN'; ?></p>""",
 """<p class="tournament-row__badge"><?php echo swat_child_status_badge( $reg_state ); ?><?php if ( $has_schedule ) : ?> <span class="swat-reg-sched"><?php esc_html_e( 'SCHEDULE LIVE', 'swat-tournament-os-child' ); ?></span><?php endif; ?></p>""", 'fp.row-badge')
fp = sub(fp,
 """<p class="tournament-row__meta"><?php echo esc_html( $format_range( $row['start'], $row['end'] ) ); ?> · Clemmons, NC</p>""",
 """<p class="tournament-row__meta"><?php echo esc_html( $format_range( $row['start'], $row['end'] ) ); ?> · Clemmons, NC</p>
								<?php echo swat_child_deadline_html( $reg_state ); ?>""", 'fp.row-deadline')
fp = sub(fp,
 """<a class="btn btn--small btn--primary" href="<?php echo esc_url( swat_os_registration_url( $row ) ); ?>">REGISTER</a>""",
 """<?php echo swat_child_register_action( $reg_state, swat_os_registration_url( $row ), 'btn btn--small btn--primary' ); ?>""", 'fp.row-action')
# 12. Admission pricing (confirmed current by the site owner, 2026-10-01). The parent's
#     hardcoded copy is out of date; without this step a rebuild silently reverts it.
old_admission = "<h3>$10 ADULTS</h3><p>Seniors $7 · Ages 7–17 $7 · Ages 6 &amp; under free.</p>"
current_admission = "<h3>$13 ADULTS</h3><p>Teens 13–17, seniors 60+ &amp; military $10 · Kids 7–12 $8 · Kids 6 &amp; under free. Cashless.</p>"
if old_admission in fp:
    fp = sub(fp, old_admission, current_admission, 'fp.admission-pricing')
else:
    assert fp.count(current_admission) == 1, '[fp.admission-pricing] expected old or current admission copy exactly once' 

# 13. header.php: Game Day uses the same site header as every other page (nav, GAME DAY pill,
#     REGISTER) instead of the minimal app header, so it matches the tournament event page.
hd = load('header.php')
a = hd.index("<?php if ( $is_gameday ) : ?>\n\t<header class=\"swat-app-header\"")
b = hd.index("<?php else : ?>\n\t<header class=\"swat-site-header\"")
hd = hd[:a] + "<?php if ( true ) : /* child: site header on Game Day too */ ?>\n\t<header class=\"swat-site-header\"" + hd[b + len("<?php else : ?>\n\t<header class=\"swat-site-header\""):]
# Owner-requested header simplification: Tournaments is a direct archive link.
menu_start = hd.index('<li class="swat-menu-parent"><a href="<?php echo esc_url( home_url( \'/tournaments/\' ) ); ?>">Tournaments</a>')
menu_end = hd.index('</ul></li>', menu_start) + len('</ul></li>')
hd = hd[:menu_start] + "<li><a href=\"<?php echo esc_url( home_url( '/tournaments/' ) ); ?>\">Tournaments</a></li>\n\t\t\t\t\t<li><a href=\"<?php echo esc_url( home_url( '/teams/' ) ); ?>\">Teams</a></li>" + hd[menu_end:]
# 1.0.29: Teams (team profiles directory) sits next to Tournaments.
assert 'Upcoming &amp; Register' not in hd, 'Tournaments submenu still present'
open(f'{child}/header.php', 'w', encoding='utf-8').write(hd)

# Owner-requested homepage cleanup and expanded tournament list.
for section in ('why-play', 'facilities'):
    fp, removed = re.subn(r'\n\t<section class="' + section + r'".*?</section>\n', '\n', fp, count=1, flags=re.S)
    assert removed == 1, 'Missing homepage section: ' + section
fp = sub(fp, 'class="tournament-list__toggle" type="button" aria-expanded="false"',
         'class="tournament-list__toggle is-open" type="button" aria-expanded="true"', 'fp.expanded-toggle')
fp = sub(fp, 'data-upcoming-toggle-label>VIEW</span>', 'data-upcoming-toggle-label>HIDE</span>', 'fp.expanded-label')
fp = sub(fp, 'data-upcoming-panel hidden>', 'data-upcoming-panel>', 'fp.expanded-panel')
# The owner confirmed registration is closed for October 2–4, 2026.
# This is a homepage display change bounded to those event dates, not future weekends.
fp = sub(fp, "$reg_state     = swat_child_registration_state( $spot );",
         """$reg_state     = swat_child_registration_state( $spot );
                        if ( $spot['start'] >= '2026-10-02' && $spot['start'] <= '2026-10-04' && ! in_array( $reg_state['status'], array( 'cancelled', 'complete' ), true ) ) {
                            $reg_state['status'] = 'closed';
                            $reg_state['label'] = __( 'Closed', 'swat-tournament-os-child' );
                            $reg_state['can_register'] = false;
                            $reg_state['deadline_label'] = '';
                        }""", 'fp.closed-weekend')
fp = sub(fp, '$show_reg      = $event_start > $today;', '$show_reg      = true;', 'fp.weekend-status-visible')
fp = sub(fp, 'Open an event above to choose your division and check availability.',
         'Open an event above for schedule updates and tournament details.', 'fp.pending-copy')
# 1.0.19: front-page.php is hand-maintained (parent-first homepage, inc/home-data.php).
# It is no longer generated from the parent, so a rebuild cannot revert it.
# open(f'{child}/front-page.php', 'w', encoding='utf-8').write(fp + ('\n' if not fp.endswith('\n') else ''))

# ------------------------------------------------------------- tournament archive
ar = load('archive-tournament.php')
ar = sub(ar, REQ_OLD, REQ_NEW, 'ar.require')
ar = sub(ar, "$is_sold_out = swat_os_registration_sold_out( $item );",
         "$reg_state = swat_child_registration_state( $item, array( 'fetch' => true ) );", 'ar.state')
ar = sub(ar, """<article class="swat-event-card swat-directory-card<?php echo $is_sold_out ? ' is-sold-out' : ''; ?>\"""",
         """<article class="swat-event-card swat-directory-card is-reg-<?php echo esc_attr( str_replace( '_', '-', $reg_state['status'] ) ); ?><?php echo 'sold_out' === $reg_state['status'] ? ' is-sold-out' : ''; ?>\"""", 'ar.article')
ar = sub(ar,
 """<div class="swat-badge <?php echo $is_sold_out ? 'is-sold-out' : ( 'COMPLETE' === $state ? 'is-final' : 'is-open' ); ?>"><?php echo esc_html( $is_sold_out ? 'SOLD OUT' : $state ); ?></div>""",
 """<div class="swat-reg-row"><?php echo swat_child_status_badge( $reg_state ); ?><?php if ( 'COMPLETE' !== $state ) : ?><span class="swat-reg-when"><?php echo esc_html( $state ); ?></span><?php endif; ?></div>""", 'ar.badge')
ar = sub(ar, """\t\t\t\t\t\t<div class="swat-event-actions">""",
         """\t\t\t\t\t\t<?php echo swat_child_deadline_html( $reg_state ); ?>
\t\t\t\t\t\t<div class="swat-event-actions">""", 'ar.deadline')
ar = re.sub(r"<\?php if \( 'COMPLETE' !== \$state && ! \$is_sold_out \) : \?>.*?<\?php endif; \?>",
            "<?php echo swat_child_register_action( $reg_state, swat_os_registration_url( $item ), '' ); ?>", ar, count=1, flags=re.S)
assert '$is_sold_out' not in ar, 'archive still references $is_sold_out'
# 1.0.19: archive-tournament.php is hand-maintained (schedule/registration actions use inc/home-data.php).
# open(f'{child}/archive-tournament.php', 'w', encoding='utf-8').write(ar)

# ----------------------------------------------------------------- single event
si = load('single-tournament.php')
si = sub(si, REQ_OLD, REQ_NEW, 'si.require')
si = sub(si, "$all_division_attr = implode( '||', array_merge( array( 'all' ), $division_names ) );\n",
         "$all_division_attr = implode( '||', array_merge( array( 'all' ), $division_names ) );\n$reg_state = swat_child_registration_state( $event, array( 'fetch' => true, 'end' => $effective_end_date ) );\n", 'si.state')
# Hero summary: only encourage registering when registration is actually available; fix missing space.
si = sub(si, "$is_future ? 'Choose your division and register early, before schedules are posted.' . swat_os_schedule_release( $event ) :",
         "$is_future ? ( $reg_state['can_register'] ? 'Choose your division and register before schedules are posted. ' : '' ) . swat_os_schedule_release( $event ) :", 'si.summary')
# Hero: status badge + deadline line under the date.
si = sub(si, """<p class="swat-tournament-summary">""",
         """<?php if ( $is_future || 'cancelled' === $reg_state['status'] ) : ?><p class="swat-reg-hero"><?php echo swat_child_status_badge( $reg_state ); ?></p><?php echo swat_child_deadline_html( $reg_state ); ?><?php endif; ?>
\t\t<p class="swat-tournament-summary">""", 'si.hero-badge')
si = sub(si,
 """<?php if ( $is_future ) : ?><a class="swat-btn swat-btn-ghost swat-btn-lg" href="<?php echo esc_url( swat_os_registration_url( $event ) ); ?>">REGISTER</a><?php endif; ?>""",
 """<?php if ( $is_future ) { echo swat_child_register_action( $reg_state, swat_os_registration_url( $event ), 'swat-btn swat-btn-ghost swat-btn-lg' ); } ?>""", 'si.hero-register')
si = sub(si, "swat_os_render_registration_details( $event );", "swat_child_render_registration_details( $event, $reg_state );", 'si.details')
# 1.0.19: single-tournament.php is hand-maintained (Gold / Silver final labels).
# open(f'{child}/single-tournament.php', 'w', encoding='utf-8').write(si)
print('templates built')
