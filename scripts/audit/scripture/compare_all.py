#!/usr/bin/env python3
"""Compare OSIS ground truth against DB columns and the simulated render (both Strong's modes).

Usage: python3 -I compare_all.py kjv_truth.jsonl sim.jsonl /path/to/bible_app.sqlite > report.json
"""
import collections
import json
import re
import sqlite3
import sys

sys.path.insert(0, __import__("os").path.dirname(__file__))
from frag import classify, parse_fragment, red_compare, norm, words  # noqa: E402

truth = {}
for line in open(sys.argv[1], encoding="utf-8"):
    r = json.loads(line)
    if r["kind"] == "verse":
        truth[r["id"]] = r
sim = {}
for line in open(sys.argv[2], encoding="utf-8"):
    r = json.loads(line)
    sim[r["id"]] = r
con = sqlite3.connect(f"file:{sys.argv[3]}?mode=ro", uri=True)
db = {}
for osis, vid, text, ft in con.execute("select osis_id, id, text, formatted_text from verses"):
    db[osis] = {"vid": vid, "text": text, "ft": ft}
rlt = collections.Counter(v for (v,) in con.execute("select verse_id from red_letter_text"))

book = lambda o: o.split(".")[0]  # noqa: E731
R = {"n": len(truth), "db_text": collections.Counter(), "db_text_ex": collections.defaultdict(list),
     "on_text": collections.Counter(), "on_text_ex": collections.defaultdict(list),
     "off_text": collections.Counter(), "off_text_ex": collections.defaultdict(list),
     "on_leaks": collections.Counter(), "on_leak_verses": collections.Counter(), "on_leak_ex": collections.defaultdict(list),
     "off_leaks": collections.Counter(),
     "red": {}, "italic": collections.Counter(), "smallcaps": collections.Counter(), "ex": collections.defaultdict(list),
     "missing_in_db": [t for t in truth if t not in db]}

per_book_red = {m: collections.defaultdict(lambda: collections.Counter()) for m in ("on", "off", "table")}


def add(counter, exd, key, osis, detail, cap=6):
    counter[key] += 1
    if len(exd[key]) < cap:
        exd[key].append(f"{osis}: {detail}")


for osis, t in truth.items():
    if osis not in db:
        continue
    d, s = db[osis], sim[osis]
    # --- DB text column
    for k, v in classify(t["text"], d["text"]).items():
        add(R["db_text"], R["db_text_ex"], k, osis, "; ".join(v)[:160])
    # --- Strong's ON (default) render
    on = parse_fragment(s["on"])
    for k, v in classify(t["text"], on["text"]).items():
        add(R["on_text"], R["on_text_ex"], k, osis, "; ".join(v)[:160])
    if on["leaks"]:
        R["on_leak_verses"][osis.split(".")[0]] += 1
        for lk in set(on["leaks"]):
            R["on_leaks"][lk] += 1
            if len(R["on_leak_ex"][lk]) < 6:
                R["on_leak_ex"][lk].append(osis)
    # --- Strong's OFF render (approximation of applyRedLetterFormatting)
    off_html = s["off"]
    if not s["xml_has_q"] and rlt.get(s["vid"]):
        off_html = '<span class="text-red-600">' + off_html + "</span>"
    off = parse_fragment(off_html)
    for k, v in classify(t["text"], off["text"]).items():
        add(R["off_text"], R["off_text_ex"], k, osis, "; ".join(v)[:160])
    for lk in set(off["leaks"]):
        R["off_leaks"][lk] += 1
    # --- red letters
    for mode, got_red in (("on", on["red"]), ("off", off["red"]), ("table", ["x"] if rlt.get(s["vid"]) else [])):
        pb = per_book_red[mode][book(osis)]
        tr, gr = t["any_red"], bool(got_red)
        if tr and gr:
            pb["tp"] += 1
            if mode != "table":
                under, over = red_compare(t["red"], got_red)
                if under or over:
                    pb["partial"] += 1
                    pb["under_words"] += under
                    pb["over_words"] += over
                    if len(R["ex"][f"{mode}_partial"]) < 12:
                        R["ex"][f"{mode}_partial"].append(f"{osis}: under={under} over={over} truth_red={t['red']} got_red={got_red}"[:400])
        elif tr and not gr:
            pb["fn"] += 1
            if len(R["ex"][f"{mode}_fn"]) < 400:
                R["ex"][f"{mode}_fn"].append(osis)
        elif gr and not tr:
            pb["fp"] += 1
            R["ex"][f"{mode}_fp"].append(f"{osis}: {got_red}"[:200] if mode != "table" else osis)
    # --- typography
    ti = len(t["italic"])
    oi = len(on["italic"])
    R["italic"]["truth_segments"] += t["tc"]
    R["italic"]["on_segments"] += oi
    if t["tc"] and not oi:
        R["italic"]["on_verse_missing_all"] += 1
        if len(R["ex"]["italic_missing"]) < 8:
            R["ex"]["italic_missing"].append(osis)
    R["smallcaps"]["truth"] += t["dn"]
    R["smallcaps"]["on"] += len(on["smallcaps"])
    if t["dn"] and not on["smallcaps"]:
        R["smallcaps"]["on_verse_missing"] += 1
        if len(R["ex"]["smallcaps_missing"]) < 8:
            R["ex"]["smallcaps_missing"].append(osis)
    # verse-level cross-container text truncation
    if t["cross"] and words(t["text"]) != words(d["text"]):
        if len(R["ex"]["cross_text_diff"]) < 12:
            R["ex"]["cross_text_diff"].append(osis)
        R["italic"]["cross_text_diff"] += 1

R["red"] = {m: {b: dict(c) for b, c in pb.items()} for m, pb in per_book_red.items()}
R["red_totals"] = {}
for m, pb in per_book_red.items():
    tot = collections.Counter()
    for c in pb.values():
        tot.update(c)
    R["red_totals"][m] = dict(tot)
R["truth_red_verses"] = sum(1 for t in truth.values() if t["any_red"])
R["truth_red_by_book"] = dict(collections.Counter(book(o) for o, t in truth.items() if t["any_red"]))
json.dump(R, sys.stdout, ensure_ascii=False, indent=1, default=list)
