"""List verses whose stored text/formatted_text lost scripture words vs the XML (notes excluded).
Usage: python3 -I truncation.py <xml_extract.sqlite> <db_snapshot.sqlite>"""
import re, sys, html, sqlite3
con = sqlite3.connect(f'file:{sys.argv[1]}?mode=ro', uri=True)
con.execute(f"attach 'file:{sys.argv[2]}?mode=ro' as db")
TAG = re.compile(r'<[^>]+>')
def words(s): return re.findall(r"[A-Za-z]+", s or '')
out = []
for oid, xt, xn, dt, ft in con.execute('select x.osis_id, x.text, x.note_text, v.text, v.formatted_text from x_verses x join db.verses v on v.osis_id=x.osis_id'):
    # remove note text from db text (db text = scripture + note text appended, no separator)
    xw = words(xt); dw = words(dt); fw = words(html.unescape(TAG.sub(' ', re.sub(r'<note.*?</note>', '', ft or '', flags=re.S))))
    # db text must start with all xml words in order
    if dw[:len(xw)] != xw:
        k = 0
        while k < min(len(xw), len(dw)) and xw[k] == dw[k]: k += 1
        missing = len(xw) - k
        fmiss = 0 if fw[:len(xw)] == xw else len(xw) - next((i for i in range(min(len(xw), len(fw))) if xw[i] != fw[i]), min(len(xw), len(fw)))
        out.append((oid, len(xw), missing, fmiss, ' '.join(xw[k:k+12])))
print('verses whose db.text does not begin with the full xml scripture text:', len(out))
for r in out: print('  %-12s xml_words=%-3d text_missing=%-3d formatted_missing=%-3d lost: %s' % r)
