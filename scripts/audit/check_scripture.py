#!/usr/bin/env python3
"""Scripture gate: the projection and the default render must equal the OSIS source of truth.

Usage:  python3 -I scripts/audit/check_scripture.py [app_root] [db_path] [--allow-fp OSIS,OSIS,...]
  app_root  whose OSIS file + render code are checked   (default /var/www/personal/bible-app)
  db_path   which projection to check                    (default <app_root>/database/bible_app.sqlite)
  --allow-fp  red-letter false positives tolerated (e.g. pending owner decisions); default none

Read-only: the DB is copied with sqlite3 `.backup` from a -readonly connection into a temp dir;
every check runs against that copy. Exit 0 only if every target holds. Prints one line per metric.
"""
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
args = [a for a in sys.argv[1:] if not a.startswith("--")]
APP = args[0] if args else "/var/www/personal/bible-app"
DB = args[1] if len(args) > 1 else os.path.join(APP, "database", "bible_app.sqlite")
ALLOW_FP = set()
for i, a in enumerate(sys.argv):
    if a == "--allow-fp" and i + 1 < len(sys.argv):
        ALLOW_FP = set(x for x in sys.argv[i + 1].split(",") if x)
OSIS = os.path.join(APP, "assets", "kjv.osis.xml")

work = tempfile.mkdtemp(prefix="check-scripture-")
results = []  # (metric, value, target_desc, ok)


def run(cmd, **kw):
    return subprocess.run(cmd, check=True, capture_output=True, text=True, **kw).stdout


def gate(metric, value, ok, target="0"):
    results.append((metric, value, target, ok))


try:
    snap = os.path.join(work, "snap.sqlite")
    run(["sqlite3", "-readonly", DB, f".backup '{snap}'"])
    xdb = os.path.join(work, "xml_extract.sqlite")
    run([sys.executable, "-I", os.path.join(HERE, "data", "osis_extract.py"), OSIS, xdb])

    # --- data pipeline -------------------------------------------------------------
    trunc = run([sys.executable, "-I", os.path.join(HERE, "data", "truncation.py"), xdb, snap])
    n = int(re.search(r"full xml scripture text:\s*(\d+)", trunc).group(1))
    gate("db.text missing scripture words (truncation.py)", n, n == 0)

    cmp_out = run([sys.executable, "-I", os.path.join(HERE, "data", "compare.py"), xdb, snap, "1"])

    def cmp_count(pattern):
        m = re.search(pattern, cmp_out)
        return int(m.group(1)) if m else 0

    for label, pat in [
        ("db.text contains note text", r"db text CONTAINS note text\s+(\d+)"),
        ("db.text truncated (<80% of xml)", r"db text TRUNCATED \(<80% of xml\)\s+(\d+)"),
        ("formatted_text contains xmlns", r"formatted_text contains xmlns\s+(\d+)"),
        ("formatted_text contains raw OSIS tag", r"formatted_text contains raw OSIS tag\s+(\d+)"),
        ("titles missing from db", r"missing from db:\s*(\d+)"),
        ("titles extra in db", r"extra in db\s*:\s*(\d+)"),
        ("titles with wrong canonical flag", r"wrong canonical flag:\s*(\d+)"),
        ("chapters whose verse count differs", r"chapters whose verse count differs xml vs db:\s*(\d+)"),
        ("red_letter_text false-positive verses", r"FALSE POSITIVE verses \(db rows, no words of Christ in xml\):\s*(\d+)"),
    ]:
        v = cmp_count(pat)
        gate(label, v, v == 0)

    fts = run(["sqlite3", "-readonly", snap], input=open(os.path.join(HERE, "data", "fts_check.sql")).read())
    m = re.search(r"terms differing \(term,col,doc,cnt\) \| (\d+)", fts)
    v = int(m.group(1)) if m else -1
    gate("FTS index vs content: terms differing", v, v == 0)
    m = re.search(r"docs matching Heb \(note marker\) \| (\d+)", fts)
    v = int(m.group(1)) if m else -1
    gate("FTS docs matching note marker 'Heb'", v, v == 0)
    for label, pat in [("FTS docs matching note-only word 'midriff'", r"docs matching midriff \(note-only word\) \| (\d+)"),
                       ("FTS docs matching title CSS 'uppercase'", r"docs matching uppercase \(title CSS\) \| (\d+)")]:
        m = re.search(pat, fts)
        v = int(m.group(1)) if m else -1
        gate(label, v, v == 0)
    m = re.search(r'phrase "all the disciples forsook him" \| (\d+)', fts)
    v = int(m.group(1)) if m else -1
    gate("FTS finds Matt 26:56 'all the disciples forsook him'", v, v == 1, "1")

    # --- scripture pipeline (truth vs real render code) ----------------------------
    sd = os.path.join(HERE, "scripture")
    truth = os.path.join(work, "kjv_truth.jsonl")
    sim = os.path.join(work, "sim.jsonl")
    run([sys.executable, "-I", os.path.join(sd, "osis_truth.py"), OSIS, truth])
    run(["php", "-d", "memory_limit=1G", os.path.join(sd, "sim_render.php"), APP, sim, snap])
    rep = json.loads(run([sys.executable, "-I", os.path.join(sd, "compare_all.py"), truth, sim, snap]))

    for k in ("missing", "run_together", "extra", "changed", "spacing"):
        v = rep["on_text"].get(k, 0)
        gate(f"default render text: {k}", v, v == 0)
    for k in ("missing", "run_together", "extra", "changed", "spacing"):
        v = rep["db_text"].get(k, 0)
        gate(f"db.text vs truth: {k}", v, v == 0)
    leaks = rep["on_leaks"]
    gate("default render raw OSIS leaks", json.dumps(leaks, sort_keys=True), not leaks, "{}")

    for mode in ("on", "off"):
        t = rep["red_totals"].get(mode, {})
        gate(f"red letters ({mode}): false negatives", t.get("fn", 0), t.get("fn", 0) == 0)
        gate(f"red letters ({mode}): partial", t.get("partial", 0), t.get("partial", 0) == 0)
        fps = [e.split(":")[0] for e in rep["ex"].get(f"{mode}_fp", [])]
        bad = [f for f in fps if f not in ALLOW_FP]
        gate(f"red letters ({mode}): false positives", len(bad), not bad,
             "0" + (f" (allowed: {','.join(sorted(ALLOW_FP))})" if ALLOW_FP else ""))

    # informational (not gated): typography totals
    info = {"italic": rep["italic"], "smallcaps": rep["smallcaps"], "truth_red_verses": rep["truth_red_verses"]}
finally:
    shutil.rmtree(work, ignore_errors=True)

w = max(len(r[0]) for r in results)
for metric, value, target, ok in results:
    print(f"{'PASS' if ok else 'FAIL'}  {metric:<{w}}  {value}  (target {target})")
print("info:", json.dumps(info))
failed = [r for r in results if not r[3]]
print(f"\n{len(results) - len(failed)}/{len(results)} checks pass" + ("" if not failed else f" — {len(failed)} FAIL"))
sys.exit(1 if failed else 0)
