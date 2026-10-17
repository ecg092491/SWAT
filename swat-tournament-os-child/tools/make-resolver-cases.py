#!/usr/bin/env python3
"""Independent oracle for the registration precedence rules.

Written as an ordered rule list straight from the spec (NOT a transcription of
the PHP). It emits tests/resolver-cases.json and asserts the spec invariants.
tests/resolver-test.php replays the same cases against the real PHP function.
"""
import itertools, json

OVERRIDES = ['', 'open', 'limited', 'sold_out', 'closed', 'complete', 'cancelled']

RULES = [
    ('admin-cancelled', lambda i: i['override'] == 'cancelled', lambda i: 'cancelled', 'admin'),
    ('pb-cancelled', lambda i: i['pb_cancelled'], lambda i: 'cancelled', 'playbook'),
    ('admin-terminal', lambda i: i['override'] in ('sold_out','closed','complete'), lambda i: i['override'], 'admin'),
    ('ended', lambda i: i['ended'], lambda i: 'complete', 'date'),
    ('pb-sold-out', lambda i: i['pb_sold_out'], lambda i: 'sold_out', 'playbook'),
    ('deadline', lambda i: i['deadline_passed'], lambda i: 'closed', 'deadline'),
    ('admin-available', lambda i: i['override'] in ('open','limited'), lambda i: i['override'], 'admin'),
    ('default', lambda i: True, lambda i: 'open', 'default'),
]

def oracle(i):
    for _, pred, res, src in RULES:
        if pred(i):
            return res(i), src

cases = []
for ov, ended, cx, so, dp in itertools.product(OVERRIDES, *[[False, True]] * 4):
    i = dict(override=ov, ended=ended, pb_cancelled=cx, pb_sold_out=so, deadline_passed=dp)
    s, src = oracle(i)
    cases.append(dict(input=i, status=s, source=src))

# Cancellation cannot be reopened; restrictive manual closures persist otherwise.
for c in cases:
    i, result = c['input'], c['status']
    if i['override'] == 'cancelled' or i['pb_cancelled']:
        assert result == 'cancelled'
    if i['override'] in ('sold_out','closed','complete') and not i['pb_cancelled']:
        assert result == i['override']
    if result in ('open','limited'):
        assert not any(i[k] for k in ('ended','pb_cancelled','pb_sold_out','deadline_passed'))
assert len(cases) == 112
json.dump(cases, open('tests/resolver-cases.json', 'w'), indent=1)
print('112 cases generated; all invariants hold')
