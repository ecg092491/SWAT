# 1.0.6 — bracket schedule display

Status: prepared, not deployed. Staging admin browser returned 502 Bad Gateway.

Staging's Playbook cache already contains 22 Sunday Autumn Harvest bracket
fixtures matching the supplied October 4 screenshot, plus one Saturday 9U
bracket fixture. No extra game posts or placeholder teams need to be created.

An older empty Autumn Harvest cache entry precedes the real event's entry.
The parent adapter picks the first matching name, hiding the real fixtures.
The child now presents the newest cache entry per event name to that adapter.
Stored synchronization data remains unchanged.

The event directory expands its date range to include synchronized bracket
fixtures, both with an existing transient and on the first cold-cache request.

Game Day's Schedule combines canonical games with bracket fixtures and
deduplicates Playbook IDs, keeping canonical games authoritative once seeded.
Scores, standings, and score-submission choices continue using canonical game
records. Unresolved virtual fixtures have no Report score action.

Verification: JavaScript syntax checked; Sunday times, fields, divisions and
seed/winner placeholders compared against the screenshot. Browser rendering
and PHP execution still require staging deployment.

This package also includes the 1.0.5 contrast fix.
