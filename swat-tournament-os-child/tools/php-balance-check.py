#!/usr/bin/env python3
"""NOT a PHP linter. Sanity check only: tracks HTML/PHP mode, skips strings and
comments, and verifies (), [], {} balance plus alt-syntax if/foreach/while/for
vs end* pairing. Use `php -l` for real syntax validation."""
import re, sys

def check(path):
    s = open(path, encoding='utf-8').read()
    i, n, php = 0, len(s), False
    stack, errs, line = [], [], 1
    pairs = {')': '(', ']': '[', '}': '{'}
    alt_open = alt_close = 0
    while i < n:
        if not php:
            j = s.find('<?', i)
            if j < 0:
                break
            line += s.count('\n', i, j)
            i = j + (5 if s.startswith('<?php', j) else (3 if s.startswith('<?=', j) else 2))
            php = True
            continue
        c = s[i]
        if c == '\n':
            line += 1
        if s.startswith('?>', i):
            php = False; i += 2; continue
        if s.startswith('//', i) or c == '#':
            m = re.compile(r'[^\n]*?(?=\?>|\n|$)').match(s, i); i = m.end(); continue
        if s.startswith('/*', i):
            k = s.find('*/', i + 2); line += s.count('\n', i, k); i = k + 2; continue
        if c in '\'"':
            q, k = c, i + 1
            while k < n and s[k] != q:
                k += 2 if s[k] == '\\' else 1
            line += s.count('\n', i, k); i = k + 1; continue
        if c in '([{':
            stack.append((c, line))
        elif c in ')]}':
            if not stack or stack[-1][0] != pairs[c]:
                errs.append(f'{path}:{line}: unexpected {c}'); stack = []
            else:
                stack.pop()
        i += 1
    for ch, ln in stack:
        errs.append(f'{path}:{ln}: unclosed {ch}')
    code = re.sub(r"'(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\"", '""', s)
    code = re.sub(r'/\*.*?\*/', '', code, flags=re.S)
    code = re.sub(r'(?m)(?<!:)//[^\n]*', '', code)
    alt_open = 0
    for m in re.finditer(r'\b(?:if|elseif|foreach|for|while|switch)\s*\(', code):
        if m.group(0).startswith('elseif'):
            continue
        depth, k = 1, m.end()
        while k < len(code) and depth:
            depth += (code[k] == '(') - (code[k] == ')')
            k += 1
        rest = code[k:k + 4].lstrip()
        if rest.startswith(':') and not rest.startswith('::'):
            alt_open += 1
    alt_close = len(re.findall(r'\b(?:endif|endforeach|endfor|endwhile|endswitch)\s*;', code))
    if alt_open != alt_close:
        errs.append(f'{path}: alt-syntax open={alt_open} close={alt_close}')
    return errs

bad = 0
for p in sys.argv[1:]:
    e = check(p)
    bad += len(e)
    print(('OK   ' if not e else 'FAIL ') + p)
    for x in e:
        print('   ', x)
sys.exit(1 if bad else 0)
