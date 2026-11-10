#!/usr/bin/env python3
"""Fail (exit 1) when an axe-core CLI results file has serious or critical violations.

Usage: axe_gate.py results.json [more.json ...]
Prints every violation (all impacts) per page; only serious/critical fail the gate (plan 8.2).
"""
import json
import sys

failing = 0
for path in sys.argv[1:]:
    for page in json.load(open(path, encoding='utf-8')):
        violations = page.get('violations', [])
        print(f"{page['url']}: {len(violations)} violation(s)")
        for v in violations:
            print(f"  [{v['impact']}] {v['id']} x{len(v['nodes'])}: {v['nodes'][0]['target']}")
            if v['impact'] in ('serious', 'critical'):
                failing += 1

print(f"serious/critical: {failing}")
sys.exit(1 if failing else 0)
