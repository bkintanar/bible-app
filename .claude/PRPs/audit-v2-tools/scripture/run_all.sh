#!/usr/bin/env bash
# Read-only scripture-correctness validation. Usage: bash run_all.sh [/var/www/personal/bible-app]
set -euo pipefail
APP=${1:-/var/www/personal/bible-app}
D=$(cd "$(dirname "$0")" && pwd)
cd "$D"
python3 -I osis_truth.py "$APP/assets/kjv.osis.xml" kjv_truth.jsonl            # ground truth (1.3s)
php -d memory_limit=1G sim_render.php "$APP" sim.jsonl                          # real render code, all 31,102 verses
python3 -I compare_all.py kjv_truth.jsonl sim.jsonl "$APP/database/bible_app.sqlite" > report.json
python3 -I -c "import json;R=json.load(open('report.json'));[print(k,R[k]) for k in ['db_text','on_text','off_text','on_leaks','red_totals','italic','smallcaps']]"
python3 -I red_letters.py kjv_truth.jsonl sim.jsonl "$APP/database/bible_app.sqlite" John.3.16 Mark.4 Luke.6 Luke.15 John.14 John.17 Rev.2 Rev.3 | head -3
python3 -I q_nesting.py "$APP/assets/kjv.osis.xml" | head -3
# live (GET only, ~60 requests): python3 -I live_sample.py kjv_truth.jsonl sim.jsonl Gen.1 Ps.23 John.3 Matt.21 1Sam.1 --random 25 --seed 11
