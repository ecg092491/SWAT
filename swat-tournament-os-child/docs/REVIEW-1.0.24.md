> **Superseded by 1.0.25.** The export reconciliation described here could double count finished games when WordPress rows differed from the export (time, division label, duplicate rows). See CHANGES.md, 1.0.25.

# Review and validation — 1.0.24

## Verified locally
- PHP 8.3 syntax lint: every PHP file passes.
- tests/run-all.sh passes with DOM enabled: 112 registration resolver scenarios, four sanitization checks, public unavailable-copy regression, 48 home action/placeholder checks, eight bracket tier checks, 12 team profile checks, 21 Playbook team checks, 29 export checks, asset URI checks, eight path cases and two menu cases.
- Existing Canes record fixtures still pass: 15-2-0, 17 games, 214 runs scored and 77 runs allowed.
- JavaScript syntax check passes for the unchanged Game Day script.
- The installable archive contains the same theme folder and required parent declaration.

## Limits
- Parent theme was not supplied; parent-dependent regeneration/asset checks were skipped by the test runner.
- No live WordPress/plugin integration or visual phone review performed. Browser QA was attempted but Chromium could not be installed in this environment.
- Export reconciliation preserves omitted games. Removing an erroneous historical game requires an explicit source correction; simply leaving it out of a later CSV does not remove it.
- Legacy rows without stable IDs require a unique matching event/date/time/division/team pairing. Ambiguous rows are preserved rather than silently removed and may require review.
- Public sold-out events without posted games say Registration closed; View Schedule is offered when games are present. Admin status names remain unchanged.

## Install
Install this ZIP as the update to SWAT Tournament OS Child. Keep the existing parent theme installed. Purge site/page caches and check homepage team search, posted event actions, Game Day and team records on staging before a live release. This package has not been deployed.
