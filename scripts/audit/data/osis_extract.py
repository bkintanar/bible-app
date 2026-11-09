"""Stream an OSIS file (milestone verses) into a scratch SQLite for comparison.

Usage: python3 -I osis_extract.py <osis.xml> <out.sqlite>

Read-only with respect to the source; writes only <out.sqlite>.
Tables:
  x_verses(osis_id, book, chap, vnum, text, note_text, jesus_text, n_q_jesus_start,
           n_q_jesus_ms, n_trans, n_divine, n_divine_in_note, n_note, n_w_strong,
           n_pilcrow, n_title_inside)
  x_titles(seq, book, chapter, type, canonical, canonical_raw, inside_verse,
           anchor_verse, prev_verse, parent, text)
  x_chapters(osis_id, book, chap)
  x_q(seq, verse, who, kind, sid, eid, text)  -- every <q> start
"""
import sqlite3
import sys
import xml.sax


class H(xml.sax.ContentHandler):
    def __init__(self):
        super().__init__()
        self.book = None
        self.chapter = None
        self.verse = None  # open verse osisID
        self.last_verse = None
        self.note_depth = 0
        self.title_stack = []  # list of dicts for open titles
        self.pending_titles = []  # titles waiting for next verse sID
        self.q_stack = []  # open container q: who
        self.q_ms_open = {}  # sID -> who for milestone q
        self.stack = []  # element names
        self.verses = {}
        self.order = []
        self.titles = []
        self.chapters = []
        self.qs = []
        self.tseq = 0
        self.qseq = 0

    def _v(self):
        return self.verses[self.verse]

    def jesus_active(self):
        if any(w == 'Jesus' for w in self.q_stack):
            return True
        return any(w == 'Jesus' for w in self.q_ms_open.values())

    def startElement(self, name, attrs):
        parent = self.stack[-1] if self.stack else None
        self.stack.append(name)
        if name == 'div' and attrs.get('type') == 'book':
            self.book = attrs.get('osisID')
        elif name == 'chapter' and attrs.get('osisID'):
            self.chapter = attrs.get('osisID')
            self.chapters.append((self.chapter, self.book, int(self.chapter.split('.')[1])))
        elif name == 'verse':
            sid, eid = attrs.get('sID'), attrs.get('eID')
            if sid is not None and attrs.get('osisID'):
                oid = attrs.get('osisID')
                self.verse = oid
                p = oid.split('.')
                self.verses[oid] = dict(osis_id=oid, book=p[0], chap=int(p[1]), vnum=int(p[2]),
                                        text=[], note_text=[], jesus_text=[], n_q_jesus_start=0,
                                        n_q_jesus_ms=0, n_trans=0, n_divine=0, n_divine_in_note=0,
                                        n_note=0, n_w_strong=0, n_pilcrow=0, n_title_inside=0)
                self.order.append(oid)
                for t in self.pending_titles:
                    t['anchor_verse'] = oid
                self.pending_titles = []
            elif eid is not None and eid == self.verse:
                self.last_verse = self.verse
                self.verse = None
        elif name == 'title':
            self.tseq += 1
            t = dict(seq=self.tseq, book=self.book, chapter=self.chapter, type=attrs.get('type') or '',
                     canonical_raw=attrs.get('canonical'), inside_verse=self.verse,
                     anchor_verse=None, prev_verse=self.last_verse, parent=parent, text=[])
            self.title_stack.append(t)
            if self.verse:
                self._v()['n_title_inside'] += 1
        elif name == 'note':
            self.note_depth += 1
            if self.verse:
                self._v()['n_note'] += 1
        elif name == 'q':
            who = attrs.get('who')
            sid, eid = attrs.get('sID'), attrs.get('eID')
            kind = 'ms_start' if sid else ('ms_end' if eid else 'container')
            self.qseq += 1
            q = dict(seq=self.qseq, verse=self.verse, who=who, kind=kind, sid=sid, eid=eid, text=[])
            self.qs.append(q)
            if kind == 'container':
                self.q_stack.append(who)
                if self.verse and who == 'Jesus' and not self.note_depth:
                    self._v()['n_q_jesus_start'] += 1
            elif kind == 'ms_start':
                self.q_ms_open[sid] = who
                if self.verse and who == 'Jesus':
                    self._v()['n_q_jesus_ms'] += 1
            else:
                self.q_ms_open.pop(eid, None)
        elif name == 'transChange':
            if self.verse and not self.note_depth:
                self._v()['n_trans'] += 1
        elif name == 'divineName':
            if self.verse:
                self._v()['n_divine'] += 1
                if self.note_depth:
                    self._v()['n_divine_in_note'] += 1
        elif name == 'w':
            if self.verse and not self.note_depth and 'strong:' in (attrs.get('lemma') or ''):
                self._v()['n_w_strong'] += 1
        elif name == 'milestone':
            if self.verse and attrs.get('type') == 'x-p':
                self._v()['n_pilcrow'] += 1

    def endElement(self, name):
        self.stack.pop()
        if name == 'title':
            t = self.title_stack.pop()
            t['text'] = ''.join(t['text'])
            self.titles.append(t)
            if t['inside_verse'] is None:
                self.pending_titles.append(t)
        elif name == 'note':
            self.note_depth -= 1
        elif name == 'q':
            if self.q_stack:
                self.q_stack.pop()
        elif name == 'chapter':
            # titles still pending at chapter end have no following verse
            self.pending_titles = []

    def characters(self, content):
        if self.title_stack:
            if not self.note_depth:
                for t in self.title_stack:
                    t['text'].append(content)
            return
        if not self.verse:
            return
        v = self._v()
        if self.note_depth:
            v['note_text'].append(content)
            return
        v['text'].append(content)
        if self.jesus_active():
            v['jesus_text'].append(content)


def norm(s):
    return ' '.join(s.split())


def main(src, out):
    h = H()
    p = xml.sax.make_parser()
    p.setFeature(xml.sax.handler.feature_namespaces, False)
    p.setFeature(xml.sax.handler.feature_external_ges, False)
    p.setContentHandler(h)
    p.parse(src)

    db = sqlite3.connect(out)
    db.executescript('''
    drop table if exists x_verses; drop table if exists x_titles; drop table if exists x_chapters; drop table if exists x_q;
    create table x_verses(osis_id text primary key, book text, chap int, vnum int, text text, note_text text,
      jesus_text text, n_q_jesus_start int, n_q_jesus_ms int, n_trans int, n_divine int, n_divine_in_note int,
      n_note int, n_w_strong int, n_pilcrow int, n_title_inside int);
    create table x_titles(seq int primary key, book text, chapter text, type text, canonical int, canonical_raw text,
      inside_verse text, anchor_verse text, prev_verse text, parent text, text text);
    create table x_chapters(osis_id text primary key, book text, chap int);
    create table x_q(seq int primary key, verse text, who text, kind text, sid text, eid text);
    ''')
    rows = []
    for oid in h.order:
        v = h.verses[oid]
        rows.append((oid, v['book'], v['chap'], v['vnum'], norm(''.join(v['text'])), norm(''.join(v['note_text'])),
                     norm(''.join(v['jesus_text'])), v['n_q_jesus_start'], v['n_q_jesus_ms'], v['n_trans'],
                     v['n_divine'], v['n_divine_in_note'], v['n_note'], v['n_w_strong'], v['n_pilcrow'],
                     v['n_title_inside']))
    db.executemany('insert into x_verses values (%s)' % ','.join('?' * 16), rows)
    db.executemany('insert into x_titles values (?,?,?,?,?,?,?,?,?,?,?)', [
        (t['seq'], t['book'], t['chapter'], t['type'], 1 if t['canonical_raw'] == 'true' else 0, t['canonical_raw'],
         t['inside_verse'], t['anchor_verse'], t['prev_verse'], t['parent'], norm(t['text'])) for t in h.titles])
    db.executemany('insert or ignore into x_chapters values (?,?,?)', h.chapters)
    db.executemany('insert into x_q values (?,?,?,?,?,?)', [
        (q['seq'], q['verse'], q['who'], q['kind'], q['sid'], q['eid']) for q in h.qs])
    db.commit()
    print('verses', len(h.order), 'titles', len(h.titles), 'chapters', len(h.chapters), 'q', len(h.qs))


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
