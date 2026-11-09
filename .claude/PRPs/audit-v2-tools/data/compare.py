"""Compare the projected DB against the XML extraction.

Usage: python3 -I compare.py <xml_extract.sqlite> <db_snapshot.sqlite> [version_id]
Opens the snapshot read-only (mode=ro). Prints a report; writes nothing.
"""
import html
import re
import sqlite3
import sys
from collections import Counter, defaultdict

xml_path, db_path = sys.argv[1], sys.argv[2]
version_id = int(sys.argv[3]) if len(sys.argv) > 3 else 1

con = sqlite3.connect(f'file:{xml_path}?mode=ro', uri=True)
con.execute(f"attach 'file:{db_path}?mode=ro' as db")
q = con.execute

TAG = re.compile(r'<[^>]+>')


def strip(s):
    return ' '.join(html.unescape(TAG.sub('', s or '')).split())


def tight(s):
    """Whitespace-insensitive, punctuation-spacing-insensitive form."""
    return re.sub(r'\s+', '', s or '')


print('== 1. Canonical structure')
print('xml books/chapters/verses:', q('select count(distinct book), count(distinct book||chap), count(*) from x_verses').fetchone())
print('db  books/chapters/verses:', q('''select count(distinct b.id), count(distinct c.id), count(v.id) from db.verses v
    join db.chapters c on c.id=v.chapter_id join db.books b on b.id=c.book_id where c.version_id=?''', (version_id,)).fetchone())
xc = dict(q('select book||"."||chap, count(*) from x_verses group by 1').fetchall())
dc = dict(q('''select b.osis_id||"."||c.chapter_number, count(v.id) from db.chapters c join db.books b on b.id=c.book_id
    left join db.verses v on v.chapter_id=c.id where c.version_id=? group by 1''', (version_id,)).fetchall())
bad = [(k, xc.get(k), dc.get(k)) for k in set(xc) | set(dc) if xc.get(k) != dc.get(k)]
print('chapters whose verse count differs xml vs db:', len(bad), bad[:10])
xv = set(r[0] for r in q('select osis_id from x_verses'))
dv = set(r[0] for r in q('select v.osis_id from db.verses v join db.chapters c on c.id=v.chapter_id where c.version_id=?', (version_id,)))
print('verses in xml not db:', len(xv - dv), sorted(xv - dv)[:10])
print('verses in db not xml:', len(dv - xv), sorted(dv - xv)[:10])

print('\n== 2. Verse text hygiene (db.verses.text)')
for label, cond in [
    ('empty/whitespace-only', "trim(text)=''"),
    ("contains '<'", "text like '%<%'"),
    ("contains '>'", "text like '%>%'"),
    ("contains '&' entity", "text like '%&%;%'"),
    ("contains '&'", "text like '%&%'"),
    ('double space', "text like '%  %'"),
    ('leading/trailing ws', "text<>trim(text)"),
    ('tab/newline', "text like '%'||char(9)||'%' or text like '%'||char(10)||'%'"),
    ('contains pilcrow', "text like '%¶%'"),
    ('space before punctuation', "text glob '* [,;:.?!]*'"),
    ("formatted_text contains raw '<q'", "formatted_text like '%<q %' or formatted_text like '%<q>%'"),
    ("formatted_text contains xmlns", "formatted_text like '%xmlns%'"),
    ("formatted_text contains raw OSIS tag", "formatted_text like '%<w %' or formatted_text like '%<transChange%' or formatted_text like '%<divineName%' or formatted_text like '%<note%' or formatted_text like '%<milestone%' or formatted_text like '%<verse %' or formatted_text like '%<seg%' or formatted_text like '%<foreign%' or formatted_text like '%<inscription%'"),
    ('original_xml null/empty', "original_xml is null or original_xml=''"),
]:
    n = q(f'select count(*) from db.verses where {cond}').fetchone()[0]
    ex = [r[0] for r in q(f'select osis_id from db.verses where {cond} limit 5')]
    print(f'  {label:42s} {n:6d} {ex}')

print('\n== 3. Verse text vs XML (notes excluded in XML text)')
rows = q('select x.osis_id, x.text, x.note_text, v.text from x_verses x join db.verses v on v.osis_id=x.osis_id '
         'join db.chapters c on c.id=v.chapter_id where c.version_id=?', (version_id,)).fetchall()
cat = Counter()
examples = defaultdict(list)
for oid, xt, xn, dt in rows:
    if xt == dt:
        cat['exact'] += 1
        continue
    if tight(xt) == tight(dt):
        k = 'whitespace-only diff'
    elif xn and tight(dt) != tight(xt) and tight(xn) and tight(xn) in tight(dt):
        k = 'db text CONTAINS note text'
    elif len(tight(dt)) < len(tight(xt)) * 0.8:
        k = 'db text TRUNCATED (<80% of xml)'
    else:
        k = 'other content diff'
    cat[k] += 1
    if len(examples[k]) < 4:
        examples[k].append((oid, xt[:140], dt[:140]))
for k, n in cat.most_common():
    print(f'  {k:40s} {n}')
for k, ex in examples.items():
    print('  --', k)
    for e in ex:
        print('    ', e[0], '\n       xml:', e[1], '\n       db :', e[2])

# run-together words: whitespace diff where db removed a space between two letters
rt = 0
rt_ex = []
for oid, xt, xn, dt in rows:
    if xt != dt and tight(xt) == tight(dt):
        xw = xt.split()
        dw = dt.split()
        if len(dw) < len(xw):
            rt += 1
            if len(rt_ex) < 6:
                rt_ex.append((oid, sorted(set(dw) - set(xw))[:4]))
print('  verses with FEWER words in db than xml (run-together):', rt, rt_ex)
ex_more = 0
ex_more_l = []
for oid, xt, xn, dt in rows:
    if xt != dt and tight(xt) == tight(dt) and len(dt.split()) > len(xt.split()):
        ex_more += 1
        if len(ex_more_l) < 6:
            ex_more_l.append((oid, sorted(set(dw for dw in dt.split()) - set(xt.split()))[:4]))
print('  verses with MORE tokens in db than xml (split words / spaced punctuation):', ex_more, ex_more_l)

print('\n== 4. Titles')
xt_rows = q("select seq, book, chapter, type, canonical, anchor_verse, text from x_titles where parent<>'work'").fetchall()
first_ch = dict(q('select b.osis_id, c.id from db.chapters c join db.books b on b.id=c.book_id '
                  'where c.version_id=? and c.chapter_number=1', (version_id,)).fetchall())
db_titles = q('''select t.id, t.title_type, t.canonical, t.title_text, v.osis_id, c.osis_id, t.title_order
    from db.titles t left join db.verses v on v.id=t.verse_id left join db.chapters c on c.id=t.chapter_id''').fetchall()
dkey = Counter()
dmap = defaultdict(list)
for tid, tt, can, txt, vo, co, order in db_titles:
    anchor = vo or co
    k = (tt, anchor, strip(txt))
    dkey[k] += 1
    dmap[k].append((tid, can))
xkey = Counter()
xcan = {}
for seq, book, chapter, tt, can, av, txt in xt_rows:
    if tt == 'main':
        anchor = f'{book}.1'
    elif tt == 'chapter':
        anchor = chapter
    else:
        anchor = av
    k = (tt, anchor, txt)
    xkey[k] += 1
    xcan[k] = can
missing = xkey - dkey
extra = dkey - xkey
print('  xml scripture titles:', sum(xkey.values()), ' db titles:', sum(dkey.values()))
print('  missing from db:', sum(missing.values()), list(missing.items())[:8])
print('  extra in db    :', sum(extra.values()), list(extra.items())[:8])
wrong_can = [(k, xcan[k], [c for _, c in dmap[k]]) for k in xkey if k in dmap and any(c != xcan[k] for _, c in dmap[k])]
print('  wrong canonical flag:', len(wrong_can), wrong_can[:5])
print('  by type xml:', Counter(k[0] for k in xkey.elements()), ' db:', Counter(k[0] for k in dkey.elements()))

print('\n== 5. Red letters')
x_q = dict(q('select osis_id, n_q_jesus_start from x_verses where n_q_jesus_start>0').fetchall())
x_j = dict(q("select osis_id, jesus_text from x_verses where jesus_text<>''").fetchall())
d_r = dict(q('''select v.osis_id, count(*) from db.red_letter_text r join db.verses v on v.id=r.verse_id
    join db.chapters c on c.id=v.chapter_id where c.version_id=? group by 1''', (version_id,)).fetchall())
print('  xml <q who=Jesus> starts:', sum(x_q.values()), 'in', len(x_q), 'verses')
print('  xml verses containing words of Christ (incl. continuation):', len(x_j))
print('  db red_letter_text rows:', sum(d_r.values()), 'in', len(d_r), 'verses')
fp = {k: v for k, v in d_r.items() if k not in x_q}
fn = {k: v for k, v in x_q.items() if d_r.get(k, 0) < v}
over = {k: (x_q[k], v) for k, v in d_r.items() if k in x_q and v > x_q[k]}
print('  FALSE POSITIVE verses (db rows, no <q> start in xml):', len(fp), list(fp.items())[:10])
print('  verses with fewer db rows than <q> starts:', len(fn), [(k, v, d_r.get(k, 0)) for k, v in list(fn.items())[:10]])
print('  verses with more db rows than <q> starts:', len(over), list(over.items())[:10])
cont = [k for k in x_j if k not in x_q]
print('  continuation verses (Christ speaking, no <q> start in verse) => no red row possible:', len(cont), cont[:8])
# text check of red rows
mism = 0
mex = []
for oid, txt, jt in q('''select v.osis_id, group_concat(r.text_content, ' '), x.jesus_text from db.red_letter_text r
        join db.verses v on v.id=r.verse_id join x_verses x on x.osis_id=v.osis_id group by v.id''').fetchall():
    if tight(strip(txt)) != tight(jt):
        mism += 1
        if len(mex) < 5:
            mex.append((oid, strip(txt)[:90], jt[:90]))
print('  red rows whose text != xml Jesus text of verse:', mism, mex)
fmt_red = q("select count(*) from db.verses where formatted_text like '%text-red-600%'").fetchone()[0]
print('  db verses whose formatted_text carries red span:', fmt_red)
red_no_q = q("""select count(*), group_concat(osis_id) from (select v.osis_id from db.verses v left join x_verses x on x.osis_id=v.osis_id
    where v.formatted_text like '%text-red-600%' and coalesce(x.jesus_text,'')='' limit 1000)""").fetchone()
print('  red formatted_text but no words of Christ in xml:', red_no_q[0], (red_no_q[1] or '')[:200])
q_no_red = q("""select count(*), group_concat(osis_id) from (select x.osis_id from x_verses x join db.verses v on v.osis_id=x.osis_id
    where x.n_q_jesus_start>0 and v.formatted_text not like '%text-red-600%')""").fetchone()
print('  verses with <q> start but formatted_text NOT red:', q_no_red[0], (q_no_red[1] or '')[:200])

print('\n== 6. Element counts per verse (xml expectation vs db rows)')
for name, xcol, table in [('transChange', 'n_trans', 'translator_changes'), ('divineName', 'n_divine', 'divine_names'),
                          ('note', 'n_note', 'study_notes'), ('w strong', 'n_w_strong', 'word_elements')]:
    where = " where strongs_number is not null" if table == 'word_elements' else ''
    dd = dict(q(f'select v.osis_id, count(*) from db.{table} t join db.verses v on v.id=t.verse_id {where} group by 1').fetchall())
    xx = dict(q(f'select osis_id, {xcol} from x_verses where {xcol}>0').fetchall())
    diff = {k: (xx.get(k, 0), dd.get(k, 0)) for k in set(dd) | set(xx) if xx.get(k, 0) != dd.get(k, 0)}
    under = sum(max(0, a - b) for a, b in diff.values())
    overc = sum(max(0, b - a) for a, b in diff.values())
    print(f'  {name:12s} xml={sum(xx.values()):7d} db={sum(dd.values()):7d} verses_differing={len(diff):5d} '
          f'under={under} over={overc} ex={list(diff.items())[:6]}')
nw = q('select count(*) from db.word_elements where strongs_number is null').fetchone()[0]
print('  word_elements rows with NULL strongs_number:', nw)

print('\n== 7. Paragraphs')
xp = dict(q('select osis_id, n_pilcrow from x_verses where n_pilcrow>0').fetchall())
starts = set(r[0] for r in q('select v.osis_id from db.paragraphs p join db.verses v on v.id=p.start_verse_id'))
expected_starts = set(xp) | set(r[0] for r in q('select osis_id from x_verses where vnum=1'))
print('  xml pilcrow verses:', len(xp), ' expected paragraph starts (pilcrow or v1):', len(expected_starts), ' db paragraph starts:', len(starts))
print('  starts missing in db:', len(expected_starts - starts), sorted(expected_starts - starts)[:8])
print('  db starts not in xml:', len(starts - expected_starts), sorted(starts - expected_starts)[:8])
# coverage: every verse covered exactly once
cov = Counter()
for ch, s, e in q('''select p.chapter_id, sv.verse_number, coalesce(ev.verse_number, sv.verse_number) from db.paragraphs p
        join db.verses sv on sv.id=p.start_verse_id left join db.verses ev on ev.id=p.end_verse_id''').fetchall():
    for n in range(s, e + 1):
        cov[(ch, n)] += 1
allv = set((r[0], r[1]) for r in q('select chapter_id, verse_number from db.verses'))
print('  verses not in any paragraph:', len(allv - set(cov)), ' verses in >1 paragraph:', sum(1 for k, n in cov.items() if n > 1))
stale = q('''select count(*) from db.paragraphs p where p.text_content <> (select group_concat(formatted_text, ' ') from
    (select v.formatted_text from db.verses v join db.verses sv on sv.id=p.start_verse_id
     left join db.verses ev on ev.id=p.end_verse_id where v.chapter_id=p.chapter_id
     and v.verse_number between sv.verse_number and coalesce(ev.verse_number, sv.verse_number) order by v.verse_number))''').fetchone()[0]
print('  paragraphs whose text_content != concat(current formatted_text):', stale)
