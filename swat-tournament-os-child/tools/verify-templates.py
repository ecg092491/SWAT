#!/usr/bin/env python3
"""Check the generated templates (front-page.php, archive-tournament.php and single-tournament.php are hand-maintained since 1.0.19) against the supplied parent without rewriting them."""
from pathlib import Path
import subprocess
import sys
import tempfile

parent, child = map(Path, sys.argv[1:3])
with tempfile.TemporaryDirectory() as generated:
    subprocess.run([sys.executable, str(child / 'tools/build-templates.py'), str(parent), generated], check=True)
    mismatches = []
    for name in ('header.php',):
        if (Path(generated) / name).read_bytes() != (child / name).read_bytes():
            mismatches.append(name)
    if mismatches:
        sys.exit('FAIL generated templates differ: ' + ', '.join(mismatches))
print('all generated templates identical')
