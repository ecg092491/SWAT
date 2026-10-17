#!/bin/sh
# Run from the theme folder in an environment with PHP 8+. Exit 1 on any failure.
set -eu
command -v php >/dev/null 2>&1 || { echo "ERROR: PHP 8+ is required" >&2; exit 127; }
cd "$(dirname "$0")/.."
echo "== php -l on every PHP file"
fail=0
for f in $(find . -name '*.php'); do php -l "$f" >/dev/null || { echo "LINT FAIL: $f"; fail=1; }; done
[ "$fail" = 0 ] && echo "all files lint clean"
echo "== registration precedence tests"
php tests/resolver-test.php
echo "== homepage actions (schedule vs registration) and bracket placeholders"
php tests/home-actions-test.php
php tests/home-render-test.php
echo "== Gold / Silver split finals"
php tests/bracket-tier-test.php
echo "== team profiles (records, seasons, events)"
php tests/team-profile-test.php
echo "== team profiles from the Playbook team page"
php tests/team-playbook-test.php
echo "== Playbook season games export"
php tests/season-export-test.php
echo "== complete results pipeline and cross-season corrections"
php tests/results-pipeline-test.php
echo "== team profile links, sitemap and descriptions"
php tests/team-links-test.php
echo "== waitlist button and directory titles"
php tests/waitlist-test.php
echo "== Game Day sponsor banners, tracking and admin data"
php tests/sponsors-test.php
echo "== asset URL fix (parent assets must resolve to the parent folder under the child)"
php tests/uri-filter-test.php
echo "== root and subdirectory routes / tournament menu"
php tests/routing-menu-test.php
echo "== Game Day JavaScript syntax and presentation regression tests"
command -v node >/dev/null 2>&1 || { echo "ERROR: Node 18+ is required for Game Day tests" >&2; exit 127; }
for f in assets/js/*.js; do node --check "$f"; done
node --test tests/gameday-*-test.cjs
echo "== generated files are current (needs the parent theme path in PARENT=)"
if [ -n "${PARENT:-}" ]; then
  python3 tools/generate-font-overrides.py "${PARENT:-}" | diff -q - assets/css/child-fonts.css && echo "child-fonts.css up to date"
  python3 tools/asset-url-audit.py "${PARENT:-}" .
  python3 tools/verify-templates.py "$PARENT" .
  python3 tools/build-gameday-chrome.py "${PARENT:-}" | diff -q - assets/css/gameday-site-chrome.css && echo "gameday-site-chrome.css up to date"
fi
[ "$fail" = 0 ]
