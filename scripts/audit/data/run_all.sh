#!/usr/bin/env bash
# Read-only data-integrity sweep: OSIS source vs SQLite projection.
# Usage: run_all.sh [repo_dir] [osis_file] [db_path]
# Takes a consistent snapshot via .backup from a -readonly connection, runs every check
# against the snapshot (temp-schema objects only), then deletes the snapshot.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="${1:-/var/www/personal/bible-app}"
OSIS="${2:-$REPO/assets/kjv.osis.xml}"
DB="${3:-$REPO/database/bible_app.sqlite}"
SNAP="$HERE/snap.$$.sqlite"
XDB="$HERE/xml_extract.$$.sqlite"
trap 'rm -f "$SNAP" "$SNAP"-shm "$SNAP"-wal "$XDB"' EXIT

echo "== live file checks (read-only)"
sqlite3 -readonly "$DB" "PRAGMA quick_check; PRAGMA integrity_check; PRAGMA journal_mode; PRAGMA page_count; PRAGMA freelist_count;"
echo "foreign_key_check rows: $(sqlite3 -readonly "$DB" 'PRAGMA foreign_key_check;' | wc -l)"
ls -la "$DB"-wal 2>/dev/null || true

sqlite3 -readonly "$DB" ".backup '$SNAP'"
python3 -I "$HERE/osis_extract.py" "$OSIS" "$XDB"

echo "== structural (orphans, duplicates, numbering)";  sqlite3 -readonly "$SNAP" < "$HERE/structural.sql"
echo "== FTS index vs content (temp reference index)";  sqlite3 -readonly "$SNAP" < "$HERE/fts_check.sql"
echo "== XML vs DB (text, titles, red letters, elements, paragraphs)"; python3 -I "$HERE/compare.py" "$XDB" "$SNAP" 1
echo "== truncated verses"; python3 -I "$HERE/truncation.py" "$XDB" "$SNAP"
