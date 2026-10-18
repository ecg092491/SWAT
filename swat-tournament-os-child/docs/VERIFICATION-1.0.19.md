# 1.0.19 verification: parent-first homepage + team schedule

Prepared 2026-10-08. **Not installed on swatourneys.com.** Everything below was run against a local WordPress 6.8.3 copy (PHP 8.3, SQLite) with the parent theme 2.0.0 from the live File Manager export and fixture data copied from the live `/wp-json/swat/v1/platform` and `/swat/v1/games?event=pink-ribbon-classic` responses of 2026-10-08 (three events Oct 9–11, the real Pink Ribbon pool games and 17 bracket fixtures, past finals, upcoming Playbook feed rows).

## Executed checks (all pass)

| Suite | Result |
|---|---|
| `php -l` on every PHP file | clean |
| `tests/resolver-test.php` (existing) | 112 cases + 4 sanitize, 0 failures |
| `tests/home-actions-test.php` (new) | 48 checks, 0 failures: every combination of schedule / started / complete × 6 registration states, plus bracket placeholder detection |
| `tests/uri-filter-test.php`, `tests/routing-menu-test.php` (existing) | pass |
| Fonts CSS current, asset-URL audit | pass |
| `tests/browser/mobile-menu.cjs` (existing) | pass, all widths |
| Browser scenarios (44 checks, Chromium, clock fixed to Sat Oct 10 1:30 PM ET) | pass |
| Responsive audit 320/375/390/430/768/1024/1440 × homepage, Game Day schedule, team view | no horizontal scroll, no clipped text, every link/button ≥44 px, no JS errors |
| Homepage link audit (44 unique links: label → destination → HTTP 200 and event API 200) | 0 bad |

Scenario checks covered: next game skips a FINAL game; delayed status shown beside the next game; cancelled game never "next" but still listed; resolved bracket slot keeps its time/field with opponent TBD; unresolved bracket games listed as "depend on advancement"; team schedule grouped by day, chronological; GET DIRECTIONS → facility address; changed time/field since last check; phone on Pacific time still shows 2:00 PM ET; unpublished schedule, team not found and completed event messages; same team in two events listed separately; "Canes Triad Gold 13U" vs "9U Canes Triad Gold" distinguished by division; 2 taps from homepage to visible team schedule; two followed teams (one in two events) each labelled with next game and event; Upcoming collapsed by default, keyboard toggle updates aria-expanded; Previous newest first with RESULTS, never REGISTER; FNL 8U and SNL 11U split finals shown as separate Gold / Silver brackets matching Playbook (fields, times, seeds), still correct after seeds resolve to teams; Pink Ribbon multi-round brackets and the single 12U final unchanged.

## Known, unchanged items

- `tools/verify-templates.py` reports `header.php`, `archive-tournament.php`, `single-tournament.php` differ from a fresh build. Those files are byte-identical to 1.0.18; the drift pre-dates this update.
- `tests/browser/gameday-views.cjs` fails with "Selected view not visible: today" on both 1.0.18 and 1.0.19 (Game Day maps `today` to `schedule` since 1.0.17; the test was not updated then).
- Registration badges (OPEN/LIMITED) and the Game Day bottom-nav labels are 13 px (bold, uppercase / space-limited at 320 px).
- Parent theme: post type `swat_score_submission` is 21 characters; WordPress rejects names over 20, so it is never registered (notice in debug log). Not changed here (parent file).

## Not verifiable here

- Live plugins that can alter output: Real-Time Find and Replace, Code Snippets, WPCode, SWAT TourneyOS, Game Day Command Core. Check their rules/snippets for homepage or Game Day text after installing.
- `/swat-gameday/v4/public` (SWAT Playbook Game Day plugin) is not in the theme; locally the theme fell back to the event directory exactly as its code does when that endpoint is empty.
- SQLite cannot run the parent's MySQL `GROUP_CONCAT … SEPARATOR` directory query; a local-only shim produced the same rows. The theme query itself is unchanged and runs on MySQL.
- Page caching on Bluehost: the homepage team index is rendered into the page; purge cache after installing.
