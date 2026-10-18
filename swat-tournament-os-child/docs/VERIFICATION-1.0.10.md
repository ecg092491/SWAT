# 1.0.10 — Homepage and Game Day cleanup

Prepared update; not installed on WordPress.

Changes:
- Removed background photos from all Game Day Friday Night Lights and Saturday Night Lights heroes through their shared event classes; dark heroes remain.
- Upcoming Tournaments now starts expanded, with the existing hide/show control retained.
- Homepage This Weekend cards show Registration Closed for events starting October 2–4, 2026. This dated display change does not close later weekends or write to Playbook/WordPress registration records. Cancelled/Complete states are preserved.
- Removed the entire Why Teams Play SWAT and Built for Baseball Weekends/facilities sections.
- Tournaments is a direct header link to /tournaments/ with no submenu on phone or desktop.
- Updated the template generator so rebuilding preserves these changes.

Executed validation:
- PHP 8.3.6 lint passed on all 14 PHP files.
- PARENT=<supplied staging parent> sh tests/run-all.sh passed: 112 registration resolver cases, 4 sanitize checks; asset URI tests; 8 routing and 2 menu tests; font and asset audits; all four generated templates identical.
- Headless Chromium 153, local PHP homepage fixture at 390/1440: removed sections absent; all three October 2–4 cards Closed; later sample events remain Open; upcoming panel expanded initially and both collapse/expand actions work; no Tournaments submenu; no horizontal overflow.
- Shared FNL/SNL Game Day hero styles at 390/1440: computed background-image is none.
- Existing mobile menu Chromium regression checks passed at 390/1024/1440.

Screenshots are local fixtures with sample event data and real theme assets. They are not screenshots of an installed staging/live update.
