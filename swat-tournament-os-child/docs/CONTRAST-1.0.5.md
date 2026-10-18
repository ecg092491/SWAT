# 1.0.5 — Game Day contrast patch (draft)

This patch uses actual parent component selectors and the four supplied screenshots. It explicitly colors Tournament Hub, Next Game, times, opponents, day names, Smart Team Alerts, and pitching instructions/labels/status. Only named text elements inside body.swat-gameday-mode .swat-gameday are affected. White select fields, refresh buttons, player cards, gold buttons and other pages retain their original colors.

The original #111111 rule is still unverified: screenshots were taken inside the WordPress Customizer and its full URL was not supplied. Staging metadata reports the parent theme active; its public page returns Coming Soon. Root-site HTML includes older inline dark-text overrides, but those alone do not establish the source of all screenshot defects.

The contrast audit now defaults to 4.5:1 and fails when [data-swat-app] is absent, so Coming Soon or sign-in screens cannot count as a passing Game Day audit. It lists candidate matching rules; it does not determine the winning CSS declaration.

## Verification performed

- Python syntax compilation passed.
- ZIP integrity checked.
- Exact new CSS selectors and preserved control rules checked statically.
- Light primary text #F5F7F9 and secondary text #C8CFD8 exceed 4.5:1 against the screenshot's #07080A backdrop and #14181D panels.

No rendered-page validation or full PHP suite was completed in this environment. Chromium installation failed; PHP is unavailable. This file is a draft package, not a verified deployment. No WordPress changes were made.

## Preview check

Upload as an update to the existing child theme, keeping the parent installed. Use a theme preview first. Confirm Tournament Hub, a followed team's Next Game and itinerary, Smart Team Alerts and Pitching on your phone. Confirm white controls still show dark text. Recheck after switching events and refreshing.

Run from the theme folder against an accessible Game Day page:

    python3 tests/browser/contrast-audit.py https://STAGING --min 4.5

Staging must expose the actual page to the audit session; HTTP basic auth does not bypass WordPress Coming Soon. A public Coming Soon screen should now fail explicitly.

Rollback: restore 1.0.4. Game records, event isolation, pitching eligibility and sync logic are unchanged.
