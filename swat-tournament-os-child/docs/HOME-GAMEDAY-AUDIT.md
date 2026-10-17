# Home + Game Day audit (1.0.34 review baseline)

## Architecture

This is a classic PHP child theme, text domain `swat-tournament-os-child`, parent `swat-tournament-os-master`. The parent has theme.json, but routes render PHP templates rather than block templates. Keep shared custom properties in child style.css. Parent theme version supplied for reference is 2.0.1; exact deployed version remains unverified.

| Surface | Implementation / behavior |
| --- | --- |
| Bootstrap | Child functions.php requires inc modules; parent helpers are called after load. |
| Fonts / assets | inc/fonts.php, assets/css/child-fonts.css; home assets from inc/home-data.php; Game Day stack in inc/gameday-hub.php. Game Day currently adds a second font/token sheet. |
| Home | front-page.php, inc/home-data.php, assets/css/home.css, assets/js/home.js; team finder is a retained feature in inc/team-finder.php and its CSS/JS. |
| Registration | inc/registration-status.php, registration-admin.php, waitlist.php. Availability defaults to Open without a positive availability signal. Admin overrides, sold-out/cancelled signals, dates and deadlines take precedence. Changing this fallback requires user approval. |
| Navigation | Child header.php hardcodes primary links, bypassing assigned primary menu. Parent inc/os-setup.php registers primary/footer. No bottom-tab menu registration found in supplied themes; existing installations may provide it through a plugin. Preserve existing locations. |
| Games admin | Reads `swat_game` posts and `_swat_game_*` metadata. The supplied theme does not register that CPT; upstream plugin owns it. Parent inc/os-admin.php supplies operational views. Plugin absent, so Games admin cannot be integration-tested here. |
| Game Day templates | Parent page-gameday.php/routes resolve child template-parts/gameday/app.php, home-hero.php, home-tools.php. Main rendering in assets/js/swat-gameday.js; follow/push in swat-my-weekend.js; appearance in swat-field-ux.js. |
| Data / sync | Parent inc/os-data.php reads public payload from upstream plugin, caches public data 45 sec, upcoming feed 5 min, failed feed 1 min, directory 15 min. inc/os-my-weekend.php caches all games 60 sec. Child inc/gameday-data-bridge.php reads sync option metadata. Actual Playbook import job/plugin is not supplied. |
| Refresh / age | Child polls games at 30 sec live / 60 sec otherwise, weekend 30 sec. Age uses upstream last_success_ts/last_sync, not fetch completion. Warnings after 300 sec, offline, failure or unverified source. |
| PWA | Parent inc/os-pwa.php generates manifest and service-worker routes; assets/js/swat-os.js registers worker. API network-first cache marks stale responses. Installed-iOS validation requires actual device/staging. |
| Settings / footer | Parent Customizer/theme settings and footer.php remain inherited. Do not replace their menu registrations, settings or routing. |

## Ranked home issues

Impact and effort: 1 low, 5 high. Priority considers benefit versus implementation cost.

| Rank | Issue | Impact | Effort | Action |
| --- | --- | --- | --- | --- |
| 1 | Hero Register links to a collapsed section; search pushes both CTAs down | 5 | 2 | Direct registration link; move retained finder below hero actions |
| 2 | Default Open availability is not grounded in real availability | 5 | 3 | Flag; seek approval before resolver change |
| 3 | Upcoming list starts collapsed and lacks consistent date/status/action scan | 4 | 2 | Expanded responsive grid; disabled action on real sold-out/closed states |
| 4 | Header Register can disappear on mobile; no Escape handler | 4 | 2 | Always-visible CTA; accessible menu enhancement; honor assigned primary menu |
| 5 | No date-driven Game Day banner above hero | 4 | 1 | Real event date check, no fabricated live scores |
| 6 | Home and Game Day use different golds/fonts/cards | 4 | 3 | Single token source and shared components |
| 7 | Facility block/quick-link grid missing | 3 | 2 | Add requested named facility and links without new stats/claims |
| 8 | Hardcoded prices and schedule-release promises | 4 | 1 | Document unchanged content; do not change meaning without approval |

## Ranked Game Day issues

| Rank | Issue | Impact | Effort | Action |
| --- | --- | --- | --- | --- |
| 1 | Current review palette/type differs from revised shared spec | 4 | 2 | Move palette/geometry to style.css and share fonts/components |
| 2 | Sticky header and sticky tabs need coordinated safe-area offsets | 4 | 2 | Measure header height; use shared offset |
| 3 | Existing one-off decorations remain (left accents/gradients) | 3 | 2 | Replace within both target surfaces only |
| 4 | Main next-game, refresh and championship behavior needs real browser evidence | 5 | 4 | Preserve regression-tested code; execute browser matrix when runtime is available |
| 5 | JS UI strings are largely English literals | 3 | 4 | Do not claim complete localization; localize new controls and report remaining work |

## Content requiring verification

Existing home copy includes adult admission $13; ages 13–17/seniors 60+/military $10; children 7–12 $8; 6 and under free; cashless; no sanction fees; schedule publication Wednesday 9 pm / Thursday 10 pm ET. These are hardcoded existing claims, not newly verified facts. They remain unchanged pending copy/data-source approval. No new field counts, turf claims, prices or deadlines will be introduced. Named facility and city come from the user's supplied product context.

## Local baseline limitations

No `docker`, `wp`, system `php`, or Chromium executable is available. A supplied PHP 8.3 runtime can run syntax/unit tests and guarded fixture HTTP responses. No WordPress installation/database or upstream Games/sync plugin is present. Playwright is installed as external test tooling, but Chromium download attempts in the preceding task returned invalid archives. Thus no WP_DEBUG, browser console, screenshot, Lighthouse, overflow, layout-shift, keyboard or iOS pass can be claimed. The optional generated-file checks already fail against the supplied parent on the unchanged prior baseline.
