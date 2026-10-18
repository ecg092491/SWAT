# Version 1.0.9 — mobile menu repair

Prepared child-theme update. Not installed or deployed to WordPress.

## Changes
- Corrected Game Day tabs' z-index with !important to override the parent theme's 400 layer.
- Hide Game Day navigation while the existing parent menu-open state is active.
- Pin the open-menu header and close button to the viewport; account for the WordPress admin toolbar.
- Keep page scrolling locked and the drawer independently scrollable.
- Left-align submenu links, remove inherited decorative pseudo-elements and list markers, allow long labels to wrap, and retain 44px minimum link heights.
- Apply mobile submenu rules through the parent's actual 1259px breakpoint.
- Keep desktop navigation and all existing links and data logic.

## Executed checks
PHP 8.3.6: tests/run-all.sh with PARENT set to the supplied staging parent export.
- php -l: all 14 PHP files passed.
- Registration: 112 resolver cases plus 4 sanitize checks, zero failures.
- Asset URI: all three checks passed.
- Routing: 8 path cases plus 2 menu cases, zero failures.
- Font overrides current; asset URL audit passed; all four generated templates identical.

Headless Chromium 153 / Playwright: tests/browser/mobile-menu.cjs at 390, 1024 and 1440px.
Local fixture renders the real child header.php using WordPress function stubs,
loads the real parent/child CSS, bundled logo/fonts and parent menu JS.
Mobile checks: no horizontal overflow; menu opens and closes; tabs hidden while
open and restored on close; background scroll locked; drawer scrolls to Contact;
submenu pseudo-elements absent, links left-aligned and at least 44px tall;
drawer receives pointer hits; selecting a menu link closes the menu.
Desktop checks: navigation visible, mobile toggle hidden, no horizontal overflow.

Screenshots show local fixtures, not the installed staging or live site.
This task did not recheck every Game Day tab or live Autumn Harvest brackets.
