#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID == 0 ]] || { printf 'Futtasd sudo-val.\n' >&2; exit 1; }
ROOT=/opt/buksza
[[ -f "$ROOT/current/release.env" ]] || { printf 'Nincs aktív Buksza-kiadás.\n'; exit 1; }
# Root-owned, installer-generated values; contains no application secret.
set -a
# shellcheck disable=SC1091
. "$ROOT/current/release.env"
set +a
OPTIONS=(-p buksza -f "$ROOT/current/docker/production/compose.yaml")
if [[ "${BUKSZA_PROXY_MODE:-caddy}" == nginx ]]; then OPTIONS+=(-f "$ROOT/current/docker/production/compose.host-nginx.yaml"); fi
docker compose "${OPTIONS[@]}" ps
if [[ "${BUKSZA_PROXY_MODE:-caddy}" == nginx ]]; then
    nginx -t
    docker compose "${OPTIONS[@]}" logs --tail=30 web scheduler
    systemctl list-timers buksza-cert-renew.timer --no-pager
else
    docker compose "${OPTIONS[@]}" logs --tail=30 caddy scheduler
fi
systemctl list-timers buksza-backup.timer --no-pager
systemctl show buksza-backup.service --property=ActiveState,Result,ExecMainStatus,ExecMainExitTimestamp --no-pager
for report in backup.json last-success.json scheduled-success.json; do
    if [[ -f "$ROOT/status/$report" ]]; then
        printf '\n[Buksza] %s\n' "$report"
        cat -- "$ROOT/status/$report"
    fi
done
