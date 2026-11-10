#!/usr/bin/env bash
# Tell the owner something failed, recovered, or succeeded. Usage: notify.sh <what> <status>
#
# NOTIFY_URL (and NOTIFY_SUCCESS_URL) come from /etc/default/bible-app-notify (root-only), e.g. an
# ntfy topic URL (NOTIFY_URL=https://ntfy.sh/<topic>) or a healthchecks.io ping URL. Without it the
# message only reaches the journal (journalctl -t bible-notify).
#
#   status "ok"            -> NOTIFY_SUCCESS_URL only (a heartbeat, e.g. healthchecks.io)
#   status "recovered ..." -> NOTIFY_URL, as good news
#   anything else          -> NOTIFY_URL, as a failure
#
# The Title/Priority/Tags headers are ntfy's (https://docs.ntfy.sh/publish/); other endpoints ignore them.
set -uo pipefail

what=${1:-unknown}; status=${2:-failed}
msg="bible.b8.co.nz: ${what} ${status} on $(hostname) at $(date -Is)"

url=${NOTIFY_URL:-}
case "$status" in
    ok)          level=info; url=${NOTIFY_SUCCESS_URL:-}; priority=low;  tags=white_check_mark ;;
    recovered*)  level=info;                              priority=default; tags=white_check_mark ;;
    *)           level=err;                               priority=high; tags=warning ;;
esac

logger -t bible-notify -p "user.${level}" -- "$msg"

[ -n "$url" ] && curl -fsS -m 10 --retry 2 \
    -H "Title: bible.b8.co.nz" -H "Priority: ${priority}" -H "Tags: ${tags}" \
    -d "$msg" "$url" >/dev/null || true
