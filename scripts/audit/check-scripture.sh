#!/usr/bin/env bash
# Scripture gate wrapper. Usage: bash scripts/audit/check-scripture.sh [app_root] [db_path] [--allow-fp A,B]
# Exits non-zero unless the projection and the default render equal the OSIS source (see check_scripture.py).
exec python3 -I "$(cd "$(dirname "$0")" && pwd)/check_scripture.py" "$@"
