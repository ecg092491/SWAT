#!/usr/bin/env python3
"""Generate assets/css/child-fonts.css from the parent theme's CSS.

Reads every public parent stylesheet, finds each rule that declares a font
family (font-family or the font shorthand), and emits a same-selector override
using the child font tokens. Same selector + !important + later load order means
the child wins without editing parent files. Re-run after a parent update.

Usage: tools/generate-font-overrides.py /path/to/parent-theme > assets/css/child-fonts.css
"""
import glob, os, re, sys

parent = sys.argv[1]
SKIP = {'swat-admin.css', 'swat-editor.css'}
FAMILY = re.compile(r'(oswald|politica|barlow|work sans|sf pro|apple-system|impact|poster-gothic|filson|inter\b|system-ui|segoe)', re.I)
DISPLAY = re.compile(r'(oswald|politica|barlow condensed|impact|poster-gothic|filson)', re.I)
HEADING_END = re.compile(r'(^|[\s>+~])h[1-6]$')
UI = re.compile(r'(action|btn|button|\bth\b|\btd\b|table|score|status|badge|tab\b|tabs|filter|__time|__field|game|team|nav|menu|__meta|chip|pill|label|input|select|__action|cta|dock|toggle|kicker|eyebrow)', re.I)

def blocks(css):
    i, n = 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0:
            return
        header = css[i:j].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            c = css[k]
            depth += (c == '{') - (c == '}')
            k += 1
        yield header, css[j + 1:k - 1]
        i = k

def walk(css):
    for header, body in blocks(css):
        if header.startswith('@'):
            if re.match(r'@(media|supports|layer)', header):
                yield from walk(body)
            continue
        yield header, body

def split_selectors(header):
    # Split on top-level commas only; commas inside :is()/:not()/[attr] stay put.
    parts, depth, cur = [], 0, ''
    for ch in header:
        if ch in '([':
            depth += 1
        elif ch in ')]':
            depth -= 1
        if ch == ',' and depth == 0:
            parts.append(cur); cur = ''
        else:
            cur += ch
    parts.append(cur)
    return [re.sub(r'\s+', ' ', x).strip() for x in parts]

def family_value(body):
    m = re.search(r'(?:^|[;\s])font-family\s*:\s*([^;]+)', body)
    if m:
        return m.group(1)
    m = re.search(r'(?:^|[;\s])font\s*:\s*([^;]+)', body)
    return m.group(1) if m else None

head, ui, body_sel = [], [], []
seen = set()
files = sorted(f for f in glob.glob(os.path.join(parent, 'assets/css/*.css')) if os.path.basename(f) not in SKIP)
for path in files:
    css = re.sub(r'/\*.*?\*/', '', open(path, encoding='utf-8').read(), flags=re.S)
    for header, body in walk(css):
        val = family_value(body)
        if not val or not FAMILY.search(val) or re.search(r'inherit|monospace', val):
            continue
        for sel in split_selectors(header):
            if not sel or sel in seen:
                continue
            seen.add(sel)
            if DISPLAY.search(val):
                (head if (HEADING_END.search(sel) or not UI.search(sel)) else ui).append(sel)
            else:
                body_sel.append(sel)

def emit(sels, token):
    return (',\n'.join(sels) + '{font-family:var(%s)!important}\n' % token) if sels else ''

print('''/*
 * SWAT Tournament OS Child - font overrides (GENERATED).
 * Source: tools/generate-font-overrides.py against the parent theme CSS.
 * Loaded only when the local Barlow/Inter files exist. Do not edit by hand.
 */
@font-face{font-family:"Inter";font-style:normal;font-weight:100 900;font-display:swap;src:url("../fonts/inter-variable.woff2") format("woff2")}
@font-face{font-family:"Barlow";font-style:normal;font-weight:700;font-display:swap;src:url("../fonts/barlow-700.woff2") format("woff2")}
@font-face{font-family:"Barlow";font-style:normal;font-weight:800;font-display:swap;src:url("../fonts/barlow-800.woff2") format("woff2")}
:root{--swat-font-head:"Barlow","Avenir Next","Segoe UI",Arial,sans-serif;--swat-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
body{font-family:var(--swat-font-body)}
h1,h2,h3,h4,h5,h6{font-family:var(--swat-font-head);overflow-wrap:break-word}
button,input,select,textarea,table{font-family:var(--swat-font-body)}
table,[class*="score"],[class*="time"]{font-variant-numeric:tabular-nums}
''')
print('/* Display/heading selectors -> Barlow */')
print(emit(head, '--swat-font-head'))
print('/* Buttons, tables, schedule/game UI, labels (display fonts replaced) -> Inter */')
print(emit(ui, '--swat-font-body'))
print('/* Selectors that used Work Sans or system stacks -> Inter */')
print(emit(body_sel, '--swat-font-body'))
print('''/* Size safety: the parent sized these display headings for condensed Oswald (and a tight
   -0.048em tracking). Barlow ExtraBold is wider, so on phones the longest words would overflow.
   Verified with a wider proxy font at 320-430px (tools/ + docs/TEST-REPORT.md). */
@media (max-width:620px){
.front-page-v2 .hero__headline{font-size:clamp(2.4rem,13vw,4.35rem);letter-spacing:-.02em}
}
@media (max-width:480px){
.swat-page-hero h1{font-size:clamp(2rem,10vw,3.55rem)!important}
.swat-tournament-hero h1{font-size:clamp(2.1rem,9.6vw,3.55rem)!important}
}''')
sys.stderr.write('head=%d ui=%d body=%d files=%d\n' % (len(head), len(ui), len(body_sel), len(files)))
