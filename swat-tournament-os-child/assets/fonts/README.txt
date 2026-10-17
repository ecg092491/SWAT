SWAT Tournament OS Child — bundled local fonts

Included: inter-variable.woff2 (normal, weights 100–900; optical size axis retained),
barlow-700.woff2 and barlow-800.woff2 (normal, Latin subset).
Sources: https://github.com/google/fonts/tree/main/ofl/inter
and https://github.com/google/fonts/tree/main/ofl/barlow
Downloaded 2026-10-01. Converted to WOFF2 with fontTools; Barlow subset to Latin.
Licenses are included as Inter-OFL.txt and Barlow-OFL.txt.
The child retains parent fonts only if any required bundled file is removed.

Size recommendation (not yet applied)
-------------------------------------
inter-variable.woff2 is ~350 KB because it carries the full glyph set and the optical-size axis.
It is preloaded on every page, which is heavy on phones. Subsetting to Latin is expected to cut
it substantially (measure the result; I could not run it in my sandbox). In an environment with
fonttools + brotli, starting from the upstream InterVariable.ttf:

  pip install fonttools brotli
  pyftsubset InterVariable.ttf --unicodes="U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2190-2193,U+2212,U+2215,U+FEFF,U+FFFD" --layout-features="kern,liga,calt,tnum,case,ccmp,locl,mark,mkmk" --flavor=woff2 --output-file=inter-variable.woff2

Keep U+2190-2193 (arrows): the theme uses a right arrow in links. Barlow does not contain arrows;
they fall back to a system font if they ever appear inside a Barlow heading.
After replacing the file, re-run tests/browser/staging-check.py.
