# v1.0.8 verification — 2026-10-01

Package is prepared, not installed. Parent files and production were not edited.

## Passed

Using PHP 8.3.6 extracted locally from official Ubuntu packages:

```sh
PARENT=/absolute/path/to/swat-tournament-os-master sh tests/run-all.sh
python3 tools/build-templates.py /absolute/path/to/swat-tournament-os-master .
python3 tools/php-balance-check.py $(find . -name '*.php')
node --check assets/js/swat-gameday.js
node --check tests/browser/gameday-views.cjs
```

- `php -l` on all 14 PHP files in this build: clean. The input build's 13 PHP files also passed before changes.
- 112 registration resolver cases and 4 sanitization checks: zero failures.
- 3 asset URI assertions: all passed.
- 8 request-path cases and 2 menu cases: zero failures.
- Generated child-fonts.css matches the parent font generator; asset URL audit passed.
- Generated header.php, front-page.php, archive-tournament.php and single-tournament.php are byte-identical to a fresh rebuild from the parent staging export. No generator/template changes were needed.
- PHP balance checker passed; JS syntax checks passed.

## Sunday bracket data path

Authenticated staging API `/swat/v1/games?event=autumn-harvest-series` returned the correct event, October 3–4 date range, 31 canonical games and 23 virtual bracket fixtures. Fixture dates: 22 on October 4 and 1 on October 3. Field, time, division, seed placeholders and advancement references are present.

Local PHP template harness used the exported parent app and hero/tools templates plus the child's header. Helpers were stubbed; this is not a full WordPress runtime. Executed the actual child Game Day script in JSDOM with the authenticated API snapshot and mocked read endpoints:

- Sunday date filter renders exactly 22 schedule cards.
- Bracket renders 23 matches.
- Sunday fixtures preserve home/away placeholders in rendered output.
- Virtual schedule fixtures offer no score-report action.
- All eight view switches and the bracket panel switch change visibility correctly.
- No JSDOM script errors. JSDOM does not perform layout; none of this certifies mobile rendering or contrast.

The parent `/swat/v1/platform` endpoint selected Friday Night Lights despite the event query; the child uses `/swat/v1/games` for event-specific updates, which returned Autumn Harvest correctly. No change to the parent was made.

## Tournament menu

Staging Main Nav's saved Tournaments item 6744 linked to `https://play.swatourneys.com/`. Global Header post 10 contains a Beaver Builder advanced-menu module using `wp_menu=main-nav` and applies site-wide. That establishes the wrong saved-link source; the Coming Soon wall prevents confirming which header a visitor actually sees.

Corrected the saved staging menu via `menu item update 6744 --link=https://swatourneys.com/staging/2948/tournaments/`. Read the `_menu_item_url` meta afterward and confirmed that exact value. An initial `--url` attempt had no effect; verification caught it before the supported `--link` update was applied.

Also fixed the child's raw request-path comparison, which previously missed `/staging/2948/tournaments/`. Unit checks cover root and staging paths, query strings, event detail routes and base-path boundaries. The existing menu filter remains included. Browser navigation into the child archive is unverified until installation and authenticated testing.

## Blocked checks and next action

- Public staging HTML and the connector HTML renderer return Bluehost Coming Soon. No signed-in browser session is available here. Coming Soon was not disabled.
- Downloaded Chromium 154 from Google's official Linux package and Chrome for Testing 134 from official Google storage. Both failed before rendering (`SIGSEGV`; a direct Chromium run also reported a denied socket operation). Playwright launch attempts produced no screenshots. The original Playwright CDN download also failed as an invalid ZIP.
- Thus no 390px/1440px screenshots, rendered contrast audit, overflow pass or overlap/cutoff pass exists. CSS is a prepared correction against actual exported selectors, not a verified finished visual result.
- The current staging parent may differ from the morning export; pitching API fields are newer than that export. Theme/plugin runtime equivalence is not certified.

Install this child update on staging, retain the existing parent, then use an authenticated Playwright session in an environment that can launch Chromium:

```sh
npm install playwright
npx playwright install chromium
SWAT_STORAGE_STATE=/absolute/path/to/staging-state.json node tests/browser/gameday-views.cjs https://swatourneys.com/staging/2948/ autumn-harvest-series screenshots
python3 tests/browser/contrast-audit.py https://swatourneys.com/staging/2948/gameday/?event=autumn-harvest-series --min 4.5
```

The contrast command requires page access too; the shipped Python audit does not consume SWAT_STORAGE_STATE. Do not publish staging or disable its protection merely to get a passing audit. Capture each view with real data, including populated scores/pool standings, pitching team/details, active weather, followed teams and both forms. Check browser computed contrast and visual geometry before production release.
