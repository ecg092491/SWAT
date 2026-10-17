#!/usr/bin/env python3
"""Static guard: warn if the parent builds asset URLs in a way the child's
stylesheet_directory_uri filter does not cover, and that the child itself never
uses get_stylesheet_directory_uri() for its own assets.
Usage: tools/asset-url-audit.py /path/to/parent /path/to/child"""
import re, sys, glob
parent, child = sys.argv[1], sys.argv[2]
bad = 0
for f in glob.glob(parent + '/**/*.php', recursive=True):
    if '_deprecated' in f: continue
    t = open(f, encoding='utf-8').read()
    for pat in (r'get_theme_file_uri\(', r'get_stylesheet_uri\(', r'get_template_directory_uri\(', r'get_stylesheet_directory\(\)'):
        for m in re.finditer(pat, t):
            print(f'NOTE parent {f.replace(parent,"")}: {pat} (check it resolves correctly under a child)'); 
sdu = sum(open(f, encoding='utf-8').read().count('get_stylesheet_directory_uri()') for f in glob.glob(parent + '/**/*.php', recursive=True) if '_deprecated' not in f)
print(f'parent calls to get_stylesheet_directory_uri(): {sdu} (covered by the child filter)')
for f in (g for g in glob.glob(child + '/**/*.php', recursive=True) if '/tests/' not in g):
    t = open(f, encoding='utf-8').read()
    n = len(re.findall(r'get_stylesheet_directory_uri\(\)', t))
    allowed = 1 if f.endswith('/functions.php') else 0   # the single capture into SWAT_CHILD_URI
    # functions.php also contains the docblock mention; count code uses only
    code = re.sub(r'/\*.*?\*/', '', t, flags=re.S)
    n = len(re.findall(r'get_stylesheet_directory_uri\(\)', code))
    if n > allowed: print(f'FAIL child {f}: {n} use(s) of get_stylesheet_directory_uri() in code (use SWAT_CHILD_URI)'); bad += 1
print('child asset-URL audit:', 'FAIL' if bad else 'ok'); sys.exit(1 if bad else 0)
