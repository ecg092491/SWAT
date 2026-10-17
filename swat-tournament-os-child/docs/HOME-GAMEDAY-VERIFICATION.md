# Home + Game Day 1.0.35 review: handoff and verification

Draft, not production-ready. No deployment. Layout implementation is committed; availability policy and full integration/browser acceptance remain open.

## Changes grouped by file

| Files | Change and reason |
| --- | --- |
| style.css | Single dark/light palette, font/scale/geometry tokens and accessible green/amber/red/gray status pairs. Gold fill #F6C515; light gold text #8a6400. |
| assets/css/shared-fonts.css; inc/fonts.php | One shared Barlow/Barlow Condensed font stylesheet on Home/Game Day; font-display swap. Skip old Inter/Barlow overrides and preloads on those two surfaces. Other pages retain legacy fonts. |
| inc/shared-design.php; functions.php | Versioned shared enqueues and scoped body class. No CPT/admin/config writes. |
| assets/css/shared-components.css | Shared cards, outline/gold buttons, badges, focus rings, reduced motion, responsive sticky header and 44px controls. |
| assets/css/home.css | Replaces old home-only token/button system and decorative hero CSS with layout-only rules: 1100px wrap, concise hero, 1/2/3-column event grid at mobile/640/900px. |
| front-page.php | Direct registration CTA, real-date active-event banner, expanded upcoming section, date blocks, disabled unavailable registration, explicit missing metadata, facility block, 2-column quick links and appearance controls. Team finder, previous results, admission details, existing copy and footer remain. |
| inc/home-data.php | Shared asset dependency; home sold-out badge wording. Availability/action resolver unchanged. |
| header.php | Honor assigned primary nav; retain original fallback links/submenus; translate fallback labels. Existing menu locations unchanged. |
| assets/js/appearance-boot.js; shared-ui.js; swat-field-ux.js | Enqueued head appearance initialization; coordinated theme choice; ResizeObserver header offset; Escape/outside-focus menu close supplementing parent click handler. |
| inc/gameday-hub.php; template-parts/gameday/app.php | Shared stylesheet dependency; remove executable inline appearance script. JSON payload script remains data, not executable code. |
| assets/css/swat-gameday-compact.css; swat-gameday-hub.css; team-schedule.css; postseason.css | Revised shared tokens, sticky-tab offset, outline controls, remove left accent bars and off-palette team decoration. Preserve game content and division behavior. |
| tests/home-render-test.php; tests/browser/home-fixture.php; fixture-parent/inc/os-registration-details.php | Guarded synthetic template rendering, no production requests. Actual home/header templates, source-name escaping, availability states, CTA destinations and empty/live cases. Parent integration is explicitly stubbed. |
| tests/browser/home-matrix.cjs; gameday-matrix.cjs; fixture.php | Reproducible Home + Game Day screenshot fixtures at 375/390/768/1280 and both themes. Optional Playwright tooling; not a new application dependency. |
| tests/gameday-contrast-test.cjs; team-links-test.php; run-all.sh | Test actual shared tokens including statuses; support translated header markup; run home renderer checks in existing suite. |

Deleted assets/css/gameday-tokens.css after moving its font faces and tokens to the shared sources. Replaced the old home.css implementation instead of stacking another home stylesheet. No plugins/frameworks/packages added.

## Commands and results

`PATH=/workspace/scratch/21208ccaeabf/bin:$PATH bash tests/run-all.sh` — PASS. PHP 8.3 with DOM, all theme PHP syntax checks, existing registration/action/team/export/routing/sponsor tests, 53 new home-render checks, all application JS syntax checks, nine Game Day regression tests. Home fixtures emitted no PHP notice/warning/deprecation under E_ALL. This is NOT WP_DEBUG in WordPress.

`node --check tests/browser/home-matrix.cjs` — PASS.

`git diff --check` — PASS for this change set.

`NODE_PATH=/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules node tests/browser/gameday-matrix.cjs . /workspace/scratch/21208ccaeabf/source/shared-after-evidence after` — BLOCKED: Chromium headless-shell executable missing.

`NODE_PATH=/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules node tests/browser/home-matrix.cjs . /workspace/scratch/21208ccaeabf/source/shared-after-evidence after` — BLOCKED: same missing executable.

No separate build pipeline, TypeScript configuration or JS/CSS linter is configured. PHP lint and Node syntax checks are not substitutes for browser/integration testing.

| Acceptance item | Result / exact next step |
| --- | --- |
| WP_DEBUG zero new notices | Not tested: no WordPress installation/database or upstream Games/sync plugin. Install exact production parent/plugin versions on staging, enable WP_DEBUG and visit both surfaces plus Games admin. |
| Before/after screenshots | Not available: browser executable missing; previous official downloads returned invalid archives. Run both matrices with Chromium installed, then repeat in real WP staging including inherited header/footer/styles. |
| Browser console zero errors | Not tested; inspect actual staging interactions with console capture. |
| 375px overflow / hero and next game above fold | Not measured. Review 375/390/768/1280 images with long names, all availability states and following 0–3 teams. |
| Refresh layout shift / highlighting | Not measured. Change live scores and status during polling, measure CLS and focus/scroll preservation, including moving games between live/next/final groups. |
| Keyboard menu/tabs/accordions | Code support present; no browser result. Exercise Tab/Shift+Tab, Escape, arrows/Home/End and focus return. |
| AA contrast | Shared text, control boundary and four status token pairs pass in both themes. Whole-page AA remains unverified, especially inherited footer, sponsor images and overlays. |
| CTA clicks | 53 server-render assertions include hero Register, Game Day, event actions and field/directory paths under a subdirectory site. No real browser click/navigation test performed. Verify configured Details destinations and off-site registration on staging. |
| iOS Safari / installed PWA | Not tested: no iPhone automation/device session. Test notch/home bar, sticky header/tabs, orientation, offline cache, theme choice and push on an actual installed PWA. |

## Lighthouse before / after

| Surface | Performance | Accessibility | Best Practices | SEO | Delta |
| --- | --- | --- | --- | --- | --- |
| Home | Not run | Not run | Not run | Not run | Unavailable |
| Game Day Today | Not run | Not run | Not run | Not run | Unavailable |
| Division | Not run | Not run | Not run | Not run | Unavailable |

Same missing Chromium and absent WP site block Lighthouse. Run matched mobile audits before/after against exact staging URLs and save HTML/JSON reports. No scores are fabricated.

## Decisions and deferred work

1. **Approval needed: availability fallback.** Existing inc/registration-status.php returns `open/default` when no positive availability signal is known; home-data.php also has an Open fallback when its resolver is missing. Existing tests intentionally expect this. The new requirement forbids unverified Open. Proposed change: use explicit Availability TBD with no event Register action until admin or source confirms open/limited. This changes data/action policy, so it has not been applied. The review build still inherits the old behavior and must not be released as meeting the real-availability rule.
2. Existing hardcoded admission prices, cashless/no-sanction-fee claims and schedule publication times remain unchanged. See audit content inventory. No field-count/turf claim was introduced. Confirm sources before altering copy meaning.
3. Parent registers primary/footer only; no bottom-tab menu location or Games CPT registration exists in supplied source. The site's upstream plugin may own these. Obtain its exact version/source for integration verification; do not invent registrations that could conflict.
4. Legacy Game Day JavaScript still contains untranslated English strings. New home/header strings use the existing child domain; complete legacy JS localization is a remaining task, not claimed complete.
5. Existing generated font/header checks against supplied parent fail on the prior unchanged baseline. The intentional custom header now also differs; stage against the exact deployed parent and update the generator contract before using it as a release gate.

The earlier interactive preview represents 1.0.34, not this expanded 1.0.35 review. A refreshed visual preview should be produced after browser verification and the availability decision.
