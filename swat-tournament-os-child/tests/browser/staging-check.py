#!/usr/bin/env python3
"""Browser checks for REAL pages (run against staging).

  pip install playwright && playwright install chromium
  python3 tests/browser/staging-check.py https://STAGING-URL --event some-event-slug [--auth user:pass]

For each page at 320, 375, 390, 430, 768 and 1280 px it reports:
  - horizontal overflow
  - visible links/buttons smaller than 44x44 (inline text links are skipped)
  - heading words wider than their box (catches Barlow being wider than Oswald)
  - console errors and failed requests
  - whether Barlow/Inter actually loaded, and any request to fonts.googleapis.com
Exit code 1 if any issue is found. Screenshots go to ./staging-shots/.
"""
import argparse, os, sys
from urllib.parse import urljoin, urlsplit, quote
from playwright.sync_api import sync_playwright

WIDTHS = [320, 375, 390, 430, 768, 1280]

JS = r"""() => {
  const de = document.documentElement, vw = de.clientWidth, out = {};
  out.overflow = de.scrollWidth > vw ? de.scrollWidth + ' > ' + vw : '';
  const vis = e => { const r = e.getBoundingClientRect(), s = getComputedStyle(e);
    return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
  out.small = [...document.querySelectorAll('a.btn,a.swat-btn,button,summary,[role=tab],.swat-event-actions a,.tournament-row__actions a,nav a,.swat-bottom-nav a')]
    .filter(vis).filter(e => { const r = e.getBoundingClientRect(); return r.height < 43.5 || r.width < 43.5; })
    .slice(0, 8).map(e => (e.className || e.tagName) + ' ' + Math.round(e.getBoundingClientRect().width) + 'x' + Math.round(e.getBoundingClientRect().height));
  const probe = document.createElement('span'); document.body.appendChild(probe);
  out.words = [];
  document.querySelectorAll('h1,h2,h3,.hero__headline').forEach(el => {
    if (!vis(el)) return;
    const cs = getComputedStyle(el), avail = el.getBoundingClientRect().width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    probe.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap';
    ['fontFamily','fontSize','fontWeight','letterSpacing','textTransform'].forEach(p => probe.style[p] = cs[p]);
    (el.innerText || '').replace(/\s+/g, ' ').trim().split(' ').forEach(w => {
      probe.textContent = w; const ww = probe.getBoundingClientRect().width;
      if (ww > avail + 0.5) out.words.push((el.className || el.tagName) + ' "' + w + '" ' + Math.round(ww) + '>' + Math.round(avail));
    });
  });
  probe.remove();
  const loaded = (family, weight) => [...document.fonts].some(f =>
    f.family.replace(/["']/g, '').toLowerCase() === family.toLowerCase() &&
    f.status === 'loaded' && (f.weight === weight || (f.weight.includes(' ') &&
      Number(weight) >= Number(f.weight.split(' ')[0]) && Number(weight) <= Number(f.weight.split(' ')[1]))));
  out.fonts = { barlow700: loaded('Barlow', '700'), barlow800: loaded('Barlow', '800'), inter: loaded('Inter', '400') };
  return out;
}"""

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('base'); ap.add_argument('--event', default=''); ap.add_argument('--auth', default='')
    a = ap.parse_args()
    base_parts = urlsplit(a.base)
    if base_parts.scheme not in ('http', 'https') or not base_parts.netloc or base_parts.query or base_parts.fragment:
        ap.error('base must be an http(s) site URL without a query or fragment')
    base = a.base.rstrip('/') + '/'
    def site_url(path):
        return urljoin(base, path.lstrip('/'))
    def within_site(url):
        actual = urlsplit(url)
        root = urlsplit(base)
        return (actual.scheme, actual.netloc) == (root.scheme, root.netloc) and (actual.path == root.path.rstrip('/') or actual.path.startswith(root.path))
    if a.auth and ':' not in a.auth:
        ap.error('--auth must be user:pass')
    
    pages = ['/', '/tournaments/', '/gameday/', '/gameday/?view=standings']
    if a.event:
        pages += [f'/tournaments/{quote(a.event, safe="")}/', f'/gameday/?event={quote(a.event, safe="")}']
    os.makedirs('staging-shots', exist_ok=True)
    issues = 0
    with sync_playwright() as pw:
        b = pw.chromium.launch()
        for path in pages:
            for w in WIDTHS:
                ctx_args = {'viewport': {'width': w, 'height': 900}}
                if a.auth:
                    u, p = a.auth.split(':', 1); ctx_args['http_credentials'] = {'username': u, 'password': p}
                ctx = b.new_context(**ctx_args); pg = ctx.new_page()
                errs, failed, google, http_errors, font_responses = [], [], [], [], []
                pg.on('console', lambda m, errs=errs: errs.append(m.text[:120]) if m.type == 'error' else None)
                pg.on('requestfailed', lambda r, failed=failed: failed.append(r.url[:100]))
                pg.on('request', lambda r, google=google: google.append(r.url) if 'fonts.googleapis.com' in r.url or 'fonts.gstatic.com' in r.url else None)
                pg.on('pageerror', lambda e, errs=errs: errs.append(str(e)[:120]))
                pg.on('response', lambda r, http_errors=http_errors: http_errors.append(str(r.status) + ' ' + r.url[:150]) if r.status >= 400 else None)
                pg.on('response', lambda r, font_responses=font_responses: font_responses.append((r.url, r.status)) if '.woff2' in r.url else None)
                try:
                    response = pg.goto(site_url(path), wait_until='domcontentloaded', timeout=45000)
                    if not within_site(pg.url):
                        raise RuntimeError('redirect escaped site base: ' + pg.url)
                    if response is None or response.status >= 400:
                        raise RuntimeError('page HTTP status: ' + str(response.status if response else 'no response'))
                    pg.wait_for_load_state('load', timeout=30000)  # stylesheets (incl. async-loaded ones) must have declared the @font-face rules first
                    pg.evaluate('''async () => {
                      await Promise.all(['700 20px Barlow','800 20px Barlow','400 16px Inter'].map(spec => document.fonts.load(spec)));
                      await document.fonts.ready;
                    }''')
                except Exception as e:
                    print(f'FAIL {path}@{w}: load error {e}'); issues += 1; ctx.close(); continue
                pg.wait_for_load_state('load', timeout=15000)
                pg.wait_for_timeout(500)
                r = pg.evaluate(JS)
                problems = []
                if r['overflow']: problems.append('overflow ' + r['overflow'])
                if r['small']: problems.append('small targets ' + str(r['small']))
                if r['words']: problems.append('words overflow ' + str(r['words']))
                if errs: problems.append('console errors ' + str(errs[:3]))
                if failed: problems.append('failed requests ' + str(failed[:3]))
                if http_errors: problems.append('HTTP errors ' + str(http_errors[:5]))
                if google: problems.append('google fonts requested ' + str(google[:2]))
                if not all(r['fonts'].values()): problems.append('Barlow/Inter not loaded ' + str(r['fonts']))
                for filename in ('barlow-700.woff2', 'barlow-800.woff2', 'inter-variable.woff2'):
                    if not any(urlsplit(url).path.endswith('/' + filename) and 200 <= status < 300 for url, status in font_responses):
                        problems.append('font file not served successfully: ' + filename)
                issues += len(problems)
                print(('OK   ' if not problems else 'FAIL ') + f'{path}@{w}' + ('' if not problems else ' :: ' + ' | '.join(problems)))
                if w in (320, 390, 1280):
                    pg.screenshot(path=f'staging-shots/{path.strip("/").replace("/", "_").replace("?", "_").replace("=", "-") or "home"}-{w}.png', full_page=True)
                ctx.close()
        b.close()
    print('issues:', issues); sys.exit(1 if issues else 0)

if __name__ == "__main__":
    main()
