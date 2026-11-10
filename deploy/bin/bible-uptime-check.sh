#!/usr/bin/env bash
# Is the site up, through its public path (DNS -> Cloudflare -> tunnel -> nginx -> PHP-FPM -> app)?
# Run every 5 minutes by bible-uptime.timer. Alerts once when it goes down (UPTIME_THRESHOLD failed
# checks in a row, so a single blip stays quiet) and once when it recovers, via notify.sh.
#
# This runs ON the host it watches, so it cannot report the whole host being down; an external
# monitor (UptimeRobot, healthchecks.io, ...) pointed at the same URL covers that.
set -uo pipefail

URL=${UPTIME_URL:-https://bible.b8.co.nz/up}
THRESHOLD=${UPTIME_THRESHOLD:-2}
STATE="${STATE_DIRECTORY:-/var/lib/bible-uptime}/state" # "<consecutive failures> <alerted yes|no>"
NOTIFY="$(dirname "$(readlink -f "$0")")/notify.sh"

fails=0
alerted=no
if [ -r "$STATE" ]; then
    read -r fails alerted < "$STATE"
fi
case "$fails" in '' | *[!0-9]*) fails=0 ;; esac

code=$(curl -s -o /dev/null -m 20 -w '%{http_code}' -A 'bible-uptime-check' "$URL")

if [ "$code" = 200 ]; then
    if [ "$alerted" = yes ]; then
        "$NOTIFY" "$URL" "recovered (HTTP 200 after ${fails} failed checks)"
    fi
    echo "0 no" > "$STATE"
    exit 0
fi

fails=$((fails + 1))
logger -t bible-uptime -p user.warning -- "$URL answered HTTP ${code:-000} (${fails} failed check(s) in a row)"

if [ "$fails" -ge "$THRESHOLD" ] && [ "$alerted" != yes ]; then
    "$NOTIFY" "$URL" "down: HTTP ${code:-000} on ${fails} checks in a row"
    alerted=yes
fi

echo "$fails $alerted" > "$STATE"
