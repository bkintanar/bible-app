#!/bin/bash
# usage: timing.sh <label> <path> <runs>
# 1 cold run (no cookie, writes jar) + N warm runs (reusing jar). Prints per-run TTFB/total/bytes/status.
label=$1; path=$2; runs=${3:-5}
jar=$(mktemp -p "$(dirname "$0")" jar.XXXX)
fmt='%{http_code} %{time_starttransfer} %{time_total} %{size_download}\n'
H=(-H 'Host: bible.b8.co.nz' -A 'perf-audit-curl')
printf '%s COLD ' "$label"; curl -s -o /dev/null -c "$jar" -w "$fmt" "${H[@]}" "http://127.0.0.1$path"
for i in $(seq 1 $runs); do printf '%s WARM ' "$label"; curl -s -o /dev/null -b "$jar" -c "$jar" -w "$fmt" "${H[@]}" "http://127.0.0.1$path"; done
rm -f "$jar"
