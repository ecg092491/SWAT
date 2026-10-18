#!/usr/bin/env python3
"""Find unreadable text on REAL pages and name the CSS rule responsible.

  pip install playwright && playwright install chromium
  python3 tests/browser/contrast-audit.py https://STAGING [--auth user:pass]
        [--paths /gameday/ "/gameday/?view=pitching"] [--width 390] [--min 3.0] [--wait 4]

For every visible text element it computes the rendered text colour (incl.
-webkit-text-fill-color and opacity), composites the real background behind
it, and reports any element below the contrast threshold, together with matching
stylesheet rules / inline style that sets its colour (stylesheet URL, selector,
value, !important). Works on cross-origin-free pages (same host as the site).

Exit code 1 if anything is below the threshold or Game Day did not load.
Matching rules are candidates, not proof of the cascade winner. Use browser
Computed Styles to confirm specificity, inheritance and source order.
Limitations: backgrounds drawn by images/gradients are approximated by the
nearest solid colour behind the element; Follow Teams state lives in the
browser, so followed-team cards only appear if you add a team in the test
session (use --script to run extra JS before auditing, e.g. to click things).
"""
import argparse, json, sys
from urllib.parse import urljoin, urlsplit
from playwright.sync_api import sync_playwright

JS = r"""(minRatio) => {
  const parse = c => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null;
    const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return {r:p[0],g:p[1],b:p[2],a:p.length>3?p[3]:1}; };
  const lin = v => { v/=255; return v<=0.03928 ? v/12.92 : Math.pow((v+0.055)/1.055,2.4); };
  const lum = c => 0.2126*lin(c.r)+0.7152*lin(c.g)+0.0722*lin(c.b);
  const over = (fg,bg) => ({r:fg.r*fg.a+bg.r*(1-fg.a), g:fg.g*fg.a+bg.g*(1-fg.a), b:fg.b*fg.a+bg.b*(1-fg.a), a:1});
  const ratio = (a,b) => { const [x,y]=[lum(a),lum(b)].sort((p,q)=>q-p); return (x+0.05)/(y+0.05); };
  const effectiveBg = el => { const layers=[]; let n=el, approx=false;
    while (n && n.nodeType===1) { const cs=getComputedStyle(n); const bg=parse(cs.backgroundColor);
      if (cs.backgroundImage && cs.backgroundImage!=='none') approx = true;
      if (bg && bg.a>0) { layers.push(bg); if (bg.a>=1) break; } n=n.parentElement; }
    let base={r:255,g:255,b:255,a:1}; for (let i=layers.length-1;i>=0;i--) base=over(layers[i],base); return {bg:base, approx}; };
  const visible = el => { const r=el.getBoundingClientRect(), cs=getComputedStyle(el);
    return r.width>1 && r.height>1 && cs.visibility!=='hidden' && cs.display!=='none'; };
  const opacityOf = el => { let o=1; for (let n=el; n && n.nodeType===1; n=n.parentElement) o*=parseFloat(getComputedStyle(n).opacity||1); return o; };
  const sheets = [...document.styleSheets];
  const rulesFor = el => { const out=[];
    const walk = (rules, href) => { for (const r of rules) {
      if (r.type===1) { let ok=false; try { ok = el.matches(r.selectorText); } catch(e){}
        if (ok) { const s=r.style; for (const p of ['color','-webkit-text-fill-color']) { const v=s.getPropertyValue(p);
          if (v) out.push({sheet:(href||'inline').split('/').slice(-3).join('/'), selector:r.selectorText.slice(0,140), prop:p, value:v, important:s.getPropertyPriority(p)==='important'}); } } }
      else if (r.cssRules && (r.type===4 || r.type===12)) { if (r.type===4 && !window.matchMedia(r.conditionText||r.media.mediaText).matches) continue; walk(r.cssRules, href); } } };
    for (const sh of sheets) { let rules; try { rules = sh.cssRules; } catch(e) { continue; } walk(rules, sh.href); }
    if (el.getAttribute('style') && /color/i.test(el.getAttribute('style'))) out.push({sheet:'(inline style attribute)', selector:'', prop:'style', value:el.getAttribute('style').slice(0,120), important:false});
    return out; };
  // If nothing sets colour on the element itself, find the nearest ancestor that does (that is where it is inherited from).
  const withInherit = el => { let r = rulesFor(el); if (r.length) return r;
    for (let n = el.parentElement; n && n !== document.documentElement; n = n.parentElement) { const rr = rulesFor(n);
      if (rr.length) { const who = n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (n.className ? '.' + String(n.className).trim().replace(/\s+/g, '.').slice(0, 50) : '');
        return rr.map(x => ({...x, selector: '[inherited from ' + who + '] ' + x.selector})); } }
    return []; };
  const bad=[]; const seen=new Set();
  const walker=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) { const t=walker.currentNode; const txt=t.textContent.trim(); if (txt.length<2) continue;
    const el=t.parentElement; if (!el || seen.has(el) || !visible(el) || ['SCRIPT','STYLE','NOSCRIPT','OPTION'].includes(el.tagName)) continue; seen.add(el);
    const cs=getComputedStyle(el); let fg=parse(cs.color); const fill=parse(getComputedStyle(el).webkitTextFillColor||''); if (fill && (fill.r!==fg.r||fill.g!==fg.g||fill.b!==fg.b||fill.a!==fg.a)) fg=fill;
    if (!fg || fg.a===0) { bad.push({text:txt.slice(0,50), tag:el.tagName.toLowerCase()+'.'+String(el.className).replace(/\s+/g,'.').slice(0,60), ratio:0, color:cs.color, bg:'?', note:'text is fully transparent', rules:withInherit(el)}); continue; }
    const {bg,approx}=effectiveBg(el); const o=opacityOf(el); const eff=over({...fg,a:fg.a*o}, bg); const r=ratio(eff,bg);
    if (r<minRatio) bad.push({text:txt.slice(0,50), tag:el.tagName.toLowerCase()+'.'+String(el.className).replace(/\s+/g,'.').slice(0,60),
      ratio:+r.toFixed(2), color:`rgb(${Math.round(eff.r)},${Math.round(eff.g)},${Math.round(eff.b)})`, bg:`rgb(${Math.round(bg.r)},${Math.round(bg.g)},${Math.round(bg.b)})`+(approx?' (approx: image/gradient)':''), opacity:o<1?o:undefined, rules:withInherit(el)}); }
  return bad; }"""

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('base'); ap.add_argument('--auth', default='')
    ap.add_argument('--paths', nargs='*', default=['/gameday/', '/gameday/?view=schedule', '/gameday/?view=pitching', '/gameday/?view=standings'])
    ap.add_argument('--width', type=int, default=390); ap.add_argument('--min', type=float, default=4.5)
    ap.add_argument('--wait', type=float, default=4.0, help='seconds to let the JS app render')
    ap.add_argument('--script', default='', help='JS to run before auditing (e.g. click to follow a team)')
    a = ap.parse_args()
    parts = urlsplit(a.base)
    if parts.scheme not in ('http', 'https') or not parts.netloc: ap.error('base must be an http(s) URL')
    base = a.base.rstrip('/') + '/'
    total = 0
    with sync_playwright() as pw:
        b = pw.chromium.launch()
        for path in a.paths:
            args = {'viewport': {'width': a.width, 'height': 900}}
            if a.auth:
                u, p = a.auth.split(':', 1); args['http_credentials'] = {'username': u, 'password': p}
            ctx = b.new_context(**args); pg = ctx.new_page()
            try:
                pg.goto(urljoin(base, path.lstrip('/')), wait_until='load', timeout=45000)
            except Exception as e:
                print(f'FAIL {path}: {e}'); total += 1; ctx.close(); continue
            pg.wait_for_timeout(int(a.wait * 1000))
            if a.script: pg.evaluate(a.script); pg.wait_for_timeout(1500)
            if not pg.locator('[data-swat-app]').count():
                print(f'FAIL {path}: Game Day did not load; title={pg.title()!r}, URL={pg.url}. Coming Soon/login/error pages are not a passing audit.')
                total += 1
                ctx.close()
                continue
            bad = pg.evaluate(JS, a.min)
            print(f'\n== {path} @ {a.width}px : {len(bad)} element(s) below {a.min}:1')
            for x in bad[:40]:
                print(f"  {x['ratio']:>5}:1  {x['tag'][:48]:48s} \"{x['text']}\"  text={x['color']} on {x['bg']}" + (f"  opacity={x['opacity']}" if x.get('opacity') else ''))
                for r in x['rules'][:6]:
                    print(f"           <- {r['prop']}: {r['value']}{' !important' if r['important'] else ''}   {r['sheet']}   {r['selector']}")
                if not x['rules']: print('           <- no rule in readable stylesheets sets colour here: it is INHERITED (check the ancestors) or comes from a cross-origin sheet')
            total += len(bad)
            ctx.close()
        b.close()
    print('\nTOTAL below threshold:', total)
    sys.exit(1 if total else 0)

if __name__ == '__main__':
    main()
