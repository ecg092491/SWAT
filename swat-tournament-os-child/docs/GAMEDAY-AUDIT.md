# Game Day audit — 2026-10-16

Baseline: unchanged child theme 1.0.33 from the uploaded archive. GitHub SWAT only had the 1.0.32 archive; SWATGameDay is a separate SwiftUI iOS application, not this website. Parent 2.0.1 was retrieved for dependency inspection. The installed production parent/plugins have not been verified.

## Implementation map

| Layer | Files and behavior |
| --- | --- |
| Route and page shell (parent) | `inc/os-routes.php`, `page-gameday.php`, `inc/os-setup.php`; template hierarchy selects the child app and header |
| Child templates | `header.php`, `template-parts/gameday/app.php`, `home-hero.php`, `home-tools.php` (tools template exists but was not included) |
| Rendering/state | `assets/js/swat-gameday.js`: all tab views, context/team/game pages, scores, pool tables, brackets, event changes, forms, refresh; `swat-my-weekend.js`: follow storage/migration, picker, weekend itinerary, push; `swat-field-ux.js`: appearance/countdown; `swat-sponsors.js`: sponsor tracking |
| Child asset routing | `functions.php`, `inc/templates.php`, `inc/fonts.php`, `inc/gameday-field-ux.php`, `inc/gameday-hub.php`, `inc/bracket-schedule.php`, `inc/sponsors.php` |
| Loaded child CSS | `child.css`, `child-fonts.css`, `gameday-site-chrome.css`, `swat-gameday-hub.css`, `swat-gameday-compact.css`, `team-schedule.css`, `postseason.css`, `schedule-actions.css`, `sponsors.css`; `swat-field-ux.css` is enqueued then suppressed by the hub style filter |
| Parent assets retained | `inc/os-enqueue.php`, `assets/css/swat-os.css`, mobile navigation refinement, division rules, weekend pitching; `assets/js/swat-os.js`, `swat-weekend-pitching.js` and division rules behavior. Parent Game Day component CSS is suppressed by the child |
| Public data | Parent `inc/os-data.php` reads `/swat-gameday/v4/public` and event game data; `inc/os-api.php` exposes `/swat/v1/games`, `/platform`, `/alert`, `/events`; `inc/os-team-path.php` supplies bracket fixtures. Upstream Playbook sync plugin is not included |
| Child data adapters | `inc/gameday-data-bridge.php` adds event-specific `last_success_ts`, `last_sync`, `source_stale`, `sync_status` from `swat_playbook_multi_event_sync_v1`; `inc/bracket-schedule.php` labels tiers; `inc/playbook-source.php` and `inc/season-export.php` support event/results sources |
| PWA (parent) | `inc/os-pwa.php` generates `/swat-gameday.webmanifest` and `/swat-gameday-sw.js`; `inc/os-routes.php` registers routes; `assets/js/swat-os.js` registers the service worker. Network-first API caching returns `X-SWAT-Stale`; child requests worker updates. Push subscribe/key endpoints are external plugins |
| Refresh | Server JSON boots the app. Browser fetches event games plus alerts, with a 15-second abort and request sequence guard. Polls every 30 seconds with live games, otherwise 60 seconds. Separate weekend data refreshes every 30 seconds. Source timestamps drive age; fetch time is only last checked. Original UI hides the connection chip and suppresses warnings for events without active games today |

## Ranked findings (impact / effort)

1. Critical / small: Today is redirected to Schedule; Today nav and hub inclusion are absent. Restore a real Today tab and put followed live games/next game first.
2. Critical / small: `isChampionship()` uses round text and round labels can be inferred. Require strict source `championship === true` and final, numeric, unequal scores for champion announcements. Missing source flags must fail closed.
3. High / small: sync status chip hidden, stale warning restricted to active-today games, missing time is vague, refresh icon lacks visible text. Keep source age and visible Retry; use five-minute warning threshold.
4. High / medium: competing CSS assigns 6/12/16px corners, Inter/body Barlow, black accent and multiple palettes. Centralize Game Day tokens; keep unrelated site styles untouched.
5. High / medium: followed games are compact prose, no live scores in strips, Next Game DOM absent. Render reusable game cards, followed chips and later-today content before hub tiles.
6. High / medium: combined divisions are filtered by exact `division`; a combined source bracket can disappear under member divisions. Respect explicit member lists or explicitly combined source labels without moving pool records.
7. Medium / small: standings compress W/L/T and hide RS/RA in team metadata. Expose W, L, T, RS, RA, retain RD, with contained scrolling and sticky team column.
8. Medium / medium: refresh uses innerHTML, loses focus and inserts score cells only when available. Reserve score/status space, preserve focused control and disclosure state, highlight changes without adding banners.
9. Medium / small: missing-status fallback says Upcoming; use explicit status TBD. All displayed source labels must remain escaped.
10. Medium / small: dark is not the default; sticky tabs and safe-area layout need one policy, keyboard tab movement needs verification.

## Baseline verification

PHP lint and all existing tests in tests/run-all.sh pass using PHP 8.3 with DOM enabled. Initial failures without DOM were environment-related and disappeared with that extension; no source edits were used to fix them.

No installed Chromium, Lighthouse, WordPress/database, or iOS simulator was available. Playwright is installed, but browser downloads returned invalid ZIP payloads. Therefore baseline browser screenshots, Lighthouse and installed-iOS checks are BLOCKED, not passed. Local fixture rendering is not full WordPress/plugin integration and must not be presented as such. Browser validation remains a release gate.

## Outside scope / integration risks

The parent infers bracket round names in os-team-path.php. The new client champion gate must ignore those inferred names. The available parent does not establish that upstream games carry `championship:true` or combined membership metadata; integration must verify them. Do not synthesize a championship flag. The native SwiftUI app and upstream plugins are separate and are not modified here.
