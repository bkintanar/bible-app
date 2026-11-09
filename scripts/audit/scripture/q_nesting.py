#!/usr/bin/env python3
"""Find suspicious <q who="Jesus"> nesting in the OSIS source (narrative text left inside an outer q).
Usage: python3 -I q_nesting.py assets/kjv.osis.xml
Prints verses where a Jesus-q is opened while another Jesus-q is still open, plus the depth-1 text that
follows the inner </q> inside the same verse (likely narration wrongly attributed to Christ)."""
import re, sys
tok = re.compile(r'<verse [^>]*sID="([^"]+)"[^>]*/>|<verse eID="[^"]+"/>|<q\b[^>]*>|</q>|<note\b.*?</note>|<[^>]+>|[^<]+', re.S)
depth = 0; cur = None; hits = {}; after_inner = None
data = open(sys.argv[1], encoding='utf-8').read()
for m in tok.finditer(data):
    s = m.group(0)
    if m.group(1):
        cur = m.group(1); after_inner = None; continue
    if s.startswith('<q'):
        if 'who="Jesus"' in s:
            depth += 1
            if depth >= 2:
                hits.setdefault(cur, [])
        else:
            depth += 0  # non-Jesus q ignored (none in KJV)
    elif s == '</q>':
        depth -= 1
        if depth >= 1 and cur in hits:
            after_inner = cur
        elif depth <= 0:
            after_inner = None  # the outer quote closed too: later text is not inside any quote
    elif not s.startswith('<') and depth >= 1 and after_inner == cur and cur in hits and s.strip():
        hits[cur].append(s.strip())
    elif s.startswith('<verse eID'):
        after_inner = None
print('verses where a Jesus q opens inside another:', len(hits))
sus = {k: ' '.join(v) for k, v in hits.items() if re.search(r'[A-Za-z]', ' '.join(v))}
print('of which have depth-1 text after the inner </q> (suspect narration inside outer q):', len(sus))
for k, v in list(sus.items())[:40]:
    print(' ', k, '|', v[:110])
