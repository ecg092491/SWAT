# 1.0.11 — Standings / Bracket game details

Prepared update; not installed on WordPress.

Every bracket game card now shows:
- Round (e.g. Semifinal or Championship) and current status.
- Date, prominent start time and field.
- Teams, seeds or winner placeholders, including TBD teams.
- Scores for live/final games.

Missing published information is labelled Date TBD / Time TBD / Field TBD.
The existing chronological division layout, fixtures, event selection and sync remain intact.

Validation executed:
- node --check assets/js/swat-gameday.js passed.
- PHP 8.3.6 lint on all 14 PHP files and tests/run-all.sh with supplied parent passed, including registration, URI, routing and generated-template checks.
- Headless Chromium 153 at 390 and 1440px executed the actual Game Day script with sample bracket API fixtures. All four games rendered; simultaneous games each retained their own time/field; championship time formatted correctly; TBD fixture stayed visible; seeds were labelled; final score retained; no JavaScript errors or page horizontal overflow.
- Bracket screenshots were visually inspected at 390px.

Screenshots use sample fixtures, not the live Autumn Harvest feed. This change does not invent game times or repair missing upstream fixtures.
