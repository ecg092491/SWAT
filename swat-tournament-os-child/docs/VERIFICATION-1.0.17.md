# 1.0.17: Game Day Hub

Prepared update. Verified on a local copy of staging; not yet installed on staging.

## What changed
- Game Day now follows the approved GameDay Hub design in SWAT black and gold. Changes:
  - event header on every view
  - game cards with star-to-follow
  - status pills: Live, Final, Delayed, Cancelled, Result pending, time
  - day tabs and filter chips, with time headers in the schedule
  - standings with a sticky team column and the seeding order listed
  - bracket grouped by round, with a champion box
  - fields grouped by facility, each with Directions
  - gold alert banner, More list and bottom tab bar
- New `inc/gameday-hub.php` changes how Game Day pages are styled; other pages are untouched:
  - It stops printing the parent's Game Day-only stylesheets and the child's `swat-field-ux.css`.
  - It prints `assets/css/gameday-site-chrome.css` where `swat-flagship.css` used to print. That file is a generated copy of flagship with only the Game Day component rules removed (`tools/build-gameday-chrome.py`). `tests/run-all.sh` checks that it is current.
  - It loads `assets/css/swat-gameday-hub.css` last.
- The Game Day block was removed from `child.css`; it now lives in the hub stylesheet. The mobile drawer rules stay in `child.css`.
- Child override `template-parts/gameday/home-hero.php`: the event header keeps the parent's event switcher and its This weekend / Upcoming / Past results grouping.
- `swat-gameday.js` (presentation only): new card, schedule, scores, standings, bracket, championships and fields renderers; follow stars; toast; filter chips; `#bracket` deep link.
  - Data loading, polling, event isolation, fixture merging, forms and the follow store are unchanged.
  - Canonical games take the round label from the matching bracket fixture.
- `swat-my-weekend.js`: the score line reads "Leading 6–4", "Won" or "Tied" from the followed team's side, falling back to the old away–home text.
- `tests/browser/mobile-menu.cjs` now loads the 1.0.17 Game Day stylesheet stack.
- `tests/browser/gameday-views.cjs` now waits for Live, Stale or ONLINE. The chip is relabeled after load, so the old wait never finished; it also failed on 1.0.16.
- Sponsor hooks (`swat_child_gameday_sponsor_slots`, `swat_child_gameday_field_sponsors`) render nothing until real sponsor data is supplied.

## Unchanged
Parent files, plugins, snippets, REST routes, Playbook sync, pitch-count bridge, division rules, follow storage format, URLs, IDs and data.

## Executed checks (local WordPress 6.8.3 + PHP 8.3)
Setup: staging parent export, this child, the SWAT plugins and every active snippet, running on fixture data.
- `PARENT=… sh tests/run-all.sh`: all passed. That covers lint, 112 resolver cases, URI tests, routes, all four templates, and the site-chrome file being current.
- `tests/browser/mobile-menu.cjs`: PASS at 390, 1024 and 1440.
- `tests/browser/gameday-views.cjs`: 18 view renders, with no overflow and no JS errors.
- `tests/browser/contrast-audit.py`: 0 elements below 4.5:1 across 8 Game Day paths at 390 and 1440.
- Viewport matrix: 8 widths (320–1440) × 8 views × light/dark = 128 renders.
  - 0 overflow, 0 Game Day tap targets under 40px, 0 text under 11px, 0 truncated names, 0 console errors.
  - The only small targets left are the desktop site-header links (33px tall at 1024 and 1440), which are unchanged.
- Long-name stress fixture: 96 renders, 0 defects.
- 68/68 functional checks:
  - follow, a 3-team limit and unfollow; chips and day tabs
  - placeholders and championships; next game with Directions
  - standings, bracket and the deep link; More destinations
  - pitching view and per-game log; change event; game and field context
  - alert banner and weather; score report and field-help submit
  - dark toggle, site menu, REST routes and namespaces
