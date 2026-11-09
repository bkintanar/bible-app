#!/usr/bin/env python3
"""Per-verse red-letter status: OSIS truth vs simulated default render vs red_letter_text table.

Usage: python3 -I red_letters.py kjv_truth.jsonl sim.jsonl bible_app.sqlite [PASSAGE_PREFIX ...]
Writes red_status.tsv next to this script; prints totals, per-book table and per-passage checks.
Status: TP (red & should be), FN (Christ's words not red), FP (red, not Christ), PARTIAL (red span differs by words).
"""
import collections
import json
import os
import sqlite3
import sys

sys.path.insert(0, os.path.dirname(__file__))
from frag import parse_fragment, red_compare  # noqa: E402

truth = {}
for l in open(sys.argv[1], encoding="utf-8"):
    r = json.loads(l)
    if r["kind"] == "verse":
        truth[r["id"]] = r
sim = {json.loads(l)["id"]: json.loads(l) for l in open(sys.argv[2], encoding="utf-8")}
con = sqlite3.connect(f"file:{sys.argv[3]}?mode=ro", uri=True)
rlt = collections.Counter(v for (v,) in con.execute("select verse_id from red_letter_text"))

rows = []
per = collections.defaultdict(collections.Counter)
for osis, t in truth.items():
    s = sim[osis]
    on = parse_fragment(s["on"])
    tr, gr = t["any_red"], bool(on["red"])
    if tr and gr:
        u, o = red_compare(t["red"], on["red"])
        st = "PARTIAL" if (u or o) else "TP"
    elif tr:
        st, u, o = "FN", t["red_words"], 0
    elif gr:
        st, u, o = "FP", 0, sum(len(x.split()) for x in on["red"])
    else:
        continue
    tab = "Y" if rlt.get(s["vid"]) else "N"
    reason = ""
    if st == "FN":
        reason = "continuation(q opened in earlier verse)" if t["q_open_at_start"] and not s["xml_has_q"] else ("xml_has_q" if s["xml_has_q"] else "other")
    rows.append((osis, st, u, o, tab, reason))
    b = osis.split(".")[0]
    per[b][st] += 1
    per[b]["truth"] += int(tr)
    per[b]["under_words"] += u
    per[b]["table_rows"] += int(tab == "Y")

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "red_status.tsv")
with open(out, "w") as f:
    f.write("osis\tstatus\tunder_words\tover_words\tin_red_letter_text\tfn_reason\n")
    for r in rows:
        f.write("\t".join(map(str, r)) + "\n")
tot = collections.Counter()
for c in per.values():
    tot.update(c)
print("TOTAL", dict(tot))
print("FN reasons", collections.Counter(r[5] for r in rows if r[1] == "FN"))
for b, c in per.items():
    print(b, dict(c))
status = {r[0]: r for r in rows}
for p in sys.argv[4:]:
    ids = [i for i, t in truth.items() if (i == p or i.startswith(p + ".")) and t["any_red"]]
    fns = [i for i in ids if status[i][1] == "FN"]
    parts = [i for i in ids if status[i][1] == "PARTIAL"]
    print(f"{p}: christ_verses={len(ids)} FN={len(fns)} PARTIAL={len(parts)} e.g. FN {fns[:6]}")
