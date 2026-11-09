#!/usr/bin/env bash
# Nightly: commit the verse-title editor's changes to the OSIS source of truth, then push them.
#
# The editor writes assets/*.osis.xml on production at runtime. Until those writes are committed,
# a disk loss loses them and bin/deploy.sh refuses to deploy (it will not merge over uncommitted
# OSIS edits). The commit lands on the live tree's current branch; before the next deploy, merge
# that branch into the work branch (deploy.sh's --ff-only merge refuses otherwise, which is the
# signal).
#
# Pushing needs a WRITE-enabled deploy key for bkintanar/bible-app at $DEPLOY_KEY (an owner action;
# this host's default SSH identity cannot push). Without it the commit is still made locally and
# a warning is logged.
set -euo pipefail

APP="${APP:-/var/www/personal/bible-app}"
DEPLOY_KEY="${DEPLOY_KEY:-/root/.ssh/bible-app-deploy}"
log() { echo "$1"; logger -t bible-osis-commit -p "user.${2:-info}" -- "$1" 2>/dev/null || true; }

cd "$APP"

if git diff --quiet -- 'assets/*.osis.xml' && git diff --cached --quiet -- 'assets/*.osis.xml'; then
    log "no editor changes to commit"
else
    # Never commit a source of truth that does not parse (the editor validates before promoting
    # a write; this guards against anything else that touched the file).
    for f in $(git diff HEAD --name-only -- 'assets/*.osis.xml'); do
        php -r '
            libxml_use_internal_errors(true);
            $r = new XMLReader();
            if (! $r->open($argv[1])) { exit(1); }
            while (@$r->read()) {}
            foreach (libxml_get_errors() as $e) { if ($e->level === LIBXML_ERR_FATAL) { exit(1); } }
        ' "$f" || { log "refusing to commit $f: it does not parse as XML" err; exit 1; }
    done

    # The body lists the titles added (+) and removed (-), so the history is readable. A changed
    # diff line can carry other, unchanged titles: only titles on one side of the diff are listed.
    changes=$(git diff HEAD -U0 -- 'assets/*.osis.xml' | perl -ne '
        next if /^(\+\+\+|---)/;
        next unless /^([+-])/;
        my $side = $1;
        while (/<title type="verse"[^>]*>([^<]*)<\/title>\s*<verse osisID="([^"]+)"/g) {
            my ($t, $v) = ($1, $2);
            $t =~ s/&lt;/</g; $t =~ s/&gt;/>/g; $t =~ s/&quot;/"/g; $t =~ s/&apos;/\x27/g; $t =~ s/&amp;/&/g;
            $seen{$side}{"$v: $t"} = 1;
        }
        END {
            for my $s ("+", "-") {
                my $o = $s eq "+" ? "-" : "+";
                print "$s $_\n" for sort grep { !$seen{$o}{$_} } keys %{ $seen{$s} || {} };
            }
        }' | head -50 || true)

    git add -- 'assets/*.osis.xml'
    # An explicit automated identity: root has no git identity, and these commits should be
    # recognisable as the nightly job's in the history.
    git -c user.name='Bible App (nightly editor commit)' -c user.email='noreply@bible.b8.co.nz' \
        commit --quiet -m "content(osis): editor changes $(date +%F)" -m "${changes:-(see diff)}"
    log "committed $(git rev-parse --short HEAD): $(git show --stat --format= HEAD | tail -1)"
fi

branch=$(git symbolic-ref --short HEAD)

if [ -z "$(git rev-list "origin/$branch..HEAD" 2>/dev/null)" ] && git rev-parse -q --verify "origin/$branch" >/dev/null; then
    exit 0 # nothing unpushed
fi

if [ ! -r "$DEPLOY_KEY" ]; then
    log "not pushed: no deploy key at $DEPLOY_KEY - $(git rev-list --count "origin/$branch..HEAD" 2>/dev/null || echo '?') commit(s) on $branch are local only" warning
    exit 0
fi

GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o BatchMode=yes" \
    git push --quiet origin "HEAD:refs/heads/$branch"
log "pushed $branch to origin"
