## 1.0.33 — Game Day sponsor banners

New screen: SWAT Operations -> Sponsors. Add a sponsor (name, link, tagline, logo from the Media Library), choose where it shows (Top of Game Day, Schedule, Scores, Standings, Bracket), optional field naming rights ("Field 3 presented by …"), optional event targeting and run dates, and pause it any time. Nothing shows on Game Day until a sponsor is saved and active.

The list shows views, clicks and click-through rate for this month and last month. A view counts once per sponsor and placement per visitor visit, only after the banner has been at least half on screen for one second. Clicks are counted through /?swat_sponsor_go=…, which redirects to the saved link with utm_source=swatourneys. Obvious bots and link previews are not counted. Sponsors added in code with the existing swat_child_gameday_sponsor_slots filter keep working, render byte-for-byte as before, and still take priority over the admin screen.

After installing: add one sponsor, open Game Day on a phone, and tap the banner once to confirm the redirect. Purge caches if the banner does not appear. Do not full-page-cache /gameday/ (rotation and targeting happen per request).

Rollback: install 1.0.32. Saved sponsors (option swat_child_sponsors) and counters (swat_spc_* options, not autoloaded) remain in the database and render nothing.



Validated full public schedules take precedence over the CSV and imported rows for the verified event and date window. Corrected scores and removed games reconcile by Playbook game ID. CSV data remains a fallback when no verified snapshot exists. Existing manual cancellations and closures remain effective; Open/Limited cannot reopen a Playbook cancellation, sold-out event, expired deadline or ended event.

The background job refreshes current events every two minutes when WordPress cron runs and revisits historical events hourly. It also reuses successful all-division responses from the existing sync. A failed/partial/empty fetch retains saved data and exposes stale/error metadata. New CSV uploads invalidate older snapshots for affected events. Source data excludes rosters and session credentials.

Admin diagnostics: GET /wp-json/swat-child/v1/source and /source/checks; POST /source/refresh accepts a known event key. Snapshot storage is separate from imported WordPress game posts; this release does not delete those posts.

Validation: 132 regression checks executed in the WordPress PHP runtime, JavaScript syntax check, preview pages and APIs checked. All 563 bundled games across 24 events compared with Playbook. Game 7337 is scheduled on Playbook, rather than the CSV’s 0–0 final; verified snapshots exclude that false tie. Weekend snapshots contain 17 Friday, 15 Saturday Night Lights and 45 Pumpkin Smash games. The older CLI test suite could not run locally because PHP CLI was unavailable.

## Version 1.0.31

Tournament directory: shorter readable titles, cleaner status pills, and JOIN WAITLIST for sold-out/closed events (links set at SWAT Operations -> Registration Status). See docs/CHANGES.md.

## Version 1.0.30

Bracket & Standings view redesigned (division picker, Bracket/Standings tabs, champion cards, compact pool tables). See docs/CHANGES.md.

## Version 1.0.29

Team profiles linked from event pages (schedule, results, standings), the main menu, the homepage and opponents on team pages; team pages in the WordPress sitemap with descriptions. See docs/CHANGES.md.

## Version 1.0.28

Preserves scheduled/live rematches when CSV identity is uncertain and prevents ambiguous doubleheaders from transferring bracket metadata to the wrong game. See docs/REVIEW-1.0.28.md.

## Version 1.0.27

Event pages can no longer list a finished game twice when WordPress rows differ from the Playbook export (time edits, time format, missing event name). See docs/CHANGES.md.

## Version 1.0.26

Fixes duplicate finals from cached team-page scores, applies corrected CSV scores to event results/standings, and reconciles game IDs across seasons. See docs/REVIEW-1.0.26.md for validation and remaining limitations.

## Version 1.0.25

Fixes 1.0.24 double-counting finished games in team records (export reconciliation), restores "tournaments" in the homepage H1. See docs/CHANGES.md.

## Version 1.0.24

Shorter parent-focused hero, schedule-first event-page actions, and non-destructive Playbook CSV reconciliation. See docs/CHANGES.md and docs/REVIEW-1.0.24.md for changes, validation and installation limits.

## Current version: 1.0.19 (prepared update; not installed)

See docs/VERIFICATION-1.0.19.md for the parent-first homepage, team schedule view and executed checks.

## Previous version: 1.0.17 (prepared update; not installed)

See docs/VERIFICATION-1.0.17.md for the Game Day Hub redesign and executed checks.

## Previous version: 1.0.11 (prepared update; not installed)

See docs/VERIFICATION-1.0.11.md for bracket game details and executed checks.

## Previous version: 1.0.10 (prepared update; not installed)

See docs/VERIFICATION-1.0.10.md for the homepage and Night Lights updates and executed checks.

## Previous version: 1.0.9 (prepared update; not installed)

See docs/VERIFICATION-1.0.9.md for mobile menu fixes and executed PHP/Chromium checks.

## Previous version: 1.0.8 (prepared update; not installed)

See docs/VERIFICATION-1.0.8.md for current results. PHP lint and the full local runner now pass under PHP 8.3.6 with the parent staging export. Browser layout, screenshots and staging theme deployment remain unverified. Older verification statements below describe earlier versions.

## Latest update: 1.0.5 draft

See docs/CONTRAST-1.0.5.md for changes, verification limits and preview instructions.

# ⚠ Version 1.0.3 — critical fix. Do not use 1.0.0, 1.0.1 or 1.0.2.

**Game Day text unreadable in some places (reported from screenshots)?** Run `python3 tests/browser/contrast-audit.py https://STAGING-URL` (see its docstring). It prints each unreadable element, its colours, and the exact rule responsible. Also do this A/B in the Customizer on the same screen: preview the parent theme, then the child. If the parent shows it too, it pre-dates the child. Compare *Appearance → Customize → Additional CSS* in both: it is stored per theme and does not carry over to a child.

The parent theme builds all of its asset URLs from `get_stylesheet_directory_uri()`. Under a child theme that returns the *child* folder, so every parent stylesheet/script/logo returned 404: pages rendered unstyled and Game Day scripts could not load. 1.0.3 fixes this in `functions.php` (a `stylesheet_directory_uri` filter; no parent files edited). The child's own assets now use `SWAT_CHILD_URI`.
If you previewed an earlier version in the Customizer on the live domain: nothing public changed, but the older code could register a harmless cron entry. Remove it with `wp cron event delete swat_child_warm_availability` (safe if it doesn't exist).

# Version 1.0.1 update

The three required fonts and licenses are now included; no separate font upload is needed.
Use the browser check against the full staging base URL:

```sh
sh tests/run-all.sh
python3 tests/browser/staging-check.py https://swatourneys.com/staging/2948 --event EVENT-SLUG
```

Replace EVENT-SLUG with a real staging event. The checker preserves the staging path and rejects redirects outside it. HTTP Basic authentication is supported with --auth; a WordPress login or Coming Soon wall requires a separately authenticated test session and is not a valid page pass.

See docs/TEST-REPORT.md for current verification. Earlier installation instructions below about missing fonts are superseded by this update.

---

# SWAT Tournament OS Child — v1.0.0 (staging build)

Child theme of **SWAT Tournament OS** (`swat-tournament-os-master`, v2.0.0).
Status: **built and statically checked; NOT yet runtime-tested in WordPress.**
Do not activate on production. See `docs/TEST-REPORT.md` for exactly what is and is not verified.

## What it does
1. **Registration status + deadline per tournament** (Open, Limited, Sold Out, Closed, Complete, Cancelled), editable at *SWAT Operations → Registration Status*. Nothing is invented: deadlines are hidden unless entered, Limited only appears if an admin chooses it, and no team counts are shown.
2. **Homepage:** removes the "Full weekend schedule" section (day tabs + game cards). Hero now shows **REGISTER** (primary) and **OPEN GAME DAY** (secondary). Upcoming/this-weekend cards show the real status and deadline, and hide REGISTER when registration isn't available.
3. **Tournament directory and event pages:** same status/deadline logic; event details only show register buttons when registration is available.
4. **Typography:** local **Barlow** (headings) + **Inter** (body, tables, buttons, game cards); the parent's Google Fonts request is removed; conflicting parent font declarations are overridden.
5. **Size safety** for the hero headline and two page h1s so Barlow's wider letterforms don't overflow on phones (active only when the local fonts are installed).
6. **Accessibility/mobile polish** for the components it touches: 44px targets, two-tone visible focus ring, solid-fill badges (all ≥ 4.5:1), reduced-motion.

## What it does NOT touch
Parent files, Game Day (`/gameday/`, `?event=`, `?team=`, `?field=`, `?view=`), `swat/v1/*` and `swat-gameday/v4` REST routes, Follow Teams, score reporting, Field Help, PWA, Playbook sync, pitching rules/calculations, registration URLs (still `play.swatourneys.com`), or any `swat_game` data. Game Day markup and JS are not modified; only typography reaches it (via CSS).

## Requirements / dependencies
| Item | Requirement |
|---|---|
| Parent theme | `swat-tournament-os-master` **must stay installed** under that exact folder name |
| WordPress / PHP | 6.4+ / 8.0+ |
| Plugins | None new. Playbook data comes from the parent's existing feed (wpgetapi `playbook`). Beaver Builder stays installed for the legacy pages that use it (about, rules, etc.); this phase does not rebuild any page in Beaver Builder |
| Paid | None. Barlow and Inter are SIL OFL (free, self-hostable) |
| Font files | **You must add 3 files** to `assets/fonts/` (see `assets/fonts/README.txt`). Until all three exist the child changes nothing about fonts and shows an admin notice |

## Step 0 — verify what is actually live (I could not)
My connector was blocked (Jetpack plan required), so the active theme is **unverified**. Check any one of:
- WP-CLI: `wp option get template && wp option get stylesheet`
- WP Admin: *Tools → Site Health → Info → Active theme*
- Expected before install: whatever is live now. Expected after activating on **staging** only: `template = swat-tournament-os-master`, `stylesheet = swat-tournament-os-child`.

## Staging install
1. Take a full backup (database + `wp-content`) and create/refresh the host's staging copy (e.g., Bluehost staging).
2. On **staging**: *Appearance → Themes → Add New → Upload* `swat-tournament-os-child-1.0.0.zip` (leave parent as is). Don't activate yet.
3. Add the three font files to `wp-content/themes/swat-tournament-os-child/assets/fonts/` (SFTP).
4. Activate the child on staging. Rewrite rules flush automatically (parent hook); if `/tournaments/` 404s, save *Settings → Permalinks* once.
5. **Theme settings:** WordPress stores Customizer settings per theme. Compare *Appearance → Customize* and *Menus → Manage Locations* against the parent. To copy parent theme mods on staging only:
   `wp eval '$m=get_option("theme_mods_swat-tournament-os-master"); if($m){update_option("theme_mods_swat-tournament-os-child",$m);}'`
6. Purge every cache (host cache, plugin cache, CDN). Do not full-page-cache `/gameday/`.
7. Enter real deadlines/overrides at *SWAT Operations → Registration Status* (only if you have verified values).
8. Run `sh tests/run-all.sh` (PHP lint + tests) and `python3 tests/browser/staging-check.py …` (see `docs/TEST-REPORT.md`), then the manual pre-launch checklist there (WordPress runtime behavior, Game Day, Beaver Builder pages, real devices).

## Registration status rules (first match wins)
1. **Admin override.** Open/Limited stop applying once the event has ended (shows Complete).
2. Playbook lifecycle status **Cancelled** → Cancelled.
3. Event end date passed → **Complete**.
4. Playbook public **SOLD OUT** detection (the parent's existing detector, unchanged) → Sold Out.
5. Admin-entered deadline has passed → **Closed**.
6. Otherwise **Open**. (Open here means "nothing says otherwise", the same assumption the parent already makes. Limited is never automatic.)

Playbook sync cannot overwrite an explicit admin override. The admin screen shows what Auto would display and warns when an override is hiding a Playbook Cancelled/Sold Out signal or a passed deadline.
Storage: one option, `swat_child_registration`, keyed `event-slug|start-date`. If Playbook changes an event's start date, the old entry appears under "Saved entries for events no longer listed" for you to delete.
Capability: `manage_options` by default (filter `swat_child_registration_capability`).

## Homepage availability freshness
To keep the homepage fast, it reads the parent's cached SOLD OUT result and never makes an HTTP request itself. A 10-minute WP-Cron job (`swat_child_warm_availability`) refreshes that cache via the parent's own detector. Directory and event pages use the parent's live check as before. If WP-Cron is disabled on the host, run it from a real cron or the homepage may show Open until a directory/event page warms the cache.

## Rollback
1. *Appearance → Themes* → activate the parent (or the previously live theme). The cron job is removed automatically on switch.
2. Purge caches. All parent behavior returns; Google Fonts load again.
3. Optional cleanup: `wp option delete swat_child_registration` (removes admin entries only; nothing else was written). Delete the child theme folder if desired.
No database migration was performed, so no data restore is needed.

## Maintenance notes
- `front-page.php`, `archive-tournament.php`, `single-tournament.php` are **copies of parent templates with scripted edits**. After a parent update, re-run `python3 tools/build-templates.py <parent> <child>`; it fails loudly if the parent changed those regions. `tools/generate-font-overrides.py` regenerates `assets/css/child-fonts.css` the same way.
- The parent routes `/tournaments/…` with its own `include`; `inc/templates.php` intercepts at `template_redirect` priority 0 to use the child versions. Removing that hook returns to parent output.
- `swat_child_cached_sold_out()` relies on the parent's transient name `swat_pb_soldout_<md5(EventId)>`. If the parent renames it, the homepage falls back to "not sold out" until updated.

## Not done in this phase
Admissions pricing editability (homepage admission text is hardcoded in the child's front-page.php, pricing confirmed 2026-10-01: $13 adults; teens 13-17/seniors 60+/military $10; kids 7-12 $8; 6 & under free; cashless), Rules/Locations/Contact page redesign, Beaver Builder layouts, any change to Game Day layout. Say the word and I'll scope them next.

## Files
`style.css`, `functions.php`; `inc/` (registration-status, registration-admin, fonts, templates); `front-page.php`, `archive-tournament.php`, `single-tournament.php`; `assets/css/{child.css, child-fonts.css}`; `assets/fonts/README.txt`; `tools/` (generators, checkers); `tests/` (resolver oracle + PHP replay); `docs/`.
