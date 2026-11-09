#!/usr/bin/env python3
"""Extract per-verse rendered text/red/italic/smallcaps/strongs from a live chapter page.

Usage: python3 -I html_extract.py page.html BOOK CHAPTER > out.json
Only the .page-current block is parsed (the page also embeds prev/next chapters).
"""
import json
import re
import sys
from html.parser import HTMLParser

VOID = {"br", "img", "hr", "meta", "link", "input", "source", "wbr", "area", "base", "col", "embed", "param", "track"}
HTML_TAGS = set("""a abbr address article aside b bdi bdo blockquote body br button canvas caption cite code col colgroup
data datalist dd del details dfn dialog div dl dt em fieldset figcaption figure footer form h1 h2 h3 h4 h5 h6 head header
hr html i iframe img input ins kbd label legend li link main mark meta nav noscript object ol optgroup option output p path
picture pre progress q rp rt ruby s samp script section select small source span strong style sub summary sup svg circle
table tbody td template textarea tfoot th thead time title tr u ul var video wbr g line rect polyline polygon defs use
""".split())


class P(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack = []
        self.in_current = None  # depth at which page-current div opened
        self.verses = {}
        self.order = []
        self.cur = None  # (num, depth)
        self.leaks = []
        self.titles = []
        self.cur_title = None
        self.last_verse = None

    def classes(self, attrs):
        return dict(attrs).get("class", "") or ""

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        cls = a.get("class", "") or ""
        if tag not in VOID:
            self.stack.append((tag, a))
        depth = len(self.stack)
        if self.in_current is None and tag == "div" and "page-current" in cls.split():
            self.in_current = depth
            return
        if self.in_current is None:
            return
        if tag not in HTML_TAGS:
            self.leaks.append((self.cur[0] if self.cur else None, tag, dict(attrs)))
        vid = a.get("id", "") or ""
        m = re.fullmatch(r"verse-(\d+)", vid)
        if m and self.cur is None:
            n = int(m.group(1))
            self.cur = (n, depth if tag not in VOID else None)
            self.verses.setdefault(n, {"segs": [], "strongs": [], "num_shown": None})
            self.order.append(n)
            self.last_verse = n
        if self.cur_title is None and tag == "div" and any(c in cls for c in ("verse-title", "psalm-title", "acrostic-title", "chapter-title", "main-title")):
            self.cur_title = {"cls": cls.split()[0], "text": "", "depth": depth, "after_verse": self.last_verse}
        if self.cur and "strong-word" in cls:
            self.verses[self.cur[0]]["strongs"].append([a.get("data-strongs"), ""])

    def handle_startendtag(self, tag, attrs):
        if self.in_current is not None and tag not in HTML_TAGS:
            self.leaks.append((self.cur[0] if self.cur else None, tag, dict(attrs)))

    def handle_endtag(self, tag):
        if tag in VOID:
            return
        # pop to matching
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                depth = i + 1
                if self.cur and self.cur[1] == depth:
                    self.cur = None
                if self.cur_title and self.cur_title["depth"] == depth:
                    t = self.cur_title
                    t["text"] = re.sub(r"\s+", " ", t["text"]).strip()
                    del t["depth"]
                    self.titles.append(t)
                    self.cur_title = None
                if self.in_current is not None and depth == self.in_current:
                    self.in_current = None
                    self.done = True
                del self.stack[i:]
                return

    def handle_data(self, data):
        if self.in_current is None:
            return
        if self.cur_title is not None:
            self.cur_title["text"] += data
        if not self.cur:
            return
        v = self.verses[self.cur[0]]
        red = it = sc = num = False
        for tag, a in self.stack:
            cls = a.get("class", "") or ""
            st = a.get("style", "") or ""
            if "text-red-" in cls:
                red = True
            if tag == "em":
                it = True
            if "small-caps" in st:
                sc = True
            if "verse-number" in cls:
                num = True
        if num:
            v["num_shown"] = (v["num_shown"] or "") + data.strip()
            return
        v["segs"].append((data, red, it, sc))
        # attribute data to innermost strong-word
        for tag, a in reversed(self.stack):
            if "strong-word" in (a.get("class", "") or ""):
                if v["strongs"]:
                    v["strongs"][-1][1] += data
                break


def norm(s):
    return re.sub(r"\s+", " ", s).strip()


def main():
    html = open(sys.argv[1], encoding="utf-8").read()
    p = P()
    p.feed(html)
    out = {"verses": {}, "leaks": p.leaks, "titles": p.titles, "order": p.order}
    for n, v in p.verses.items():
        segs = v["segs"]
        text = norm("".join(s[0] for s in segs))
        red = []
        cur = ""
        for s, r, _, _ in segs:
            if r:
                cur += s
            else:
                if cur.strip():
                    red.append(norm(cur))
                cur = ""
        if cur.strip():
            red.append(norm(cur))
        out["verses"][n] = {
            "text": text,
            "red": red,
            "italic": [norm(s[0]) for s in segs if s[2] and s[0].strip()],
            "smallcaps": [norm(s[0]) for s in segs if s[3] and s[0].strip()],
            "num_shown": v["num_shown"],
            "strongs": [[a, norm(b)] for a, b in v["strongs"]],
        }
    json.dump(out, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
