> **Superseded by 1.0.27.** swat_child_export_reconcile_event() in this version could list a finished game twice on event pages and in standings when WordPress rows lacked event_name and their time differed from the export (edited, or "9:00" vs "09:00"). See CHANGES.md, 1.0.27.

# Validation — 1.0.26

PHP 8.3 with DOM: every PHP file passes syntax lint; tests/run-all.sh passes, including the original 34 export checks and eleven new results-pipeline checks. The new test invokes the actual swat_child_team_profile() production function against real shipped CSV data and a mocked conflicting cached HTML score: Canes remains 15-2-0, 17 games, 214 RS / 77 RA.

Event reconciliation tests verify corrected scores replace stale WordPress scores, retain uniquely matched post/round metadata, preserve unrelated upcoming games, and replace a matched stale live row without duplicates. The template uses reconciled inputs for both event cards and its standings/results computation.

Global export/read and upload/regroup tests verify a corrected date crossing the Spring/Fall boundary leaves one Playbook ID, omitted games remain, and repeated uploads are idempotent.

Limits: parent theme not supplied; parent-dependent generation/asset checks skipped. No live WordPress/plugin or browser layout verification; no deployment. PHP 8.0 compatibility reviewed at syntax/API level, execution tested on 8.3.

Policies retained: finals in a CSV event come from the export; legitimate WordPress-only finals absent from every export for that event are excluded. Games omitted from a later CSV remain; deletions require a future explicit removal mechanism. CSV corrections take priority over HTML scores for represented events, so upload a current export to apply newer source corrections. Game Day REST synchronization is unchanged; this patch fixes child-theme profile and event-page data paths.

Install as the child theme update while keeping swat-tournament-os-master installed. Purge caches and verify team records and event standings on staging before publishing.
