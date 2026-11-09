#!/usr/bin/env python3
"""Build tests/Fixtures/mini-kjv.osis.xml by slicing whole chapters out of the real source.

The chapters are copied byte-for-byte (with their book wrapper and main title), so tests exercise
the importer against genuine KJV markup. Each chapter is here for a reason:

  Gen.1, Gen.2      divineName/transChange basics ("These are", LORD), editorial titles, main title
  Ps.3, Ps.18, Ps.23  canonical psalm titles incl. small-caps LORD in a title; "The LORD is my shepherd"
  Matt.5, Matt.6    a discourse-level <q> wrapping per-verse <q> (nested quotes), Matt 6:10 "as it is in heaven"
  Matt.9            Matt 9:6 (quote interrupted by narration; the old regex hack)
  Matt.15-28 (selected)  quote boundaries aligned to the KJV red-letter text (Task 2.4); Matt 21:31 truncation
  Matt.26           Matt 26:56 truncation ("Then all the disciples forsook him, and fled.")
  Mark.2            Mark 2:10 truncation with a parenthesis
  John.3            a quote spanning 3:10-21 (John 3:16 is a continuation verse); self-closing <w/> on 3:15/3:16
  1John.4           numbered book

Usage: python3 -I tests/Fixtures/build_mini_kjv.py [source] [dest]
"""
import re
import sys

SRC = sys.argv[1] if len(sys.argv) > 1 else "assets/kjv.osis.xml"
DST = sys.argv[2] if len(sys.argv) > 2 else "tests/Fixtures/mini-kjv.osis.xml"
WANT = {
    "Gen": [1, 2], "Ps": [3, 18, 23],
    "Matt": [5, 6, 9, 15, 16, 17, 19, 20, 21, 22, 26, 28], "Mark": [2], "John": [3], "1John": [4],
}
OT = ["Gen", "Ps"]

s = open(SRC, encoding="utf-8").read()
head = s[: s.index("</header>") + len("</header>")]
out = [head, "\n"]


def book_parts(book):
    start = s.index(f'<div type="book" osisID="{book}"')
    first_ch = s.index("<chapter ", start)
    prelude = s[start:first_ch]  # <div type="book" ...>\n<title type="main">...</title>\n
    chapters = []
    for n in WANT[book]:
        a = s.index(f'<chapter osisID="{book}.{n}"', start)
        b = s.index("</chapter>", a) + len("</chapter>")
        chapters.append(s[a:b])
    return prelude, chapters


for group, subtype, books in (("OT", "x-OT", [b for b in WANT if b in OT]), ("NT", "x-NT", [b for b in WANT if b not in OT])):
    out.append(f'<div type="bookGroup" canonical="true" subType="{subtype}">\n')
    for book in books:
        prelude, chapters = book_parts(book)
        out.append(prelude)
        out.append("\n".join(chapters))
        out.append("\n</div>\n")
    out.append("</div>\n")

tail = s[s.rindex("</osisText>"):]
out.append(tail)
xml = "".join(out)
open(DST, "w", encoding="utf-8").write(xml)
print(f"wrote {DST}: {len(xml)} bytes, chapters={sum(len(v) for v in WANT.values())}, verses={len(re.findall(r'<verse [^>]*sID=', xml))}")
