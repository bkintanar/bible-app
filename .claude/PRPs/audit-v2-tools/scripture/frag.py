"""Shared helpers: parse a rendered verse HTML fragment; tokenise; diff classify."""
import difflib
import re
from html.parser import HTMLParser

HTML_TAGS = set("""a abbr b br div em i mark p small span strong sub sup u""".split())
WORD = re.compile(r"[A-Za-z’'֐-׿]+")


def norm(s):
    return re.sub(r"\s+", " ", s).strip()


class Frag(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack = []
        self.segs = []
        self.leaks = []
        self.strongs = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag not in HTML_TAGS:
            self.leaks.append(tag)
        if tag == "br":
            return
        self.stack.append((tag, a))
        if "strong-word" in (a.get("class") or ""):
            self.strongs.append([a.get("data-strongs"), ""])

    def handle_startendtag(self, tag, attrs):
        if tag not in HTML_TAGS:
            self.leaks.append(tag + "/")

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                del self.stack[i:]
                return

    def handle_data(self, data):
        red = it = sc = False
        for tag, a in self.stack:
            cls = a.get("class") or ""
            if "text-red-" in cls:
                red = True
            if tag == "em":
                it = True
            if "small-caps" in (a.get("style") or ""):
                sc = True
        self.segs.append((data, red, it, sc))
        for tag, a in reversed(self.stack):
            if "strong-word" in (a.get("class") or ""):
                if self.strongs:
                    self.strongs[-1][1] += data
                break


def parse_fragment(html):
    f = Frag()
    f.feed(html or "")
    f.close()
    return summarise(f.segs, f.leaks, f.strongs)


def summarise(segs, leaks, strongs):
    text = norm("".join(s[0] for s in segs))
    red, cur = [], ""
    for s, r, _, _ in segs:
        if r:
            cur += s
        else:
            if cur.strip():
                red.append(norm(cur))
            cur = ""
    if cur.strip():
        red.append(norm(cur))
    return {
        "text": text,
        "red": red,
        "italic": [norm(s[0]) for s in segs if s[2] and s[0].strip()],
        "smallcaps": [norm(s[0]) for s in segs if s[3] and s[0].strip()],
        "leaks": leaks,
        "strongs": [[a, norm(b)] for a, b in strongs],
    }


def tokens(s):
    return re.findall(r"[\w’'֐-׿]+|[^\w\s]", s)


def words(s):
    return WORD.findall(s)


def classify(truth, got):
    """Return dict of error classes between truth text and rendered text."""
    errs = {}
    tt, gt = tokens(truth), tokens(got)
    sm = difflib.SequenceMatcher(a=tt, b=gt, autojunk=False)
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op == "equal":
            continue
        a, b = tt[i1:i2], gt[j1:j2]
        if op == "replace" and "".join(a) == "".join(b):
            errs.setdefault("run_together", []).append(f"{' '.join(a)} -> {''.join(b)}")
        elif op == "delete":
            errs.setdefault("missing", []).append(" ".join(a))
        elif op == "insert":
            errs.setdefault("extra", []).append(" ".join(b))
        else:
            errs.setdefault("changed", []).append(f"{' '.join(a)} -> {' '.join(b)}")
    if not errs and norm(truth) != norm(got):
        # same tokens, spacing differs
        sp = []
        for m in re.finditer(r"\s([,.;:!?])", got):
            if not re.search(re.escape(got[max(0, m.start() - 6):m.end()]), truth):
                sp.append(got[max(0, m.start() - 8):m.end() + 4])
        errs["spacing"] = sp or [f"{norm(got)[:80]}"]
    return errs


def red_compare(truth_red, got_red):
    tw = words(" ".join(truth_red))
    gw = words(" ".join(got_red))
    sm = difflib.SequenceMatcher(a=tw, b=gw, autojunk=False)
    under = over = 0
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op in ("delete", "replace"):
            under += i2 - i1
        if op in ("insert", "replace"):
            over += j2 - j1
    return under, over
