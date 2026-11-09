#!/usr/bin/env python3
"""Stream an OSIS file (milestone or contained verses) and emit per-verse ground truth.

Usage: python3 -I osis_truth.py assets/kjv.osis.xml out.jsonl

Per verse (JSON line):
  id, text (notes/titles excluded, ws-collapsed), red (text inside <q who="Jesus">),
  red_words, words, any_red, all_red, tc (transChange added count), dn (divineName count),
  w_selfclose, para (x-p milestone in span), para_added, note_text, titles_before [(type,canonical,text)],
  sid_parent, eid_parent, cross (sID/eID in different containers), q_open_at_start (verse begins inside a Jesus q)
Also emits records with kind=colophon / kind=title_chapter / kind=book.
"""
import json
import re
import sys
import xml.sax

NS = "http://www.bibletechnologies.net/2003/OSIS/namespace"


def norm(s):
    return re.sub(r"\s+", " ", s).strip()


class H(xml.sax.ContentHandler):
    def __init__(self, out):
        super().__init__()
        self.out = out
        self.stack = []  # (name, attrs)
        self.qstack = []  # who values
        self.note = 0
        self.title = 0
        self.tc = 0
        self.dn = 0
        self.colophon = 0
        self.verse = None  # current verse dict
        self.pending_titles = []
        self.cur_title = None
        self.cur_colophon = None
        self.chapter = None
        self.book = None
        self.w_text = None  # track text within w to detect self-closing/empty

    def startElementNS(self, name, qname, attrs):
        uri, local = name
        a = {k[1]: v for k, v in attrs.items()}
        self.stack.append((local, a))
        if local == "verse":
            if "sID" in a or ("osisID" in a and "eID" not in a and "sID" not in a):
                vid = a.get("osisID") or a.get("sID")
                self.verse = {
                    "id": vid,
                    "segs": [],
                    "tc": 0,
                    "dn": 0,
                    "w": 0,
                    "w_selfclose": 0,
                    "para": False,
                    "para_added": False,
                    "note_text": [],
                    "titles_before": self.pending_titles,
                    "sid_parent": self.stack[-2][0] if len(self.stack) > 1 else None,
                    "sid_depth": len(self.stack),
                    "q_open_at_start": "Jesus" in self.qstack,
                    "contained": "sID" not in a,
                    "inner_titles": [],
                }
                self.pending_titles = []
            elif "eID" in a and self.verse is not None:
                self.verse["eid_parent"] = self.stack[-2][0] if len(self.stack) > 1 else None
                self.verse["eid_depth"] = len(self.stack)
                self.flush()
        elif local == "q":
            self.qstack.append(a.get("who", ""))
        elif local == "note":
            self.note += 1
            if self.verse is not None:
                self.verse["note_text"].append("")
        elif local == "title":
            self.title += 1
            if self.title == 1:
                self.cur_title = {"type": a.get("type"), "canonical": a.get("canonical"), "text": ""}
        elif local == "transChange":
            self.tc += 1
            if self.verse is not None and not self.note and not self.title and a.get("type") == "added":
                self.verse["tc"] += 1
        elif local == "divineName":
            self.dn += 1
            if self.verse is not None and not self.note and not self.title:
                self.verse["dn"] += 1
        elif local == "milestone":
            if self.verse is not None and a.get("type") == "x-p":
                self.verse["para"] = True
                if a.get("subType") == "x-added":
                    self.verse["para_added"] = True
        elif local == "w":
            self.w_text = ""
            if self.verse is not None and not self.note and not self.title:
                self.verse["w"] += 1
        elif local == "div":
            if a.get("type") == "colophon":
                self.colophon += 1
                self.cur_colophon = {"kind": "colophon", "id": a.get("osisID"), "text": ""}
            elif a.get("type") == "book":
                self.book = a.get("osisID")
                self.out.write(json.dumps({"kind": "book", "id": self.book}) + "\n")
        elif local == "chapter":
            if "osisID" in a and "eID" not in a:
                self.chapter = a.get("osisID")
                self.out.write(json.dumps({"kind": "chapter", "id": self.chapter}) + "\n")

    def endElementNS(self, name, qname):
        local = name[1]
        self.stack.pop()
        if local == "q":
            self.qstack.pop()
        elif local == "note":
            self.note -= 1
        elif local == "title":
            self.title -= 1
            if self.title == 0 and self.cur_title is not None:
                t = self.cur_title
                t["text"] = norm(t["text"])
                if self.verse is not None:
                    self.verse["inner_titles"].append(t)
                else:
                    self.pending_titles.append(t)
                self.cur_title = None
        elif local == "transChange":
            self.tc -= 1
        elif local == "divineName":
            self.dn -= 1
        elif local == "w":
            if self.verse is not None and not self.note and not self.title and not self.w_text:
                self.verse["w_selfclose"] += 1
            self.w_text = None
        elif local == "div" and self.cur_colophon is not None and self.colophon:
            self.colophon -= 1
            if self.colophon == 0:
                self.cur_colophon["text"] = norm(self.cur_colophon["text"])
                self.out.write(json.dumps(self.cur_colophon) + "\n")
                self.cur_colophon = None
        elif local == "verse" and self.verse is not None and self.verse.get("contained"):
            self.flush()

    def characters(self, content):
        if self.w_text is not None:
            self.w_text += content
        if self.cur_title is not None and self.title and not self.note:
            self.cur_title["text"] += content
            return
        if self.cur_colophon is not None and not self.note:
            self.cur_colophon["text"] += content
        if self.verse is None:
            return
        if self.note:
            self.verse["note_text"][-1] += content
            return
        if self.title:
            return
        red = "Jesus" in self.qstack
        self.verse["segs"].append((content, red, self.tc > 0, self.dn > 0))

    def flush(self):
        v = self.verse
        self.verse = None
        segs = v.pop("segs")
        text = norm("".join(s[0] for s in segs))
        # red text: join with markers to keep boundaries
        red_parts = []
        cur = ""
        for s, r, _, _ in segs:
            if r:
                cur += s
            else:
                if cur.strip():
                    red_parts.append(norm(cur))
                cur = ""
        if cur.strip():
            red_parts.append(norm(cur))
        black = norm("".join(s[0] for s in segs if not s[1]))
        italic = [norm(s[0]) for s in segs if s[2] and s[0].strip()]
        v["text"] = text
        v["red"] = red_parts
        v["any_red"] = bool(red_parts)
        v["red_words"] = sum(len(re.findall(r"[A-Za-z’']+", p)) for p in red_parts)
        v["words"] = len(re.findall(r"[A-Za-z’']+", text))
        v["black_words"] = len(re.findall(r"[A-Za-z’']+", black))
        v["all_red"] = v["any_red"] and v["black_words"] == 0
        v["italic"] = italic
        v["note_text"] = [norm(n) for n in v["note_text"]]
        v["cross"] = v.get("sid_parent") != v.get("eid_parent") or v.get("sid_depth") != v.get("eid_depth")
        v["kind"] = "verse"
        self.out.write(json.dumps(v, ensure_ascii=False) + "\n")


def main():
    src, dst = sys.argv[1], sys.argv[2]
    with open(dst, "w", encoding="utf-8") as out:
        p = xml.sax.make_parser()
        p.setFeature(xml.sax.handler.feature_namespaces, True)
        p.setContentHandler(H(out))
        p.parse(src)


if __name__ == "__main__":
    main()
