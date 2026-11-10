#!/usr/bin/env python3
"""Fetch chapters from the LIVE site (GET only, sequential) and compare rendered verses to OSIS truth + simulation.

Usage: python3 -I live_sample.py kjv_truth.jsonl sim.jsonl [--random N] [--seed S] [Book.Chapter ...]
Env: HOST (default bible.b8.co.nz), BASE (default http://127.0.0.1)
Saves pages under ./html/, prints per-chapter summary and totals.
"""
import collections
import json
import os
import random
import re
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from frag import classify, red_compare  # noqa: E402

HOST = os.environ.get("HOST", "bible.b8.co.nz")
BASE = os.environ.get("BASE", "http://127.0.0.1")

args = sys.argv[3:]
nrand, seed, chaps = 0, 7, []
i = 0
while i < len(args):
    if args[i] == "--random":
        nrand = int(args[i + 1]); i += 2
    elif args[i] == "--seed":
        seed = int(args[i + 1]); i += 2
    else:
        chaps.append(args[i]); i += 1

truth, chapter_ids = {}, []
for l in open(sys.argv[1], encoding="utf-8"):
    r = json.loads(l)
    if r["kind"] == "verse":
        truth[r["id"]] = r
    elif r["kind"] == "chapter":
        chapter_ids.append(r["id"])
sim = {json.loads(l)["id"]: json.loads(l) for l in open(sys.argv[2], encoding="utf-8")}
if nrand:
    rnd = random.Random(seed)
    pool = [c for c in chapter_ids if not re.match(r"^\d", c) and c not in chaps]
    chaps += rnd.sample(pool, nrand)

os.makedirs(os.path.join(HERE, "html"), exist_ok=True)
T = collections.Counter()
EX = collections.defaultdict(list)
for ch in chaps:
    b, c = ch.split(".")
    path = os.path.join(HERE, "html", f"{b}_{c}.html")
    code = subprocess.run(["curl", "-s", "-o", path, "-w", "%{http_code}", "-H", f"Host: {HOST}", f"{BASE}/books/{b}/chapters/{c}"],
                          capture_output=True, text=True).stdout
    if code != "200":
        print(f"{ch}: HTTP {code}")
        T["http_non200"] += 1
        continue
    out = subprocess.run([sys.executable, "-I", os.path.join(HERE, "html_extract.py"), path, b, c], capture_output=True, text=True).stdout
    page = json.loads(out)
    expected = sorted(int(k.split(".")[2]) for k in truth if k.startswith(f"{b}.{c}."))
    got = sorted(int(k) for k in page["verses"])
    cs = collections.Counter()
    if got != expected:
        cs["verse_set_mismatch"] += 1
        EX["verse_set_mismatch"].append(f"{ch}: missing={sorted(set(expected)-set(got))[:10]} extra={sorted(set(got)-set(expected))[:10]}")
    for n in got:
        osis = f"{b}.{c}.{n}"
        if osis not in truth:
            continue
        t, v = truth[osis], page["verses"][str(n)]
        cs["verses"] += 1
        if n != 1 and v["num_shown"] != str(n):
            cs["verse_number_wrong"] += 1
            EX["verse_number_wrong"].append(f"{osis}: shown={v['num_shown']}")
        errs = classify(t["text"], v["text"])
        for k, lst in errs.items():
            cs["text_" + k] += 1
            if len(EX["text_" + k]) < 10:
                EX["text_" + k].append(f"{osis}: {'; '.join(lst)[:150]}")
        # sim agreement: the live page (Strong's off by default, OD-3) against the simulated Strong's-ON
        # render - text and red letters are the same on both paths (run_all checks db/on/off)
        from frag import parse_fragment
        sv = parse_fragment(sim[osis]["on"])
        if sv["text"] != v["text"] or sv["red"] != v["red"]:
            cs["sim_disagree"] += 1
            if len(EX["sim_disagree"]) < 10:
                EX["sim_disagree"].append(f"{osis}: live={v['text'][:60]!r} sim={sv['text'][:60]!r}")
        tr, gr = t["any_red"], bool(v["red"])
        if tr and gr:
            u, o = red_compare(t["red"], v["red"])
            cs["red_tp"] += 1
            if u or o:
                cs["red_partial"] += 1
        elif tr:
            cs["red_fn"] += 1
            if len(EX["red_fn"]) < 30:
                EX["red_fn"].append(osis)
        elif gr:
            cs["red_fp"] += 1
            EX["red_fp"].append(f"{osis}: {v['red']}")
        if t["tc"] and len(v["italic"]) < t["tc"]:
            cs["italic_short"] += 1
            EX["italic_short"].append(f"{osis}: truth={t['tc']} live={len(v['italic'])}")
        if t["dn"] and len(v["smallcaps"]) < t["dn"]:
            cs["smallcaps_short"] += 1
            EX["smallcaps_short"].append(f"{osis}: truth={t['dn']} live={len(v['smallcaps'])}")
    lk = collections.Counter(l[1] for l in page["leaks"])
    titles = [(x["cls"], x["text"], x["after_verse"]) for x in page["titles"]]
    T.update(cs)
    T["leaks"] += sum(lk.values())
    print(f"{ch}: " + ", ".join(f"{k}={v}" for k, v in sorted(cs.items())) + (f" leaks={dict(lk)}" if lk else "") + (f" titles={titles[:4]}" if titles else ""))
print("TOTAL", dict(T), "chapters", len(chaps))
for k, v in EX.items():
    print("EX", k, v[:10])
