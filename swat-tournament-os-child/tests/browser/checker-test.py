#!/usr/bin/env python3
"""Regression checks against local browser fixtures, not SWAT staging.
Run: python3 tests/browser/checker-test.py (Playwright + Chromium required).
"""
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from pathlib import Path
import subprocess, sys, tempfile, threading

ROOT = Path(__file__).resolve().parents[2]
MODE = 'good'
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args): pass
    def do_GET(self):
        if not self.path.startswith('/staging/2948/'):
            self.send_response(404); self.end_headers(); return
        if self.path.endswith('.woff2'):
            file = ROOT / 'assets/fonts' / self.path.rsplit('/', 1)[1]
            data = file.read_bytes(); self.send_response(200); self.send_header('Content-Type','font/woff2'); self.end_headers(); self.wfile.write(data); return
        if self.path.endswith('/bad.js'):
            self.send_response(404); self.end_headers(); return
        if MODE == 'redirect':
            self.send_response(302); self.send_header('Location','/outside/'); self.end_headers(); return
        css = ''.join(f'@font-face{{font-family:"{family}";font-weight:{weight};src:url("/staging/2948/{filename}")}}' for family,weight,filename in [('Barlow','700','barlow-700.woff2'),('Barlow','800','barlow-800.woff2'),('Inter','100 900','inter-variable.woff2')])
        if MODE == 'missing': css = ''
        extra = '<script src="/staging/2948/bad.js"></script>' if MODE == 'http' else ''
        if MODE == 'js': extra = '<script>throw new Error("fixture uncaught exception")</script>'
        data = f'<html><head><style>{css} body{{font-family:Inter;margin:16px}} h1{{font-family:Barlow;font-size:24px;font-weight:800}} button{{width:100px;height:48px}}</style></head><body><h1>SWAT fixture</h1><button>Register</button>{extra}</body></html>'.encode()
        self.send_response(200); self.send_header('Content-Type','text/html'); self.end_headers(); self.wfile.write(data)

server = ThreadingHTTPServer(('127.0.0.1',0), Handler)
threading.Thread(target=server.serve_forever,daemon=True).start()
try:
    with tempfile.TemporaryDirectory() as work:
        for mode, expected in [('good','issues: 0'),('missing','Barlow/Inter not loaded'),('http','HTTP errors'),('redirect','redirect escaped site base'),('js','fixture uncaught exception')]:
            MODE = mode
            runner = 'import runpy; d=runpy.run_path(sys.argv[1]); d["main"].__globals__["WIDTHS"]=[320]; sys.argv=[sys.argv[1],sys.argv[2],"--event","real-event"]; d["main"]()'
            result = subprocess.run([sys.executable,'-c','import sys; '+runner,str(ROOT/'tests/browser/staging-check.py'),f'http://127.0.0.1:{server.server_port}/staging/2948'],capture_output=True,text=True,cwd=work,timeout=60)
            assert expected in result.stdout, (mode,result.stdout,result.stderr)
            assert result.returncode == (0 if mode=='good' else 1), (mode,result.returncode)
            print('PASS',mode)
finally:
    server.shutdown()
print('5 browser regression scenarios passed (320px local fixtures; not staging).')
