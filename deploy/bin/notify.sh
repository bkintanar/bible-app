#!/usr/bin/env bash
# Tell the owner something failed (or succeeded). Usage: notify.sh <what> <status>
#
# NOTIFY_URL (and NOTIFY_SUCCESS_URL) come from /etc/default/bible-app-notify, which the OWNER creates,
# e.g. an ntfy topic URL (NOTIFY_URL=https://ntfy.sh/<topic>) or a healthchecks.io ping URL. Without
# it the message only reaches the journal (journalctl -t bible-notify).
set -uo pipefail

# As ExecStopPost=: notify only when the service did not stop cleanly (systemd sets SERVICE_RESULT).
# A Restart=always service never enters "failed", so OnFailure= cannot see its crashes.
if [ "${1:-}" = "--if-failed" ]; then
    shift
    [ "${SERVICE_RESULT:-success}" = success ] && exit 0
    set -- "${1:-unknown}" "stopped: ${SERVICE_RESULT} (${EXIT_CODE:-?} ${EXIT_STATUS:-?})"
fi

what=${1:-unknown}; status=${2:-failed}
msg="bible.b8.co.nz: ${what} ${status} on $(hostname) at $(date -Is)"
logger -t bible-notify -p "user.$([ "$status" = ok ] && echo info || echo err)" -- "$msg"

url=${NOTIFY_URL:-}
[ "$status" = ok ] && url=${NOTIFY_SUCCESS_URL:-}
[ -n "$url" ] && curl -fsS -m 10 --retry 2 -d "$msg" "$url" >/dev/null || true
