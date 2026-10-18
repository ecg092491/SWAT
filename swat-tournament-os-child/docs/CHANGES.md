# 1.0.33: Game Day sponsor banners (2026-10-16)

Sponsor banners on Game Day, managed without code at SWAT Operations -> Sponsors.

- New inc/sponsors.php: admin screen (add/edit/pause/delete, Media Library logo, placements, field naming rights, event targeting, run dates), stats (views, clicks, CTR for this and last month), click redirect, view endpoint POST /wp-json/swat-child/v1/sponsor-view, Game Day assets. Capability manage_options (filter swat_child_sponsor_capability).
- Feeds the existing 1.0.17 hooks at priority 5: swat_child_gameday_sponsor_slots (banners) and swat_child_gameday_field_sponsors (game-card field notes). Code filters at the default priority still win.
- Placements: header (existing), bracket (existing), plus new schedule, scores and standings strips at the top of those views in template-parts/gameday/app.php. All sit outside the containers the Game Day script re-renders, so they survive data refreshes.
- Picking: a sponsor targeted at the current event replaces every-event sponsors in that placement; sponsors sharing a placement rotate per page view. Paused, not-yet-started and ended sponsors never render. Start and end dates are inclusive, in the site time zone.
- Strip (inc/gameday-hub.php): optional logo (white tile, visible in dark mode), tagline and tracking id. Without them the markup is byte-for-byte the 1.0.32 strip. Tracked banners link to /?swat_sponsor_go=<id>&slot=<slot>; the redirect target is always the saved sponsor link (no open redirect) with utm_* tags added unless the link already has utm_source.
- Counting: one option per sponsor/month/placement/kind (swat_spc_<id>_<YYYYMM>_<slot>_<v|c>), not autoloaded, incremented with a single INSERT ... ON DUPLICATE KEY UPDATE so concurrent requests do not lose counts. assets/js/swat-sponsors.js reports a view when at least half the banner is on screen for one second, once per sponsor/placement per browser visit (sessionStorage). Bots and link previews are ignored.
- New assets/css/sponsors.css loads on Game Day after postseason.css, only when a sponsor exists. Whole banner is the tap target (at least 44px), visible keyboard focus.
- Tests: tests/sponsors-test.php (94 checks: URL safety, sanitising, lifecycle, targeting and rotation, legacy markup unchanged, code-filter priority, field rights, UTM, counter keys, view validation, bots, template wiring) added to tests/run-all.sh. Full suite passes on PHP 8.3.
- Executed in WordPress 6.8.3 (SQLite database driver) with a stub of the parent's Game Day functions: 53 end-to-end checks covering the SWAT Operations menu, add/edit/delete, nonce and capability rejection, the rendered app.php placements, asset enqueueing on and off Game Day, the REST view endpoint (form and JSON bodies), click redirects, bot filtering, stats in the list, counter cleanup on delete, and 8 parallel processes x 25 increments = 200 with no lost counts. No PHP notices from the theme.
- Chromium (real app.php markup and Game Day stylesheets) at 320, 390 and 1024px, light and dark: 290 checks for 44px tap targets, no horizontal scroll, 16px gutters, text contrast at least 4.5:1, no overlapping text, logo on a white tile, whole-banner tap target, keyboard focus ring, and view-reporting timing (not before 1s, not while hidden or off-screen, not again after refresh in the same visit).
- Not verified: the real parent theme and live data, MySQL itself (the counter SQL is standard MySQL/MariaDB but was executed through the SQLite driver), real devices, Media Library behaviour on the live host, and caching/CDN layers.

## 1.0.32

- One validated public Playbook snapshot for event pages, records, team search and Game Day v1/v4/v6 feeds.
- Preserve stable IDs and bracket metadata; replace corrected/removed games within verified event windows.
- Keep CSV fallback, invalidate snapshots after uploads, and retain last-good data on failures.
- Report event-specific Unix sync times and stale/failure signals.
- Prevent stale availability overrides from reopening official cancellations/sold-out events.
- Discover historical sources from public Playbook catalog and team event links; preserve manual rainouts.
- Restore historical games API access beyond parent directory’s 12-event limit.
- Include 1.0.31 waitlist changes.
- Passed 132 regression checks in WordPress; compared all 563 bundled games with Playbook.

# 1.0.31: Tournament directory cleanup and waitlist (2026-10-16)

/tournaments/ cards are easier to scan, and sold-out or closed events offer a waitlist instead of a dead "Registration closed" box.

- Titles: the card heading drops the date and age groups the card already shows in its date tile and division line, and all-caps names are title-cased (acronyms MLB, UDC, SWAT, USSSA, NC, USA kept). "FRIDAY NIGHT LIGHTS 10/16 - 9U & 11U" -> "Friday Night Lights"; "UDC SPORTS/RETURF SOLUTIONS PUMPKIN SMASH" -> "UDC Sports/Returf Solutions Pumpkin Smash". Names that would repeat after cleanup keep their age groups: the three Friday Night Lights cards read "Friday Night Lights · 9U & 11U", "· 9U & 10U" and "· 8U & 11U" (swat_child_directory_titles()). The full Playbook name stays in the heading's title attribute and in button labels for screen readers. New swat_child_clean_event_title() in inc/waitlist.php. Event pages and the homepage are unchanged (the homepage already splits titles with swat_child_home_split_title()).
- Status line: "UPCOMING" removed (every card above Past Results is upcoming); "Schedule posted" and "This weekend" are now pills.
- Waitlist: Sold Out and Closed events show JOIN WAITLIST wherever swat_child_register_action() is used (directory cards and the event page). Cancelled still shows EVENT CANCELLED (no longer a dashed box). Link order: the event's own waitlist link, then a default waitlist link, then /contact/?subject=waitlist&event=<name>. Both links are set at SWAT Operations -> Registration Status and stored in a separate option, swat_child_waitlist, so registration records are unchanged. Only absolute http(s) URLs are saved.
- Tests: tests/waitlist-test.php (38 checks: every title from the live directory, repeated-title age groups, edge cases, link precedence, unsafe URLs, per-status actions). Rendered the real archive-tournament.php with stubbed parent functions in Chromium at 320 and 390px: no PHP warnings, no JS errors, no horizontal scroll.
- Verified against the owner's 2026-10-16 directory screenshot: all 12 upcoming events keep their status (Wood Bat World Series Open + REGISTER; the three "Registration closed" events get JOIN WAITLIST). The theme only shows Cancelled when Playbook reports CANCELLED or an admin override says so.
- Not verified: live Playbook / swatourneys.com (blocked from the build environment), parent theme CSS on the real directory, the Contact page's handling of the subject/event query (it may ignore them; set a default waitlist link such as a Google Form to collect requests properly).

# 1.0.30: Bracket & Standings redesign (2026-10-16)

Game Day's Bracket & Standings view (?view=standings) restyled to match the approved phone mockup. Data, ranking rules and endpoints unchanged.

- Header: "Bracket & Standings" eyebrow + event name (updates to the loaded event).
- Controls: gold "Choose your division" select beside an outlined "Download Bracket" button (download dialog unchanged).
- Tabs: Bracket | Standings with a gold underline. Opens on Bracket once any bracket game in the division is live or final, otherwise Standings; a user's tab choice or a #bracket link is kept.
- Bracket: one card per tier (Gold, Silver, Bronze...): Champion, Runner-Up, final score, field and time. Undecided title games show the matchup with Upcoming/Live/Delayed. Other rounds sit under "<Tier> Bracket — Earlier rounds" as collapsible rows ("2 games · completed", +/−); completed rounds start closed, in-progress rounds open; open/closed state survives refreshes.
- Standings: compact # / Team / W-L / RD table (W-L-T when ties exist), leader in gold, followed teams highlighted, coin-flip note kept. Grouped by pool when the feed provides pool/pool_name, otherwise one table per division. Seeding rules moved into a collapsible "How SWAT seeds pool play".
- Removed from the standings table: the follow star and the RA/RS columns (RS/RA show under the team name on hover/focus). Follow is still available on game cards and team pages.
- New assets/css/postseason.css, scoped to #swat-postseason and loaded after every other Game Day sheet (inc/gameday-hub.php).
- Verified: Chromium at 320/390/1024px, dark and light, with finished and in-progress sample brackets: no JS errors, no horizontal scroll. Full PHP suite passes. Not verified: the parent theme CSS and live data on WordPress.

# 1.0.29: Team profiles linked across the site (2026-10-09)

Team profiles (/teams/, /teams/{team}/, since 1.0.19) were reachable only from Game Day's team view. They are now linked everywhere teams appear. New: inc/team-links.php, tests/team-links-test.php.

- Event pages (/tournaments/{event}/): every real team name in schedule cards, results and pool standings links to its profile. Bracket placeholders ("#1 Seed", "Winner of Game 3") and names with no profile stay plain text.
- Shared names (1.0.22: Camel City 9U/10U/12U and 13 others in the 2026 Fall export) link straight to the right team only when exactly one team with that name has played in that game's age group; otherwise to the name's chooser page. A team playing up is never linked to another team (e.g. Carolina Chaos 7U playing 8U links to /teams/carolina-chaos-7u/).
- Main menu: "Teams" after "Tournaments" (header.php, and tools/build-templates.py so a rebuild keeps it).
- Homepage: quiet "Browse all teams" text link under the hero team search. Search field position unchanged.
- Team pages: opponent names link to the opponent's profile when that profile exists (opponents known only from a Playbook team page stay plain).
- Search engines: team pages added to the core WordPress sitemap as /wp-sitemap-teams-1.xml (listed in /wp-sitemap.xml). Team pages print a meta description (e.g. "Canes Triad 7U Black: 15-2-0 in 2026 Fall at SWAT Tournaments...") and a canonical URL (?season= views point to the profile URL). Skipped automatically if Yoast, Rank Math, AIOSEO or SEOPress is active.
- Link index cached 15 minutes (transient swat_child_team_link_index_v1); cleared when a Playbook season export is uploaded.
- Styling (child.css): team links inherit the card colour with an underline; inline with vertical padding for a larger tap area, so card layout, line height and any parent ellipsis are unchanged.
- Tests: tests/team-links-test.php, 34 checks with the real export: single-name, playing-up, placeholders, unknown names, every shared-name game side links to its own team or the chooser (never another team), ambiguous age group, opponent keys, escaping, sitemap entries, descriptions, template wiring. Full suite passes (PHP 8.3).
- Executed the real single-tournament.php and team-profile.php with stubbed parent functions and parent-shaped August Heat rows: no PHP errors or warnings; 292 team links (128 cards, 128 results, 36 standings); placeholders unlinked; all 36 distinct targets render a real profile; Canes page shows 15-2-0 with 17 linked opponents. Homepage rendered in Chromium at 320-1440: no horizontal scroll, no JS errors.
- Not verified: parent theme CSS on event pages (link colour/underline on the real cards), the core sitemap endpoint on live WordPress, live plugins. After installing: purge caches, open an event page and tap a few team names, open /wp-sitemap.xml and check the teams sitemap, then submit the sitemap in Google Search Console.

# 1.0.28: Preserve rematches and reject ambiguous doubleheader metadata

- Date/teams-only fallback now recovers metadata only from finished WordPress rows. Scheduled/live rows require stable-ID, unique time, or unique score evidence before replacement. A separate same-day rematch is retained when the CSV only represents the earlier final.
- Fallback matching requires uniqueness in both directions, checked against the original display/export inputs. Processing order cannot make an equal-score doubleheader appear unique or transfer a championship label to the first game.
- Seven new pipeline regression checks: scheduled and live rematches, ambiguous equal-score doubleheaders in both export orders, correct unique-time championship transfer, unique-score metadata recovery, and unique finished-pair recovery. Updated the prior weak live-match test to require preservation when identity cannot be established.
- Full PHP 8.3 local suite and syntax lint pass; 30 pipeline checks and 34 export checks pass. No parent theme/live WordPress/browser integration verification or deployment.
- Existing CSV authority and omission/deletion policies retained. Game Day synchronization and UI unchanged.

# 1.0.27: Event pages can no longer list a finished game twice (2026-10-09)

- Fixed (1.0.26 regression): swat_child_export_reconcile_event() matched WordPress rows to export finals only on date + time + teams, dropping 1.0.25's date + teams + score fallback, and removed unmatched finished rows only when they carried an event_name. Parent event rows carry no playbook_id (1.0.20 note) and the child adds event_name to bracket fixtures itself, so an edited time or a "9:00" vs "09:00" time format produced two copies of the game in schedule cards and in the standings input. Reproduced with the real August Heat rows: 64 games became 74.
- Fix: rows passed to the reconciler are stamped with the event's name (both lists come from one event), so the CSV-authority rule replaces every finished WordPress row of the event. Matching (only to keep post id, round and bracket metadata): Playbook id, then date + normalised time + teams, then date + teams + score, then date + teams when the export has exactly one such game that day (stale live copy). Ambiguous candidates are never merged. Times normalised by new swat_child_export_norm_time() ("9:00", "09:00", "9:00 AM", "18:00:00").
- Fixed: stored seasons are flattened oldest upload first, so if data saved by an earlier version still holds one Playbook id in two seasons, the most recent upload's copy wins (1.0.26 kept whichever season was stored last).
- Fixed: an upload now updates the file name and upload time only for the seasons it contains (1.0.26 stamped every stored season).
- Tests: tests/results-pipeline-test.php 23 checks (was 11). The 12 new checks use parent-shaped rows (no playbook_id, no event_name, post ids, unpadded / AM-PM / edited times, stale live row, bracket fixture with round, unrelated upcoming game, duplicate post row in the standings input) plus season ordering and metadata. All 12 fail on 1.0.26 and pass on 1.0.27. Full suite passes on PHP 8.3; no PHP 8.1+ syntax added.
- Unchanged: team-profile pipeline (still 15-2-0, 17 games, 214/77 with the conflicting cached Playbook page), homepage, registration, Game Day.
- Scope: Game Day still reads WordPress data only and can show a pre-correction score where the CSV corrected one (since 1.0.22). An uploaded CSV still takes priority over a newer CSV shipped in data/ (since 1.0.22).
- Not verified: the parent theme's real row shape, live WordPress, plugins. After installing, purge caches and compare one finished event page (game count and standings) and one team page against Playbook.

# 1.0.26: Consistent results authority and cross-season corrections

- Team profiles: the complete production path respects CSV-authoritative events when applying a cached/live Playbook HTML team page. A conflicting HTML score no longer adds a duplicate final. HTML still supplements events absent from the export.
- Event pages: replace CSV-event finals with corrected export finals in schedule cards, results and standings inputs. Stable-ID or unique legacy matches retain post IDs and round metadata. Unrelated upcoming/live games remain. A uniquely matched stale live row becomes the export final.
- Exports: combine shipped/uploaded game IDs globally before grouping by season. Uploads flatten prior season buckets, reconcile IDs, then regroup by corrected date; moving a game across June/July does not leave a duplicate in the old season.
- Tests: added tests/results-pipeline-test.php to the full suite. Eleven checks cover the actual swat_child_team_profile() call with a conflicting cached HTML score, corrected event scores and metadata, stale live rows, cross-season migration, partial preservation, idempotence and duplicate finals.
- Homepage layout and copy, Playbook synchronization, registration actions and parent theme unchanged.
- Existing policies retained: export events are treated as complete for finals; omitted games already exported are retained rather than deleted. No live deployment performed.

# 1.0.25: Fix double-counted results from 1.0.24; hero copy (2026-10-09)

- Fixed (1.0.24 regression): team records could double count finished games. 1.0.24 only replaced a WordPress final with the export's copy when they matched exactly (Playbook id, or event/date/time/division/teams); any difference kept both. With the real 2026 Fall export, Canes Triad 7U Black (Playbook 15-2-0, 17 games) showed 16-2-0 when one game's time had changed after sync, 30-4-0 when WordPress labelled the division "7U" instead of "7U Open", and 16-2-0 for the post row + bracket row duplicate described in 1.0.20.
- Restored the 1.0.22 rule: for an event in the export, finished games come from the export (WordPress finals of that event are replaced). Also new: a WordPress row of any status whose Playbook id is an export final is replaced (stale live copy). Upcoming/live games and events not in the export are unchanged.
- Kept the 1.0.24 goal (partial CSV uploads never erase finals) at the right level: new swat_child_export_combine() combines uploads with the previous upload and the shipped file by Playbook game id (every export row has one). Newer rows win; omitted rows stay. Used by swat_child_export_games() and the upload handler.
- Hero copy: H1 "Youth baseball tournaments in the NC Triad" (restores the search term "tournaments"); subheading "Pool play, brackets and Game Day for every SWAT event." (1.0.24's "Find your team's schedule..." repeated the search card title directly below it); text-wrap:pretty on the subheading. 1.0.24 layout (search above the proof row on phones) unchanged.
- Unchanged from 1.0.24: event-page actions (no REGISTER/badge once games are displayed); public "Sold Out" reads "Registration closed".
- Tests: tests/season-export-test.php 34 checks (was 29). The 1.0.24 partial/idempotent/distinct-id checks now target swat_child_export_combine; new regression checks run the four mismatch cases above (rescheduled time, division label, duplicate row, rows with ids) through the team profile and require 15-2-0 / 17 games. tests/run-all.sh passes.
- Hero verified in Chromium with the real templates/CSS/JS and stubbed WordPress data (live, pending, no events) at 320/390/768/1280/1440: no horizontal scroll, no hero target under 44 px, no JS errors; search returns results, REGISTER opens Upcoming, status pill jumps to #this-weekend. Search field top: 390x844 460 px (1.0.24: 406, 1.0.23: 550); 320x640 483 px; 1280x800 281 px.
- Not verified: live WordPress, parent theme CSS, live plugins. Purge page cache after installing. Check one team profile against Playbook after install.

# 1.0.24: Parent-first hero and safer results reconciliation

- Shorter hero heading and description. Team search precedes the proof row on phones; desktop retains the two-column hero.
- Event pages with displayed games suppress registration buttons and status badges, leaving View Schedule and Open Game Day. Admin Sold Out state remains available; public unavailable notes read Registration closed when no schedule exists. Homepage/archive retain their schedule-aware action rules.
- CSV finals reconcile by stable Playbook game ID, with a unique event/date/time/division/team fallback for legacy rows. Partial imports preserve omitted finals. Distinct IDs are never merged by appearance. Uploads reconcile with previous/shipped results rather than replacing the season wholesale.
- Added regression checks for partial imports, corrected scores, repeated imports, live-to-final transitions, distinct IDs and ambiguous legacy rows.
- No synchronization logic or parent theme files changed. This is an installable child-theme update, not a live deployment.
- Validation details: see REVIEW-1.0.24.md.

# 1.0.23: Homepage hero redesign (2026-10-09)

- The team finder ("Find my team's schedule") now lives inside the hero instead of a separate band below it: right column at 1024 px and up, directly under the headline on phones and tablets. The hero's FIND MY TEAM'S SCHEDULE button (a jump link to that card) is removed as redundant.
- Search field position in the first screen (local harness, same data): 1280x800 743 -> 281 px from top; 390x844 703 -> 550; 320x640 748 -> 571 (now fully visible); 768x1024 723 -> 686.
- Status pill replaces the static kicker when a weekend exists: "Happening now · Oct 9-11" (green pulse) when an event is live with a published schedule, otherwise "Next up · Oct 9-11"; it links to the This Weekend section (new id="this-weekend"). Uses the same weekend models as the cards, so it never claims more than they do. With no events the old "SWAT Tournaments · Clemmons, NC" kicker shows.
- Slogan "Tournament baseball. Run better." removed. H1 is now descriptive: "Youth travel baseball tournaments in the NC Triad" (last phrase in gold); lede "Schedules, scores, fields and registration for teams, players and families."; "Clemmons, NC / Local & independent / No sanction fees" moved into a proof row.
- CTAs: REGISTER FOR A TOURNAMENT (gold, still opens Upcoming via data-swh-open) + OPEN GAME DAY.
- Decorative baseball-diamond line art (inline SVG, aria-hidden, no image request); gold base line under the hero. Live-dot pulse disabled under prefers-reduced-motion.
- Changed: front-page.php (hero markup), assets/css/home.css (hero block rewritten; .swh-band--finder removed), style.css + functions.php version. home.js, team-finder.php/.js/.css unchanged.
- Verified locally with the real templates/CSS/JS and stubbed WordPress data (live, schedule pending, no events) at 320/390/768/1280/1440: no horizontal scroll, no hero target under 44 px, no JS errors; search returns results, REGISTER opens Upcoming (aria-expanded=true), status pill jumps to #this-weekend. tests/run-all.sh passes. Not yet checked on swatourneys.com with the parent theme CSS and live plugins; purge the page cache after installing.

# 1.0.22: Playbook season export as the record of finished games (2026-10-08)

- The Playbook "season games" CSV export (563 games, 24 events, Aug 7 – Oct 4, 2026 Fall) ships in data/playbook-season-2026-fall.csv. For every event in it, finished games come from the export: team records and results, and event pages (missing games are added). WordPress still supplies upcoming and live games and events not in the export. New inc/season-export.php.
- August Heat Championship event page: 64 games (was 36; its 28 bracket games never synced). Canes Triad 7U Black: 15-2-0, 17 games, 214 RS, 77 RA, with real times, fields and Pool/Bracket labels for the August Heat bracket games.
- Event pages also resolve for export-only events that never synced (e.g. Friday Night Lights 8/7).
- Settings → SWAT Team Profiles: "Playbook season results" upload. A new export replaces that season (upload a full-season export after each weekend). Non-export CSVs are rejected with the missing columns listed.
- Teams that share a name are now separate profiles. The export shows 10 names used by different Playbook teams (e.g. Camel City 9U, 10U, 12U; Flying Pigs 7U, 8U); their games were being combined into one record. Those get /teams/{name}-{age}/ ("Camel City (9U)"), /teams/{name}/ lists the choices, and opponents show the age group. A team that plays up keeps one profile (one Playbook id).
- The Playbook team page reader (1.0.21) now only adds missing games and never removes one.
- Tests: tests/season-export-test.php (22 checks with the real export). Local site: Canes 15-2-0 = Playbook; August Heat 64 games; FNL 8/7 page 7 games; Camel City chooser + three profiles (18 games total, none lost); upload via wp-admin imports, replaces the season and rejects other files; no horizontal scroll at 320/390/1440 on team and event pages; full suite, 44 scenario checks, 67-game Playbook comparison and link audit pass.

# 1.0.21: Team records from the Playbook team page (2026-10-08)

- Team profiles now take finished games and the season record from the team's Playbook profile page (play.swatourneys.com/team/details/{season}/{team}), read server-side. Live problem: August Heat's two bracket games were never synced, so /teams/canes-triad-7u-black/ showed 13-2-0 while Playbook shows 15-2-0. New inc/team-playbook.php.
- Only the Playbook GAMES and EVENTS tables are read; roster, birth dates and coaches are ignored.
- WordPress games that match a Playbook game (same event, teams, score) keep their time, field and pool/bracket label; Playbook-only games are added in Playbook order; finished WordPress games Playbook does not list are dropped for that season. Upcoming games still come from WordPress.
- Team links: Settings → SWAT Team Profiles (one Playbook team link per line; the name is read from the page). Canes Triad 7U Black is pre-filled. Opponent links on every Playbook page read are remembered, so those teams work without setup.
- Cache: 30 minutes per team and season; a failed fetch retries after 5 minutes; the last good copy is used while Playbook is unreachable; with no copy the profile uses WordPress data as before. Results note links to the Playbook profile when it was used.
- Tests: tests/team-playbook-test.php (21 checks; fixture tests/fixtures/playbook-team-canes-7u-black.html built from the live page's game table). Local site with the live data state and a mocked Playbook response: 15-2-0, 17 games, 214 RS, 77 RA; one fetch then cached; Playbook down with a saved copy 15-2-0; down with no copy 13-2-0 (WordPress fallback).

# 1.0.20: Older event pages, duplicate finals, complete team records (2026-10-08)

- Older event pages work again. The parent's event directory holds only the 12 newest synced events, so /tournaments/august-heat-championship/, /friday-night-lights-9-4-7u-12u/ and /strike-out-cancer-.../ returned "Event not found" (and team-profile links to them were dead). single-tournament.php now falls back to swat_child_event_by_slug() (inc/event-display.php), which looks up any synced event (15-minute cache). The /tournaments/ list and homepage are unchanged.
- Finished events no longer list each bracket game twice. swat_os_event_games() rows have no playbook_id while the bracket rows for the same posts do, so the old de-duplication missed them (live: FNL 10/2 showed 20 games for 18; SNL 9/26 18 for 15). Rows now match on post id or Playbook id, bracket rows first.
- Team records include finished bracket games that are in the Playbook bracket cache but were never imported as game posts (live: Canes Triad 7U Black showed 13-2-0 vs Playbook 15-2-0; the two August Heat bracket wins were missing). Games already imported (by Playbook id, or same date, time and teams) are not counted twice; placeholder slots are ignored. inc/team-profile.php.
- Team search placeholder now reads "Start typing your team name" (no example team) on the homepage and Game Day. inc/team-finder.php.
- Local verification: fixtures/old-events.php reproduces the live state (17 events, Aug Heat bracket games only in cache, FNL 10/2 final imported with Playbook meta): profile 15-2-0, 214 RS, 77 RA; FNL 10/2 shows 3 games (old logic 4); all five event links on the profile return 200; unknown event still 404. Full suite, 44 scenario checks, Playbook comparison (67 games) and link audit pass.

# 1.0.19: Parent-first homepage + team schedule (2026-10-08)

- Homepage rebuilt around "When and where does my child play?": hero FIND MY TEAM'S SCHEDULE, weekend-wide team search (division + event + date per result), This Weekend cards, Upcoming and Previous tournaments (both collapsed by default, accessible chevron toggles), Game Day, Know before you arrive, About. See VERIFICATION-1.0.19.md.
- Button/badge logic is data-driven and unit tested (inc/home-data.php, tests/home-actions-test.php): VIEW SCHEDULE only when games exist; a published schedule removes registration actions/badges; REGISTER only for future events accepting entries; no OPEN badge once an event has started; previous events offer RESULTS. The hard-coded Oct 2-4 "Closed" override is removed.
- Game Day team view (?event=&team=): next game card (day, date, AM/PM time, opponent or honest TBD, facility + field, GET DIRECTIONS), delayed/cancelled/changed-since-last-check notes, possible bracket games that depend on advancement, full schedule grouped by day. Exact team matching (no substring matches). Game times computed in America/New_York and labelled ET on phones in other zones.
- Followed-teams strip shows each team's next game (with opponent, facility, field, event) across all events this weekend, with Schedule and Directions links.
- Split finals named Gold / Silver (/ Bronze): when every bracket game in a division schedule is a standalone title game, Game Day labels them by Playbook game number (FNL 10/9 8U: Gold SW 2, Silver SW 7; SNL 10/10 11U: Gold SW 4, Silver SW 3). Based on bracket links, not seed names, so it survives seeding. Per-game override filter: swat_child_bracket_labels (inc/gameday-hub.php). The event page (/tournaments/{event}/) uses the same rule server-side (inc/bracket-schedule.php): GOLD CHAMPIONSHIP / SILVER CHAMPIONSHIP badges, Silver styled silver (tests/bracket-tier-test.php).
- /tournaments/: VIEW SCHEDULE now appears only when synced games or bracket fixtures exist (a Sold Out event no longer implies a schedule); events with a posted schedule show SCHEDULE POSTED instead of a registration badge; Sold Out / Registration Closed show as text. Same unit-tested rule as the homepage. archive-tournament.php is hand-maintained from 1.0.19.
- Team profiles: /teams/ (searchable directory) and /teams/{team}/ with upcoming games (time, field, facility, link to the Game Day team view), season record, win %, runs scored/allowed/difference with per-game averages, per-event records and every final score, with a season switcher (Fall = Jul–Dec, Spring = Jan–Jun). Built only from synced games; no rosters, birth dates or coaches. /teams/?team={name} redirects to the profile; the Game Day team view links to it. Verified against the Playbook profile for Canes Triad 7U Black (15-2-0, 214 RS, 77 RA) in tests/team-profile-test.php. New: inc/team-profile.php, team-profile.php, assets/css/team-profile.css.
- Team search also on Game Day's schedule view. Footer text 16px with 44px link targets (site-wide).
- New: inc/home-data.php, inc/team-finder.php, assets/css/home.css, assets/css/team-finder.css, assets/css/team-schedule.css, assets/js/home.js, assets/js/team-finder.js, tests/home-actions-test.php, docs/VERIFICATION-1.0.19.md.
- Changed: front-page.php, archive-tournament.php and single-tournament.php (hand-maintained; no longer generated by tools/build-templates.py), template-parts/gameday/app.php, assets/js/swat-gameday.js, inc/gameday-hub.php, assets/css/child.css (footer), functions.php, style.css, tests/run-all.sh, tools/build-templates.py, tools/verify-templates.py.
- Removed: assets/css/home-refined.css (replaced by home.css). The homepage no longer loads front-page-v2.css or front-page-weekend.js (their rules/markup are not used there).

# 1.0.17: Game Day Hub (2026-10-04)

- Game Day redesigned to the approved GameDay Hub artifact in SWAT black and gold. See VERIFICATION-1.0.17.md.
- New: inc/gameday-hub.php, assets/css/swat-gameday-hub.css, assets/css/gameday-site-chrome.css (generated), tools/build-gameday-chrome.py, template-parts/gameday/home-hero.php.
- Changed: template-parts/gameday/app.php, assets/js/swat-gameday.js, assets/js/swat-my-weekend.js (score wording), assets/css/child.css (Game Day block moved to the hub), functions.php, style.css, tests/run-all.sh, tests/browser/mobile-menu.cjs, tests/browser/gameday-views.cjs.

# 1.0.8 — verification, light panels and subdirectory routing (2026-10-01)

- Ran PHP 8.3.6 lint on every child PHP file and the complete test runner with PARENT pointing to the 2026-10-01 staging parent export. All passed. All four generated templates reproduce byte-for-byte.
- Added positive, component-scoped light styling for pool standings, bracket boards/matches, championships, More tiles, Fields, Weather and pitching text. Corrected muted card text and followed-team highlights for white backgrounds. Added wrapping for long team names/metadata and narrow-screen standings sizing. Hero, Your Teams and form-panel styles were not edited. These CSS changes are source-checked, not visually verified.
- Fixed the child router's staging-path mismatch: it now removes the home URL's path before matching tournament routes and rejects requests outside that base. Root-site behavior remains covered by tests.
- Added 8 path cases and 2 menu cases to tests/run-all.sh. Added all-four-template regeneration verification to the runner when PARENT is supplied.
- Added tests/browser/gameday-views.cjs for real-site 390px/1440px screenshots of all views. It fails on Coming Soon, sign-in, unexpected redirects, update failures, missing views, JS errors and page overflow. It does not certify text contrast or absence of overlapping elements; those still require the contrast audit and visual review.
- Staging-only saved-menu correction: Main Nav item 6744 previously linked to https://play.swatourneys.com/. The legacy Beaver Builder Global Header uses Main Nav. Updated item to https://swatourneys.com/staging/2948/tournaments/ and verified the stored meta value. No parent files or production site were changed. Saved menu data is outside this ZIP; the existing child menu filter continues to enforce the local tournament URL.
- Authenticated staging /swat/v1/games?event=autumn-harvest-series returned 31 canonical games and 23 bracket fixtures: 22 Sunday October 4 fixtures and 1 Saturday October 3 fixture. The shipped child JS rendered all 22 Sunday fixtures in filtered Schedule and all 23 bracket matches in a local JSDOM harness. No bracket data/display code was changed.
- Blocked verification: public staging HTML is Coming Soon. Both Chromium 154 and Chrome for Testing 134 terminate before rendering in this execution environment. No screenshots, computed contrast, 390px/1440px layout pass or real staging click-through can be claimed. See VERIFICATION-1.0.8.md.

# 1.0.6 (Game Day UI)
- Game Day redesign to match the tournament event page: site header on Game Day (child `header.php`, generated by `tools/build-templates.py` step 13), light page surface, segmented section tabs at the top (the old bottom bar, moved by `swat-gameday.js`), pill filters, event-style game cards. CSS is the "Game Day redesign" block at the end of `assets/css/child.css`.
- Game Day script URL now cache-busts by file mtime (inc/bracket-schedule.php); the fixed version string let browsers keep the old script, so the tabs stayed at the bottom.
- Removed "WordPress checked" and "WORDPRESS REFRESHED" wording from the Game Day status line (now "Updated … · Playbook synced …" and "ONLINE").
- Refresh now lives in the status bar (a button is added by `swat-gameday.js` because the minimal app header is no longer rendered).
- Earlier "bottom bar hidden on Today / header padding" rules replaced by the above.
- `inc/menu-links.php`: top-level "Tournaments" item in any WordPress nav menu now links to /tournaments/. The parent's hardcoded header already did.
- Bottom nav hidden on the Today view only (Tournament Hub tiles duplicate it there); kept on sub-views, where the hub tiles are hidden.
- Desktop header padding now matches the 840px content column. CSS only, in assets/css/child.css.

# 1.0.6

Bracket schedule display (see SCHEDULE-1.0.6.md). Includes the 1.0.5 contrast fix. Guarded missing start/end dates in `swat_child_bracket_event_dates()`.

# 1.0.5 (draft)

Scoped Game Day text contrast overrides; contrast audit rejects non-Game Day pages and defaults to 4.5:1. See CONTRAST-1.0.5.md.

# Changes — 1.0.4

- New diagnostic: `tests/browser/contrast-audit.py` lists unreadable text on real pages and names the stylesheet rule (or the ancestor it is inherited from) that sets the colour. Added after phone screenshots of Game Day showed some headings, labels and times rendered as near-black (#111111) text on the dark background.
- No change to theme behaviour from 1.0.3.

# Changes — 1.0.3 (IMPORTANT FIX)

- **Fix: parent assets 404 under the child theme.** The parent builds all asset URLs from `get_stylesheet_directory_uri()` (31 uses via `SWAT_OS_URI`), which points at the child folder when the child is active, so CSS/JS/logo/PWA files all failed to load (reported from a phone screenshot: unstyled page). The child now filters `stylesheet_directory_uri` to the parent folder (see `functions.php`) and uses a new `SWAT_CHILD_URI` constant for its own assets. 1.0.0–1.0.2 were affected; do not use them.
- Fix: the 10-minute cron job is no longer scheduled during a Customizer preview.
- New: `tests/uri-filter-test.php` and `tools/asset-url-audit.py`.

# Changes — 1.0.2

- Fix: preload Barlow 700 (the face most headings use) instead of 800, which the homepage does not use (verified in Chromium: home loads Barlow 700 only; event pages load 700 and 800).
- Docs: recommend subsetting Inter (350 KB) to Latin; exact command in `assets/fonts/README.txt`.
- No other code changes; 1.0.1 fonts, checker and lint-script changes are carried forward unchanged.

# Changes — 1.0.1

- Bundle Barlow 700/800 and variable Inter WOFF2 plus licenses.
- Preserve staging subdirectory URLs; reject redirects outside the requested site.
- Verify registered loaded font faces and successful font HTTP responses.
- Detect HTTP errors and uncaught JavaScript errors.
- Fail immediately and clearly when PHP is absent; support unset PARENT under set -u.
- Version bumped to 1.0.1.

# Change list — 1.0.0

**Added**
- `inc/registration-status.php`: status/deadline model, precedence resolver (pure, unit-testable), badge/deadline/action helpers, child event-details renderer, 10-minute SOLD OUT cache warm-up job.
- `inc/registration-admin.php`: *SWAT Operations → Registration Status* (capability check, nonce, whitelisted keys, sanitised values).
- `inc/fonts.php`: conditional local font loading; removes parent `swat-fonts` (Google Fonts) only when all local files exist; preloads two files; admin notice when missing.
- `inc/templates.php`: priority-0 route override for `/tournaments/` and `/tournaments/{slug}/`; child stylesheet loader.
- `assets/css/child.css`, generated `assets/css/child-fonts.css`.

**Changed (child copies of parent templates)**
- `front-page.php`: removed weekend game-time data build, markup, day tabs, game cards (and the game queries behind them); hero CTAs → REGISTER + OPEN GAME DAY (+ text link to tournaments); status badge + deadline on this-weekend and upcoming cards; REGISTER hidden unless Open/Limited; schedule-release note kept (neutral wording, only when a schedule is pending).
- `archive-tournament.php`: status badge, deadline, gated register action (parent SOLD OUT detection reused).
- `single-tournament.php`: status badge/deadline in hero, gated REGISTER, child event-details block; fixed missing space between two sentences in the hero summary.

**Unchanged:** every parent file, Game Day, REST routes, Playbook sync, pitching logic, registration URLs, data.

## 1.0.6 audit notes (against parent staging export 2026-10-01)
- Verified: parent serves `bracket_fixtures` on /swat/v1/platform and /swat/v1/games; templates rebuild identically for archive and single; fonts and asset-URL audits pass.
- `updateAge()` falls back to the parent's `last_sync` because the parent never sends `last_success_ts`.
- Not provided by the parent (JS branches inert): `sync_status`, `schedule_pending`, `last_success_ts`; markup hooks `data-swat-day-tabs`, `data-swat-open-bracket`.
- Admission pricing in `front-page.php` ($13 adults; teens 13-17, seniors 60+ & military $10; kids 7-12 $8; 6 & under free; cashless) confirmed correct by the site owner. The parent's $10 copy is outdated. `tools/build-templates.py` now applies this so a rebuild no longer reverts it.
