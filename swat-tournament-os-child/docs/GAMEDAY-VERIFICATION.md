# Game Day 1.0.34 review candidate

Status: implemented, automated checks passing; browser acceptance pending. Do not treat this as a production-verified release. No deployment was performed.

## Baseline and scope

The baseline is the supplied `swat-tournament-os-child-1.0.33.zip`, imported unchanged in this branch. The repository previously contained only the older 1.0.32 archive. Parent reference: supplied SWAT Tournament OS 2.0.1 archive. The upstream Playbook sync plugin and a running WordPress installation were not available. See GAMEDAY-AUDIT.md for the implementation map and ranked findings.

Today now prioritizes followed live games, the next game, and later games before hub tiles. Shared tokens cover the hub and division views. The source timestamp drives the compact refresh state, with an explicit warning after 300 seconds, failed sync, unverified timestamps, or offline data. Refresh preserves focus/disclosure state and highlights changed scores/status. Score cells reserve space, but actual layout stability still requires browser measurement. Pool standings retain ranking and run differential while exposing W, L, T, RS and RA in a contained scroll region with a sticky team column.

Champion display requires literal source `championship: true`, a final status and valid unequal scores. Round names and pool ranking cannot award a championship. Combined division membership comes from explicit source membership or an explicit combined division label. Names are escaped by the existing HTML escape helper; renderer tests exercise hostile names.

## Executed checks

| Check | Baseline | Candidate |
| --- | --- | --- |
| PHP syntax, all existing default PHP tests | Pass | Pass |
| JavaScript syntax, all child JS assets | Not part of old runner | Pass |
| Champion, sync, combined division model assertions | Added in candidate | Pass |
| Actual renderer tests: champion, collapsed rounds, escaping, combined membership | Added in candidate | Pass |
| Shared text/button/control token contrast | Added in candidate | Pass, both themes |
| New Node regression tests | — | 9 tests passed |
| Local PHP fixture over HTTP | — | 200, Today/team markup; six API states returned 200 |
| Separate build / TypeScript check | No configuration | No configuration |
| Optional generated-file checks against supplied parent | FAIL, pre-existing | Not repaired outside scope |
| Browser screenshots, overflow, keyboard, refresh layout shift | BLOCKED | BLOCKED |
| Lighthouse Today Performance / Accessibility | Not run | Not run |
| Lighthouse Division Performance / Accessibility | Not run | Not run |
| Installed iOS Safari PWA | Not available | Not available |

The optional parent compatibility checks report generated font CSS and header mismatches on the unchanged baseline. Confirm the exact deployed parent before regenerating these files. This is separate from the layout change.

Chromium and Chromium headless-shell downloads produced invalid archives in this environment. Playwright cannot launch because its executable is absent. Cloud browser access to the local fixture also failed. No screenshots, Lighthouse scores, whole-page WCAG certification, keyboard results, no-overflow claim or no-layout-shift claim are supplied. Token contrast tests alone cannot certify complete rendered pages.

## Reproduction and acceptance gates

Run `bash tests/run-all.sh` with PHP 8+ including DOM and Node 18+. Optional parent checks: `PARENT=/path/to/exact-parent bash tests/run-all.sh`.

The guarded fixture serves the real child templates, CSS and JavaScript with explicitly synthetic data. It is not a WordPress integration test and does not reproduce production service-worker registration. It only runs under CLI/CLI-server with `SWAT_FIXTURE_ENABLED=1`.

With Playwright and Chromium available as external test tooling:

```sh
node tests/browser/gameday-matrix.cjs /path/to/unchanged-1.0.33 /tmp/gameday-before before
node tests/browser/gameday-matrix.cjs . /tmp/gameday-after after
```

Each run captures Today, Schedule, Scores, Standings, Division and More at 375/390/768 pixels in dark/light, for pregame, live, final, no-followed-team, sync-failed, stale, no-data, unverified and loading states (324 screenshots per revision). It records page overflow and JavaScript errors. The harness itself still needs its first browser run; inspect selector compatibility against the baseline.

On staging with the exact parent and sync plugin, complete these release gates:

1. Review every before/after image. Confirm next-game time/field/opponent and Directions are initially visible, long names wrap, all controls are at least 44px, and no page scroll occurs horizontally at 375px. Check both member divisions for combined brackets.
2. Change a source score/status during polling; measure layout shift and inspect the highlight with normal/reduced motion. Confirm focus and open details survive. Exercise Tab, arrow/Home/End navigation, follow/unfollow, event selection and Retry.
3. Run full-page contrast/accessibility checks in both themes, including controls, banners, dialogs, standings and real sponsor content. Shared light gold text is #8a5d00 because #c98a00 does not meet 4.5:1 on white.
4. Run Lighthouse mobile against Today and Division, before and after, using the same staging data/network settings. Record Performance and Accessibility scores and artifacts. No numeric targets or scores are fabricated here.
5. On an actual iPhone, test Safari and installed Home Screen mode, safe-area insets, sticky tabs, appearance, offline/stale service-worker responses, update activation, Add to Home Screen instructions, permissions and team push alerts.
6. Verify live payloads expose the literal championship flag and combined membership. If absent, the UI intentionally declares no champion; do not infer one to compensate.

No new application packages were added. Self-hosted Barlow 400/500/600 and Barlow Condensed 600/700 TTF assets come from google/fonts (ofl/barlow and ofl/barlowcondensed); the included OFL license covers them. Existing unrelated fonts remain for other pages. Playwright is optional external test tooling, not an application dependency.
