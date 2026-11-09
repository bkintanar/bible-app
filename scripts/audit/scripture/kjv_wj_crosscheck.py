#!/usr/bin/env python3
"""Cross-check red-letter coverage (words of Christ) in our OSIS source against eBible.org's red-letter KJV.

Reference: eBible.org KJV USFM (`\\wj ... \\wj*` = words of Jesus), downloaded once into ./ref/:
    curl -sSL -o ref/eng-kjv_usfm.zip https://ebible.org/Scriptures/eng-kjv_usfm.zip
    (unzip the *.usfm files into ref/usfm/)

Usage: python3 -I kjv_wj_crosscheck.py kjv_truth.jsonl [ref/usfm] [--examples N]
  kjv_truth.jsonl comes from osis_truth.py (per-verse 'red' = Christ's words in our source).

Differences are REPORTED for owner review, never applied: red-letter traditions can differ, and the
OSIS file is the source of truth. Exit 0 always (informational); the summary line is grep-able.
"""
import collections
import difflib
import glob
import json
import os
import re
import sys

truth_path = sys.argv[1]
ref_dir = sys.argv[2] if len(sys.argv) > 2 and not sys.argv[2].startswith("--") else os.path.join(os.path.dirname(os.path.abspath(__file__)), "ref", "usfm")
N_EX = int(sys.argv[sys.argv.index("--examples") + 1]) if "--examples" in sys.argv else 25

USFM2OSIS = {"MAT": "Matt", "MRK": "Mark", "LUK": "Luke", "JHN": "John", "ACT": "Acts", "1CO": "1Cor", "2CO": "2Cor",
             "REV": "Rev", "ROM": "Rom", "GAL": "Gal", "EPH": "Eph", "PHP": "Phil", "1TH": "1Thess", "1TI": "1Tim",
             "HEB": "Heb", "2PE": "2Pet"}
WORD = re.compile(r"[a-z]+")


def words(text):
    # Spelling-only variants between editions (Judæa/Judea/Judaea, Cæsar/Cesar) are not differences.
    t = text.lower().replace("’", "'").replace("æ", "e").replace("ae", "e")
    return WORD.findall(t)


def parse_usfm(path, osis_book):
    s = open(path, encoding="utf-8-sig").read()
    s = re.sub(r"\\f .*?\\f\*", " ", s, flags=re.S)           # footnotes
    s = re.sub(r"\\x .*?\\x\*", " ", s, flags=re.S)           # cross references
    s = re.sub(r"\\\+?w ([^|\\]*)\|[^\\]*\\\+?w\*", r"\1", s)  # \w word|attrs\w*  -> word
    s = re.sub(r"\\\+?(add|nd|k|qt|tl|sc|it|bd)\*?\s?", " ", s)  # inline character styles -> keep text
    chap = verse = None
    in_wj = False
    out = collections.defaultdict(lambda: {"all": [], "wj": []})
    for m in re.finditer(r"\\c (\d+)|\\v (\d+)|\\wj\*|\\wj\b|\\[a-z0-9]+\*?|[^\\]+", s):
        tok = m.group(0)
        if m.group(1):
            chap, verse, in_wj = int(m.group(1)), None, False
        elif m.group(2):
            verse = int(m.group(2))
        elif tok == "\\wj*":
            in_wj = False
        elif tok.startswith("\\wj"):
            in_wj = True
        elif tok.startswith("\\"):
            continue
        elif chap and verse:
            key = f"{osis_book}.{chap}.{verse}"
            ws = words(tok)
            out[key]["all"] += ws
            if in_wj:
                out[key]["wj"] += ws
    return out


ref = {}
for path in sorted(glob.glob(os.path.join(ref_dir, "*.usfm"))):
    code = re.sub(r"^\d+-", "", os.path.basename(path))[:3].upper()
    if code in USFM2OSIS:
        ref.update(parse_usfm(path, USFM2OSIS[code]))

truth = {}
for line in open(truth_path, encoding="utf-8"):
    r = json.loads(line)
    if r["kind"] == "verse":
        truth[r["id"]] = words(" ".join(r.get("red") or []))

ref_red = {k: v["wj"] for k, v in ref.items() if v["wj"]}
ours_red = {k: v for k, v in truth.items() if v}
only_ours = sorted(set(ours_red) - set(ref_red))
only_ref = sorted(set(ref_red) - set(ours_red))
word_diff = []
for k in sorted(set(ours_red) & set(ref_red)):
    a, b = ours_red[k], ref_red[k]
    if a != b:
        sm = difflib.SequenceMatcher(a=a, b=b, autojunk=False)
        extra = [" ".join(a[i1:i2]) for op, i1, i2, j1, j2 in sm.get_opcodes() if op in ("delete", "replace")]
        missing = [" ".join(b[j1:j2]) for op, i1, i2, j1, j2 in sm.get_opcodes() if op in ("insert", "replace")]
        word_diff.append((k, extra, missing))

print(f"reference verses with words of Jesus: {len(ref_red)}   ours: {len(ours_red)}")
print(f"SUMMARY only_ours={len(only_ours)} only_ref={len(only_ref)} word_diff={len(word_diff)}")
print(f"\n-- red in our source, not in reference ({len(only_ours)}):")
for k in only_ours[:N_EX]:
    print(f"  {k}: {' '.join(ours_red[k])[:120]}")
print(f"\n-- red in reference, not in our source ({len(only_ref)}):")
for k in only_ref[:N_EX]:
    print(f"  {k}: {' '.join(ref_red[k])[:120]}")
print(f"\n-- both red, words differ ({len(word_diff)}):")
for k, extra, missing in word_diff[:N_EX]:
    print(f"  {k}: ours-only={extra[:3]} ref-only={missing[:3]}")
