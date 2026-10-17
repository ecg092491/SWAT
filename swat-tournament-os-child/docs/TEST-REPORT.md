# Verification update — version 1.0.4 (2026-10-01)

Phone screenshots of Game Day under 1.0.3 (Customizer preview) show the app working: styled, ONLINE status, Change event, Tournament Hub, schedule-posted alert, a followed team with itinerary and bottom navigation. They also show some text nearly invisible: the measured colour of that text is exactly #111111 on a #07080A background (contrast about 1.1:1). Affected: the TOURNAMENT HUB and NEXT GAME headings, game times, opponent lines, the pitching intro/labels/status text.

**Cause not yet identified; not confirmed to be this child theme.** Evidence so far: (1) the child's CSS contains zero rules setting #111/#111111 colour; (2) the parent's #111 rules are button/gold-accent text, none on generic headings/labels; no inline or `theme.json` source; (3) a browser reproduction using the real Game Day stylesheet stack in load order renders all of those elements light (contrast 18:1), identical with and without the child CSS. My fixture markup is an approximation of the real JS-rendered DOM, so this does not rule the child out. Remaining suspects: per-theme Customizer *Additional CSS* (does not carry over to the child), a plugin/snippet, or a parent rule that only applies to the real DOM.

**Next step (needs your site):** run `tests/browser/contrast-audit.py` against staging Game Day (validated here: reports 0 on a clean fixture; with three planted defects it found all five affected elements and named the rule/ancestor responsible). And do the Customizer A/B: preview the parent theme on the same screens.

---

# Verification update — version 1.0.3 (2026-10-01)

**What happened:** a phone screenshot of a Customizer preview showed the page unstyled. Root cause: the parent defines `SWAT_OS_URI = get_stylesheet_directory_uri()` (31 uses) and calls it directly in a few places, which points at the child folder under a child theme, so parent assets 404'd. My earlier fixtures loaded parent CSS from disk and could not see this; that was a blind spot in my testing, and I should have audited the parent for stylesheet-vs-template path assumptions up front.

**Fix:** filter `stylesheet_directory_uri` to the parent folder (works because the child's functions.php runs before the parent's); `SWAT_CHILD_URI` for the child's own assets.

**Checked here (no PHP/WordPress):**
- Reproduced the symptom at URL level: parent CSS requested from the child folder => 404s, unstyled page, buttons collapse to ~76x20; requested from the parent folder => styled, 0 overflow, 0 undersized targets. The browser checker flags the broken variant.
- `tools/asset-url-audit.py`: the parent has 7 direct `get_stylesheet_directory_uri()` calls plus the `SWAT_OS_URI` constant, all covered by the filter; no parent use of `get_theme_file_uri()`, `get_stylesheet_uri()` or hardcoded theme folders; the child has no other uses.
- Found and fixed a checker race (font faces were requested at DOMContentLoaded, before async-loaded CSS could declare them, which would false-fail on cache plugins that defer CSS).
- Existing checker regression suite: 5/5 pass. PHP files bracket-balanced.

**Not run (needs PHP/WordPress):** `tests/uri-filter-test.php` (new; part of `run-all.sh`) and everything listed as pending earlier. **Please re-test on staging with this version**: parent CSS/JS must return 200 from `/themes/swat-tournament-os-master/`, and Game Day must load and behave as before.

---

# Verification update — version 1.0.2 (2026-10-01)

Independently checked in my sandbox (Chromium + Python; no PHP):
- The three bundled WOFF2 files load and decode in Chromium. Inter's weight axis works (400 vs 800 widths differ). Barlow is regular width, not condensed: "TOURNAMENT" = 6.31 em (my Arial-metric proxy was 7.09 em, so my earlier proxy-based pass was about 12% conservative). Characters checked present in Inter and Barlow: é ñ ü ’ “ ” – — $ € % & @ #; Barlow lacks → and ← (they would fall back; the theme uses arrows only in body-font links).
- Re-ran my layout suite with the REAL fonts at 320/375/390/430/768/1280 on component fixtures: 0 overflow, 0 undersized targets, 0 headings with a word wider than its box. The hero word fills 86–87% of its box at 320 and 390, so the size safety keeps comfortable headroom (it is slightly more conservative than strictly needed).
- Found and fixed: the preload hint named Barlow 800, but the homepage uses Barlow 700 (event pages use both). Now preloads 700.
- Open performance item: Inter is ~350 KB and preloaded site-wide. Subset it (command in `assets/fonts/README.txt`).
- Carried forward as REPORTED by the 1.0.1 notes (I did not re-run these and had no PHP/staging access): PHP 8.3.6 lint pass on every file, 112 resolver + 4 sanitize cases passing in real PHP, authenticated staging read showing parent v2.0.0 / stylesheet swat-tournament-os-master.
- Still pending: activation and runtime on staging, real-page layout at the five phone widths, Game Day regression, Beaver Builder, real Playbook sold-out/cancelled checks.

---

# Verification update — version 1.0.1 (2026-10-01)

- PHP 8.3.6: every PHP file linted successfully.
- Actual PHP registration tests: 112 resolver cases + 4 sanitization checks, zero failures.
- Three real WOFF2 files bundled and parsed successfully with fontTools; OFL licenses included.
- Staging authenticated read confirmed SWAT Tournament OS v2.0.0, stylesheet swat-tournament-os-master.
- Browser checker now preserves /staging/2948, rejects redirects outside the base, detects HTTP 4xx/5xx and JavaScript exceptions, explicitly loads all three font faces and requires successful WOFF2 responses.
- Browser regression fixtures in Chromium: clean pages pass; missing fonts, HTTP 404 assets, out-of-base redirects and uncaught JavaScript errors each fail correctly (five scenarios at 320px). These fixtures do not verify SWAT staging layout.
- Parent CSS regeneration comparison, activation, staging layout, WordPress runtime, Follow Teams, score submission, Beaver Builder, and real Playbook sold-out checks remain pending.
- No site files or settings changed during this rebuild.

The original author's report below describes earlier work; its blocked PHP and font statements are superseded above.

---

# Test report — SWAT Tournament OS Child 1.0.0

Date built: 2026-10-01. Environment: sandbox with Python, Node, headless Chromium. **No PHP binary, no WordPress, no network, no access to the live site's admin.**

## Completed checks (what was actually run)
| # | Check | Result |
|---|---|---|
| 1 | Read the full parent theme (131 files): routes, enqueue order, Playbook helpers, templates | Done. Findings drove the design (e.g., parent `include`s its tournament templates, so a normal child override would never load) |
| 2 | Scripted template edits against the parent | All 24 exact-string replacements matched (`tools/build-templates.py` asserts each). Homepage no longer contains `weekend-day-schedule` / `data-weekend-day` |
| 3 | Bracket/quote balance of every PHP file (`tools/php-balance-check.py`) | Passes on all child PHP files. The checker was first run on 63 known-good parent PHP files (all pass), so it is credible — but it is **not** a PHP syntax check |
| 4 | Every parent function the child calls exists in the parent; no name collisions | 52 `swat_*` calls checked; 0 undefined; 0 collisions |
| 5 | Registration precedence logic | Independent oracle (ordered rule list), 112 input combinations, invariants asserted (sync cannot override admin; Limited only from admin; etc.). A line-by-line Python port of the actual PHP function body matches all 112. **This tests my reading of the logic, not PHP execution** |
| 6 | Font override CSS | Generated from all 16 parent stylesheets (151 selectors); paren-aware (a first version broke `:is()` lists and was fixed); Chromium parsed all 8 grouped rules; computed styles show Barlow on headings, Inter on body/buttons/meta, beating the parent's `!important` declarations |
| 7 | Badge contrast | All five badge pairs ≥ 7.9:1 (AA needs 4.5:1) |
| 8 | Component fixtures in Chromium using the parent's real CSS + child CSS, at **320, 375, 390, 430, 768, 1280 px** (home + event/directory components) | No horizontal overflow; all 10–13 checked controls ≥ 44×44 px at every width. Screenshots delivered alongside the ZIP (`screenshots/`) |
| 8b | **Heading-fit test** (does the longest word in each display heading fit its box at 320/375/390/430/768?) | First version of the test falsely passed a broken case (it measured the wrong font) — caught by validating it on a known-bad configuration, then fixed. With the fixed test, the parent's mobile hero (`TOURNAMENT`) and the directory `h1` overflowed under a wide font. Added a scoped size safety (3 rules at the end of `child-fonts.css`, active only when local fonts are installed). Now passes at all widths **using Liberation Sans Bold as a stand-in that is roughly 10% wider than Barlow ExtraBold, so a pass is conservative. Real Barlow is still unverified.** |
| 9 | Active theme verification | **Blocked** (connector needs a Jetpack plan). Commands provided in README |

## NOT done — required before any launch
| Check | Why pending | How |
|---|---|---|
| **PHP lint** (`php -l`) on every file | No PHP here | `find . -name '*.php' -exec php -l {} \;` (PHP 8.0+) and `php tests/resolver-test.php` (expects 0 failures) |
| **WordPress runtime test** on staging | No WordPress here | Activate on staging; load `/`, `/tournaments/`, one `/tournaments/{slug}/`, `/gameday/` (+ `?event=`, `?team=`, `?field=`, `?view=`); check PHP error log is clean; open *SWAT Operations → Registration Status*, save entries, confirm public pages change; confirm Game Day, Follow Teams, score report, Field Help behave exactly as before |
| **Mobile browser checks of real pages** at 320/375/390/430/768 + desktop | Fixtures only; real pages weren't rendered | Real iPhone Safari + Android Chrome + devtools at those widths, on home, directory, event, Game Day |
| **Barlow/Inter visual pass** | Font files not available to me | Barlow is wider than Oswald. A size safety is in place for the hero and two h1s; check every other long uppercase heading (and the hero) at 320 px once the real files are installed, and tell me which to tune |
| Beaver Builder legacy pages still edit/render | No site access | Open an about/rules page in the Beaver Builder editor on staging |
| Playbook integration | No site access | Confirm event feed loads; sold-out detection still shows Sold Out for a known sold-out event; Cancelled mapping against a real Playbook `Status` value |
| Theme-mod/menu carry-over | WordPress stores these per theme | Compare Customizer + menu locations; copy mods if needed (README step 5) |
| Cache/CDN behavior | Host-specific | Purge after saving registration settings; confirm `/gameday/` is not full-page cached |
| Accessibility audit (axe/Lighthouse, screen reader, keyboard walk) | Not run | Run on staging pages above |

## Known risks to watch
- Three parent templates are copied (drift on parent updates — mitigated by the asserting build script).
- Homepage availability depends on the parent's cached SOLD OUT result; freshness depends on WP-Cron.
- "Cancelled" from Playbook assumes the feed's `Status` field uses `CANCELLED`/`CANCELED` (the parent already filters on that exact value).
- Playbook date changes orphan admin entries (shown in the admin screen for cleanup).

## One-command checks for your environment (included in the ZIP)
- `sh tests/run-all.sh` : `php -l` on every file + the precedence tests (set `PARENT=/path/to/parent` to also confirm `child-fonts.css` is current).
- `python3 tests/browser/staging-check.py https://STAGING --event EVENT-SLUG [--auth user:pass]` : real pages at 320/375/390/430/768/1280: overflow, tap targets, heading-word fit, console errors, failed requests, Barlow/Inter actually loaded, any Google Fonts request. Exit code 1 on any issue. I validated this script against my fixtures (it correctly reported 0 layout issues and flagged only the missing font files); it has **not** been run against a real SWAT page.

## Not included in the ZIP
The component fixtures I used live in my sandbox and point at absolute paths, so they are not shipped (the staging checker above replaces them for real pages). Screenshots from them are delivered separately (`screenshots/`, rendered with a fallback font, not Barlow/Inter).
