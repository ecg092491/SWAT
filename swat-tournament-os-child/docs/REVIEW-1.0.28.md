# Validation — 1.0.28

Every PHP file passes PHP 8.3 syntax lint. The complete tests/run-all.sh suite passes, including 30 results pipeline checks and 34 season export checks.

Confirmed regressions fixed:
- CSV final NC Made 7U vs Canes at 09:00 plus a separate ID-less same-day 15:00 scheduled/live row: both games remain when no stronger identity evidence exists.
- Two exported same-day matchups with equal scores plus an edited-time ID-less championship row: neither export receives the uncertain round label, independent of CSV order.

Positive controls confirm unique-time/score matching still retains post IDs and championship metadata, unique finished pairings retain metadata, and the complete Canes production profile fixture remains 15-2-0 over 17 games, 214 RS / 77 RA.

Limits: parent theme absent; parent-dependent generated-template/asset checks skipped. Live WordPress/plugin/browser integration unverified. PHP 8.0 reviewed at syntax/API level, executed on 8.3. No deployment performed.

Preserving an uncertain scheduled/live row may leave a stale copy visible until synchronization supplies stronger identity evidence. This intentionally avoids silently deleting a real rematch. Ambiguous finished-row metadata may be absent from canonical CSV finals rather than assigned to the wrong game.

Existing policies retained: CSV-represented events are authoritative for finals; omitted WordPress-only finals may be excluded. Previously exported games omitted from later CSV uploads remain, with no deletion mechanism. Game Day REST data and synchronization are unchanged.

Install on staging, keep swat-tournament-os-master installed, purge caches, and compare an event schedule/bracket/standings and team record against Playbook before publishing.
